import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { createRouter, createMemoryHistory } from 'vue-router'

// T3 regression coverage: the dashboard renders a dedicated error state when
// the summary request fails (instead of silently showing a fabricated zero),
// retries from that state, exposes a manual refresh control that never blanks
// the page, and keeps the standard /login redirect on a 401.
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

const cashCurrent = {
  session: { id: 7, status: 'open', opened_at: '2026-10-04T08:30:00' },
  summary: { opening_amount: 100, total_income: 200, total_expenses: 20, expected_amount: 280 }
}

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
const cashCalls = () => getMock.mock.calls.filter(([url]) => url === '/api/cash-register/current')

const installStats = stats =>
  getMock.mockImplementation(async url => {
    if (url === '/api/dashboard/stats') {
      if (stats instanceof Error) throw stats
      return { data: stats }
    }
    if (url === '/api/cash-register/current') return { data: cashCurrent }
    return { data: [] }
  })

describe('dashboard load states (T3)', () => {
  beforeEach(() => {
    signInAs('administrador')
    getMock.mockReset()
  })

  it('renders the summary error state with a Reintentar action when the summary fails', async () => {
    installStats(Object.assign(new Error('boom'), { status: 500 }))

    const { wrapper } = await mountDashboard()

    const error = wrapper.find('[data-state="error-stats"]')
    expect(error.exists()).toBe(true)
    expect(error.attributes('role')).toBe('alert')
    expect(error.text()).toContain('No pudimos cargar el resumen')
    expect(error.text()).toContain('Reintentar')
    expect(error.find('svg').exists()).toBe(true)

    // #388 - the failure keeps its own block: the static destinations and the
    // cash card stay on screen from their own sources.
    expect(wrapper.find('[data-dashboard-block="destinations"]').exists()).toBe(true)
    expect(wrapper.find('[data-stat-card="cash-balance"]').exists()).toBe(true)

    wrapper.unmount()
  })

  it('re-issues only the summary when Reintentar is clicked and clears the error', async () => {
    let attempt = 0
    getMock.mockImplementation(async url => {
      if (url === '/api/dashboard/stats') {
        attempt += 1
        if (attempt === 1) throw { status: 500 }
        return { data: { appointments_today: 6 } }
      }
      if (url === '/api/cash-register/current') return { data: cashCurrent }
      return { data: [] }
    })

    const { wrapper } = await mountDashboard()
    expect(wrapper.find('[data-state="error-stats"]').exists()).toBe(true)

    await wrapper.find('[data-retry-stats]').trigger('click')
    await flushPromises()
    await flushPromises()

    // The retry is summary-only: the cash reading is neither re-fetched nor
    // unmounted.
    expect(statsCalls()).toHaveLength(2)
    expect(cashCalls()).toHaveLength(1)
    expect(wrapper.find('[data-state="error-stats"]').exists()).toBe(false)
    expect(wrapper.find('[data-stat="appointments-today"]').text()).toBe('6')

    wrapper.unmount()
  })

  it('refresh control reloads without unmounting the current content', async () => {
    installStats({ appointments_today: 2 })

    const { wrapper } = await mountDashboard()

    const refresh = wrapper.find('[data-refresh-button]')
    expect(refresh.exists()).toBe(true)
    expect(refresh.text()).toContain('Actualizar')
    expect(wrapper.find('[data-stat="appointments-today"]').text()).toBe('2')

    let resolveStats
    getMock.mockImplementation(async url => {
      if (url === '/api/dashboard/stats') {
        return new Promise(resolve => {
          resolveStats = resolve
        })
      }
      if (url === '/api/cash-register/current') return { data: cashCurrent }
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

    expect(wrapper.find('[data-stat="appointments-today"]').text()).toBe('7')
    expect(wrapper.find('[data-dashboard-content]').attributes('aria-busy')).toBe('false')
    expect(wrapper.find('[aria-label="Cargando resumen"]').exists()).toBe(false)
    // The cash reading is served from the composable state after the first
    // load: a manual refresh never duplicates it.
    expect(cashCalls()).toHaveLength(1)

    wrapper.unmount()
  })

  it('still redirects to /login when the summary request returns 401', async () => {
    getMock.mockImplementation(async () => {
      throw { status: 401, response: { data: { message: 'Sesión expirada' } } }
    })

    const { wrapper, router } = await mountDashboard()
    await flushPromises()

    expect(router.currentRoute.value.path).toBe('/login')

    wrapper.unmount()
  })

  it('renders zero instead of a fabricated day when the summary payload is not an object', async () => {
    installStats([])

    const { wrapper } = await mountDashboard()

    expect(wrapper.find('[data-state="error-stats"]').exists()).toBe(false)
    expect(wrapper.find('[data-stat="appointments-today"]').text()).toBe('0')

    wrapper.unmount()
  })
})
