import { ref, computed } from 'vue'
import { useApi } from './useApi'

export function useProcedureCatalog () {
  const { get, post, put, delete: del } = useApi()

  const procedures = ref([])
  const currentProcedure = ref(null)
  const loading = ref(false)
  const error = ref(null)
  const pagination = ref({
    current_page: 1,
    last_page: 1,
    per_page: 20,
    total: 0
  })

  const hasProcedures = computed(() => procedures.value.length > 0)
  const totalPages = computed(() => pagination.value?.last_page || 1)
  const currentPage = computed(() => pagination.value?.current_page || 1)

  const buildQuery = (filters = {}) => {
    const params = new URLSearchParams()
    Object.entries(filters).forEach(([key, value]) => {
      if (value !== null && value !== undefined && value !== '') {
        params.append(key, value)
      }
    })
    return params.toString()
  }

  const getProcedures = async (filters = {}) => {
    try {
      loading.value = true
      error.value = null
      const response = await get(`/api/procedure-catalog?${buildQuery(filters)}`)
      procedures.value = response.data || []
      pagination.value = response.meta || pagination.value
      return response.data
    } catch (err) {
      error.value = err.response?.data?.message || 'Error al obtener procedimientos'
      procedures.value = []
      throw err
    } finally {
      loading.value = false
    }
  }

  const getProcedure = async id => {
    try {
      loading.value = true
      error.value = null
      const response = await get(`/api/procedure-catalog/${id}`)
      currentProcedure.value = response.data
      return response.data
    } catch (err) {
      error.value = err.response?.data?.message || 'Error al obtener el procedimiento'
      throw err
    } finally {
      loading.value = false
    }
  }

  const createProcedure = async data => {
    try {
      loading.value = true
      error.value = null
      const response = await post('/api/procedure-catalog', data)
      const created = response.data
      procedures.value.unshift(created)
      return created
    } catch (err) {
      error.value = err.response?.data?.message || 'Error al crear el procedimiento'
      throw err
    } finally {
      loading.value = false
    }
  }

  const updateProcedure = async (id, data) => {
    try {
      loading.value = true
      error.value = null
      const response = await put(`/api/procedure-catalog/${id}`, data)
      const updated = response.data
      const index = procedures.value.findIndex(p => p.id === id)
      if (index !== -1) procedures.value[index] = updated
      if (currentProcedure.value?.id === id) currentProcedure.value = updated
      return updated
    } catch (err) {
      error.value = err.response?.data?.message || 'Error al actualizar el procedimiento'
      throw err
    } finally {
      loading.value = false
    }
  }

  const deactivateProcedure = async id => {
    try {
      loading.value = true
      error.value = null
      await del(`/api/procedure-catalog/${id}`)
      const index = procedures.value.findIndex(p => p.id === id)
      if (index !== -1) procedures.value[index].is_active = false
      if (currentProcedure.value?.id === id) currentProcedure.value.is_active = false
      return true
    } catch (err) {
      error.value = err.response?.data?.message || 'Error al desactivar el procedimiento'
      throw err
    } finally {
      loading.value = false
    }
  }

  const clearError = () => {
    error.value = null
  }
  const reset = () => {
    procedures.value = []
    currentProcedure.value = null
    loading.value = false
    error.value = null
  }

  return {
    procedures,
    currentProcedure,
    loading,
    error,
    pagination,
    hasProcedures,
    totalPages,
    currentPage,
    getProcedures,
    getProcedure,
    createProcedure,
    updateProcedure,
    deactivateProcedure,
    clearError,
    reset
  }
}
