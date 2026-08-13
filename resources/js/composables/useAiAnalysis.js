import { ref, computed } from 'vue'
import { useApi } from './useApi'
import { useToast } from './useToast'

export function useAiAnalysis() {
  const { get, post, del } = useApi()
  const { success, error } = useToast()

  const loading = ref(false)
  const analysis = ref(null)
  const analyses = ref([])
  const stats = ref(null)

  /**
   * Analizar imagen con IA
   */
  const analyzeImage = async attachmentId => {
    try {
      loading.value = true
      const response = await post(`/api/ai-analysis/analyze/${attachmentId}`)
      analysis.value = response.data
      success('Análisis solicitado exitosamente')
      return response.data
    } catch (err) {
      error(err.response?.data?.message || 'Error al analizar imagen')
      throw err
    } finally {
      loading.value = false
    }
  }

  /**
   * Obtener análisis por adjunto
   */
  const getAnalysisByAttachment = async attachmentId => {
    try {
      loading.value = true
      const response = await get(`/api/ai-analysis/attachment/${attachmentId}`)
      return response.data
    } catch (err) {
      return null
    } finally {
      loading.value = false
    }
  }

  /**
   * Obtener análisis por paciente
   */
  const getPatientAnalyses = async (patientId, filters = {}) => {
    try {
      loading.value = true
      const params = new URLSearchParams(filters).toString()
      const response = await get(`/api/ai-analysis/patient/${patientId}?${params}`)
      analyses.value = response.data
      return response
    } catch (err) {
      error('Error al obtener análisis del paciente')
      throw err
    } finally {
      loading.value = false
    }
  }

  /**
   * Obtener análisis pendientes de revisión
   */
  const getPendingAnalyses = async (filters = {}) => {
    try {
      loading.value = true
      const params = new URLSearchParams(filters).toString()
      const response = await get(`/api/ai-analysis/pending?${params}`)
      analyses.value = response.data
      return response
    } catch (err) {
      error('Error al obtener análisis pendientes')
      throw err
    } finally {
      loading.value = false
    }
  }

  /**
   * Obtener lista de análisis con filtros
   */
  const getAnalyses = async (filters = {}) => {
    try {
      loading.value = true
      const params = new URLSearchParams(filters).toString()
      const response = await get(`/api/ai-analysis?${params}`)
      analyses.value = response.data
      return response
    } catch (err) {
      error('Error al obtener análisis')
      throw err
    } finally {
      loading.value = false
    }
  }

  /**
   * Obtener análisis específico
   */
  const getAnalysis = async id => {
    try {
      loading.value = true
      const response = await get(`/api/ai-analysis/${id}`)
      analysis.value = response.data
      return response.data
    } catch (err) {
      error('Error al obtener análisis')
      throw err
    } finally {
      loading.value = false
    }
  }

  /**
   * Revisar análisis (aceptar/rechazar)
   */
  const reviewAnalysis = async (analysisId, decision, notes = null) => {
    try {
      loading.value = true
      const response = await post(`/api/ai-analysis/${analysisId}/review`, {
        decision,
        notes
      })
      analysis.value = response.data
      success('Análisis revisado exitosamente')
      return response.data
    } catch (err) {
      error(err.response?.data?.message || 'Error al revisar análisis')
      throw err
    } finally {
      loading.value = false
    }
  }

  /**
   * Obtener estadísticas de uso de IA
   */
  const getStats = async (filters = {}) => {
    try {
      loading.value = true
      const params = new URLSearchParams(filters).toString()
      const response = await get(`/api/ai-analysis/stats?${params}`)
      stats.value = response.data
      return response.data
    } catch (err) {
      error('Error al obtener estadísticas')
      throw err
    } finally {
      loading.value = false
    }
  }

  /**
   * Eliminar análisis
   */
  const deleteAnalysis = async id => {
    try {
      loading.value = true
      await del(`/api/ai-analysis/${id}`)
      success('Análisis eliminado exitosamente')

      // Remover de la lista local
      const index = analyses.value.findIndex(a => a.id === id)
      if (index > -1) {
        analyses.value.splice(index, 1)
      }
    } catch (err) {
      error(err.response?.data?.message || 'Error al eliminar análisis')
      throw err
    } finally {
      loading.value = false
    }
  }

  /**
   * Subir imagen y analizar en un solo paso
   */
  const uploadAndAnalyze = async (patientId, file, description, category) => {
    try {
      loading.value = true

      // Log de datos para debugging

      const formData = new FormData()
      formData.append('patient_id', parseInt(patientId))
      formData.append('image', file)
      formData.append('description', description || '')
      formData.append('category', category)

      // Slice 08 / FF-006: useApi.post() detects FormData and lets the
      // browser set Content-Type automatically. The `options.headers`
      // 3rd argument was silently ignored by useApi, so removing it here
      // makes the call signature honest.
      const response = await post('/api/ai-analysis/upload-and-analyze', formData)

      success('Análisis completado exitosamente')
      return response.data
    } catch (err) {
      error(err.response?.data?.message || 'Error al analizar la imagen')
      throw err
    } finally {
      loading.value = false
    }
  }

  /**
   * Computed properties
   */
  const hasAnalysis = computed(() => analysis.value !== null)
  const isAnalyzing = computed(() => loading.value && analysis.value?.status === 'processing')
  const isCompleted = computed(() => analysis.value?.status === 'completed')
  const isFailed = computed(() => analysis.value?.status === 'failed')
  const isReviewed = computed(() => analysis.value?.reviewed === true)
  const isPendingReview = computed(
    () => analysis.value?.status === 'completed' && !analysis.value?.reviewed
  )

  /**
   * Helper methods
   */
  const getConfidenceColor = score => {
    if (score >= 90) return 'green'
    if (score >= 70) return 'yellow'
    return 'red'
  }

  const getConfidenceLabel = score => {
    if (score >= 90) return 'Alta'
    if (score >= 70) return 'Media'
    return 'Baja'
  }

  const getStatusLabel = status => {
    const labels = {
      pending: 'Pendiente',
      processing: 'Procesando',
      completed: 'Completado',
      failed: 'Fallido'
    }
    return labels[status] || status
  }

  const getReviewDecisionLabel = decision => {
    const labels = {
      accepted: 'Aceptado',
      rejected: 'Rechazado',
      partial: 'Parcial'
    }
    return labels[decision] || 'Sin revisar'
  }

  const formatDate = date => {
    if (!date) return 'N/A'
    return new Date(date).toLocaleDateString('es-ES', {
      year: 'numeric',
      month: 'short',
      day: 'numeric',
      hour: '2-digit',
      minute: '2-digit'
    })
  }

  return {
    // Slice 08 / T-08.10 + T-08.11 canonical shape.
    loading,
    analysis,
    analyses,
    data: analyses, // alias (T-08.10) — primary collection
    stats,

    // Actions
    analyzeImage,
    getAnalysisByAttachment,
    getPatientAnalyses,
    getPendingAnalyses,
    getAnalyses,
    getAnalysis,
    reviewAnalysis,
    getStats,
    deleteAnalysis,
    uploadAndAnalyze,

    // Computed
    hasAnalysis,
    isAnalyzing,
    isCompleted,
    isFailed,
    isReviewed,
    isPendingReview,

    // Helpers
    getConfidenceColor,
    getConfidenceLabel,
    getStatusLabel,
    getReviewDecisionLabel,
    formatDate,

    // Slice 08 / T-08.11: refresh + retry aliases.
    refresh: getAnalyses,
    retry: getAnalyses
  }
}
