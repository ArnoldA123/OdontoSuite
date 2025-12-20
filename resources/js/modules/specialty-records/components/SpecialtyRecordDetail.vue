<template>
  <div class="specialty-record-detail">
    <div class="detail-header">
      <div class="detail-title">
        <h2 class="text-xl font-semibold text-theme-primary">
          {{ record.title }}
        </h2>
        <div class="detail-meta">
          <span class="specialty-badge">{{ getSpecialtyLabel(record.specialty) }}</span>
          <span class="text-sm text-theme-secondary">
            {{ formatDate(record.created_at) }}
          </span>
        </div>
      </div>
      <button @click="$emit('close')" class="close-button">
        <XMarkIcon class="w-6 h-6" />
      </button>
    </div>

    <div class="detail-content">
      <!-- Información del paciente -->
      <div class="detail-section">
        <h3 class="section-title">Información del Paciente</h3>
        <div class="patient-info">
          <div class="info-item">
            <span class="info-label">Nombre:</span>
            <span class="info-value">{{ record.patient?.first_name }} {{ record.patient?.last_name }}</span>
          </div>
          <div class="info-item" v-if="record.patient?.dni">
            <span class="info-label">DNI:</span>
            <span class="info-value">{{ record.patient.dni }}</span>
          </div>
          <div class="info-item" v-if="record.patient?.phone">
            <span class="info-label">Teléfono:</span>
            <span class="info-value">{{ record.patient.phone }}</span>
          </div>
        </div>
      </div>

      <!-- Descripción -->
      <div class="detail-section" v-if="record.description">
        <h3 class="section-title">Descripción</h3>
        <div class="description-content">
          {{ record.description }}
        </div>
      </div>

      <!-- Información específica por especialidad -->
      <div class="detail-section">
        <h3 class="section-title">Información Específica</h3>

        <!-- Implantología -->
        <div v-if="record.specialty === 'implantology'" class="specialty-info">
          <div class="info-item" v-if="record.implant_count">
            <span class="info-label">Número de Implantes:</span>
            <span class="info-value">{{ record.implant_count }}</span>
          </div>
          <div class="info-item" v-if="record.implant_type">
            <span class="info-label">Tipo de Implante:</span>
            <span class="info-value">{{ record.implant_type }}</span>
          </div>
          <div class="info-item" v-if="record.position">
            <span class="info-label">Posición:</span>
            <span class="info-value">{{ record.position }}</span>
          </div>
        </div>

        <!-- Ortodoncia -->
        <div v-else-if="record.specialty === 'orthodontics'" class="specialty-info">
          <div class="info-item" v-if="record.treatment_type">
            <span class="info-label">Tipo de Tratamiento:</span>
            <span class="info-value">{{ getTreatmentTypeLabel(record.treatment_type) }}</span>
          </div>
          <div class="info-item" v-if="record.estimated_duration">
            <span class="info-label">Duración Estimada:</span>
            <span class="info-value">{{ record.estimated_duration }} meses</span>
          </div>
          <div class="info-item" v-if="record.main_problem">
            <span class="info-label">Problema Principal:</span>
            <span class="info-value">{{ record.main_problem }}</span>
          </div>
        </div>

        <!-- Endodoncia -->
        <div v-else-if="record.specialty === 'endodontics'" class="specialty-info">
          <div class="info-item" v-if="record.tooth_number">
            <span class="info-label">Diente Tratado:</span>
            <span class="info-value">{{ record.tooth_number }}</span>
          </div>
          <div class="info-item" v-if="record.canal_count">
            <span class="info-label">Número de Conductos:</span>
            <span class="info-value">{{ record.canal_count }}</span>
          </div>
          <div class="info-item" v-if="record.obturation_material">
            <span class="info-label">Material de Obturación:</span>
            <span class="info-value">{{ record.obturation_material }}</span>
          </div>
        </div>

        <!-- Rehabilitación -->
        <div v-else-if="record.specialty === 'rehabilitation'" class="specialty-info">
          <div class="info-item" v-if="record.prosthesis_type">
            <span class="info-label">Tipo de Prótesis:</span>
            <span class="info-value">{{ getProsthesisTypeLabel(record.prosthesis_type) }}</span>
          </div>
          <div class="info-item" v-if="record.material">
            <span class="info-label">Material:</span>
            <span class="info-value">{{ record.material }}</span>
          </div>
          <div class="info-item" v-if="record.involved_teeth">
            <span class="info-label">Dientes Involucrados:</span>
            <span class="info-value">{{ record.involved_teeth }}</span>
          </div>
        </div>

        <!-- Cirugía Oral -->
        <div v-else-if="record.specialty === 'oral_surgery'" class="specialty-info">
          <div class="info-item" v-if="record.surgery_type">
            <span class="info-label">Tipo de Cirugía:</span>
            <span class="info-value">{{ getSurgeryTypeLabel(record.surgery_type) }}</span>
          </div>
          <div class="info-item" v-if="record.anesthesia">
            <span class="info-label">Anestesia Utilizada:</span>
            <span class="info-value">{{ record.anesthesia }}</span>
          </div>
          <div class="info-item" v-if="record.complications">
            <span class="info-label">Complicaciones:</span>
            <span class="info-value">{{ record.complications }}</span>
          </div>
        </div>
      </div>

      <!-- Información del procedimiento -->
      <div class="detail-section" v-if="record.procedure_date || record.professional_name">
        <h3 class="section-title">Información del Procedimiento</h3>
        <div class="procedure-info">
          <div class="info-item" v-if="record.procedure_date">
            <span class="info-label">Fecha del Procedimiento:</span>
            <span class="info-value">{{ formatDate(record.procedure_date) }}</span>
          </div>
          <div class="info-item" v-if="record.professional_name">
            <span class="info-label">Profesional Responsable:</span>
            <span class="info-value">{{ record.professional_name }}</span>
          </div>
        </div>
      </div>

      <!-- Observaciones -->
      <div class="detail-section" v-if="record.observations">
        <h3 class="section-title">Observaciones</h3>
        <div class="observations-content">
          {{ record.observations }}
        </div>
      </div>

      <!-- Próxima cita -->
      <div class="detail-section" v-if="record.next_appointment">
        <h3 class="section-title">Próxima Cita</h3>
        <div class="appointment-info">
          <div class="info-item">
            <span class="info-label">Fecha y Hora:</span>
            <span class="info-value">{{ formatDateTime(record.next_appointment) }}</span>
          </div>
        </div>
      </div>
    </div>

    <div class="detail-footer">
      <div class="footer-actions">
        <button
          @click="$emit('edit', record)"
          class="btn btn-outline"
          v-if="canEdit"
        >
          <PencilIcon class="w-4 h-4 mr-2" />
          Editar
        </button>
        <button
          @click="viewHistory"
          class="btn btn-secondary"
          v-if="canViewHistory"
        >
          <ClockIcon class="w-4 h-4 mr-2" />
          Ver Historial
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import { usePermissions } from '@/composables/usePermissions'
import {
  XMarkIcon,
  PencilIcon,
  ClockIcon
} from '@heroicons/vue/24/outline'

const props = defineProps({
  record: {
    type: Object,
    required: true
  }
})

const emit = defineEmits(['close', 'edit', 'view-history'])

const { can } = usePermissions()

// Computed
const canEdit = computed(() => {
  return can('specialty-records.update')
})

const canViewHistory = computed(() => {
  return can('specialty-records.history.view')
})

// Métodos
const formatDate = (date) => {
  if (!date) return 'N/A'
  return new Date(date).toLocaleDateString('es-ES', {
    year: 'numeric',
    month: 'long',
    day: 'numeric'
  })
}

const formatDateTime = (date) => {
  if (!date) return 'N/A'
  return new Date(date).toLocaleString('es-ES', {
    year: 'numeric',
    month: 'long',
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit'
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

const viewHistory = () => {
  emit('view-history', props.record)
}
</script>

<style scoped>
.specialty-record-detail {
  @apply bg-theme-surface-elevated rounded-lg shadow-lg max-w-4xl mx-auto;
}

.detail-header {
  @apply flex items-center justify-between p-6 border-b border-theme;
}

.detail-title h2 {
  @apply text-xl font-semibold text-theme-primary;
}

.detail-meta {
  @apply flex items-center space-x-3 mt-2;
}

.specialty-badge {
  @apply px-2 py-1 text-xs font-medium bg-primary-100 text-primary-800 rounded-full;
}

.close-button {
  @apply p-2 text-theme-secondary hover:text-theme-primary transition-colors;
}

.detail-content {
  @apply p-6 space-y-6;
}

.detail-section {
  @apply space-y-3;
}

.section-title {
  @apply text-lg font-medium text-theme-primary;
}

.patient-info,
.specialty-info,
.procedure-info,
.appointment-info {
  @apply space-y-2;
}

.info-item {
  @apply flex justify-between items-center py-1;
}

.info-label {
  @apply text-sm font-medium text-theme-secondary;
}

.info-value {
  @apply text-sm text-theme-primary;
}

.description-content,
.observations-content {
  @apply text-sm text-theme-primary bg-theme-surface p-3 rounded-lg;
}

.detail-footer {
  @apply p-6 border-t border-theme;
}

.footer-actions {
  @apply flex items-center justify-end space-x-3;
}

.btn {
  @apply inline-flex items-center px-4 py-2 text-sm font-medium rounded-lg transition-colors;
}

.btn-outline {
  @apply border border-theme text-theme-primary hover:bg-theme-surface;
}

.btn-secondary {
  @apply bg-theme-surface-elevated text-theme-primary hover:bg-theme-surface;
}
</style>
