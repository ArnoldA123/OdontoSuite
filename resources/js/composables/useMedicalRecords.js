import { reactive, ref, computed } from 'vue'
import { useApi } from './useApi'

/**
 * Data layer for the Historias clínicas module.
 *
 * Every read owns its own loading/error slot so one surface (evolutions, say)
 * can fail without blanking the rest of the history, and every write keeps the
 * lists in sync without a full refetch.
 */
const SURFACES = ['records', 'evolutions', 'attachments', 'stats']

const EMPTY_ERRORS = () => ({ ...Object.fromEntries(SURFACES.map(s => [s, null])), write: null })
const EMPTY_LOADING = () => Object.fromEntries(SURFACES.map(s => [s, false]))

const messageFrom = (err, fallback) => err?.response?.data?.message || fallback

const buildQuery = params => {
  const search = new URLSearchParams()
  const append = (key, value) => {
    if (value === null || value === undefined) return
    const text = String(value).trim()
    if (text === '') return
    search.set(key, text)
  }

  append('patient_id', params.patient_id)
  append('search', params.search)
  append('status', params.status)
  append('branch_id', params.branch_id)
  append('per_page', params.per_page)
  append('page', params.page)

  const query = search.toString()
  return query ? `?${query}` : ''
}

export function useMedicalRecords() {
  const { get, post, put, delete: del } = useApi()

  const records = ref([])
  const meta = ref(null)
  const currentRecord = ref(null)
  const evolutions = ref([])
  const attachments = ref([])
  const stats = ref(null)

  const loading = ref(false)
  const loadingBySurface = reactive(EMPTY_LOADING())
  const errors = reactive(EMPTY_ERRORS())

  const error = computed(() => Object.values(errors).find(Boolean) || null)
  const hasRecords = computed(() => records.value.length > 0)
  const hasEvolutions = computed(() => evolutions.value.length > 0)
  const hasAttachments = computed(() => attachments.value.length > 0)

  const read = async (surface, operation, fallback) => {
    loadingBySurface[surface] = true
    errors[surface] = null

    try {
      return await operation()
    } catch (err) {
      errors[surface] = messageFrom(err, fallback)
      throw err
    } finally {
      loadingBySurface[surface] = false
    }
  }

  const write = async (operation, fallback) => {
    loading.value = true
    errors.write = null

    try {
      return await operation()
    } catch (err) {
      errors.write = messageFrom(err, fallback)
      throw err
    } finally {
      loading.value = false
    }
  }

  const getRecords = async (params = {}) =>
    read(
      'records',
      async () => {
        const response = await get(`/api/medical-records${buildQuery(params)}`)
        records.value = response.data || []
        meta.value = response.meta || null

        return records.value
      },
      'Error al obtener historias clínicas'
    )

  const getRecord = async id =>
    read(
      'records',
      async () => {
        const response = await get(`/api/medical-records/${id}`)
        currentRecord.value = response.data || null

        return currentRecord.value
      },
      'Error al obtener historia clínica'
    )

  const createRecord = async payload =>
    write(async () => {
      const response = await post('/api/medical-records', payload)
      records.value.unshift(response.data)

      return response.data
    }, 'Error al crear historia clínica')

  const updateRecord = async (id, payload) =>
    write(async () => {
      const response = await put(`/api/medical-records/${id}`, payload)
      const updated = response.data
      const index = records.value.findIndex(record => record.id === id)

      if (index !== -1) records.value[index] = updated
      if (currentRecord.value?.id === id) currentRecord.value = updated

      return updated
    }, 'Error al actualizar historia clínica')

  const deleteRecord = async id =>
    write(async () => {
      await del(`/api/medical-records/${id}`)
      records.value = records.value.filter(record => record.id !== id)

      if (currentRecord.value?.id === id) currentRecord.value = null

      return true
    }, 'Error al eliminar historia clínica')

  const getEvolutions = async recordId =>
    read(
      'evolutions',
      async () => {
        const response = await get(`/api/medical-records/${recordId}/evolutions`)
        evolutions.value = response.data || []

        return evolutions.value
      },
      'Error al obtener evoluciones'
    )

  const addEvolution = async (recordId, payload) =>
    write(async () => {
      const response = await post(`/api/medical-records/${recordId}/evolutions`, payload)
      evolutions.value.unshift(response.data)

      return response.data
    }, 'Error al agregar evolución')

  const updateEvolution = async (id, payload) =>
    write(async () => {
      const response = await put(`/api/medical-records/evolutions/${id}`, payload)
      const updated = response.data
      const index = evolutions.value.findIndex(evolution => evolution.id === id)

      if (index !== -1) evolutions.value[index] = updated

      return updated
    }, 'Error al actualizar evolución')

  const deleteEvolution = async id =>
    write(async () => {
      await del(`/api/medical-records/evolutions/${id}`)
      evolutions.value = evolutions.value.filter(evolution => evolution.id !== id)

      return true
    }, 'Error al eliminar evolución')

  const getAttachments = async (patientId, category = null) => {
    const query = category ? `?category=${encodeURIComponent(category)}` : ''

    return read(
      'attachments',
      async () => {
        const response = await get(`/api/medical-records/patient/${patientId}/attachments${query}`)
        attachments.value = response.data || []

        return attachments.value
      },
      'Error al obtener archivos'
    )
  }

  // Compatibility shim for the pre-redesign page; removed once the page reads
  // the attachments surface directly.
  const getAttachmentsByCategory = (patientId, category = null) =>
    getAttachments(patientId, category)

  const uploadAttachment = async payload =>
    write(async () => {
      const response = await post('/api/medical-records/attachments', payload, {
        headers: { 'Content-Type': 'multipart/form-data' }
      })
      attachments.value.unshift(response.data)

      return response.data
    }, 'Error al subir archivo')

  const deleteAttachment = async id =>
    write(async () => {
      await del(`/api/medical-records/attachments/${id}`)
      attachments.value = attachments.value.filter(attachment => attachment.id !== id)

      return true
    }, 'Error al eliminar archivo')

  const getStats = async patientId =>
    read(
      'stats',
      async () => {
        const response = await get(`/api/medical-records/patient/${patientId}/stats`)
        stats.value = response.data || null

        return stats.value
      },
      'Error al obtener estadísticas'
    )

  const clearError = () => {
    Object.assign(errors, EMPTY_ERRORS())
  }

  const reset = () => {
    records.value = []
    meta.value = null
    currentRecord.value = null
    evolutions.value = []
    attachments.value = []
    stats.value = null
    loading.value = false
    Object.assign(loadingBySurface, EMPTY_LOADING())
    Object.assign(errors, EMPTY_ERRORS())
  }

  return {
    records,
    meta,
    currentRecord,
    evolutions,
    attachments,
    stats,

    loading,
    loadingBySurface,
    errors,
    error,

    hasRecords,
    hasEvolutions,
    hasAttachments,

    getRecords,
    getRecord,
    createRecord,
    updateRecord,
    deleteRecord,

    getEvolutions,
    addEvolution,
    updateEvolution,
    deleteEvolution,

    getAttachments,
    getAttachmentsByCategory,
    uploadAttachment,
    deleteAttachment,

    getStats,
    clearError,
    reset
  }
}
