import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { createRouter, createMemoryHistory } from 'vue-router'

// U2 regression coverage: the week preview is retired with its endpoint and
// its grouping state. The forward-looking surface is now the "Dónde seguir"
// block, which points at the agenda instead of previewing its rows.
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
import pageSource from '../../../resources/js/modules/dashboard/DashboardPage.vue?raw'

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

describe('dashboard retired week preview (U2)', () => {
  beforeEach(() => {
    signInAs('administrador')
    getMock.mockReset()
    getMock.mockImplementation(async url => {
      if (url === '/api/dashboard/stats') return { data: { appointments_today: 1 } }
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
  })

  it('never requests the retired upcoming endpoint', async () => {
    const wrapper = await mountDashboard()

    const requested = getMock.mock.calls.map(([url]) => url)
    expect(requested).not.toContain('/api/dashboard/upcoming')

    wrapper.unmount()
  })

  it('renders no week block, day grouping or row hook', async () => {
    const wrapper = await mountDashboard()

    expect(wrapper.find('[data-dashboard-block="upcoming"]').exists()).toBe(false)
    expect(wrapper.find('[data-upcoming-surface]').exists()).toBe(false)
    expect(wrapper.findAll('[data-upcoming-group]')).toHaveLength(0)
    expect(wrapper.findAll('[data-upcoming-row]')).toHaveLength(0)
    expect(wrapper.find('[data-state="error-upcoming"]').exists()).toBe(false)

    wrapper.unmount()
  })

  it('drops the grouping state and its helpers from the page source', async () => {
    for (const deadCode of [
      'upcomingAppointments',
      'upcomingError',
      'upcomingGroups',
      'upcomingDayKey',
      'upcomingDayLabel',
      'fetchUpcomingAppointments',
      'retryUpcomingAppointments'
    ]) {
      expect(pageSource).not.toContain(deadCode)
    }

    // The forward-looking destination is the agenda link.
    expect(pageSource).toContain("to: '/calendar'")

    // The mounted page keeps its own promise: no week section in the DOM.
    const wrapper = await mountDashboard()
    expect(wrapper.text()).not.toContain('Próximos días')
    wrapper.unmount()
  })
})
