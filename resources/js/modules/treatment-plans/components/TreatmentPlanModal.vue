<template>
  <div class="modal-overlay" @click="closeModal">
    <div class="modal-content" @click.stop>
      <div class="modal-header">
        <h2 class="modal-title">
          {{ isEdit ? 'Editar Plan de Tratamiento' : 'Nuevo Plan de Tratamiento' }}
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
              <label class="form-label">Título *</label>
              <input
                v-model="form.title"
                type="text"
                class="form-input"
                :class="{ 'border-red-500': errors.title }"
                placeholder="Ej: Rehabilitación completa superior"
              />
              <p v-if="errors.title" class="form-error">{{ errors.title }}</p>
            </div>

            <div class="form-group">
              <label class="form-label">Descripción</label>
              <textarea
                v-model="form.description"
                class="form-textarea"
                rows="3"
                placeholder="Descripción del plan de tratamiento..."
              ></textarea>
            </div>
          </div>

          <!-- Configuración del plan -->
          <div class="form-section">
            <h3 class="section-title">Configuración del Plan</h3>

            <div class="form-row">
              <div class="form-group">
                <label class="form-label">Duración estimada (semanas)</label>
                <input
                  v-model.number="form.estimated_duration_weeks"
                  type="number"
                  class="form-input"
                  min="1"
                  max="104"
                />
              </div>

              <div class="form-group">
                <label class="form-label">Fecha de inicio</label>
                <input
                  v-model="form.start_date"
                  type="date"
                  class="form-input"
                />
              </div>
            </div>

            <div class="form-row">
              <div class="form-group">
                <label class="form-label">Fecha de fin</label>
                <input
                  v-model="form.end_date"
                  type="date"
                  class="form-input"
                />
              </div>

              <div class="form-group">
                <label class="form-label">Fases del tratamiento</label>
                <input
                  v-model="form.phases"
                  type="text"
                  class="form-input"
                  placeholder="Ej: Fase 1, Fase 2, Fase 3"
                />
              </div>
            </div>

            <div class="form-row">
              <div class="form-group">
                <label class="form-label flex items-center">
                  <input
                    v-model="form.requires_anesthesia"
                    type="checkbox"
                    class="mr-2"
                  />
                  Requiere anestesia
                </label>
              </div>

              <div class="form-group">
                <label class="form-label flex items-center">
                  <input
                    v-model="form.is_urgent"
                    type="checkbox"
                    class="mr-2"
                  />
                  Tratamiento urgente
                </label>
              </div>
            </div>
          </div>

          <!-- Procedimientos -->
          <div class="form-section">
            <h3 class="section-title">Procedimientos</h3>

            <div class="procedures-list">
              <div
                v-for="(item, index) in form.items"
                :key="index"
                class="procedure-item"
              >
                <div class="procedure-content">
                  <div class="procedure-description">
                    <input
                      v-model="item.description"
                      type="text"
                      class="form-input"
                      placeholder="Descripción del procedimiento"
                    />
                  </div>

                  <div class="procedure-details">
                    <input
                      v-model.number="item.quantity"
                      type="number"
                      class="form-input"
                      placeholder="Cantidad"
                      min="0.01"
                      step="0.01"
                    />

                    <input
                      v-model.number="item.unit_price"
                      type="number"
                      class="form-input"
                      placeholder="Precio unitario"
                      min="0"
                      step="0.01"
                    />

                    <input
                      v-model="item.category"
                      type="text"
                      class="form-input"
                      placeholder="Categoría"
                    />
                  </div>
                </div>

                <button
                  type="button"
                  @click="removeItem(index)"
                  class="procedure-remove"
                >
                  <TrashIcon class="w-4 h-4" />
                </button>
              </div>
            </div>

            <button
              type="button"
              @click="addItem"
              class="btn btn-outline btn-sm"
            >
              <PlusIcon class="w-4 h-4 mr-1" />
              Agregar Procedimiento
            </button>
          </div>

          <!-- Notas -->
          <div class="form-section">
            <h3 class="section-title">Notas</h3>

            <div class="form-group">
              <label class="form-label">Notas internas</label>
              <textarea
                v-model="form.notes"
                class="form-textarea"
                rows="3"
                placeholder="Notas para el equipo médico..."
              ></textarea>
            </div>

            <div class="form-group">
              <label class="form-label">Notas para el paciente</label>
              <textarea
                v-model="form.patient_notes"
                class="form-textarea"
                rows="3"
                placeholder="Información para el paciente..."
              ></textarea>
            </div>
          </div>
        </div>

        <!-- Resumen de costos -->
        <div v-if="form.items.length > 0" class="cost-summary">
          <h3 class="section-title">Resumen de Costos</h3>
          <div class="cost-details">
            <div class="cost-row">
              <span>Subtotal:</span>
              <span>S/ {{ formatPrice(subtotal) }}</span>
            </div>
            <div class="cost-row">
              <span>Descuento:</span>
              <span>- S/ {{ formatPrice(discountAmount) }}</span>
            </div>
            <div class="cost-row total">
              <span>Total:</span>
              <span>S/ {{ formatPrice(finalCost) }}</span>
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
          <span v-if="loading" class="animate-spin rounded-full h-4 w-4 border-b-2 border-white mr-2"></span>
          {{ isEdit ? 'Actualizar' : 'Crear' }} Plan
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, watch, onMounted } from 'vue'
import { useTreatmentPlans } from '@/composables/useTreatmentPlans'
import PatientSelector from '@/components/ui/PatientSelector.vue'
import { XMarkIcon, PlusIcon, TrashIcon } from '@heroicons/vue/24/outline'

const props = defineProps({
  plan: {
    type: Object,
    default: null
  },
  isEdit: {
    type: Boolean,
    default: false
  }
})

const emit = defineEmits(['close', 'saved'])

// Composables
const { createPlan, updatePlan, loading } = useTreatmentPlans()

// Estado reactivo
const selectedPatient = ref(null)

const form = ref({
  patient_id: '',
  title: '',
  description: '',
  estimated_duration_weeks: null,
  start_date: '',
  end_date: '',
  phases: '',
  requires_anesthesia: false,
  is_urgent: false,
  notes: '',
  patient_notes: '',
  items: []
})

const errors = ref({})

// Computed
const subtotal = computed(() => {
  return form.value.items.reduce((total, item) => {
    return total + (item.quantity * item.unit_price)
  }, 0)
})

const discountAmount = computed(() => {
  return 0 // Por ahora sin descuentos
})

const finalCost = computed(() => {
  return subtotal.value - discountAmount.value
})

// Métodos
const handlePatientChange = (patient) => {
  selectedPatient.value = patient
  form.value.patient_id = patient?.id || ''
}

const addItem = () => {
  form.value.items.push({
    description: '',
    quantity: 1,
    unit_price: 0,
    category: ''
  })
}

const removeItem = (index) => {
  form.value.items.splice(index, 1)
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

    if (Object.keys(errors.value).length > 0) {
      return
    }

    // Preparar datos
    const data = {
      ...form.value,
      items: form.value.items.filter(item => item.description.trim() !== '')
    }

    // Debug: verificar datos antes de enviar
    console.log('Datos a enviar:', data)
    console.log('Patient ID:', data.patient_id, 'Tipo:', typeof data.patient_id)

    if (props.isEdit) {
      await updatePlan(props.plan.id, data)
    } else {
      await createPlan(data)
    }

    emit('saved')
  } catch (err) {
    console.error('Error saving plan:', err)
    if (err.response?.data?.errors) {
      errors.value = err.response.data.errors
    }
  }
}

const closeModal = () => {
  emit('close')
}

const formatPrice = (price) => {
  return new Intl.NumberFormat('es-PE', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2
  }).format(price || 0)
}

const initializeForm = () => {
  if (props.plan) {
    form.value = {
      patient_id: props.plan.patient_id || '',
      title: props.plan.title || '',
      description: props.plan.description || '',
      estimated_duration_weeks: props.plan.estimated_duration_weeks || null,
      start_date: props.plan.start_date || '',
      end_date: props.plan.end_date || '',
      phases: props.plan.phases || '',
      requires_anesthesia: props.plan.requires_anesthesia || false,
      is_urgent: props.plan.is_urgent || false,
      notes: props.plan.notes || '',
      patient_notes: props.plan.patient_notes || '',
      items: props.plan.items || []
    }
  } else {
    // Agregar un item por defecto
    addItem()
  }
}

// Watchers
watch(() => props.plan, () => {
  initializeForm()
}, { immediate: true })

// Lifecycle
onMounted(() => {
  initializeForm()
})
</script>

<style scoped>
.modal-overlay {
  @apply fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4;
}

.modal-content {
  @apply bg-theme-surface-elevated rounded-lg shadow-xl max-w-4xl w-full max-h-[90vh] overflow-hidden flex flex-col;
}

.modal-header {
  @apply flex justify-between items-center p-6 border-b border-theme;
}

.modal-title {
  @apply text-xl font-semibold text-theme-primary;
}

.modal-close {
  @apply text-theme-secondary hover:text-theme-primary transition-colors;
}

.modal-body {
  @apply flex-1 overflow-y-auto p-6;
}

.form-grid {
  @apply space-y-6;
}

.form-section {
  @apply space-y-4;
}

.section-title {
  @apply text-lg font-medium text-theme-primary border-b border-theme pb-2;
}

.form-row {
  @apply grid grid-cols-1 md:grid-cols-2 gap-4;
}

.form-group {
  @apply space-y-1;
}

.form-label {
  @apply block text-sm font-medium text-theme-primary;
}

.form-input {
  @apply w-full px-3 py-2 border border-theme rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent bg-theme-surface-elevated text-theme-primary;
}

.form-textarea {
  @apply w-full px-3 py-2 border border-theme rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent resize-none bg-theme-surface-elevated text-theme-primary;
}

.form-error {
  @apply text-sm text-red-600;
}

.procedures-list {
  @apply space-y-3 mb-4;
}

.procedure-item {
  @apply flex items-center space-x-3 p-3 border border-theme rounded-lg;
}

.procedure-content {
  @apply flex-1 space-y-2;
}

.procedure-description {
  @apply w-full;
}

.procedure-details {
  @apply grid grid-cols-3 gap-2;
}

.procedure-remove {
  @apply text-red-500 hover:text-red-700 transition-colors;
}

.cost-summary {
  @apply mt-6 p-4 bg-theme-surface rounded-lg;
}

.cost-details {
  @apply space-y-2;
}

.cost-row {
  @apply flex justify-between items-center;
}

.cost-row.total {
  @apply font-semibold text-lg border-t border-theme pt-2;
}

.modal-footer {
  @apply flex justify-end space-x-3 p-6 border-t border-theme;
}

.btn {
  @apply inline-flex items-center justify-center px-4 py-2 border border-transparent text-sm font-medium rounded-md transition-colors;
}

.btn-sm {
  @apply px-3 py-1 text-xs;
}

.btn-outline {
  @apply border-theme text-theme-primary bg-theme-surface-elevated hover:bg-theme-surface;
}

.btn-primary {
  @apply bg-primary-600 text-white hover:bg-primary-700;
}
</style>
