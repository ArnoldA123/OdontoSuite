import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { nextTick } from 'vue'
import { createRouter, createMemoryHistory } from 'vue-router'
const { patchMock, apiAppointments, deleteMock, getGate } = vi.hoisted(() => ({
  patchMock: vi.fn(),
  // T7: delete spy so the useConfirm routing test can assert that NO API
  // call happens before the confirm resolves and that the confirmed call
  // hits the exact appointment endpoint.
  deleteMock: vi.fn().mockResolvedValue({ data: {} }),
  // T7: `get` gate. When deferred, `get` returns a promise that only settles
  // through `getGate.release()` — lets the loading-state test hold the page
  // in `loading === true` deterministically.
  getGate: { deferred: false, release: null },
  // T5: `get` seeds one appointment at 2026-05-13 10:00 (30 min). Tests that
  // need the chip mount with a faked system time at that week; every other
  // test falls outside the rendered range and sees no chip. T6 month tests
  // swap in a busier day through this handle (restored in afterEach).
  apiAppointments: {
    value: [
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
  }
}))
vi.mock('../../../resources/js/composables/useApi', () => ({
  useApi: () => ({
    get: () => {
      const payload = () => ({ data: JSON.parse(JSON.stringify(apiAppointments.value)) })
      if (getGate.deferred) {
        return new Promise(resolve => {
          getGate.release = () => resolve(payload())
        })
      }
      return Promise.resolve(payload())
    },
    post: async () => ({ data: {} }),
    put: async () => ({ data: {} }),
    patch: patchMock,
    // T7: CalendarPage destructures `del` (useApi line 148), not `delete` —
    // the old mock never implemented it, so the delete flow silently threw.
    del: deleteMock,
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
import Modal from '../../../resources/js/components/ui/Modal.vue'
import DentalMotifLayer from '../../../resources/js/components/ui/DentalMotifLayer.vue'
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

// T8b defect 1 — click-to-create prefill race: clicking an empty slot must
// land in NewAppointmentModal with the Fecha y Hora input ALREADY populated
// with the slot's `YYYY-MM-DDTHH:mm`, regardless of the order in which the
// page sets `newAppointmentPrefill` and `showNewAppointmentModal`. The old
// T4 anchor only asserted the modal *props*; this block asserts the rendered
// input value (the thing the real-browser pass saw empty).
describe('T8b click-to-create prefill smoke (defect 1)', () => {
  const expectedPrefill = slotKey => {
    const dateStr = slotKey.slice(0, 10)
    const hour = Number(slotKey.slice(11))
    return `${dateStr}T${String(hour).padStart(2, '0')}:00`
  }

  const scheduledInput = () => document.querySelector('input[type="datetime-local"]')

  const mounted = []
  const mountPage = () => {
    const host = document.createElement('div')
    document.body.appendChild(host)
    const wrapper = mount(Page, { attachTo: host, global: { plugins: [router, uiComponents] } })
    mounted.push({ wrapper, host })
    return wrapper
  }

  beforeEach(() => {
    vi.useFakeTimers({ toFake: ['Date'] })
    vi.setSystemTime(new Date(2026, 4, 13, 7, 0, 0))
    globalToasts.value.splice(0, globalToasts.value.length)
    localStorage.removeItem('user')
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

  it('populates the Fecha y Hora input from the clicked slot in Week view', async () => {
    const wrapper = mountPage()
    await flushPromises()

    const slot = wrapper.find('button[data-calendar-slot]')
    expect(slot.exists()).toBe(true)
    const key = slot.attributes('data-calendar-slot')

    await slot.trigger('click')
    await flushPromises()

    const input = scheduledInput()
    expect(input, 'the modal form must render its datetime-local input').not.toBeNull()
    expect(input.value).toBe(expectedPrefill(key))
  })

  it('populates the Fecha y Hora input from the clicked slot in Day view', async () => {
    const wrapper = mountPage()
    await flushPromises()

    await wrapper.find('[data-calendar-view="day"]').trigger('click')
    await flushPromises()

    const slot = wrapper.find('button[data-calendar-slot]')
    expect(slot.exists()).toBe(true)
    const key = slot.attributes('data-calendar-slot')

    await slot.trigger('click')
    await flushPromises()

    const input = scheduledInput()
    expect(input, 'the modal form must render its datetime-local input').not.toBeNull()
    expect(input.value).toBe(expectedPrefill(key))
  })

  // Order-independence regressions: the prefill must survive every reopen
  // sequence, whatever the previous modal session was (button open with no
  // slot context, repeated slot opens, or an edit session).
  it('re-prefills after a previous open through the Nueva cita button (no slot context)', async () => {
    const wrapper = mountPage()
    await flushPromises()

    await wrapper.find('[data-calendar-action="new-appointment"]').trigger('click')
    await flushPromises()
    expect(scheduledInput().value).toBe('') // button open carries no slot context

    // close through the modal cancel button
    const cancel = Array.from(document.querySelectorAll('button')).find(
      b => b.textContent.trim() === 'Cancelar'
    )
    cancel.click()
    await flushPromises()
    expect(scheduledInput()).toBeNull() // closed → form unmounted

    const slot = wrapper.find('button[data-calendar-slot]')
    const key = slot.attributes('data-calendar-slot')
    await slot.trigger('click')
    await flushPromises()
    expect(scheduledInput().value).toBe(expectedPrefill(key))
  })

  it('re-prefills when the same slot is opened a second time', async () => {
    const wrapper = mountPage()
    await flushPromises()

    const slot = wrapper.find('button[data-calendar-slot]')
    const key = slot.attributes('data-calendar-slot')

    await slot.trigger('click')
    await flushPromises()
    expect(scheduledInput().value).toBe(expectedPrefill(key))

    const cancel = Array.from(document.querySelectorAll('button')).find(
      b => b.textContent.trim() === 'Cancelar'
    )
    cancel.click()
    await flushPromises()

    await slot.trigger('click')
    await flushPromises()
    expect(scheduledInput().value).toBe(expectedPrefill(key))
  })

  it('prefills the slot after a previous edit session (edit data must not leak)', async () => {
    localStorage.setItem('user', JSON.stringify({ id: 9, role: 'odontologo', name: 'Odonto' }))
    const wrapper = mountPage()
    await flushPromises()

    // open edit mode through the details modal
    await wrapper.find('[data-calendar-appointment="1"]').trigger('click')
    await nextTick()
    document.querySelector('[data-calendar-action="edit"]').click()
    await flushPromises()
    expect(scheduledInput().value).toBe('2026-05-13T10:00:00'.slice(0, 16)) // edit populates

    const cancel = Array.from(document.querySelectorAll('button')).find(
      b => b.textContent.trim() === 'Cancelar'
    )
    cancel.click()
    await flushPromises()

    const slot = wrapper.find('button[data-calendar-slot]')
    const key = slot.attributes('data-calendar-slot')
    await slot.trigger('click')
    await flushPromises()

    const input = scheduledInput()
    expect(input.value).toBe(expectedPrefill(key))
    // create mode: no leftover edit binding
    expect(wrapper.findComponent(NewAppointmentModal).props('appointment')).toBeNull()
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
    expect(document.body.textContent).toContain('Detalles de la Cita')
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
    // T7: the details surface teleports through UiModal, so visibility is
    // asserted on document.body (it was `wrapper.text()` when the modal was
    // a hand-rolled div inside the page tree).
    expect(document.body.textContent).not.toContain('Detalles de la Cita')
    wrapper.unmount()
  })

  it('informs when a drop is rejected client-side on a past slot (T8b investigation)', async () => {
    // Real-browser hypothesis replay: the seeded fixtures sit in a week that
    // is entirely in the past, so `canDropOn` rejects the drop BEFORE any
    // request — the old code cancelled silently (no PATCH, no toast). The
    // rejection must now be announced quietly while staying client-side.
    vi.setSystemTime(new Date(2026, 4, 13, 15, 0, 0))
    const wrapper = mountPage()
    await flushPromises()

    const chip = wrapper.find(chipSelector)
    stubSlotRects(wrapper, '2026-05-13-14')

    startDrag(chip)
    await nextTick()
    dispatchPointer(window, 'pointermove', { clientX: 150, clientY: 240 })
    await nextTick()
    dispatchPointer(window, 'pointerup', { clientX: 150, clientY: 240 })
    await flushPromises()

    // client-side rejection: no request, chip rolled back, no details modal
    expect(patchMock).not.toHaveBeenCalled()
    expect(
      wrapper.find(`[data-calendar-drop-target="2026-05-13-10"] ${chipSelector}`).exists()
    ).toBe(true)
    expect(document.body.textContent).not.toContain('Detalles de la Cita')

    // …but no longer silent: an informative (non-error) toast names the rule.
    const last = globalToasts.value.at(-1)
    expect(last?.type).toBe('info')
    expect(last?.message).toBe('No se puede mover a un horario pasado')
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

// T8b defects 2 & 3 — chip anatomy: the registry status tint must survive
// the CSS cascade in ALL three views (base classes carry shape only, status
// classes carry bg + border colour), and chip time text must be the compact
// `HH:mm - HH:mm` (no locale a. m./p. m. clipping) with a `title` carrying
// the full patient + range text.
describe('T8b chip tint and compact time smoke (defects 2/3)', () => {
  const mounted = []
  const mountPage = () => {
    const host = document.createElement('div')
    document.body.appendChild(host)
    const wrapper = mount(Page, { attachTo: host, global: { plugins: [router, uiComponents] } })
    mounted.push({ wrapper, host })
    return wrapper
  }

  const defaultAppointments = apiAppointments.value

  beforeEach(() => {
    vi.useFakeTimers({ toFake: ['Date'] })
    vi.setSystemTime(new Date(2026, 4, 13, 7, 0, 0))
    localStorage.removeItem('user')
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
    apiAppointments.value = defaultAppointments
    vi.useRealTimers()
    localStorage.removeItem('user')
  })

  const assertChipContract = (chip, view) => {
    const classes = chip.classes()

    // Defect 2: the registry tint is the ONLY background/border-colour the
    // chip wears — no base or template token may compete with it in the
    // cascade (both would set background-color/border-color and the winner
    // would be decided by stylesheet order).
    expect(
      classes.filter(c => c.startsWith('bg-')),
      `${view}: single bg`
    ).toEqual(['bg-accent-50'])
    expect(
      classes.filter(c => c.startsWith('border-')),
      `${view}: single border colour`
    ).toEqual(['border-accent-200'])

    // Defect 3: compact range, never the long locale form, full title.
    expect(chip.text(), `${view}: compact range`).toContain('10:00 - 10:30')
    expect(chip.text(), `${view}: no locale meridiem`).not.toMatch(/a\. m\.|p\. m\./)
    expect(chip.attributes('title'), `${view}: full title`).toContain('Ana Pérez')
    expect(chip.attributes('title'), `${view}: full title range`).toContain('10:30')
  }

  it('carries the registry tint + compact time on the Week chip', async () => {
    const wrapper = mountPage()
    await flushPromises()

    const chip = wrapper.find('[data-calendar-appointment="1"]')
    expect(chip.exists()).toBe(true)
    assertChipContract(chip, 'week')
  })

  it('carries the registry tint + compact time on the Day chip', async () => {
    const wrapper = mountPage()
    await flushPromises()
    await wrapper.find('[data-calendar-view="day"]').trigger('click')
    await flushPromises()

    const chip = wrapper.find('[data-calendar-appointment="1"]')
    expect(chip.exists()).toBe(true)
    assertChipContract(chip, 'day')
  })

  it('carries the registry tint + compact time on the Month chip', async () => {
    const wrapper = mountPage()
    await flushPromises()
    await wrapper.find('[data-calendar-view="month"]').trigger('click')
    await flushPromises()

    const chip = wrapper.find('[data-calendar-appointment="1"]')
    expect(chip.exists()).toBe(true)
    assertChipContract(chip, 'month')
  })
})

// T6 month view: behavioural anchors for the redesigned month body. The
// data-calendar-* month hooks + drill-down wiring are pinned structurally by
// CitasCalendarMonthViewTest; these cases exercise the real mounted page: the
// 42-cell quiet grid, the registry chips opening the details modal, and the
// D8 "N más" drill-down switching view + date to the Day grid. System time is
// pinned to Wednesday 2026-05-13 07:00 local; the API mock is swapped for a
// busier 2026-05-13 (5 appointments -> 3 chips + "+2 más").
describe('appointments month view smoke (T6)', () => {
  const appointmentAt = (id, datetime, firstName) => ({
    id,
    scheduled_at: `${datetime}:00`,
    ends_at: `${datetime}:30`,
    duration_minutes: 30,
    status: 'scheduled',
    notes: null,
    patient: { id: 100 + id, first_name: firstName, last_name: 'Pérez' },
    user: { id: 2, name: 'Dra. Ruiz' },
    dental_chair: null,
    appointment_type: { id: 3, name: 'Limpieza' }
  })

  const busyDay = [
    appointmentAt(1, '2026-05-13T09:00', 'Ana'),
    appointmentAt(2, '2026-05-13T10:00', 'Luis'),
    appointmentAt(3, '2026-05-13T11:00', 'María'),
    appointmentAt(4, '2026-05-13T12:00', 'Jorge'),
    appointmentAt(5, '2026-05-13T13:00', 'Sofía'),
    appointmentAt(6, '2026-05-20T15:00', 'Pedro')
  ]

  const defaultAppointments = apiAppointments.value
  const mounted = []
  const mountPage = () => {
    const host = document.createElement('div')
    document.body.appendChild(host)
    const wrapper = mount(Page, { attachTo: host, global: { plugins: [router, uiComponents] } })
    mounted.push({ wrapper, host })
    return wrapper
  }

  const toMonthView = async wrapper => {
    await wrapper.find('[data-calendar-view="month"]').trigger('click')
    await flushPromises()
  }

  beforeEach(() => {
    vi.useFakeTimers({ toFake: ['Date'] })
    vi.setSystemTime(new Date(2026, 4, 13, 7, 0, 0))
    apiAppointments.value = busyDay
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
    apiAppointments.value = defaultAppointments
    vi.useRealTimers()
  })

  it('renders the 42-cell quiet month grid with the shared weekday header (T6)', async () => {
    const wrapper = mountPage()
    await flushPromises()
    await toMonthView(wrapper)

    const cells = wrapper.findAll('[data-calendar-month-day]')
    expect(cells).toHaveLength(42)
    expect(cells[0].attributes('data-calendar-month-day')).toBe('2026-04-27')

    const header = wrapper.findAll('[data-calendar-month-weekday]')
    expect(header.map(cell => cell.text())).toEqual([
      'Lun',
      'Mar',
      'Mié',
      'Jue',
      'Vie',
      'Sáb',
      'Dom'
    ])

    // Today (2026-05-13) wears the accent token highlight; out-of-month days
    // are quiet via the theme surface token (T6 quiet design language).
    expect(wrapper.find('[data-calendar-month-day="2026-05-13"]').classes()).toContain(
      'bg-accent-50'
    )
    expect(wrapper.find('[data-calendar-month-day="2026-04-27"]').classes()).toContain(
      'bg-theme-surface'
    )
  })

  it('caps month chips at the cell limit and opens the details modal from a chip (T6)', async () => {
    const wrapper = mountPage()
    await flushPromises()
    await toMonthView(wrapper)

    const cell = wrapper.find('[data-calendar-month-day="2026-05-13"]')
    const chips = cell.findAll('[data-calendar-appointment]')
    expect(chips).toHaveLength(3)
    expect(chips[0].text()).toContain('09:00')
    expect(chips[0].text()).toContain('Ana Pérez')

    const more = wrapper.find('[data-calendar-more="2026-05-13"]')
    expect(more.exists()).toBe(true)
    expect(more.attributes('data-calendar-more-count')).toBe('2')
    expect(more.text()).toBe('+2 más')

    await chips[0].trigger('click')
    expect(document.body.textContent).toContain('Detalles de la Cita')
  })

  it('drills down from "N más" to the Day view for that date (T6/D8)', async () => {
    const wrapper = mountPage()
    await flushPromises()
    await toMonthView(wrapper)

    await wrapper.find('[data-calendar-more="2026-05-13"]').trigger('click')
    await flushPromises()

    expect(wrapper.find('[data-calendar-view="day"]').attributes('aria-pressed')).toBe('true')
    expect(wrapper.text()).toContain('miércoles, 13 de mayo de 2026')
    expect(wrapper.findAll('[data-calendar-month-day]')).toHaveLength(0)
    expect(wrapper.find('button[data-calendar-slot="2026-05-13-9"]').exists()).toBe(true)
  })

  it('drills down from the day-number header to the Day view for that date (T6)', async () => {
    const wrapper = mountPage()
    await flushPromises()
    await toMonthView(wrapper)

    await wrapper.find('[data-calendar-day-link="2026-05-20"]').trigger('click')
    await flushPromises()

    expect(wrapper.find('[data-calendar-view="day"]').attributes('aria-pressed')).toBe('true')
    expect(wrapper.text()).toContain('miércoles, 20 de mayo de 2026')
  })
})

// T7 — appointment details surface rework. The details modal now renders
// through UiModal (teleported to document.body, like every other modal in
// the app), so assertions on it go through document queries and on the
// UiModal component instance — never through wrapper DOM lookups. The block
// also pins the two pre-existing bug fixes discovered in T4/T5:
//   - "Editar" gates on `can.editAppointment` (the real usePermissions key;
//     `can.updateAppointment` never existed, so the button never rendered);
//   - the selected appointment is bound into NewAppointmentModal via the
//     `appointment` prop (the modal already implements edit mode around it).
describe('T7 details modal smoke', () => {
  const t7Appointment = {
    id: 1,
    scheduled_at: '2026-05-13T10:00:00',
    ends_at: '2026-05-13T10:30:00',
    duration_minutes: 30,
    status: 'scheduled',
    notes: 'Primera visita de control',
    patient: { id: 7, first_name: 'Ana', last_name: 'Pérez' },
    user: { id: 2, name: 'Dra. Ruiz' },
    dental_chair: { id: 1, name: 'A1', code: 'A1' },
    appointment_type: { id: 3, name: 'Limpieza' }
  }
  const t7DefaultAppointments = apiAppointments.value

  const mounted = []
  const mountPage = () => {
    const host = document.createElement('div')
    document.body.appendChild(host)
    const wrapper = mount(Page, { attachTo: host, global: { plugins: [router, uiComponents] } })
    mounted.push({ wrapper, host })
    return wrapper
  }

  const openDetails = async wrapper => {
    await wrapper.find('[data-calendar-appointment="1"]').trigger('click')
    await nextTick()
  }

  // UiModal is shared by the details surface, the confirm dialog and the
  // appointment form — identify the details instance by its title.
  const detailsModal = wrapper =>
    wrapper.findAllComponents(Modal).find(m => m.props('title') === 'Detalles de la Cita')

  beforeEach(() => {
    vi.useFakeTimers({ toFake: ['Date'] })
    vi.setSystemTime(new Date(2026, 4, 13, 7, 0, 0))
    globalToasts.value.splice(0, globalToasts.value.length)
    apiAppointments.value = [t7Appointment]
    deleteMock.mockClear()
    localStorage.removeItem('user')
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
    apiAppointments.value = t7DefaultAppointments
    vi.useRealTimers()
    localStorage.removeItem('user')
  })

  it('renders the details surface through UiModal with the registry status pill (T7)', async () => {
    const wrapper = mountPage()
    await flushPromises()
    await openDetails(wrapper)

    // 1) chrome: the UiModal component owns the surface (title prop), and the
    //    legacy hand-rolled backdrop is gone from the page tree.
    const modal = detailsModal(wrapper)
    expect(modal).toBeDefined()
    expect(modal.props('title')).toBe('Detalles de la Cita')
    expect(wrapper.find('.bg-opacity-50').exists()).toBe(false)

    // 2) content: summary from real AppointmentResource fields + the hook.
    const details = document.querySelector('[data-calendar-detail]')
    expect(details).not.toBeNull()
    expect(details.textContent).toContain('Ana Pérez')
    expect(details.textContent).toContain('Dra. Ruiz')
    expect(details.textContent).toContain('Sillón A1')
    expect(details.textContent).toContain('10:00')
    expect(details.textContent).toContain('Primera visita de control')

    // 3) status pill rendered from the appointmentStatus registry
    //    (label comes from the registry, keyed by data-status).
    const pill = details.querySelector('[data-status="scheduled"]')
    expect(pill).not.toBeNull()
    expect(pill.textContent).toContain('Programada')
  })

  it('binds the appointment into NewAppointmentModal from the Editar action (T7 bugfix)', async () => {
    localStorage.setItem('user', JSON.stringify({ id: 9, role: 'odontologo', name: 'Odonto' }))
    const wrapper = mountPage()
    await flushPromises()
    await openDetails(wrapper)

    const edit = document.querySelector('[data-calendar-action="edit"]')
    expect(edit).not.toBeNull()
    edit.click()
    await flushPromises()

    const form = wrapper.findComponent(NewAppointmentModal)
    expect(form.props('modelValue')).toBe(true)
    expect(form.props('appointment')).toBeTruthy()
    expect(form.props('appointment').id).toBe(1)

    // Edit mode, not an empty form: the UiModal title flips on
    // `props.appointment?.id` inside NewAppointmentModal.
    expect(document.body.textContent).toContain('Editar Cita')

    // The details surface hands over and closes.
    const modal = detailsModal(wrapper)
    expect(modal).toBeDefined()
    expect(modal.props('modelValue')).toBe(false)
  })

  it('hides Editar from roles without editAppointment and keeps delete gated (T7)', async () => {
    // finanzas: editAppointment false (usePermissions line 30), deleteAppointment false.
    localStorage.setItem('user', JSON.stringify({ id: 99, role: 'finanzas', name: 'Fin' }))
    const wrapper = mountPage()
    await flushPromises()
    await openDetails(wrapper)

    expect(document.querySelector('[data-calendar-action="edit"]')).toBeNull()
    expect(document.querySelector('[data-calendar-action="delete"]')).toBeNull()
    // The form never opens from the details surface for this role.
    expect(wrapper.findComponent(NewAppointmentModal).props('modelValue')).toBe(false)
  })

  it('routes delete through useConfirm before hitting the API (T7)', async () => {
    localStorage.setItem('user', JSON.stringify({ id: 1, role: 'administrador', name: 'Admin' }))
    const wrapper = mountPage()
    await flushPromises()
    await openDetails(wrapper)

    const delButton = document.querySelector('[data-calendar-action="delete"]')
    expect(delButton).not.toBeNull()

    // 1) the confirm dialog opens BEFORE any API call.
    delButton.click()
    await flushPromises()
    expect(deleteMock).not.toHaveBeenCalled()
    const dialog = document.querySelector('[role="alertdialog"]')
    expect(dialog).not.toBeNull()
    expect(dialog.textContent).toContain('¿Estás seguro de que quieres eliminar esta cita?')

    // 2) cancelling the confirm must not call the API.
    const cancelButton = Array.from(dialog.querySelectorAll('button')).find(
      b => b.textContent.trim() === 'Cancelar'
    )
    expect(cancelButton).toBeTruthy()
    cancelButton.click()
    await flushPromises()
    expect(deleteMock).not.toHaveBeenCalled()

    // 3) confirming deletes the exact appointment and reloads.
    document.querySelector('[data-calendar-action="delete"]').click()
    await flushPromises()
    const reopened = document.querySelector('[role="alertdialog"]')
    expect(reopened).not.toBeNull()
    const confirmButton = Array.from(reopened.querySelectorAll('button')).find(
      b => b.textContent.trim() === 'Eliminar'
    )
    expect(confirmButton).toBeTruthy()
    confirmButton.click()
    await flushPromises()

    expect(deleteMock).toHaveBeenCalledTimes(1)
    expect(deleteMock).toHaveBeenCalledWith('/api/appointments/1')
    expect(globalToasts.value.at(-1)?.type).toBe('success')
  })
})

// T7 — D1/D9 empty range states: when the loaded range has zero
// appointments the grid shell renders the quiet empty block (dental motif +
// icon + per-view copy + permission-gated CTA) INSTEAD of the grids, and it
// never shows while the range is loading. The hooks + copy + ordering are
// also pinned structurally by CitasCalendarEmptyStateTest.
describe('T7 empty range smoke', () => {
  const t7DefaultAppointments = apiAppointments.value
  const mounted = []
  const mountPage = () => {
    const host = document.createElement('div')
    document.body.appendChild(host)
    const wrapper = mount(Page, { attachTo: host, global: { plugins: [router, uiComponents] } })
    mounted.push({ wrapper, host })
    return wrapper
  }

  beforeEach(() => {
    vi.useFakeTimers({ toFake: ['Date'] })
    vi.setSystemTime(new Date(2026, 4, 13, 7, 0, 0))
    globalToasts.value.splice(0, globalToasts.value.length)
    apiAppointments.value = []
    getGate.deferred = false
    getGate.release = null
    localStorage.removeItem('user')
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
    apiAppointments.value = t7DefaultAppointments
    getGate.deferred = false
    getGate.release = null
    vi.useRealTimers()
    localStorage.removeItem('user')
  })

  it('shows the motif empty block and hides the grids on an empty range (T7/D1)', async () => {
    const wrapper = mountPage()
    await flushPromises()

    const empty = wrapper.find('[data-calendar-empty]')
    expect(empty.exists()).toBe(true)

    // shared dental motif (box variant — the hero keeps the only other layer)
    expect(empty.find('[data-calendar-empty-motif]').exists()).toBe(true)
    const motifs = wrapper.findAllComponents(DentalMotifLayer)
    expect(motifs.filter(m => m.props('variant') === 'box')).toHaveLength(1)

    // per-view copy: starts on the week view
    expect(empty.text()).toContain('Sin citas esta semana')

    // grids' content is hidden (no slot hit areas, no chips)
    expect(wrapper.find('[data-calendar-slot]').exists()).toBe(false)
    expect(wrapper.findAll('[data-calendar-appointment]')).toHaveLength(0)

    // optional CTA, present for a role that can create appointments
    expect(empty.find('[data-calendar-action="empty-new-appointment"]').exists()).toBe(true)

    // day view copy
    await wrapper.find('[data-calendar-view="day"]').trigger('click')
    await flushPromises()
    expect(wrapper.find('[data-calendar-empty]').text()).toContain('Sin citas este día')

    // month view copy + month grid hidden too
    await wrapper.find('[data-calendar-view="month"]').trigger('click')
    await flushPromises()
    expect(wrapper.find('[data-calendar-empty]').text()).toContain('Sin citas este mes')
    expect(wrapper.find('[data-calendar-month-grid]').exists()).toBe(false)
    expect(wrapper.findAll('[data-calendar-month-day]')).toHaveLength(0)
  })

  it('hides the empty block for roles without createAppointment (T7 gate)', async () => {
    localStorage.setItem('user', JSON.stringify({ id: 99, role: 'finanzas', name: 'Fin' }))
    const wrapper = mountPage()
    await flushPromises()

    const empty = wrapper.find('[data-calendar-empty]')
    expect(empty.exists()).toBe(true)
    expect(empty.find('[data-calendar-action="empty-new-appointment"]').exists()).toBe(false)
  })

  it('never shows the empty block while the range is loading (T7/D9)', async () => {
    getGate.deferred = true
    const wrapper = mountPage()
    await nextTick()

    expect(wrapper.text()).toContain('Cargando agenda...')
    expect(wrapper.find('[data-calendar-empty]').exists()).toBe(false)

    getGate.deferred = false
    getGate.release()
    await flushPromises()

    expect(wrapper.find('[data-calendar-empty]').exists()).toBe(true)
  })
})
