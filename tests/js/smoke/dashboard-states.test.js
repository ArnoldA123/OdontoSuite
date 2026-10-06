import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { createRouter, createMemoryHistory } from 'vue-router'

// T3 regression coverage: the dashboard must render a dedicated error state
// when the stats request fails (instead of silently showing empty content),
// retry from that state, keep the agenda usable when only the today request
// fails, and expose a manual refresh control that never blanks the page.
const { getMock } = vi.hoisted(() => ({ getMock: vi.fn() }))

vi.mock('../../../resources/js/composables/useApi', () => ({
  useApi: () => ({
    get: getMock,
    post: async () => ({ data: {} }),
    put: async () => ({ data: {} }),
    patch: async () => ({ data: {} }),
    delete: async () => ({ data: {} }),
    setToken: () => {},
    normalizeError: () => ''
  })
}))
vi.mock('../../../resources/js/composables/useEcho', () => ({
  useEcho: () => ({
    echo: {},
    channel: () => ({}),
    privateChannel: () => ({}),
    connectionStatus: { value: 'connected' }
  })
}))
vi.mock('../../../resources/js/composables/useWebSocketNotifications', () => ({
  useWebSocketNotifications: () => ({})
}))

import uiComponents from '../../../resources/js/plugins/ui-components'
import Page from '../../../resources/js/modules/dashboard/DashboardPage.vue'

const makeAppointment = id => ({
  id,
  scheduled_at: `2026-10-04T${String(8 + id).padStart(2, '0')}:30:00-05:00`,
  status: 'confirmed',
  patient: {
    id: 100 + id,
    full_name: `Paciente ${id}`,
    first_name: 'Paciente',
    last_name: `${id}`
  },
  appointment_type: { id: 3, name: 'Limpieza dental' },
  user: { id: 2, name: 'Dra. Carla Núñez' }
})

const signInAs = role => {
  localStorage.setItem('auth_token', 'test-token')
  localStorage.setItem('user', JSON.stringify({ id: 1, name: 'Ana Admin', role }))
}

const mountDashboard = async () => {
  const router = createRouter({
    history: createMemoryHistory(),
    routes: [{ path: '/:pathMatch(.*)*', component: { template: '<div />' } }]
  })
  await router.push('/dashboard')
  await router.isReady()

  const wrapper = mount(Page, { global: { plugins: [router, uiComponents] } })
  await flushPromises()
  await flushPromises()
  return { wrapper, router }
}

const statsCalls = () => getMock.mock.calls.filter(([url]) => url === '/api/dashboard/stats')
const todayCalls = () =>
  getMock.mock.calls.filter(([url]) => url === '/api/dashboard/appointments-today')
const pendingCalls = () => getMock.mock.calls.filter(([url]) => url === '/api/dashboard/pending')

describe('dashboard load states (T3)', () => {
  beforeEach(() => {
    signInAs('administrador')
    getMock.mockReset()
  })

  it('renders the stats error state with a Reintentar action when stats fails', async () => {
    getMock.mockImplementation(async url => {
      if (url === '/api/dashboard/stats') {
        throw { status: 500, response: { data: { message: 'boom' } } }
      }
      if (url === '/api/dashboard/appointments-today') return { data: [makeAppointment(1)] }
      return { data: [] }
    })

    const { wrapper } = await mountDashboard()

    const error = wrapper.find('[data-state="error-stats"]')
    expect(error.exists()).toBe(true)
    expect(error.attributes('role')).toBe('alert')
    expect(error.text()).toContain('No pudimos cargar el resumen')
    expect(error.text()).toContain('Reintentar')
    expect(error.find('svg').exists()).toBe(true)

    // The failed stats must not be rendered as stale/empty content.
    expect(wrapper.find('[data-stat-card="appointments-today"]').exists()).toBe(false)
    expect(wrapper.find('[data-appointment-row]').exists()).toBe(false)

    wrapper.unmount()
  })

  it('re-issues the full load path when Reintentar is clicked and clears the error', async () => {
    let attempt = 0
    getMock.mockImplementation(async url => {
      if (url === '/api/dashboard/stats') {
        attempt += 1
        if (attempt === 1) throw { status: 500 }
        return { data: { appointments_today: 6 } }
      }
      if (url === '/api/dashboard/appointments-today') return { data: [] }
      return { data: [] }
    })

    const { wrapper } = await mountDashboard()
    expect(wrapper.find('[data-state="error-stats"]').exists()).toBe(true)

    await wrapper.find('[data-retry-stats]').trigger('click')
    await flushPromises()
    await flushPromises()

    expect(statsCalls()).toHaveLength(2)
    expect(todayCalls()).toHaveLength(2)
    expect(pendingCalls()).toHaveLength(2)
    expect(wrapper.find('[data-state="error-stats"]').exists()).toBe(false)
    expect(wrapper.find('[data-stat-card="appointments-today"]').text()).toContain('6')

    wrapper.unmount()
  })

  it('shows the agenda error inline and retries only the today resource', async () => {
    let attempt = 0
    getMock.mockImplementation(async url => {
      if (url === '/api/dashboard/stats') return { data: { appointments_today: 4 } }
      if (url === '/api/dashboard/appointments-today') {
        attempt += 1
        if (attempt === 1) throw { status: 500 }
        return { data: [makeAppointment(1)] }
      }
      return { data: [] }
    })

    const { wrapper } = await mountDashboard()

    const agenda = wrapper.find('section[aria-label="Agenda de hoy"]')
    const inlineError = agenda.find('[data-state="error-appointments"]')
    expect(inlineError.exists()).toBe(true)
    expect(inlineError.attributes('role')).toBe('alert')
    expect(inlineError.text()).toContain('Reintentar')

    // The rest of the page stays usable.
    expect(wrapper.find('[data-stat-card="appointments-today"]').text()).toContain('4')
    expect(wrapper.find('[data-action="patients"]').exists()).toBe(true)

    await wrapper.find('[data-retry-appointments]').trigger('click')
    await flushPromises()
    await flushPromises()

    expect(attempt).toBe(2)
    expect(statsCalls()).toHaveLength(1)
    expect(pendingCalls()).toHaveLength(1)
    expect(wrapper.find('[data-state="error-appointments"]').exists()).toBe(false)
    expect(wrapper.find('[data-appointment-row]').exists()).toBe(true)

    wrapper.unmount()
  })

  it('refresh control reloads without unmounting the current content', async () => {
    getMock.mockImplementation(async url => {
      if (url === '/api/dashboard/stats') return { data: { appointments_today: 2 } }
      if (url === '/api/dashboard/appointments-today') return { data: [] }
      return { data: [] }
    })

    const { wrapper } = await mountDashboard()

    const refresh = wrapper.find('[data-refresh-button]')
    expect(refresh.exists()).toBe(true)
    expect(refresh.attributes('aria-label')).toBe('Actualizar')
    expect(wrapper.find('[data-stat-card="appointments-today"]').text()).toContain('2')

    let resolveStats
    getMock.mockImplementation(async url => {
      if (url === '/api/dashboard/stats') {
        return new Promise(resolve => {
          resolveStats = resolve
        })
      }
      if (url === '/api/dashboard/appointments-today') return { data: [] }
      return { data: [] }
    })

    await wrapper.find('[data-refresh-button]').trigger('click')
    await flushPromises()

    // In flight: the current content stays mounted and the page reports busy.
    expect(wrapper.find('[aria-label="Cargando resumen"]').exists()).toBe(false)
    expect(wrapper.find('[data-stat-card="appointments-today"]').exists()).toBe(true)
    expect(wrapper.find('[data-dashboard-content]').attributes('aria-busy')).toBe('true')

    resolveStats({ data: { appointments_today: 7 } })
    await flushPromises()
    await flushPromises()

    expect(wrapper.find('[data-stat-card="appointments-today"]').text()).toContain('7')
    expect(wrapper.find('[data-dashboard-content]').attributes('aria-busy')).toBe('false')
    expect(wrapper.find('[aria-label="Cargando resumen"]').exists()).toBe(false)
    // T7b: the manual refresh re-issues the fourth dashboard resource too.
    expect(pendingCalls()).toHaveLength(2)

    wrapper.unmount()
  })

  it('still redirects to /login when the stats request returns 401', async () => {
    getMock.mockImplementation(async () => {
      throw { status: 401, response: { data: { message: 'Sesión expirada' } } }
    })

    const { wrapper, router } = await mountDashboard()
    await flushPromises()

    expect(router.currentRoute.value.path).toBe('/login')

    wrapper.unmount()
  })

  it.each([
    ['404', 404],
    ['401', 401]
  ])('degrades the agenda to empty on a today %s response', async (_label, status) => {
    getMock.mockImplementation(async url => {
      if (url === '/api/dashboard/stats') return { data: {} }
      if (url === '/api/dashboard/appointments-today') throw { status }
      return { data: [] }
    })

    const { wrapper } = await mountDashboard()

    expect(wrapper.find('[data-state="error-appointments"]').exists()).toBe(false)
    expect(wrapper.find('[data-state="empty-appointments"]').exists()).toBe(true)

    wrapper.unmount()
  })
})
