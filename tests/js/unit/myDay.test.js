import { describe, it, expect, vi, beforeEach } from 'vitest'
import { flushPromises } from '@vue/test-utils'

// F1 (#281) — the desk composable: bucket mapping, the two custody actions and
// the `paciente_listo` subscription. The API, Echo and the toast centre are
// mocked because this suite pins the contract, not their transports.
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

import { useMyDay } from '../../../resources/js/composables/useMyDay.js'

const appointment = (id, status = 'arrived') => ({
  id,
  status,
  scheduled_at: '2026-10-21T09:30:00',
  patient: { id: id + 100, full_name: `Paciente ${id}` },
  appointment_type: { id: 2, name: 'Limpieza dental' },
  dental_chair: { id: 1, name: 'Sillón 1' }
})

// F5 (#316): the prosthetic cases the workshop returned. The API scopes them to
// the authenticated professional, so the composable only buckets and counts.
const labCase = (id = 31) => ({
  id,
  work: 'Corona metal-cerámica',
  material: 'Zirconia',
  status: 'delivered',
  sent_at: '2026-10-18',
  promised_at: '2026-10-25',
  delivered_at: '2026-10-21',
  patient: { id: 300, first_name: 'Ana', last_name: 'Torres' },
  technician: { id: 9, name: 'Téc. Mario Vega' },
  dental_piece: { id: 4, fdi_number: 26 }
})

const payload = () => ({
  data: {
    waiting: [appointment(11)],
    attending: [appointment(12, 'in_consultation')],
    upcoming: [appointment(13, 'confirmed'), appointment(14, 'scheduled')],
    ready_lab_cases: [labCase()]
  },
  meta: {
    date: '2026-10-21',
    counts: { waiting: 1, attending: 1, upcoming: 2, ready_lab_cases: 1 }
  }
})

beforeEach(() => {
  api.get.mockReset()
  api.post.mockReset()
  toastMock.success.mockReset()
  toastMock.error.mockReset()
  toastMock.info.mockReset()
  echoMock.echo.leave.mockReset()
  echoMock.channelStub.listen.mockClear()
  echoMock.privateChannel.mockReset()
  echoMock.privateChannel.mockImplementation(() => echoMock.channelStub)
})

describe('useMyDay maps the three buckets', () => {
  it('loads the queue and its counts from /api/my-day', async () => {
    api.get.mockResolvedValue(payload())

    const { queue, counts, load } = useMyDay()
    await load()

    expect(api.get).toHaveBeenCalledWith('/api/my-day')
    expect(queue.value.waiting).toHaveLength(1)
    expect(queue.value.attending).toHaveLength(1)
    expect(queue.value.upcoming).toHaveLength(2)
    expect(counts.value).toEqual({ waiting: 1, attending: 1, upcoming: 2, ready_lab_cases: 1 })
  })

  it('falls back to the bucket length when the payload carries no counts', async () => {
    api.get.mockResolvedValue({ data: { waiting: [appointment(21)] } })

    const { counts, load } = useMyDay()
    await load()

    expect(counts.value).toEqual({ waiting: 1, attending: 0, upcoming: 0, ready_lab_cases: 0 })
  })
})

describe('useMyDay maps the ready laboratory cases', () => {
  it('buckets the ready prosthetic cases and their count', async () => {
    api.get.mockResolvedValue(payload())

    const { queue, counts, load } = useMyDay()
    await load()

    expect(queue.value.ready_lab_cases).toHaveLength(1)
    expect(queue.value.ready_lab_cases[0]).toMatchObject({
      id: 31,
      work: 'Corona metal-cerámica',
      status: 'delivered',
      promised_at: '2026-10-25'
    })
    expect(counts.value.ready_lab_cases).toBe(1)
  })

  it('falls back to the bucket length when the counts omit the lab bucket', async () => {
    const fixture = payload()
    delete fixture.meta.counts.ready_lab_cases
    api.get.mockResolvedValue(fixture)

    const { counts, load } = useMyDay()
    await load()

    expect(counts.value.ready_lab_cases).toBe(1)
  })

  it('leaves the lab bucket empty and counted at zero without cases', async () => {
    api.get.mockResolvedValue({ data: { waiting: [appointment(21)] } })

    const { queue, counts, load } = useMyDay()
    await load()

    expect(queue.value.ready_lab_cases).toEqual([])
    expect(counts.value.ready_lab_cases).toBe(0)
  })
})

describe('useMyDay custody actions', () => {
  it('markArrival posts to /check-in, toasts and reloads the queue', async () => {
    api.get.mockResolvedValue(payload())
    api.post.mockResolvedValue({ data: appointment(11) })

    const { load, markArrival } = useMyDay()
    await load()
    await markArrival(appointment(11))

    expect(api.post).toHaveBeenCalledWith('/api/appointments/11/check-in', {})
    expect(toastMock.success).toHaveBeenCalledWith('Llegada registrada.')
    expect(api.get).toHaveBeenCalledTimes(2)
  })

  it('startAttention posts to /start-attention, toasts and reloads the queue', async () => {
    api.get.mockResolvedValue(payload())
    api.post.mockResolvedValue({ data: appointment(11, 'in_consultation') })

    const { load, startAttention } = useMyDay()
    await load()
    await startAttention(appointment(11))

    expect(api.post).toHaveBeenCalledWith('/api/appointments/11/start-attention', {})
    expect(toastMock.success).toHaveBeenCalledWith('Atención iniciada.')
    expect(api.get).toHaveBeenCalledTimes(2)
  })

  it('surfaces the 409 message, rethrows and keeps the queue mounted', async () => {
    api.get.mockResolvedValue(payload())

    const { queue, load, startAttention } = useMyDay()
    await load()
    const before = queue.value

    const conflict = {
      response: {
        data: {
          error: { error_code: 'attention_not_allowed', message: 'La cita no está en espera.' }
        }
      },
      status: 409
    }
    api.post.mockRejectedValue(conflict)

    await expect(startAttention(appointment(11))).rejects.toBe(conflict)

    expect(toastMock.error).toHaveBeenCalledWith('La cita no está en espera.')
    expect(queue.value).toBe(before)
    expect(api.get).toHaveBeenCalledTimes(1)
  })
})

describe('useMyDay realtime', () => {
  it('subscribes to the professional private channel and refreshes on paciente_listo', async () => {
    api.get.mockResolvedValue(payload())

    const { subscribe, unsubscribe } = useMyDay()
    subscribe(7)

    expect(echoMock.privateChannel).toHaveBeenCalledWith('user.7')
    expect(echoMock.channelStub.listen).toHaveBeenCalledWith(
      '.paciente_listo',
      expect.any(Function)
    )

    const handler = echoMock.channelStub.listen.mock.calls[0][1]
    handler({ patient: { full_name: 'Ana Torres' } })
    await flushPromises()

    expect(toastMock.info).toHaveBeenCalledWith('Paciente listo: Ana Torres')
    expect(api.get).toHaveBeenCalledWith('/api/my-day')

    unsubscribe(7)
    expect(echoMock.echo.leave).toHaveBeenCalledWith('private-user.7')
  })

  // F5 (#285): the ready prosthetic case travels on the same private channel
  // as `paciente_listo`. The desk announces it and refreshes, so the case the
  // API already publishes under `ready_lab_cases` shows up without a reload.
  it('listens for the ready laboratory case and refreshes the desk', async () => {
    api.get.mockResolvedValue(payload())

    const { subscribe } = useMyDay()
    subscribe(7)

    expect(echoMock.channelStub.listen).toHaveBeenCalledWith(
      '.caso_laboratorio_listo',
      expect.any(Function)
    )

    const handler = echoMock.channelStub.listen.mock.calls.find(
      ([eventName]) => eventName === '.caso_laboratorio_listo'
    )[1]
    api.get.mockClear()
    handler({ case: { work: 'Corona metal-cerámica' }, patient: { full_name: 'Ana Torres' } })
    await flushPromises()

    expect(toastMock.info).toHaveBeenCalledWith('Caso de laboratorio listo: Corona metal-cerámica')
    expect(api.get).toHaveBeenCalledWith('/api/my-day')
  })

  it('stays quiet when the realtime transport is unavailable', () => {
    echoMock.privateChannel.mockImplementation(() => null)

    const { subscribe } = useMyDay()

    expect(() => subscribe(9)).not.toThrow()
    expect(echoMock.privateChannel).toHaveBeenCalledWith('user.9')
  })
})
