<template>
  <StatusBadge :variant="variant" :show-dot="showDot" :label="statusLabel" />
</template>

<script setup>
import { computed } from 'vue'
import StatusBadge from '@/components/ui/StatusBadge.vue'

const props = defineProps({
  status: {
    type: String,
    required: true
  }
})

// One status vocabulary for the whole app: the module maps its domain states
// onto the shared primitive variants instead of re-declaring colour aliases.
const VARIANT_BY_STATUS = {
  draft: 'neutral',
  proposed: 'info',
  approved: 'success',
  in_progress: 'warning',
  completed: 'success',
  cancelled: 'error'
}

const LABEL_BY_STATUS = {
  draft: 'Borrador',
  proposed: 'Propuesto',
  approved: 'Aprobado',
  in_progress: 'En Progreso',
  completed: 'Completado',
  cancelled: 'Cancelado'
}

const variant = computed(() => VARIANT_BY_STATUS[props.status] ?? 'neutral')

const statusLabel = computed(() => LABEL_BY_STATUS[props.status] ?? props.status)

const showDot = computed(() => props.status === 'completed')
</script>
