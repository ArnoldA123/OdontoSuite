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

const VARIANT_CLASSES = {
  info: 'bg-primary-50 text-primary-700 border border-primary-100',
  success: 'bg-success-badge border border-success-100',
  warning: 'bg-warning-badge border border-warning-100',
  error: 'bg-danger-badge border border-error-100',
  neutral: 'bg-theme-surface text-theme-secondary border border-theme'
}

const VARIANT_DOT = {
  info: 'bg-primary-500',
  success: 'bg-success-500',
  warning: 'bg-warning-500',
  error: 'bg-error-500',
  neutral: 'bg-theme-secondary'
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
