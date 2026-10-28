import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { createRouter, createMemoryHistory } from 'vue-router'

// #394 S4: cancelling an appointment asks for a motive, sends it with the
// status change, and the success notice names the pending reminders that were
// cancelled with it. A rejection (422) stays in the dialog on its field.
const apiState = vi.hoisted(() => ({
  patchMock: vi.fn(),
  patchResponse: { data: {}, meta: { reminders_cancelled: 0 } }
}))

vi.mock('../../../resources/js/composables/useApi', () => ({
  useApi: () => ({
    get: async url => {
      if (url !== '/api/appointments') return { data: [], meta: { last_page: 1 } }
      return {
        data: [
          {
            id: 1,
            scheduled_at: '2026-05-13T10:00:00',
            ends_at: '2026-05-13T10:30:00',
            duration_minutes: 30,
            status: 'scheduled',
            notes: null,
            cancellation_reason: null,
            patient: { id: 7, first_name: 'Ana', last_name: 'Pérez' },
            user: { id: 2, name: 'Dra. Ruiz' },
            dental_chair: null,
            appointment_type: { id: 3, name: 'Limpieza' }
          }
        ]
      }
    },
    post: async () => ({ data: {} }),
    put: async () => ({ data: {} }),
    patch: apiState.patchMock,
    del: async () => ({ data: {} }),
    setToken: () => {},
    normalizeError: (err, fallback) => {
      const data = err?.response?.data
      const firstError = data?.errors && Object.values(data.errors).flat()[0]
      return firstError || data?.message || fallback
    }
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
import NewAppointmentModal from '../../../resources/js/components/appointments/NewAppointmentModal.vue'
import { globalToasts } from '../../../resources/js/composables/useToast.js'

const router = createRouter({
  history: createMemoryHistory(),
  routes: [{ path: '/:pathMatch(.*)*', component: { template: '<div />' } }]
})

const mountPage = () =>
  mount(Page, {
    attachTo: document.body,
    global: { plugins: [router, uiComponents], stubs: { teleport: true } }
  })

const openCancellationDialog = async wrapper => {
  await flushPromises()
  await wrapper.find('[data-calendar-appointment="1"]').trigger('click')
  await flushPromises()
  expect(wrapper.find('[data-calendar-detail]').exists()).toBe(true)

  await wrapper.find('[data-calendar-action="status-cancelled"]').trigger('click')
  await flushPromises()

  expect(wrapper.find('[data-calendar-cancel]').exists()).toBe(true)
}

const lastToastMessage = () => globalToasts.value[globalToasts.value.length - 1]?.message

describe('calendar cancellation motive (#394 S4)', () => {
  beforeEach(() => {
    vi.useFakeTimers({ toFake: ['Date'] })
    vi.setSystemTime(new Date(2026, 4, 13, 7, 0, 0))
    apiState.patchMock.mockReset()
    apiState.patchMock.mockResolvedValue({ data: {}, meta: { reminders_cancelled: 0 } })
    globalToasts.value = []
  })

  afterEach(() => {
    vi.useRealTimers()
    document.body.innerHTML = ''
  })

  it('opens the confirmation dialog without sending the status change', async () => {
    const wrapper = mountPage()

    await openCancellationDialog(wrapper)

    expect(apiState.patchMock).not.toHaveBeenCalled()
    expect(wrapper.text()).toContain('Motivo de la cancelación')
    expect(wrapper.text()).toContain('Ana Pérez')

    wrapper.unmount()
  })

  it('puts the focus inside the confirmation dialog and refuses an empty motive', async () => {
    const wrapper = mountPage()

    await openCancellationDialog(wrapper)

    const dialog = wrapper.find('[data-calendar-cancel]').element.closest('[role="dialog"]')
    expect(dialog.contains(document.activeElement)).toBe(true)

    await wrapper.find('[data-calendar-cancel]').trigger('submit')
    await flushPromises()

    expect(apiState.patchMock).not.toHaveBeenCalled()
    expect(wrapper.find('[data-calendar-cancel]').exists()).toBe(true)
    expect(wrapper.text()).toContain('Indica el motivo de la cancelación.')

    wrapper.unmount()
  })

  it('sends the typed motive and names the cancelled reminders in the notice', async () => {
    apiState.patchMock.mockResolvedValue({ data: {}, meta: { reminders_cancelled: 3 } })
    const wrapper = mountPage()

    await openCancellationDialog(wrapper)

    await wrapper.find('[data-calendar-cancel-reason] textarea').setValue('  El paciente viaja  ')
    await wrapper.find('[data-calendar-cancel]').trigger('submit')
    await flushPromises()

    expect(apiState.patchMock).toHaveBeenCalledTimes(1)
    expect(apiState.patchMock).toHaveBeenCalledWith('/api/appointments/1/status', {
      status: 'cancelled',
      cancellation_reason: 'El paciente viaja'
    })
    expect(lastToastMessage()).toBe('Cita cancelada. Se cancelaron 3 recordatorios pendientes.')

    expect(wrapper.find('[data-calendar-cancel]').exists()).toBe(false)
    expect(wrapper.find('[data-calendar-detail]').exists()).toBe(false)

    wrapper.unmount()
  })

  it('does not mention reminders when the cancellation cancelled none', async () => {
    const wrapper = mountPage()

    await openCancellationDialog(wrapper)

    await wrapper.find('[data-calendar-cancel-reason] textarea').setValue('Duplicada')
    await wrapper.find('[data-calendar-cancel]').trigger('submit')
    await flushPromises()

    expect(lastToastMessage()).toBe('Cita cancelada.')

    wrapper.unmount()
  })

  it('keeps the dialog open and shows the reason on a 422', async () => {
    apiState.patchMock.mockRejectedValue({
      response: {
        status: 422,
        data: {
          message: 'Los datos proporcionados no son válidos.',
          errors: { cancellation_reason: ['Indica el motivo de la cancelación.'] }
        }
      }
    })
    const wrapper = mountPage()

    await openCancellationDialog(wrapper)

    await wrapper.find('[data-calendar-cancel-reason] textarea').setValue('Motivo')
    await wrapper.find('[data-calendar-cancel]').trigger('submit')
    await flushPromises()

    expect(wrapper.find('[data-calendar-cancel]').exists()).toBe(true)
    expect(wrapper.text()).toContain('Indica el motivo de la cancelación.')
    expect(wrapper.find('[data-calendar-detail]').exists()).toBe(true)

    wrapper.unmount()
  })
})

describe('appointment edit cancellation motive (#394 S4)', () => {
  it('prefills the motive from the appointment when the status is cancelled', async () => {
    const wrapper = mount(NewAppointmentModal, {
      props: {
        modelValue: false,
        appointment: {
          id: 9,
          patient_id: 1,
          user_id: 2,
          scheduled_at: '2026-05-13T10:00:00',
          duration_minutes: 30,
          appointment_type_id: 3,
          dental_chair_id: 4,
          status: 'cancelled',
          cancellation_reason: 'Motivo previo',
          notes: null
        }
      },
      global: { plugins: [uiComponents], stubs: { teleport: true } }
    })

    await wrapper.setProps({ modelValue: true })
    await flushPromises()

    const field = wrapper.find('[data-appointment-cancel-reason] textarea')
    expect(field.exists()).toBe(true)
    expect(field.element.value).toBe('Motivo previo')

    wrapper.unmount()
  })
})
