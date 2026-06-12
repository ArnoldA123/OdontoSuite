<template>
  <div class="specialty-record-card">
    <div class="card-header">
      <div class="card-title">
        <h3 class="title">{{ record.title }}</h3>
        <span class="specialty-badge">{{ getSpecialtyLabel(record.specialty) }}</span>
      </div>
      <div class="card-actions">
        <button
          @click="$emit('view', record)"
          class="action-btn"
          title="Ver detalles"
        >
          <EyeIcon class="w-4 h-4" />
        </button>
        <button
          @click="$emit('edit', record)"
          class="action-btn"
          title="Editar"
          v-if="canEdit"
        >
          <PencilIcon class="w-4 h-4" />
        </button>
        <button
          @click="$emit('delete', record)"
          class="action-btn action-btn-danger"
          title="Eliminar"
          v-if="canDelete"
        >
          <TrashIcon class="w-4 h-4" />
        </button>
      </div>
    </div>

    <div class="card-content">
      <!-- Información del paciente -->
      <div class="patient-info">
        <div class="patient-name">
          <UserIcon class="w-4 h-4 text-theme-secondary" />
          <span>{{ record.patient?.first_name }} {{ record.patient?.last_name }}</span>
        </div>
        <div class="patient-dni" v-if="record.patient?.dni">
          <span class="text-sm text-theme-secondary">{{ record.patient.dni }}</span>
        </div>
      </div>

      <!-- Descripción -->
      <div class="description" v-if="record.description">
        <p class="description-text">{{ record.description }}</p>
      </div>

      <!-- Información específica por especialidad -->
      <div class="specialty-info">
        <!-- Implantología -->
        <div v-if="record.specialty === 'implantology'" class="specialty-details">
          <div class="detail-item" v-if="record.implant_count">
            <span class="detail-label">Implantes:</span>
            <span class="detail-value">{{ record.implant_count }}</span>
          </div>
          <div class="detail-item" v-if="record.implant_type">
            <span class="detail-label">Tipo:</span>
            <span class="detail-value">{{ record.implant_type }}</span>
          </div>
        </div>

        <!-- Ortodoncia -->
        <div v-else-if="record.specialty === 'orthodontics'" class="specialty-details">
          <div class="detail-item" v-if="record.treatment_type">
            <span class="detail-label">Tratamiento:</span>
            <span class="detail-value">{{ getTreatmentTypeLabel(record.treatment_type) }}</span>
          </div>
          <div class="detail-item" v-if="record.estimated_duration">
            <span class="detail-label">Duración:</span>
            <span class="detail-value">{{ record.estimated_duration }} meses</span>
          </div>
        </div>

        <!-- Endodoncia -->
        <div v-else-if="record.specialty === 'endodontics'" class="specialty-details">
          <div class="detail-item" v-if="record.tooth_number">
            <span class="detail-label">Diente:</span>
            <span class="detail-value">{{ record.tooth_number }}</span>
          </div>
          <div class="detail-item" v-if="record.canal_count">
            <span class="detail-label">Conductos:</span>
            <span class="detail-value">{{ record.canal_count }}</span>
          </div>
        </div>

        <!-- Rehabilitación -->
        <div v-else-if="record.specialty === 'rehabilitation'" class="specialty-details">
          <div class="detail-item" v-if="record.prosthesis_type">
            <span class="detail-label">Prótesis:</span>
            <span class="detail-value">{{ getProsthesisTypeLabel(record.prosthesis_type) }}</span>
          </div>
          <div class="detail-item" v-if="record.material">
            <span class="detail-label">Material:</span>
            <span class="detail-value">{{ record.material }}</span>
          </div>
        </div>

        <!-- Cirugía Oral -->
        <div v-else-if="record.specialty === 'oral_surgery'" class="specialty-details">
          <div class="detail-item" v-if="record.surgery_type">
            <span class="detail-label">Cirugía:</span>
            <span class="detail-value">{{ getSurgeryTypeLabel(record.surgery_type) }}</span>
          </div>
          <div class="detail-item" v-if="record.anesthesia">
            <span class="detail-label">Anestesia:</span>
            <span class="detail-value">{{ record.anesthesia }}</span>
          </div>
        </div>
      </div>

      <!-- Información adicional -->
      <div class="additional-info">
        <div class="info-item" v-if="record.professional_name">
          <span class="info-label">Profesional:</span>
          <span class="info-value">{{ record.professional_name }}</span>
        </div>
        <div class="info-item" v-if="record.procedure_date">
          <span class="info-label">Fecha:</span>
          <span class="info-value">{{ formatDate(record.procedure_date) }}</span>
        </div>
      </div>
    </div>

    <div class="card-footer">
      <div class="footer-left">
        <span class="created-date">
          Creado {{ formatDate(record.created_at) }}
        </span>
      </div>
      <div class="footer-right">
        <span class="status-indicator" :class="getStatusClass(record.status)">
          {{ getStatusLabel(record.status) }}
        </span>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import { usePermissions } from '@/composables/usePermissions'
import {
  EyeIcon,
  PencilIcon,
  TrashIcon,
  UserIcon
} from '@heroicons/vue/24/outline'

const props = defineProps({
  record: {
    type: Object,
    required: true
  }
})

const emit = defineEmits(['view', 'edit', 'delete'])

const { can } = usePermissions()

// Computed
const canEdit = computed(() => {
  return can('specialty-records.update')
})

const canDelete = computed(() => {
  return can('specialty-records.delete')
})

// Métodos
const formatDate = (date) => {
  if (!date) return 'N/A'
  return new Date(date).toLocaleDateString('es-ES', {
    year: 'numeric',
    month: 'short',
    day: 'numeric'
  })
}

const getSpecialtyLabel = (specialty) => {
  const labels = {
    implantology: 'Implantología',
    orthodontics: 'Ortodoncia',
    endodontics: 'Endodoncia',
    rehabilitation: 'Rehabilitación',
    oral_surgery: 'Cirugía Oral'
  }
  return labels[specialty] || specialty
}

const getTreatmentTypeLabel = (type) => {
  const labels = {
    fixed: 'Aparatología Fija',
    removable: 'Aparatología Removible',
    invisible: 'Ortodoncia Invisible'
  }
  return labels[type] || type
}

const getProsthesisTypeLabel = (type) => {
  const labels = {
    crown: 'Corona',
    bridge: 'Puente',
    denture: 'Dentadura',
    implant_prosthesis: 'Prótesis sobre Implante'
  }
  return labels[type] || type
}

const getSurgeryTypeLabel = (type) => {
  const labels = {
    extraction: 'Extracción',
    wisdom_tooth: 'Muela del Juicio',
    implant_placement: 'Colocación de Implante',
    biopsy: 'Biopsia'
  }
  return labels[type] || type
}

const getStatusLabel = (status) => {
  const labels = {
    active: 'Activo',
    completed: 'Completado',
    pending: 'Pendiente',
    cancelled: 'Cancelado'
  }
  return labels[status] || status
}

const getStatusClass = (status) => {
  const classes = {
    active: 'status-active',
    completed: 'status-completed',
    pending: 'status-pending',
    cancelled: 'status-cancelled'
  }
  return classes[status] || 'status-default'
}
</script>

<style scoped>
.specialty-record-card {
  @apply bg-theme-surface-elevated border border-theme rounded-lg shadow-sm hover-lift transition-shadow;
}

.card-header {
  @apply flex items-center justify-between p-4 border-b border-theme;
}

.card-title {
  @apply flex items-center space-x-3;
}

.title {
  @apply text-lg font-medium text-theme-primary;
}

.specialty-badge {
  @apply px-2 py-1 text-xs font-medium bg-primary-50 text-primary-700 rounded-full;
}

.card-actions {
  @apply flex items-center space-x-1;
}

.action-btn {
  @apply p-2 text-theme-secondary hover:text-theme-primary transition-colors;
}

.action-btn-danger {
  @apply hover:text-red-600;
}

.card-content {
  @apply p-4 space-y-4;
}

.patient-info {
  @apply flex items-center justify-between;
}

.patient-name {
  @apply flex items-center space-x-2 text-sm font-medium text-theme-primary;
}

.patient-dni {
  @apply text-sm text-theme-secondary;
}

.description {
  @apply text-sm text-theme-primary;
}

.description-text {
  @apply line-clamp-2;
}

.specialty-info {
  @apply space-y-2;
}

.specialty-details {
  @apply flex flex-wrap gap-2;
}

.detail-item {
  @apply flex items-center space-x-1 text-xs;
}

.detail-label {
  @apply text-theme-secondary;
}

.detail-value {
  @apply text-theme-primary font-medium;
}

.additional-info {
  @apply space-y-1;
}

.info-item {
  @apply flex items-center justify-between text-xs;
}

.info-label {
  @apply text-theme-secondary;
}

.info-value {
  @apply text-theme-primary;
}

.card-footer {
  @apply flex items-center justify-between p-4 border-t border-theme bg-theme-surface;
}

.footer-left {
  @apply text-xs text-theme-secondary;
}

.footer-right {
  @apply flex items-center;
}

.status-indicator {
  @apply px-2 py-1 text-xs font-medium rounded-full;
}

.status-active {
  @apply bg-success-badge;
}

.status-completed {
  @apply bg-primary-50 text-primary-700;
}

.status-pending {
  @apply bg-warning-badge;
}

.status-cancelled {
  @apply bg-danger-badge;
}

.status-default {
  @apply bg-theme-surface text-theme-secondary;
}
</style>
