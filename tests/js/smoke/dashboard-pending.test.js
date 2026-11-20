import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { createRouter, createMemoryHistory } from 'vue-router'

// U1 / U2 coverage: the commercial pending answers moved to the Pendientes
// desk. The dashboard no longer asks for them, renders no merged row and keeps
// no gating state; the receiving section is exercised by continuity.test.js.
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
import continuitySource from '../../../resources/js/modules/continuity/ContinuityPage.vue?raw'

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

describe('dashboard retired pending block (U2)', () => {
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
      // Even if the page asked, this payload would render a merged row.
      if (url === '/api/dashboard/pending') {
        return {
          data: {
            quotations: {
              count: 1,
              items: [
                {
                  id: 11,
                  patient_name: 'Ana Torres Quispe',
                  total_amount: 149.9,
                  status: 'sent',
                  created_at: '2026-10-03T10:00:00.000Z'
                }
              ]
            }
          }
        }
      }
      return { data: [] }
    })
  })

  it('never requests the pending endpoint from the dashboard', async () => {
    const wrapper = await mountDashboard()

    const requested = getMock.mock.calls.map(([url]) => url)
    expect(requested).not.toContain('/api/dashboard/pending')

    wrapper.unmount()
  })

  it('renders no pending section, row or error block', async () => {
    const wrapper = await mountDashboard()

    expect(wrapper.find('[data-dashboard-block="attention"]').exists()).toBe(false)
    expect(wrapper.find('section[aria-label="Requiere tu atención"]').exists()).toBe(false)
    expect(wrapper.findAll('[data-pending-row]')).toHaveLength(0)
    expect(wrapper.find('[data-state="error-pending"]').exists()).toBe(false)
    expect(wrapper.find('[data-state="empty-pending"]').exists()).toBe(false)

    wrapper.unmount()
  })

  it('drops the merged-row model and its handlers from the page source', async () => {
    for (const deadCode of [
      'pendingRows',
      'PENDING_TYPE_META',
      'fetchPending',
      'retryPending',
      'goToPendingEntity',
      'data-pending-row'
    ]) {
      expect(pageSource).not.toContain(deadCode)
    }

    // The receiving surface is the Pendientes desk.
    expect(continuitySource).toContain("get('/api/dashboard/pending')")
    expect(continuitySource).toContain('data-continuity-section="pending"')
  })
})
