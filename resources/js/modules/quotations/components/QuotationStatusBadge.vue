<template>
  <span :class="statusClasses">
    {{ statusMeta.label }}
  </span>
</template>

<script setup>
import { computed } from 'vue'
import { getStatusMeta } from '../quotationMeta'

const props = defineProps({
  status: {
    type: String,
    required: true
  }
})

// Legacy alias ramp kept for the un-migrated consumers of this badge.
const VARIANT_CLASSES = {
  neutral: 'bg-theme-surface text-theme-secondary',
  info: 'bg-primary-100 text-primary-700',
  success: 'bg-success-100 text-success-700',
  warning: 'bg-warning-100 text-warning-700',
  error: 'bg-error-100 text-error-700'
}

const statusMeta = computed(() => getStatusMeta(props.status))

const statusClasses = computed(() => [
  'inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium',
  VARIANT_CLASSES[statusMeta.value.variant] || VARIANT_CLASSES.neutral
])
</script>
