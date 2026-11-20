import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { createRouter, createMemoryHistory } from 'vue-router'

// U2 regression coverage: GET /api/dashboard/appointments-today no longer has
// a reader on the dashboard. The day count is published by the summary and the
// card only appears for the desks that may read appointments.
const { getMock, installPayload } = vi.hoisted(() => {
  const getMock = vi.fn()

  const installPayload = (stats = {}) => {
    getMock.mockImplementation(async url => {
      if (url === '/api/dashboard/stats') return { data: stats }
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
  return wrapper
}

const requestedUrls = () => getMock.mock.calls.map(([url]) => url)

describe('dashboard retired today list (U2)', () => {
  beforeEach(() => {
    signInAs('administrador')
    getMock.mockReset()
    installPayload({ appointments_today: 4 })
  })

  it('never requests the retired appointments-today endpoint', async () => {
    const wrapper = await mountDashboard()

    expect(requestedUrls()).not.toContain('/api/dashboard/appointments-today')
    expect(requestedUrls()).toContain('/api/dashboard/stats')

    wrapper.unmount()
  })

  it('publishes the day count from the summary it already fetches', async () => {
    const wrapper = await mountDashboard()

    expect(wrapper.find('[data-stat="appointments-today"]').text()).toBe('4')
    expect(wrapper.find('[data-kpi-caption="appointments-today"]').text()).toBe('Agenda del día')

    wrapper.unmount()
  })

  it('hides the day card from a role that cannot read appointments', async () => {
    signInAs('finanzas')

    const wrapper = await mountDashboard()

    expect(wrapper.find('[data-stat-card="appointments-today"]').exists()).toBe(false)
    // The cash card is the one this desk keeps.
    expect(wrapper.find('[data-stat-card="cash-balance"]').exists()).toBe(true)

    wrapper.unmount()
  })

  it('hides the cash card from a clinical role without view.cash_register', async () => {
    signInAs('odontologo')

    const wrapper = await mountDashboard()

    expect(wrapper.find('[data-stat-card="appointments-today"]').exists()).toBe(true)
    expect(wrapper.find('[data-stat-card="cash-balance"]').exists()).toBe(false)

    wrapper.unmount()
  })
})
