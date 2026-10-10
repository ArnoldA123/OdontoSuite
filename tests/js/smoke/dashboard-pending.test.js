import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { createRouter, createMemoryHistory } from 'vue-router'

// WU3 / D6 regression coverage: the dashboard renders ONE unified
// "Requiere tu atención" block where quotations and treatment plans appear as rows of
// a single dataset. Each row: patient name, amount, type badge
// (Presupuesto / Plan de tratamiento), status badge, date, and ONE
// row-level action that routes to the entity's module list. Merged rows
// sort by their pending date ascending. Visibility keeps the backend
// payload-key contract: a subset key present renders its rows, an absent
// key hides them, and no keys at all hide the whole section. A failing
// pending request shows an inline error whose retry re-issues ONLY that
// resource.
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

describe('dashboard unified pending list (WU3 / D6)', () => {
  beforeEach(() => {
    signInAs('administrador')
    getMock.mockReset()
    installPayload()
  })

  it('merges quotations and plans into ONE list with type badges sorted by pending date ascending', async () => {
    installPayload({
      pending: {
        quotations: {
          count: 2,
          items: [
            quotationItem(11, 'Ana Torres Quispe', 'viewed', 149.9, localIso(2026, 9, 3)),
            quotationItem(12, 'Luis Rojas', 'sent', 89, localIso(2026, 9, 1))
          ]
        },
        treatment_plans: {
          count: 1,
          items: [planItem(21, 'Marta Díaz', 'proposed', localIso(2026, 9, 2))]
        }
      }
    })

    const { wrapper } = await mountDashboard()

    const section = wrapper.find('section[aria-label="Requiere tu atención"]')
    expect(section.exists()).toBe(true)

    // ONE list: no per-type columns, headers or counts.
    expect(wrapper.findAll('[data-pending-group]')).toHaveLength(0)
    expect(section.text()).not.toContain('Presupuestos pendientes')
    expect(section.text()).not.toContain('Planes por aceptar')

    const rows = section.findAll('[data-pending-row]')
    expect(rows).toHaveLength(3)

    // Sorted by pending date ascending: 1 oct, 2 oct, 3 oct.
    expect(rows[0].text()).toContain('Luis Rojas')
    expect(rows[0].text()).toContain('1 oct')
    expect(rows[1].text()).toContain('Marta Díaz')
    expect(rows[1].text()).toContain('2 oct')
    expect(rows[2].text()).toContain('Ana Torres Quispe')
    expect(rows[2].text()).toContain('3 oct')

    // Row anatomy: patient name, amount, type badge, status badge, date.
    const quotationRow = rows[2]
    expect(normalize(quotationRow.text())).toContain('S/ 149.90')
    expect(quotationRow.text()).toContain('Presupuesto')
    expect(quotationRow.text()).toContain('Visto')
    expect(quotationRow.text()).toContain('3 oct')

    const planRow = rows[1]
    expect(normalize(planRow.text())).toContain('S/ 249.50')
    expect(planRow.text()).toContain('Plan de tratamiento')
    expect(planRow.text()).toContain('Propuesto')

    expect(pendingCalls()).toHaveLength(1)

    wrapper.unmount()
  })

  it('gives every row exactly one action that routes to the entity module list', async () => {
    installPayload({
      pending: {
        quotations: { count: 1, items: [quotationItem(11, 'Ana Torres Quispe')] },
        treatment_plans: { count: 1, items: [planItem(21, 'Marta Díaz')] }
      }
    })

    const { wrapper, router } = await mountDashboard()

    const rows = wrapper.findAll('[data-pending-row]')
    expect(rows).toHaveLength(2)

    for (const row of rows) {
      const actions = row.findAll('button')
      expect(actions).toHaveLength(1)
    }

    // No section-level overflow link: the row action IS the way in.
    const section = wrapper.find('section[aria-label="Requiere tu atención"]')
    expect(section.text()).not.toContain('Ver todos')

    const quotationRow = wrapper.find('[data-pending-row="quotations"]')
    await quotationRow.find('button').trigger('click')
    await flushPromises()
    expect(router.currentRoute.value.path).toBe('/quotations')

    await router.push('/dashboard')
    await flushPromises()

    const planRow = wrapper.find('[data-pending-row="treatment-plans"]')
    await planRow.find('button').trigger('click')
    await flushPromises()
    expect(router.currentRoute.value.path).toBe('/treatment-plans')

    wrapper.unmount()
  })

  it('renders only the treatment-plan rows when the payload omits quotations', async () => {
    installPayload({
      pending: {
        treatment_plans: { count: 1, items: [planItem(21, 'Marta Díaz')] }
      }
    })

    const { wrapper } = await mountDashboard()

    const section = wrapper.find('section[aria-label="Requiere tu atención"]')
    expect(section.exists()).toBe(true)
    expect(section.findAll('[data-pending-row]')).toHaveLength(1)
    expect(section.find('[data-pending-row="quotations"]').exists()).toBe(false)
    expect(section.find('[data-pending-row="treatment-plans"]').exists()).toBe(true)

    wrapper.unmount()
  })

  it('hides the whole section when the payload carries neither subset', async () => {
    installPayload({ pending: {} })

    const { wrapper } = await mountDashboard()

    expect(wrapper.find('section[aria-label="Requiere tu atención"]').exists()).toBe(false)
    expect(wrapper.findAll('[data-pending-row]')).toHaveLength(0)
    expect(pendingCalls()).toHaveLength(1)

    wrapper.unmount()
  })

  it('renders ONE unified empty state when every visible subset has no items', async () => {
    installPayload({
      pending: {
        quotations: { count: 0, items: [] },
        treatment_plans: { count: 0, items: [] }
      }
    })

    const { wrapper } = await mountDashboard()

    const section = wrapper.find('section[aria-label="Requiere tu atención"]')
    expect(section.exists()).toBe(true)

    const empties = section.findAll('[data-state="empty-pending"]')
    expect(empties).toHaveLength(1)
    expect(empties[0].text()).toContain('Sin pendientes')
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

    const section = wrapper.find('section[aria-label="Requiere tu atención"]')
    const error = section.find('[data-state="error-pending"]')
    expect(error.exists()).toBe(true)
    expect(error.attributes('role')).toBe('alert')
    expect(error.text()).toContain('No pudimos cargar los pendientes')
    expect(error.text()).toContain('Reintentar')

    // The rest of the page stays usable; the day KPI renders from its
    // single source (the empty today list).
    expect(wrapper.find('[data-stat="appointments-today"]').text()).toBe('0')

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
})
