<template>
  <div
    class="treatment-plan-card"
    :class="cardClasses"
    @click="$emit('view', plan)"
  >
    <div class="card-header">
      <div class="flex justify-between items-start gap-2">
        <div class="min-w-0">
          <h3 class="plan-title truncate">{{ plan.title }}</h3>
          <p class="plan-number">{{ plan.plan_number }}</p>
        </div>
        <div class="flex items-center gap-1 shrink-0">
          <span v-if="plan.is_overdue" class="overdue-badge" title="Plan vencido">
            Vencido
          </span>
          <PlanStatusBadge :status="plan.status" />
        </div>
      </div>
    </div>

    <div class="card-body">
      <div class="patient-info">
        <div class="flex items-center space-x-2">
          <UserIcon class="w-4 h-4 text-theme-secondary shrink-0" />
          <span class="text-sm text-theme-primary truncate">
            {{ plan.patient?.first_name }} {{ plan.patient?.last_name }}
          </span>
        </div>
      </div>

      <div class="plan-details">
        <div class="detail-row">
          <span class="detail-label">Procedimientos:</span>
          <span class="detail-value">{{ totalItems }}</span>
        </div>
        <div class="detail-row">
          <span class="detail-label">Costo total:</span>
          <span class="detail-value font-semibold text-primary-600">
            S/ {{ formatPrice(plan.final_cost) }}
          </span>
        </div>
        <div class="detail-row">
          <span class="detail-label">Duración estimada:</span>
          <span class="detail-value">{{ plan.estimated_duration_weeks || '—' }} semanas</span>
        </div>
        <div class="detail-row">
          <span class="detail-label">Creado:</span>
          <span class="detail-value">{{ formatDate(plan.created_at) }}</span>
        </div>
      </div>

      <div v-if="progress && totalItems > 0" class="progress-wrap">
        <div class="progress-bar" :aria-label="`Progreso: ${progress.progress_percentage}%`">
          <div
            class="progress-fill"
            :class="progressFillClass"
            :style="{ width: progress.progress_percentage + '%' }"
          />
        </div>
        <p class="progress-text">
          {{ progress.completed_items }}/{{ progress.total_items }} completados
          <span class="text-theme-secondary">·</span>
          <span class="text-theme-secondary">{{ progress.progress_percentage }}%</span>
        </p>
      </div>

      <div v-if="plan.notes" class="plan-notes">
        <p class="text-sm text-theme-secondary line-clamp-2">{{ plan.notes }}</p>
      </div>
    </div>

    <div class="card-footer" @click.stop>
      <div class="flex justify-between items-center gap-2">
        <div class="flex space-x-1">
          <button
            @click="$emit('view', plan)"
            class="btn btn-sm btn-outline"
            title="Ver detalles"
            aria-label="Ver detalles"
          >
            <EyeIcon class="w-4 h-4" />
          </button>
          <button
            @click="$emit('edit', plan)"
            class="btn btn-sm btn-outline"
            title="Editar"
            aria-label="Editar"
            :disabled="!canEdit"
          >
            <PencilIcon class="w-4 h-4" />
          </button>
          <button
            @click="$emit('duplicate', plan)"
            class="btn btn-sm btn-outline"
            title="Duplicar"
            aria-label="Duplicar"
          >
            <DocumentDuplicateIcon class="w-4 h-4" />
          </button>
        </div>

        <div class="flex space-x-1 relative">
          <button
            v-if="canChangeStatus && availableStatuses.length"
            @click="showStatusMenu = !showStatusMenu"
            class="btn btn-sm btn-secondary"
            :title="`Cambiar estado (actual: ${plan.status})`"
            aria-label="Cambiar estado"
            aria-haspopup="menu"
            :aria-expanded="showStatusMenu"
          >
            <ArrowPathIcon class="w-4 h-4" />
          </button>
          <button
            @click="confirmDelete"
            class="btn btn-sm btn-danger"
            title="Eliminar"
            aria-label="Eliminar"
            :disabled="!canDelete"
          >
            <TrashIcon class="w-4 h-4" />
          </button>

          <div
            v-if="showStatusMenu"
            class="status-menu"
            role="menu"
            @click.stop
          >
            <div class="status-options">
              <button
                v-for="status in availableStatuses"
                :key="status.value"
                @click="changeStatus(status.value)"
                class="status-option"
                role="menuitem"
              >
                {{ status.label }}
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { useAuth } from '@/composables/useAuth'
import PlanStatusBadge from './PlanStatusBadge.vue'
import {
  UserIcon,
  EyeIcon,
  PencilIcon,
  DocumentDuplicateIcon,
  ArrowPathIcon,
  TrashIcon,
} from '@heroicons/vue/24/outline'

const props = defineProps({
  plan: {
    type: Object,
    required: true,
  },
})

const emit = defineEmits(['view', 'edit', 'duplicate', 'change-status', 'delete'])

const { user } = useAuth()
const showStatusMenu = ref(false)

const canChangeStatus = computed(() =>
  ['administrador', 'odontologo', 'implantologo'].includes(user.value?.role)
)

const canEdit = computed(() => {
  if (['administrador', 'odontologo', 'implantologo'].includes(user.value?.role)) {
    return true
  }
  return ['draft', 'proposed'].includes(props.plan.status)
})

const canDelete = computed(() => {
  if (!['administrador'].includes(user.value?.role)) return false
  return props.plan.status === 'draft'
})

const totalItems = computed(() => props.plan.progress?.total_items ?? props.plan.items?.length ?? 0)
const progress = computed(() => props.plan.progress ?? null)

const progressFillClass = computed(() => {
  const pct = progress.value?.progress_percentage ?? 0
  if (pct >= 100) return 'is-complete'
  if (pct >= 60) return 'is-high'
  if (pct >= 30) return 'is-mid'
  return 'is-low'
})

const cardClasses = computed(() => ({
  'is-overdue': props.plan.is_overdue,
  'is-completed': props.plan.status === 'completed',
  'is-cancelled': props.plan.status === 'cancelled',
}))

const availableStatuses = computed(() => {
  const map = {
    draft: [
      { value: 'proposed', label: 'Propuesto' },
      { value: 'cancelled', label: 'Cancelado' },
    ],
    proposed: [
      { value: 'approved', label: 'Aprobado' },
      { value: 'cancelled', label: 'Cancelado' },
    ],
    approved: [
      { value: 'in_progress', label: 'En Progreso' },
      { value: 'cancelled', label: 'Cancelado' },
    ],
    in_progress: [
      { value: 'completed', label: 'Completado' },
      { value: 'cancelled', label: 'Cancelado' },
    ],
  }
  return map[props.plan.status] || []
})

const formatPrice = (price) =>
  new Intl.NumberFormat('es-PE', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  }).format(price || 0)

const formatDate = (date) => {
  if (!date) return '—'
  return new Date(date).toLocaleDateString('es-PE', {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
  })
}

const changeStatus = (status) => {
  showStatusMenu.value = false
  emit('change-status', props.plan.id, status)
}

const confirmDelete = () => {
  const ok = window.confirm(
    `¿Eliminar el plan "${props.plan.title}" (${props.plan.plan_number})?\n\nEsta acción no se puede deshacer.`
  )
  if (ok) emit('delete', props.plan.id)
}

const handleClickOutside = (e) => {
  if (showStatusMenu.value && !e.target.closest('.status-menu')) {
    showStatusMenu.value = false
  }
}

onMounted(() => document.addEventListener('click', handleClickOutside))
onUnmounted(() => document.removeEventListener('click', handleClickOutside))
</script>

<style scoped>
.treatment-plan-card {
  @apply bg-theme-surface-elevated rounded-lg border border-theme shadow-sm hover-lift transition-all cursor-pointer;
  border-left-width: 4px;
}

.treatment-plan-card.is-overdue {
  border-left-color: rgb(220 38 38);
}

.treatment-plan-card.is-completed {
  border-left-color: rgb(16 185 129);
  opacity: 0.85;
}

.treatment-plan-card.is-cancelled {
  border-left-color: rgb(156 163 175);
  opacity: 0.7;
}

.card-header {
  @apply p-4 border-b border-theme;
}

.plan-title {
  @apply text-lg font-semibold text-theme-primary mb-1;
}

.plan-number {
  @apply text-xs text-theme-secondary font-mono;
}

.card-body {
  @apply p-4 space-y-3;
}

.patient-info,
.plan-details {
  @apply space-y-2;
}

.detail-row {
  @apply flex justify-between items-center text-sm;
}

.detail-label {
  @apply text-theme-secondary;
}

.detail-value {
  @apply text-theme-primary;
}

.progress-wrap {
  @apply pt-1;
}

.progress-bar {
  @apply h-1.5 w-full bg-theme-surface rounded-full overflow-hidden;
}

.progress-fill {
  @apply h-full transition-all duration-300;
  background-color: rgb(229 231 235);
}

.progress-fill.is-low {
  background-color: rgb(248 113 113);
}
.progress-fill.is-mid {
  background-color: rgb(251 191 36);
}
.progress-fill.is-high {
  background-color: rgb(34 197 94);
}
.progress-fill.is-complete {
  background-color: rgb(16 185 129);
}

.progress-text {
  @apply text-xs text-theme-secondary mt-1;
}

.plan-notes {
  @apply p-2 bg-theme-surface rounded text-sm;
}

.card-footer {
  @apply p-3 border-t border-theme;
}

.overdue-badge {
  @apply inline-flex items-center px-2 py-0.5 rounded text-xs font-medium;
  background-color: rgb(254 226 226);
  color: rgb(185 28 28);
}

.status-menu {
  @apply absolute bottom-full right-0 mb-2 bg-theme-surface-elevated border border-theme rounded-lg shadow-lg z-20 min-w-[160px];
}

.status-options {
  @apply p-1;
}

.status-option {
  @apply w-full px-3 py-2 text-left text-sm text-theme-primary hover:bg-theme-surface rounded transition-colors;
}

.btn {
  @apply inline-flex items-center justify-center px-2 py-1.5 border border-transparent text-xs font-medium rounded-md transition-colors disabled:opacity-50 disabled:cursor-not-allowed;
}

.btn-outline {
  @apply border-theme text-theme-primary bg-theme-surface-elevated hover:bg-theme-surface;
}

.btn-secondary {
  @apply bg-theme-surface text-theme-primary hover:bg-theme-surface-elevated;
}

.btn-danger {
  background-color: rgb(254 226 226);
  color: rgb(185 28 28);
}
.btn-danger:hover {
  background-color: rgb(252 165 165);
}
.btn-danger:disabled {
  @apply opacity-30;
}
</style>
