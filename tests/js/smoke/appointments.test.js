import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { nextTick } from 'vue'
import { createRouter, createMemoryHistory } from 'vue-router'
const { patchMock } = vi.hoisted(() => ({
  patchMock: vi.fn()
}))
vi.mock('../../../resources/js/composables/useApi', () => ({
  useApi: () => ({
    // T5: `get` seeds one appointment at 2026-05-13 10:00 (30 min). Tests that
    // need the chip mount with a faked system time at that week; every other
    // test falls outside the rendered range and sees no chip.
    get: async () => ({
      data: [
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
    }),
    post: async () => ({ data: {} }),
    put: async () => ({ data: {} }),
    patch: patchMock,
    delete: async () => ({ data: {} }),
    setToken: () => {},
    normalizeError: err => err?.response?.data?.message || 'Error desconocido'
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
import Page from '../../../resources/js/modules/appointments/CalendarPage.vue'
import NewAppointmentModal from '../../../resources/js/components/appointments/NewAppointmentModal.vue'
import { globalToasts } from '../../../resources/js/composables/useToast.js'
const router = createRouter({
  history: createMemoryHistory(),
  routes: [{ path: '/:pathMatch(.*)*', component: { template: '<div />' } }]
})
describe('appointments smoke', () => {
  it('mounts CalendarPage.vue and renders content', async () => {
    const wrapper = mount(Page, { global: { plugins: [router, uiComponents] } })
    await flushPromises()
    expect(wrapper.text()).toContain('Agenda')
    wrapper.unmount()
  })

  // T4 click-to-create: behavioural anchor for the slot → modal prefill
  // contract (the data-calendar-* hooks + permission gate are pinned
  // structurally by CitasCalendarAppShellTest; this case exercises the
  // real wiring end to end in the mounted page).
  it('opens NewAppointmentModal prefilled when an empty slot is clicked (T4)', async () => {
    const wrapper = mount(Page, { global: { plugins: [router, uiComponents] } })
    await flushPromises()

    const slot = wrapper.find('button[data-calendar-slot]')
    expect(slot.exists()).toBe(true)

    const key = slot.attributes('data-calendar-slot')
    const dateStr = key.slice(0, 10)
    const hour = Number(key.slice(11))
    expect(key).toBe(`${dateStr}-${hour}`)

    await slot.trigger('click')
    await flushPromises()

    const modal = wrapper.findComponent(NewAppointmentModal)
    expect(modal.props('modelValue')).toBe(true)
    expect(modal.props('initialDate')).toBe(`${dateStr}T${String(hour).padStart(2, '0')}:00`)
    wrapper.unmount()
  })
})

// T5 drag-and-drop reschedule: interaction contract for the Day/Week grids.
// The pointer lifecycle (threshold -> drag -> hover -> drop) is driven with
// real MouseEvents on window; slot geometry is stubbed because jsdom rects
// are all zero. System time is pinned to Wednesday 2026-05-13 07:00 local so
// the seeded 10:00 appointment renders in the week grid and the 14:00 target
// slot is >= 1 minute in the future.
describe('appointments drag reschedule smoke (T5)', () => {
  const chipSelector = '[data-calendar-appointment="1"]'

  // collectDropTargets() reads the document, so the page must be attached.
  // Hosts are tracked so afterEach tears down even after a failed assertion —
  // otherwise a leaked grid feeds stale rect stubs into the next test.
  const mounted = []
  const mountPage = () => {
    const host = document.createElement('div')
    document.body.appendChild(host)
    const wrapper = mount(Page, { attachTo: host, global: { plugins: [router, uiComponents] } })
    mounted.push({ wrapper, host })
    return wrapper
  }

  const stubSlotRects = (wrapper, targetKey) => {
    wrapper.findAll('[data-calendar-drop-target]').forEach(cell => {
      const isTarget = cell.attributes('data-calendar-drop-target') === targetKey
      vi.spyOn(cell.element, 'getBoundingClientRect').mockReturnValue(
        isTarget
          ? { left: 100, top: 200, right: 300, bottom: 280, width: 200, height: 80 }
          : { left: -10000, top: -10000, right: -10000, bottom: -10000, width: 0, height: 0 }
      )
    })
  }

  const dispatchPointer = (target, type, init = {}) => {
    const node = target && target.element ? target.element : target
    node.dispatchEvent(
      new MouseEvent(type, { button: 0, cancelable: true, bubbles: true, ...init })
    )
  }

  const startDrag = (chip, from = { clientX: 10, clientY: 10 }) => {
    dispatchPointer(chip, 'pointerdown', from)
    dispatchPointer(window, 'pointermove', {
      clientX: from.clientX + 30,
      clientY: from.clientY + 30
    })
  }

  const conflictError = () => ({
    response: {
      status: 422,
      data: {
        message: 'El sillón A1 ya tiene una cita en ese horario',
        errors: { scheduled_at: ['El sillón A1 ya tiene una cita en ese horario'] },
        conflict: {
          type: 'chair_conflict',
          conflicting_appointment: {
            id: 9,
            scheduled_at: '2026-05-13T14:00:00',
            ends_at: '2026-05-13T14:30:00',
            patient: { id: 8, full_name: 'Luis García' },
            professional: { id: 2, name: 'Dra. Ruiz' },
            dental_chair: { id: 1, name: 'A1', code: 'A1' }
          }
        }
      }
    }
  })

  beforeEach(() => {
    vi.useFakeTimers({ toFake: ['Date'] })
    vi.setSystemTime(new Date(2026, 4, 13, 7, 0, 0))
    patchMock.mockReset()
    patchMock.mockResolvedValue({ data: {}, meta: { message: 'Cita reprogramada exitosamente' } })
    globalToasts.value.splice(0, globalToasts.value.length)
  })

  afterEach(() => {
    while (mounted.length) {
      const { wrapper, host } = mounted.pop()
      try {
        wrapper.unmount()
      } catch {
        // already unmounted
      }
      host.remove()
    }
    vi.useRealTimers()
    localStorage.removeItem('user')
  })

  it('drags a chip past the threshold and PATCHes reschedule keeping the duration (T5)', async () => {
    const wrapper = mountPage()
    await flushPromises()

    const chip = wrapper.find(chipSelector)
    expect(chip.exists()).toBe(true)
    stubSlotRects(wrapper, '2026-05-13-14')

    startDrag(chip)
    await nextTick()
    expect(chip.attributes('data-calendar-drag-state')).toBe('active')

    dispatchPointer(window, 'pointermove', { clientX: 150, clientY: 240 })
    await nextTick()
    expect(
      wrapper
        .find('[data-calendar-drop-target="2026-05-13-14"]')
        .attributes('data-calendar-drop-active')
    ).toBe('')

    dispatchPointer(window, 'pointerup', { clientX: 150, clientY: 240 })
    // Real browsers fire the click in the same task as the pointerup; the
    // drop must suppress it.
    chip.element.dispatchEvent(new MouseEvent('click', { bubbles: true }))
    await flushPromises()

    expect(patchMock).toHaveBeenCalledTimes(1)
    expect(patchMock).toHaveBeenCalledWith('/api/appointments/1/reschedule', {
      scheduled_at: '2026-05-13T14:00',
      duration_minutes: 30
    })

    const moved = wrapper.find(
      '[data-calendar-drop-target="2026-05-13-14"] [data-calendar-appointment="1"]'
    )
    expect(moved.exists()).toBe(true)
    expect(moved.attributes('data-calendar-drag-state')).toBeUndefined()
    expect(moved.attributes('data-calendar-draggable')).toBe('1')
    expect(globalToasts.value.at(-1)?.type).toBe('success')
    expect(wrapper.text()).not.toContain('Detalles de la Cita')
    wrapper.unmount()
  })

  it('keeps click-to-select when the press stays under the drag threshold (T5)', async () => {
    const wrapper = mountPage()
    await flushPromises()

    const chip = wrapper.find(chipSelector)
    dispatchPointer(chip, 'pointerdown', { clientX: 10, clientY: 10 })
    dispatchPointer(window, 'pointermove', { clientX: 12, clientY: 12 })
    dispatchPointer(window, 'pointerup', { clientX: 12, clientY: 12 })
    await nextTick()

    expect(chip.attributes('data-calendar-drag-state')).toBeUndefined()
    expect(patchMock).not.toHaveBeenCalled()

    chip.element.dispatchEvent(new MouseEvent('click', { bubbles: true }))
    await nextTick()
    expect(wrapper.text()).toContain('Detalles de la Cita')
    wrapper.unmount()
  })

  it('cancels the drag on Escape without moving the appointment (T5)', async () => {
    const wrapper = mountPage()
    await flushPromises()

    const chip = wrapper.find(chipSelector)
    stubSlotRects(wrapper, '2026-05-13-14')

    startDrag(chip)
    await nextTick()
    expect(chip.attributes('data-calendar-drag-state')).toBe('active')

    window.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape', cancelable: true }))
    dispatchPointer(window, 'pointerup', { clientX: 150, clientY: 240 })
    await flushPromises()

    expect(patchMock).not.toHaveBeenCalled()
    const home = wrapper.find(`[data-calendar-drop-target="2026-05-13-10"] ${chipSelector}`)
    expect(home.exists()).toBe(true)
    expect(home.attributes('data-calendar-drag-state')).toBeUndefined()
    wrapper.unmount()
  })

  it('ignores a drop onto the appointment current slot (T5, no request)', async () => {
    const wrapper = mountPage()
    await flushPromises()

    const chip = wrapper.find(chipSelector)
    stubSlotRects(wrapper, '2026-05-13-10')

    startDrag(chip)
    await nextTick()
    dispatchPointer(window, 'pointerup', { clientX: 150, clientY: 240 })
    await flushPromises()

    expect(patchMock).not.toHaveBeenCalled()
    expect(globalToasts.value).toHaveLength(0)
    wrapper.unmount()
  })

  it('rolls back to the original slot and names the blocker on a 422 conflict (T5)', async () => {
    patchMock.mockRejectedValueOnce(conflictError())
    const wrapper = mountPage()
    await flushPromises()

    const chip = wrapper.find(chipSelector)
    stubSlotRects(wrapper, '2026-05-13-14')

    startDrag(chip)
    await nextTick()
    dispatchPointer(window, 'pointermove', { clientX: 150, clientY: 240 })
    dispatchPointer(window, 'pointerup', { clientX: 150, clientY: 240 })
    await flushPromises()

    expect(patchMock).toHaveBeenCalledTimes(1)
    expect(
      wrapper.find(`[data-calendar-drop-target="2026-05-13-10"] ${chipSelector}`).exists()
    ).toBe(true)
    expect(
      wrapper.find(`[data-calendar-drop-target="2026-05-13-14"] ${chipSelector}`).exists()
    ).toBe(false)

    const lastToast = globalToasts.value.at(-1)
    expect(lastToast?.type).toBe('error')
    expect(lastToast?.message).toContain('sillón A1')
    expect(lastToast?.message).toContain('Luis García')
    wrapper.unmount()
  })

  it('rolls back and surfaces the server message on a 422 validation error (T5)', async () => {
    patchMock.mockRejectedValueOnce({
      response: {
        status: 422,
        data: {
          message: 'La fecha y hora de la cita debe ser al menos 1 minuto en el futuro.',
          errors: {
            scheduled_at: ['La fecha y hora de la cita debe ser al menos 1 minuto en el futuro.']
          }
        }
      }
    })
    const wrapper = mountPage()
    await flushPromises()

    const chip = wrapper.find(chipSelector)
    stubSlotRects(wrapper, '2026-05-13-14')

    startDrag(chip)
    await nextTick()
    dispatchPointer(window, 'pointermove', { clientX: 150, clientY: 240 })
    dispatchPointer(window, 'pointerup', { clientX: 150, clientY: 240 })
    await flushPromises()

    expect(
      wrapper.find(`[data-calendar-drop-target="2026-05-13-10"] ${chipSelector}`).exists()
    ).toBe(true)
    expect(globalToasts.value.at(-1)?.message).toContain('al menos 1 minuto en el futuro')
    wrapper.unmount()
  })

  it('rolls back on a network error with an honest toast (T5)', async () => {
    patchMock.mockRejectedValueOnce(new Error('Network down'))
    const wrapper = mountPage()
    await flushPromises()

    const chip = wrapper.find(chipSelector)
    stubSlotRects(wrapper, '2026-05-13-14')

    startDrag(chip)
    await nextTick()
    dispatchPointer(window, 'pointermove', { clientX: 150, clientY: 240 })
    dispatchPointer(window, 'pointerup', { clientX: 150, clientY: 240 })
    await flushPromises()

    expect(
      wrapper.find(`[data-calendar-drop-target="2026-05-13-10"] ${chipSelector}`).exists()
    ).toBe(true)
    expect(globalToasts.value.at(-1)?.type).toBe('error')
    wrapper.unmount()
  })

  it('gates dragging on editAppointment permission (T5, finanzas cannot drag)', async () => {
    localStorage.setItem('user', JSON.stringify({ id: 99, role: 'finanzas', name: 'Fin' }))
    const wrapper = mountPage()
    await flushPromises()

    const chip = wrapper.find(chipSelector)
    expect(chip.exists()).toBe(true)
    expect(chip.classes()).not.toContain('cursor-grab')
    stubSlotRects(wrapper, '2026-05-13-14')

    startDrag(chip)
    await nextTick()
    dispatchPointer(window, 'pointermove', { clientX: 150, clientY: 240 })
    dispatchPointer(window, 'pointerup', { clientX: 150, clientY: 240 })
    await flushPromises()

    expect(chip.attributes('data-calendar-drag-state')).toBeUndefined()
    expect(patchMock).not.toHaveBeenCalled()
    wrapper.unmount()
  })

  it('exposes the drag hooks on chips and drop targets (T5)', async () => {
    const wrapper = mountPage()
    await flushPromises()

    const chip = wrapper.find(chipSelector)
    expect(chip.attributes('data-calendar-draggable')).toBe('1')
    expect(chip.attributes('data-calendar-drag-state')).toBeUndefined()

    const cells = wrapper.findAll('[data-calendar-drop-target]')
    expect(cells.length).toBe(112) // 7 week days x 16 hour rows
    cells.forEach(cell => {
      expect(cell.attributes('data-calendar-drop-active')).toBeUndefined()
    })
    wrapper.unmount()
  })
})
