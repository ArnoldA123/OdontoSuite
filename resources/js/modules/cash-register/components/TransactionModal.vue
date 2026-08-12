<template>
  <Modal
    :model-value="show"
    :title="isRefund ? 'Registrar Egreso' : 'Registrar Ingreso'"
    size="lg"
    @update:model-value="$emit('close')"
    @close="$emit('close')"
  >
    <form
      @submit.prevent="handleSubmit"
    >
      <div class="space-y-4 bg-canvas">
        <!-- Estado (Ingreso / Egreso) -->
        <UiStatusBadge
          :variant="isRefund ? 'error' : 'success'"
          :label="isRefund ? 'Egreso' : 'Ingreso'"
          size="md"
        />
        <!-- Búsqueda de Paciente -->
        <div>
          <label class="block text-sm font-medium text-theme-primary mb-1">
            Paciente <span class="text-red-500">*</span>
          </label>
          <div class="relative">
            <input
              v-model="patientSearch"
              type="text"
              :class="inputClasses"
              placeholder="Buscar paciente por nombre, DNI o email..."
              @input="searchPatients"
              @focus="showPatientResults = true"
            />
            <div v-if="searchingPatients" class="absolute inset-y-0 right-0 pr-3 flex items-center">
              <UiLoadingSpinner size="xs" variant="primary" :centered="false" aria-label="Buscando pacientes" />
            </div>
          </div>

          <!-- Resultados de búsqueda -->
          <div v-if="showPatientResults && patientResults.length > 0" class="absolute z-10 w-full mt-1 bg-theme-surface-elevated border border-hairline rounded-md shadow-lg max-h-60 overflow-auto">
            <div
              v-for="patient in patientResults"
              :key="patient.id"
              class="px-4 py-2 hover:bg-theme-surface cursor-pointer border-b border-hairline last:border-b-0"
              @click="selectPatient(patient)"
            >
              <div class="font-medium text-theme-primary">{{ patient.name }} {{ patient.last_name }}</div>
              <div class="text-sm text-theme-secondary">{{ patient.dni }} - {{ patient.email }}</div>
            </div>
          </div>

          <!-- Paciente seleccionado -->
          <UiCard variant="flat" padding="sm" class="mt-2">
            <div v-if="selectedPatient" class="flex justify-between items-center">
              <div>
                <div class="font-medium text-theme-primary">{{ selectedPatient.name }} {{ selectedPatient.last_name }}</div>
                <div class="text-sm text-theme-secondary">{{ selectedPatient.dni }} - {{ selectedPatient.email }}</div>
              </div>
              <button
                type="button"
                @click="clearPatient"
                class="text-systemBlue-600 hover:text-systemBlue-700"
              >
                <XMarkIcon class="w-4 h-4" />
              </button>
            </div>
          </UiCard>
        </div>

        <!-- Tipo de Transacción -->
        <div>
          <label class="block text-sm font-medium text-theme-primary mb-1">
            Tipo de Transacción <span class="text-red-500">*</span>
          </label>
          <select
            v-model="formData.type"
            :class="inputClasses"
            :disabled="loading"
          >
            <option value="payment">Ingreso</option>
            <option value="refund">Egreso</option>
          </select>
        </div>

        <!-- Cita o Plan de Tratamiento -->
        <div>
          <label class="block text-sm font-medium text-theme-primary mb-1">
            {{ formData.type === 'payment' ? 'Cita o Plan de Tratamiento' : 'Concepto' }}
          </label>
          <select
            v-model="formData.appointment_id"
            :class="inputClasses"
            :disabled="loading"
            v-if="formData.type === 'payment'"
          >
            <option value="">Seleccionar cita</option>
            <option
              v-for="appointment in patientAppointments"
              :key="appointment.id"
              :value="appointment.id"
            >
              {{ formatAppointment(appointment) }}
            </option>
          </select>
          <input
            v-else
            v-model="formData.description"
            type="text"
            :class="inputClasses"
            :disabled="loading"
            placeholder="Descripción del egreso..."
          />
        </div>

        <!-- Método de Pago -->
        <div>
          <label class="block text-sm font-medium text-theme-primary mb-1">
            Método de Pago <span class="text-red-500">*</span>
          </label>
          <select
            v-model="formData.payment_method_id"
            :class="inputClasses"
            :disabled="loading"
          >
            <option value="">Seleccionar método de pago</option>
            <option
              v-for="method in paymentMethods"
              :key="method.id"
              :value="method.id"
            >
              {{ method.name }}
            </option>
          </select>
        </div>

        <!-- Monto -->
        <div>
          <CurrencyInput
            v-model="formData.amount"
            label="Monto"
            placeholder="0.00"
            :required="true"
            :min="0.01"
            :precision="2"
            :error="errors.amount"
          />
        </div>

        <!-- Descuento -->
        <div v-if="formData.type === 'income'" class="space-y-3">
          <div class="flex items-center">
            <input
              v-model="applyDiscount"
              type="checkbox"
              class="h-4 w-4 text-systemBlue-500 border-hairline rounded"
            />
            <label class="ml-2 text-sm font-medium text-theme-primary">
              Aplicar descuento
            </label>
          </div>

          <div v-if="applyDiscount" class="grid grid-cols-2 gap-4">
            <div>
              <label class="block text-sm font-medium text-theme-primary mb-1">Tipo de Descuento</label>
              <select
                v-model="formData.discount_type"
                :class="inputClasses"
                :disabled="loading"
              >
                <option value="percentage">Porcentaje</option>
                <option value="fixed">Monto fijo</option>
              </select>
            </div>

            <div>
              <label class="block text-sm font-medium text-theme-primary mb-1">
                {{ formData.discount_type === 'percentage' ? 'Porcentaje (%)' : 'Monto (S/.)' }}
              </label>
              <CurrencyInput
                v-model="formData.discount_amount"
                :placeholder="formData.discount_type === 'percentage' ? '0' : '0.00'"
                :min="0"
                :max="formData.discount_type === 'percentage' ? 100 : formData.amount"
                :precision="formData.discount_type === 'percentage' ? 0 : 2"
                @input="calculateDiscount"
              />
            </div>
          </div>

          <!-- Autorización de descuento -->
          <div v-if="requiresAuthorization">
            <UiStatusBadge
              variant="warning"
              size="md"
              label="Descuento mayor al 10% requiere autorización del administrador"
            />
          </div>
        </div>

        <!-- Referencia de Pago -->
        <div v-if="needsReference">
          <label class="block text-sm font-medium text-theme-primary mb-1">
            Número de Referencia
          </label>
          <input
            v-model="formData.reference_number"
            type="text"
            :class="inputClasses"
            :disabled="loading"
            placeholder="Número de operación, voucher, etc."
          />
        </div>

        <!-- Notas -->
        <div>
          <label class="block text-sm font-medium text-theme-primary mb-1">
            Notas
          </label>
          <textarea
            v-model="formData.notes"
            :class="inputClasses"
            :disabled="loading"
            rows="3"
            placeholder="Notas adicionales sobre la transacción..."
            maxlength="500"
          ></textarea>
          <p class="mt-1 text-sm text-theme-secondary">
            {{ formData.notes?.length || 0 }}/500 caracteres
          </p>
        </div>

        <!-- Resumen -->
        <div class="bg-theme-surface border border-hairline rounded-lg p-4">
          <h3 class="text-sm font-semibold text-theme-primary mb-2">Resumen de la Transacción</h3>
          <div class="space-y-1 text-sm">
            <div class="flex justify-between">
              <span class="text-theme-secondary">Subtotal:</span>
              <span class="font-medium text-theme-primary tabular-nums">{{ formatCurrency(formData.amount) }}</span>
            </div>
            <div v-if="discountAmount > 0" class="flex justify-between">
              <span class="text-theme-secondary">Descuento:</span>
              <span class="font-medium text-systemRed-600 tabular-nums">-{{ formatCurrency(discountAmount) }}</span>
            </div>
            <div class="flex justify-between border-t border-hairline pt-1">
              <span class="font-semibold text-theme-primary">Total:</span>
              <span class="font-bold text-lg text-theme-primary tabular-nums">{{ formatCurrency(totalAmount) }}</span>
            </div>
          </div>
        </div>
      </div>

    </form>

    <template #footer>
      <div class="flex justify-end space-x-3">
        <Button
          type="button"
          variant="secondary"
          @click="$emit('close')"
          :disabled="loading"
        >
          Cancelar
        </Button>
        <Button
          type="submit"
          variant="primary"
          :loading="loading"
          :disabled="!canSubmit"
          @click="handleSubmit"
        >
          <PlusIcon class="w-4 h-4 mr-2" />
          {{ isRefund ? 'Registrar devolución' : 'Registrar pago' }}
        </Button>
      </div>
    </template>
  </Modal>
</template>

<script setup>
import { ref, computed, watch, onMounted } from 'vue'
import Modal from '@/components/ui/Modal.vue'
import Button from '@/components/ui/Button.vue'
import UiCard from '@/components/ui/Card.vue'
import UiStatusBadge from '@/components/ui/StatusBadge.vue'
import UiLoadingSpinner from '@/components/ui/LoadingSpinner.vue'
import CurrencyInput from '@/components/ui/CurrencyInput.vue'
import { useTransactions } from '@/composables/useTransactions'
import { useApi } from '@/composables/useApi'
import { usePermissions } from '@/composables/usePermissions'
import { formatCurrency } from '@/composables/useFormatters'
import { PlusIcon, XMarkIcon } from '@heroicons/vue/24/outline'

const props = defineProps({
  show: {
    type: Boolean,
    default: false
  },
  // PR-pagos-03 — additive `type` prop. Default `'payment'` (Ingreso).
  // Caller passes `type="refund"` for the Egreso flow (e.g. the "Devolución"
  // button on TransactionList). The reactivity logic of the modal is
  // untouched; only the title + primary button label + status badge
  // variant follow the prop value (PAGOS-CON-001 / design §3.2).
  type: {
    type: String,
    default: 'payment',
    validator: v => ['payment', 'refund'].includes(v)
  }
})

const emit = defineEmits(['close', 'success'])

const { createTransaction } = useTransactions()
const { get } = useApi()
const { applyLargeDiscount } = usePermissions()

// PR-pagos-03 §3.2 — `isRefund` drives the modal title + primary button
// label + status badge variant per the `PAGOS-MOD-001-1` ramp mapping.
const isRefund = computed(() => props.type === 'refund')

// Estado
const loading = ref(false)
const errors = ref({})
const patientSearch = ref('')
const showPatientResults = ref(false)
const searchingPatients = ref(false)
const patientResults = ref([])
const selectedPatient = ref(null)
const patientAppointments = ref([])
const paymentMethods = ref([])
const applyDiscount = ref(false)

// Formulario
const formData = ref({
  patient_id: null,
  appointment_id: null,
  treatment_plan_id: null,
  payment_method_id: null,
  type: 'payment',
  amount: 0,
  description: '',
  discount_type: 'percentage',
  discount_amount: 0,
  discount_authorized_by: null,
  notes: '',
  reference_number: ''
})

// Validación simple
const validateForm = () => {
  let valid = true
  if (!formData.value.patient_id) {
    errors.value.patient_id = 'El paciente es requerido'
    valid = false
  }
  if (!formData.value.payment_method_id) {
    errors.value.payment_method_id = 'El método de pago es requerido'
    valid = false
  }
  if (!formData.value.amount || formData.value.amount <= 0) {
    errors.value.amount = 'El monto debe ser mayor a 0'
    valid = false
  }
  return valid
}

// Computed
const needsReference = computed(() => {
  const method = paymentMethods.value.find(m => m.id === formData.value.payment_method_id)
  return method && ['tarjeta_debito', 'tarjeta_credito', 'transferencia'].includes(method.name.toLowerCase())
})

const requiresAuthorization = computed(() => {
  if (!applyDiscount.value || !formData.value.amount || !formData.value.discount_amount) return false

  const discountPercentage = (formData.value.discount_amount / formData.value.amount) * 100
  return discountPercentage > 10 && !applyLargeDiscount.value
})

const discountAmount = computed(() => {
  if (!applyDiscount.value || !formData.value.discount_amount || !formData.value.amount) return 0

  if (formData.value.discount_type === 'percentage') {
    return (formData.value.amount * formData.value.discount_amount) / 100
  } else {
    return Math.min(formData.value.discount_amount, formData.value.amount)
  }
})

const totalAmount = computed(() => {
  return formData.value.amount - discountAmount.value
})

const canSubmit = computed(() => {
  return selectedPatient.value &&
         formData.value.payment_method_id &&
         formData.value.amount > 0 &&
         !loading.value
})

const inputClasses = computed(() => {
  const base = 'block w-full px-3 py-2 border border-hairline rounded-md shadow-sm bg-theme-surface-elevated text-theme-primary focus:outline-none sm:text-sm'
  return loading.value ? `${base} bg-theme-surface cursor-not-allowed opacity-50` : `${base}`
})

// Métodos
const loadPaymentMethods = async () => {
  try {
    const response = await get('/api/payment-methods')
    paymentMethods.value = response.data || []
  } catch (error) {
  }
}

const searchPatients = async () => {
  if (patientSearch.value.length < 2) {
    patientResults.value = []
    return
  }

  searchingPatients.value = true
  try {
    const response = await get('/api/patients/search', {
      params: { q: patientSearch.value }
    })
    patientResults.value = response.data || []
  } catch (error) {
  } finally {
    searchingPatients.value = false
  }
}

const selectPatient = async (patient) => {
  selectedPatient.value = patient
  formData.value.patient_id = patient.id
  patientSearch.value = `${patient.name} ${patient.last_name}`
  showPatientResults.value = false

  // Cargar citas del paciente
  await loadPatientAppointments(patient.id)
}

const clearPatient = () => {
  selectedPatient.value = null
  formData.value.patient_id = null
  patientSearch.value = ''
  patientAppointments.value = []
}

const loadPatientAppointments = async (patientId) => {
  try {
    const response = await get('/api/appointments', {
      params: { patient_id: patientId, status: 'scheduled' }
    })
    patientAppointments.value = response.data || []
  } catch (error) {
  }
}

const calculateDiscount = () => {
  // Auto-calcular descuento si es porcentaje
  if (formData.value.discount_type === 'percentage' && formData.value.discount_amount > 100) {
    formData.value.discount_amount = 100
  }
}

const handleSubmit = async () => {
  loading.value = true
  errors.value = {}

  if (!validateForm()) {
    loading.value = false
    return
  }

  try {
    const submitData = {
      ...formData.value,
      patient_id: selectedPatient.value.id,
      description: formData.value.type === 'payment' ?
        (patientAppointments.value.find(a => a.id === formData.value.appointment_id)?.description || 'Pago de servicios') :
        formData.value.description
    }

    const result = await createTransaction(submitData)
    emit('success', result)
    emit('close')
  } catch (error) {
    if (error.response?.data?.errors) {
      errors.value = error.response.data.errors
    } else {
      errors.value = { general: error.message || 'Error al registrar la transacción' }
    }
  } finally {
    loading.value = false
  }
}

// formatCurrency is imported from useFormatters (PAGOS-MNY-002 / PR-pagos-01).

const formatAppointment = (appointment) => {
  return `${appointment.appointment_type?.name || 'Cita'} - ${new Date(appointment.scheduled_at).toLocaleDateString('es-PE')}`
}

// Watch
watch(() => props.show, (newValue) => {
  if (newValue) {
    // Reset form when modal opens
    formData.value = {
      patient_id: null,
      appointment_id: null,
      treatment_plan_id: null,
      payment_method_id: null,
      type: 'payment',
      amount: 0,
      description: '',
      discount_type: 'percentage',
      discount_amount: 0,
      discount_authorized_by: null,
      notes: '',
      reference_number: ''
    }

    selectedPatient.value = null
    patientSearch.value = ''
    patientResults.value = []
    patientAppointments.value = []
    applyDiscount.value = false
    errors.value = {}
  }
})

// Lifecycle
onMounted(() => {
  loadPaymentMethods()
})
</script>

