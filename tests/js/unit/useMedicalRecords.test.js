import { beforeEach, describe, expect, it, vi } from 'vitest'

const { api } = vi.hoisted(() => ({
  api: {
    get: vi.fn(),
    post: vi.fn(),
    put: vi.fn(),
    delete: vi.fn(),
    normalizeError: vi.fn()
  }
}))

vi.mock('../../../resources/js/composables/useApi', () => ({ useApi: () => api }))

import { useMedicalRecords } from '../../../resources/js/composables/useMedicalRecords'

/**
 * The composable is the only seam between the Historias clínicas surfaces and
 * the API. These tests pin the endpoint each call reaches and the state each
 * response lands in. #292: a correction is an addendum appended to the row it
 * corrects; the original fields are never rewritten.
 */
describe('useMedicalRecords', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  const query = callIndex => {
    const url = new URL(api.get.mock.calls[callIndex][0], 'http://localhost')
    return { path: url.pathname, params: Object.fromEntries(url.searchParams) }
  }

  it('sends the index filters and keeps the pagination meta', async () => {
    api.get.mockResolvedValueOnce({
      data: [{ id: 1 }],
      meta: { total: 11, current_page: 2, per_page: 10, last_page: 2 }
    })

    const store = useMedicalRecords()
    await store.getRecords({ patient_id: 7, search: 'HC-2026', status: 'all', page: 2 })

    expect(query(0).path).toBe('/api/medical-records')
    expect(query(0).params).toEqual({
      patient_id: '7',
      search: 'HC-2026',
      status: 'all',
      page: '2'
    })
    expect(store.records.value).toEqual([{ id: 1 }])
    expect(store.meta.value).toEqual({
      total: 11,
      current_page: 2,
      per_page: 10,
      last_page: 2
    })
  })

  it('omits empty filters instead of sending an empty search', async () => {
    api.get.mockResolvedValueOnce({ data: [], meta: {} })

    await useMedicalRecords().getRecords({ patient_id: null, search: '   ', status: '' })

    expect(query(0).params).toEqual({})
  })

  it('stores the record detail it fetched', async () => {
    api.get.mockResolvedValueOnce({ data: { id: 5, record_number: 'HC-1' } })

    const store = useMedicalRecords()
    await store.getRecord(5)

    expect(query(0).path).toBe('/api/medical-records/5')
    expect(store.currentRecord.value).toEqual({ id: 5, record_number: 'HC-1' })
  })

  it('peeks a record without taking over the workspace selection', async () => {
    const store = useMedicalRecords()
    api.get.mockResolvedValueOnce({ data: { id: 12, diagnosis: 'Caries', addenda: [] } })
    await store.getRecord(12)
    api.get.mockResolvedValueOnce({ data: { id: 99, addenda: [{ id: 1 }] } })

    const peeked = await store.peekRecord(99)

    expect(peeked).toEqual({ id: 99, addenda: [{ id: 1 }] })
    expect(store.currentRecord.value.id).toBe(12)
  })

  it('asks for evolutions by record id, not by patient id', async () => {
    api.get.mockResolvedValueOnce({ data: [{ id: 9 }] })

    const store = useMedicalRecords()
    await store.getEvolutions(12)

    expect(query(0).path).toBe('/api/medical-records/12/evolutions')
    expect(store.evolutions.value).toEqual([{ id: 9 }])
  })

  it('creates an evolution on its record', async () => {
    api.post.mockResolvedValueOnce({ data: { id: 4, subjective: 'Dolor' } })

    const store = useMedicalRecords()
    await store.addEvolution(12, { subjective: 'Dolor' })

    expect(api.post).toHaveBeenCalledWith('/api/medical-records/12/evolutions', {
      subjective: 'Dolor'
    })
    expect(store.evolutions.value[0]).toEqual({ id: 4, subjective: 'Dolor' })
  })

  it('appends the addendum to the evolution it corrects and keeps the original', async () => {
    const store = useMedicalRecords()
    api.get.mockResolvedValueOnce({ data: [{ id: 4, subjective: 'Dolor', addenda: [] }] })
    await store.getEvolutions(12)
    api.post.mockResolvedValueOnce({
      data: { id: 9, reason: 'Error de digitación', content: 'La pieza correcta es la 36' }
    })

    const addendum = await store.addEvolutionAddendum(4, {
      reason: 'Error de digitación',
      content: 'La pieza correcta es la 36'
    })

    expect(api.post).toHaveBeenCalledWith('/api/medical-records/evolutions/4/addenda', {
      reason: 'Error de digitación',
      content: 'La pieza correcta es la 36'
    })
    expect(store.evolutions.value[0].subjective).toBe('Dolor')
    expect(store.evolutions.value[0].addenda).toEqual([addendum])
  })

  it('appends a record-level addendum to the open history without touching its fields', async () => {
    const store = useMedicalRecords()
    api.get.mockResolvedValueOnce({ data: { id: 12, diagnosis: 'Caries', addenda: [] } })
    await store.getRecord(12)
    api.post.mockResolvedValueOnce({ data: { id: 10, reason: 'Dato omitido' } })

    const addendum = await store.addRecordAddendum(12, {
      reason: 'Dato omitido',
      content: 'Antecedente de penicilina'
    })

    expect(api.post).toHaveBeenCalledWith('/api/medical-records/12/addenda', {
      reason: 'Dato omitido',
      content: 'Antecedente de penicilina'
    })
    expect(store.currentRecord.value.diagnosis).toBe('Caries')
    expect(store.currentRecord.value.addenda).toEqual([addendum])
  })

  it('loads the active specialty catalog the evolution form validates against', async () => {
    const payload = [{ id: 1, code: 'endodoncia', name: 'Endodoncia' }]
    api.get.mockResolvedValueOnce({ data: payload })

    const store = useMedicalRecords()
    await store.getSpecialties()

    expect(query(0).path).toBe('/api/specialties/active')
    expect(store.specialties.value).toEqual(payload)
  })

  it('stores the stats payload the panel renders', async () => {
    const payload = { total_evolutions: 3, total_attachments: 2, specialties_involved: [] }
    api.get.mockResolvedValueOnce({ data: payload })

    const store = useMedicalRecords()
    await store.getStats(7)

    expect(query(0).path).toBe('/api/medical-records/patient/7/stats')
    expect(store.stats.value).toEqual(payload)
  })

  it('lists every attachment unless a category is chosen', async () => {
    const store = useMedicalRecords()
    api.get.mockResolvedValue({ data: [] })

    await store.getAttachments(7)
    await store.getAttachments(7, 'radiografia')

    expect(query(0).params).toEqual({})
    expect(query(1).params).toEqual({ category: 'radiografia' })
  })

  it('uploads an attachment and puts it first in the gallery', async () => {
    const formData = new FormData()
    formData.append('patient_id', '7')
    api.post.mockResolvedValueOnce({ data: { id: 3, original_name: 'rx.jpg' } })

    const store = useMedicalRecords()
    await store.uploadAttachment(formData)

    expect(api.post).toHaveBeenCalledWith('/api/medical-records/attachments', formData, {
      headers: { 'Content-Type': 'multipart/form-data' }
    })
    expect(store.attachments.value[0]).toEqual({ id: 3, original_name: 'rx.jpg' })
  })

  it('keeps a per-surface error and rethrows it', async () => {
    api.get.mockRejectedValueOnce({ response: { data: { message: 'Sin conexión' } } })

    const store = useMedicalRecords()

    await expect(store.getStats(7)).rejects.toBeTruthy()
    expect(store.errors.stats).toBe('Sin conexión')
    expect(store.stats.value).toBeNull()
  })

  it('clears every list on reset', async () => {
    const store = useMedicalRecords()
    api.get.mockResolvedValueOnce({ data: [{ id: 1 }], meta: { total: 1 } })
    await store.getRecords({ patient_id: 7 })

    store.reset()

    expect(store.records.value).toEqual([])
    expect(store.meta.value).toBeNull()
    expect(store.evolutions.value).toEqual([])
    expect(store.attachments.value).toEqual([])
    expect(store.specialties.value).toEqual([])
    expect(store.stats.value).toBeNull()
  })
})
