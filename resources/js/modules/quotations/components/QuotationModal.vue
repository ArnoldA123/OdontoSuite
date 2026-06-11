<template>
  <div class="modal-overlay" @click="closeModal">
    <div class="modal-content" @click.stop>
      <div class="modal-header">
        <h2 class="modal-title">
          {{ isEdit ? 'Editar Presupuesto' : 'Nuevo Presupuesto' }}
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
                v-model="form.patient_id"
                :error="errors.patient_id"
                @change="handlePatientChange"
              />
            </div>

            <div class="form-group">
              <label class="form-label">Plan de Tratamiento</label>
              <TreatmentPlanSelector
                v-model="form.treatment_plan_id"
                :patient-id="form.patient_id"
                @change="handleTreatmentPlanChange"
              />
            </div>

            <div class="form-row">
              <div class="form-group">
                <label class="form-label">Fecha del presupuesto</label>
                <input
                  v-model="form.quotation_date"
                  type="date"
                  class="form-input"
                />
              </div>

              <div class="form-group">
                <label class="form-label">Válido hasta</label>
                <input
                  v-model="form.valid_until"
                  type="date"
                  class="form-input"
                />
              </div>
            </div>
          </div>

          <!-- Items del presupuesto -->
          <div class="form-section">
            <h3 class="section-title">Items del Presupuesto</h3>

            <div class="items-list">
              <div
                v-for="(item, index) in form.items"
                :key="index"
                class="item-row"
              >
                <div class="item-content">
                  <div class="item-description">
                    <input
                      v-model="item.description"
                      type="text"
                      class="form-input"
                      placeholder="Descripción del item"
                    />
                  </div>

                  <div class="item-details">
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

                    <div class="item-total">
                      S/ {{ formatPrice(item.quantity * item.unit_price) }}
                    </div>
                  </div>
                </div>

                <button
                  type="button"
                  @click="removeItem(index)"
                  class="item-remove"
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
              Agregar Item
            </button>
          </div>

          <!-- Descuentos e impuestos -->
          <div class="form-section">
            <h3 class="section-title">Descuentos e Impuestos</h3>

            <div class="form-row">
              <div class="form-group">
                <label class="form-label">Descuento (%)</label>
                <input
                  v-model.number="form.discount_percentage"
                  type="number"
                  class="form-input"
                  min="0"
                  max="100"
                  step="0.01"
                  @input="calculateDiscount"
                />
              </div>

              <div class="form-group">
                <label class="form-label">Descuento (S/)</label>
                <input
                  v-model.number="form.discount_amount"
                  type="number"
                  class="form-input"
                  min="0"
                  step="0.01"
                  @input="calculateDiscount"
                />
              </div>
            </div>

            <div class="form-row">
              <div class="form-group">
                <label class="form-label">IGV (%)</label>
                <input
                  v-model.number="form.tax_percentage"
                  type="number"
                  class="form-input"
                  min="0"
                  max="100"
                  step="0.01"
                  @input="calculateTax"
                />
              </div>

              <div class="form-group">
                <label class="form-label">IGV (S/)</label>
                <input
                  v-model.number="form.tax_amount"
                  type="number"
                  class="form-input"
                  min="0"
                  step="0.01"
                  @input="calculateTax"
                />
              </div>
            </div>
          </div>

          <!-- Términos y condiciones -->
          <div class="form-section">
            <h3 class="section-title">Términos y Condiciones</h3>

            <div class="form-group">
              <label class="form-label">Términos y condiciones</label>
              <RichTextEditor
                v-model="form.terms_conditions"
                placeholder="Términos y condiciones del presupuesto..."
              />
            </div>

            <div class="form-group">
              <label class="form-label">Notas adicionales</label>
              <textarea
                v-model="form.notes"
                class="form-textarea"
                rows="3"
                placeholder="Notas adicionales..."
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
            <div v-if="form.discount_amount > 0" class="cost-row">
              <span>Descuento:</span>
              <span>- S/ {{ formatPrice(form.discount_amount) }}</span>
            </div>
            <div v-if="form.tax_amount > 0" class="cost-row">
              <span>IGV:</span>
              <span>S/ {{ formatPrice(form.tax_amount) }}</span>
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
          {{ isEdit ? 'Actualizar' : 'Crear' }} Presupuesto
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, watch, onMounted } from 'vue'
import { useQuotations } from '@/composables/useQuotations'
import PatientSelector from '@/components/ui/PatientSelector.vue'
import TreatmentPlanSelector from '@/components/ui/TreatmentPlanSelector.vue'
import RichTextEditor from '@/components/ui/RichTextEditor.vue'
import { XMarkIcon, PlusIcon, TrashIcon } from '@heroicons/vue/24/outline'

const props = defineProps({
  quotation: {
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
const { createQuotation, updateQuotation, loading } = useQuotations()

// Estado reactivo
const form = ref({
  patient_id: '',
  treatment_plan_id: '',
  quotation_date: '',
  valid_until: '',
  subtotal: 0,
  discount_percentage: 0,
  discount_amount: 0,
  tax_percentage: 0,
  tax_amount: 0,
  total_amount: 0,
  terms_conditions: '',
  notes: '',
  items: []
})

const errors = ref({})

// Computed
const subtotal = computed(() => {
  return form.value.items.reduce((total, item) => {
    return total + (item.quantity * item.unit_price)
  }, 0)
})

const finalCost = computed(() => {
  return subtotal.value - form.value.discount_amount + form.value.tax_amount
})

// Métodos
const handlePatientChange = (patient) => {
  form.value.patient_id = patient?.id || ''
}

const handleTreatmentPlanChange = (treatmentPlan) => {
  form.value.treatment_plan_id = treatmentPlan?.id || ''

  if (treatmentPlan && treatmentPlan.items) {
    form.value.items = treatmentPlan.items.map(item => ({
      description: item.description,
      quantity: item.quantity,
      unit_price: item.unit_price
    }))
  }
}

const addItem = () => {
  form.value.items.push({
    description: '',
    quantity: 1,
    unit_price: 0
  })
}

const removeItem = (index) => {
  form.value.items.splice(index, 1)
}

const calculateDiscount = () => {
  if (form.value.discount_percentage > 0) {
    form.value.discount_amount = subtotal.value * (form.value.discount_percentage / 100)
  }
}

const calculateTax = () => {
  if (form.value.tax_percentage > 0) {
    const subtotalAfterDiscount = subtotal.value - form.value.discount_amount
    form.value.tax_amount = subtotalAfterDiscount * (form.value.tax_percentage / 100)
  }
}

const handleSubmit = async () => {
  try {
    errors.value = {}

    // Validaciones básicas
    if (!form.value.patient_id) {
      errors.value.patient_id = 'El paciente es obligatorio'
    }
    if (form.value.items.length === 0) {
      errors.value.items = 'Debe agregar al menos un item'
    }

    if (Object.keys(errors.value).length > 0) {
      return
    }

    // Preparar datos
    const data = {
      ...form.value,
      subtotal: subtotal.value,
      total_amount: finalCost.value,
      items: form.value.items.filter(item => item.description.trim() !== '')
    }

    if (props.isEdit) {
      await updateQuotation(props.quotation.id, data)
    } else {
      await createQuotation(data)
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

const formatPrice = (price) => {
  return new Intl.NumberFormat('es-PE', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2
  }).format(price || 0)
}

const initializeForm = () => {
  if (props.quotation) {
    form.value = {
      patient_id: props.quotation.patient_id || '',
      treatment_plan_id: props.quotation.treatment_plan_id || '',
      quotation_date: props.quotation.quotation_date || '',
      valid_until: props.quotation.valid_until || '',
      subtotal: props.quotation.subtotal || 0,
      discount_percentage: props.quotation.discount_percentage || 0,
      discount_amount: props.quotation.discount_amount || 0,
      tax_percentage: props.quotation.tax_percentage || 0,
      tax_amount: props.quotation.tax_amount || 0,
      total_amount: props.quotation.total_amount || 0,
      terms_conditions: props.quotation.terms_conditions || '',
      notes: props.quotation.notes || '',
      items: props.quotation.items || []
    }
  } else {
    // Valores por defecto
    form.value.quotation_date = new Date().toISOString().split('T')[0]
    form.value.valid_until = new Date(Date.now() + 30 * 24 * 60 * 60 * 1000).toISOString().split('T')[0]
    addItem()
  }
}

// Watchers
watch(() => props.quotation, () => {
  initializeForm()
}, { immediate: true })

watch(subtotal, () => {
  calculateDiscount()
  calculateTax()
})

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

.items-list {
  @apply space-y-3 mb-4;
}

.item-row {
  @apply flex items-center space-x-3 p-3 border border-theme rounded-lg;
}

.item-content {
  @apply flex-1 space-y-2;
}

.item-description {
  @apply w-full;
}

.item-details {
  @apply grid grid-cols-3 gap-2;
}

.item-total {
  @apply flex items-center justify-center text-sm font-medium text-theme-secondary;
}

.item-remove {
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
