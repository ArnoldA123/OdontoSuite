<template>
  <div class="medical-record-detail">
    <div class="detail-header">
      <div class="detail-title">
        <h2 class="text-xl font-semibold text-theme-primary">
          {{ record.title }}
        </h2>
        <div class="detail-meta">
          <span class="text-sm text-theme-secondary">
            {{ formatDate(record.created_at) }}
          </span>
        </div>
      </div>
      <button class="close-button" @click="$emit('close')">
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
            <span class="info-value">
              {{ record.patient?.first_name }} {{ record.patient?.last_name }}
            </span>
          </div>
          <div v-if="record.patient?.dni" class="info-item">
            <span class="info-label">DNI:</span>
            <span class="info-value">{{ record.patient.dni }}</span>
          </div>
          <div v-if="record.patient?.phone" class="info-item">
            <span class="info-label">Teléfono:</span>
            <span class="info-value">{{ record.patient.phone }}</span>
          </div>
        </div>
      </div>

      <!-- Descripción -->
      <div v-if="record.description" class="detail-section">
        <h3 class="section-title">Descripción</h3>
        <div class="description-content">
          {{ record.description }}
        </div>
      </div>

      <!-- Diagnósticos -->
      <div v-if="record.primary_diagnosis" class="detail-section">
        <h3 class="section-title">Diagnósticos</h3>
        <div class="diagnosis-info">
          <div v-if="record.primary_diagnosis" class="info-item">
            <span class="info-label">Principal:</span>
            <span class="info-value">{{ record.primary_diagnosis }}</span>
          </div>
          <div v-if="record.secondary_diagnoses" class="info-item">
            <span class="info-label">Secundarios:</span>
            <span class="info-value">{{ record.secondary_diagnoses }}</span>
          </div>
        </div>
      </div>

      <!-- Tratamiento -->
      <div v-if="record.treatment" class="detail-section">
        <h3 class="section-title">Tratamiento</h3>
        <div class="treatment-content">
          {{ record.treatment }}
        </div>
      </div>

      <!-- Medicamentos -->
      <div v-if="record.medications" class="detail-section">
        <h3 class="section-title">Medicamentos</h3>
        <div class="medications-content">
          {{ record.medications }}
        </div>
      </div>

      <!-- Alergias -->
      <div v-if="record.allergies" class="detail-section">
        <h3 class="section-title">Alergias</h3>
        <div class="allergies-content">
          {{ record.allergies }}
        </div>
      </div>

      <!-- Antecedentes -->
      <div v-if="record.medical_history" class="detail-section">
        <h3 class="section-title">Antecedentes Médicos</h3>
        <div class="history-content">
          {{ record.medical_history }}
        </div>
      </div>

      <!-- Notas -->
      <div v-if="record.notes" class="detail-section">
        <h3 class="section-title">Notas Adicionales</h3>
        <div class="notes-content">
          {{ record.notes }}
        </div>
      </div>
    </div>

    <div class="detail-footer">
      <div class="footer-actions">
        <button v-if="canEdit" class="btn btn-outline" @click="$emit('edit', record)">
          <PencilIcon class="w-4 h-4 mr-2" />
          Editar
        </button>
        <button v-if="canAddEvolution" class="btn btn-primary" @click="addEvolution">
          <PlusIcon class="w-4 h-4 mr-2" />
          Agregar Evolución
        </button>
        <button v-if="canUpload" class="btn btn-secondary" @click="uploadAttachment">
          <DocumentPlusIcon class="w-4 h-4 mr-2" />
          Subir Archivo
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import { usePermissions } from '@/composables/usePermissions'
import { XMarkIcon, PencilIcon, PlusIcon, DocumentPlusIcon } from '@heroicons/vue/24/outline'

const props = defineProps({
  record: {
    type: Object,
    required: true
  }
})

const emit = defineEmits(['close', 'edit', 'add-evolution', 'upload-attachment'])

const { can } = usePermissions()

// Computed
const canEdit = computed(() => {
  return can('medical-records.update')
})

const canAddEvolution = computed(() => {
  return can('medical-records.evolutions.create')
})

const canUpload = computed(() => {
  return can('medical-records.attachments.create')
})

// Métodos
const formatDate = date => {
  if (!date) return 'N/A'
  return new Date(date).toLocaleDateString('es-ES', {
    year: 'numeric',
    month: 'long',
    day: 'numeric'
  })
}

const addEvolution = () => {
  emit('add-evolution', props.record)
}

const uploadAttachment = () => {
  emit('upload-attachment', props.record)
}
</script>

<style scoped>
.medical-record-detail {
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
.diagnosis-info {
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
.treatment-content,
.medications-content,
.allergies-content,
.history-content,
.notes-content {
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

.btn-primary {
  @apply bg-primary-600 text-white hover:bg-primary-700;
}

.btn-secondary {
  @apply bg-theme-surface-elevated text-theme-primary hover:bg-theme-surface;
}
</style>
