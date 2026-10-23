import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { createRouter, createMemoryHistory } from 'vue-router'

// F4 (#310) — the reception desk of continuity renders the two populations the
// queue publishes, the recall metric verbatim, and the complete load states.
// Transports and authorization are mocked: the page only consumes their
// contracts.
const harness = vi.hoisted(() => ({
  get: vi.fn(),
  post: vi.fn(),
  channelStub: { listen: vi.fn() },
  privateChannel: vi.fn(),
  leave: vi.fn(),
  toast: { success: vi.fn(), error: vi.fn(), info: vi.fn() }
}))

vi.mock('../../../resources/js/composables/useApi', () => ({
  useApi: () => ({
    get: harness.get,
    post: harness.post,
    put: vi.fn(),
    patch: vi.fn(),
    delete: vi.fn(),
    setToken: () => {},
    normalizeError: () => ''
  })
}))

vi.mock('../../../resources/js/composables/useEcho', () => ({
  useEcho: () => ({
    echo: { leave: harness.leave },
    channel: () => harness.channelStub,
    privateChannel: harness.privateChannel,
    connectionStatus: { value: 'connected' }
  })
}))

vi.mock('../../../resources/js/composables/useWebSocketNotifications', () => ({
  useWebSocketNotifications: () => ({})
}))

vi.mock('../../../resources/js/composables/useAuth', () => ({
  useAuth: () => ({
    user: { value: { id: 4, name: 'Rosa Recepción', role: 'recepcionista' } },
    isAuthenticated: { value: true }
  })
}))

vi.mock('../../../resources/js/composables/useToast', () => ({
  useToast: () => harness.toast,
  globalToasts: { value: [] }
}))

import uiComponents from '../../../resources/js/plugins/ui-components'
import Page from '../../../resources/js/modules/continuity/ContinuityPage.vue'

const recall = (id, patient, reason, dueDate, months = 6) => ({
  id,
  patient_id: id + 100,
  interval_months: months,
  reason,
  due_date: dueDate,
  status: 'pending',
  notes: null,
  patient: { id: id + 100, first_name: patient[0], last_name: patient[1] }
})

const request = (id, extra = {}) => ({
  id,
  patient_id: id + 200,
  kind: 'next_session',
  status: 'pending',
  preferred_from: '2026-11-02T09:00:00',
  preferred_to: '2026-11-02T09:30:00',
  duration_minutes: 30,
  notes: null,
  appointment_type_id: 2,
  treatment_plan_id: null,
  clinical_encounter_id: 8,
  appointment_id: 14,
  patient: { id: id + 200, full_name: 'Luis Rojas' },
  appointment_type: { id: 2, name: 'Control' },
  treatment_plan: null,
  clinical_encounter: { id: 8 },
  ...extra
})

const queuePayload = (recalls, requests) => ({
  data: {
    recalls,
    requests,
    counts: {
      recalls: recalls.length,
      requests: requests.length,
      total: recalls.length + requests.length
    }
  },
  meta: { date: '2026-10-23' }
})

const fullPayload = () =>
  queuePayload(
    [
      recall(31, ['Ana', 'Torres'], 'Control semestral', '2026-10-01', 6),
      recall(32, ['María', 'Quispe'], 'Revisión anual', '2026-09-15', 12)
    ],
    [request(41)]
  )

const emptyPayload = () => queuePayload([], [])

const metricsPayload = rate => ({
  data: {
    total: 10,
    pending: 2,
    scheduled: 3,
    completed: rate === null ? 0 : 5,
    cancelled: 1,
    overdue: 2,
    compliance_rate: rate
  }
})

const mockApi = (queueResponse, rate = 0.6667) => {
  harness.get.mockImplementation(async url =>
    url === '/api/patient-recalls/metrics' ? metricsPayload(rate) : queueResponse
  )
}

const mountPage = async () => {
  const router = createRouter({
    history: createMemoryHistory(),
    routes: [{ path: '/:pathMatch(.*)*', component: { template: '<div />' } }]
  })
  await router.push('/continuity')
  await router.isReady()

  const wrapper = mount(Page, { global: { plugins: [router, uiComponents] } })
  await flushPromises()
  return wrapper
}

const queueCalls = () =>
  harness.get.mock.calls.filter(([url]) => url === '/api/continuity/queue').length

beforeEach(() => {
  localStorage.setItem('auth_token', 'test-token')
  localStorage.setItem(
    'user',
    JSON.stringify({ id: 4, name: 'Rosa Recepción', role: 'recepcionista' })
  )
  harness.get.mockReset()
  harness.post.mockReset()
  harness.toast.success.mockReset()
  harness.toast.error.mockReset()
  harness.toast.info.mockReset()
  harness.privateChannel.mockReset()
  harness.leave.mockReset()
})

describe('continuity desk', () => {
  it('renders the hero and the four KPIs from the API payload', async () => {
    mockApi(fullPayload())

    const wrapper = await mountPage()

    expect(wrapper.find('[data-continuity-hero]').exists()).toBe(true)
    expect(wrapper.find('[data-continuity-title]').text()).toBe('Cola de continuidad')

    expect(wrapper.find('[data-kpi-card="recalls"] [data-kpi-value]').text()).toBe('2')
    expect(wrapper.find('[data-kpi-card="requests"] [data-kpi-value]').text()).toBe('1')
    expect(wrapper.find('[data-kpi-card="total"] [data-kpi-value]').text()).toBe('3')
    expect(wrapper.find('[data-kpi-card="compliance"] [data-kpi-value]').text()).toBe('67%')

    wrapper.unmount()
  })

  it('shows the compliance rate the API published, verbatim', async () => {
    mockApi(fullPayload(), 0.5)

    const wrapper = await mountPage()

    const raw = wrapper.find('[data-kpi-card="compliance"] [data-kpi-raw]')
    expect(raw.attributes('data-kpi-raw')).toBe('0.5')
    expect(wrapper.find('[data-kpi-card="compliance"] [data-kpi-value]').text()).toBe('50%')

    wrapper.unmount()
  })

  it('renders both populations with their mapped columns', async () => {
    mockApi(fullPayload())

    const wrapper = await mountPage()

    const recalls = wrapper.find('[data-continuity-section="recalls"]')
    expect(recalls.find('[data-continuity-table="recalls"]').exists()).toBe(true)
    const recallText = recalls.text()
    expect(recallText).toContain('Ana Torres')
    expect(recallText).toContain('Control semestral')
    expect(recallText).toContain('01 oct 2026')
    expect(recallText).toContain('6 meses')
    expect(recallText).toContain('María Quispe')
    expect(recallText).toContain('12 meses')
    expect(recallText).toContain('Vencidos y sin cita agendada')
    // Unit A2 fills this column; T2 only has to publish it.
    expect(recallText).toContain('Acciones')

    const requests = wrapper.find('[data-continuity-section="requests"]')
    expect(requests.find('[data-continuity-table="requests"]').exists()).toBe(true)
    const requestText = requests.text()
    expect(requestText).toContain('Luis Rojas')
    expect(requestText).toContain('Próxima sesión')
    expect(requestText).toContain('02 nov 2026, 09:00 – 09:30')
    expect(requestText).toContain('30 min')
    expect(requestText).toContain('Atención')
    expect(requestText).toContain('Conducta pendiente de materializar')
    expect(requestText).toContain('Acciones')

    wrapper.unmount()
  })

  it('renders the two empty states when both arrays come back empty', async () => {
    mockApi(emptyPayload())

    const wrapper = await mountPage()

    expect(wrapper.find('[data-continuity-empty="recalls"]').exists()).toBe(true)
    expect(wrapper.find('[data-continuity-empty="requests"]').exists()).toBe(true)
    expect(wrapper.find('[data-continuity-table="recalls"]').exists()).toBe(false)
    expect(wrapper.find('[data-continuity-table="requests"]').exists()).toBe(false)

    wrapper.unmount()
  })

  it('reports a failed load and refetches the queue from the retry button', async () => {
    // Module state is a singleton: start from a known empty queue so the retry
    // panel is the only section surface left after the failure.
    mockApi(emptyPayload())
    const primer = await mountPage()
    primer.unmount()

    harness.get.mockRejectedValue({
      response: { data: { message: 'No se pudo conectar con el servidor.' } }
    })

    const wrapper = await mountPage()

    const panel = wrapper.find('[data-continuity-error]')
    expect(panel.exists()).toBe(true)
    expect(panel.attributes('role')).toBe('alert')
    expect(panel.text()).toContain('No se pudo conectar con el servidor.')
    expect(wrapper.find('[data-continuity-table="recalls"]').exists()).toBe(false)
    expect(wrapper.find('[data-continuity-empty="recalls"]').exists()).toBe(false)

    const before = queueCalls()
    mockApi(fullPayload())
    await wrapper.find('[data-continuity-retry]').trigger('click')
    await flushPromises()

    expect(queueCalls()).toBe(before + 1)
    expect(wrapper.find('[data-continuity-error]').exists()).toBe(false)
    expect(wrapper.find('[data-continuity-table="recalls"]').exists()).toBe(true)

    wrapper.unmount()
  })
})
