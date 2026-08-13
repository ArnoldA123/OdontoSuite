import { ref, computed } from 'vue'
import { useApi } from './useApi'

export function useQuotations() {
  const { get, post, put, delete: del } = useApi()

  // Estado reactivo
  const quotations = ref([])
  const currentQuotation = ref(null)
  const loading = ref(false)
  const error = ref(null)
  const pagination = ref({
    current_page: 1,
    last_page: 1,
    per_page: 15,
    total: 0
  })

  // Computed
  const hasQuotations = computed(() => quotations.value && quotations.value.length > 0)
  const totalPages = computed(() => pagination.value?.last_page || 1)
  const currentPage = computed(() => pagination.value?.current_page || 1)

  // Métodos
  const getQuotations = async (filters = {}) => {
    try {
      loading.value = true
      error.value = null

      const params = new URLSearchParams()
      Object.keys(filters).forEach(key => {
        if (filters[key] !== null && filters[key] !== undefined && filters[key] !== '') {
          params.append(key, filters[key])
        }
      })

      const response = await get(`/api/quotations?${params.toString()}`)
      quotations.value = response.data || []
      pagination.value = response.meta || {
        current_page: 1,
        last_page: 1,
        per_page: 15,
        total: 0
      }

      return response.data
    } catch (err) {
      error.value = err.response?.data?.message || 'Error al obtener presupuestos'
      throw err
    } finally {
      loading.value = false
    }
  }

  const getQuotation = async id => {
    try {
      loading.value = true
      error.value = null

      const response = await get(`/api/quotations/${id}`)
      currentQuotation.value = response.data

      return response.data
    } catch (err) {
      error.value = err.response?.data?.message || 'Error al obtener presupuesto'
      throw err
    } finally {
      loading.value = false
    }
  }

  const createQuotation = async data => {
    try {
      loading.value = true
      error.value = null

      const response = await post('/api/quotations', data)
      const newQuotation = response.data

      // Agregar al inicio de la lista
      quotations.value.unshift(newQuotation)

      return newQuotation
    } catch (err) {
      error.value = err.response?.data?.message || 'Error al crear presupuesto'
      throw err
    } finally {
      loading.value = false
    }
  }

  const updateQuotation = async (id, data) => {
    try {
      loading.value = true
      error.value = null

      const response = await put(`/api/quotations/${id}`, data)
      const updatedQuotation = response.data

      // Actualizar en la lista
      const index = quotations.value.findIndex(quotation => quotation.id === id)
      if (index !== -1) {
        quotations.value[index] = updatedQuotation
      }

      // Actualizar presupuesto actual si es el mismo
      if (currentQuotation.value?.id === id) {
        currentQuotation.value = updatedQuotation
      }

      return updatedQuotation
    } catch (err) {
      error.value = err.response?.data?.message || 'Error al actualizar presupuesto'
      throw err
    } finally {
      loading.value = false
    }
  }

  const deleteQuotation = async id => {
    try {
      loading.value = true
      error.value = null

      await del(`/api/quotations/${id}`)

      // Remover de la lista
      quotations.value = quotations.value.filter(quotation => quotation.id !== id)

      // Limpiar presupuesto actual si es el mismo
      if (currentQuotation.value?.id === id) {
        currentQuotation.value = null
      }

      return true
    } catch (err) {
      error.value = err.response?.data?.message || 'Error al eliminar presupuesto'
      throw err
    } finally {
      loading.value = false
    }
  }

  const approveQuotation = async (id, approvalData = {}) => {
    try {
      loading.value = true
      error.value = null

      const response = await post(`/api/quotations/${id}/approve`, approvalData)
      const updatedQuotation = response.data

      // Actualizar en la lista
      const index = quotations.value.findIndex(quotation => quotation.id === id)
      if (index !== -1) {
        quotations.value[index] = updatedQuotation
      }

      // Actualizar presupuesto actual si es el mismo
      if (currentQuotation.value?.id === id) {
        currentQuotation.value = updatedQuotation
      }

      return updatedQuotation
    } catch (err) {
      error.value = err.response?.data?.message || 'Error al aprobar presupuesto'
      throw err
    } finally {
      loading.value = false
    }
  }

  const rejectQuotation = async (id, reason) => {
    try {
      loading.value = true
      error.value = null

      const response = await post(`/api/quotations/${id}/reject`, { reason })
      const updatedQuotation = response.data

      // Actualizar en la lista
      const index = quotations.value.findIndex(quotation => quotation.id === id)
      if (index !== -1) {
        quotations.value[index] = updatedQuotation
      }

      // Actualizar presupuesto actual si es el mismo
      if (currentQuotation.value?.id === id) {
        currentQuotation.value = updatedQuotation
      }

      return updatedQuotation
    } catch (err) {
      error.value = err.response?.data?.message || 'Error al rechazar presupuesto'
      throw err
    } finally {
      loading.value = false
    }
  }

  const downloadPDF = async id => {
    try {
      loading.value = true
      error.value = null

      const response = await get(`/api/quotations/${id}/pdf`, {
        responseType: 'blob'
      })

      // Crear URL del blob y descargar
      const blob = new Blob([response.data], { type: 'application/pdf' })
      const url = window.URL.createObjectURL(blob)
      const link = document.createElement('a')
      link.href = url
      link.download = `presupuesto_${id}.pdf`
      document.body.appendChild(link)
      link.click()
      document.body.removeChild(link)
      window.URL.revokeObjectURL(url)

      return true
    } catch (err) {
      error.value = err.response?.data?.message || 'Error al descargar PDF'
      throw err
    } finally {
      loading.value = false
    }
  }

  const getQuotationsByPatient = async patientId => {
    try {
      loading.value = true
      error.value = null

      const response = await get(`/api/quotations/patient/${patientId}`)
      quotations.value = response.data

      return response.data
    } catch (err) {
      error.value = err.response?.data?.message || 'Error al obtener presupuestos del paciente'
      throw err
    } finally {
      loading.value = false
    }
  }

  const clearError = () => {
    error.value = null
  }

  const reset = () => {
    quotations.value = []
    currentQuotation.value = null
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
    quotations,
    currentQuotation,
    loading,
    error,
    pagination,

    // Computed
    hasQuotations,
    totalPages,
    currentPage,

    // Métodos
    getQuotations,
    getQuotation,
    createQuotation,
    updateQuotation,
    deleteQuotation,
    approveQuotation,
    rejectQuotation,
    downloadPDF,
    getQuotationsByPatient,
    clearError,
    reset
  }
}
