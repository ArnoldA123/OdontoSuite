import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { createRouter, createMemoryHistory } from 'vue-router'

// #388 regression coverage: the summary (GET /api/dashboard/stats) is one
// source among four. A failure of the summary keeps its error block in its
// own place and must NOT unmount the agenda, the pending rows or the week
// preview when their own requests answered 200. The summary retry re-issues
// ONLY that resource, so the other blocks are never re-fetched nor unmounted.
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

const makeAppointment = (
  id,
  scheduledAt = `2026-10-04T${String(8 + id).padStart(2, '0')}:30:00-05:00`
) => ({
  id,
  scheduled_at: scheduledAt,
  status: 'confirmed',
  patient: {
    id: 100 + id,
    full_name: `Paciente ${id}`,
    first_name: 'Paciente',
    last_name: `${id}`
  },
  appointment_type: { id: 3, name: 'Limpieza dental' },
  user: { id: 2, name: 'Dra. Carla Núñez' }
})

const pendingQuotations = {
  quotations: {
    items: [
      {
        id: 41,
        patient_name: 'Ana Torres',
        total_amount: '120.00',
        status: 'sent',
        created_at: '2026-10-20T10:00:00'
      }
    ]
  }
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
const todayCalls = () => callsTo('/api/dashboard/appointments-today')
const upcomingCalls = () => callsTo('/api/dashboard/upcoming')
const pendingCalls = () => callsTo('/api/dashboard/pending')

// The three data blocks, plus their rows: mounting is measured on each
// block's own hook so a summary failure cannot hide them behind a parent
// conditional.
const BLOCK_HOOKS = ['hoy', 'attention', 'upcoming']

const expectIsolatedBlocks = wrapper => {
  for (const hook of BLOCK_HOOKS) {
    expect(wrapper.find(`[data-dashboard-block="${hook}"]`).exists(), hook).toBe(true)
  }
  expect(wrapper.findAll('[data-appointment-row]')).toHaveLength(2)
  expect(wrapper.findAll('[data-pending-row]')).toHaveLength(1)
  expect(wrapper.findAll('[data-upcoming-row]')).toHaveLength(1)
}

// Install the four sources with the summary failing on its first attempt.
const installSourcesWithFailingSummary = () => {
  let statsAttempt = 0
  getMock.mockImplementation(async url => {
    if (url === '/api/dashboard/stats') {
      statsAttempt += 1
      if (statsAttempt === 1) throw { status: 500 }
      return { data: {} }
    }
    if (url === '/api/dashboard/appointments-today') {
      return { data: [makeAppointment(1), makeAppointment(2)] }
    }
    if (url === '/api/dashboard/upcoming') {
      return { data: [makeAppointment(3, '2026-10-06T09:00:00-05:00')] }
    }
    if (url === '/api/dashboard/pending') return { data: pendingQuotations }
    return { data: [] }
  })
}

describe('dashboard section isolation (#388)', () => {
  beforeEach(() => {
    signInAs('administrador')
    getMock.mockReset()
  })

  it('keeps hoy, attention and upcoming mounted when only the summary fails', async () => {
    installSourcesWithFailingSummary()

    const { wrapper } = await mountDashboard()

    expect(wrapper.find('[data-state="error-stats"]').exists()).toBe(true)
    expectIsolatedBlocks(wrapper)
    expect(wrapper.find('[data-stat="appointments-today"]').text()).toBe('2')

    wrapper.unmount()
  })

  it('retries only the summary and leaves the other blocks mounted', async () => {
    installSourcesWithFailingSummary()

    const { wrapper } = await mountDashboard()

    expect(wrapper.find('[data-state="error-stats"]').exists()).toBe(true)
    expect(statsCalls()).toHaveLength(1)
    expect(todayCalls()).toHaveLength(1)
    expect(upcomingCalls()).toHaveLength(1)
    expect(pendingCalls()).toHaveLength(1)

    await wrapper.find('[data-retry-stats]').trigger('click')
    await flushPromises()
    await flushPromises()

    expect(statsCalls()).toHaveLength(2)
    expect(todayCalls()).toHaveLength(1)
    expect(upcomingCalls()).toHaveLength(1)
    expect(pendingCalls()).toHaveLength(1)

    expect(wrapper.find('[data-state="error-stats"]').exists()).toBe(false)
    expectIsolatedBlocks(wrapper)

    wrapper.unmount()
  })
})
