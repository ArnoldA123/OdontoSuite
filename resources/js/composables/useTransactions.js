import { ref, computed } from 'vue'
import { useApi } from './useApi'

export function useTransactions() {
  const { get, post, put, del } = useApi()

  // Estado reactivo
  const transactions = ref([])
  const loading = ref(false)
  const error = ref(null)
  const pagination = ref(null)

  // Computed properties
  const totalTransactions = computed(() => pagination.value?.total || 0)
  const hasTransactions = computed(() => transactions.value.length > 0)

  // Obtener transacciones con filtros
  const getTransactions = async (filters = {}) => {
    loading.value = true
    error.value = null

    try {
      const response = await get('/api/transactions', { params: filters })
      transactions.value = response.data || []
      pagination.value = response.meta || null
      return response
    } catch (err) {
      error.value = err.message || 'Error al obtener las transacciones'
      throw err
    } finally {
      loading.value = false
    }
  }

  // Crear transacción
  const createTransaction = async data => {
    loading.value = true
    error.value = null

    try {
      const response = await post('/api/transactions', data)

      // Agregar a la lista local si es necesario
      if (transactions.value.length > 0) {
        transactions.value.unshift(response.data)
      }

      return response.data
    } catch (err) {
      error.value = err.message || 'Error al crear la transacción'
      throw err
    } finally {
      loading.value = false
    }
  }

  // Obtener transacción por ID
  const getTransaction = async id => {
    loading.value = true
    error.value = null

    try {
      const response = await get(`/api/transactions/${id}`)
      return response.data
    } catch (err) {
      error.value = err.message || 'Error al obtener la transacción'
      throw err
    } finally {
      loading.value = false
    }
  }

  // Actualizar transacción
  const updateTransaction = async (id, data) => {
    loading.value = true
    error.value = null

    try {
      const response = await put(`/api/transactions/${id}`, data)

      // Actualizar en la lista local
      const index = transactions.value.findIndex(t => t.id === id)
      if (index !== -1) {
        transactions.value[index] = response.data
      }

      return response.data
    } catch (err) {
      error.value = err.message || 'Error al actualizar la transacción'
      throw err
    } finally {
      loading.value = false
    }
  }

  // Anular transacción
  const voidTransaction = async (id, reason) => {
    loading.value = true
    error.value = null

    try {
      const response = await post(`/api/transactions/${id}/void`, { reason })

      // Actualizar en la lista local
      const index = transactions.value.findIndex(t => t.id === id)
      if (index !== -1) {
        transactions.value[index] = response.data
      }

      return response.data
    } catch (err) {
      error.value = err.message || 'Error al anular la transacción'
      throw err
    } finally {
      loading.value = false
    }
  }

  // Eliminar transacción
  const deleteTransaction = async id => {
    loading.value = true
    error.value = null

    try {
      await del(`/api/transactions/${id}`)

      // Remover de la lista local
      const index = transactions.value.findIndex(t => t.id === id)
      if (index !== -1) {
        transactions.value.splice(index, 1)
      }

      return true
    } catch (err) {
      error.value = err.message || 'Error al eliminar la transacción'
      throw err
    } finally {
      loading.value = false
    }
  }

  // Generar comprobante
  const generateReceipt = async id => {
    loading.value = true
    error.value = null

    try {
      const response = await post(`/api/transactions/${id}/receipt`)
      return response.data
    } catch (err) {
      error.value = err.message || 'Error al generar el comprobante'
      throw err
    } finally {
      loading.value = false
    }
  }

  // Obtener lista de transacciones (para dropdowns)
  const getTransactionsList = async (filters = {}) => {
    loading.value = true
    error.value = null

    try {
      const response = await get('/api/transactions/list', { params: filters })
      return response.data || []
    } catch (err) {
      error.value = err.message || 'Error al obtener la lista de transacciones'
      throw err
    } finally {
      loading.value = false
    }
  }

  // Calcular descuentos
  const calculateDiscount = (amount, discountType, discountValue) => {
    if (!amount || !discountValue) return 0

    if (discountType === 'percentage') {
      return (amount * discountValue) / 100
    } else {
      return Math.min(discountValue, amount) // No puede exceder el monto
    }
  }

  // Validar autorización de descuento
  const requiresDiscountAuthorization = (amount, discountAmount) => {
    if (!amount || !discountAmount) return false

    const discountPercentage = (discountAmount / amount) * 100
    return discountPercentage > 10
  }

  // Formatear monto para mostrar
  const formatAmount = amount => {
    return new Intl.NumberFormat('es-PE', {
      style: 'currency',
      currency: 'PEN'
    }).format(amount || 0)
  }

  // Filtrar transacciones localmente
  const filterTransactions = filters => {
    let filtered = [...transactions.value]

    if (filters.patient_id) {
      filtered = filtered.filter(t => t.patient_id === filters.patient_id)
    }

    if (filters.type) {
      filtered = filtered.filter(t => t.type === filters.type)
    }

    if (filters.payment_method_id) {
      filtered = filtered.filter(t => t.payment_method_id === filters.payment_method_id)
    }

    if (filters.status) {
      filtered = filtered.filter(t => t.status === filters.status)
    }

    if (filters.date_from) {
      const dateFrom = new Date(filters.date_from)
      filtered = filtered.filter(t => new Date(t.created_at) >= dateFrom)
    }

    if (filters.date_to) {
      const dateTo = new Date(filters.date_to)
      filtered = filtered.filter(t => new Date(t.created_at) <= dateTo)
    }

    return filtered
  }

  // Obtener estadísticas de transacciones
  const getTransactionStats = (transactionList = null) => {
    const list = transactionList || transactions.value

    const stats = {
      total: list.length,
      totalAmount: list.reduce((sum, t) => sum + (t.amount || 0), 0),
      byType: {},
      byStatus: {},
      byPaymentMethod: {}
    }

    list.forEach(transaction => {
      // Por tipo
      stats.byType[transaction.type] = (stats.byType[transaction.type] || 0) + 1

      // Por estado
      stats.byStatus[transaction.status] = (stats.byStatus[transaction.status] || 0) + 1

      // Por método de pago
      const methodName = transaction.payment_method?.name || 'N/A'
      stats.byPaymentMethod[methodName] = (stats.byPaymentMethod[methodName] || 0) + 1
    })

    return stats
  }

  // Limpiar estado
  const clearState = () => {
    transactions.value = []
    pagination.value = null
    error.value = null
  }

  return {
    // Estado — Slice 08 / T-08.10 + T-08.11 canonical shape.
    transactions,
    data: transactions, // alias (T-08.10)
    loading,
    error,
    pagination,

    // Computed
    totalTransactions,
    hasTransactions,

    // Métodos
    getTransactions,
    createTransaction,
    getTransaction,
    updateTransaction,
    voidTransaction,
    deleteTransaction,
    generateReceipt,
    getTransactionsList,
    calculateDiscount,
    requiresDiscountAuthorization,
    formatAmount,
    filterTransactions,
    getTransactionStats,
    clearState,

    // Slice 08 / T-08.11: refresh + retry aliases.
    refresh: getTransactions,
    retry: getTransactions
  }
}
