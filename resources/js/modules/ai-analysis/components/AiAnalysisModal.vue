<template>
  <div class="modal-overlay" @click="$emit('close')">
    <div class="modal-container" @click.stop>
      <!-- Header -->
      <div class="modal-header">
        <div class="header-content">
          <h2 class="modal-title">
            <CpuChipIcon class="w-6 h-6 text-primary-600 mr-2" />
            Análisis de IA
          </h2>
          <p class="modal-subtitle">
            {{ analysis.patient?.full_name || 'Paciente' }} - {{ formatDate(analysis.created_at) }}
          </p>
        </div>
        <button class="close-btn" @click="$emit('close')">
          <XMarkIcon class="w-6 h-6" />
        </button>
      </div>

      <!-- Content -->
      <div class="modal-content">
        <div class="content-grid">
          <!-- Image Section -->
          <div class="image-section">
            <div class="image-container">
              <img
                v-if="analysis.clinical_attachment?.file_path"
                :src="getImageUrl(analysis.clinical_attachment.file_path)"
                :alt="analysis.clinical_attachment.original_name"
                class="analysis-image"
              />
              <div v-else class="no-image">
                <PhotoIcon class="w-16 h-16 text-theme-secondary" />
                <p class="no-image-text">Imagen no disponible</p>
              </div>
            </div>

            <div class="image-info">
              <h4 class="info-title">Información de la Imagen</h4>
              <div class="info-grid">
                <div class="info-item">
                  <span class="info-label">Archivo:</span>
                  <span class="info-value">
                    {{ analysis.clinical_attachment?.original_name || 'N/A' }}
                  </span>
                </div>
                <div class="info-item">
                  <span class="info-label">Tipo:</span>
                  <span class="info-value">
                    {{ analysis.clinical_attachment?.category || 'N/A' }}
                  </span>
                </div>
                <div class="info-item">
                  <span class="info-label">Modelo IA:</span>
                  <span class="info-value">{{ analysis.model_used }}</span>
                </div>
                <div class="info-item">
                  <span class="info-label">Confianza General:</span>
                  <span
                    class="confidence-score"
                    :class="getConfidenceClass(analysis.confidence_score)"
                  >
                    {{ analysis.confidence_score }}%
                  </span>
                </div>
              </div>
            </div>
          </div>

          <!-- Analysis Section -->
          <div class="analysis-section">
            <!-- Status -->
            <div class="status-section">
              <div class="status-header">
                <h3 class="section-title">Estado del Análisis</h3>
                <span class="status-badge" :class="getStatusClass(analysis.status)">
                  {{ getStatusLabel(analysis.status) }}
                </span>
              </div>

              <div v-if="analysis.status === 'processing'" class="processing-info">
                <div class="processing-spinner" />
                <p class="processing-text">Procesando imagen con IA...</p>
              </div>
            </div>

            <!-- Findings -->
            <div v-if="analysis.findings && analysis.findings.length > 0" class="findings-section">
              <h3 class="section-title">Hallazgos Clínicos</h3>
              <div class="findings-list">
                <div
                  v-for="(finding, index) in analysis.findings"
                  :key="index"
                  class="finding-card"
                >
                  <div class="finding-header">
                    <h4 class="finding-diagnosis">
                      {{ finding.diagnosis }}
                    </h4>
                    <div class="finding-meta">
                      <span
                        class="confidence-badge"
                        :class="getConfidenceClass(finding.confidence)"
                      >
                        {{ finding.confidence }}%
                      </span>
                      <span class="severity-badge" :class="getSeverityClass(finding.severity)">
                        {{ getSeverityLabel(finding.severity) }}
                      </span>
                    </div>
                  </div>
                  <div class="finding-details">
                    <p class="finding-location">
                      <MapPinIcon class="w-4 h-4 mr-1" />
                      {{ finding.location }}
                    </p>
                    <p class="finding-description">
                      {{ finding.description }}
                    </p>
                  </div>
                </div>
              </div>
            </div>

            <!-- Recommendations -->
            <div
              v-if="analysis.recommendations && analysis.recommendations.length > 0"
              class="recommendations-section"
            >
              <h3 class="section-title">Recomendaciones de Tratamiento</h3>
              <ul class="recommendations-list">
                <li
                  v-for="(recommendation, index) in analysis.recommendations"
                  :key="index"
                  class="recommendation-item"
                >
                  <CheckCircleIcon class="w-5 h-5 text-green-600 mr-2 flex-shrink-0" />
                  {{ recommendation }}
                </li>
              </ul>
            </div>

            <!-- Review Section -->
            <div v-if="analysis.reviewed" class="review-section">
              <h3 class="section-title">Revisión del Odontólogo</h3>
              <div class="review-content">
                <div class="review-decision">
                  <span class="decision-label">Decisión:</span>
                  <span
                    class="decision-badge"
                    :class="getReviewDecisionClass(analysis.review_decision)"
                  >
                    {{ getReviewDecisionLabel(analysis.review_decision) }}
                  </span>
                </div>
                <div class="review-meta">
                  <span class="reviewer">
                    Revisado por: {{ analysis.reviewed_by?.name || 'N/A' }}
                  </span>
                  <span class="review-date">{{ formatDate(analysis.reviewed_at) }}</span>
                </div>
                <div v-if="analysis.review_notes" class="review-notes">
                  <h4 class="notes-title">Notas del Odontólogo:</h4>
                  <p class="notes-content">
                    {{ analysis.review_notes }}
                  </p>
                </div>
              </div>
            </div>

            <!-- Review Form (if not reviewed) -->
            <div v-if="!analysis.reviewed && analysis.status === 'completed'" class="review-form">
              <h3 class="section-title">Revisar Análisis</h3>
              <form class="form" @submit.prevent="submitReview">
                <div class="form-group">
                  <label class="form-label">Decisión</label>
                  <select v-model="reviewForm.decision" class="form-select" required>
                    <option value="">Seleccionar decisión</option>
                    <option value="accepted">Aceptar hallazgos</option>
                    <option value="rejected">Rechazar hallazgos</option>
                    <option value="partial">Aceptar parcialmente</option>
                  </select>
                </div>

                <div class="form-group">
                  <label class="form-label">Notas (opcional)</label>
                  <textarea
                    v-model="reviewForm.notes"
                    class="form-textarea"
                    rows="3"
                    placeholder="Agregar comentarios sobre el análisis..."
                  />
                </div>

                <div class="form-actions">
                  <button type="button" class="btn btn-secondary" @click="$emit('close')">
                    Cancelar
                  </button>
                  <button type="submit" class="btn btn-primary" :disabled="!reviewForm.decision">
                    Guardar Revisión
                  </button>
                </div>
              </form>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive } from 'vue'
import { useAiAnalysis } from '@/composables/useAiAnalysis'
import {
  CpuChipIcon,
  XMarkIcon,
  PhotoIcon,
  MapPinIcon,
  CheckCircleIcon
} from '@heroicons/vue/24/outline'

const props = defineProps({
  analysis: {
    type: Object,
    required: true
  }
})

const emit = defineEmits(['close', 'review'])

const { getStatusLabel, getReviewDecisionLabel, getConfidenceColor, formatDate } = useAiAnalysis()

// Review form
const reviewForm = reactive({
  decision: '',
  notes: ''
})

// Methods
const getImageUrl = filePath => {
  return `/storage/${filePath}`
}

const getStatusClass = status => {
  const classes = {
    pending: 'bg-warning-100 text-warning-700',
    processing: 'bg-primary-100 text-primary-800',
    completed: 'bg-success-100 text-success-700',
    failed: 'bg-error-100 text-error-700'
  }
  return classes[status] || 'bg-theme-surface text-theme-secondary'
}

const getConfidenceClass = confidence => {
  if (confidence >= 90) return 'bg-success-100 text-success-700'
  if (confidence >= 70) return 'bg-warning-100 text-warning-700'
  return 'bg-error-100 text-error-700'
}

const getSeverityClass = severity => {
  const classes = {
    leve: 'bg-success-100 text-success-700',
    moderado: 'bg-warning-100 text-warning-700',
    severo: 'bg-error-100 text-error-700'
  }
  return classes[severity] || 'bg-theme-surface text-theme-secondary'
}

const getSeverityLabel = severity => {
  const labels = {
    leve: 'Leve',
    moderado: 'Moderado',
    severo: 'Severo'
  }
  return labels[severity] || severity
}

const getReviewDecisionClass = decision => {
  const classes = {
    accepted: 'bg-success-100 text-success-700',
    rejected: 'bg-error-100 text-error-700',
    partial: 'bg-warning-100 text-warning-700'
  }
  return classes[decision] || 'bg-theme-surface text-theme-secondary'
}

const submitReview = () => {
  emit('review', props.analysis.id, reviewForm.decision, reviewForm.notes)
}
</script>

<style scoped>
.modal-overlay {
  @apply fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4;
}

.modal-container {
  @apply bg-theme-surface-elevated rounded-lg shadow-xl max-w-6xl w-full max-h-[90vh] overflow-hidden;
}

.modal-header {
  @apply flex items-center justify-between p-6 border-b border-theme;
}

.header-content {
  @apply flex-1;
}

.modal-title {
  @apply text-xl font-bold text-theme-primary flex items-center;
}

.modal-subtitle {
  @apply text-theme-secondary mt-1;
}

.close-btn {
  @apply p-2 text-theme-secondary hover:text-theme-primary transition-colors;
}

.modal-content {
  @apply p-6 overflow-y-auto max-h-[calc(90vh-120px)];
}

.content-grid {
  @apply grid grid-cols-1 lg:grid-cols-2 gap-6;
}

.image-section {
  @apply space-y-4;
}

.image-container {
  @apply w-full h-64 bg-theme-surface rounded-lg overflow-hidden;
}

.analysis-image {
  @apply w-full h-full object-cover;
}

.no-image {
  @apply w-full h-full flex flex-col items-center justify-center;
}

.no-image-text {
  @apply text-theme-secondary mt-2;
}

.image-info {
  @apply bg-theme-surface rounded-lg p-4;
}

.info-title {
  @apply text-lg font-medium text-theme-primary mb-3;
}

.info-grid {
  @apply grid grid-cols-1 gap-2;
}

.info-item {
  @apply flex justify-between items-center;
}

.info-label {
  @apply text-sm font-medium text-theme-primary;
}

.info-value {
  @apply text-sm text-theme-primary;
}

.confidence-score {
  @apply px-2 py-1 text-sm font-medium rounded-full;
}

.analysis-section {
  @apply space-y-6;
}

.status-section {
  @apply bg-theme-surface rounded-lg p-4;
}

.status-header {
  @apply flex items-center justify-between mb-2;
}

.section-title {
  @apply text-lg font-medium text-theme-primary;
}

.status-badge {
  @apply px-3 py-1 text-sm font-medium rounded-full;
}

.processing-info {
  @apply flex items-center space-x-3 mt-3;
}

.processing-spinner {
  @apply animate-spin rounded-full h-5 w-5 border-b-2 border-primary-600;
}

.processing-text {
  @apply text-sm text-theme-secondary;
}

.findings-section,
.recommendations-section,
.review-section {
  @apply space-y-4;
}

.findings-list {
  @apply space-y-3;
}

.finding-card {
  @apply bg-theme-surface-elevated border border-theme rounded-lg p-4;
}

.finding-header {
  @apply flex items-center justify-between mb-2;
}

.finding-diagnosis {
  @apply text-base font-medium text-theme-primary;
}

.finding-meta {
  @apply flex items-center space-x-2;
}

.confidence-badge,
.severity-badge {
  @apply px-2 py-1 text-xs font-medium rounded-full;
}

.finding-details {
  @apply space-y-2;
}

.finding-location {
  @apply flex items-center text-sm text-theme-secondary;
}

.finding-description {
  @apply text-sm text-theme-primary;
}

.recommendations-list {
  @apply space-y-2;
}

.recommendation-item {
  @apply flex items-start text-sm text-theme-primary;
}

.review-content {
  @apply bg-primary-50 rounded-lg p-4 space-y-3;
}

.review-decision {
  @apply flex items-center space-x-2;
}

.decision-label {
  @apply text-sm font-medium text-theme-primary;
}

.decision-badge {
  @apply px-2 py-1 text-sm font-medium rounded-full;
}

.review-meta {
  @apply flex items-center justify-between text-sm text-theme-secondary;
}

.review-notes {
  @apply space-y-2;
}

.notes-title {
  @apply text-sm font-medium text-theme-primary;
}

.notes-content {
  @apply text-sm text-theme-primary italic;
}

.review-form {
  @apply bg-warning-50 rounded-lg p-4;
}

.form {
  @apply space-y-4;
}

.form-group {
  @apply space-y-2;
}

.form-label {
  @apply text-sm font-medium text-theme-primary;
}

.form-select,
.form-textarea {
  @apply w-full px-3 py-2 border border-theme rounded-lg text-sm focus:ring-2 focus:ring-primary-500 focus:border-accent bg-theme-surface-elevated text-theme-primary;
}

.form-actions {
  @apply flex items-center justify-end space-x-3;
}

.btn {
  @apply inline-flex items-center px-4 py-2 text-sm font-medium rounded-lg transition-colors;
}

.btn-primary {
  @apply bg-primary-600 text-white hover:bg-primary-700 disabled:opacity-50;
}

.btn-secondary {
  @apply bg-theme-surface text-theme-primary hover:bg-theme-surface-elevated;
}
</style>
