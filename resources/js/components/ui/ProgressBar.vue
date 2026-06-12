<template>
  <div class="progress-wrap">
    <div v-if="showLabel" class="progress-text" aria-hidden="true">
      {{ Math.round(clampedValue) }}% completado
    </div>
    <div
      class="progress-bar"
      role="progressbar"
      :aria-valuenow="Math.round(clampedValue)"
      aria-valuemin="0"
      aria-valuemax="100"
      :aria-label="ariaLabel"
    >
      <div class="progress-fill" :class="fillClasses" :style="{ width: `${clampedValue}%` }" />
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  value: {
    type: Number,
    required: true,
    validator: v => Number.isFinite(v)
  },
  thresholds: {
    type: Object,
    default: () => ({ low: 30, mid: 60, high: 90 })
  },
  showLabel: {
    type: Boolean,
    default: true
  },
  label: {
    type: String,
    default: 'Progreso'
  }
})

const clampedValue = computed(() => {
  const v = Number(props.value)
  if (Number.isNaN(v)) return 0
  return Math.max(0, Math.min(100, v))
})

const fillTone = computed(() => {
  const v = clampedValue.value
  const t = props.thresholds
  if (v < t.low) return 'low'
  if (v < t.mid) return 'mid'
  if (v < t.high) return 'high'
  return 'complete'
})

const fillClasses = computed(() => `is-${fillTone.value}`)

const ariaLabel = computed(() => `${props.label}: ${Math.round(clampedValue.value)}%`)
</script>

<style scoped>
.progress-wrap {
  @apply w-full;
}

.progress-bar {
  @apply h-1.5 w-full rounded-full overflow-hidden;
  background-color: var(--color-surface);
}

.progress-fill {
  @apply h-full transition-all duration-500 ease-out;
}

.progress-fill.is-low {
  background-color: var(--color-danger);
}

.progress-fill.is-mid {
  background-color: var(--color-warning);
}

.progress-fill.is-high {
  background-color: var(--color-success-light);
}

.progress-fill.is-complete {
  background-color: var(--color-success);
}

.progress-text {
  @apply text-xs mb-1;
  color: var(--color-text-secondary);
}

@media (prefers-reduced-motion: reduce) {
  .progress-fill {
    transition: none;
  }
}
</style>
