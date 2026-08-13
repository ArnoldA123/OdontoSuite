import { ref, computed } from 'vue'
import { useApi } from './useApi'

export function useMedicalRecords() {
  const { get, post, put, delete: del } = useApi()

  // Estado reactivo
  const records = ref([])
  const currentRecord = ref(null)
  const evolutions = ref([])
  const attachments = ref([])
  const loading = ref(false)
  const error = ref(null)

  // Computed
  const hasRecords = computed(() => records.value && records.value.length > 0)
  const hasEvolutions = computed(() => evolutions.value && evolutions.value.length > 0)
  const hasAttachments = computed(() => attachments.value && attachments.value.length > 0)

  // Métodos
  const getRecords = async patientId => {
    try {
      loading.value = true
      error.value = null

      const response = await get(`/api/medical-records?patient_id=${patientId}`)
      records.value = response.data || []

      return response.data || []
    } catch (err) {
      error.value = err.response?.data?.message || 'Error al obtener historias clínicas'
      throw err
    } finally {
      loading.value = false
    }
  }

  const getRecord = async id => {
    try {
      loading.value = true
      error.value = null

      const response = await get(`/api/medical-records/${id}`)
      currentRecord.value = response.data

      return response.data
    } catch (err) {
      error.value = err.response?.data?.message || 'Error al obtener historia clínica'
      throw err
    } finally {
      loading.value = false
    }
  }

  const createRecord = async data => {
    try {
      loading.value = true
      error.value = null

      const response = await post('/api/medical-records', data)
      const newRecord = response.data

      // Agregar al inicio de la lista
      records.value.unshift(newRecord)

      return newRecord
    } catch (err) {
      error.value = err.response?.data?.message || 'Error al crear historia clínica'
      throw err
    } finally {
      loading.value = false
    }
  }

  const updateRecord = async (id, data) => {
    try {
      loading.value = true
      error.value = null

      const response = await put(`/api/medical-records/${id}`, data)
      const updatedRecord = response.data

      // Actualizar en la lista
      const index = records.value.findIndex(record => record.id === id)
      if (index !== -1) {
        records.value[index] = updatedRecord
      }

      // Actualizar registro actual si es el mismo
      if (currentRecord.value?.id === id) {
        currentRecord.value = updatedRecord
      }

      return updatedRecord
    } catch (err) {
      error.value = err.response?.data?.message || 'Error al actualizar historia clínica'
      throw err
    } finally {
      loading.value = false
    }
  }

  const deleteRecord = async id => {
    try {
      loading.value = true
      error.value = null

      await del(`/api/medical-records/${id}`)

      // Remover de la lista
      records.value = records.value.filter(record => record.id !== id)

      // Limpiar registro actual si es el mismo
      if (currentRecord.value?.id === id) {
        currentRecord.value = null
      }

      return true
    } catch (err) {
      error.value = err.response?.data?.message || 'Error al eliminar historia clínica'
      throw err
    } finally {
      loading.value = false
    }
  }

  const addEvolution = async (recordId, data) => {
    try {
      loading.value = true
      error.value = null

      const response = await post(`/api/medical-records/${recordId}/evolutions`, data)
      const newEvolution = response.data

      // Agregar al inicio de la lista de evoluciones
      evolutions.value.unshift(newEvolution)

      // Actualizar registro actual si es el mismo
      if (currentRecord.value?.id === recordId) {
        if (!currentRecord.value.evolutions) {
          currentRecord.value.evolutions = []
        }
        currentRecord.value.evolutions.unshift(newEvolution)
      }

      return newEvolution
    } catch (err) {
      error.value = err.response?.data?.message || 'Error al agregar evolución'
      throw err
    } finally {
      loading.value = false
    }
  }

  const getEvolutions = async recordId => {
    try {
      loading.value = true
      error.value = null

      const response = await get(`/api/medical-records/${recordId}/evolutions`)
      evolutions.value = response.data

      return response.data
    } catch (err) {
      error.value = err.response?.data?.message || 'Error al obtener evoluciones'
      throw err
    } finally {
      loading.value = false
    }
  }

  const uploadAttachment = async data => {
    try {
      loading.value = true
      error.value = null

      const formData = new FormData()
      Object.keys(data).forEach(key => {
        if (data[key] !== null && data[key] !== undefined) {
          formData.append(key, data[key])
        }
      })

      const response = await post('/api/medical-records/attachments', formData, {
        headers: {
          'Content-Type': 'multipart/form-data'
        }
      })
      const newAttachment = response.data

      // Agregar a la lista de adjuntos
      attachments.value.unshift(newAttachment)

      return newAttachment
    } catch (err) {
      error.value = err.response?.data?.message || 'Error al subir archivo'
      throw err
    } finally {
      loading.value = false
    }
  }

  const getStats = async patientId => {
    try {
      loading.value = true
      error.value = null

      const response = await get(`/api/medical-records/patient/${patientId}/stats`)

      return response.data
    } catch (err) {
      error.value = err.response?.data?.message || 'Error al obtener estadísticas'
      throw err
    } finally {
      loading.value = false
    }
  }

  const getAttachmentsByCategory = async (patientId, category = 'general') => {
    try {
      loading.value = true
      error.value = null

      const response = await get(
        `/api/medical-records/patient/${patientId}/attachments?category=${category}`
      )
      attachments.value = response.data

      return response.data
    } catch (err) {
      error.value = err.response?.data?.message || 'Error al obtener archivos'
      throw err
    } finally {
      loading.value = false
    }
  }

  const deleteAttachment = async attachmentId => {
    try {
      loading.value = true
      error.value = null

      await del(`/api/medical-records/attachments/${attachmentId}`)

      // Remover de la lista
      attachments.value = attachments.value.filter(attachment => attachment.id !== attachmentId)

      return true
    } catch (err) {
      error.value = err.response?.data?.message || 'Error al eliminar archivo'
      throw err
    } finally {
      loading.value = false
    }
  }

  const clearError = () => {
    error.value = null
  }

  const reset = () => {
    records.value = []
    currentRecord.value = null
    evolutions.value = []
    attachments.value = []
    loading.value = false
    error.value = null
  }

  return {
    // Estado
    records,
    currentRecord,
    evolutions,
    attachments,
    loading,
    error,

    // Computed
    hasRecords,
    hasEvolutions,
    hasAttachments,

    // Métodos
    getRecords,
    getRecord,
    createRecord,
    updateRecord,
    deleteRecord,
    addEvolution,
    getEvolutions,
    uploadAttachment,
    getStats,
    getAttachmentsByCategory,
    deleteAttachment,
    clearError,
    reset
  }
}
