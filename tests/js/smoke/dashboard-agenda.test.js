import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { createRouter, createMemoryHistory } from 'vue-router'

// U2 regression coverage: the dashboard no longer lists appointments. The
// agenda lives in /calendar; the page keeps the New Appointment modal only
// because /appointments/new redirects here with `?openAppointmentModal=true`.
const { getMock, installPayload } = vi.hoisted(() => {
  const getMock = vi.fn()

  const installPayload = () => {
    getMock.mockImplementation(async url => {
      if (url === '/api/dashboard/stats') return { data: { appointments_today: 2 } }
      if (url === '/api/cash-register/current') {
        return {
          data: {
            session: { id: 7, status: 'open', opened_at: '2026-10-04T08:30:00' },
            summary: { opening_amount: 100, expected_amount: 280 }
          }
        }
      }
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

const mountDashboard = async (path = '/dashboard') => {
  const router = createRouter({
    history: createMemoryHistory(),
    routes: [{ path: '/:pathMatch(.*)*', component: { template: '<div />' } }]
  })
  await router.push(path)
  await router.isReady()

  const wrapper = mount(Page, { global: { plugins: [router, uiComponents] } })
  await flushPromises()
  return { wrapper, router }
}

describe('dashboard retired agenda (U2)', () => {
  beforeEach(() => {
    signInAs('administrador')
    getMock.mockReset()
    installPayload()
  })

  it('renders no agenda list, row hook or appointment CTA', async () => {
    const { wrapper } = await mountDashboard()

    expect(wrapper.find('section[aria-label="Agenda de hoy"]').exists()).toBe(false)
    expect(wrapper.findAll('[data-appointment-row]')).toHaveLength(0)
    expect(wrapper.find('[data-state="empty-appointments"]').exists()).toBe(false)
    expect(wrapper.find('[data-state="error-appointments"]').exists()).toBe(false)

    // The header CTA went with its section: the agenda owns appointment
    // creation now.
    const newAppointmentCtas = wrapper
      .findAll('button')
      .filter(button => button.text().trim() === 'Nueva cita')
    expect(newAppointmentCtas).toHaveLength(0)

    wrapper.unmount()
  })

  it('keeps the New Appointment modal mounted for the /appointments/new redirect', async () => {
    const { wrapper } = await mountDashboard()

    const modal = wrapper.findComponent(NewAppointmentModal)
    expect(modal.exists()).toBe(true)
    expect(modal.props('modelValue')).toBe(false)

    wrapper.unmount()
  })

  it('opens the modal from the redirect query and clears it', async () => {
    const { wrapper, router } = await mountDashboard('/dashboard?openAppointmentModal=true')
    await flushPromises()

    expect(wrapper.findComponent(NewAppointmentModal).props('modelValue')).toBe(true)
    expect(router.currentRoute.value.query.openAppointmentModal).toBeUndefined()

    wrapper.unmount()
  })
})
