import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { createRouter, createMemoryHistory } from 'vue-router'

// T3 regression coverage: single CTA per destination. "Ver calendario" lives
// only on the Acciones rápidas header, the quick-action grid drops the
// "Nueva Cita" tile (four destinations remain), and the KPI strip is a static
// reference surface (no click affordance, no navigation). The two allowed
// appointment CTAs — the agenda header "Nueva cita" and the empty-state
// "Crear nueva cita" — still open the appointment modal.
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
    expect(quickActions.findAll('button')).toHaveLength(1)
    expect(quickActions.text()).toContain('Ver calendario')

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
      'Reportes'
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
