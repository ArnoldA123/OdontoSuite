import { ref, computed } from 'vue'
import { useApi } from './useApi'
import { useNotifications } from './useNotifications'

export function useTreatmentPlans() {
  const { get, post, put, delete: del } = useApi()
  const { addNotification } = useNotifications()

  // Estado reactivo
  const plans = ref([])
  const currentPlan = ref(null)
  const loading = ref(false)
  const error = ref(null)
  const pagination = ref({
    current_page: 1,
    last_page: 1,
    per_page: 15,
    total: 0
  })

  // Computed
  const hasPlans = computed(() => plans.value && plans.value.length > 0)
  const totalPages = computed(() => pagination.value?.last_page || 1)
  const currentPage = computed(() => pagination.value?.current_page || 1)

  // Métodos
  const getPlans = async (filters = {}) => {
    try {
      loading.value = true
      error.value = null

      const params = new URLSearchParams()
      Object.keys(filters).forEach(key => {
        if (filters[key] !== null && filters[key] !== undefined && filters[key] !== '') {
          params.append(key, filters[key])
        }
      })

      const response = await get(`/api/treatment-plans?${params.toString()}`)
      plans.value = response.data?.data || []
      pagination.value = response.data?.meta || {
        current_page: 1,
        last_page: 1,
        per_page: 15,
        total: 0
      }

      return response.data
    } catch (err) {
      error.value = err.response?.data?.message || 'Error al obtener planes de tratamiento'
      throw err
    } finally {
      loading.value = false
    }
  }

  const getPlan = async (id) => {
    try {
      loading.value = true
      error.value = null

      const response = await get(`/api/treatment-plans/${id}`)
      currentPlan.value = response.data.data

      return response.data.data
    } catch (err) {
      error.value = err.response?.data?.message || 'Error al obtener plan de tratamiento'
      throw err
    } finally {
      loading.value = false
    }
  }

  const createPlan = async (data) => {
    try {
      loading.value = true
      error.value = null

      console.log('Creando plan con datos:', data)
      const response = await post('/api/treatment-plans', data)
      console.log('Respuesta del servidor:', response)
      const newPlan = response.data.data

      // Agregar al inicio de la lista
      plans.value.unshift(newPlan)
      console.log('Plan agregado a la lista. Total planes:', plans.value.length)

      // Mostrar notificación de éxito
      addNotification('Plan de tratamiento creado exitosamente', 'success')

      return newPlan
    } catch (err) {
      console.error('Error en createPlan:', err)
      error.value = err.response?.data?.message || 'Error al crear plan de tratamiento'
      throw err
    } finally {
      loading.value = false
    }
  }

  const updatePlan = async (id, data) => {
    try {
      loading.value = true
      error.value = null

      const response = await put(`/api/treatment-plans/${id}`, data)
      const updatedPlan = response.data.data

      // Actualizar en la lista
      const index = plans.value.findIndex(plan => plan.id === id)
      if (index !== -1) {
        plans.value[index] = updatedPlan
      }

      // Actualizar plan actual si es el mismo
      if (currentPlan.value?.id === id) {
        currentPlan.value = updatedPlan
      }

      return updatedPlan
    } catch (err) {
      error.value = err.response?.data?.message || 'Error al actualizar plan de tratamiento'
      throw err
    } finally {
      loading.value = false
    }
  }

  const deletePlan = async (id) => {
    try {
      loading.value = true
      error.value = null

      await del(`/api/treatment-plans/${id}`)

      // Remover de la lista
      plans.value = plans.value.filter(plan => plan.id !== id)

      // Limpiar plan actual si es el mismo
      if (currentPlan.value?.id === id) {
        currentPlan.value = null
      }

      return true
    } catch (err) {
      error.value = err.response?.data?.message || 'Error al eliminar plan de tratamiento'
      throw err
    } finally {
      loading.value = false
    }
  }

  const changeStatus = async (id, status) => {
    try {
      loading.value = true
      error.value = null

      const response = await post(`/api/treatment-plans/${id}/change-status`, { status })
      const updatedPlan = response.data.data

      // Actualizar en la lista
      const index = plans.value.findIndex(plan => plan.id === id)
      if (index !== -1) {
        plans.value[index] = updatedPlan
      }

      // Actualizar plan actual si es el mismo
      if (currentPlan.value?.id === id) {
        currentPlan.value = updatedPlan
      }

      return updatedPlan
    } catch (err) {
      error.value = err.response?.data?.message || 'Error al cambiar estado del plan'
      throw err
    } finally {
      loading.value = false
    }
  }

  const duplicatePlan = async (id) => {
    try {
      loading.value = true
      error.value = null

      const response = await post(`/api/treatment-plans/${id}/duplicate`)
      const duplicatedPlan = response.data.data

      // Agregar al inicio de la lista
      plans.value.unshift(duplicatedPlan)

      return duplicatedPlan
    } catch (err) {
      error.value = err.response?.data?.message || 'Error al duplicar plan de tratamiento'
      throw err
    } finally {
      loading.value = false
    }
  }

  const addItem = async (planId, itemData) => {
    try {
      loading.value = true
      error.value = null

      const response = await post(`/api/treatment-plans/${planId}/add-item`, itemData)
      const updatedPlan = response.data.data

      // Actualizar en la lista
      const index = plans.value.findIndex(plan => plan.id === planId)
      if (index !== -1) {
        plans.value[index] = updatedPlan
      }

      // Actualizar plan actual si es el mismo
      if (currentPlan.value?.id === planId) {
        currentPlan.value = updatedPlan
      }

      return updatedPlan
    } catch (err) {
      error.value = err.response?.data?.message || 'Error al agregar procedimiento'
      throw err
    } finally {
      loading.value = false
    }
  }

  const removeItem = async (itemId) => {
    try {
      loading.value = true
      error.value = null

      await del(`/api/treatment-plans/items/${itemId}`)

      // Actualizar plan actual si tiene el item
      if (currentPlan.value?.items) {
        currentPlan.value.items = currentPlan.value.items.filter(item => item.id !== itemId)
      }

      return true
    } catch (err) {
      error.value = err.response?.data?.message || 'Error al eliminar procedimiento'
      throw err
    } finally {
      loading.value = false
    }
  }

  const clearError = () => {
    error.value = null
  }

  const reset = () => {
    plans.value = []
    currentPlan.value = null
    loading.value = false
    error.value = null
    pagination.value = {
      current_page: 1,
      last_page: 1,
      per_page: 15,
      total: 0
    }
  }

  return {
    // Estado
    plans,
    currentPlan,
    loading,
    error,
    pagination,

    // Computed
    hasPlans,
    totalPages,
    currentPage,

    // Métodos
    getPlans,
    getPlan,
    createPlan,
    updatePlan,
    deletePlan,
    changeStatus,
    duplicatePlan,
    addItem,
    removeItem,
    clearError,
    reset
  }
}
