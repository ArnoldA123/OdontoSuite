import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { createRouter, createMemoryHistory } from 'vue-router'

// D5 / R4 regression coverage adapted to the three destinations: the
// "Acciones rápidas" block that re-listed the whole sidebar is gone, the page
// now owns exactly three link cards (Agenda, Mi jornada, Pendientes), the KPI
// strip stays a static reference surface and the cash card keeps its single
// contextual CTA. No other sidebar destination is duplicated in the content.
const { getMock, installPayload } = vi.hoisted(() => {
  const getMock = vi.fn()

  const installPayload = (stats = {}) => {
    getMock.mockImplementation(async url => {
      if (url === '/api/dashboard/stats') return { data: stats }
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

describe('dashboard destinations and single CTA per surface (D5/R4)', () => {
  beforeEach(() => {
    signInAs('administrador')
    getMock.mockReset()
    installPayload()
  })

  it('renders no quick-actions block and no sidebar-duplicating navigation', async () => {
    const { wrapper } = await mountDashboard()

    expect(wrapper.find('section[aria-label="Acciones rápidas"]').exists()).toBe(false)
    expect(wrapper.text()).not.toContain('Acciones rápidas')
    expect(wrapper.findAll('[data-action]')).toHaveLength(0)

    // The only content links are the three destinations of the new block: the
    // page never re-lists the sidebar.
    const content = wrapper.find('[data-dashboard-content]')
    expect(content.exists()).toBe(true)
    for (const href of [
      '/patients',
      '/professionals',
      '/environments',
      '/business-intelligence',
      '/cash-register',
      '/quotations',
      '/treatment-plans'
    ]) {
      expect(content.find(`a[href="${href}"]`).exists()).toBe(false)
    }

    wrapper.unmount()
  })

  it('renders the two KPI cards without a click affordance', async () => {
    const { wrapper, router } = await mountDashboard()

    const cards = wrapper.findAll('[data-stat-card]')
    expect(cards).toHaveLength(2)

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

  it('keeps the cash card Ir a Caja as the only cash destination CTA (D4)', async () => {
    const { wrapper, router } = await mountDashboard()

    const cashCtas = buttonsWithText(wrapper, 'Ir a Caja')
    expect(cashCtas).toHaveLength(1)

    const card = wrapper.find('[data-stat-card="cash-balance"]')
    expect(card.exists()).toBe(true)
    expect(card.find('button').text()).toContain('Ir a Caja')
    expect(wrapper.find('[data-dashboard-header]').text()).not.toContain('Ir a Caja')

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

  it('renders the Métodos de pago sidebar entry', async () => {
    const { wrapper } = await mountDashboard()

    const sidebar = wrapper.find('[data-app-chrome="sidebar"]')
    expect(sidebar.exists()).toBe(true)
    expect(sidebar.text()).toContain('Métodos de pago')
    expect(sidebar.text()).not.toContain('Metodos de Pago')
    expect(sidebar.text()).toContain('Pendientes')
    expect(sidebar.text()).not.toContain('Continuidad')

    const link = sidebar.find('a[href="/settings/payment-methods"]')
    expect(link.exists()).toBe(true)
    expect(link.text()).toContain('Métodos de pago')

    wrapper.unmount()
  })

  it('names the Business Intelligence destination only in the sidebar', async () => {
    const { wrapper } = await mountDashboard()

    // The dashboard renders no BI tile anymore; the sidebar owns the name.
    const content = wrapper.find('[data-dashboard-content]')
    expect(content.text()).not.toContain('Business Intelligence')
    expect(wrapper.text()).not.toContain('Reportes')

    const sidebarLink = wrapper.find('[data-app-chrome="sidebar"] a[href="/business-intelligence"]')
    expect(sidebarLink.exists()).toBe(true)
    expect(sidebarLink.text()).toContain('Business Intelligence')

    wrapper.unmount()
  })

  it('renders every remaining dashboard action as a real button control', async () => {
    const { wrapper } = await mountDashboard()

    const content = wrapper.find('[data-dashboard-content]')
    const buttons = content.findAll('button')
    expect(buttons.length).toBeGreaterThanOrEqual(2)

    for (const button of buttons) {
      // Real controls only: no clickable div masquerading as a button.
      expect(button.element.tagName.toLowerCase()).toBe('button')
      expect(button.attributes('role')).not.toBe('button')
    }

    // The destination cards are links, not fake buttons.
    for (const link of content.findAll('[data-dashboard-destination]')) {
      expect(link.element.tagName.toLowerCase()).toBe('a')
    }

    expect(content.find('[data-action]').exists()).toBe(false)

    wrapper.unmount()
  })

  it('keeps every dashboard stroke on the documented 1.5 baseline', async () => {
    const { wrapper } = await mountDashboard()

    const content = wrapper.find('[data-dashboard-content]')
    expect(content.findAll('svg').length).toBeGreaterThanOrEqual(1)

    // The 2.0 default is retired (apple-design §16 baseline).
    expect(content.findAll('[stroke-width="2"]')).toHaveLength(0)

    wrapper.unmount()
  })
})
