<template>
  <div class="evolution-timeline">
    <div class="timeline-header">
      <div class="flex justify-between items-center">
        <h3 class="timeline-title">Evoluciones Clínicas</h3>
        <button
          @click="$emit('add')"
          class="btn btn-primary btn-sm"
        >
          <PlusIcon class="w-4 h-4 mr-1" />
          Nueva Evolución
        </button>
      </div>
    </div>

    <div v-if="loading" class="flex justify-center py-8">
      <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-primary-600"></div>
    </div>

    <div v-else-if="evolutions.length === 0" class="empty-state">
      <ClockIcon class="w-12 h-12 text-theme-secondary mx-auto mb-4" />
      <h3 class="text-lg font-medium text-theme-primary mb-2">No hay evoluciones</h3>
      <p class="text-theme-secondary mb-4">Comienza agregando la primera evolución</p>
      <button @click="$emit('add')" class="btn btn-primary">
        Agregar Evolución
      </button>
    </div>

    <div v-else class="timeline-content">
      <div class="timeline-line"></div>

      <div
        v-for="(evolution, index) in evolutions"
        :key="evolution.id"
        class="timeline-item"
        :class="{ 'timeline-item-left': index % 2 === 0, 'timeline-item-right': index % 2 === 1 }"
      >
        <div class="timeline-marker">
          <div class="marker-dot"></div>
        </div>

        <div class="timeline-card">
          <div class="card-header">
            <div class="flex justify-between items-start">
              <div>
                <h4 class="evolution-title">{{ formatDate(evolution.evolution_date) }}</h4>
                <p class="evolution-specialty">{{ evolution.specialty || 'General' }}</p>
              </div>
              <div class="evolution-actions">
                <button
                  @click="$emit('edit', evolution)"
                  class="btn btn-sm btn-outline"
                  title="Editar"
                >
                  <PencilIcon class="w-4 h-4" />
                </button>
                <button
                  @click="confirmDelete(evolution)"
                  class="btn btn-sm btn-danger"
                  title="Eliminar"
                >
                  <TrashIcon class="w-4 h-4" />
                </button>
              </div>
            </div>
          </div>

          <div class="card-body">
            <!-- SOAP -->
            <div v-if="evolution.subjective || evolution.objective || evolution.assessment || evolution.plan" class="soap-section">
              <div v-if="evolution.subjective" class="soap-item">
                <h5 class="soap-label">Subjetivo:</h5>
                <p class="soap-content">{{ evolution.subjective }}</p>
              </div>

              <div v-if="evolution.objective" class="soap-item">
                <h5 class="soap-label">Objetivo:</h5>
                <p class="soap-content">{{ evolution.objective }}</p>
              </div>

              <div v-if="evolution.assessment" class="soap-item">
                <h5 class="soap-label">Evaluación:</h5>
                <p class="soap-content">{{ evolution.assessment }}</p>
              </div>

              <div v-if="evolution.plan" class="soap-item">
                <h5 class="soap-label">Plan:</h5>
                <p class="soap-content">{{ evolution.plan }}</p>
              </div>
            </div>

            <!-- Procedimientos realizados -->
            <div v-if="evolution.procedures_performed" class="procedures-section">
              <h5 class="section-label">Procedimientos realizados:</h5>
              <p class="section-content">{{ evolution.procedures_performed }}</p>
            </div>

            <!-- Materiales utilizados -->
            <div v-if="evolution.materials_used" class="materials-section">
              <h5 class="section-label">Materiales utilizados:</h5>
              <p class="section-content">{{ evolution.materials_used }}</p>
            </div>

            <!-- Prescripciones -->
            <div v-if="evolution.prescriptions" class="prescriptions-section">
              <h5 class="section-label">Prescripciones:</h5>
              <p class="section-content">{{ evolution.prescriptions }}</p>
            </div>

            <!-- Recomendaciones -->
            <div v-if="evolution.recommendations" class="recommendations-section">
              <h5 class="section-label">Recomendaciones:</h5>
              <p class="section-content">{{ evolution.recommendations }}</p>
            </div>

            <!-- Signos vitales -->
            <div v-if="evolution.vital_signs" class="vital-signs-section">
              <h5 class="section-label">Signos vitales:</h5>
              <div class="vital-signs-grid">
                <div v-if="evolution.vital_signs.blood_pressure" class="vital-sign">
                  <span class="vital-label">PA:</span>
                  <span class="vital-value">{{ evolution.vital_signs.blood_pressure }}</span>
                </div>
                <div v-if="evolution.vital_signs.heart_rate" class="vital-sign">
                  <span class="vital-label">FC:</span>
                  <span class="vital-value">{{ evolution.vital_signs.heart_rate }} bpm</span>
                </div>
                <div v-if="evolution.vital_signs.temperature" class="vital-sign">
                  <span class="vital-label">T°:</span>
                  <span class="vital-value">{{ evolution.vital_signs.temperature }}°C</span>
                </div>
                <div v-if="evolution.vital_signs.respiratory_rate" class="vital-sign">
                  <span class="vital-label">FR:</span>
                  <span class="vital-value">{{ evolution.vital_signs.respiratory_rate }} rpm</span>
                </div>
              </div>
            </div>

            <!-- Seguimiento -->
            <div v-if="evolution.requires_follow_up" class="follow-up-section">
              <div class="flex items-center space-x-2">
                <div class="w-2 h-2 bg-yellow-500 rounded-full"></div>
                <span class="text-sm font-medium text-yellow-700">Requiere seguimiento</span>
                <span v-if="evolution.follow_up_date" class="text-sm text-theme-secondary">
                  - {{ formatDate(evolution.follow_up_date) }}
                </span>
              </div>
            </div>

            <!-- Notas de próxima cita -->
            <div v-if="evolution.next_appointment_notes" class="next-appointment-section">
              <h5 class="section-label">Notas para próxima cita:</h5>
              <p class="section-content">{{ evolution.next_appointment_notes }}</p>
            </div>
          </div>

          <div class="card-footer">
            <div class="flex justify-between items-center text-xs text-theme-secondary">
              <span>Por {{ evolution.created_by?.first_name }} {{ evolution.created_by?.last_name }}</span>
              <span>{{ formatDateTime(evolution.created_at) }}</span>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue'
import { useConfirm } from '@/composables/useConfirm'
import { PlusIcon, ClockIcon, PencilIcon, TrashIcon } from '@heroicons/vue/24/outline'

const props = defineProps({
  evolutions: {
    type: Array,
    default: () => []
  },
  loading: {
    type: Boolean,
    default: false
  }
})

const emit = defineEmits(['add', 'edit', 'delete'])

// Métodos
const formatDate = (date) => {
  return new Date(date).toLocaleDateString('es-PE', {
    year: 'numeric',
    month: 'long',
    day: 'numeric'
  })
}

const formatDateTime = (date) => {
  return new Date(date).toLocaleDateString('es-PE', {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit'
  })
}

const confirmDelete = async (evolution) => {
  const ok = await confirm({
    title: 'Eliminar evolución',
    message: '¿Estás seguro de que quieres eliminar esta evolución?',
    confirmText: 'Eliminar',
    variant: 'danger',
  })
  if (ok) {
    emit('delete', evolution.id)
  }
}
</script>

<style scoped>
.evolution-timeline {
  @apply space-y-6;
}

.timeline-header {
  @apply flex justify-between items-center;
}

.timeline-title {
  @apply text-lg font-semibold text-theme-primary;
}

.timeline-content {
  @apply relative;
}

.timeline-line {
  @apply absolute left-8 top-0 bottom-0 w-0.5 bg-theme;
}

.timeline-item {
  @apply relative flex items-start space-x-4 mb-8;
}

.timeline-item-left {
  @apply flex-row;
}

.timeline-item-right {
  @apply flex-row-reverse space-x-reverse;
}

.timeline-marker {
  @apply flex-shrink-0 w-16 flex justify-center;
}

.marker-dot {
  @apply w-4 h-4 bg-primary-500 rounded-full border-4 border-white shadow-lg;
}

.timeline-card {
  @apply flex-1 bg-theme-surface-elevated rounded-lg border border-theme shadow-sm;
}

.card-header {
  @apply p-4 border-b border-theme;
}

.evolution-title {
  @apply text-lg font-semibold text-theme-primary;
}

.evolution-specialty {
  @apply text-sm text-theme-secondary;
}

.evolution-actions {
  @apply flex space-x-2;
}

.card-body {
  @apply p-4 space-y-4;
}

.soap-section {
  @apply space-y-3;
}

.soap-item {
  @apply space-y-1;
}

.soap-label {
  @apply text-sm font-medium text-theme-primary;
}

.soap-content {
  @apply text-sm text-theme-secondary;
}

.procedures-section,
.materials-section,
.prescriptions-section,
.recommendations-section,
.next-appointment-section {
  @apply space-y-1;
}

.section-label {
  @apply text-sm font-medium text-theme-primary;
}

.section-content {
  @apply text-sm text-theme-secondary;
}

.vital-signs-section {
  @apply space-y-2;
}

.vital-signs-grid {
  @apply grid grid-cols-2 md:grid-cols-4 gap-2;
}

.vital-sign {
  @apply flex justify-between items-center p-2 bg-theme-surface rounded;
}

.vital-label {
  @apply text-xs font-medium text-theme-secondary;
}

.vital-value {
  @apply text-xs font-semibold text-theme-primary;
}

.follow-up-section {
  @apply p-3 bg-yellow-50 border border-yellow-200 rounded-lg;
}

.card-footer {
  @apply p-4 border-t border-theme bg-theme-surface;
}

.empty-state {
  @apply text-center py-12;
}

.btn {
  @apply inline-flex items-center justify-center px-4 py-2 border border-transparent text-sm font-medium rounded-md transition-colors;
}

.btn-sm {
  @apply px-3 py-1 text-xs;
}

.btn-primary {
  @apply bg-primary-600 text-white hover:bg-primary-700;
}

.btn-outline {
  @apply border-theme text-theme-primary bg-theme-surface-elevated hover:bg-theme-surface;
}

.btn-danger {
  @apply bg-red-100 text-red-700 hover:bg-red-200;
}
</style>
