import { describe, it, expect, vi, beforeEach } from 'vitest'
import { flushPromises } from '@vue/test-utils'

// F5 (#316) — the laboratory desk composable: query building, paging, the
// phase door and the ready-case subscription. The API, Echo and the toast
// centre are mocked because this suite pins the contract, not their transports.
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

// The composable holds module-scope state (one singleton per app), so every
// test imports a fresh copy instead of inheriting the previous filters.
const freshComposable = async () => {
  vi.resetModules()
  const module = await import('../../../resources/js/composables/useLabCases.js')
  return module.useLabCases()
}

const CASE = {
  id: 11,
  patient_id: 3,
  professional_id: 7,
  technician_id: 9,
  dental_piece_id: 4,
  work: 'Corona metal-cerámica',
  material: 'Zirconio',
  sent_at: '2026-10-20',
  promised_at: '2026-10-24',
  status: 'in_laboratory',
  notes: null,
  delivered_at: null,
  placed_at: null,
  patient: { id: 3, first_name: 'Ana', last_name: 'Torres' },
  professional: { id: 7, name: 'Dr. Luis Rojas' },
  technician: { id: 9, name: 'Tec. Marco Díaz' },
  dental_piece: { id: 4, fdi_number: '16' }
}

const pagePayload = () => ({
  data: [CASE],
  meta: { current_page: 1, last_page: 2, per_page: 15, total: 16 }
})

beforeEach(() => {
  api.get.mockReset()
  api.post.mockReset()
  toastMock.success.mockReset()
  toastMock.error.mockReset()
  toastMock.info.mockReset()
  echoMock.echo.leave.mockReset()
  echoMock.channelStub.listen.mockReset()
  echoMock.channelStub.listen.mockImplementation(() => echoMock.channelStub)
  echoMock.privateChannel.mockReset()
  echoMock.privateChannel.mockImplementation(() => echoMock.channelStub)
})

describe('useLabCases listing', () => {
  it('loads the first page without filters', async () => {
    api.get.mockResolvedValue(pagePayload())

    const { cases, meta, load } = await freshComposable()
    await load()

    expect(api.get).toHaveBeenCalledWith('/api/lab-cases?page=1')
    expect(cases.value).toHaveLength(1)
    expect(meta.value).toEqual({ current_page: 1, last_page: 2, per_page: 15, total: 16 })
  })

  it('builds the query from the non-empty filters', async () => {
    api.get.mockResolvedValue(pagePayload())

    const { setFilter } = await freshComposable()
    await setFilter({ status: 'delivered', technician_id: 'me', overdue: true })

    expect(api.get).toHaveBeenCalledWith(
      '/api/lab-cases?status=delivered&technician_id=me&overdue=1&page=1'
    )
  })

  it('resets the page when a filter changes and pages without losing it', async () => {
    api.get.mockResolvedValue(pagePayload())

    const { filters, setFilter, goToPage } = await freshComposable()

    await goToPage(3)
    expect(filters.value.page).toBe(3)
    expect(api.get).toHaveBeenLastCalledWith('/api/lab-cases?page=3')

    api.get.mockClear()
    await setFilter({ technician_id: '9' })

    expect(filters.value.page).toBe(1)
    expect(api.get).toHaveBeenCalledWith('/api/lab-cases?technician_id=9&page=1')

    api.get.mockClear()
    await goToPage(2)

    expect(filters.value.page).toBe(2)
    expect(api.get).toHaveBeenCalledWith('/api/lab-cases?technician_id=9&page=2')
  })

  it('keeps the previous list when a refresh fails', async () => {
    api.get.mockResolvedValue(pagePayload())

    const { cases, error, load } = await freshComposable()
    await load()
    const mounted = cases.value

    api.get.mockRejectedValueOnce({ response: { data: { message: 'Sin conexión' } } })
    await load()

    expect(cases.value).toBe(mounted)
    expect(error.value).toBe('Sin conexión')
  })
})

describe('useLabCases detail and phase', () => {
  it('loads one case through its own endpoint', async () => {
    api.get.mockResolvedValue({ data: CASE })

    const { loadOne } = await freshComposable()
    const fresh = await loadOne(11)

    expect(api.get).toHaveBeenCalledWith('/api/lab-cases/11')
    expect(fresh).toEqual(CASE)
  })

  it('posts the phase to its door, toasts the API message and reloads the list', async () => {
    api.post.mockResolvedValue({
      data: { ...CASE, status: 'placed' },
      meta: { message: 'Fase del caso de laboratorio actualizada exitosamente' }
    })
    api.get.mockResolvedValue(pagePayload())

    const { transition } = await freshComposable()
    const fresh = await transition(CASE, 'placed')

    expect(api.post).toHaveBeenCalledWith('/api/lab-cases/11/phase', { phase: 'placed' })
    expect(toastMock.success).toHaveBeenCalledWith(
      'Fase del caso de laboratorio actualizada exitosamente'
    )
    expect(api.get).toHaveBeenCalledWith('/api/lab-cases?page=1')
    expect(fresh.status).toBe('placed')
  })

  it('surfaces the service sentence of a rejected move and rethrows it', async () => {
    const rejected = {
      response: {
        data: {
          message: 'Fase no permitida',
          errors: { phase: ['El caso ya está en la fase «placed»; no puede volver a avanzar.'] }
        }
      },
      status: 422
    }
    api.post.mockRejectedValue(rejected)

    const { transition } = await freshComposable()

    await expect(transition(CASE, 'placed')).rejects.toBe(rejected)

    expect(toastMock.error).toHaveBeenCalledWith(
      'El caso ya está en la fase «placed»; no puede volver a avanzar.'
    )
    expect(api.get).not.toHaveBeenCalled()
  })
})

describe('useLabCases realtime', () => {
  it('subscribes to the private lab-cases channel and refreshes on caso_laboratorio_listo', async () => {
    api.get.mockResolvedValue(pagePayload())

    const { subscribe, unsubscribe } = await freshComposable()
    subscribe()

    expect(echoMock.privateChannel).toHaveBeenCalledWith('lab-cases')
    expect(echoMock.channelStub.listen).toHaveBeenCalledWith(
      '.caso_laboratorio_listo',
      expect.any(Function)
    )

    const handler = echoMock.channelStub.listen.mock.calls[0][1]
    handler({ case: { work: 'Corona metal-cerámica' } })
    await flushPromises()

    expect(toastMock.info).toHaveBeenCalledWith('Caso de laboratorio listo: Corona metal-cerámica')
    expect(api.get).toHaveBeenCalledWith('/api/lab-cases?page=1')

    unsubscribe()
    expect(echoMock.echo.leave).toHaveBeenCalledWith('private-lab-cases')
  })

  it('subscribes only once even if the page mounts twice', async () => {
    const { subscribe } = await freshComposable()

    subscribe()
    subscribe()

    expect(echoMock.privateChannel).toHaveBeenCalledTimes(1)
  })
})
