import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { createRouter, createMemoryHistory } from 'vue-router'

// #396 S6: a failed range read must render an explicit error block with a
// retry, never the empty agenda block. The API mock fails only the first
// `/api/appointments` read so the retry can prove recovery without reload.
const apiState = vi.hoisted(() => ({
  appointmentReads: 0,
  appointmentPayload: []
}))

vi.mock('../../../resources/js/composables/useApi', () => ({
  useApi: () => ({
    get: async url => {
      if (url !== '/api/appointments') return { data: [], meta: {} }
      apiState.appointmentReads += 1
      if (apiState.appointmentReads === 1) {
        throw {
          response: { status: 500, data: { message: 'La agenda no está disponible' } }
        }
      }
      return { data: JSON.parse(JSON.stringify(apiState.appointmentPayload)) }
    },
    post: async () => ({ data: {} }),
    put: async () => ({ data: {} }),
    patch: async () => ({ data: {} }),
    del: async () => ({ data: {} }),
    setToken: () => {},
    normalizeError: (err, fallback) => err?.response?.data?.message || fallback
  })
}))

const channelStub = { listen: () => channelStub }
vi.mock('../../../resources/js/composables/useEcho', () => ({
  useEcho: () => ({
    echo: { leave: () => {} },
    channel: () => channelStub,
    privateChannel: () => channelStub,
    connectionStatus: { value: 'connected' }
  })
}))
vi.mock('../../../resources/js/composables/useWebSocketNotifications', () => ({
  useWebSocketNotifications: () => ({})
}))

vi.mock('../../../resources/js/composables/useAuth', async () => {
  const { computed } = await import('vue')
  return {
    useAuth: () => ({
      user: computed(() => ({
        id: 1,
        name: 'Test User',
        role: 'administrador',
        permissions: [
          'manage.appointment',
          'complete.appointment',
          'check_in.appointment',
          'start.consultation',
          'announce.patient'
        ]
      })),
      token: computed(() => 'test-token'),
      isAuthenticated: computed(() => true),
      isLoading: computed(() => false),
      login: async () => {},
      logout: async () => {},
      authLogout: async () => {},
      getCurrentUser: async () => {},
      hasRole: () => true,
      hasAnyRole: () => true
    })
  }
})

import uiComponents from '../../../resources/js/plugins/ui-components'
import Page from '../../../resources/js/modules/appointments/CalendarPage.vue'

const router = createRouter({
  history: createMemoryHistory(),
  routes: [{ path: '/:pathMatch(.*)*', component: { template: '<div />' } }]
})

describe('calendar load error (#396 S6)', () => {
  beforeEach(() => {
    vi.useFakeTimers({ toFake: ['Date'] })
    vi.setSystemTime(new Date(2026, 4, 13, 7, 0, 0))
    apiState.appointmentReads = 0
    apiState.appointmentPayload = [
      {
        id: 1,
        scheduled_at: '2026-05-13T10:00:00',
        ends_at: '2026-05-13T10:30:00',
        duration_minutes: 30,
        status: 'scheduled',
        notes: null,
        patient: { id: 7, first_name: 'Ana', last_name: 'Pérez' },
        user: { id: 2, name: 'Dra. Ruiz' },
        dental_chair: null,
        appointment_type: { id: 3, name: 'Limpieza' }
      }
    ]
  })

  afterEach(() => {
    vi.useRealTimers()
  })

  it('shows the error block instead of the empty state and recovers through retry', async () => {
    const wrapper = mount(Page, { global: { plugins: [router, uiComponents] } })
    await flushPromises()

    expect(wrapper.find('[data-calendar-error]').exists()).toBe(true)
    expect(wrapper.find('[data-calendar-error]').text()).toContain('La agenda no está disponible')
    expect(wrapper.find('[data-calendar-empty]').exists()).toBe(false)

    await wrapper.find('[data-calendar-retry]').trigger('click')
    await flushPromises()

    expect(wrapper.find('[data-calendar-error]').exists()).toBe(false)
    expect(wrapper.find('[data-calendar-empty]').exists()).toBe(false)
    expect(wrapper.find('[data-calendar-appointment="1"]').exists()).toBe(true)

    wrapper.unmount()
  })
})
