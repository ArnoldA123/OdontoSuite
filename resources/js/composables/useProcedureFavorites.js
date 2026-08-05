import { ref, computed } from 'vue'
import { useApi } from './useApi'

export function useProcedureFavorites () {
  const { get, post, delete: del, put } = useApi()

  const favorites = ref([])
  const forMe = ref([])
  const loading = ref(false)
  const error = ref(null)
  const pagination = ref({
    current_page: 1,
    last_page: 1,
    per_page: 20,
    total: 0
  })

  const hasFavorites = computed(() => favorites.value.length > 0)
  const isFavorite = id => favorites.value.some(f => f.id === id)

  const getFavorites = async () => {
    try {
      loading.value = true
      error.value = null
      const response = await get('/api/procedure-catalog-favorites')
      favorites.value = response.data || []
      return favorites.value
    } catch (err) {
      error.value = err.response?.data?.message || 'Error al obtener favoritos'
      favorites.value = []
      throw err
    } finally {
      loading.value = false
    }
  }

  const getForMe = async (filters = {}) => {
    try {
      loading.value = true
      error.value = null
      const params = new URLSearchParams()
      Object.entries(filters).forEach(([key, value]) => {
        if (value !== null && value !== undefined && value !== '') {
          params.append(key, value)
        }
      })
      const qs = params.toString()
      const response = await get(`/api/procedure-catalog/for-me${qs ? `?${qs}` : ''}`)
      forMe.value = response.data || []
      pagination.value = response.meta || pagination.value
      return forMe.value
    } catch (err) {
      error.value = err.response?.data?.message || 'Error al obtener procedimientos'
      forMe.value = []
      throw err
    } finally {
      loading.value = false
    }
  }

  const addFavorite = async procedureId => {
    try {
      loading.value = true
      error.value = null
      const response = await post(`/api/procedure-catalog/${procedureId}/favorite`)
      const created = response.data
      if (created)
        favorites.value.push({
          id: created.id,
          code: created.code,
          name: created.name,
          specialty: created.specialty,
          specialty_name: created.specialty_name,
          default_cost: created.default_cost,
          default_duration_minutes: created.default_duration_minutes,
          position: created.pivot?.position ?? favorites.value.length + 1
        })
      return created
    } catch (err) {
      error.value = err.response?.data?.message || 'Error al marcar como favorito'
      throw err
    } finally {
      loading.value = false
    }
  }

  const removeFavorite = async procedureId => {
    try {
      loading.value = true
      error.value = null
      await del(`/api/procedure-catalog/${procedureId}/favorite`)
      favorites.value = favorites.value.filter(f => f.id !== procedureId)
      return true
    } catch (err) {
      error.value = err.response?.data?.message || 'Error al quitar favorito'
      throw err
    } finally {
      loading.value = false
    }
  }

  const reorderFavorites = async orderedIds => {
    try {
      loading.value = true
      error.value = null
      const response = await put('/api/procedure-catalog-favorites/reorder', { ids: orderedIds })
      favorites.value = response.data || favorites.value
      return favorites.value
    } catch (err) {
      error.value = err.response?.data?.message || 'Error al reordenar favoritos'
      throw err
    } finally {
      loading.value = false
    }
  }

  const toggleFavorite = async procedure => {
    if (isFavorite(procedure.id)) {
      await removeFavorite(procedure.id)
      return false
    }
    await addFavorite(procedure.id)
    return true
  }

  const clearError = () => {
    error.value = null
  }

  return {
    favorites,
    forMe,
    loading,
    error,
    pagination,
    hasFavorites,
    isFavorite,
    getFavorites,
    getForMe,
    addFavorite,
    removeFavorite,
    reorderFavorites,
    toggleFavorite,
    clearError
  }
}
