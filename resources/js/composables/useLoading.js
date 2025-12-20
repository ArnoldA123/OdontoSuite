import { ref } from 'vue'

export function useLoading() {
  const loading = ref(false)
  const loadingMessage = ref('')

  const setLoading = (value, message = '') => {
    loading.value = value
    loadingMessage.value = message
  }

  const withLoading = async (fn, message = 'Cargando...') => {
    setLoading(true, message)
    try {
      const result = await fn()
      return result
    } finally {
      setLoading(false)
    }
  }

  return {
    loading,
    loadingMessage,
    setLoading,
    withLoading
  }
}
