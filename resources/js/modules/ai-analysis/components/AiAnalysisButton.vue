<template>
  <div class="relative inline-flex flex-col items-center gap-2 rounded-ios">
    <UiButton
      v-if="!hasAnalysis"
      variant="primary"
      size="sm"
      :disabled="loading || !canAnalyze"
      @click="startAnalysis"
    >
      <template #icon-left>
        <span
          v-if="loading"
          class="h-4 w-4 animate-spin rounded-full border-2 border-white/30 border-t-white"
          aria-hidden="true"
        />
        <CpuChipIcon v-else class="h-4 w-4" aria-hidden="true" />
      </template>
      {{ loading ? 'Analizando...' : 'Analizar con IA' }}
    </UiButton>

    <template v-else>
      <UiButton variant="secondary" size="sm" @click="viewAnalysis">
        <template #icon-left>
          <CpuChipIcon class="h-4 w-4" aria-hidden="true" />
        </template>
        <span class="text-label">{{ getStatusLabel(analysis.status) }}</span>
        <span v-if="analysis.confidence_score" class="text-xs font-medium text-theme-secondary">
          {{ analysis.confidence_score }}%
        </span>
      </UiButton>

      <UiStatusPill
        v-if="analysis.reviewed"
        :status="analysis.review_decision || 'unknown'"
        :variant="reviewDecisionVariant(analysis.review_decision)"
        size="sm"
      >
        {{ getReviewDecisionLabel(analysis.review_decision) }}
      </UiStatusPill>
    </template>
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

const emit = defineEmits(['analysisCompleted', 'viewAnalysis'])

const {
  loading,
  analysis,
  analyzeImage,
  getAnalysisByAttachment,
  getStatusLabel,
  getReviewDecisionLabel
} = useAiAnalysis()

const { can } = usePermissions()

const hasAnalysis = ref(false)

const canAnalyze = computed(() => {
  if (!can['ai-analysis.analyze']?.value) return false

  if (props.attachment) {
    return props.attachment.category === 'radiografia' && props.attachment.file_type === 'image'
  }

  return true
})

const reviewDecisionVariant = decision => {
  const variants = {
    accepted: 'success',
    rejected: 'error',
    partial: 'warning'
  }
  return variants[decision] || 'neutral'
}

const loadAnalysis = async () => {
  try {
    const existingAnalysis = await getAnalysisByAttachment(props.attachmentId)
    if (existingAnalysis) {
      analysis.value = existingAnalysis
      hasAnalysis.value = true
    }
  } catch {
    // Sin análisis previo se ofrece crear uno nuevo
  }
}

const startAnalysis = async () => {
  try {
    await analyzeImage(props.attachmentId)
    hasAnalysis.value = true
    emit('analysisCompleted', analysis.value)
  } catch {
    // El error ya se muestra vía toast en el composable
  }
}

const viewAnalysis = () => {
  emit('viewAnalysis', analysis.value)
}

onMounted(() => {
  loadAnalysis()
})
</script>
