import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { createRouter, createMemoryHistory } from 'vue-router'

// T5 regression coverage: the dashboard renders the upcoming-week strip
// from GET /api/dashboard/upcoming between the agenda and the KPI grid,
// grouped by day with short Spanish day headers. A failing upcoming
// request renders an inline error whose retry re-issues ONLY that
// resource, and the section never blanks an already-loaded payload on a
// silent refresh.
const { getMock, makeAppointment, installPayload } = vi.hoisted(() => {
  const makeAppointment = (id, scheduledAt, fullName, typeName = 'Limpieza dental') => {
    const parts = fullName.split(' ')
    return {
      id,
      scheduled_at: scheduledAt,
      status: 'confirmed',
      patient: {
        id: 100 + id,
        first_name: parts[0],
        last_name: parts.slice(1).join(' '),
        full_name: fullName
      },
      appointment_type: { id: 3, name: typeName },
      user: { id: 2, name: 'Dra. Carla Núñez' }
    }
  }

  const getMock = vi.fn()

  const installPayload = ({ stats = {}, today = [], upcoming = [] } = {}) => {
    getMock.mockImplementation(async url => {
      if (url === '/api/dashboard/stats') return { data: stats }
      if (url === '/api/dashboard/appointments-today') return { data: today }
      if (url === '/api/dashboard/upcoming') return { data: upcoming }
      return { data: [] }
    })
  }

  return { getMock, makeAppointment, installPayload }
})

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
  return { wrapper, router }
}

const upcomingCalls = () => getMock.mock.calls.filter(([url]) => url === '/api/dashboard/upcoming')
const statsCalls = () => getMock.mock.calls.filter(([url]) => url === '/api/dashboard/stats')
const todayCalls = () =>
  getMock.mock.calls.filter(([url]) => url === '/api/dashboard/appointments-today')

describe('dashboard upcoming week strip (T5)', () => {
  beforeEach(() => {
    signInAs('administrador')
    getMock.mockReset()
    installPayload()
  })

  it('renders /api/dashboard/upcoming grouped by day between the agenda and the KPI grid', async () => {
    installPayload({
      upcoming: [
        makeAppointment(1, '2026-10-05T09:00:00-05:00', 'Ana Torres Quispe', 'Limpieza dental'),
        makeAppointment(2, '2026-10-05T11:30:00-05:00', 'Luis Rojas', 'Consulta'),
        makeAppointment(3, '2026-10-08T10:00:00-05:00', 'Marta Díaz', 'Ortodoncia')
      ]
    })

    const { wrapper } = await mountDashboard()

    expect(upcomingCalls()).toHaveLength(1)

    const section = wrapper.find('section[aria-label="Próximas citas"]')
    expect(section.exists()).toBe(true)
    // T3 — the week strip is not a calendar destination: the single
    // "Ver calendario" CTA lives in the Acciones rápidas header.
    expect(section.text()).not.toContain('Ver calendario')

    // Section order: agenda -> upcoming -> KPI grid (ops IA).
    const html = wrapper.html()
    const agendaIdx = html.indexOf('aria-label="Agenda de hoy"')
    const upcomingIdx = html.indexOf('aria-label="Próximas citas"')
    const kpiIdx = html.indexOf('aria-label="Resumen del día"')
    expect(agendaIdx).toBeGreaterThan(-1)
    expect(upcomingIdx).toBeGreaterThan(agendaIdx)
    expect(kpiIdx).toBeGreaterThan(upcomingIdx)

    // Grouped by day: two local days, first with two rows.
    const groups = section.findAll('[data-upcoming-group]')
    expect(groups).toHaveLength(2)
    expect(groups[0].findAll('[data-upcoming-row]')).toHaveLength(2)
    expect(groups[1].findAll('[data-upcoming-row]')).toHaveLength(1)

    // Short Spanish day headers ("Lun 6" / "Mar 7" style).
    const headers = section.findAll('[data-upcoming-day]')
    expect(headers).toHaveLength(2)
    expect(headers[0].text()).toMatch(/^(Dom|Lun|Mar|Mié|Jue|Vie|Sáb) \d{1,2}$/)
    expect(headers[1].text()).toMatch(/^(Dom|Lun|Mar|Mié|Jue|Vie|Sáb) \d{1,2}$/)
    expect(headers[0].text()).not.toBe(headers[1].text())

    // Row anatomy: time (tabular), patient full_name, type.
    const firstRow = groups[0].find('[data-upcoming-row]')
    expect(firstRow.text()).toContain('Ana Torres Quispe')
    expect(firstRow.text()).toContain('Limpieza dental')
    expect(firstRow.find('[data-upcoming-time]').classes()).toContain('tabular-nums')

    wrapper.unmount()
  })

  it('renders the in-section Spanish empty state when the week is clear', async () => {
    const { wrapper } = await mountDashboard()

    const section = wrapper.find('section[aria-label="Próximas citas"]')
    const empty = section.find('[data-state="empty-upcoming"]')
    expect(empty.exists()).toBe(true)
    expect(empty.text()).toContain('Sin citas programadas para esta semana')

    // The strip owns its own marker; today's empty-state marker stays on
    // the agenda and is never reused here.
    expect(empty.attributes('data-state')).toBe('empty-upcoming')
    expect(section.find('[data-state="empty-appointments"]').exists()).toBe(false)
    expect(wrapper.find('[data-state="empty-appointments"]').exists()).toBe(true)

    wrapper.unmount()
  })

  it('shows an inline error and retries ONLY the upcoming resource', async () => {
    let upcomingAttempt = 0
    getMock.mockImplementation(async url => {
      if (url === '/api/dashboard/stats') return { data: { appointments_today: 4 } }
      if (url === '/api/dashboard/appointments-today') {
        return { data: [makeAppointment(9, '2026-10-04T08:30:00-05:00', 'Paciente Hoy')] }
      }
      if (url === '/api/dashboard/upcoming') {
        upcomingAttempt += 1
        if (upcomingAttempt === 1) throw { status: 500 }
        return {
          data: [makeAppointment(1, '2026-10-05T09:00:00-05:00', 'Ana Torres Quispe')]
        }
      }
      return { data: [] }
    })

    const { wrapper } = await mountDashboard()

    const section = wrapper.find('section[aria-label="Próximas citas"]')
    const error = section.find('[data-state="error-upcoming"]')
    expect(error.exists()).toBe(true)
    expect(error.attributes('role')).toBe('alert')
    expect(error.text()).toContain('No pudimos cargar las próximas citas')
    expect(error.text()).toContain('Reintentar')

    // The rest of the page stays usable; the day KPI renders from its
    // single source (today's one-row agenda).
    expect(wrapper.find('[data-appointment-row]').exists()).toBe(true)
    expect(wrapper.find('[data-stat="appointments-today"]').text()).toBe('1')

    await wrapper.find('[data-retry-upcoming]').trigger('click')
    await flushPromises()
    await flushPromises()

    expect(upcomingCalls()).toHaveLength(2)
    expect(statsCalls()).toHaveLength(1)
    expect(todayCalls()).toHaveLength(1)
    expect(section.find('[data-state="error-upcoming"]').exists()).toBe(false)
    expect(section.findAll('[data-upcoming-row]')).toHaveLength(1)

    wrapper.unmount()
  })

  it('redirects to /login when the upcoming request returns 401', async () => {
    getMock.mockImplementation(async url => {
      if (url === '/api/dashboard/stats') return { data: {} }
      if (url === '/api/dashboard/appointments-today') return { data: [] }
      if (url === '/api/dashboard/upcoming') throw { status: 401 }
      return { data: [] }
    })

    const { wrapper, router } = await mountDashboard()
    await flushPromises()

    expect(router.currentRoute.value.path).toBe('/login')

    wrapper.unmount()
  })

  it('renders a section skeleton on first load and keeps rows during a silent refresh', async () => {
    let resolveUpcoming
    getMock.mockImplementation(async url => {
      if (url === '/api/dashboard/stats') return { data: {} }
      if (url === '/api/dashboard/appointments-today') return { data: [] }
      if (url === '/api/dashboard/upcoming') {
        return new Promise(resolve => {
          resolveUpcoming = resolve
        })
      }
      return { data: [] }
    })

    const { wrapper } = await mountDashboard()

    expect(wrapper.find('[aria-label="Cargando próximas citas"]').exists()).toBe(true)

    resolveUpcoming({
      data: [makeAppointment(1, '2026-10-05T09:00:00-05:00', 'Ana Torres Quispe')]
    })
    await flushPromises()
    await flushPromises()

    expect(wrapper.find('[aria-label="Cargando próximas citas"]').exists()).toBe(false)
    expect(wrapper.findAll('[data-upcoming-row]')).toHaveLength(1)

    // Silent refresh: a pending upcoming request never blanks the strip.
    getMock.mockImplementation(async url => {
      if (url === '/api/dashboard/stats') return { data: {} }
      if (url === '/api/dashboard/appointments-today') return { data: [] }
      if (url === '/api/dashboard/upcoming') return new Promise(() => {})
      return { data: [] }
    })

    await wrapper.find('[data-refresh-button]').trigger('click')
    await flushPromises()

    expect(wrapper.find('[aria-label="Cargando próximas citas"]').exists()).toBe(false)
    expect(wrapper.findAll('[data-upcoming-row]')).toHaveLength(1)
    expect(wrapper.find('[data-dashboard-content]').attributes('aria-busy')).toBe('true')

    wrapper.unmount()
  })
})
