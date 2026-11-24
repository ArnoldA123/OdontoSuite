import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { createRouter, createMemoryHistory } from 'vue-router'

// U2 - the dashboard points at the three destinations of the daily work
// instead of listing appointments. Each card is a link with one line of what
// it resolves, gated by the permission its own screen demands, and it carries
// no counter and no extra API call.
const harness = vi.hoisted(() => ({ get: vi.fn(), permissions: [] }))

vi.mock('../../../resources/js/composables/useApi', () => ({
  useApi: () => ({
    get: harness.get,
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
vi.mock('../../../resources/js/composables/useAuth', async () => {
  const { computed } = await import('vue')
  return {
    useAuth: () => ({
      user: computed(() => ({
        id: 1,
        name: 'Ana Admin',
        role: 'administrador',
        permissions: harness.permissions
      })),
      token: computed(() => 'test-token'),
      isAuthenticated: computed(() => true)
    })
  }
})

import uiComponents from '../../../resources/js/plugins/ui-components'
import Page from '../../../resources/js/modules/dashboard/DashboardPage.vue'
import rolePermissions from '../support/role-permissions.json'

const cashCurrent = {
  session: { id: 7, status: 'open', opened_at: '2026-10-04T08:30:00' },
  summary: { opening_amount: 100, total_income: 200, total_expenses: 20, expected_amount: 280 }
}

const asRole = role => {
  harness.permissions = rolePermissions[role]
  localStorage.setItem('auth_token', 'test-token')
  localStorage.setItem('user', JSON.stringify({ id: 1, name: 'Ana Admin', role }))
}

const mountDashboard = async () => {
  const router = createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: '/calendar', component: { template: '<div>Agenda</div>' } },
      { path: '/my-day', component: { template: '<div>Mi jornada</div>' } },
      { path: '/continuity', component: { template: '<div>Pendientes</div>' } },
      { path: '/:pathMatch(.*)*', component: { template: '<div />' } }
    ]
  })
  await router.push('/dashboard')
  await router.isReady()

  const wrapper = mount(Page, { global: { plugins: [router, uiComponents] } })
  await flushPromises()
  return { wrapper, router }
}

const requestedUrls = () => harness.get.mock.calls.map(([url]) => url)

const REMOVED_ENDPOINTS = [
  '/api/dashboard/appointments-today',
  '/api/dashboard/upcoming',
  '/api/dashboard/pending'
]

describe('dashboard "Dónde seguir" block (U2)', () => {
  beforeEach(() => {
    harness.get.mockReset()
    harness.get.mockImplementation(async url => {
      if (url === '/api/dashboard/stats') return { data: { appointments_today: 4 } }
      if (url === '/api/cash-register/current') return { data: cashCurrent }
      return { data: [] }
    })
    asRole('administrador')
  })

  it('renders the three destination links with their routes and one line each', async () => {
    const { wrapper } = await mountDashboard()

    const block = wrapper.find('[data-dashboard-block="destinations"]')
    expect(block.exists()).toBe(true)
    expect(block.attributes('aria-label')).toBe('Dónde seguir')
    expect(block.find('h2').text()).toBe('Dónde seguir')

    const links = block.findAll('[data-dashboard-destination]')
    expect(links).toHaveLength(3)

    const routesByKey = Object.fromEntries(
      links.map(link => [link.attributes('data-dashboard-destination'), link.attributes('href')])
    )
    expect(routesByKey).toEqual({
      agenda: '/calendar',
      'my-day': '/my-day',
      pending: '/continuity'
    })

    const text = block.text()
    expect(text).toContain('Agenda')
    expect(text).toContain('Mi jornada')
    expect(text).toContain('Pendientes')
    // One line of what each destination resolves.
    expect(text).toContain('Citas, horarios y ambientes del día.')
    expect(text).toContain('Tu cola de atención del día.')
    expect(text).toContain('respuestas comerciales')

    wrapper.unmount()
  })

  it('navigates to the destination without issuing any new request', async () => {
    const { wrapper, router } = await mountDashboard()

    const before = requestedUrls().length

    await wrapper.find('[data-dashboard-destination="agenda"]').trigger('click')
    await flushPromises()
    expect(router.currentRoute.value.path).toBe('/calendar')

    await wrapper.find('[data-dashboard-destination="my-day"]').trigger('click')
    await flushPromises()
    expect(router.currentRoute.value.path).toBe('/my-day')

    await wrapper.find('[data-dashboard-destination="pending"]').trigger('click')
    await flushPromises()
    expect(router.currentRoute.value.path).toBe('/continuity')

    expect(requestedUrls()).toHaveLength(before)

    wrapper.unmount()
  })

  it('offers no invented counters on the destination cards', async () => {
    const { wrapper } = await mountDashboard()

    const block = wrapper.find('[data-dashboard-block="destinations"]')
    expect(block.text()).not.toMatch(/\d/)

    // The retired lists left no row hook behind either.
    expect(wrapper.findAll('[data-pending-row]')).toHaveLength(0)
    expect(wrapper.findAll('[data-appointment-row]')).toHaveLength(0)
    expect(wrapper.findAll('[data-upcoming-row]')).toHaveLength(0)

    wrapper.unmount()
  })

  it('offers only the destinations the role can open', async () => {
    asRole('recepcionista')
    const reception = await mountDashboard()

    const receptionKeys = reception.wrapper
      .findAll('[data-dashboard-destination]')
      .map(link => link.attributes('data-dashboard-destination'))
    expect(receptionKeys).toEqual(['agenda', 'pending'])
    reception.wrapper.unmount()

    // Finance holds no agenda grant but reads quotations, so the commercial
    // answers of Pendientes stay reachable through `view.quotation`.
    asRole('finanzas')
    const finance = await mountDashboard()

    const financeKeys = finance.wrapper
      .findAll('[data-dashboard-destination]')
      .map(link => link.attributes('data-dashboard-destination'))
    expect(financeKeys).toEqual(['pending'])
    expect(finance.wrapper.find('[data-stat-card="cash-balance"]').exists()).toBe(true)
    expect(finance.wrapper.find('[data-stat-card="appointments-today"]').exists()).toBe(false)
    finance.wrapper.unmount()
  })

  it('hides the destinations block from a session with none of the grants', async () => {
    harness.permissions = ['view.dashboard']

    const { wrapper } = await mountDashboard()

    expect(wrapper.find('[data-dashboard-block="destinations"]').exists()).toBe(false)
    expect(wrapper.findAll('[data-dashboard-destination]')).toHaveLength(0)
    expect(wrapper.find('[data-dashboard-header]').exists()).toBe(true)

    wrapper.unmount()
  })

  it('shows the Pendientes menu entry to finance through the quotation grant', async () => {
    asRole('finanzas')

    const { wrapper } = await mountDashboard()

    const sidebar = wrapper.find('[data-app-chrome="sidebar"]')
    expect(sidebar.text()).toContain('Pendientes')
    expect(sidebar.find('a[href="/continuity"]').exists()).toBe(true)

    wrapper.unmount()
  })

  it('hides the Pendientes menu entry from a session with neither grant', async () => {
    harness.permissions = ['view.dashboard', 'view.patient']

    const { wrapper } = await mountDashboard()

    const sidebar = wrapper.find('[data-app-chrome="sidebar"]')
    expect(sidebar.find('a[href="/continuity"]').exists()).toBe(false)

    wrapper.unmount()
  })

  it('never asks for the endpoints the retired blocks owned', async () => {
    const { wrapper } = await mountDashboard()
    await flushPromises()

    for (const endpoint of REMOVED_ENDPOINTS) {
      expect(requestedUrls()).not.toContain(endpoint)
    }

    wrapper.unmount()
  })

  it('replaces the three retired blocks in the page body', async () => {
    const { wrapper } = await mountDashboard()

    for (const label of ['Agenda de hoy', 'Requiere tu atención', 'Próximos días']) {
      expect(wrapper.find(`section[aria-label="${label}"]`).exists()).toBe(false)
    }
    expect(wrapper.text()).not.toContain('Agenda de hoy')
    expect(wrapper.text()).not.toContain('Requiere tu atención')
    expect(wrapper.text()).not.toContain('Próximos días')

    wrapper.unmount()
  })
})
