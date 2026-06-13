import { ref, computed } from 'vue'
import { useApi } from './useApi'

/**
 * Sprint 1 (B-CASH-3): composable para CRUD admin de sucursales.
 * Patron: identico a useProcedureCatalog.js (mismo modulo del proyecto).
 * Mantiene `branches`, `pagination`, `loading`, `error` y expone las
 * operaciones tipicas del CRUD. Usado por BranchesPage (admin) y por
 * OpenCashModal (solo listar para el dropdown de Abrir Caja).
 */
export function useBranches () {
  const { get, post, put, delete: del } = useApi()

  const branches = ref([])
  const currentBranch = ref(null)
  const loading = ref(false)
  const error = ref(null)
  const pagination = ref({
    current_page: 1,
    last_page: 1,
    per_page: 20,
    total: 0
  })

  const hasBranches = computed(() => branches.value.length > 0)
  const totalPages = computed(() => pagination.value?.last_page || 1)
  const currentPage = computed(() => pagination.value?.current_page || 1)
  const activeBranches = computed(() => branches.value.filter(b => b.is_active))

  const buildQuery = (filters = {}) => {
    const params = new URLSearchParams()
    Object.entries(filters).forEach(([key, value]) => {
      if (value !== null && value !== undefined && value !== '') {
        params.append(key, value)
      }
    })
    return params.toString()
  }

  const getBranches = async (filters = {}) => {
    try {
      loading.value = true
      error.value = null
      const response = await get(`/api/branches?${buildQuery(filters)}`)
      // El backend devuelve { data: [...] } sin paginar, o { data: { data: [...], meta: {...} } }
      // si fuera paginado. Aceptamos ambos formatos.
      const body = response.data
      if (body?.data && Array.isArray(body.data)) {
        branches.value = body.data
      } else if (body?.data && Array.isArray(body.data.data)) {
        branches.value = body.data.data
        pagination.value = body.data.meta || pagination.value
      } else {
        branches.value = []
      }
      return response.data
    } catch (err) {
      error.value = err.response?.data?.message || 'Error al obtener sucursales'
      branches.value = []
      throw err
    } finally {
      loading.value = false
    }
  }

  const getBranch = async id => {
    try {
      loading.value = true
      error.value = null
      const response = await get(`/api/branches/${id}`)
      currentBranch.value = response.data
      return response.data
    } catch (err) {
      error.value = err.response?.data?.message || 'Error al obtener la sucursal'
      throw err
    } finally {
      loading.value = false
    }
  }

  const createBranch = async data => {
    try {
      loading.value = true
      error.value = null
      const response = await post('/api/branches', data)
      const created = response.data
      branches.value.unshift(created)
      return created
    } catch (err) {
      error.value = err.response?.data?.message || 'Error al crear la sucursal'
      throw err
    } finally {
      loading.value = false
    }
  }

  const updateBranch = async (id, data) => {
    try {
      loading.value = true
      error.value = null
      const response = await put(`/api/branches/${id}`, data)
      const updated = response.data
      const index = branches.value.findIndex(b => b.id === id)
      if (index !== -1) branches.value[index] = updated
      if (currentBranch.value?.id === id) currentBranch.value = updated
      return updated
    } catch (err) {
      error.value = err.response?.data?.message || 'Error al actualizar la sucursal'
      throw err
    } finally {
      loading.value = false
    }
  }

  const deleteBranch = async id => {
    try {
      loading.value = true
      error.value = null
      await del(`/api/branches/${id}`)
      const index = branches.value.findIndex(b => b.id === id)
      if (index !== -1) branches.value.splice(index, 1)
      return true
    } catch (err) {
      error.value = err.response?.data?.message || 'Error al eliminar la sucursal'
      throw err
    } finally {
      loading.value = false
    }
  }

  const toggleActive = async branch => {
    // Soft-toggle: reusa updateBranch con is_active invertido.
    return updateBranch(branch.id, { ...branch, is_active: !branch.is_active })
  }

  const clearError = () => {
    error.value = null
  }

  const reset = () => {
    branches.value = []
    currentBranch.value = null
    loading.value = false
    error.value = null
  }

  return {
    branches,
    currentBranch,
    loading,
    error,
    pagination,
    hasBranches,
    totalPages,
    currentPage,
    activeBranches,
    getBranches,
    getBranch,
    createBranch,
    updateBranch,
    deleteBranch,
    toggleActive,
    clearError,
    reset
  }
}
