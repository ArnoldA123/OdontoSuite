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
      specialties.value = response.data || []
      return specialties.value
    } catch (err) {
      error.value = err.response?.data?.message || 'Error al obtener especialidades'
      specialties.value = []
      throw err
    } finally {
      loading.value = false
    }
  }

  return {
    // Slice 08 / T-08.10 + T-08.11 canonical shape.
    specialties,
    data: specialties, // alias (T-08.10)
    loading,
    error,
    getSpecialties,

    // Slice 08 / T-08.11: refresh + retry aliases.
    refresh: getSpecialties,
    retry: getSpecialties,
  }
}
