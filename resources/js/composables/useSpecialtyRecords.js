import { ref, computed } from 'vue'
import { useApi } from './useApi'

export function useSpecialtyRecords() {
  const { get, post, put, delete: del } = useApi()

  // Estado reactivo
  const records = ref([])
  const currentRecord = ref(null)
  const allRecords = ref({})
  const loading = ref(false)
  const error = ref(null)

  // Computed
  const hasRecords = computed(() => records.value && records.value.length > 0)
  const hasAllRecords = computed(() => allRecords.value && Object.keys(allRecords.value).length > 0)

  // Métodos
  const getRecords = async (patientId, specialty) => {
    try {
      loading.value = true
      error.value = null

      const response = await get(`/api/specialty-records/patient/${patientId}/${specialty}`)
      records.value = response.data || []

      return response.data || []
    } catch (err) {
      error.value = err.response?.data?.message || 'Error al obtener registros de especialidad'
      throw err
    } finally {
      loading.value = false
    }
  }

  const getRecord = async id => {
    try {
      loading.value = true
      error.value = null

      const response = await get(`/api/specialty-records/${id}`)
      currentRecord.value = response.data

      return response.data
    } catch (err) {
      error.value = err.response?.data?.message || 'Error al obtener registro de especialidad'
      throw err
    } finally {
      loading.value = false
    }
  }

  const createRecord = async data => {
    try {
      loading.value = true
      error.value = null

      const response = await post('/api/specialty-records', data)
      const newRecord = response.data

      // Agregar al inicio de la lista
      records.value.unshift(newRecord)

      return newRecord
    } catch (err) {
      error.value = err.response?.data?.message || 'Error al crear registro de especialidad'
      throw err
    } finally {
      loading.value = false
    }
  }

  const updateRecord = async (id, data) => {
    try {
      loading.value = true
      error.value = null

      const response = await put(`/api/specialty-records/${id}`, data)
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
      error.value = err.response?.data?.message || 'Error al actualizar registro de especialidad'
      throw err
    } finally {
      loading.value = false
    }
  }

  const deleteRecord = async (id, specialty) => {
    try {
      loading.value = true
      error.value = null

      await del(`/api/specialty-records/${id}`, {
        data: { specialty }
      })

      // Remover de la lista
      records.value = records.value.filter(record => record.id !== id)

      // Limpiar registro actual si es el mismo
      if (currentRecord.value?.id === id) {
        currentRecord.value = null
      }

      return true
    } catch (err) {
      error.value = err.response?.data?.message || 'Error al eliminar registro de especialidad'
      throw err
    } finally {
      loading.value = false
    }
  }

  const getAllRecords = async patientId => {
    try {
      loading.value = true
      error.value = null

      const response = await get(`/api/specialty-records/patient/${patientId}/all`)
      allRecords.value = response.data

      return response.data
    } catch (err) {
      error.value = err.response?.data?.message || 'Error al obtener todos los registros'
      throw err
    } finally {
      loading.value = false
    }
  }

  const getStats = async (patientId, specialty) => {
    try {
      loading.value = true
      error.value = null

      const response = await get(`/api/specialty-records/patient/${patientId}/${specialty}/stats`)

      return response.data
    } catch (err) {
      error.value = err.response?.data?.message || 'Error al obtener estadísticas'
      throw err
    } finally {
      loading.value = false
    }
  }

  // Métodos específicos por especialidad
  const createImplantologyRecord = async data => {
    return await createRecord({ ...data, specialty: 'implantologia' })
  }

  const createOrthodonticsRecord = async data => {
    return await createRecord({ ...data, specialty: 'ortodoncia' })
  }

  const createEndodonticsRecord = async data => {
    return await createRecord({ ...data, specialty: 'endodoncia' })
  }

  const createRehabilitationRecord = async data => {
    return await createRecord({ ...data, specialty: 'rehabilitacion' })
  }

  const createOralSurgeryRecord = async data => {
    return await createRecord({ ...data, specialty: 'cirugia_oral' })
  }

  const getImplantologyRecords = async patientId => {
    return await getRecords(patientId, 'implantologia')
  }

  const getOrthodonticsRecords = async patientId => {
    return await getRecords(patientId, 'ortodoncia')
  }

  const getEndodonticsRecords = async patientId => {
    return await getRecords(patientId, 'endodoncia')
  }

  const getRehabilitationRecords = async patientId => {
    return await getRecords(patientId, 'rehabilitacion')
  }

  const getOralSurgeryRecords = async patientId => {
    return await getRecords(patientId, 'cirugia_oral')
  }

  const clearError = () => {
    error.value = null
  }

  const reset = () => {
    records.value = []
    currentRecord.value = null
    allRecords.value = {}
    loading.value = false
    error.value = null
  }

  return {
    // Estado
    records,
    currentRecord,
    allRecords,
    loading,
    error,

    // Computed
    hasRecords,
    hasAllRecords,

    // Métodos generales
    getRecords,
    getRecord,
    createRecord,
    updateRecord,
    deleteRecord,
    getAllRecords,
    getStats,

    // Métodos específicos por especialidad
    createImplantologyRecord,
    createOrthodonticsRecord,
    createEndodonticsRecord,
    createRehabilitationRecord,
    createOralSurgeryRecord,
    getImplantologyRecords,
    getOrthodonticsRecords,
    getEndodonticsRecords,
    getRehabilitationRecords,
    getOralSurgeryRecords,

    // Utilidades
    clearError,
    reset
  }
}
