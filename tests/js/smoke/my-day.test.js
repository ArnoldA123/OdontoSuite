import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { createRouter, createMemoryHistory } from 'vue-router'

// F1 (#281) — the professional's desk renders the three buckets in order, maps
// each custody state to its one action, and posts through the API before
// reloading the queue. Transports and authorization are mocked: the desk only
// consumes their contracts.
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

vi.mock('../../../resources/js/composables/usePermissions', () => ({
  usePermissions: () => ({
    can: {
      checkInAppointment: { value: true },
      startConsultation: { value: true },
      completeConsultation: { value: true }
    }
  })
}))

vi.mock('../../../resources/js/composables/useAuth', () => ({
  useAuth: () => ({
    user: { value: { id: 7, name: 'Dra. Elsa Pérez', role: 'odontologo' } },
    isAuthenticated: { value: true }
  })
}))

vi.mock('../../../resources/js/composables/useToast', () => ({
  useToast: () => harness.toast,
  globalToasts: { value: [] }
}))

import uiComponents from '../../../resources/js/plugins/ui-components'
import Page from '../../../resources/js/modules/my-day/MyDayPage.vue'

const row = (id, status, patient, type, chair) => ({
  id,
  status,
  scheduled_at: '2026-10-21T09:30:00',
  duration_minutes: 30,
  patient: { id: id + 100, full_name: patient, phone: '999888777' },
  user: { id: 7, name: 'Dra. Elsa Pérez' },
  appointment_type: { id: 2, name: type },
  dental_chair: { id: 1, name: chair }
})

const queuePayload = () => ({
  data: {
    waiting: [row(11, 'arrived', 'Ana Torres', 'Limpieza dental', 'Sillón 1')],
    attending: [row(12, 'in_consultation', 'Luis Rojas', 'Endodoncia', 'Sillón 2')],
    upcoming: [row(13, 'confirmed', 'María Quispe', 'Control', 'Sillón 1')]
  },
  meta: {
    date: '2026-10-21',
    counts: { waiting: 1, attending: 1, upcoming: 1 }
  }
})

const mountPage = async () => {
  const router = createRouter({
    history: createMemoryHistory(),
    routes: [{ path: '/:pathMatch(.*)*', component: { template: '<div />' } }]
  })
  await router.push('/my-day')
  await router.isReady()

  const wrapper = mount(Page, { global: { plugins: [router, uiComponents] } })
  await flushPromises()
  return wrapper
}

const myDayCalls = () => harness.get.mock.calls.filter(([url]) => url === '/api/my-day').length

beforeEach(() => {
  localStorage.setItem('auth_token', 'test-token')
  localStorage.setItem(
    'user',
    JSON.stringify({ id: 7, name: 'Dra. Elsa Pérez', role: 'odontologo' })
  )
  harness.get.mockReset()
  harness.post.mockReset()
  harness.toast.success.mockReset()
  harness.toast.error.mockReset()
  harness.toast.info.mockReset()
  harness.channelStub.listen.mockReset()
  harness.channelStub.listen.mockImplementation(() => harness.channelStub)
  harness.privateChannel.mockReset()
  harness.privateChannel.mockImplementation(() => harness.channelStub)
  harness.leave.mockReset()

  harness.get.mockResolvedValue(queuePayload())
  harness.post.mockImplementation(async url => ({ data: { url } }))
})

describe('my-day desk', () => {
  it('renders the three sections in queue order', async () => {
    const wrapper = await mountPage()

    const headings = wrapper.findAll('[data-my-day-section] h3').map(node => node.text())
    expect(headings).toEqual(['En espera', 'Atendiendo', 'Próximos'])

    wrapper.unmount()
  })

  it('renders a waiting row with the patient name and time', async () => {
    const wrapper = await mountPage()

    const waitingRow = wrapper.find('[data-my-day-section="waiting"] [data-my-day-row]')
    expect(waitingRow.exists()).toBe(true)
    expect(waitingRow.find('[data-my-day-patient]').text()).toBe('Ana Torres')
    expect(waitingRow.find('[data-my-day-time]').text()).toBe('09:30')
    expect(waitingRow.text()).toContain('Limpieza dental')
    expect(waitingRow.text()).toContain('Sillón 1')

    wrapper.unmount()
  })

  it('starts the attention from the queue and reloads it', async () => {
    const wrapper = await mountPage()
    expect(myDayCalls()).toBe(1)

    await wrapper
      .find('[data-my-day-section="waiting"] [data-my-day-action="start"]')
      .trigger('click')
    await flushPromises()

    expect(harness.post).toHaveBeenCalledWith('/api/appointments/11/start-attention', {})
    expect(harness.toast.success).toHaveBeenCalledWith('Atención iniciada.')
    expect(myDayCalls()).toBe(2)

    wrapper.unmount()
  })

  it('marks the arrival from the upcoming list', async () => {
    const wrapper = await mountPage()

    await wrapper
      .find('[data-my-day-section="upcoming"] [data-my-day-action="arrive"]')
      .trigger('click')
    await flushPromises()

    expect(harness.post).toHaveBeenCalledWith('/api/appointments/13/check-in', {})
    expect(harness.toast.success).toHaveBeenCalledWith('Llegada registrada.')
    expect(myDayCalls()).toBe(2)

    wrapper.unmount()
  })

  it('offers the consultation continuation on an attended appointment', async () => {
    const wrapper = await mountPage()

    const continueAction = wrapper.find(
      '[data-my-day-section="attending"] [data-my-day-action="continue"]'
    )
    expect(continueAction.exists()).toBe(true)
    expect(continueAction.text()).toBe('Continuar consulta')
    expect(
      wrapper.find('[data-my-day-section="waiting"] [data-my-day-action="arrive"]').exists()
    ).toBe(false)

    wrapper.unmount()
  })

  it('renders the empty copy of a bucket with no rows', async () => {
    const payload = queuePayload()
    payload.data.upcoming = []
    payload.meta.counts.upcoming = 0
    harness.get.mockResolvedValue(payload)

    const wrapper = await mountPage()

    const empty = wrapper.find('[data-my-day-empty="upcoming"]')
    expect(empty.exists()).toBe(true)
    expect(empty.text()).toContain('Sin citas pendientes hoy')

    wrapper.unmount()
  })
})
