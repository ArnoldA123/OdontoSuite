import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { createRouter, createMemoryHistory } from 'vue-router'

// U2 regression coverage: the dashboard no longer lists appointments. The
// agenda lives in /calendar; the page keeps the New Appointment modal only
// because /appointments/new redirects here with `?openAppointmentModal=true`.
const { getMock, installPayload } = vi.hoisted(() => {
  const getMock = vi.fn()

  const installPayload = () => {
    getMock.mockImplementation(async url => {
      if (url === '/api/dashboard/stats') return { data: { appointments_today: 2 } }
      if (url === '/api/cash-register/current') {
        return {
          data: {
            session: { id: 7, status: 'open', opened_at: '2026-10-04T08:30:00' },
            summary: { opening_amount: 100, expected_amount: 280 }
          }
        }
      }
      return { data: [] }
    })
  }

  return { getMock, installPayload }
})

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
// El canal privado guarda sus manejadores: la prueba del guard de eco emite
// `.appointment.created` sin un Reverb vivo, igual que el smoke de la agenda.
const echoHandlers = vi.hoisted(() => ({ byEvent: {} }))
vi.mock('../../../resources/js/composables/useEcho', () => ({
  useEcho: () => ({
    echo: { leave: () => {} },
    channel: () => ({}),
    privateChannel: () => ({
      listen(name, handler) {
        const key = String(name).replace(/^\./, '')
        echoHandlers.byEvent[key] = [...(echoHandlers.byEvent[key] || []), handler]
        return this
      }
    }),
    connectionStatus: { value: 'connected' }
  })
}))
// El guard de eco se prueba contra el módulo real más abajo
// (`vi.importActual`); el mock solo cubre a los consumidores de la página.
const localEcho = new Set()
vi.mock('../../../resources/js/composables/useWebSocketNotifications', () => ({
  useWebSocketNotifications: () => ({}),
  markLocalAppointmentEcho: id => {
    if (id !== null && id !== undefined && id !== '') localEcho.add(String(id))
  },
  consumeLocalAppointmentEcho: id => localEcho.delete(String(id))
}))

import { defineComponent } from 'vue'
import uiComponents from '../../../resources/js/plugins/ui-components'
import Page from '../../../resources/js/modules/dashboard/DashboardPage.vue'
import NewAppointmentModal from '../../../resources/js/components/appointments/NewAppointmentModal.vue'
import { useNotifications } from '../../../resources/js/composables/useNotifications'

const signInAs = role => {
  localStorage.setItem('auth_token', 'test-token')
  localStorage.setItem('user', JSON.stringify({ id: 1, name: 'Ana Admin', role }))
}

const mountDashboard = async (path = '/dashboard') => {
  const router = createRouter({
    history: createMemoryHistory(),
    routes: [{ path: '/:pathMatch(.*)*', component: { template: '<div />' } }]
  })
  await router.push(path)
  await router.isReady()

  const wrapper = mount(Page, { global: { plugins: [router, uiComponents] } })
  await flushPromises()
  return { wrapper, router }
}

describe('dashboard retired agenda (U2)', () => {
  beforeEach(() => {
    signInAs('administrador')
    getMock.mockReset()
    installPayload()
  })

  it('renders no agenda list, row hook or appointment CTA', async () => {
    const { wrapper } = await mountDashboard()

    expect(wrapper.find('section[aria-label="Agenda de hoy"]').exists()).toBe(false)
    expect(wrapper.findAll('[data-appointment-row]')).toHaveLength(0)
    expect(wrapper.find('[data-state="empty-appointments"]').exists()).toBe(false)
    expect(wrapper.find('[data-state="error-appointments"]').exists()).toBe(false)

    // The header CTA went with its section: the agenda owns appointment
    // creation now.
    const newAppointmentCtas = wrapper
      .findAll('button')
      .filter(button => button.text().trim() === 'Nueva cita')
    expect(newAppointmentCtas).toHaveLength(0)

    wrapper.unmount()
  })

  it('keeps the New Appointment modal mounted for the /appointments/new redirect', async () => {
    const { wrapper } = await mountDashboard()

    const modal = wrapper.findComponent(NewAppointmentModal)
    expect(modal.exists()).toBe(true)
    expect(modal.props('modelValue')).toBe(false)

    wrapper.unmount()
  })

  it('opens the modal from the redirect query and clears it', async () => {
    const { wrapper, router } = await mountDashboard('/dashboard?openAppointmentModal=true')
    await flushPromises()

    expect(wrapper.findComponent(NewAppointmentModal).props('modelValue')).toBe(true)
    expect(router.currentRoute.value.query.openAppointmentModal).toBeUndefined()

    wrapper.unmount()
  })
})

// Ronda 2 del alta: el dashboard confirma la creación una sola vez y su acción
// abre la agenda del día creado. El modal ya no confirma por su cuenta.
describe('dashboard: una sola confirmación por cita creada', () => {
  it('confirms once, with an action that opens the created day', async () => {
    signInAs('administrador')
    getMock.mockReset()
    installPayload()

    const { wrapper, router } = await mountDashboard()
    const modal = wrapper.findComponent(NewAppointmentModal)

    modal.vm.$emit('created', {
      id: 4242,
      date: '2026-12-03',
      time: '09:00',
      summary: 'Ana Pérez · jue 3 dic, 09:00–09:30'
    })
    await flushPromises()

    const confirmations = wrapper.findAll('[data-appointment-confirmation]')
    expect(confirmations).toHaveLength(1)
    expect(confirmations[0].attributes('data-appointment-confirmation-id')).toBe('4242')
    expect(confirmations[0].text()).toContain('Cita agendada')
    expect(confirmations[0].text()).toContain('Ana Pérez')

    await confirmations[0].find('[data-appointment-confirmation-action]').trigger('click')
    await flushPromises()

    expect(router.currentRoute.value.path).toBe('/calendar')
    expect(router.currentRoute.value.query.date).toBe('2026-12-03')

    wrapper.unmount()
  })

  it('dismisses the confirmation without touching the dashboard', async () => {
    signInAs('administrador')
    getMock.mockReset()
    installPayload()

    const { wrapper, router } = await mountDashboard()
    wrapper
      .findComponent(NewAppointmentModal)
      .vm.$emit('created', { id: 4242, date: '2026-12-03', summary: 'Ana Pérez' })
    await flushPromises()

    await wrapper.find('[data-appointment-confirmation-dismiss]').trigger('click')
    await flushPromises()

    expect(wrapper.find('[data-appointment-confirmation]').exists()).toBe(false)
    expect(router.currentRoute.value.path).toBe('/dashboard')

    wrapper.unmount()
  })
})

// Ronda 2 del alta: el eco de la propia mesa no se anuncia dos veces.
//
// El guard vive en `useWebSocketNotifications` (registro de ids escritos por
// esta pestaña) y se prueba contra el módulo REAL: los mocks de este archivo
// solo cubren a los consumidores de la página, así que aquí se importa con
// `vi.importActual` y se emiten los eventos por el canal privado capturado.
describe('guard de eco de la difusión (shell)', () => {
  const mountProbe = async () => {
    const { useWebSocketNotifications } = await vi.importActual(
      '../../../resources/js/composables/useWebSocketNotifications'
    )
    const { markLocalAppointmentEcho } = await vi.importActual(
      '../../../resources/js/composables/useWebSocketNotifications'
    )

    const router = createRouter({
      history: createMemoryHistory(),
      routes: [{ path: '/:pathMatch(.*)*', component: { template: '<div />' } }]
    })
    await router.push('/calendar')
    await router.isReady()

    const Probe = defineComponent({
      setup() {
        useWebSocketNotifications()
        return () => null
      }
    })

    const wrapper = mount(Probe, { global: { plugins: [router] } })
    await flushPromises()

    return { wrapper, markLocalAppointmentEcho }
  }

  const emit = async (eventName, payload) => {
    for (const handler of echoHandlers.byEvent[eventName] || []) {
      handler(payload)
    }
    await flushPromises()
  }

  // T1: el sobre que difunde una cita es operativo; no trae paciente.
  const appointmentEvent = id => ({
    appointment: {
      id,
      user_id: 8,
      status: 'scheduled',
      scheduled_at: '2026-12-03T09:00:00+00:00',
      updated_at: '2026-12-03T08:00:00+00:00'
    }
  })

  beforeEach(() => {
    echoHandlers.byEvent = {}
    localStorage.setItem('auth_token', 'test-token')
    // El canal `appointments` exige `manage.appointment`, la misma llave que la
    // ruta: la sesión de recepción la tiene.
    localStorage.setItem(
      'user',
      JSON.stringify({
        id: 2,
        name: 'Recepcionista Test',
        role: 'recepcionista',
        permissions: ['manage.appointment']
      })
    )
    useNotifications().clearAll()
  })

  it('stays silent for the appointment this desk just created', async () => {
    const { wrapper, markLocalAppointmentEcho } = await mountProbe()
    const { notifications } = useNotifications()

    expect(echoHandlers.byEvent['appointment.created']?.length).toBeGreaterThan(0)

    const before = notifications.value.length
    markLocalAppointmentEcho(4242)

    await emit('appointment.created', appointmentEvent(4242))
    expect(notifications.value.length).toBe(before)

    wrapper.unmount()
  })

  it('still announces an appointment created by another desk', async () => {
    const { wrapper } = await mountProbe()
    const { notifications } = useNotifications()

    const before = notifications.value.length

    await emit('appointment.created', appointmentEvent(9999))

    expect(notifications.value.length).toBe(before + 1)
    // La copia es neutral: el sobre no trae paciente y no se inventa uno.
    expect(notifications.value[0].title).toBe('Nueva cita agregada')
    expect(notifications.value[0].message).toBe('Cita creada en la agenda.')
    expect(notifications.value[0].message).not.toContain('Paciente')
    expect(notifications.value[0].message).not.toContain('undefined')

    wrapper.unmount()
  })

  it('applies the same guard to the update echo', async () => {
    const { wrapper, markLocalAppointmentEcho } = await mountProbe()
    const { notifications } = useNotifications()

    const before = notifications.value.length
    markLocalAppointmentEcho(4242)

    await emit('appointment.updated', appointmentEvent(4242))
    expect(notifications.value.length).toBe(before)

    await emit('appointment.updated', appointmentEvent(7777))
    expect(notifications.value.length).toBe(before + 1)

    wrapper.unmount()
  })
})
