<template>
  <div class="modal-overlay" @click="closeModal">
    <div class="modal-content" @click.stop>
      <div class="modal-header">
        <h2 class="modal-title">
          {{ isEdit ? 'Editar Registro de Especialidad' : 'Nuevo Registro de Especialidad' }}
        </h2>
        <button @click="closeModal" class="modal-close">
          <XMarkIcon class="w-6 h-6" />
        </button>
      </div>

      <form @submit.prevent="handleSubmit" class="modal-body">
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
              <label class="form-label">Especialidad *</label>
              <select
                v-model="form.specialty"
                class="form-input"
                :class="{ 'border-red-500': errors.specialty }"
                required
                @change="handleSpecialtyChange"
              >
                <option value="">Seleccionar especialidad</option>
                <option value="implantology">Implantología</option>
                <option value="orthodontics">Ortodoncia</option>
                <option value="endodontics">Endodoncia</option>
                <option value="rehabilitation">Rehabilitación</option>
                <option value="oral_surgery">Cirugía Oral</option>
              </select>
              <p v-if="errors.specialty" class="form-error">{{ errors.specialty }}</p>
            </div>

            <div class="form-group">
              <label class="form-label">Título *</label>
              <input
                v-model="form.title"
                type="text"
                class="form-input"
                :class="{ 'border-red-500': errors.title }"
                placeholder="Ej: Evaluación de implantes"
                required
              />
              <p v-if="errors.title" class="form-error">{{ errors.title }}</p>
            </div>

            <div class="form-group">
              <label class="form-label">Descripción</label>
              <textarea
                v-model="form.description"
                class="form-textarea"
                rows="3"
                placeholder="Descripción del registro..."
              ></textarea>
            </div>
          </div>

          <!-- Campos específicos por especialidad -->
          <div class="form-section">
            <h3 class="section-title">Información Específica</h3>

            <!-- Implantología -->
            <div v-if="form.specialty === 'implantology'" class="specialty-fields">
              <div class="form-group">
                <label class="form-label">Número de Implantes</label>
                <input
                  v-model="form.implant_count"
                  type="number"
                  class="form-input"
                  min="1"
                />
              </div>
              <div class="form-group">
                <label class="form-label">Tipo de Implante</label>
                <input
                  v-model="form.implant_type"
                  type="text"
                  class="form-input"
                  placeholder="Marca y modelo del implante"
                />
              </div>
              <div class="form-group">
                <label class="form-label">Posición</label>
                <input
                  v-model="form.position"
                  type="text"
                  class="form-input"
                  placeholder="Posición del implante"
                />
              </div>
            </div>

            <!-- Ortodoncia -->
            <div v-else-if="form.specialty === 'orthodontics'" class="specialty-fields">
              <div class="form-group">
                <label class="form-label">Tipo de Tratamiento</label>
                <select v-model="form.treatment_type" class="form-input">
                  <option value="">Seleccionar tipo</option>
                  <option value="fixed">Aparatología Fija</option>
                  <option value="removable">Aparatología Removible</option>
                  <option value="invisible">Ortodoncia Invisible</option>
                </select>
              </div>
              <div class="form-group">
                <label class="form-label">Duración Estimada (meses)</label>
                <input
                  v-model="form.estimated_duration"
                  type="number"
                  class="form-input"
                  min="1"
                />
              </div>
              <div class="form-group">
                <label class="form-label">Problema Principal</label>
                <textarea
                  v-model="form.main_problem"
                  class="form-textarea"
                  rows="2"
                  placeholder="Descripción del problema principal"
                ></textarea>
              </div>
            </div>

            <!-- Endodoncia -->
            <div v-else-if="form.specialty === 'endodontics'" class="specialty-fields">
              <div class="form-group">
                <label class="form-label">Diente Tratado</label>
                <input
                  v-model="form.tooth_number"
                  type="text"
                  class="form-input"
                  placeholder="Número del diente"
                />
              </div>
              <div class="form-group">
                <label class="form-label">Número de Conductos</label>
                <input
                  v-model="form.canal_count"
                  type="number"
                  class="form-input"
                  min="1"
                />
              </div>
              <div class="form-group">
                <label class="form-label">Material de Obturación</label>
                <input
                  v-model="form.obturation_material"
                  type="text"
                  class="form-input"
                  placeholder="Material utilizado"
                />
              </div>
            </div>

            <!-- Rehabilitación -->
            <div v-else-if="form.specialty === 'rehabilitation'" class="specialty-fields">
              <div class="form-group">
                <label class="form-label">Tipo de Prótesis</label>
                <select v-model="form.prosthesis_type" class="form-input">
                  <option value="">Seleccionar tipo</option>
                  <option value="crown">Corona</option>
                  <option value="bridge">Puente</option>
                  <option value="denture">Dentadura</option>
                  <option value="implant_prosthesis">Prótesis sobre Implante</option>
                </select>
              </div>
              <div class="form-group">
                <label class="form-label">Material</label>
                <input
                  v-model="form.material"
                  type="text"
                  class="form-input"
                  placeholder="Material de la prótesis"
                />
              </div>
              <div class="form-group">
                <label class="form-label">Dientes Involucrados</label>
                <input
                  v-model="form.involved_teeth"
                  type="text"
                  class="form-input"
                  placeholder="Números de dientes"
                />
              </div>
            </div>

            <!-- Cirugía Oral -->
            <div v-else-if="form.specialty === 'oral_surgery'" class="specialty-fields">
              <div class="form-group">
                <label class="form-label">Tipo de Cirugía</label>
                <select v-model="form.surgery_type" class="form-input">
                  <option value="">Seleccionar tipo</option>
                  <option value="extraction">Extracción</option>
                  <option value="wisdom_tooth">Muela del Juicio</option>
                  <option value="implant_placement">Colocación de Implante</option>
                  <option value="biopsy">Biopsia</option>
                </select>
              </div>
              <div class="form-group">
                <label class="form-label">Anestesia Utilizada</label>
                <input
                  v-model="form.anesthesia"
                  type="text"
                  class="form-input"
                  placeholder="Tipo de anestesia"
                />
              </div>
              <div class="form-group">
                <label class="form-label">Complicaciones</label>
                <textarea
                  v-model="form.complications"
                  class="form-textarea"
                  rows="2"
                  placeholder="Complicaciones durante la cirugía"
                ></textarea>
              </div>
            </div>
          </div>

          <!-- Información adicional -->
          <div class="form-section">
            <h3 class="section-title">Información Adicional</h3>

            <div class="form-group">
              <label class="form-label">Fecha del Procedimiento</label>
              <input
                v-model="form.procedure_date"
                type="date"
                class="form-input"
              />
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

            <div class="form-group">
              <label class="form-label">Observaciones</label>
              <textarea
                v-model="form.observations"
                class="form-textarea"
                rows="3"
                placeholder="Observaciones adicionales..."
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
          {{ loading ? 'Guardando...' : (isEdit ? 'Actualizar' : 'Crear Registro') }}
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useSpecialtyRecords } from '@/composables/useSpecialtyRecords'
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

const { createRecord, updateRecord, loading } = useSpecialtyRecords()

const selectedPatient = ref(null)

const form = ref({
  patient_id: '',
  specialty: '',
  title: '',
  description: '',
  // Campos específicos por especialidad
  implant_count: '',
  implant_type: '',
  position: '',
  treatment_type: '',
  estimated_duration: '',
  main_problem: '',
  tooth_number: '',
  canal_count: '',
  obturation_material: '',
  prosthesis_type: '',
  material: '',
  involved_teeth: '',
  surgery_type: '',
  anesthesia: '',
  complications: '',
  // Campos generales
  procedure_date: '',
  professional_name: '',
  observations: '',
  next_appointment: ''
})

const errors = ref({})

const handlePatientChange = (patient) => {
  selectedPatient.value = patient
  form.value.patient_id = patient?.id || ''
}

const handleSpecialtyChange = () => {
  // Limpiar campos específicos cuando cambia la especialidad
  const specialtyFields = [
    'implant_count', 'implant_type', 'position',
    'treatment_type', 'estimated_duration', 'main_problem',
    'tooth_number', 'canal_count', 'obturation_material',
    'prosthesis_type', 'material', 'involved_teeth',
    'surgery_type', 'anesthesia', 'complications'
  ]

  specialtyFields.forEach(field => {
    form.value[field] = ''
  })
}

const handleSubmit = async () => {
  try {
    errors.value = {}

    // Validaciones básicas
    if (!form.value.patient_id || form.value.patient_id === '') {
      errors.value.patient_id = 'El paciente es obligatorio'
      return
    }
    if (!form.value.specialty) {
      errors.value.specialty = 'La especialidad es obligatoria'
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
    console.error('Error saving specialty record:', err)
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
      specialty: props.record.specialty || '',
      title: props.record.title || '',
      description: props.record.description || '',
      implant_count: props.record.implant_count || '',
      implant_type: props.record.implant_type || '',
      position: props.record.position || '',
      treatment_type: props.record.treatment_type || '',
      estimated_duration: props.record.estimated_duration || '',
      main_problem: props.record.main_problem || '',
      tooth_number: props.record.tooth_number || '',
      canal_count: props.record.canal_count || '',
      obturation_material: props.record.obturation_material || '',
      prosthesis_type: props.record.prosthesis_type || '',
      material: props.record.material || '',
      involved_teeth: props.record.involved_teeth || '',
      surgery_type: props.record.surgery_type || '',
      anesthesia: props.record.anesthesia || '',
      complications: props.record.complications || '',
      procedure_date: props.record.procedure_date || '',
      professional_name: props.record.professional_name || '',
      observations: props.record.observations || '',
      next_appointment: props.record.next_appointment || ''
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

.specialty-fields {
  @apply space-y-4;
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
