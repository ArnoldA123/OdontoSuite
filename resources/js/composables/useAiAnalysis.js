import { ref, computed } from 'vue'
import { useApi } from './useApi'
import { useToast } from './useToast'

/**
 * Data layer for the AI analysis module.
 *
 * One notice per context: this composable never announces an error that a
 * consumer surface already paints. Reads rethrow so the page owns its retry
 * block, `uploadAndAnalyze` stays silent because `AiAnalysisSheet` renders the
 * inline message, and `getAnalysisByAttachment` still resolves `null` for "no
 * analysis yet". Only actions with no consumer error surface (`analyzeImage`,
 * `reviewAnalysis`, `voidAnalysis`) keep the error toast.
 */
export function useAiAnalysis() {
  const { get, post } = useApi()
  const { success, error } = useToast()

  const loading = ref(false)
  const analysis = ref(null)
  const analyses = ref([])
  const stats = ref(null)

  /**
   * Analizar imagen con IA (#296): the patient notice is part of the request
   * and the clinical act is optional.
   */
  const analyzeImage = async (attachmentId, options = {}) => {
    try {
      loading.value = true
      const response = await post(`/api/ai-analysis/analyze/${attachmentId}`, {
        notice_acknowledged: true,
        clinical_encounter_id: options.clinicalEncounterId ?? null
      })
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
    } finally {
      loading.value = false
    }
  }

  /**
   * Anular análisis conservando la traza (#296)
   */
  const voidAnalysis = async (id, reason) => {
    try {
      loading.value = true
      const response = await post(`/api/ai-analysis/${id}/void`, { reason })
      success('Análisis anulado exitosamente')

      // El análisis anulado sale de la lista local; su traza queda en el servidor.
      const index = analyses.value.findIndex(a => a.id === id)
      if (index > -1) {
        analyses.value.splice(index, 1)
      }

      return response.data
    } catch (err) {
      // A cited analysis is rejected with 422 and the reason in `errors`; the
      // generic `message` of a validation failure says nothing to the reader.
      const errors = err.response?.data?.errors
      const detail = errors && typeof errors === 'object' ? Object.values(errors).flat()[0] : null
      error(detail || err.response?.data?.message || 'Error al anular análisis')
      throw err
    } finally {
      loading.value = false
    }
  }

  /**
   * Subir imagen y analizar en un solo paso (#296): the declaration is always
   * sent and the clinical act travels only when the caller knows it.
   */
  const uploadAndAnalyze = async (
    patientId,
    file,
    description,
    category,
    clinicalEncounterId = null
  ) => {
    try {
      loading.value = true

      // Log de datos para debugging

      const formData = new FormData()
      formData.append('patient_id', parseInt(patientId))
      formData.append('image', file)
      formData.append('description', description || '')
      formData.append('category', category)
      formData.append('notice_acknowledged', '1')

      if (clinicalEncounterId) {
        formData.append('clinical_encounter_id', clinicalEncounterId)
      }

      // Slice 08 / FF-006: useApi.post() detects FormData and lets the
      // browser set Content-Type automatically. The `options.headers`
      // 3rd argument was silently ignored by useApi, so removing it here
      // makes the call signature honest.
      const response = await post('/api/ai-analysis/upload-and-analyze', formData)

      success('Análisis completado exitosamente')
      return response.data
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
    voidAnalysis,
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
