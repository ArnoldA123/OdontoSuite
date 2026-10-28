import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { createRouter, createMemoryHistory } from 'vue-router'

// #389 (S4) — the desk's error copy. `useApi` rejects a transport failure with
// the native `TypeError('Failed to fetch')`, which has no `response`: the desk
// must fall back to its Spanish sentence instead of echoing the browser text.
// A domain error (a custody 409) keeps the server message, and the retry still
// recovers the queue.
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

const row = (id, status, patient) => ({
  id,
  status,
  scheduled_at: '2026-10-21T09:30:00',
  duration_minutes: 30,
  patient: { id: id + 100, full_name: patient, phone: '999888777' },
  user: { id: 7, name: 'Dra. Elsa Pérez' },
  appointment_type: { id: 2, name: 'Limpieza dental' },
  dental_chair: { id: 1, name: 'Sillón 1' }
})

const queuePayload = () => ({
  data: {
    waiting: [row(11, 'arrived', 'Ana Torres')],
    attending: [],
    upcoming: [],
    ready_lab_cases: []
  },
  meta: {
    date: '2026-10-21',
    counts: { waiting: 1, attending: 0, upcoming: 0, ready_lab_cases: 0 }
  }
})

const makeRouter = async () => {
  const router = createRouter({
    history: createMemoryHistory(),
    routes: [{ path: '/:pathMatch(.*)*', component: { template: '<div />' } }]
  })
  await router.push('/my-day')
  await router.isReady()
  return router
}

const mountPage = async () => {
  const router = await makeRouter()
  const wrapper = mount(Page, { global: { plugins: [router, uiComponents] } })
  await flushPromises()
  return wrapper
}

const countTexts = wrapper => wrapper.findAll('[data-my-day-count] dd').map(dd => dd.text())

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
})

// ORDER MATTERS: useMyDay() keeps its state at module scope, so the first row
// must stay first: it is the only one that can observe `hasLoaded` still false
// and therefore the counters as unknown (`—`). The other rows run after a
// successful load has landed the real counts.
describe('my-day error message (#389)', () => {
  it('a transport failure explains itself in Spanish, never with the browser text', async () => {
    harness.get.mockRejectedValue(new TypeError('Failed to fetch'))

    const wrapper = await mountPage()

    expect(wrapper.find('[data-my-day-error]').isVisible()).toBe(true)
    expect(wrapper.find('[data-my-day-error-message]').text()).toBe('No se pudo cargar tu día')
    expect(wrapper.find('[data-my-day-error]').text()).not.toContain('Failed to fetch')
    expect(wrapper.find('[data-my-day-retry]').isVisible()).toBe(true)
    // The first load never answered, so the counters stay unknown (#387).
    expect(countTexts(wrapper)).toEqual(['—', '—', '—', '—'])

    wrapper.unmount()
  })

  it('a domain error keeps the server message', async () => {
    harness.get.mockRejectedValue({
      status: 409,
      response: {
        data: { error: { message: 'La atención todavía no puede iniciarse.' } }
      }
    })

    const wrapper = await mountPage()

    expect(wrapper.find('[data-my-day-error-message]').text()).toBe(
      'La atención todavía no puede iniciarse.'
    )

    wrapper.unmount()
  })

  it('the retry still recovers the queue', async () => {
    harness.get
      .mockRejectedValueOnce(new TypeError('Failed to fetch'))
      .mockResolvedValue(queuePayload())

    const wrapper = await mountPage()

    expect(wrapper.find('[data-my-day-error]').isVisible()).toBe(true)
    expect(wrapper.find('[data-my-day-error-message]').text()).toBe('No se pudo cargar tu día')

    await wrapper.find('[data-my-day-retry]').trigger('click')
    await flushPromises()

    expect(wrapper.find('[data-my-day-error]').exists()).toBe(false)
    expect(wrapper.findAll('[data-my-day-section="waiting"] [data-my-day-row]')).toHaveLength(1)

    wrapper.unmount()
  })
})
