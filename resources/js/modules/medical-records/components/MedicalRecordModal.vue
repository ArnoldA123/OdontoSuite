<template>
  <div class="modal-overlay" @click="closeModal">
    <div class="modal-content" @click.stop>
      <div class="modal-header">
        <h2 class="modal-title">
          {{ isEdit ? 'Editar Historia Clínica' : 'Nueva Historia Clínica' }}
        </h2>
        <button class="modal-close" @click="closeModal">
          <XMarkIcon class="w-6 h-6" />
        </button>
      </div>

      <form class="modal-body" @submit.prevent="handleSubmit">
        <div class="form-grid">
          <!-- Información básica -->
          <div class="form-section">
            <h3 class="section-title">Información Básica</h3>

            <div class="form-group">
              <label class="form-label">Paciente *</label>
              <PatientSelector
                v-model="selectedPatient"
                :error="errors.patient_id"
                @patient-selected="handlePatientChange"
              />
            </div>

            <div class="form-group">
              <label class="form-label">Título *</label>
              <input
                v-model="form.title"
                type="text"
                class="form-input"
                :class="{ 'border-red-500': errors.title }"
                placeholder="Ej: Historia clínica inicial"
                required
              />
              <p v-if="errors.title" class="form-error">
                {{ errors.title }}
              </p>
            </div>

            <div class="form-group">
              <label class="form-label">Descripción</label>
              <textarea
                v-model="form.description"
                class="form-textarea"
                rows="3"
                placeholder="Descripción de la historia clínica..."
              />
            </div>
          </div>

          <!-- Información clínica -->
          <div class="form-section">
            <h3 class="section-title">Información Clínica</h3>

            <div class="form-group">
              <label class="form-label">Diagnóstico Principal</label>
              <input
                v-model="form.primary_diagnosis"
                type="text"
                class="form-input"
                placeholder="Diagnóstico principal..."
              />
            </div>

            <div class="form-group">
              <label class="form-label">Diagnósticos Secundarios</label>
              <textarea
                v-model="form.secondary_diagnoses"
                class="form-textarea"
                rows="2"
                placeholder="Diagnósticos secundarios..."
              />
            </div>

            <div class="form-group">
              <label class="form-label">Tratamiento</label>
              <textarea
                v-model="form.treatment"
                class="form-textarea"
                rows="3"
                placeholder="Plan de tratamiento..."
              />
            </div>

            <div class="form-group">
              <label class="form-label">Medicamentos</label>
              <textarea
                v-model="form.medications"
                class="form-textarea"
                rows="2"
                placeholder="Medicamentos prescritos..."
              />
            </div>
          </div>

          <!-- Información adicional -->
          <div class="form-section">
            <h3 class="section-title">Información Adicional</h3>

            <div class="form-group">
              <label class="form-label">Alergias</label>
              <input
                v-model="form.allergies"
                type="text"
                class="form-input"
                placeholder="Alergias conocidas..."
              />
            </div>

            <div class="form-group">
              <label class="form-label">Antecedentes Médicos</label>
              <textarea
                v-model="form.medical_history"
                class="form-textarea"
                rows="2"
                placeholder="Antecedentes médicos relevantes..."
              />
            </div>

            <div class="form-group">
              <label class="form-label">Notas Adicionales</label>
              <textarea
                v-model="form.notes"
                class="form-textarea"
                rows="3"
                placeholder="Notas adicionales..."
              />
            </div>
          </div>
        </div>
      </form>

      <div class="modal-footer">
        <button type="button" class="btn btn-outline" :disabled="loading"
@click="closeModal">
          Cancelar
        </button>
        <button type="submit" class="btn btn-primary" :disabled="loading"
@click="handleSubmit">
          <CheckIcon class="w-4 h-4 mr-2" />
          {{ loading ? 'Guardando...' : isEdit ? 'Actualizar' : 'Crear Historia' }}
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useMedicalRecords } from '@/composables/useMedicalRecords'
import PatientSelector from '@/components/ui/PatientSelector.vue'
import { XMarkIcon, CheckIcon } from '@heroicons/vue/24/outline'

const props = defineProps({
  record: {
    type: Object,
    default: null
  },
  isEdit: {
    type: Boolean,
    default: false
  }
})

const emit = defineEmits(['close', 'saved'])

const { createRecord, updateRecord, loading } = useMedicalRecords()

const selectedPatient = ref(null)

const form = ref({
  patient_id: '',
  title: '',
  description: '',
  primary_diagnosis: '',
  secondary_diagnoses: '',
  treatment: '',
  medications: '',
  allergies: '',
  medical_history: '',
  notes: ''
})

const errors = ref({})

const handlePatientChange = patient => {
  selectedPatient.value = patient
  form.value.patient_id = patient?.id || ''
}

const handleSubmit = async () => {
  try {
    errors.value = {}

    // Validaciones básicas
    if (!form.value.patient_id || form.value.patient_id === '') {
      errors.value.patient_id = 'El paciente es obligatorio'
      return
    }
    if (!form.value.title || form.value.title.trim() === '') {
      errors.value.title = 'El título es obligatorio'
      return
    }

    if (props.isEdit) {
      await updateRecord(props.record.id, form.value)
    } else {
      await createRecord(form.value)
    }

    emit('saved')
  } catch (err) {
    if (err.response?.data?.errors) {
      errors.value = err.response.data.errors
    }
  }
}

const closeModal = () => {
  emit('close')
}

const initializeForm = () => {
  if (props.record) {
    form.value = {
      patient_id: props.record.patient_id || '',
      title: props.record.title || '',
      description: props.record.description || '',
      primary_diagnosis: props.record.primary_diagnosis || '',
      secondary_diagnoses: props.record.secondary_diagnoses || '',
      treatment: props.record.treatment || '',
      medications: props.record.medications || '',
      allergies: props.record.allergies || '',
      medical_history: props.record.medical_history || '',
      notes: props.record.notes || ''
    }
  }
}

onMounted(() => {
  initializeForm()
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
