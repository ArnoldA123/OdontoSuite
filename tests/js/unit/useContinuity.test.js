import { describe, it, expect, vi, beforeEach } from 'vitest'

// F4 (#310) — the continuity desk composable: bucket/count mapping from
// `GET /api/continuity/queue`, the metric from `GET /api/patient-recalls/metrics`
// and the two writes. The API and the toast centre are mocked because this suite
// pins the contract, not their transports.
const api = vi.hoisted(() => ({ get: vi.fn(), post: vi.fn() }))

const toastMock = vi.hoisted(() => ({
  success: vi.fn(),
  error: vi.fn(),
  info: vi.fn()
}))

vi.mock('../../../resources/js/composables/useApi', () => ({
  useApi: () => ({ get: api.get, post: api.post })
}))

vi.mock('../../../resources/js/composables/useToast', () => ({
  useToast: () => toastMock
}))

import { useContinuity } from '../../../resources/js/composables/useContinuity.js'

const recall = (id, extra = {}) => ({
  id,
  patient_id: id + 100,
  interval_months: 6,
  reason: 'Control semestral',
  due_date: '2026-10-01',
  status: 'pending',
  notes: null,
  patient: { id: id + 100, first_name: 'Ana', last_name: 'Torres' },
  ...extra
})

const request = (id, extra = {}) => ({
  id,
  patient_id: id + 200,
  kind: 'next_session',
  status: 'pending',
  preferred_from: '2026-11-02T09:00:00',
  preferred_to: '2026-11-02T09:30:00',
  duration_minutes: 30,
  notes: null,
  appointment_type_id: 2,
  treatment_plan_id: null,
  clinical_encounter_id: 8,
  appointment_id: 14,
  patient: { id: id + 200, full_name: 'Luis Rojas' },
  appointment_type: { id: 2, name: 'Control' },
  treatment_plan: null,
  clinical_encounter: { id: 8 },
  ...extra
})

const queuePayload = (counts = { recalls: 1, requests: 1, total: 2 }) => ({
  data: {
    recalls: [recall(31)],
    requests: [request(41)],
    counts
  },
  meta: { date: '2026-10-23' }
})

const metricsPayload = () => ({
  data: {
    total: 10,
    pending: 2,
    scheduled: 3,
    completed: 4,
    cancelled: 1,
    overdue: 2,
    compliance_rate: 0.6667
  }
})

const mockGet = (queueResponse = queuePayload()) => {
  api.get.mockImplementation(async url =>
    url === '/api/patient-recalls/metrics' ? metricsPayload() : queueResponse
  )
}

beforeEach(() => {
  api.get.mockReset()
  api.post.mockReset()
  toastMock.success.mockReset()
  toastMock.error.mockReset()
  toastMock.info.mockReset()
})

describe('useContinuity load', () => {
  it('requests both endpoints and maps the buckets and counts', async () => {
    mockGet()

    const { queue, metrics, error, load } = useContinuity()
    await load()

    expect(api.get).toHaveBeenCalledWith('/api/continuity/queue')
    expect(api.get).toHaveBeenCalledWith('/api/patient-recalls/metrics')
    expect(queue.value.recalls).toHaveLength(1)
    expect(queue.value.requests).toHaveLength(1)
    expect(queue.value.counts).toEqual({ recalls: 1, requests: 1, total: 2 })
    expect(metrics.value.compliance_rate).toBe(0.6667)
    expect(error.value).toBeNull()
  })

  it('falls back to the array lengths when the payload carries no counts', async () => {
    mockGet({ data: { recalls: [recall(32), recall(33)], requests: [request(42)] } })

    const { queue, load } = useContinuity()
    await load()

    expect(queue.value.counts).toEqual({ recalls: 2, requests: 1, total: 3 })
  })

  it('keeps the previous queue mounted and records the message when the refresh fails', async () => {
    mockGet()

    const { queue, error, load } = useContinuity()
    await load()
    const before = queue.value

    api.get.mockRejectedValue({
      response: { data: { message: 'El servidor no respondió.' } }
    })
    await load()

    expect(error.value).toBe('El servidor no respondió.')
    expect(queue.value).toBe(before)
  })
})

describe('useContinuity schedule', () => {
  it('posts a continuity request to its schedule door and reloads the queue', async () => {
    mockGet()
    api.post.mockResolvedValue({ data: { request: request(41), appointment: { id: 90 } } })

    const { load, schedule } = useContinuity()
    await load()
    api.get.mockClear()

    const payload = {
      scheduled_at: '2026-11-02T09:00:00',
      user_id: 7,
      dental_chair_id: 1
    }
    const result = await schedule('request', request(41), payload)

    expect(api.post).toHaveBeenCalledWith('/api/continuity-requests/41/schedule', payload)
    expect(toastMock.success).toHaveBeenCalledWith('Cita de continuidad agendada.')
    expect(api.get).toHaveBeenCalledWith('/api/continuity/queue')
    expect(result.appointment.id).toBe(90)
  })

  it('posts an overdue recall to the recall schedule door with its own copy', async () => {
    mockGet()
    api.post.mockResolvedValue({ data: { recall: recall(31), appointment: { id: 91 } } })

    const { load, schedule } = useContinuity()
    await load()
    api.get.mockClear()

    const payload = {
      scheduled_at: '2026-10-01T09:00:00',
      user_id: 7,
      dental_chair_id: 1,
      appointment_type_id: 2,
      duration_minutes: 30
    }
    await schedule('recall', recall(31), payload)

    expect(api.post).toHaveBeenCalledWith('/api/patient-recalls/31/schedule', payload)
    expect(toastMock.success).toHaveBeenCalledWith('Cita de control agendada.')
    expect(api.get).toHaveBeenCalledWith('/api/patient-recalls/metrics')
  })

  it('toasts the server message and rethrows when the write is rejected', async () => {
    mockGet()

    const { load, schedule } = useContinuity()
    await load()
    api.get.mockClear()

    const conflict = {
      response: { data: { message: 'El profesional ya tiene una cita en ese horario.' } }
    }
    api.post.mockRejectedValue(conflict)

    await expect(schedule('request', request(41), {})).rejects.toBe(conflict)

    expect(toastMock.error).toHaveBeenCalledWith('El profesional ya tiene una cita en ese horario.')
    expect(toastMock.success).not.toHaveBeenCalled()
    expect(api.get).not.toHaveBeenCalled()
  })
})

describe('useContinuity dismissRequest', () => {
  it('posts the reason, toasts and reloads', async () => {
    mockGet()
    api.post.mockResolvedValue({ data: request(41, { status: 'dismissed' }) })

    const { load, dismissRequest } = useContinuity()
    await load()
    api.get.mockClear()

    await dismissRequest(request(41), 'El paciente ya agendó por su cuenta.')

    expect(api.post).toHaveBeenCalledWith('/api/continuity-requests/41/dismiss', {
      reason: 'El paciente ya agendó por su cuenta.'
    })
    expect(toastMock.success).toHaveBeenCalledWith('Solicitud descartada.')
    expect(api.get).toHaveBeenCalledWith('/api/continuity/queue')
  })

  it('rethrows a rejected dismissal so the caller can map the field error', async () => {
    mockGet()

    const { load, dismissRequest } = useContinuity()
    await load()
    api.get.mockClear()

    const invalid = {
      response: {
        data: {
          message: 'El motivo es obligatorio.',
          errors: { reason: ['El motivo es obligatorio.'] }
        }
      }
    }
    api.post.mockRejectedValue(invalid)

    await expect(dismissRequest(request(41), '')).rejects.toBe(invalid)

    expect(toastMock.error).toHaveBeenCalledWith('El motivo es obligatorio.')
    expect(api.get).not.toHaveBeenCalled()
  })
})
