import { ref, computed } from 'vue'
import { useApi } from './useApi.js'

export function useApiWithLoading() {
  const { get, post, put, patch, delete: del, setToken } = useApi()
  const loading = ref(false)
  const error = ref(null)
  const loadingStates = ref({})

  const setLoading = (state, key = 'default') => {
    if (key === 'default') {
      loading.value = state
    } else {
      loadingStates.value[key] = state
    }
  }

  const isLoading = (key = 'default') => {
    if (key === 'default') {
      return loading.value
    }
    return loadingStates.value[key] || false
  }

  const setError = (err) => {
    error.value = err
  }

  const clearError = () => {
    error.value = null
  }

  const withLoading = async (asyncFn, loadingKey = 'default') => {
    try {
      setLoading(true, loadingKey)
      clearError()
      const result = await asyncFn()
      return result
    } catch (err) {
      setError(err.response?.data?.message || err.message || 'Error desconocido')
      throw err
    } finally {
      setLoading(false, loadingKey)
    }
  }

  const apiGet = async (url, options = {}, loadingKey = 'default') => {
    return withLoading(() => get(url, options), loadingKey)
  }

  const apiPost = async (url, data, loadingKey = 'default') => {
    return withLoading(() => post(url, data), loadingKey)
  }

  const apiPut = async (url, data, loadingKey = 'default') => {
    return withLoading(() => put(url, data), loadingKey)
  }

  const apiPatch = async (url, data, loadingKey = 'default') => {
    return withLoading(() => patch(url, data), loadingKey)
  }

  const apiDelete = async (url, loadingKey = 'default') => {
    return withLoading(() => del(url), loadingKey)
  }

  const hasAnyLoading = computed(() => {
    return loading.value || Object.values(loadingStates.value).some(state => state)
  })

  const clearAllLoading = () => {
    loading.value = false
    loadingStates.value = {}
  }

  return {
    // Loading states
    loading: computed(() => loading.value),
    loadingStates: computed(() => loadingStates.value),
    isLoading,
    hasAnyLoading,
    setLoading,
    clearAllLoading,

    // Error handling
    error: computed(() => error.value),
    setError,
    clearError,

    // API methods with loading
    get: apiGet,
    post: apiPost,
    put: apiPut,
    patch: apiPatch,
    delete: apiDelete,

    // Utility
    withLoading,
    setToken
  }
}
