import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { createRouter, createMemoryHistory } from 'vue-router'

// T3 regression coverage: single CTA per destination. "Ver calendario" lives
// only on the Acciones rápidas header, the quick-action grid drops the
// "Nueva Cita" tile (four destinations remain), and the KPI strip is a static
// reference surface (no click affordance, no navigation). The two allowed
// appointment CTAs — the agenda header "Nueva cita" and the empty-state
// "Crear nueva cita" — still open the appointment modal.
//
// T6 coverage (second describe block): one name per destination and real
// button semantics. The sidebar names the payment-methods module "Métodos de
// Pago", the /business-intelligence tile uses the sidebar name instead of
// "Reportes", every tile is a native <button type="button"> (the card is a
// presentation wrapper, not the control), and every dashboard stroke rides
// the documented 1.5 baseline.
const { getMock, installPayload } = vi.hoisted(() => {
  const getMock = vi.fn()

  const installPayload = ({ stats = {}, today = [] } = {}) => {
    getMock.mockImplementation(async url => {
      if (url === '/api/dashboard/stats') return { data: stats }
      if (url === '/api/dashboard/appointments-today') return { data: today }
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
import NewAppointmentModal from '../../../resources/js/components/appointments/NewAppointmentModal.vue'

const signInAs = role => {
  localStorage.setItem('auth_token', 'test-token')
  localStorage.setItem('user', JSON.stringify({ id: 1, name: 'Ana Admin', role }))
  // The sidebar renders its labels only when expanded; pin the state so a
  // stored value from another mount cannot hide the nav copy.
  localStorage.setItem('sidebar-collapsed', 'false')
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

const buttonsWithText = (wrapper, text) =>
  wrapper.findAll('button').filter(button => button.text().includes(text))

describe('dashboard single CTA per destination (T3)', () => {
  beforeEach(() => {
    signInAs('administrador')
    getMock.mockReset()
    installPayload()
  })

  it('renders exactly one Ver calendario CTA and keeps it in the Acciones rápidas header', async () => {
    const { wrapper, router } = await mountDashboard()

    const calendarCtas = buttonsWithText(wrapper, 'Ver calendario')
    expect(calendarCtas).toHaveLength(1)

    const quickActions = wrapper.find('section[aria-label="Acciones rápidas"]')
    expect(quickActions.exists()).toBe(true)
    expect(quickActions.text()).toContain('Ver calendario')

    // T6: the section now holds the header CTA plus the four destination
    // tiles (real buttons); the duplicate would be a second "Ver calendario"
    // inside the tile grid.
    const grid = quickActions.find('[data-reveal="quick-actions"]')
    expect(grid.exists()).toBe(true)
    expect(grid.text()).not.toContain('Ver calendario')
    expect(grid.findAll('button')).toHaveLength(4)

    // The two section headers that used to duplicate it no longer carry it.
    for (const label of ['Agenda de hoy', 'Próximas citas']) {
      const section = wrapper.find(`section[aria-label="${label}"]`)
      expect(section.exists()).toBe(true)
      expect(section.text()).not.toContain('Ver calendario')
    }

    await calendarCtas[0].trigger('click')
    await flushPromises()
    expect(router.currentRoute.value.path).toBe('/calendar')

    wrapper.unmount()
  })

  it('drops the Nueva Cita quick-action tile and keeps the four destination tiles', async () => {
    const { wrapper } = await mountDashboard()

    const quickActions = wrapper.find('section[aria-label="Acciones rápidas"]')
    const tiles = quickActions.findAll('[data-action]')

    expect(tiles).toHaveLength(4)
    expect(quickActions.find('[data-action="new-appointment"]').exists()).toBe(false)
    expect(quickActions.text()).not.toContain('Nueva Cita')
    expect(tiles.map(tile => tile.find('p').text())).toEqual([
      'Pacientes',
      'Profesionales',
      'Ambientes',
      'Business Intelligence'
    ])

    wrapper.unmount()
  })

  it('keeps the agenda header Nueva cita CTA and opens the appointment modal', async () => {
    const { wrapper } = await mountDashboard()

    const headerCta = wrapper
      .findAll('button')
      .find(button => button.text().trim() === 'Nueva cita')
    expect(headerCta).toBeTruthy()

    await headerCta.trigger('click')
    await flushPromises()

    expect(wrapper.findComponent(NewAppointmentModal).props('modelValue')).toBe(true)

    wrapper.unmount()
  })

  it('keeps the empty-state Crear nueva cita CTA and opens the appointment modal', async () => {
    const { wrapper } = await mountDashboard()

    const emptyCta = wrapper.find('[data-cta="empty-create-appointment"]')
    expect(emptyCta.exists()).toBe(true)
    expect(emptyCta.text()).toContain('Crear nueva cita')

    await emptyCta.trigger('click')
    await flushPromises()

    expect(wrapper.findComponent(NewAppointmentModal).props('modelValue')).toBe(true)

    wrapper.unmount()
  })

  it('renders all five KPI cards without a click affordance', async () => {
    const { wrapper, router } = await mountDashboard()

    const cards = wrapper.findAll('[data-stat-card]')
    expect(cards).toHaveLength(5)

    for (const card of cards) {
      expect(card.attributes('data-clickable')).not.toBe('true')
      expect(card.attributes('data-hover')).toBeUndefined()
      expect(card.classes()).not.toContain('cursor-pointer')
    }

    // Clicking any KPI card must not navigate anywhere.
    for (const card of cards) {
      await card.trigger('click')
    }
    await flushPromises()
    expect(router.currentRoute.value.path).toBe('/dashboard')

    wrapper.unmount()
  })

  it('keeps the header Ir a Caja as the only cash destination CTA', async () => {
    const { wrapper, router } = await mountDashboard()

    const cashCtas = buttonsWithText(wrapper, 'Ir a Caja')
    expect(cashCtas).toHaveLength(1)

    await cashCtas[0].trigger('click')
    await flushPromises()
    expect(router.currentRoute.value.path).toBe('/cash-register')

    wrapper.unmount()
  })
})

describe('dashboard action naming and button semantics (T6)', () => {
  beforeEach(() => {
    signInAs('administrador')
    getMock.mockReset()
    installPayload()
  })

  it('renders the accented Métodos de Pago sidebar entry', async () => {
    const { wrapper } = await mountDashboard()

    const sidebar = wrapper.find('[data-app-chrome="sidebar"]')
    expect(sidebar.exists()).toBe(true)
    expect(sidebar.text()).toContain('Métodos de Pago')
    expect(sidebar.text()).not.toContain('Metodos de Pago')

    const link = sidebar.find('a[href="/settings/payment-methods"]')
    expect(link.exists()).toBe(true)
    expect(link.text()).toContain('Métodos de Pago')

    wrapper.unmount()
  })

  it('names the /business-intelligence tile after the sidebar entry', async () => {
    const { wrapper } = await mountDashboard()

    const quickActions = wrapper.find('section[aria-label="Acciones rápidas"]')
    const tile = quickActions.find('[data-action="reports"]')
    expect(tile.exists()).toBe(true)
    expect(tile.find('p').text()).toBe('Business Intelligence')
    expect(tile.text()).not.toContain('Reportes')
    // The subtitle is not part of the rename.
    expect(tile.text()).toContain('Análisis y estadísticas')

    const sidebarLink = wrapper.find('[data-app-chrome="sidebar"] a[href="/business-intelligence"]')
    expect(sidebarLink.exists()).toBe(true)
    expect(sidebarLink.text()).toContain('Business Intelligence')

    wrapper.unmount()
  })

  it('renders every quick-action tile through a real type="button" control', async () => {
    const { wrapper, router } = await mountDashboard()

    const quickActions = wrapper.find('section[aria-label="Acciones rápidas"]')
    const tiles = quickActions.findAll('[data-action]')
    expect(tiles).toHaveLength(4)

    const destinations = {
      patients: '/patients',
      professionals: '/professionals',
      environments: '/environments',
      reports: '/business-intelligence'
    }

    for (const tile of tiles) {
      const action = tile.attributes('data-action')

      // The card surface is presentation only: the interactive element is
      // the native button inside it (no clickable div, no role="button").
      expect(tile.attributes('data-clickable')).not.toBe('true')
      expect(tile.find('[role="button"]').exists()).toBe(false)

      const buttons = tile.findAll('button')
      expect(buttons).toHaveLength(1)
      expect(buttons[0].attributes('type')).toBe('button')
      // The button stays a full-bleed target so the whole card remains the
      // click region, and keeps a visible keyboard focus ring.
      expect(buttons[0].classes()).toContain('w-full')
      expect(buttons[0].classes().join(' ')).toContain('focus-visible:ring-2')

      await buttons[0].trigger('click')
      await flushPromises()
      expect(router.currentRoute.value.path).toBe(destinations[action])

      await router.push('/dashboard')
      await flushPromises()
    }

    wrapper.unmount()
  })

  it('keeps every dashboard stroke on the documented 1.5 baseline', async () => {
    const { wrapper } = await mountDashboard()

    const content = wrapper.find('[data-dashboard-content]')
    expect(content.exists()).toBe(true)
    expect(content.findAll('svg').length).toBeGreaterThanOrEqual(5)

    // The 2.0 default is retired: the empty-state line art and every
    // chevron ride stroke-width="1.5" (apple-design §16 baseline).
    expect(content.findAll('[stroke-width="2"]')).toHaveLength(0)

    const calendarCta = buttonsWithText(wrapper, 'Ver calendario')[0]
    expect(calendarCta.find('svg path').attributes('stroke-width')).toBe('1.5')

    wrapper.unmount()
  })
})
