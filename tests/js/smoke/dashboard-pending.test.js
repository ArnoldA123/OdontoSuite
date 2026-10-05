import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { createRouter, createMemoryHistory } from 'vue-router'

// T7b regression coverage: the dashboard renders the "Pendientes" block
// (quotations and treatment plans awaiting a decision) between the week
// preview and the KPI grid. Visibility follows the backend contract: a
// subset key present in the payload renders its group, an absent key hides
// it, and no keys at all hide the whole section. A failing pending request
// shows an inline error whose retry re-issues ONLY that resource, and the
// manual refresh includes it in the full load path without blanking rows.
const { getMock, installPayload } = vi.hoisted(() => {
  const getMock = vi.fn()

  const installPayload = ({ stats = {}, today = [], upcoming = [], pending = {} } = {}) => {
    getMock.mockImplementation(async url => {
      if (url === '/api/dashboard/stats') return { data: stats }
      if (url === '/api/dashboard/appointments-today') return { data: today }
      if (url === '/api/dashboard/upcoming') return { data: upcoming }
      if (url === '/api/dashboard/pending') return { data: pending }
      return { data: [] }
    })
  }

  return { getMock, installPayload }
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
    routes: [
      { path: '/dashboard', component: { template: '<div />' } },
      { path: '/quotations', component: { template: '<div>Presupuestos</div>' } },
      { path: '/treatment-plans', component: { template: '<div>Planes</div>' } },
      { path: '/login', component: { template: '<div>Login</div>' } },
      { path: '/:pathMatch(.*)*', component: { template: '<div />' } }
    ]
  })
  await router.push('/dashboard')
  await router.isReady()

  const wrapper = mount(Page, { global: { plugins: [router, uiComponents] } })
  await flushPromises()
  await flushPromises()
  return { wrapper, router }
}

// Local-time ISO strings so the rendered day is stable across test timezones.
const localIso = (year, monthIndex, day) => new Date(year, monthIndex, day, 10, 0).toISOString()

const quotationItem = (
  id,
  patientName,
  status = 'sent',
  total = 149.9,
  createdAt = localIso(2026, 9, 3)
) => ({
  id,
  patient_name: patientName,
  total_amount: total,
  status,
  created_at: createdAt
})

const planItem = (id, patientName, status = 'proposed', createdAt = localIso(2026, 9, 2)) => ({
  id,
  patient_name: patientName,
  title: 'Plan de tratamiento',
  final_cost: 249.5,
  status,
  created_at: createdAt
})

const pendingCalls = () => getMock.mock.calls.filter(([url]) => url === '/api/dashboard/pending')
const statsCalls = () => getMock.mock.calls.filter(([url]) => url === '/api/dashboard/stats')
const todayCalls = () =>
  getMock.mock.calls.filter(([url]) => url === '/api/dashboard/appointments-today')
const upcomingCalls = () => getMock.mock.calls.filter(([url]) => url === '/api/dashboard/upcoming')

// Intl rendering uses U+00A0 between the currency glyph and the amount.
const normalize = value => value.replace(/\u00a0/g, ' ')

describe('dashboard pending block (T7b)', () => {
  beforeEach(() => {
    signInAs('administrador')
    getMock.mockReset()
    installPayload()
  })

  it('renders both groups with counts, rows, Spanish status labels and the PEN total', async () => {
    installPayload({
      pending: {
        quotations: {
          count: 2,
          items: [
            quotationItem(11, 'Ana Torres Quispe', 'viewed', 149.9),
            quotationItem(12, 'Luis Rojas', 'sent', 89)
          ]
        },
        treatment_plans: {
          count: 1,
          items: [planItem(21, 'Marta Díaz', 'proposed')]
        }
      }
    })

    const { wrapper } = await mountDashboard()

    const section = wrapper.find('section[aria-label="Pendientes"]')
    expect(section.exists()).toBe(true)
    expect(section.text()).toContain('Pendientes')
    expect(section.text()).toContain('Presupuestos pendientes')
    expect(section.text()).toContain('Planes por aceptar')

    const quotationGroup = section.find('[data-pending-group="quotations"]')
    expect(quotationGroup.exists()).toBe(true)
    expect(quotationGroup.text()).toContain('2')

    const quotationRows = quotationGroup.findAll('[data-pending-row]')
    expect(quotationRows).toHaveLength(2)
    expect(quotationRows[0].text()).toContain('Ana Torres Quispe')
    expect(quotationRows[0].text()).toContain('Visto')
    expect(normalize(quotationRows[0].text())).toContain('S/ 149.90')
    expect(quotationRows[0].text()).toContain('3 oct')
    expect(quotationRows[1].text()).toContain('Enviado')

    const planGroup = section.find('[data-pending-group="treatment-plans"]')
    expect(planGroup.exists()).toBe(true)
    expect(planGroup.text()).toContain('1')

    const planRows = planGroup.findAll('[data-pending-row]')
    expect(planRows).toHaveLength(1)
    expect(planRows[0].text()).toContain('Marta Díaz')
    expect(planRows[0].text()).toContain('Propuesto')
    expect(planRows[0].text()).toContain('2 oct')

    // Section order: agenda -> upcoming -> pendientes -> KPI grid.
    const html = wrapper.html()
    const agendaIdx = html.indexOf('aria-label="Agenda de hoy"')
    const upcomingIdx = html.indexOf('aria-label="Próximas citas"')
    const pendingIdx = html.indexOf('aria-label="Pendientes"')
    const kpiIdx = html.indexOf('aria-label="Resumen del día"')
    expect(upcomingIdx).toBeGreaterThan(agendaIdx)
    expect(pendingIdx).toBeGreaterThan(upcomingIdx)
    expect(kpiIdx).toBeGreaterThan(pendingIdx)

    expect(pendingCalls()).toHaveLength(1)

    wrapper.unmount()
  })

  it('renders only the plans group when the payload omits quotations', async () => {
    installPayload({
      pending: {
        treatment_plans: { count: 1, items: [planItem(21, 'Marta Díaz')] }
      }
    })

    const { wrapper } = await mountDashboard()

    const section = wrapper.find('section[aria-label="Pendientes"]')
    expect(section.exists()).toBe(true)
    expect(section.find('[data-pending-group="quotations"]').exists()).toBe(false)
    expect(section.text()).not.toContain('Presupuestos pendientes')
    expect(section.find('[data-pending-group="treatment-plans"]').exists()).toBe(true)

    wrapper.unmount()
  })

  it('hides the whole section when the payload carries neither subset', async () => {
    installPayload({ pending: {} })

    const { wrapper } = await mountDashboard()

    expect(wrapper.find('section[aria-label="Pendientes"]').exists()).toBe(false)
    expect(wrapper.find('[data-pending-group]').exists()).toBe(false)
    expect(pendingCalls()).toHaveLength(1)

    wrapper.unmount()
  })

  it('renders the small Spanish empty copy per group when a subset has no pending items', async () => {
    installPayload({
      pending: {
        quotations: { count: 0, items: [] },
        treatment_plans: { count: 0, items: [] }
      }
    })

    const { wrapper } = await mountDashboard()

    const section = wrapper.find('section[aria-label="Pendientes"]')
    expect(section.exists()).toBe(true)
    expect(section.text()).toContain('Sin presupuestos pendientes')
    expect(section.text()).toContain('Sin planes por aceptar')
    expect(wrapper.findAll('[data-pending-row]')).toHaveLength(0)

    wrapper.unmount()
  })

  it('shows an inline error and retries ONLY the pending resource', async () => {
    let pendingAttempt = 0
    getMock.mockImplementation(async url => {
      if (url === '/api/dashboard/stats') return { data: { appointments_today: 4 } }
      if (url === '/api/dashboard/appointments-today') return { data: [] }
      if (url === '/api/dashboard/upcoming') return { data: [] }
      if (url === '/api/dashboard/pending') {
        pendingAttempt += 1
        if (pendingAttempt === 1) throw { status: 500 }
        return {
          data: {
            quotations: { count: 1, items: [quotationItem(11, 'Ana Torres Quispe', 'sent')] }
          }
        }
      }
      return { data: [] }
    })

    const { wrapper } = await mountDashboard()

    const section = wrapper.find('section[aria-label="Pendientes"]')
    const error = section.find('[data-state="error-pending"]')
    expect(error.exists()).toBe(true)
    expect(error.attributes('role')).toBe('alert')
    expect(error.text()).toContain('No pudimos cargar los pendientes')
    expect(error.text()).toContain('Reintentar')

    // The rest of the page stays usable.
    expect(wrapper.find('[data-stat-card="appointments-today"]').text()).toContain('4')

    await wrapper.find('[data-retry-pending]').trigger('click')
    await flushPromises()
    await flushPromises()

    expect(pendingCalls()).toHaveLength(2)
    expect(statsCalls()).toHaveLength(1)
    expect(todayCalls()).toHaveLength(1)
    expect(upcomingCalls()).toHaveLength(1)
    expect(section.find('[data-state="error-pending"]').exists()).toBe(false)
    expect(section.findAll('[data-pending-row]')).toHaveLength(1)

    wrapper.unmount()
  })

  it('renders a first-load skeleton and never blanks loaded rows on refresh', async () => {
    let resolvePending
    getMock.mockImplementation(async url => {
      if (url === '/api/dashboard/stats') return { data: {} }
      if (url === '/api/dashboard/appointments-today') return { data: [] }
      if (url === '/api/dashboard/upcoming') return { data: [] }
      if (url === '/api/dashboard/pending') {
        return new Promise(resolve => {
          resolvePending = resolve
        })
      }
      return { data: [] }
    })

    const { wrapper } = await mountDashboard()

    expect(wrapper.find('[aria-label="Cargando pendientes"]').exists()).toBe(true)

    resolvePending({
      data: { quotations: { count: 1, items: [quotationItem(11, 'Ana Torres Quispe')] } }
    })
    await flushPromises()
    await flushPromises()

    expect(wrapper.find('[aria-label="Cargando pendientes"]').exists()).toBe(false)
    expect(wrapper.findAll('[data-pending-row]')).toHaveLength(1)

    // Silent refresh: a pending request never blanks the loaded block.
    getMock.mockImplementation(async url => {
      if (url === '/api/dashboard/stats') return { data: {} }
      if (url === '/api/dashboard/appointments-today') return { data: [] }
      if (url === '/api/dashboard/upcoming') return { data: [] }
      if (url === '/api/dashboard/pending') return new Promise(() => {})
      return { data: [] }
    })

    await wrapper.find('[data-refresh-button]').trigger('click')
    await flushPromises()

    expect(pendingCalls()).toHaveLength(2)
    expect(statsCalls()).toHaveLength(2)
    expect(todayCalls()).toHaveLength(2)
    expect(upcomingCalls()).toHaveLength(2)
    expect(wrapper.find('[aria-label="Cargando pendientes"]').exists()).toBe(false)
    expect(wrapper.findAll('[data-pending-row]')).toHaveLength(1)

    wrapper.unmount()
  })

  it('redirects to /login when the pending request returns 401', async () => {
    getMock.mockImplementation(async url => {
      if (url === '/api/dashboard/stats') return { data: {} }
      if (url === '/api/dashboard/appointments-today') return { data: [] }
      if (url === '/api/dashboard/upcoming') return { data: [] }
      if (url === '/api/dashboard/pending') throw { status: 401 }
      return { data: [] }
    })

    const { wrapper, router } = await mountDashboard()
    await flushPromises()

    expect(router.currentRoute.value.path).toBe('/login')

    wrapper.unmount()
  })

  it('links each group to its module with a ghost Ver todos action', async () => {
    installPayload({
      pending: {
        quotations: { count: 1, items: [quotationItem(11, 'Ana Torres Quispe')] },
        treatment_plans: { count: 1, items: [planItem(21, 'Marta Díaz')] }
      }
    })

    const { wrapper, router } = await mountDashboard()

    const quotationGroup = wrapper.find('[data-pending-group="quotations"]')
    const planGroup = wrapper.find('[data-pending-group="treatment-plans"]')

    const quotationLink = quotationGroup
      .findAll('button')
      .find(button => button.text().includes('Ver todos'))
    const planLink = planGroup.findAll('button').find(button => button.text().includes('Ver todos'))
    expect(quotationLink).toBeTruthy()
    expect(planLink).toBeTruthy()

    await quotationLink.trigger('click')
    await flushPromises()
    expect(router.currentRoute.value.path).toBe('/quotations')

    await planLink.trigger('click')
    await flushPromises()
    expect(router.currentRoute.value.path).toBe('/treatment-plans')

    wrapper.unmount()
  })
})
