import { describe, it, expect, vi, beforeEach } from 'vitest'
import { flushPromises } from '@vue/test-utils'

// T2 — la cola de Mi día es una vista de la agenda: la difusión de una cita la
// vuelve a leer, con el payload operativo (sin paciente) y sin un toast por
// cada evento. Este archivo fija ese contrato; `myDay.test.js` conserva el
// mapa de los tres buckets y las puertas de custodia.
const api = vi.hoisted(() => ({ get: vi.fn(), post: vi.fn() }))

const echoMock = vi.hoisted(() => {
  const channelStub = { listen: vi.fn() }
  channelStub.listen.mockImplementation(() => channelStub)
  return {
    channelStub,
    privateChannel: vi.fn(() => channelStub),
    echo: { leave: vi.fn() }
  }
})

const toastMock = vi.hoisted(() => ({
  success: vi.fn(),
  error: vi.fn(),
  info: vi.fn()
}))

vi.mock('../../../resources/js/composables/useApi', () => ({
  useApi: () => ({ get: api.get, post: api.post })
}))

vi.mock('../../../resources/js/composables/useEcho', () => ({
  useEcho: () => ({ echo: echoMock.echo, privateChannel: echoMock.privateChannel })
}))

vi.mock('../../../resources/js/composables/useToast', () => ({
  useToast: () => toastMock
}))

vi.mock('../../../resources/js/composables/usePermissions', () => ({
  usePermissions: () => ({
    can: {
      viewMyDay: { value: true }
    }
  })
}))

import { useMyDay } from '../../../resources/js/composables/useMyDay.js'

// El payload que el backend difunde desde T1: id, profesional, estado, hora y
// sello. No trae paciente y la cola no lo pide.
const appointmentEvent = (id, userId = 7) => ({
  appointment: {
    id,
    user_id: userId,
    status: 'arrived',
    scheduled_at: '2026-10-21T09:30:00',
    updated_at: '2026-10-21T09:31:00'
  }
})

const handlerFor = eventName =>
  echoMock.channelStub.listen.mock.calls.find(([name]) => name === eventName)?.[1]

const myDayPayload = () => ({
  data: { waiting: [], attending: [], upcoming: [], ready_lab_cases: [] },
  meta: { counts: { waiting: 0, attending: 0, upcoming: 0, ready_lab_cases: 0 } }
})

const subscribedChannels = () => echoMock.privateChannel.mock.calls.map(([name]) => name)

beforeEach(() => {
  api.get.mockReset()
  api.post.mockReset()
  toastMock.success.mockReset()
  toastMock.error.mockReset()
  toastMock.info.mockReset()
  echoMock.echo.leave.mockReset()
  echoMock.privateChannel.mockReset()
  echoMock.channelStub.listen.mockReset()
  echoMock.channelStub.listen.mockImplementation(() => echoMock.channelStub)
  echoMock.privateChannel.mockImplementation(() => echoMock.channelStub)
  api.get.mockResolvedValue(myDayPayload())
})

describe('Mi día escucha la difusión de citas (T2)', () => {
  it('abre el canal privado appointments junto al del profesional', () => {
    const { subscribe, unsubscribe } = useMyDay()
    subscribe(7)

    expect(subscribedChannels()).toEqual(['user.7', 'appointments'])

    unsubscribe(7)
  })

  it('escucha los cinco eventos de la cita y refresca la cola sin tostar', async () => {
    const { subscribe, unsubscribe } = useMyDay()
    subscribe(7)

    const eventNames = [
      '.appointment.created',
      '.appointment.updated',
      '.appointment.checked_in',
      '.appointment.completed',
      '.appointment.deleted'
    ]

    for (const eventName of eventNames) {
      expect(handlerFor(eventName), `${eventName} debe tener manejador`).toBeTypeOf('function')
    }

    for (const eventName of eventNames) {
      api.get.mockClear()
      await handlerFor(eventName)(appointmentEvent(11))
      await flushPromises()

      expect(api.get, `${eventName} re-lee la cola`).toHaveBeenCalledWith('/api/my-day')
    }

    // Refrescar no habla: el aviso de la cita pertenece a su escritorio.
    expect(toastMock.info).not.toHaveBeenCalled()
    expect(toastMock.success).not.toHaveBeenCalled()
    expect(toastMock.error).not.toHaveBeenCalled()

    unsubscribe(7)
  })

  it('lee el sobre del borrado sin pedir datos del paciente', async () => {
    const { subscribe, unsubscribe } = useMyDay()
    subscribe(7)

    await handlerFor('.appointment.deleted')({ appointment_id: 11, user_id: 7 })
    await flushPromises()

    expect(api.get).toHaveBeenCalledWith('/api/my-day')
    expect(toastMock.info).not.toHaveBeenCalled()

    unsubscribe(7)
  })

  it('libera el nombre real del canal en el desmontaje', () => {
    const { subscribe, unsubscribe } = useMyDay()
    subscribe(7)
    unsubscribe(7)

    expect(echoMock.echo.leave).toHaveBeenCalledWith('private-user.7')
    expect(echoMock.echo.leave).toHaveBeenCalledWith('private-appointments')
  })

  it('conserva el aviso personal de paciente_listo', async () => {
    const { subscribe, unsubscribe } = useMyDay()
    subscribe(7)

    await handlerFor('.paciente_listo')({ patient: { full_name: 'Ana Torres' } })
    await flushPromises()

    expect(toastMock.info).toHaveBeenCalledWith('Paciente listo: Ana Torres')
    expect(api.get).toHaveBeenCalledWith('/api/my-day')

    unsubscribe(7)
  })

  it('no duplica la suscripción cuando el mismo profesional vuelve a montar', () => {
    const { subscribe, unsubscribe } = useMyDay()
    subscribe(7)
    subscribe(7)

    expect(subscribedChannels()).toEqual(['user.7', 'appointments'])

    unsubscribe(7)
  })
})
