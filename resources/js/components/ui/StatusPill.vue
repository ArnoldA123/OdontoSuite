<template>
  <span :class="pillClasses" :aria-label="ariaLabel" role="status">
    <span
      v-if="showDot"
      class="inline-block w-1.5 h-1.5 rounded-full"
      :class="dotClasses"
      aria-hidden="true"
    />
    <slot>{{ displayLabel }}</slot>
  </span>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  status: {
    type: String,
    required: true
  },
  variant: {
    type: String,
    default: null,
    validator: value =>
      value === null || ['info', 'success', 'warning', 'error', 'neutral'].includes(value)
  },
  size: {
    type: String,
    default: 'md',
    validator: value => ['sm', 'md'].includes(value)
  },
  showDot: {
    type: Boolean,
    default: false
  }
})

const STATUS_MAP = {
  scheduled: { variant: 'info', label: 'Programado' },
  confirmed: { variant: 'info', label: 'Confirmado' },
  in_consultation: { variant: 'info', label: 'En consulta' },
  completed: { variant: 'success', label: 'Completado' },
  in_progress: { variant: 'info', label: 'En progreso' },
  cancelled: { variant: 'error', label: 'Cancelado' },
  no_show: { variant: 'error', label: 'No asistió' },
  draft: { variant: 'neutral', label: 'Borrador' },
  proposed: { variant: 'neutral', label: 'Propuesto' },
  approved: { variant: 'success', label: 'Aprobado' },
  pending: { variant: 'warning', label: 'Pendiente' }
}

// iOS filled pattern (Decision 7): 100 background + 600 text.
const VARIANT_CLASSES = {
  info: 'bg-systemBlue-100 text-systemBlue-700 border border-systemBlue-100',
  success: 'bg-systemGreen-100 text-systemGreen-700 border border-systemGreen-100',
  warning: 'bg-systemYellow-100 text-systemYellow-700 border border-systemYellow-100',
  error: 'bg-systemRed-100 text-systemRed-700 border border-systemRed-100',
  neutral: 'bg-systemGray-100 text-systemGray-600 border border-systemGray-100'
}

const VARIANT_DOT = {
  info: 'bg-systemBlue-500',
  success: 'bg-systemGreen-500',
  warning: 'bg-systemYellow-500',
  error: 'bg-systemRed-500',
  neutral: 'bg-systemGray-500'
}

const SIZE_CLASSES = {
  sm: 'px-2 py-0.5 text-xs min-h-[20px]',
  md: 'px-2.5 py-0.5 text-xs min-h-[22px]'
}

const resolvedVariant = computed(() => {
  if (props.variant) return props.variant
  return STATUS_MAP[props.status]?.variant ?? 'neutral'
})

const displayLabel = computed(() => {
  return STATUS_MAP[props.status]?.label ?? props.status
})

const ariaLabel = computed(() => `Estado: ${displayLabel.value}`)

const pillClasses = computed(() =>
  [
    'inline-flex items-center gap-1.5 rounded-full font-medium',
    'transition-colors duration-200 select-none',
    SIZE_CLASSES[props.size],
    VARIANT_CLASSES[resolvedVariant.value]
  ].join(' ')
)

const dotClasses = computed(() => VARIANT_DOT[resolvedVariant.value])
</script>
