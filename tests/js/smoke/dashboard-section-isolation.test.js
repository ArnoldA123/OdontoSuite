import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { createRouter, createMemoryHistory } from 'vue-router'

// #388 regression coverage adapted to U2: the summary is an independent
// source. A failure of GET /api/dashboard/stats keeps its error block in its
// own place and must NOT unmount the destinations block nor the cash card.
// Reintentar re-issues ONLY the summary, so nothing else is re-fetched.
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

const cashCurrent = {
  session: { id: 7, status: 'open', opened_at: '2026-10-04T08:30:00' },
  summary: { opening_amount: 100, total_income: 200, total_expenses: 20, expected_amount: 280 }
}

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
  await flushPromises()
  return { wrapper, router }
}

const callsTo = url => getMock.mock.calls.filter(([requested]) => requested === url)
const statsCalls = () => callsTo('/api/dashboard/stats')
const cashCalls = () => callsTo('/api/cash-register/current')

// The static block and the cash card: mounting is measured on each block's own
// hook so a summary failure cannot hide them behind a parent conditional.
const expectIsolatedBlocks = wrapper => {
  expect(wrapper.find('[data-dashboard-block="destinations"]').exists()).toBe(true)
  expect(wrapper.find('[data-stat-card="cash-balance"]').exists()).toBe(true)
  expect(wrapper.findAll('[data-dashboard-destination]')).toHaveLength(3)
}

// Install the sources with the summary failing on its first attempt.
const installSourcesWithFailingSummary = () => {
  let statsAttempt = 0
  getMock.mockImplementation(async url => {
    if (url === '/api/dashboard/stats') {
      statsAttempt += 1
      if (statsAttempt === 1) throw { status: 500 }
      return { data: { appointments_today: 4 } }
    }
    if (url === '/api/cash-register/current') return { data: cashCurrent }
    return { data: [] }
  })
}

describe('dashboard section isolation (#388)', () => {
  beforeEach(() => {
    signInAs('administrador')
    getMock.mockReset()
  })

  it('keeps the destinations and the cash card mounted when only the summary fails', async () => {
    installSourcesWithFailingSummary()

    const { wrapper } = await mountDashboard()

    expect(wrapper.find('[data-state="error-stats"]').exists()).toBe(true)
    expectIsolatedBlocks(wrapper)
    expect(wrapper.find('[data-stat="appointments-today"]').text()).toBe('N/D')

    wrapper.unmount()
  })

  it('retries only the summary and leaves the rest mounted', async () => {
    installSourcesWithFailingSummary()

    const { wrapper } = await mountDashboard()

    expect(wrapper.find('[data-state="error-stats"]').exists()).toBe(true)
    expect(statsCalls()).toHaveLength(1)
    expect(cashCalls()).toHaveLength(1)

    await wrapper.find('[data-retry-stats]').trigger('click')
    await flushPromises()
    await flushPromises()

    expect(statsCalls()).toHaveLength(2)
    expect(cashCalls()).toHaveLength(1)

    expect(wrapper.find('[data-state="error-stats"]').exists()).toBe(false)
    expect(wrapper.find('[data-stat="appointments-today"]').text()).toBe('4')
    expectIsolatedBlocks(wrapper)

    wrapper.unmount()
  })
})
