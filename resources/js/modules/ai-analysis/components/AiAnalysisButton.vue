<template>
  <div class="ai-analysis-button">
    <!-- Button to start analysis -->
    <button
      v-if="!hasAnalysis"
      :disabled="loading || !canAnalyze"
      class="analysis-btn"
      :class="{
        'btn-primary': canAnalyze,
        'btn-disabled': !canAnalyze
      }"
      @click="startAnalysis"
    >
      <CpuChipIcon class="w-4 h-4 mr-2" />
      <span v-if="loading">Analizando...</span>
      <span v-else>Analizar con IA</span>
    </button>

    <!-- Analysis status badge -->
    <div v-else class="analysis-status">
      <button class="status-btn" :class="getStatusClass(analysis.status)" @click="viewAnalysis">
        <CpuChipIcon class="w-4 h-4 mr-2" />
        <span class="status-text">{{ getStatusLabel(analysis.status) }}</span>
        <span v-if="analysis.confidence_score" class="confidence-score">
          {{ analysis.confidence_score }}%
        </span>
      </button>

      <!-- Review status indicator -->
      <div v-if="analysis.reviewed" class="review-indicator">
        <span class="review-badge" :class="getReviewDecisionClass(analysis.review_decision)">
          {{ getReviewDecisionLabel(analysis.review_decision) }}
        </span>
      </div>
    </div>

    <!-- Loading spinner -->
    <div v-if="loading" class="loading-overlay">
      <div class="loading-spinner" />
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useAiAnalysis } from '@/composables/useAiAnalysis'
import { usePermissions } from '@/composables/usePermissions'
import { CpuChipIcon } from '@heroicons/vue/24/outline'

const props = defineProps({
  attachmentId: {
    type: [Number, String],
    required: true
  },
  attachment: {
    type: Object,
    default: null
  }
})

const emit = defineEmits(['analysis-completed', 'view-analysis'])

const {
  loading,
  analysis,
  analyzeImage,
  getAnalysisByAttachment,
  getStatusLabel,
  getReviewDecisionLabel,
  getConfidenceColor
} = useAiAnalysis()

const { can } = usePermissions()

// State
const hasAnalysis = ref(false)

// Computed
const canAnalyze = computed(() => {
  // Check if user can analyze with AI
  if (!can('ai-analysis.analyze')) return false

  // Check if attachment is a radiography
  if (props.attachment) {
    return props.attachment.category === 'radiografia' && props.attachment.file_type === 'image'
  }

  return true
})

// Methods
const loadAnalysis = async () => {
  try {
    const existingAnalysis = await getAnalysisByAttachment(props.attachmentId)
    if (existingAnalysis) {
      analysis.value = existingAnalysis
      hasAnalysis.value = true
    }
  } catch (error) {}
}

const startAnalysis = async () => {
  try {
    await analyzeImage(props.attachmentId)
    hasAnalysis.value = true
    emit('analysis-completed', analysis.value)
  } catch (error) {}
}

const viewAnalysis = () => {
  emit('view-analysis', analysis.value)
}

const getStatusClass = status => {
  const classes = {
    pending: 'status-pending',
    processing: 'status-processing',
    completed: 'status-completed',
    failed: 'status-failed'
  }
  return classes[status] || 'status-unknown'
}

const getReviewDecisionClass = decision => {
  const classes = {
    accepted: 'review-accepted',
    rejected: 'review-rejected',
    partial: 'review-partial'
  }
  return classes[decision] || 'review-unknown'
}

// Lifecycle
onMounted(() => {
  loadAnalysis()
})
</script>

<style scoped>
.ai-analysis-button {
  @apply relative inline-block;
}

.analysis-btn {
  @apply inline-flex items-center px-3 py-2 text-sm font-medium rounded-lg transition-colors;
}

.btn-primary {
  @apply bg-primary-600 text-white hover:bg-primary-700;
}

.btn-disabled {
  @apply bg-theme-surface text-theme-secondary cursor-not-allowed;
}

.analysis-status {
  @apply flex flex-col items-center space-y-2;
}

.status-btn {
  @apply inline-flex items-center px-3 py-2 text-sm font-medium rounded-lg transition-colors;
}

.status-pending {
  @apply bg-warning-badge;
}

.status-processing {
  @apply bg-primary-50 text-primary-700;
}

.status-completed {
  @apply bg-success-badge;
}

.status-failed {
  @apply bg-danger-badge;
}

.status-unknown {
  @apply bg-theme-surface text-theme-secondary hover:bg-theme-surface-elevated;
}

.status-text {
  @apply mr-2;
}

.confidence-score {
  @apply ml-2 px-2 py-1 text-xs font-medium bg-theme-surface-elevated rounded-full;
  opacity: 0.8;
}

.review-indicator {
  @apply mt-1;
}

.review-badge {
  @apply px-2 py-1 text-xs font-medium rounded-full;
}

.review-accepted {
  @apply bg-success-badge;
}

.review-rejected {
  @apply bg-danger-badge;
}

.review-partial {
  @apply bg-warning-badge;
}

.review-unknown {
  @apply bg-theme-surface text-theme-secondary;
}

.loading-overlay {
  @apply absolute inset-0 flex items-center justify-center bg-theme-surface-elevated rounded-lg;
  opacity: 0.9;
}

.loading-spinner {
  @apply animate-spin rounded-full h-5 w-5 border-b-2 border-primary-600;
}
</style>
