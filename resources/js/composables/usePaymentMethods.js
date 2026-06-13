import { ref, computed } from 'vue'
import { useApi } from './useApi'

/**
 * Sprint 2 (B-CASH-3 + B-CASH-4 prep): composable para CRUD admin
 * de metodos de pago. Patron identico a useBranches.js.
 * Endpoint: /api/payment-methods (admin CRUD) o /api/payment-methods/active
 * (publico para dropdowns).
 */
export function usePaymentMethods (endpoint = '/api/payment-methods') {
  const { get, post, put, delete: del } = useApi()

  const methods = ref([])
  const currentMethod = ref(null)
  const loading = ref(false)
  const error = ref(null)

  const hasMethods = computed(() => methods.value.length > 0)
  const activeMethods = computed(() => methods.value.filter(m => m.is_active))
  const systemMethods = computed(() => methods.value.filter(m => m.is_system))
  const customMethods = computed(() => methods.value.filter(m => !m.is_system))

  const buildQuery = (filters = {}) => {
    const params = new URLSearchParams()
    Object.entries(filters).forEach(([key, value]) => {
      if (value !== null && value !== undefined && value !== '') {
        params.append(key, value)
      }
    })
    return params.toString()
  }

  const getMethods = async (filters = {}) => {
    try {
      loading.value = true
      error.value = null
      const response = await get(`${endpoint}?${buildQuery(filters)}`)
      const body = response.data
      if (body?.data && Array.isArray(body.data)) {
        methods.value = body.data
      } else {
        methods.value = []
      }
      return response.data
    } catch (err) {
      error.value = err.response?.data?.message || 'Error al obtener metodos de pago'
      methods.value = []
      throw err
    } finally {
      loading.value = false
    }
  }

  const getMethod = async id => {
    try {
      loading.value = true
      error.value = null
      const response = await get(`${endpoint}/${id}`)
      currentMethod.value = response.data
      return response.data
    } catch (err) {
      error.value = err.response?.data?.message || 'Error al obtener el metodo de pago'
      throw err
    } finally {
      loading.value = false
    }
  }

  const createMethod = async data => {
    try {
      loading.value = true
      error.value = null
      const response = await post(endpoint, data)
      const created = response.data
      methods.value.unshift(created)
      return created
    } catch (err) {
      error.value = err.response?.data?.message || 'Error al crear el metodo de pago'
      throw err
    } finally {
      loading.value = false
    }
  }

  const updateMethod = async (id, data) => {
    try {
      loading.value = true
      error.value = null
      const response = await put(`${endpoint}/${id}`, data)
      const updated = response.data
      const index = methods.value.findIndex(m => m.id === id)
      if (index !== -1) methods.value[index] = updated
      if (currentMethod.value?.id === id) currentMethod.value = updated
      return updated
    } catch (err) {
      error.value = err.response?.data?.message || 'Error al actualizar el metodo de pago'
      throw err
    } finally {
      loading.value = false
    }
  }

  const deleteMethod = async id => {
    try {
      loading.value = true
      error.value = null
      await del(`${endpoint}/${id}`)
      const index = methods.value.findIndex(m => m.id === id)
      if (index !== -1) methods.value.splice(index, 1)
      return true
    } catch (err) {
      error.value = err.response?.data?.message || 'Error al eliminar el metodo de pago'
      throw err
    } finally {
      loading.value = false
    }
  }

  const toggleActive = async method => {
    return updateMethod(method.id, { ...method, is_active: !method.is_active })
  }

  const clearError = () => {
    error.value = null
  }

  const reset = () => {
    methods.value = []
    currentMethod.value = null
    loading.value = false
    error.value = null
  }

  return {
    methods,
    currentMethod,
    loading,
    error,
    hasMethods,
    activeMethods,
    systemMethods,
    customMethods,
    getMethods,
    getMethod,
    createMethod,
    updateMethod,
    deleteMethod,
    toggleActive,
    clearError,
    reset
  }
}
