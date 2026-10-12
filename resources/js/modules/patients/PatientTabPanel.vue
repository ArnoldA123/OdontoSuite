<template>
  <div :data-patient-tab-panel="state" :data-tab="state" class="space-y-4">
    <div v-if="$slots.header" class="flex items-center justify-between gap-3">
      <slot name="header" />
    </div>
    <UiCard variant="glass">
      <LoadingSpinner v-if="loading" size="lg" :text="loadingText" class="py-8" />
      <UiSectionEmpty
        v-else-if="error"
        :data-state="errorState || `error-${state}`"
        :title="errorTitle"
        :description="errorDescription"
      >
        <UiButton variant="secondary" size="sm" data-tab-retry @click="emit('retry')">
          Reintentar
        </UiButton>
      </UiSectionEmpty>
      <UiSectionEmpty
        v-else-if="empty"
        :data-state="emptyState || `empty-${state}`"
        :title="emptyTitle"
        :description="emptyDescription"
      >
        <slot name="empty-action" />
      </UiSectionEmpty>
      <slot v-else />
    </UiCard>
  </div>
</template>

<script setup>
import UiCard from '../../components/ui/Card.vue'
import UiButton from '../../components/ui/Button.vue'
import LoadingSpinner from '../../components/ui/LoadingSpinner.vue'
import UiSectionEmpty from '../../components/ui/UiSectionEmpty.vue'

defineProps({
  state: { type: String, required: true },
  loading: { type: Boolean, default: false },
  loadingText: { type: String, default: '' },
  error: { type: Boolean, default: false },
  errorState: { type: String, default: '' },
  errorTitle: { type: String, default: '' },
  errorDescription: { type: String, default: '' },
  empty: { type: Boolean, default: false },
  emptyState: { type: String, default: '' },
  emptyTitle: { type: String, default: '' },
  emptyDescription: { type: String, default: '' }
})

const emit = defineEmits(['retry'])
</script>
