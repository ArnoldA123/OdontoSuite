<template>
  <div class="medical-record-card">
    <div class="card-header">
      <div class="flex justify-between items-start">
        <div>
          <h3 class="record-title">
            {{ record.record_number }}
          </h3>
          <p class="record-date">
            {{ formatDate(record.first_visit_date) }}
          </p>
        </div>
        <div class="record-status">
          <span :class="statusClasses">
            {{ statusLabel }}
          </span>
        </div>
      </div>
    </div>

    <div class="card-body">
      <div class="record-summary">
        <div class="summary-item">
          <span class="summary-label">Motivo de consulta:</span>
          <span class="summary-value">{{ record.chief_complaint || 'No especificado' }}</span>
        </div>

        <div v-if="record.diagnosis" class="summary-item">
          <span class="summary-label">Diagnóstico:</span>
          <span class="summary-value">{{ record.diagnosis }}</span>
        </div>

        <div v-if="record.treatment_plan" class="summary-item">
          <span class="summary-label">Plan de tratamiento:</span>
          <span class="summary-value">{{ record.treatment_plan }}</span>
        </div>
      </div>

      <div class="record-stats">
        <div class="stat-item">
          <span class="stat-label">Evoluciones:</span>
          <span class="stat-value">{{ record.evolutions?.length || 0 }}</span>
        </div>

        <div class="stat-item">
          <span class="stat-label">Adjuntos:</span>
          <span class="stat-value">{{ record.attachments?.length || 0 }}</span>
        </div>

        <div class="stat-item">
          <span class="stat-label">Creado:</span>
          <span class="stat-value">{{ formatDate(record.created_at) }}</span>
        </div>
      </div>

      <div v-if="record.notes" class="record-notes">
        <p class="text-sm text-theme-secondary line-clamp-2">
          {{ record.notes }}
        </p>
      </div>
    </div>

    <div class="card-footer">
      <div class="flex justify-between items-center">
        <div class="flex space-x-2">
          <button
            class="btn btn-sm btn-outline"
            title="Ver detalles"
            @click="$emit('view', record)"
          >
            <EyeIcon class="w-4 h-4" />
          </button>
          <button class="btn btn-sm btn-outline" title="Editar" @click="$emit('edit', record)">
            <PencilIcon class="w-4 h-4" />
          </button>
        </div>

        <div class="flex space-x-2">
          <button class="btn btn-sm btn-danger" title="Eliminar" @click="confirmDelete">
            <TrashIcon class="w-4 h-4" />
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import { useConfirm } from '@/composables/useConfirm'
import { EyeIcon, PencilIcon, TrashIcon } from '@heroicons/vue/24/outline'

const props = defineProps({
  record: {
    type: Object,
    required: true
  }
})

const emit = defineEmits(['view', 'edit', 'delete'])

// Computed
const statusClasses = computed(() => {
  const baseClasses = 'inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium'

  if (props.record.is_active) {
    return `${baseClasses} bg-success-100 text-success-700`
  } else {
    return `${baseClasses} bg-theme-surface text-theme-secondary`
  }
})

const statusLabel = computed(() => {
  return props.record.is_active ? 'Activa' : 'Inactiva'
})

// Métodos
const formatDate = date => {
  return new Date(date).toLocaleDateString('es-PE', {
    year: 'numeric',
    month: 'short',
    day: 'numeric'
  })
}

const confirmDelete = async () => {
  const ok = await confirm({
    title: 'Eliminar historia clínica',
    message: '¿Estás seguro de que quieres eliminar esta historia clínica?',
    confirmText: 'Eliminar',
    variant: 'danger'
  })
  if (ok) {
    emit('delete', props.record.id)
  }
}
</script>

<style scoped>
.medical-record-card {
  @apply bg-theme-surface-elevated rounded-lg border border-theme shadow-sm hover-lift transition-shadow;
}

.card-header {
  @apply p-4 border-b border-theme;
}

.record-title {
  @apply text-lg font-semibold text-theme-primary mb-1;
}

.record-date {
  @apply text-sm text-theme-secondary;
}

.record-status {
  @apply flex-shrink-0;
}

.card-body {
  @apply p-4;
}

.record-summary {
  @apply space-y-2 mb-4;
}

.summary-item {
  @apply flex flex-col space-y-1;
}

.summary-label {
  @apply text-xs font-medium text-theme-secondary uppercase tracking-wider;
}

.summary-value {
  @apply text-sm text-theme-primary;
}

.record-stats {
  @apply grid grid-cols-3 gap-4 mb-4;
}

.stat-item {
  @apply text-center;
}

.stat-label {
  @apply block text-xs text-theme-secondary;
}

.stat-value {
  @apply block text-sm font-semibold text-theme-primary;
}

.record-notes {
  @apply p-3 bg-theme-surface rounded-lg;
}

.card-footer {
  @apply p-4 border-t border-theme;
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

.btn-danger {
  @apply bg-error-100 text-red-700 hover:bg-red-200;
}
</style>
