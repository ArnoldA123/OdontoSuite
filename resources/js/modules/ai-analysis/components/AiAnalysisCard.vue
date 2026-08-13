<template>
  <div class="analysis-card">
    <!-- Header -->
    <div class="card-header">
      <div class="patient-info">
        <h3 class="patient-name">
          {{ analysis.patient?.full_name || 'Paciente' }}
        </h3>
        <p class="analysis-date">
          {{ formatDate(analysis.created_at) }}
        </p>
      </div>
      <div class="status-badge" :class="statusClass">
        {{ getStatusLabel(analysis.status) }}
      </div>
    </div>

    <!-- Image Preview -->
    <div class="image-preview" @click="$emit('view', analysis)">
      <img
        v-if="analysis.clinical_attachment?.file_path"
        :src="getImageUrl(analysis.clinical_attachment.file_path)"
        :alt="analysis.clinical_attachment.original_name"
        class="preview-image"
      />
      <div v-else class="no-image">
        <PhotoIcon class="w-8 h-8 text-theme-secondary" />
      </div>
    </div>

    <!-- Analysis Info -->
    <div class="analysis-info">
      <div v-if="analysis.findings && analysis.findings.length > 0" class="findings-section">
        <h4 class="section-title">Hallazgos Principales</h4>
        <div class="findings-list">
          <div v-for="(finding, index) in mainFindings" :key="index" class="finding-item">
            <div class="finding-header">
              <span class="finding-diagnosis">{{ finding.diagnosis }}</span>
              <span class="confidence-badge" :class="getConfidenceClass(finding.confidence)">
                {{ finding.confidence }}%
              </span>
            </div>
            <p class="finding-location">
              {{ finding.location }}
            </p>
          </div>
        </div>
      </div>

      <div
        v-if="analysis.recommendations && analysis.recommendations.length > 0"
        class="recommendations-section"
      >
        <h4 class="section-title">Recomendaciones</h4>
        <ul class="recommendations-list">
          <li
            v-for="(recommendation, index) in mainRecommendations"
            :key="index"
            class="recommendation-item"
          >
            {{ recommendation }}
          </li>
        </ul>
      </div>

      <div v-if="analysis.reviewed" class="review-section">
        <div class="review-info">
          <span class="review-decision" :class="getReviewDecisionClass(analysis.review_decision)">
            {{ getReviewDecisionLabel(analysis.review_decision) }}
          </span>
          <span class="review-date">{{ formatDate(analysis.reviewed_at) }}</span>
        </div>
        <p v-if="analysis.review_notes" class="review-notes">
          {{ analysis.review_notes }}
        </p>
      </div>
    </div>

    <!-- Actions -->
    <div class="card-actions">
      <button class="action-btn action-btn-primary" @click="$emit('view', analysis)">
        <EyeIcon class="w-4 h-4 mr-2" />
        Ver Detalles
      </button>

      <button
        v-if="!analysis.reviewed && analysis.status === 'completed'"
        class="action-btn action-btn-secondary"
        @click="$emit('review', analysis)"
      >
        <CheckCircleIcon class="w-4 h-4 mr-2" />
        Revisar
      </button>

      <button
        v-if="!analysis.reviewed"
        class="action-btn action-btn-danger"
        @click="$emit('delete', analysis)"
      >
        <TrashIcon class="w-4 h-4" />
      </button>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import { useAiAnalysis } from '@/composables/useAiAnalysis'
import { PhotoIcon, EyeIcon, CheckCircleIcon, TrashIcon } from '@heroicons/vue/24/outline'

const props = defineProps({
  analysis: {
    type: Object,
    required: true
  }
})

const emit = defineEmits(['view', 'review', 'delete'])

const { getStatusLabel, getReviewDecisionLabel, getConfidenceColor, formatDate } = useAiAnalysis()

// Computed
const mainFindings = computed(() => {
  if (!props.analysis.findings) return []
  return props.analysis.findings.slice(0, 2) // Primeros 2 hallazgos
})

const mainRecommendations = computed(() => {
  if (!props.analysis.recommendations) return []
  return props.analysis.recommendations.slice(0, 2) // Primeras 2 recomendaciones
})

const statusClass = computed(() => {
  const { status } = props.analysis
  const classes = {
    pending: 'bg-warning-badge',
    processing: 'bg-primary-50 text-primary-700',
    completed: 'bg-success-badge',
    failed: 'bg-danger-badge'
  }
  return classes[status] || 'bg-theme-surface text-theme-secondary'
})

// Methods
const getImageUrl = filePath => {
  return `/storage/${filePath}`
}

const getConfidenceClass = confidence => {
  if (confidence >= 90) return 'bg-success-badge'
  if (confidence >= 70) return 'bg-warning-badge'
  return 'bg-danger-badge'
}

const getReviewDecisionClass = decision => {
  const classes = {
    accepted: 'bg-success-badge',
    rejected: 'bg-danger-badge',
    partial: 'bg-warning-badge'
  }
  return classes[decision] || 'bg-theme-surface text-theme-secondary'
}
</script>

<style scoped>
.analysis-card {
  @apply bg-theme-surface-elevated border border-theme rounded-lg overflow-hidden hover-lift transition-shadow;
}

.card-header {
  @apply flex items-center justify-between p-4 border-b border-theme;
}

.patient-info {
  @apply flex-1;
}

.patient-name {
  @apply text-lg font-medium text-theme-primary;
}

.analysis-date {
  @apply text-sm text-theme-secondary;
}

.status-badge {
  @apply px-3 py-1 text-xs font-medium rounded-full;
}

.image-preview {
  @apply w-full h-48 bg-theme-surface cursor-pointer overflow-hidden;
}

.preview-image {
  @apply w-full h-full object-cover hover:scale-105 transition-transform;
}

.no-image {
  @apply w-full h-full flex items-center justify-center;
}

.analysis-info {
  @apply p-4 space-y-4;
}

.findings-section,
.recommendations-section {
  @apply space-y-2;
}

.section-title {
  @apply text-sm font-medium text-theme-primary;
}

.findings-list {
  @apply space-y-2;
}

.finding-item {
  @apply p-3 bg-theme-surface rounded-lg;
}

.finding-header {
  @apply flex items-center justify-between mb-1;
}

.finding-diagnosis {
  @apply text-sm font-medium text-theme-primary;
}

.confidence-badge {
  @apply px-2 py-1 text-xs font-medium rounded-full;
}

.finding-location {
  @apply text-xs text-theme-secondary;
}

.recommendations-list {
  @apply space-y-1;
}

.recommendation-item {
  @apply text-sm text-theme-primary;
}

.review-section {
  @apply p-3 bg-primary-50 rounded-lg;
}

.review-info {
  @apply flex items-center justify-between mb-2;
}

.review-decision {
  @apply px-2 py-1 text-xs font-medium rounded-full;
}

.review-date {
  @apply text-xs text-theme-secondary;
}

.review-notes {
  @apply text-sm text-theme-primary italic;
}

.card-actions {
  @apply flex items-center justify-between p-4 border-t border-theme bg-theme-surface;
}

.action-btn {
  @apply inline-flex items-center px-3 py-2 text-sm font-medium rounded-lg transition-colors;
}

.action-btn-primary {
  @apply bg-primary-600 text-white hover:bg-primary-700;
}

.action-btn-secondary {
  @apply bg-primary-500 text-white hover:bg-primary-600;
}

.action-btn-danger {
  @apply bg-error-600 text-white hover:bg-error-700;
}
</style>
