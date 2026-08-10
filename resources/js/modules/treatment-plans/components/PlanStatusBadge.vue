<template>
  <span :class="statusClasses">
    {{ statusLabel }}
  </span>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  status: {
    type: String,
    required: true
  }
})

const statusClasses = computed(() => {
  const baseClasses = 'inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium'

  const statusMap = {
    draft: `${baseClasses} bg-theme-surface text-theme-secondary`,
    proposed: `${baseClasses} bg-primary-100 text-primary-800`,
    approved: `${baseClasses} bg-success-100 text-success-700`,
    in_progress: `${baseClasses} bg-warning-100 text-warning-700`,
    completed: `${baseClasses} bg-success-badge text-success-text`,
    cancelled: `${baseClasses} bg-error-100 text-error-700`
  }

  return statusMap[props.status] || `${baseClasses} bg-theme-surface text-theme-secondary`
})

const statusLabel = computed(() => {
  const labels = {
    draft: 'Borrador',
    proposed: 'Propuesto',
    approved: 'Aprobado',
    in_progress: 'En Progreso',
    completed: 'Completado',
    cancelled: 'Cancelado'
  }

  return labels[props.status] || props.status
})
</script>
