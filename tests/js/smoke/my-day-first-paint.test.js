import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { createRouter, createMemoryHistory } from 'vue-router'

// #387 (S2) — Mi día paints the skeleton from its first frame and never shows
// a zero counter while the first queue response is in flight. Before the fix
// `loading` started false (so the sections painted empty) and the counters
// rendered 0 as if the queue were empty.
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
    attending: [row(12, 'in_consultation', 'Luis Rojas')],
    upcoming: [row(13, 'confirmed', 'María Quispe')],
    ready_lab_cases: []
  },
  meta: {
    date: '2026-10-21',
    counts: { waiting: 1, attending: 1, upcoming: 1, ready_lab_cases: 0 }
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

// Synchronous mount on purpose: no `await` may sit between mount() and the
// first-frame assertions, or the queue response would already have landed.
const mountPage = router => mount(Page, { global: { plugins: [router, uiComponents] } })

const countTexts = wrapper => wrapper.findAll('[data-my-day-count] dd').map(dd => dd.text())

beforeEach(() => {
  localStorage.setItem('auth_token', 'test-token')
  localStorage.setItem(
    'user',
    JSON.stringify({ id: 7, name: 'Dra. Elsa Pérez', role: 'odontologo' })
  )
  harness.get.mockReset()
  harness.post.mockReset()
  harness.channelStub.listen.mockReset()
  harness.channelStub.listen.mockImplementation(() => harness.channelStub)
  harness.privateChannel.mockReset()
  harness.privateChannel.mockImplementation(() => harness.channelStub)
  harness.leave.mockReset()
  harness.toast.success.mockReset()
  harness.toast.error.mockReset()
  harness.toast.info.mockReset()

  harness.get.mockResolvedValue(queuePayload())
})

// ORDER MATTERS: useMyDay() keeps its state at module scope, so the first test
// of this file is the only one that can observe the very first frame.
describe('my-day first paint (#387)', () => {
  it('renders the loading skeleton on the first frame and no fake counters', async () => {
    const router = await makeRouter()
    const wrapper = mountPage(router)

    // No flush yet: this is the first frame.
    expect(wrapper.find('[data-my-day-loading]').exists()).toBe(true)
    expect(wrapper.findAll('[data-my-day-section]')).toHaveLength(0)
    expect(wrapper.html()).not.toContain('data-my-day-empty')
    expect(countTexts(wrapper)).toEqual(['—', '—', '—', '—'])

    await flushPromises()

    expect(wrapper.find('[data-my-day-loading]').exists()).toBe(false)
    expect(wrapper.find('[data-my-day-section="waiting"]').exists()).toBe(true)
    expect(wrapper.find('[data-my-day-section="attending"]').exists()).toBe(true)
    expect(wrapper.find('[data-my-day-section="upcoming"]').exists()).toBe(true)
    expect(countTexts(wrapper)).toEqual(['1', '1', '1', '0'])

    wrapper.unmount()
  })
})
