<template>
  <div class="modal-overlay" @click="closeModal">
    <div class="modal-content" @click.stop>
      <div class="modal-header">
        <h2 class="modal-title">Aprobar Presupuesto</h2>
        <button class="modal-close" @click="closeModal">
          <XMarkIcon class="w-6 h-6" />
        </button>
      </div>

      <div class="modal-body">
        <div class="approval-info">
          <div class="quotation-summary">
            <h3 class="summary-title">Resumen del Presupuesto</h3>
            <div class="summary-details">
              <div class="detail-row">
                <span class="label">Número:</span>
                <span class="value">#{{ quotation.quotation_number }}</span>
              </div>
              <div class="detail-row">
                <span class="label">Paciente:</span>
                <span class="value">
                  {{ quotation.patient?.first_name }} {{ quotation.patient?.last_name }}
                </span>
              </div>
              <div class="detail-row">
                <span class="label">Total:</span>
                <span class="value">S/ {{ formatPrice(quotation.total_amount) }}</span>
              </div>
            </div>
          </div>

          <form class="approval-form" @submit.prevent="handleSubmit">
            <div class="form-group">
              <label class="form-label">Comentarios de Aprobación</label>
              <textarea
                v-model="form.comments"
                class="form-textarea"
                rows="3"
                placeholder="Comentarios adicionales sobre la aprobación..."
              />
            </div>

            <div class="form-group">
              <label class="form-label">Fecha de Aprobación</label>
              <input v-model="form.approved_at" type="datetime-local"
class="form-input" required
/>
            </div>

            <div class="form-group">
              <label class="form-label">Aprobado por</label>
              <input
                v-model="form.approved_by"
                type="text"
                class="form-input"
                placeholder="Nombre del aprobador"
                required
              />
            </div>

            <div class="form-group">
              <label class="form-label">Condiciones Especiales</label>
              <textarea
                v-model="form.conditions"
                class="form-textarea"
                rows="2"
                placeholder="Condiciones especiales o restricciones..."
              />
            </div>
          </form>
        </div>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-outline" :disabled="loading"
@click="closeModal">
          Cancelar
        </button>
        <button type="submit" class="btn btn-primary" :disabled="loading"
@click="handleSubmit">
          <CheckIcon class="w-4 h-4 mr-2" />
          {{ loading ? 'Aprobando...' : 'Aprobar Presupuesto' }}
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useQuotations } from '@/composables/useQuotations'
import { XMarkIcon, CheckIcon } from '@heroicons/vue/24/outline'

const props = defineProps({
  quotation: {
    type: Object,
    required: true
  }
})

const emit = defineEmits(['close', 'approved'])

const { approveQuotation, loading } = useQuotations()

const form = ref({
  comments: '',
  approved_at: '',
  approved_by: '',
  conditions: ''
})

const handleSubmit = async () => {
  try {
    const approvalData = {
      comments: form.value.comments,
      approved_at: form.value.approved_at,
      approved_by: form.value.approved_by,
      conditions: form.value.conditions
    }

    await approveQuotation(props.quotation.id, approvalData)
    emit('approved', props.quotation)
    closeModal()
  } catch (err) {}
}

const closeModal = () => {
  emit('close')
}

const formatPrice = price => {
  return Number(price).toFixed(2)
}

onMounted(() => {
  // Set default values
  form.value.approved_at = new Date().toISOString().slice(0, 16)
  form.value.approved_by = 'Usuario Actual' // This should come from auth context
})
</script>

<style scoped>
.modal-overlay {
  @apply fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4;
}

.modal-content {
  @apply bg-theme-surface-elevated rounded-lg shadow-xl max-w-2xl w-full max-h-[90vh] overflow-y-auto;
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

.approval-info {
  @apply space-y-6;
}

.quotation-summary {
  @apply bg-theme-surface rounded-lg p-4;
}

.summary-title {
  @apply text-lg font-medium text-theme-primary mb-3;
}

.summary-details {
  @apply space-y-2;
}

.detail-row {
  @apply flex justify-between items-center;
}

.label {
  @apply text-sm font-medium text-theme-secondary;
}

.value {
  @apply text-sm text-theme-primary;
}

.approval-form {
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
