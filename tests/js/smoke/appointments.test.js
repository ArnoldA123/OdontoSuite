import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { nextTick } from 'vue'
import { createRouter, createMemoryHistory } from 'vue-router'
const {
  patchMock,
  postMock,
  apiAppointments,
  deleteMock,
  getGate,
  getCalls,
  apiPatients,
  apiProfiles,
  apiAppointmentTypes,
  apiAvailability,
  echoListeners
} = vi.hoisted(() => ({
  patchMock: vi.fn(),
  // Redesign del alta: los dobles de `get` que la superficie nueva necesita
  // (búsqueda de paciente, perfiles de la lista de pares y la lectura del día
  // que puede fallar). Los valores por omisión dejan intacto el resto de la
  // suite, que solo depende de `apiAppointments`.
  getCalls: [],
  apiPatients: { value: [] },
  apiProfiles: { professionals: null, chairs: null },
  // El catálogo de tipos trae la especialidad de cada uno (el contrato que
  // publica `GET /api/appointment-types/active` desde esta ronda).
  apiAppointmentTypes: { value: null },
  apiAvailability: { fail: false },
  // El canal privado guarda sus manejadores para que una prueba pueda emitir
  // el eco de la difusión (`.appointment.created`) sin un Reverb vivo.
  echoListeners: { handlers: {} },
  // #281: each custody action walks its own door (check-in / announce /
  // start-attention), so the spy pins the exact endpoint per action.
  postMock: vi.fn().mockResolvedValue({ data: {} }),
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
    get: (url, options = {}) => {
      getCalls.push({ url: String(url), params: options?.params || null })

      if (String(url).includes('/api/patients/search')) {
        return Promise.resolve({ data: JSON.parse(JSON.stringify(apiPatients.value)) })
      }
      if (String(url) === '/api/users/active' && apiProfiles.professionals) {
        return Promise.resolve({ data: JSON.parse(JSON.stringify(apiProfiles.professionals)) })
      }
      if (String(url).startsWith('/api/dental-chairs/active') && apiProfiles.chairs) {
        return Promise.resolve({ data: JSON.parse(JSON.stringify(apiProfiles.chairs)) })
      }
      if (String(url) === '/api/appointment-types/active' && apiAppointmentTypes.value) {
        return Promise.resolve({ data: JSON.parse(JSON.stringify(apiAppointmentTypes.value)) })
      }
      if (String(url) === '/api/appointments' && apiAvailability.fail) {
        return Promise.reject(new Error('network'))
      }

      const payload = () => ({ data: JSON.parse(JSON.stringify(apiAppointments.value)) })
      if (getGate.deferred) {
        return new Promise(resolve => {
          getGate.release = () => resolve(payload())
        })
      }
      return Promise.resolve(payload())
    },
    post: postMock,
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
    privateChannel: () => ({
      listen(name, handler) {
        const key = String(name).replace(/^\./, '')
        echoListeners.handlers[key] = [...(echoListeners.handlers[key] || []), handler]
        return this
      }
    }),
    connectionStatus: { value: 'connected' }
  })
}))
// El guard de eco vive en el composable real; aquí solo hace falta su registro
// para que la agenda marque lo que escribe, con la misma semántica de un uso.
const localEcho = new Set()
vi.mock('../../../resources/js/composables/useWebSocketNotifications', () => ({
  useWebSocketNotifications: () => ({}),
  markLocalAppointmentEcho: id => {
    if (id !== null && id !== undefined && id !== '') localEcho.add(String(id))
  },
  consumeLocalAppointmentEcho: id => localEcho.delete(String(id))
}))
// #280: the page derives its action gates from user.permissions. Keep a
// reactive role handle so a test can switch roles without reimporting modules.
const authRole = vi.hoisted(() => ({ ref: null }))
vi.mock('../../../resources/js/composables/useAuth', async () => {
  const { ref, computed } = await import('vue')
  const { default: rolePermissions } = await import('../support/role-permissions.json')
  const role = ref('administrador')
  authRole.ref = role
  const user = computed(() => ({
    id: 1,
    name: 'Test User',
    role: role.value,
    permissions: rolePermissions[role.value] ?? []
  }))
  return {
    useAuth: () => ({
      user,
      token: computed(() => 'test-token'),
      isAuthenticated: computed(() => true),
      isLoading: computed(() => false),
      login: async () => {},
      logout: async () => {},
      authLogout: async () => {},
      getCurrentUser: async () => {},
      hasRole: value => role.value === value,
      hasAnyRole: values => values.includes(role.value)
    })
  }
})
import uiComponents from '../../../resources/js/plugins/ui-components'
import Page from '../../../resources/js/modules/appointments/CalendarPage.vue'
import { useConsultation } from '../../../resources/js/composables/useConsultation'
import NewAppointmentModal from '../../../resources/js/components/appointments/NewAppointmentModal.vue'
import Modal from '../../../resources/js/components/ui/Modal.vue'
import DentalMotifLayer from '../../../resources/js/components/ui/DentalMotifLayer.vue'
import { statusChipClasses } from '../../../resources/js/modules/appointments/appointmentStatus.js'
import { formatTime } from '../../../resources/js/composables/useFormatters.js'
import { globalToasts } from '../../../resources/js/composables/useToast.js'
const router = createRouter({
  history: createMemoryHistory(),
  routes: [{ path: '/:pathMatch(.*)*', component: { template: '<div />' } }]
})
beforeEach(() => {
  if (authRole.ref) authRole.ref.value = 'administrador'
  // T6 — la agenda abre en Día. Las medidas de este archivo son de la Semana
  // (chips por minuto, arrastre, franja de huecos) y del Mes, que se abren a
  // mano: la preferencia declarada aquí es el estado de partida, no el
  // comportamiento por omisión (eso lo fijan `calendar-responsive.test.js` y
  // `calendar-mobile-layout.test.js`).
  window.localStorage.setItem('calendar-view', 'week')
})

describe('appointments smoke', () => {
  it('mounts CalendarPage.vue and renders content', async () => {
    const wrapper = mount(Page, { global: { plugins: [router, uiComponents] } })
    await flushPromises()
    // La cejilla nombra la superficie y la sede que la agenda está mostrando.
    expect(wrapper.find('[data-page-header-kicker]').text()).toContain('Calendario')
    expect(wrapper.find('[data-page-header-title]').exists()).toBe(true)
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

  it('renders the appointment notes as a textarea instead of an invalid input type', async () => {
    const warnSpy = vi.spyOn(console, 'warn').mockImplementation(() => {})

    const wrapper = mount(NewAppointmentModal, {
      props: { modelValue: true },
      global: { plugins: [uiComponents], stubs: { teleport: true } }
    })
    await flushPromises()

    expect(wrapper.find('textarea').exists()).toBe(true)
    expect(
      warnSpy.mock.calls
        .map(args => String(args[0]))
        .filter(message => message.includes('custom validator check failed for prop "type"'))
    ).toEqual([])

    warnSpy.mockRestore()
    wrapper.unmount()
  })
})

// T8b defect 1 — click-to-create prefill race: clicking an empty slot must
// land in NewAppointmentModal with the date and the hour ALREADY populated
// from the slot (`YYYY-MM-DD` + `HH:mm`), regardless of the order in which
// the page sets `newAppointmentPrefill` and `showNewAppointmentModal`. The old
// T4 anchor only asserted the modal *props*; this block asserts the rendered
// input values (the thing the real-browser pass saw empty).
//
// The dialog reads "cuándo" as two fields (date + time), so the prefill is
// measured on both halves — the contract is the same one the old
// `datetime-local` carried.
describe('T8b click-to-create prefill smoke (defect 1)', () => {
  const expectedPrefill = slotKey => {
    const dateStr = slotKey.slice(0, 10)
    const hour = Number(slotKey.slice(11))
    return { date: dateStr, time: `${String(hour).padStart(2, '0')}:00` }
  }

  const formRoot = () => document.querySelector('[data-appointment-form]')
  const dateInput = () => document.querySelector('[data-appointment-form] input[type="date"]')
  // La hora ya no es un `<input type="time">`: la elige la rejilla de casillas,
  // así que la hora del formulario es la casilla marcada.
  const timeInput = () =>
    document.querySelector('[data-appointment-form] [data-appointment-slot][aria-pressed="true"]')

  const selectedTime = () => {
    const slot = timeInput()
    return slot ? slot.getAttribute('data-appointment-slot') : null
  }

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

    const input = { date: dateInput(), time: timeInput() }
    expect(input.date, 'the modal form must render its date input').not.toBeNull()
    expect(input.time, 'the modal hour grid must mark the prefilled slot').not.toBeNull()
    expect(input.date.value).toBe(expectedPrefill(key).date)
    expect(selectedTime()).toBe(expectedPrefill(key).time)
  })

  it('populates the Fecha y Hora input from the clicked slot in Day view', async () => {
    // T5a: el tablero sólo ofrece huecos de 30 a 120 min. La cita por omisión
    // deja el día en dos huecos mayores, así que este caso monta una cita de
    // 60 min a las 09:00 y deja el de 07:00–08:00 agendable.
    const defaultAppointments = apiAppointments.value
    apiAppointments.value = [
      {
        ...defaultAppointments[0],
        scheduled_at: '2026-05-13T09:00:00',
        ends_at: '2026-05-13T10:00:00',
        duration_minutes: 60
      }
    ]

    try {
      const wrapper = mountPage()
      await flushPromises()

      await wrapper.find('[data-calendar-view="day"]').trigger('click')
      await flushPromises()

      const slot = wrapper.find('button[data-calendar-slot]')
      expect(slot.exists()).toBe(true)
      const key = slot.attributes('data-calendar-slot')

      await slot.trigger('click')
      await flushPromises()

      const input = { date: dateInput(), time: timeInput() }
      expect(input.date, 'the modal form must render its date input').not.toBeNull()
      expect(input.time, 'the modal hour grid must mark the prefilled slot').not.toBeNull()
      expect(input.date.value).toBe(expectedPrefill(key).date)
      expect(selectedTime()).toBe(expectedPrefill(key).time)
    } finally {
      apiAppointments.value = defaultAppointments
    }
  })

  // Order-independence regressions: the prefill must survive every reopen
  // sequence, whatever the previous modal session was (button open with no
  // slot context, repeated slot opens, or an edit session).
  it('re-prefills after a previous open through the Nueva cita button (no slot context)', async () => {
    const wrapper = mountPage()
    await flushPromises()

    await wrapper.find('[data-calendar-action="new-appointment"]').trigger('click')
    await flushPromises()
    // button open carries no slot context
    expect(formRoot()).not.toBeNull()
    expect(dateInput().value).toBe('')
    expect(selectedTime()).toBeNull()

    // close through the modal cancel button
    const cancel = Array.from(document.querySelectorAll('button')).find(
      b => b.textContent.trim() === 'Cancelar'
    )
    cancel.click()
    await flushPromises()
    expect(formRoot()).toBeNull() // closed → form unmounted

    const slot = wrapper.find('button[data-calendar-slot]')
    const key = slot.attributes('data-calendar-slot')
    await slot.trigger('click')
    await flushPromises()
    expect(dateInput().value).toBe(expectedPrefill(key).date)
    expect(selectedTime()).toBe(expectedPrefill(key).time)
  })

  it('re-prefills when the same slot is opened a second time', async () => {
    const wrapper = mountPage()
    await flushPromises()

    const slot = wrapper.find('button[data-calendar-slot]')
    const key = slot.attributes('data-calendar-slot')

    await slot.trigger('click')
    await flushPromises()
    expect(dateInput().value).toBe(expectedPrefill(key).date)
    expect(selectedTime()).toBe(expectedPrefill(key).time)

    const cancel = Array.from(document.querySelectorAll('button')).find(
      b => b.textContent.trim() === 'Cancelar'
    )
    cancel.click()
    await flushPromises()

    await slot.trigger('click')
    await flushPromises()
    expect(dateInput().value).toBe(expectedPrefill(key).date)
    expect(selectedTime()).toBe(expectedPrefill(key).time)
  })

  it('prefills the slot after a previous edit session (edit data must not leak)', async () => {
    authRole.ref.value = 'odontologo'
    const wrapper = mountPage()
    await flushPromises()

    // open edit mode through the details modal
    await wrapper.find('[data-calendar-appointment="1"]').trigger('click')
    await nextTick()
    document.querySelector('[data-calendar-action="edit"]').click()
    await flushPromises()
    // edit populates both halves of the schedule
    expect(dateInput().value).toBe('2026-05-13')
    expect(selectedTime()).toBe('10:00')

    const cancel = Array.from(document.querySelectorAll('button')).find(
      b => b.textContent.trim() === 'Cancelar'
    )
    cancel.click()
    await flushPromises()

    const slot = wrapper.find('button[data-calendar-slot]')
    const key = slot.attributes('data-calendar-slot')
    await slot.trigger('click')
    await flushPromises()

    expect(dateInput().value).toBe(expectedPrefill(key).date)
    expect(selectedTime()).toBe(expectedPrefill(key).time)
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

  // T5b: el bloque de la semana se posiciona por minuto dentro de su columna de
  // día (una hora = 84 px), así que su `top` es la prueba de dónde quedó:
  // 10:00 -> 252 px y 14:00 -> 588 px sobre la ventana que arranca a las 07:00.
  const chipTop = wrapper =>
    Math.round(Number.parseFloat(wrapper.get(chipSelector).element.style.top))
  const WEEK_TOP_10 = 252
  const WEEK_TOP_14 = 588

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
      '[data-calendar-week-column="2026-05-13"] [data-calendar-appointment="1"]'
    )
    expect(moved.exists()).toBe(true)
    expect(chipTop(wrapper)).toBe(WEEK_TOP_14)
    expect(moved.attributes('data-calendar-drag-state')).toBeUndefined()
    expect(moved.attributes('data-calendar-draggable')).toBe('1')
    expect(globalToasts.value.at(-1)?.type).toBe('success')
    expect(document.querySelector('[data-calendar-detail]')).toBeNull()
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
    expect(document.querySelector('[data-calendar-detail]')).not.toBeNull()
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
    const home = wrapper.find(chipSelector)
    expect(home.exists()).toBe(true)
    expect(chipTop(wrapper)).toBe(WEEK_TOP_10)
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
    // asserted on the teleported hook (it was `wrapper.text()` when the modal
    // was a hand-rolled div inside the page tree).
    expect(document.querySelector('[data-calendar-detail]')).toBeNull()
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
    expect(chipTop(wrapper)).toBe(WEEK_TOP_10)
    expect(document.querySelector('[data-calendar-detail]')).toBeNull()

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
    expect(chipTop(wrapper)).toBe(WEEK_TOP_10)
    expect(chipTop(wrapper)).toBe(WEEK_TOP_10)

    const lastToast = globalToasts.value.at(-1)
    expect(lastToast?.type).toBe('error')
    expect(lastToast?.message).toContain('ambiente A1')
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

    expect(chipTop(wrapper)).toBe(WEEK_TOP_10)
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

    expect(chipTop(wrapper)).toBe(WEEK_TOP_10)
    expect(globalToasts.value.at(-1)?.type).toBe('error')
    wrapper.unmount()
  })

  it('gates dragging on editAppointment permission (T5, finanzas cannot drag)', async () => {
    authRole.ref.value = 'finanzas'
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
    // 7 días x 13 horas: la ventana de la semana arranca a las 07:00.
    expect(cells.length).toBe(7 * 13)
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

  const assertChipContract = (chip, view, { time = '10:00 - 10:30' } = {}) => {
    const classes = chip.classes()
    const [{ status }] = apiAppointments.value
    const registryTokens = statusChipClasses(status).split(/\s+/).filter(Boolean)

    // Defect 2: the registry tint is the ONLY background/border-colour the
    // chip wears — no base or template token may compete with it in the
    // cascade (both would set background-color/border-color and the winner
    // would be decided by stylesheet order). S3 moved the status identity
    // into its own ramp, so the expected tint is read from the registry.
    expect(
      classes.filter(c => c.startsWith('bg-')),
      `${view}: single bg`
    ).toEqual(registryTokens.filter(c => c.startsWith('bg-')))
    expect(
      classes.filter(c => c.startsWith('border-')),
      `${view}: single border colour`
    ).toEqual(registryTokens.filter(c => c.startsWith('border-')))

    // Defect 3: compact range, never the long locale form, full title. En el
    // tablero del día un bloque de 30 min imprime sólo su hora de inicio (el
    // rango completo sigue en el `title`), porque su alto es el de esos 30 min.
    expect(chip.text(), `${view}: compact range`).toContain(time)
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
    expect(chip.attributes('data-calendar-block-tier')).toBe('compact')
    assertChipContract(chip, 'day', { time: '10:00' })
  })

  it('carries the registry tint + compact time on the Month chip', async () => {
    const wrapper = mountPage()
    await flushPromises()
    await wrapper.find('[data-calendar-view="month"]').trigger('click')
    await flushPromises()

    const chip = wrapper.find('[data-calendar-appointment="1"]')
    expect(chip.exists()).toBe(true)
    assertChipContract(chip, 'month', { time: '10:00' })
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
      'bg-accent-100'
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
    expect(document.querySelector('[data-calendar-detail]')).not.toBeNull()
  })

  it('drills down from "N más" to the Day view for that date (T6/D8)', async () => {
    const wrapper = mountPage()
    await flushPromises()
    await toMonthView(wrapper)

    await wrapper.find('[data-calendar-more="2026-05-13"]').trigger('click')
    await flushPromises()

    expect(wrapper.find('[data-calendar-view="day"]').attributes('aria-pressed')).toBe('true')
    // El tablero titula con el día visible, sin año: la fecha es el título.
    expect(wrapper.text()).toContain('Miércoles 13 de mayo')
    expect(wrapper.findAll('[data-calendar-month-day]')).toHaveLength(0)
    // Los huecos agendables del recurso declaran su hora de inicio real: la
    // agenda del día ocupa 09:00–13:30, así que el primer hueco abre 07:00.
    const slot = wrapper.find('button[data-calendar-slot="2026-05-13-7"]')
    expect(slot.exists()).toBe(true)
    expect(slot.attributes('data-calendar-free-slot-start')).toBe('07:00')
  })

  it('drills down from the day-number header to the Day view for that date (T6)', async () => {
    const wrapper = mountPage()
    await flushPromises()
    await toMonthView(wrapper)

    await wrapper.find('[data-calendar-day-link="2026-05-20"]').trigger('click')
    await flushPromises()

    expect(wrapper.find('[data-calendar-view="day"]').attributes('aria-pressed')).toBe('true')
    expect(wrapper.text()).toContain('Miércoles 20 de mayo')
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

    // 2) content: the header names the patient and the when-line; the body
    //    carries the facts from the real AppointmentResource payload.
    const dialog = document.querySelector('[role="dialog"]')
    expect(dialog).not.toBeNull()
    expect(dialog.textContent).toContain('Ana Pérez')
    expect(dialog.textContent).toContain('10:00-10:30')
    // El recurso del tablero se llama «ambiente» y su nombre es el de la fila.
    expect(dialog.textContent).toContain('Ambiente')
    expect(dialog.textContent).toContain('A1')

    const details = document.querySelector('[data-calendar-detail]')
    expect(details).not.toBeNull()
    expect(details.textContent).toContain('Dra. Ruiz')
    expect(details.textContent).toContain('Limpieza')
    expect(details.textContent).toContain('30 minutos')
    expect(details.textContent).toContain('Primera visita de control')

    // 3) status pill rendered from the appointmentStatus registry
    //    (label comes from the registry, keyed by data-status).
    const pill = details.querySelector('[data-status="scheduled"]')
    expect(pill).not.toBeNull()
    expect(pill.textContent).toContain('Programada')

    // 4) the five-stage rail replaces the old button pile.
    const rail = details.querySelector('[data-appointment-rail="steps"]')
    expect(rail).not.toBeNull()
    expect(rail.querySelectorAll('[data-appointment-rail-step]')).toHaveLength(5)
  })

  it('binds the appointment into NewAppointmentModal from the Editar action (T7 bugfix)', async () => {
    authRole.ref.value = 'odontologo'
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
    expect(document.body.textContent).toContain('Editar cita')

    // The details surface hands over and closes.
    const modal = detailsModal(wrapper)
    expect(modal).toBeDefined()
    expect(modal.props('modelValue')).toBe(false)
  })

  it('hides Editar from roles without editAppointment and keeps delete gated (T7)', async () => {
    // finanzas: editAppointment false (usePermissions line 30), deleteAppointment false.
    authRole.ref.value = 'finanzas'
    const wrapper = mountPage()
    await flushPromises()
    await openDetails(wrapper)

    expect(document.querySelector('[data-calendar-action="edit"]')).toBeNull()
    expect(document.querySelector('[data-calendar-action="delete"]')).toBeNull()
    // The form never opens from the details surface for this role.
    expect(wrapper.findComponent(NewAppointmentModal).props('modelValue')).toBe(false)
  })

  it('routes delete through useConfirm before hitting the API (T7)', async () => {
    authRole.ref.value = 'administrador'
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
// #281 — custody doors from the calendar. Reception marks the arrival, the
// announcement notifies the professional and the clinic starts the act; each
// action hits its own endpoint (the generic PATCH /status rejects the custody
// statuses), shows its own toast and reloads the agenda.
describe('F1 custody actions smoke (#281)', () => {
  const custodyAppointment = status => ({
    id: 1,
    scheduled_at: '2026-05-13T10:00:00',
    ends_at: '2026-05-13T10:30:00',
    duration_minutes: 30,
    status,
    notes: null,
    patient: { id: 7, first_name: 'Ana', last_name: 'Pérez' },
    user: { id: 2, name: 'Dra. Ruiz' },
    dental_chair: null,
    appointment_type: { id: 3, name: 'Limpieza' }
  })

  const defaultAppointments = apiAppointments.value

  const mounted = []
  const mountPage = () => {
    const host = document.createElement('div')
    document.body.appendChild(host)
    const wrapper = mount(Page, { attachTo: host, global: { plugins: [router, uiComponents] } })
    mounted.push({ wrapper, host })
    return wrapper
  }

  const detailsModal = wrapper =>
    wrapper.findAllComponents(Modal).find(m => m.props('title') === 'Detalles de la Cita')

  const openDetails = async wrapper => {
    await wrapper.find('[data-calendar-appointment="1"]').trigger('click')
    await nextTick()
  }

  beforeEach(() => {
    vi.useFakeTimers({ toFake: ['Date'] })
    vi.setSystemTime(new Date(2026, 4, 13, 7, 0, 0))
    globalToasts.value.splice(0, globalToasts.value.length)
    postMock.mockReset()
    postMock.mockResolvedValue({ data: {} })
    // S5: la llegada se registra sobre una cita confirmada; programada
    // conserva «Confirmar cita» como único primario.
    apiAppointments.value = [custodyAppointment('confirmed')]
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

  it('marks the arrival through POST /check-in and reloads the agenda', async () => {
    const wrapper = mountPage()
    await flushPromises()
    await openDetails(wrapper)

    const arrival = document.querySelector('[data-calendar-action="check-in"]')
    expect(arrival).not.toBeNull()
    expect(arrival.textContent).toContain('Registrar llegada')
    expect(arrival.hasAttribute('data-appointment-primary')).toBe(true)
    expect(document.querySelectorAll('[data-appointment-primary]')).toHaveLength(1)

    arrival.click()
    await flushPromises()

    expect(postMock).toHaveBeenCalledTimes(1)
    expect(postMock).toHaveBeenCalledWith('/api/appointments/1/check-in', {})
    expect(globalToasts.value.at(-1)).toMatchObject({
      type: 'success',
      message: 'Llegada registrada.'
    })

    // the handler reloads the range and drops the selection: details closed
    expect(detailsModal(wrapper).props('modelValue')).toBe(false)
  })

  it('hides the arrival action once the patient is already waiting', async () => {
    apiAppointments.value = [custodyAppointment('arrived')]
    const wrapper = mountPage()
    await flushPromises()
    await openDetails(wrapper)

    expect(document.querySelector('[data-calendar-detail] [data-status="arrived"]')).not.toBeNull()
    expect(document.querySelector('[data-calendar-action="check-in"]')).toBeNull()
  })
})

// S1/S2/S5 — el detalle de la cita comunica el estado con un rail de cinco
// etapas (o una franja de desenlace) y ofrece UNA acción primaria derivada del
// estado. Las puertas de permiso siguen decidiendo si su botón existe, y las
// horas de cada etapa salen sólo de campos publicados por la API.
describe('detalle de cita: siguiente paso, rail y desenlaces', () => {
  const appointment = (status, extra = {}) => ({
    id: 1,
    scheduled_at: '2026-05-13T10:00:00',
    ends_at: '2026-05-13T10:30:00',
    duration_minutes: 30,
    status,
    notes: null,
    patient: { id: 7, first_name: 'Ana', last_name: 'Pérez' },
    user: { id: 2, name: 'Dra. Ruiz' },
    dental_chair: null,
    appointment_type: { id: 3, name: 'Limpieza' },
    ...extra
  })

  const defaultAppointments = apiAppointments.value

  const mounted = []
  const mountPage = () => {
    const host = document.createElement('div')
    document.body.appendChild(host)
    const wrapper = mount(Page, { attachTo: host, global: { plugins: [router, uiComponents] } })
    mounted.push({ wrapper, host })
    return wrapper
  }

  const unmountAll = () => {
    while (mounted.length) {
      const { wrapper, host } = mounted.pop()
      try {
        wrapper.unmount()
      } catch {
        // already unmounted
      }
      host.remove()
    }
  }

  const openDetails = async status => {
    apiAppointments.value = [appointment(status)]
    const wrapper = mountPage()
    await flushPromises()
    await wrapper.find('[data-calendar-appointment="1"]').trigger('click')
    await nextTick()
    return wrapper
  }

  const primaries = () => document.querySelectorAll('[data-appointment-primary]')

  beforeEach(async () => {
    vi.useFakeTimers({ toFake: ['Date'] })
    vi.setSystemTime(new Date(2026, 4, 13, 7, 0, 0))
    globalToasts.value.splice(0, globalToasts.value.length)
    authRole.ref.value = 'administrador'
    await router.push('/calendar')
  })

  afterEach(() => {
    unmountAll()
    apiAppointments.value = defaultAppointments
    authRole.ref.value = 'administrador'
    vi.useRealTimers()
    localStorage.removeItem('user')
  })

  // S5 — una transición por estado: el primario es la fase que sigue, y nunca
  // hay dos fases en la misma pantalla.
  it.each([
    ['scheduled', 1, 'status-confirmed'],
    ['confirmed', 1, 'check-in'],
    ['arrived', 1, 'start-attention'],
    ['in_consultation', 0, null],
    ['in_progress', 0, null],
    ['completed', 0, null],
    ['cancelled', 0, null],
    ['no_show', 0, null],
    ['rescheduled', 0, null]
  ])('%s publica exactamente %i acción(es) primaria(s)', async (status, expected, hook) => {
    await openDetails(status)

    const primaryNodes = primaries()
    expect(primaryNodes).toHaveLength(expected)
    if (hook) {
      expect(primaryNodes[0].getAttribute('data-calendar-action')).toBe(hook)
    }
  })

  it('nunca publica dos botones de fase en el mismo estado', async () => {
    const phaseHooks = ['status-confirmed', 'check-in', 'start-attention', 'announce']

    for (const status of [
      'scheduled',
      'confirmed',
      'arrived',
      'in_consultation',
      'in_progress',
      'completed',
      'cancelled',
      'no_show',
      'rescheduled'
    ]) {
      await openDetails(status)

      const card = document.querySelector('[data-appointment-now]')
      const rendered = card
        ? [...card.querySelectorAll('[data-calendar-action]')]
            .map(node => node.getAttribute('data-calendar-action'))
            .filter(hook => phaseHooks.includes(hook))
        : []

      expect(rendered.length, status).toBeLessThanOrEqual(1)
      expect(primaries().length, status).toBeLessThanOrEqual(1)

      unmountAll()
    }
  })

  it('no ofrece registrar la llegada antes de confirmar', async () => {
    await openDetails('scheduled')

    expect(document.querySelector('[data-calendar-action="check-in"]')).toBeNull()
    expect(document.querySelector('[data-calendar-action="status-confirmed"]')).not.toBeNull()
    expect(primaries()).toHaveLength(1)
  })

  it('publica la matriz de acciones de la tarjeta por estado', async () => {
    const expected = {
      scheduled: ['status-confirmed'],
      confirmed: ['check-in'],
      arrived: ['start-attention', 'my-day'],
      in_consultation: ['my-day'],
      in_progress: ['my-day'],
      completed: [],
      cancelled: [],
      no_show: [],
      rescheduled: []
    }

    for (const [status, hooks] of Object.entries(expected)) {
      await openDetails(status)

      const card = document.querySelector('[data-appointment-now]')
      const rendered = hooks.length
        ? [...card.querySelectorAll('[data-calendar-action]')].map(node =>
            node.getAttribute('data-calendar-action')
          )
        : []
      expect(rendered, status).toEqual(hooks)

      unmountAll()
    }
  })

  it('pinta la leyenda compacta con las rampas nuevas de estado', async () => {
    apiAppointments.value = [appointment('scheduled')]
    mountPage()
    await flushPromises()

    const legend = document.querySelector('[data-calendar-legend]')
    expect(legend).not.toBeNull()
    // T5b: una sola línea de puntos y etiquetas con los cinco estados que se
    // leen en el bloque; los tres desenlaces salen de la leyenda.
    const entries = [...legend.querySelectorAll('[data-status]')]
    expect(entries).toHaveLength(5)
    expect(entries.map(entry => entry.getAttribute('data-status'))).toEqual([
      'scheduled',
      'confirmed',
      'arrived',
      'in_progress',
      'completed'
    ])
    const dots = entries.map(entry => entry.querySelector('span').className)
    expect(dots).toEqual(
      expect.arrayContaining([
        expect.stringContaining('bg-systemIndigo-500'),
        expect.stringContaining('bg-systemYellow-500')
      ])
    )
    expect(entries[0].className).toContain('text-[10px]')
    expect(legend.querySelectorAll('[data-variant]')).toHaveLength(0)
  })

  it('guarda cancelar y eliminar en el menú de secundarias', async () => {
    await openDetails('scheduled')

    const menu = document.querySelector('[data-appointment-menu]')
    expect(menu).not.toBeNull()
    expect(menu.querySelector('[data-calendar-action="status-cancelled"]')).not.toBeNull()
    expect(menu.querySelector('[data-calendar-action="delete"]')).not.toBeNull()
    expect(menu.querySelector('[data-appointment-action="reschedule"]')).not.toBeNull()

    // S1: ninguna destructiva se sienta junto al primario.
    const now = document.querySelector('[data-appointment-now]')
    expect(now.querySelector('[data-calendar-action="status-cancelled"]')).toBeNull()
    expect(now.querySelector('[data-calendar-action="delete"]')).toBeNull()

    // El disparador abre y cierra su panel.
    expect(menu.style.display).toBe('none')
    document.querySelector('[data-appointment-menu-trigger]').click()
    await flushPromises()
    expect(menu.style.display).not.toBe('none')
    document.querySelector('[data-appointment-menu-trigger]').click()
    await flushPromises()
    expect(menu.style.display).toBe('none')
  })

  it('sube el relleno sólo en los estados que piden acción', async () => {
    const cases = [
      ['scheduled', 'bg-systemGray-100', false],
      ['confirmed', 'bg-systemSteel-50', false],
      ['arrived', 'bg-systemIndigo-100', true],
      ['in_consultation', 'bg-systemYellow-100', true],
      ['in_progress', 'bg-systemYellow-100', true],
      ['completed', 'bg-systemGreen-50', false],
      ['cancelled', 'bg-systemRed-50', false],
      ['no_show', 'bg-systemOrange-50', false],
      ['rescheduled', 'bg-systemPurple-50', false]
    ]

    for (const [status, wash, pulses] of cases) {
      await openDetails(status)

      const pill = document.querySelector(`[data-calendar-detail] [data-status="${status}"]`)
      expect(pill, status).not.toBeNull()
      expect(pill.className, status).toContain(wash)

      // `rescheduled` is the one state without a dot; only the states that ask
      // for an action pulse it.
      const dot = pill.querySelector('.status-badge__dot')
      if (dot) {
        expect(dot.className.includes('motion-safe:animate-pulse-subtle'), status).toBe(pulses)
      } else {
        expect(pulses, `${status} needs a dot to pulse`).toBe(false)
      }

      unmountAll()
    }
  })

  it('dibuja el rail sin halo ni pulso y con los conectores recortados', async () => {
    await openDetails('arrived')

    const rail = document.querySelector('[data-appointment-rail="steps"]')
    expect(rail).not.toBeNull()
    expect(rail.innerHTML).not.toContain('ring-4')
    expect(rail.innerHTML).not.toContain('animate-pulse')

    const connectors = rail.querySelectorAll('[data-appointment-rail-connector]')
    expect(connectors).toHaveLength(4)
    for (const connector of connectors) {
      const style = connector.getAttribute('style') || ''
      expect(style).toContain('left: calc(-50% + 14px)')
      expect(style).toContain('right: calc(50% - 14px)')
      expect(style).not.toContain('width')
    }

    // El nodo actual se rellena con el color del estado y un punto blanco.
    const current = rail.querySelector('[aria-current="step"]')
    expect(current).not.toBeNull()
    expect(current.innerHTML).toContain('bg-systemIndigo-500')
    expect(current.innerHTML).toContain('bg-white')
    expect(current.innerHTML).not.toContain('ring-')
  })

  it('muestra sede y procedimiento cuando el payload los publica', async () => {
    const factLabels = () =>
      Array.from(document.querySelectorAll('[data-calendar-detail] dt')).map(node =>
        node.textContent.trim()
      )

    apiAppointments.value = [
      appointment('confirmed', {
        branch: { id: 3, name: 'San Isidro' },
        procedure: { id: 5, name: 'Resina compuesta', code: 'PROC-001' }
      })
    ]
    const wrapper = mountPage()
    await flushPromises()
    await wrapper.find('[data-calendar-appointment="1"]').trigger('click')
    await nextTick()

    const detail = document.querySelector('[data-calendar-detail]')
    expect(factLabels()).toContain('Sede')
    expect(factLabels()).toContain('Procedimiento')
    expect(detail.textContent).toContain('San Isidro')
    expect(detail.textContent).toContain('Resina compuesta')
    unmountAll()

    // S6 — sin las relaciones precargadas, las dos filas no existen.
    await openDetails('confirmed')
    expect(factLabels()).not.toContain('Sede')
    expect(factLabels()).not.toContain('Procedimiento')
    expect(document.querySelector('[data-calendar-detail]').textContent).not.toContain('San Isidro')
  })

  it('marca la etapa actual de cada estado vivo en el rail', async () => {
    const expected = {
      scheduled: 'scheduled',
      confirmed: 'confirmed',
      arrived: 'arrived',
      in_consultation: 'in_consultation',
      completed: 'completed'
    }

    for (const [status, step] of Object.entries(expected)) {
      await openDetails(status)

      expect(document.querySelectorAll('[data-appointment-rail-step]')).toHaveLength(5)
      const current = document.querySelector('[data-appointment-rail-step][aria-current="step"]')
      expect(current, status).not.toBeNull()
      expect(current.getAttribute('data-appointment-rail-step')).toBe(step)

      unmountAll()
    }
  })

  it('sustituye el rail por una franja de desenlace en los tres desenlaces', async () => {
    for (const status of ['cancelled', 'no_show', 'rescheduled']) {
      await openDetails(status)

      const band = document.querySelector('[data-appointment-rail="outcome"]')
      expect(band, status).not.toBeNull()
      expect(document.querySelector('[data-appointment-rail-step]')).toBeNull()
      expect(band.querySelector(`[data-status="${status}"]`), status).not.toBeNull()

      unmountAll()
    }
  })

  it('distingue con una píldora propia los cuatro estados que antes eran verdes', async () => {
    const variants = new Set()

    for (const status of ['scheduled', 'confirmed', 'arrived', 'rescheduled']) {
      await openDetails(status)

      const pill = document.querySelector(`[data-calendar-detail] [data-status="${status}"]`)
      expect(pill, status).not.toBeNull()
      variants.add(pill.getAttribute('data-variant'))

      unmountAll()
    }

    expect(variants.size).toBe(4)
    expect(variants.has('success')).toBe(false)
  })

  it('presenta el estado legacy in_progress igual que En consulta', async () => {
    await openDetails('in_progress')

    const pill = document.querySelector('[data-calendar-detail] [data-status="in_progress"]')
    expect(pill.textContent).toContain('En consulta')
    const current = document.querySelector('[data-appointment-rail-step][aria-current="step"]')
    expect(current.getAttribute('data-appointment-rail-step')).toBe('in_consultation')
    expect(primaries()).toHaveLength(0)
    expect(document.querySelector('[data-calendar-action="my-day"]')).not.toBeNull()
  })

  it('no inventa horas de etapa: sólo las publicadas aparecen', async () => {
    await openDetails('arrived')
    expect(document.querySelectorAll('[data-appointment-rail-time]')).toHaveLength(0)
    // The five rows always exist (alignment); their text is what is absent.
    expect(document.querySelectorAll('[data-appointment-rail-time-slot]')).toHaveLength(5)
    expect(
      Array.from(document.querySelectorAll('[data-appointment-rail-time-slot]')).every(
        node => node.textContent.trim() === ''
      )
    ).toBe(true)
    expect(document.querySelector('[data-appointment-status-since]')).toBeNull()
    unmountAll()

    apiAppointments.value = [
      appointment('arrived', {
        created_at: '2026-05-13T09:12:00',
        checked_in_at: '2026-05-13T10:02:00'
      })
    ]
    const wrapper = mountPage()
    await flushPromises()
    await wrapper.find('[data-calendar-appointment="1"]').trigger('click')
    await nextTick()

    const times = Array.from(document.querySelectorAll('[data-appointment-rail-time]')).map(node =>
      node.textContent.trim()
    )
    expect(times).toEqual([formatTime('2026-05-13T09:12:00'), formatTime('2026-05-13T10:02:00')])
    expect(document.querySelector('[data-appointment-status-since]').textContent).toContain(
      `Llegó a las ${formatTime('2026-05-13T10:02:00')}`
    )
  })

  it('inicia la atención desde arrived con start.consultation', async () => {
    postMock.mockClear()
    await openDetails('arrived')

    const start = document.querySelector('[data-calendar-action="start-attention"]')
    expect(start).not.toBeNull()
    expect(start.textContent).toContain('Iniciar atención')
    expect(start.hasAttribute('data-appointment-primary')).toBe(true)
    expect(primaries()).toHaveLength(1)
    // Nunca las dos puertas a la vez.
    expect(document.querySelector('[data-calendar-action="announce"]')).toBeNull()

    const bridge = document.querySelector('[data-calendar-action="my-day"]')
    expect(bridge).not.toBeNull()
    expect(bridge.tagName).toBe('A')
    expect(bridge.textContent.trim()).toBe('Ir a mi jornada')
    expect(bridge.hasAttribute('data-appointment-primary')).toBe(false)

    start.click()
    await flushPromises()
    expect(postMock).toHaveBeenCalledWith('/api/appointments/1/start-attention', {})
    expect(globalToasts.value.at(-1)).toMatchObject({
      type: 'success',
      message: 'Atención iniciada.'
    })
    // La puerta recarga la agenda y cierra el detalle.
    expect(document.querySelector('[data-calendar-detail]')).toBeNull()
  })

  it('anuncia al paciente cuando no puede iniciar la atención', async () => {
    authRole.ref.value = 'recepcionista'
    postMock.mockClear()

    await openDetails('arrived')

    expect(document.querySelector('[data-calendar-action="start-attention"]')).toBeNull()
    const announce = document.querySelector('[data-calendar-action="announce"]')
    expect(announce).not.toBeNull()
    expect(announce.textContent).toContain('Anunciar paciente')
    expect(announce.hasAttribute('data-appointment-primary')).toBe(true)
    expect(primaries()).toHaveLength(1)
    // Recepción no tiene Mi jornada: no queda un hueco vacío.
    expect(document.querySelector('[data-calendar-action="my-day"]')).toBeNull()

    announce.click()
    await flushPromises()
    expect(postMock).toHaveBeenCalledWith('/api/appointments/1/announce', {})
    expect(globalToasts.value.at(-1)).toMatchObject({
      type: 'success',
      message: 'Paciente anunciado.'
    })
  })

  it('no abre el cierre clínico desde la agenda', async () => {
    await openDetails('arrived')

    expect(document.querySelector('[data-calendar-action="complete-consultation"]')).toBeNull()
    expect(document.querySelector('[data-calendar-action="start-consultation"]')).toBeNull()
    expect(document.body.textContent).not.toContain('Cerrar atención')
    expect(document.body.textContent).not.toContain('Firma')
  })

  it('bridges a consultation in progress to My Day and navigates there', async () => {
    await openDetails('in_consultation')

    const bridge = document.querySelector('[data-calendar-action="my-day"]')
    expect(bridge).not.toBeNull()
    expect(bridge.textContent.trim()).toBe('Ir a mi jornada')
    expect(bridge.getAttribute('href')).toBe('/my-day')

    bridge.click()
    await flushPromises()
    expect(router.currentRoute.value.path).toBe('/my-day')
  })

  it('keeps reception check-in and hides the doors it cannot open', async () => {
    authRole.ref.value = 'recepcionista'

    // Programada: sólo Confirmar cita; la llegada espera su turno.
    await openDetails('scheduled')
    expect(document.querySelector('[data-calendar-action="check-in"]')).toBeNull()
    const confirm = document.querySelector('[data-calendar-action="status-confirmed"]')
    expect(confirm.textContent).toContain('Confirmar cita')
    expect(confirm.hasAttribute('data-appointment-primary')).toBe(true)
    expect(document.querySelector('[data-calendar-action="my-day"]')).toBeNull()
    unmountAll()

    await openDetails('confirmed')

    const checkIn = document.querySelector('[data-calendar-action="check-in"]')
    expect(checkIn).not.toBeNull()
    expect(checkIn.textContent).toContain('Registrar llegada')
    expect(checkIn.hasAttribute('data-appointment-primary')).toBe(true)
    expect(document.querySelector('[data-calendar-action="status-confirmed"]')).toBeNull()
    expect(document.querySelector('[data-calendar-action="my-day"]')).toBeNull()
    unmountAll()

    // Recepción anuncia al profesional; la atención se inicia en otra puerta.
    await openDetails('arrived')
    const announce = document.querySelector('[data-calendar-action="announce"]')
    expect(announce).not.toBeNull()
    expect(announce.textContent).toContain('Anunciar paciente')
    expect(announce.hasAttribute('data-appointment-primary')).toBe(true)
    expect(document.querySelector('[data-calendar-action="start-attention"]')).toBeNull()
    expect(document.querySelector('[data-calendar-action="my-day"]')).toBeNull()
    unmountAll()

    // En consulta sin `view.my_day`: ni primario ni enlace, ningún hueco.
    await openDetails('in_consultation')
    expect(primaries()).toHaveLength(0)
    expect(document.querySelector('[data-calendar-action="my-day"]')).toBeNull()
  })
})

// The two doors reception does not own are pinned at the composable contract:
// can.announcePatient / can.startConsultation are the flags that reveal their
// buttons, so the endpoint + toast contract is exercised here (the page wiring
// itself is pinned structurally by CitasCalendarDetailsModalTest).
describe('useConsultation custody actions (#281)', () => {
  beforeEach(() => {
    globalToasts.value.splice(0, globalToasts.value.length)
    postMock.mockReset()
    postMock.mockResolvedValue({ data: { id: 1 } })
  })

  it('announces the patient through POST /announce without touching the status', async () => {
    const { announcePatient } = useConsultation()

    await announcePatient({ id: 4 })

    expect(postMock).toHaveBeenCalledWith('/api/appointments/4/announce', {})
    expect(globalToasts.value.at(-1)).toMatchObject({
      type: 'success',
      message: 'Paciente anunciado.'
    })
  })

  it('starts the attention through POST /start-attention', async () => {
    const { startAttention } = useConsultation()

    await startAttention({ id: 4 })

    expect(postMock).toHaveBeenCalledWith('/api/appointments/4/start-attention', {})
    expect(globalToasts.value.at(-1)).toMatchObject({
      type: 'success',
      message: 'Atención iniciada.'
    })
  })

  it('surfaces the custody rejection message and rethrows (409)', async () => {
    const { startAttention } = useConsultation()
    postMock.mockRejectedValueOnce({
      response: {
        status: 409,
        data: {
          error: {
            error_code: 'attention_not_allowed',
            message: 'La atención se inicia desde una cita con llegada registrada.',
            context: { current_status: 'scheduled' }
          }
        }
      }
    })

    await expect(startAttention({ id: 4 })).rejects.toBeTruthy()
    expect(globalToasts.value.at(-1)).toMatchObject({
      type: 'error',
      message: 'La atención se inicia desde una cita con llegada registrada.'
    })
  })
})

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

  it('shows the quiet empty block and hides the grids on an empty range (T7/D1)', async () => {
    const wrapper = mountPage()
    await flushPromises()

    const empty = wrapper.find('[data-calendar-empty]')
    expect(empty.exists()).toBe(true)

    // Empty states match the shared primitive: icon, title and copy, no motif.
    expect(empty.find('[data-calendar-empty-motif]').exists()).toBe(false)
    expect(wrapper.findAllComponents(DentalMotifLayer)).toHaveLength(0)

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
    authRole.ref.value = 'finanzas'
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

// Rediseño del alta de cita (ronda 2) — la superficie se mide sobre el
// contrato que la ronda 2 fija, no sobre sus clases:
//   · el paciente se busca en el servidor (`q`, mínimo 2 caracteres);
//   · profesional y sillón son dos listas independientes: cada recurso declara
//     su ocupación con su motivo, y ninguna combinación de dos recursos libres
//     queda bloqueada por no ser «la pareja» de la agenda;
//   · la hora se elige en una rejilla de casillas de 15 minutos, no en la rueda
//     nativa de un `<input type="time">`;
//   · el tipo de cita depende de la especialidad del profesional elegido;
//   · una cita creada deja una sola confirmación, con su acción a la agenda.
describe('alta de cita: captura rápida + disponibilidad', () => {
  const mountModal = async (props = {}, options = {}) => {
    const wrapper = mount(NewAppointmentModal, {
      props: { modelValue: true, ...props },
      ...options,
      global: { plugins: [uiComponents], stubs: { teleport: true }, ...(options.global || {}) }
    })
    await flushPromises()
    return wrapper
  }

  const schedule = async (wrapper, date = '2026-05-13', time = '10:00') => {
    await wrapper.find('[data-appointment-form] input[type="date"]').setValue(date)
    await flushPromises()

    // La rejilla se pagina por bloques de tres horas: la casilla pedida puede
    // estar en otro bloque, así que el ayudante los recorre.
    if (!wrapper.find(`[data-appointment-form] [data-appointment-slot="${time}"]`).exists()) {
      for (const tab of wrapper.findAll('[data-appointment-hour-block]')) {
        await tab.trigger('click')
        await flushPromises()
        if (wrapper.find(`[data-appointment-form] [data-appointment-slot="${time}"]`).exists())
          break
      }
    }

    const slot = wrapper.find(`[data-appointment-form] [data-appointment-slot="${time}"]`)
    expect(slot.exists(), `the hour grid must offer ${time}`).toBe(true)
    await slot.trigger('click')
    await flushPromises()
  }

  beforeEach(() => {
    getCalls.length = 0
    apiPatients.value = []
    apiProfiles.professionals = null
    apiProfiles.chairs = null
    apiAvailability.fail = false
    globalToasts.value.splice(0, globalToasts.value.length)
  })

  it('searches the patient on the server and picks it with the keyboard', async () => {
    apiPatients.value = [
      { id: 41, name: 'Ana Torres Chávez', dni: '45872310', age: 34 },
      { id: 42, name: 'Jorge Torres Medina', dni: '70912844', age: 51 }
    ]

    const wrapper = await mountModal()
    const search = wrapper.find('#appointment-patient-search')
    expect(search.exists()).toBe(true)

    await search.setValue('tor')
    // 300 ms of typing idle before the request leaves the component.
    await new Promise(resolve => setTimeout(resolve, 350))
    await flushPromises()

    const lookups = getCalls.filter(call => call.url.includes('/api/patients/search'))
    expect(lookups).toHaveLength(1)
    expect(lookups[0].params).toMatchObject({ q: 'tor' })
    // The whole patient table is no longer loaded into the modal.
    expect(getCalls.some(call => /^\/api\/patients\?/.test(call.url))).toBe(false)

    const listbox = wrapper.find('[role="listbox"]')
    expect(listbox.exists()).toBe(true)
    const options = wrapper.findAll('[role="option"]')
    expect(options).toHaveLength(2)
    expect(options[0].attributes('aria-selected')).toBe('true')

    await search.trigger('keydown', { key: 'ArrowDown' })
    expect(wrapper.findAll('[role="option"]')[1].attributes('aria-selected')).toBe('true')

    await search.trigger('keydown', { key: 'Enter' })
    await flushPromises()

    const identity = wrapper.find('[data-appointment-patient]')
    expect(identity.exists()).toBe(true)
    expect(identity.attributes('data-appointment-patient')).toBe('42')
    expect(identity.text()).toContain('Jorge Torres Medina')
    expect(wrapper.find('[role="listbox"]').exists()).toBe(false)

    wrapper.unmount()
  })

  it('offers professionals and chairs as two independent lists, each with its reason', async () => {
    apiProfiles.professionals = [
      { id: 2, name: 'Dra. Ruiz', specialty: 'Odontología', role: 'odontologo' },
      { id: 3, name: 'Dr. Pinto', specialty: 'Implantología', role: 'implantologo' }
    ]
    apiProfiles.chairs = [
      { id: 3, name: 'Sillón 3', code: 'S3' },
      { id: 4, name: 'Sillón 4', code: 'S4' }
    ]

    const wrapper = await mountModal()
    await schedule(wrapper)

    // La cita sembrada (10:00–10:30) ocupa a la Dra. Ruiz y no a ningún sillón:
    // la disponibilidad del sillón es independiente de la del profesional.
    const door = wrapper.find('[data-appointment-team-door]')
    expect(door.exists()).toBe(true)
    expect(door.attributes('data-appointment-free-professionals')).toBe('1')
    expect(door.attributes('data-appointment-free-chairs')).toBe('2')

    const people = wrapper.findAll('[data-appointment-professional]')
    expect(people).toHaveLength(2)
    const busyPerson = people.find(row => row.attributes('data-appointment-professional') === '2')
    expect(busyPerson.text()).toContain('Ocupado hasta 10:30')
    expect(busyPerson.attributes('disabled')).toBeDefined()
    expect(busyPerson.attributes('aria-label')).toContain('ocupado hasta 10:30')

    const freePerson = people.find(row => row.attributes('data-appointment-professional') === '3')
    expect(freePerson.text()).toContain('Libre')
    expect(freePerson.attributes('disabled')).toBeUndefined()

    // Dos recursos libres se combinan sin que nadie proponga la pareja.
    await freePerson.trigger('click')
    await wrapper.find('[data-appointment-chair="4"]').trigger('click')
    await flushPromises()

    const combination = wrapper.find('[data-appointment-combination]')
    expect(combination.text()).toContain('Dr. Pinto')
    expect(combination.text()).toContain('Sillón 4')
    expect(combination.text()).toContain('10:00–10:30')

    const summary = wrapper.find('[data-appointment-summary]')
    expect(summary.text()).toContain('Dr. Pinto')
    expect(summary.text()).toContain('Sillón 4')
    expect(summary.text()).toContain('10:00–10:30')

    wrapper.unmount()
  })

  it('offers the pairs the day already used as a single click', async () => {
    apiProfiles.professionals = [
      { id: 2, name: 'Dra. Ruiz', specialty: 'Odontología', role: 'odontologo' },
      { id: 3, name: 'Dr. Pinto', specialty: 'Implantología', role: 'implantologo' }
    ]
    apiProfiles.chairs = [{ id: 3, name: 'Sillón 3', code: 'S3' }]
    // La agenda del día usó Pinto en el Sillón 3 a las 08:00.
    apiAppointments.value = [
      {
        id: 11,
        scheduled_at: '2026-05-13T08:00:00',
        ends_at: '2026-05-13T08:30:00',
        duration_minutes: 30,
        status: 'scheduled',
        user_id: 3,
        dental_chair_id: 3,
        patient: { id: 7, first_name: 'Ana', last_name: 'Pérez' }
      }
    ]

    const wrapper = await mountModal()
    await schedule(wrapper, '2026-05-13', '10:00')

    const suggestions = wrapper.findAll('[data-appointment-suggestion]')
    expect(suggestions).toHaveLength(1)
    expect(suggestions[0].text()).toContain('Dr. Pinto')
    expect(suggestions[0].text()).toContain('Sillón 3')
    expect(suggestions[0].text()).toContain('Libre')

    await suggestions[0].trigger('click')
    await flushPromises()

    expect(wrapper.find('[data-appointment-combination]').text()).toContain('Dr. Pinto')
    expect(wrapper.find('[data-appointment-combination]').text()).toContain('Sillón 3')

    wrapper.unmount()
    apiAppointments.value = [
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

  it('declares an hour outside the nominal window instead of hiding it', async () => {
    const wrapper = await mountModal({ initialDate: '2026-05-13T06:00' })
    await flushPromises()

    const marked = wrapper.find(
      '[data-appointment-form] [data-appointment-slot][aria-pressed="true"]'
    )
    expect(marked.exists()).toBe(true)
    expect(marked.attributes('data-appointment-slot')).toBe('06:00')

    wrapper.unmount()
  })

  it('replaces the native time input with a 15-minute grid that declares its occupied cells', async () => {
    apiProfiles.professionals = [
      { id: 2, name: 'Dra. Ruiz', specialty: 'Odontología', role: 'odontologo' }
    ]
    apiProfiles.chairs = [{ id: 3, name: 'Sillón 3', code: 'S3' }]

    const wrapper = await mountModal()

    // Sin fecha ni duración no hay rejilla: se dice qué falta.
    expect(wrapper.find('[data-appointment-hour-grid]').exists()).toBe(false)
    expect(wrapper.find('[data-appointment-hour-hint]').text()).toContain('Elige la fecha')
    expect(wrapper.find('[data-appointment-form] input[type="time"]').exists()).toBe(false)

    await wrapper.find('[data-appointment-form] input[type="date"]').setValue('2026-05-13')
    await flushPromises()

    const grid = wrapper.find('[data-appointment-hour-grid]')
    expect(grid.exists()).toBe(true)
    expect(wrapper.findAll('[data-appointment-hour-block]')).toHaveLength(4)

    // Una hora elegida abre la ventana: recién entonces la agenda puede decir
    // quién está libre, y el profesional elegido convierte las casillas de su
    // cita en ocupadas. Las 10:30 viven en el bloque «10–13».
    await wrapper.find('[data-appointment-hour-block="midday"]').trigger('click')
    await flushPromises()
    await wrapper.find('[data-appointment-slot="10:30"]').trigger('click')
    await flushPromises()
    await wrapper.find('[data-appointment-professional="2"]').trigger('click')
    await flushPromises()

    const blocked = wrapper.find('[data-appointment-slot="10:00"]')
    expect(blocked.attributes('disabled')).toBeDefined()
    expect(blocked.attributes('aria-label')).toBe('10:00, ocupado hasta 10:30')

    const free = wrapper.find('[data-appointment-slot="10:30"]')
    expect(free.attributes('disabled')).toBeUndefined()
    expect(free.attributes('aria-label')).toBe('10:30')
    expect(free.attributes('aria-pressed')).toBe('true')
    expect(wrapper.find('[data-appointment-end]').text()).toContain('Termina 11:00')

    wrapper.unmount()
  })

  it('does not invent a specialty for the frequent types when nobody is chosen', async () => {
    apiProfiles.professionals = [
      { id: 9, name: 'Dra. Ferrer', specialty: 'orthodontics', role: 'odontologo' }
    ]
    apiAppointmentTypes.value = [
      {
        id: 3,
        name: 'Consulta General',
        default_duration_minutes: 30,
        specialty: { id: 1, code: 'general', name: 'General' }
      },
      {
        id: 4,
        name: 'Ortodoncia',
        default_duration_minutes: 30,
        specialty: { id: 2, code: 'ortodoncia', name: 'Ortodoncia' }
      }
    ]

    const wrapper = await mountModal()

    expect(wrapper.find('[data-appointment-type-specialty]').exists()).toBe(false)

    await wrapper.find('[data-appointment-type-more]').trigger('click')
    await flushPromises()
    expect(wrapper.findAll('[data-appointment-type-option]')).toHaveLength(2)

    wrapper.unmount()
    apiAppointmentTypes.value = null
  })

  it('pages the team lists instead of clipping them', async () => {
    apiProfiles.professionals = [
      { id: 2, name: 'Dra. Ruiz', specialty: 'Odontología', role: 'odontologo' },
      { id: 3, name: 'Dr. Pinto', specialty: 'Implantología', role: 'implantologo' },
      { id: 4, name: 'Dra. Ferrer', specialty: 'Ortodoncia', role: 'odontologo' },
      { id: 5, name: 'Dr. Rojas', specialty: 'Endodoncia', role: 'odontologo' },
      { id: 6, name: 'Dra. Paz', specialty: 'Rehabilitación', role: 'odontologo' }
    ]
    apiProfiles.chairs = [
      { id: 4, name: 'Ambiente 1', code: 'AMB-001' },
      { id: 5, name: 'Ambiente 2', code: 'AMB-002' }
    ]

    const wrapper = await mountModal()
    await schedule(wrapper, '2026-05-13', '09:00')

    // Cuatro filas por página: nada se corta a la mitad, el resto se pagina.
    expect(wrapper.findAll('[data-appointment-professional]')).toHaveLength(4)
    expect(wrapper.find('[data-appointment-professionals-range]').text()).toBe('1–4/5')
    // Dos sillones caben enteros: no hay paginador.
    expect(wrapper.findAll('[data-appointment-chair]')).toHaveLength(2)
    expect(wrapper.find('[data-appointment-chairs-pager]').exists()).toBe(false)

    await wrapper.find('[data-appointment-professionals-next]').trigger('click')
    await flushPromises()

    expect(wrapper.findAll('[data-appointment-professional]')).toHaveLength(1)
    expect(wrapper.find('[data-appointment-professionals-range]').text()).toBe('5–5/5')

    await wrapper.find('[data-appointment-professionals-prev]').trigger('click')
    await flushPromises()
    expect(wrapper.find('[data-appointment-professionals-range]').text()).toBe('1–4/5')

    wrapper.unmount()
  })

  it('wraps a long professional name instead of cutting it with an ellipsis', async () => {
    apiProfiles.professionals = [
      {
        id: 2,
        name: 'Dra. Carmen Beatriz Salazar Villanueva',
        specialty: 'Odontología',
        role: 'odontologo'
      }
    ]
    apiProfiles.chairs = [{ id: 4, name: 'Ambiente 1', code: 'AMB-001' }]

    const wrapper = await mountModal()
    await schedule(wrapper, '2026-05-13', '09:00')

    const row = wrapper.find('[data-appointment-professional="2"]')
    expect(row.exists()).toBe(true)
    expect(row.text()).toContain('Dra. Carmen Beatriz Salazar Villanueva')
    expect(row.html()).not.toContain('truncate')
    expect(row.html()).toContain('break-words')

    wrapper.unmount()
  })

  it('moves across the hour grid with the arrow keys, skipping occupied cells', async () => {
    apiProfiles.professionals = [
      { id: 2, name: 'Dra. Ruiz', specialty: 'Odontología', role: 'odontologo' }
    ]

    const wrapper = await mountModal({}, { attachTo: document.body })
    await wrapper.find('[data-appointment-form] input[type="date"]').setValue('2026-05-13')
    await flushPromises()
    // Las 09:30 abren una ventana en la que la Dra. Ruiz todavía está libre;
    // su cita de 10:00 es la que la rejilla marca ocupada.
    await wrapper.find('[data-appointment-slot="09:30"]').trigger('click')
    await flushPromises()
    await wrapper.find('[data-appointment-professional="2"]').trigger('click')
    await flushPromises()

    const origin = wrapper.find('[data-appointment-slot="09:30"]')
    await origin.trigger('keydown', { key: 'ArrowRight' })
    await flushPromises()

    // 09:45, 10:00 y 10:15 chocan con la cita de 10:00–10:30; la flecha
    // aterriza en la primera casilla libre, no en una muerta.
    expect(document.activeElement?.getAttribute('data-appointment-slot')).toBe('10:30')

    document.activeElement.dispatchEvent(
      new KeyboardEvent('keydown', { key: 'ArrowLeft', bubbles: true })
    )
    await flushPromises()

    expect(document.activeElement?.getAttribute('data-appointment-slot')).toBe('09:30')

    wrapper.unmount()
  })

  it('never claims «Libre» when the day agenda could not be read', async () => {
    apiAvailability.fail = true

    const wrapper = await mountModal()
    await schedule(wrapper)

    expect(wrapper.find('[data-appointment-team-door]').exists()).toBe(false)
    expect(wrapper.text()).not.toContain('Libre')
    expect(wrapper.text()).toContain('No se pudo leer la agenda del día')
    expect(wrapper.find('[data-appointment-manual-pick]').exists()).toBe(true)
    // La rejilla sigue ofreciendo horas, pero ninguna se declara ocupada.
    expect(wrapper.findAll('[data-appointment-slot][disabled]')).toHaveLength(0)
    expect(wrapper.find('[data-appointment-hour-note]').text()).toContain(
      'No se pudo leer la agenda del día'
    )

    wrapper.unmount()
  })

  it('puts the types of the chosen professional specialty first and keeps the rest grouped', async () => {
    apiProfiles.professionals = [
      { id: 9, name: 'Dra. Ferrer', specialty: 'orthodontics', role: 'odontologo' }
    ]
    apiAppointmentTypes.value = [
      {
        id: 3,
        name: 'Consulta General',
        default_duration_minutes: 30,
        specialty: { id: 1, code: 'general', name: 'General' }
      },
      {
        id: 4,
        name: 'Ortodoncia',
        default_duration_minutes: 30,
        specialty: { id: 2, code: 'ortodoncia', name: 'Ortodoncia' }
      },
      { id: 5, name: 'Sin catálogo', default_duration_minutes: 15, specialty: null }
    ]

    const wrapper = await mountModal()
    await schedule(wrapper, '2026-05-13', '09:00')

    await wrapper.find('[data-appointment-professional="9"]').trigger('click')
    await flushPromises()

    // La especialidad del profesional vive en el rótulo del bloque —con su
    // conteo—, no repetida en cada chip.
    expect(wrapper.find('[data-appointment-type-specialty]').text()).toBe('Ortodoncia · 1 tipo')

    const chips = wrapper.findAll('[data-appointment-type-chip]')
    expect(chips.map(chip => chip.attributes('data-appointment-type-chip'))).toEqual(['4'])
    expect(wrapper.findAll('[data-appointment-chip-specialty]')).toHaveLength(0)

    // Sin profesional, el rótulo no inventa especialidad.
    await wrapper.find('[data-appointment-professional="9"]').trigger('click')
    await wrapper.find('[data-appointment-type-more]').trigger('click')
    await flushPromises()

    // «Otros tipos» expande el catálogo en el propio flujo, agrupado y con
    // «Sin especialidad» al final; sin menú flotante.
    const catalog = wrapper.find('[data-appointment-type-catalog]')
    expect(catalog.exists()).toBe(true)
    expect(wrapper.find('[data-appointment-section="type"] .select-container').exists()).toBe(false)
    const groups = wrapper.findAll('[data-appointment-type-group]')
    expect(groups.map(group => group.attributes('data-appointment-type-group'))).toEqual([
      'General',
      'Ortodoncia',
      'Sin especialidad'
    ])
    expect(wrapper.findAll('[data-appointment-type-option]')).toHaveLength(3)

    // Elegir desde el catálogo deja el tipo marcado en su propia fila.
    await wrapper.find('[data-appointment-type-option="5"]').trigger('click')
    await flushPromises()
    expect(wrapper.find('[data-appointment-type-option="5"]').attributes('aria-pressed')).toBe(
      'true'
    )

    wrapper.unmount()
    apiAppointmentTypes.value = null
  })

  it('offers the lifecycle status only when editing an appointment', async () => {
    const create = await mountModal()
    expect(create.text()).not.toContain('Estado de la cita')
    create.unmount()

    const edit = await mountModal({
      appointment: {
        id: 9,
        patient_id: 7,
        user_id: 2,
        scheduled_at: '2026-05-13T10:00:00',
        duration_minutes: 30,
        appointment_type_id: 3,
        dental_chair_id: 3,
        status: 'scheduled'
      }
    })

    expect(edit.text()).toContain('Estado de la cita')
    edit.unmount()
  })
})

// Una sola confirmación por cita creada. El modal no confirma, el eco de
// difusión de la propia mesa no confirma: la agenda pinta la línea del alta con
// su acción, salta al día creado y deja el chip marcado.
describe('alta de cita: una sola confirmación y el día creado', () => {
  const createdAppointment = {
    id: 4242,
    scheduled_at: '2026-12-03T09:00:00',
    ends_at: '2026-12-03T09:30:00',
    duration_minutes: 30,
    status: 'scheduled',
    notes: null,
    patient: { id: 7, first_name: 'Ana', last_name: 'Pérez' },
    user: { id: 3, name: 'Dr. Pinto' },
    dental_chair: { id: 4, name: 'Sillón 4' },
    appointment_type: { id: 4, name: 'Ortodoncia' }
  }

  const mountPage = async () => {
    const wrapper = mount(Page, {
      attachTo: document.body,
      // El diálogo se monta dentro del árbol de la prueba: la petición y la
      // confirmación se miden sobre la misma página.
      global: { plugins: [router, uiComponents], stubs: { teleport: true } }
    })
    await flushPromises()
    return wrapper
  }

  const openAndFill = async (wrapper, date = '2026-12-03', time = '09:00') => {
    await wrapper.find('[data-calendar-action="new-appointment"]').trigger('click')
    await flushPromises()

    const search = wrapper.find('#appointment-patient-search')
    await search.setValue('ana')
    await new Promise(resolve => setTimeout(resolve, 350))
    await flushPromises()
    await wrapper.findAll('[role="option"]')[0].trigger('click')
    await flushPromises()

    await wrapper.find('[data-appointment-form] input[type="date"]').setValue(date)
    await flushPromises()
    await wrapper.find(`[data-appointment-form] [data-appointment-slot="${time}"]`).trigger('click')
    await flushPromises()

    await wrapper.find('[data-appointment-type-chip="4"]').trigger('click')
    await wrapper.find('[data-appointment-professional="3"]').trigger('click')
    await wrapper.find('[data-appointment-chair="4"]').trigger('click')
    await flushPromises()
  }

  beforeEach(() => {
    globalToasts.value.splice(0, globalToasts.value.length)
    echoListeners.handlers = {}
    apiPatients.value = [{ id: 7, name: 'Ana Pérez', dni: '45872310', age: 34 }]
    apiProfiles.professionals = [
      { id: 2, name: 'Dra. Ruiz', specialty: 'Odontología', role: 'odontologo' },
      { id: 3, name: 'Dr. Pinto', specialty: 'Implantología', role: 'implantologo' }
    ]
    apiProfiles.chairs = [{ id: 4, name: 'Sillón 4', code: 'S4' }]
    apiAppointmentTypes.value = [
      {
        id: 4,
        name: 'Ortodoncia',
        default_duration_minutes: 30,
        specialty: { id: 2, code: 'ortodoncia', name: 'Ortodoncia' }
      }
    ]
    apiAppointments.value = []
    postMock.mockResolvedValueOnce({ data: createdAppointment })
  })

  it('leaves exactly one confirmation, jumps to the created day and marks its chip', async () => {
    const wrapper = await mountPage()
    await openAndFill(wrapper)

    const toastCountBefore = globalToasts.value.length

    // La lectura del día es la que trae la cita recién escrita: el alta no
    // inventa el chip, lo pide a la agenda igual que cualquier otra.
    apiAppointments.value = [createdAppointment]
    await wrapper.find('[data-appointment-form]').trigger('submit')
    await flushPromises()
    await flushPromises()

    // El modal ya no confirma por su cuenta.
    expect(globalToasts.value.length).toBe(toastCountBefore)

    const confirmations = wrapper.findAll('[data-appointment-confirmation]')
    expect(confirmations).toHaveLength(1)
    expect(confirmations[0].attributes('data-appointment-confirmation-id')).toBe('4242')
    expect(confirmations[0].text()).toContain('Cita agendada')
    expect(confirmations[0].text()).toContain('Ana Pérez')
    expect(confirmations[0].find('[data-appointment-confirmation-action]').text()).toBe(
      'Ver en la agenda'
    )

    // La agenda salta al día de la cita creada y el chip queda marcado.
    expect(wrapper.text()).toContain('dic')
    const created = wrapper.find('[data-appointment-created]')
    expect(created.exists()).toBe(true)
    expect(created.attributes('data-calendar-appointment')).toBe('4242')

    // El eco de la difusión por la propia creación no vuelve a avisar.
    const createdHandlers = echoListeners.handlers['appointment.created'] || []
    expect(createdHandlers.length).toBeGreaterThan(0)
    for (const handler of createdHandlers) {
      await handler({ appointment: { id: 4242 } })
    }
    await flushPromises()
    expect(globalToasts.value.length).toBe(toastCountBefore)

    // La cita de otra mesa sí avisa, una sola vez.
    for (const handler of createdHandlers) {
      await handler({ appointment: { id: 9999 } })
    }
    await flushPromises()
    expect(globalToasts.value.length).toBe(toastCountBefore + 1)
    expect(globalToasts.value[globalToasts.value.length - 1].message).toBe('Nueva cita agregada')

    wrapper.unmount()
  })
})
