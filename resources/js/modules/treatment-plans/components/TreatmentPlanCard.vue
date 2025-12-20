<template>
  <div class="treatment-plan-card">
    <div class="card-header">
      <div class="flex justify-between items-start">
        <div>
          <h3 class="plan-title">{{ plan.title }}</h3>
          <p class="plan-number">{{ plan.plan_number }}</p>
        </div>
        <PlanStatusBadge :status="plan.status" />
      </div>
    </div>

    <div class="card-body">
      <div class="patient-info">
        <div class="flex items-center space-x-2">
          <UserIcon class="w-4 h-4 text-theme-secondary" />
          <span class="text-sm text-theme-primary">{{ plan.patient?.first_name }} {{ plan.patient?.last_name }}</span>
        </div>
      </div>

      <div class="plan-details">
        <div class="detail-row">
          <span class="detail-label">Procedimientos:</span>
          <span class="detail-value">{{ plan.items?.length || 0 }}</span>
        </div>
        <div class="detail-row">
          <span class="detail-label">Costo total:</span>
          <span class="detail-value font-semibold text-primary-600">S/ {{ formatPrice(plan.final_cost) }}</span>
        </div>
        <div class="detail-row">
          <span class="detail-label">Duración estimada:</span>
          <span class="detail-value">{{ plan.estimated_duration_weeks }} semanas</span>
        </div>
        <div class="detail-row">
          <span class="detail-label">Creado:</span>
          <span class="detail-value">{{ formatDate(plan.created_at) }}</span>
        </div>
      </div>

      <div v-if="plan.notes" class="plan-notes">
        <p class="text-sm text-theme-secondary line-clamp-2">{{ plan.notes }}</p>
      </div>
    </div>

    <div class="card-footer">
      <div class="flex justify-between items-center">
        <div class="flex space-x-2">
          <button
            @click="$emit('view', plan)"
            class="btn btn-sm btn-outline"
            title="Ver detalles"
          >
            <EyeIcon class="w-4 h-4" />
          </button>
          <button
            @click="$emit('edit', plan)"
            class="btn btn-sm btn-outline"
            title="Editar"
          >
            <PencilIcon class="w-4 h-4" />
          </button>
          <button
            @click="$emit('duplicate', plan)"
            class="btn btn-sm btn-outline"
            title="Duplicar"
          >
            <DocumentDuplicateIcon class="w-4 h-4" />
          </button>
        </div>

        <div class="flex space-x-2">
          <button
            v-if="canChangeStatus"
            @click="showStatusMenu = !showStatusMenu"
            class="btn btn-sm btn-secondary"
            title="Cambiar estado"
          >
            <ArrowPathIcon class="w-4 h-4" />
          </button>
          <button
            @click="confirmDelete"
            class="btn btn-sm btn-danger"
            title="Eliminar"
          >
            <TrashIcon class="w-4 h-4" />
          </button>
        </div>
      </div>

      <!-- Status menu -->
      <div v-if="showStatusMenu" class="status-menu">
        <div class="status-options">
          <button
            v-for="status in availableStatuses"
            :key="status.value"
            @click="changeStatus(status.value)"
            class="status-option"
          >
            {{ status.label }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue'
import { useAuth } from '@/composables/useAuth'
import PlanStatusBadge from './PlanStatusBadge.vue'
import {
  UserIcon,
  EyeIcon,
  PencilIcon,
  DocumentDuplicateIcon,
  ArrowPathIcon,
  TrashIcon
} from '@heroicons/vue/24/outline'

const props = defineProps({
  plan: {
    type: Object,
    required: true
  }
})

const emit = defineEmits(['view', 'edit', 'duplicate', 'change-status', 'delete'])

// Composables
const { user } = useAuth()

// Estado reactivo
const showStatusMenu = ref(false)

// Computed
const canChangeStatus = computed(() => {
  return user.value?.role === 'administrador' || user.value?.role === 'odontologo'
})

const availableStatuses = computed(() => {
  const statusMap = {
    draft: [
      { value: 'proposed', label: 'Propuesto' },
      { value: 'cancelled', label: 'Cancelado' }
    ],
    proposed: [
      { value: 'approved', label: 'Aprobado' },
      { value: 'cancelled', label: 'Cancelado' }
    ],
    approved: [
      { value: 'in_progress', label: 'En Progreso' },
      { value: 'cancelled', label: 'Cancelado' }
    ],
    in_progress: [
      { value: 'completed', label: 'Completado' },
      { value: 'cancelled', label: 'Cancelado' }
    ]
  }

  return statusMap[props.plan.status] || []
})

// Métodos
const formatPrice = (price) => {
  return new Intl.NumberFormat('es-PE', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2
  }).format(price || 0)
}

const formatDate = (date) => {
  return new Date(date).toLocaleDateString('es-PE', {
    year: 'numeric',
    month: 'short',
    day: 'numeric'
  })
}

const changeStatus = (status) => {
  showStatusMenu.value = false
  emit('change-status', props.plan.id, status)
}

const confirmDelete = () => {
  if (confirm('¿Estás seguro de que quieres eliminar este plan de tratamiento?')) {
    emit('delete', props.plan.id)
  }
}
</script>

<style scoped>
.treatment-plan-card {
  @apply bg-theme-surface-elevated rounded-lg border border-theme shadow-sm hover:shadow-md transition-shadow;
}

.card-header {
  @apply p-4 border-b border-theme;
}

.plan-title {
  @apply text-lg font-semibold text-theme-primary mb-1;
}

.plan-number {
  @apply text-sm text-theme-secondary;
}

.card-body {
  @apply p-4;
}

.patient-info {
  @apply mb-4;
}

.plan-details {
  @apply space-y-2 mb-4;
}

.detail-row {
  @apply flex justify-between items-center;
}

.detail-label {
  @apply text-sm text-theme-secondary;
}

.detail-value {
  @apply text-sm text-theme-primary;
}

.plan-notes {
  @apply mt-4 p-3 bg-theme-surface rounded-lg;
}

.card-footer {
  @apply p-4 border-t border-theme relative;
}

.status-menu {
  @apply absolute bottom-full left-0 right-0 mb-2 bg-theme-surface-elevated border border-theme rounded-lg shadow-lg z-10;
}

.status-options {
  @apply p-2;
}

.status-option {
  @apply w-full px-3 py-2 text-left text-sm text-theme-primary hover:bg-theme-surface rounded;
}

.btn {
  @apply inline-flex items-center justify-center px-3 py-2 border border-transparent text-sm font-medium rounded-md transition-colors;
}

.btn-sm {
  @apply px-2 py-1 text-xs;
}

.btn-outline {
  @apply border-theme text-theme-primary bg-theme-surface-elevated hover:bg-theme-surface;
}

.btn-secondary {
  @apply bg-theme-surface text-theme-primary hover:bg-theme-surface-elevated;
}

.btn-danger {
  @apply bg-red-100 text-red-700 hover:bg-red-200;
}
</style>
