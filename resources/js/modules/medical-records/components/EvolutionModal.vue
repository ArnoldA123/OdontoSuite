<template>
  <div class="modal-overlay" @click="closeModal">
    <div class="modal-content" @click.stop>
      <div class="modal-header">
        <h2 class="modal-title">Agregar Evolución Clínica</h2>
        <button @click="closeModal" class="modal-close">
          <XMarkIcon class="w-6 h-6" />
        </button>
      </div>

      <form @submit.prevent="handleSubmit" class="modal-body">
        <div class="form-grid">
          <!-- Información básica -->
          <div class="form-section">
            <h3 class="section-title">Información de la Evolución</h3>

            <div class="form-group">
              <label class="form-label">Fecha y Hora *</label>
              <input
                v-model="form.evolution_date"
                type="datetime-local"
                class="form-input"
                :class="{ 'border-red-500': errors.evolution_date }"
                required
              />
              <p v-if="errors.evolution_date" class="form-error">{{ errors.evolution_date }}</p>
            </div>

            <div class="form-group">
              <label class="form-label">Tipo de Evolución</label>
              <select
                v-model="form.evolution_type"
                class="form-input"
              >
                <option value="consultation">Consulta</option>
                <option value="treatment">Tratamiento</option>
                <option value="follow_up">Seguimiento</option>
                <option value="emergency">Emergencia</option>
                <option value="other">Otro</option>
              </select>
            </div>

            <div class="form-group">
              <label class="form-label">Profesional Responsable</label>
              <input
                v-model="form.professional_name"
                type="text"
                class="form-input"
                placeholder="Nombre del profesional"
              />
            </div>
          </div>

          <!-- Descripción de la evolución -->
          <div class="form-section">
            <h3 class="section-title">Descripción de la Evolución</h3>

            <div class="form-group">
              <label class="form-label">Motivo de Consulta</label>
              <textarea
                v-model="form.consultation_reason"
                class="form-textarea"
                rows="2"
                placeholder="Motivo de la consulta o evolución..."
              ></textarea>
            </div>

            <div class="form-group">
              <label class="form-label">Examen Clínico</label>
              <textarea
                v-model="form.clinical_examination"
                class="form-textarea"
                rows="3"
                placeholder="Resultados del examen clínico..."
              ></textarea>
            </div>

            <div class="form-group">
              <label class="form-label">Observaciones *</label>
              <textarea
                v-model="form.observations"
                class="form-textarea"
                rows="4"
                :class="{ 'border-red-500': errors.observations }"
                placeholder="Observaciones detalladas de la evolución..."
                required
              ></textarea>
              <p v-if="errors.observations" class="form-error">{{ errors.observations }}</p>
            </div>
          </div>

          <!-- Tratamiento y seguimiento -->
          <div class="form-section">
            <h3 class="section-title">Tratamiento y Seguimiento</h3>

            <div class="form-group">
              <label class="form-label">Tratamiento Realizado</label>
              <textarea
                v-model="form.treatment_performed"
                class="form-textarea"
                rows="3"
                placeholder="Tratamiento realizado en esta sesión..."
              ></textarea>
            </div>

            <div class="form-group">
              <label class="form-label">Medicamentos Prescritos</label>
              <textarea
                v-model="form.prescribed_medications"
                class="form-textarea"
                rows="2"
                placeholder="Medicamentos prescritos..."
              ></textarea>
            </div>

            <div class="form-group">
              <label class="form-label">Recomendaciones</label>
              <textarea
                v-model="form.recommendations"
                class="form-textarea"
                rows="2"
                placeholder="Recomendaciones para el paciente..."
              ></textarea>
            </div>

            <div class="form-group">
              <label class="form-label">Próxima Cita</label>
              <input
                v-model="form.next_appointment"
                type="datetime-local"
                class="form-input"
              />
            </div>
          </div>
        </div>
      </form>

      <div class="modal-footer">
        <button
          type="button"
          @click="closeModal"
          class="btn btn-outline"
          :disabled="loading"
        >
          Cancelar
        </button>
        <button
          type="submit"
          @click="handleSubmit"
          class="btn btn-primary"
          :disabled="loading"
        >
          <CheckIcon class="w-4 h-4 mr-2" />
          {{ loading ? 'Guardando...' : 'Agregar Evolución' }}
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useMedicalRecords } from '@/composables/useMedicalRecords'
import { XMarkIcon, CheckIcon } from '@heroicons/vue/24/outline'

const props = defineProps({
  record: {
    type: Object,
    required: true
  }
})

const emit = defineEmits(['close', 'saved'])

const { addEvolution, loading } = useMedicalRecords()

const form = ref({
  evolution_date: '',
  evolution_type: 'consultation',
  professional_name: '',
  consultation_reason: '',
  clinical_examination: '',
  observations: '',
  treatment_performed: '',
  prescribed_medications: '',
  recommendations: '',
  next_appointment: ''
})

const errors = ref({})

const handleSubmit = async () => {
  try {
    errors.value = {}

    // Validaciones básicas
    if (!form.value.evolution_date) {
      errors.value.evolution_date = 'La fecha es obligatoria'
      return
    }
    if (!form.value.observations || form.value.observations.trim() === '') {
      errors.value.observations = 'Las observaciones son obligatorias'
      return
    }

    await addEvolution(props.record.id, form.value)
    emit('saved')
  } catch (err) {
    console.error('Error adding evolution:', err)
    if (err.response?.data?.errors) {
      errors.value = err.response.data.errors
    }
  }
}

const closeModal = () => {
  emit('close')
}

onMounted(() => {
  // Set default values
  form.value.evolution_date = new Date().toISOString().slice(0, 16)
  form.value.professional_name = 'Usuario Actual' // This should come from auth context
})
</script>

<style scoped>
.modal-overlay {
  @apply fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4;
}

.modal-content {
  @apply bg-theme-surface-elevated rounded-lg shadow-xl max-w-4xl w-full max-h-[90vh] overflow-y-auto;
}

.modal-header {
  @apply flex items-center justify-between p-6 border-b border-theme;
}

.modal-title {
  @apply text-xl font-semibold text-theme-primary;
}

.modal-close {
  @apply p-2 text-theme-secondary hover:text-theme-primary transition-colors;
}

.modal-body {
  @apply p-6;
}

.form-grid {
  @apply grid grid-cols-1 lg:grid-cols-2 gap-6;
}

.form-section {
  @apply space-y-4;
}

.section-title {
  @apply text-lg font-medium text-theme-primary border-b border-theme pb-2;
}

.form-group {
  @apply space-y-2;
}

.form-label {
  @apply block text-sm font-medium text-theme-primary;
}

.form-input {
  @apply block w-full px-3 py-2 border border-theme rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent bg-theme-surface-elevated text-theme-primary;
}

.form-textarea {
  @apply block w-full px-3 py-2 border border-theme rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent resize-none bg-theme-surface-elevated text-theme-primary;
}

.form-error {
  @apply text-sm text-red-600;
}

.modal-footer {
  @apply flex items-center justify-end space-x-3 p-6 border-t border-theme;
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
</style>
