import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { defineComponent } from 'vue'
import { createRouter, createMemoryHistory } from 'vue-router'

// T2 — privacidad de la difusión: el panel global no anuncia la cita de otro
// profesional a un escritorio clínico. Administración y recepción conservan el
// aviso porque su alcance es la sede entera. El payload que llega desde T1 es
// operativo (sin paciente) y el panel no depende de él.
const authState = vi.hoisted(() => ({ ref: null }))

const echoMock = vi.hoisted(() => {
  const channels = {}
  const makeChannel = name => {
    const channel = { name, listen: vi.fn(() => channel) }
    channels[name] = channel
    return channel
  }

  return { channels, privateChannel: vi.fn(name => makeChannel(name)), echo: { leave: vi.fn() } }
})

const notificationMock = vi.hoisted(() => ({ addNotification: vi.fn() }))
const toastMock = vi.hoisted(() => ({ error: vi.fn(), success: vi.fn(), info: vi.fn() }))

vi.mock('../../../resources/js/composables/useAuth', async () => {
  const { ref } = await import('vue')
  const user = ref(null)
  authState.ref = user
  return { useAuth: () => ({ user }) }
})

vi.mock('../../../resources/js/composables/useEcho', () => ({
  useEcho: () => ({
    echo: echoMock.echo,
    channel: vi.fn(),
    privateChannel: echoMock.privateChannel,
    connectionStatus: { value: 'connected' }
  })
}))

vi.mock('../../../resources/js/composables/useNotifications', () => ({
  useNotifications: () => ({ addNotification: notificationMock.addNotification })
}))

vi.mock('../../../resources/js/composables/useToast', () => ({
  useToast: () => toastMock
}))

import {
  useWebSocketNotifications,
  markLocalAppointmentEcho
} from '../../../resources/js/composables/useWebSocketNotifications'

const router = createRouter({
  history: createMemoryHistory(),
  routes: [{ path: '/:pathMatch(.*)*', component: { template: '<div />' } }]
})

const Probe = defineComponent({
  setup() {
    useWebSocketNotifications()
    return () => null
  }
})

const mountProbe = async () => {
  await router.push('/calendar')
  await router.isReady()

  const wrapper = mount(Probe, { global: { plugins: [router] } })
  await flushPromises()
  return wrapper
}

// El payload reducido del contrato: id, profesional, estado, hora y sello.
const eventFor = (id, userId) => ({
  appointment: {
    id,
    user_id: userId,
    status: 'scheduled',
    scheduled_at: '2026-12-03T09:00:00',
    updated_at: '2026-12-03T08:00:00'
  }
})

const emit = async (eventName, payload) => {
  const channel = echoMock.channels.appointments
  expect(channel, 'las pruebas necesitan el canal appointments').toBeTruthy()

  for (const [name, handler] of channel.listen.mock.calls) {
    if (name === `.${eventName}`) handler(payload)
  }
  await flushPromises()
}

const session = (role, id = 1) => ({
  id,
  name: 'Sesión',
  role,
  permissions: ['manage.appointment']
})

beforeEach(() => {
  notificationMock.addNotification.mockReset()
  toastMock.error.mockReset()
  echoMock.privateChannel.mockClear()
  echoMock.echo.leave.mockClear()
  Object.keys(echoMock.channels).forEach(name => delete echoMock.channels[name])
})

describe('el panel global no anuncia la cita de otro clínico (T2)', () => {
  it('silencia la cita ajena en un escritorio clínico', async () => {
    authState.ref.value = session('odontologo', 1)
    const wrapper = await mountProbe()

    await emit('appointment.created', eventFor(9001, 9))

    expect(notificationMock.addNotification).not.toHaveBeenCalled()
    wrapper.unmount()
  })

  it('anuncia la cita propia del clínico', async () => {
    authState.ref.value = session('odontologo', 1)
    const wrapper = await mountProbe()

    await emit('appointment.created', eventFor(9002, 1))

    expect(notificationMock.addNotification).toHaveBeenCalledTimes(1)
    expect(notificationMock.addNotification.mock.calls[0][0]).toContain('Cita creada')
    // La clave estable viaja con el aviso para que el almacén deduplique.
    expect(notificationMock.addNotification.mock.calls[0][2]?.key).toBe('appointment.created:9002')
    wrapper.unmount()
  })

  it('aplica la misma guarda al cambio y a la anulación', async () => {
    authState.ref.value = session('implantologo', 4)
    const wrapper = await mountProbe()

    await emit('appointment.updated', eventFor(9003, 9))
    expect(notificationMock.addNotification).not.toHaveBeenCalled()

    await emit('appointment.updated', eventFor(9004, 4))
    expect(notificationMock.addNotification).toHaveBeenCalledTimes(1)

    await emit('appointment.deleted', { appointment_id: 9005, user_id: 9 })
    expect(notificationMock.addNotification).toHaveBeenCalledTimes(1)

    wrapper.unmount()
  })

  it('conserva el aviso de la cita ajena para recepción y administración', async () => {
    authState.ref.value = session('recepcionista', 2)
    const wrapper = await mountProbe()

    await emit('appointment.created', eventFor(9006, 9))

    expect(notificationMock.addNotification).toHaveBeenCalledTimes(1)
    wrapper.unmount()

    authState.ref.value = session('administrador', 3)
    const adminWrapper = await mountProbe()

    await emit('appointment.updated', eventFor(9007, 9))

    expect(notificationMock.addNotification).toHaveBeenCalledTimes(2)
    adminWrapper.unmount()
  })

  it('no depende de datos del paciente en el payload', async () => {
    authState.ref.value = session('odontologo', 1)
    const wrapper = await mountProbe()

    await emit('appointment.updated', eventFor(9008, 1))

    const [, , options] = notificationMock.addNotification.mock.calls[0]
    expect(String(options?.title)).not.toContain('undefined')
    expect(String(notificationMock.addNotification.mock.calls[0][0])).not.toContain('undefined')
    wrapper.unmount()
  })

  it('sigue descartando el eco de la cita escrita por esta pestaña', async () => {
    authState.ref.value = session('odontologo', 1)
    const wrapper = await mountProbe()

    markLocalAppointmentEcho(9009)
    await emit('appointment.created', eventFor(9009, 1))

    expect(notificationMock.addNotification).not.toHaveBeenCalled()
    wrapper.unmount()
  })
})
