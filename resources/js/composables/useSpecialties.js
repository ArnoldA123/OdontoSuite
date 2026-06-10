import { ref } from 'vue'
import { useApi } from './useApi'

export function useSpecialties () {
  const { get } = useApi()
  const specialties = ref([])
  const loading = ref(false)
  const error = ref(null)

  const getSpecialties = async (activeOnly = false) => {
    try {
      loading.value = true
      error.value = null
      const url = activeOnly ? '/api/specialties/active' : '/api/specialties'
      const response = await get(url)
      specialties.value = response.data?.data || []
      return specialties.value
    } catch (err) {
      error.value = err.response?.data?.message || 'Error al obtener especialidades'
      specialties.value = []
      throw err
    } finally {
      loading.value = false
    }
  }

  return { specialties, loading, error, getSpecialties }
}
