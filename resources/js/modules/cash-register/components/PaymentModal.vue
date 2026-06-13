<template>
  <Modal
    :model-value="show"
    title="Registrar Cobro de Paciente"
    size="xl"
    @update:model-value="$emit('close')"
    @close="$emit('close')"
    class="overflow-y-auto"
  >
    <form @submit.prevent="handleSubmit" class="space-y-4 md:space-y-6">
      <!-- Información del Paciente -->
      <div class="bg-theme-surface border border-theme rounded-lg p-3 md:p-4">
        <h3 class="text-sm md:text-base font-semibold text-theme-primary mb-3">Información del Paciente</h3>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-3 md:gap-4">
          <div>
            <label class="block text-xs md:text-sm font-medium text-theme-primary mb-1">
              Paciente
            </label>
            <select
              v-model="formData.patient_id"
              :disabled="!!selectedPatient"
              class="block w-full px-2 md:px-3 py-2 text-sm md:text-base border rounded-md shadow-sm
                     bg-theme-surface-elevated
                     text-theme-primary
                     border-theme
                     disabled:bg-theme-surface disabled:opacity-50
                     disabled:cursor-not-allowed
                     focus:ring-primary-500 focus:border-accent"
              :class="{ 'border-red-500': errors.patient_id }"
              required
              size="5"
            >
              <option value="">Seleccionar paciente</option>
              <option
                v-for="patient in pendingPatients"
                :key="patient.id"
                :value="patient.id"
              >
                {{ patient.name }} - {{ patient.document_number }}
              </option>
            </select>
            <p v-if="selectedPatient" class="mt-1 text-xs text-theme-secondary">
              Paciente seleccionado desde pagos pendientes
            </p>
            <p v-if="errors.patient_id" class="mt-1 text-xs md:text-sm text-red-600">
              {{ errors.patient_id[0] }}
            </p>
          </div>

          <div>
            <label class="block text-xs md:text-sm font-medium text-theme-primary mb-1">
              Cita Relacionada
            </label>
            <select
              v-model="formData.appointment_id"
              class="block w-full px-2 md:px-3 py-2 text-sm md:text-base border rounded-md shadow-sm
                     bg-theme-surface-elevated
                     text-theme-primary
                     border-theme
                     focus:ring-primary-500 focus:border-accent"
              :class="{ 'border-red-500': errors.appointment_id }"
            >
              <option value="">Seleccionar cita (opcional)</option>
              <option v-for="appointment in patientAppointments" :key="appointment.id" :value="appointment.id">
                {{ formatDate(appointment.date) }} - {{ appointment.appointment_type?.name }}
              </option>
            </select>
            <p v-if="errors.appointment_id" class="mt-1 text-xs md:text-sm text-red-600">
              {{ errors.appointment_id[0] }}
            </p>
          </div>
        </div>
      </div>

      <!-- Detalles del Pago -->
      <div class="space-y-3 md:space-y-4">
        <h3 class="text-base md:text-lg font-medium text-theme-primary">Detalles del Pago</h3>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-3 md:gap-4">
          <div>
            <label class="block text-xs md:text-sm font-medium text-theme-primary mb-1">
              Concepto *
            </label>
            <input
              v-model="formData.concept"
              type="text"
              placeholder="Ej: Consulta, Tratamiento, etc."
              class="block w-full px-2 md:px-3 py-2 text-sm md:text-base border rounded-md shadow-sm
                     bg-theme-surface-elevated
                     text-theme-primary
                     border-theme
                     placeholder-theme-secondary
                     focus:ring-primary-500 focus:border-accent"
              :class="{ 'border-red-500': errors.concept }"
              required
            />
            <p v-if="errors.concept" class="mt-1 text-xs md:text-sm text-red-600">
              {{ errors.concept[0] }}
            </p>
          </div>

          <div>
            <label class="block text-xs md:text-sm font-medium text-theme-primary mb-1">
              Metodo de Pago *
            </label>
            <select
              v-model="formData.payment_method_id"
              class="block w-full px-2 md:px-3 py-2 text-sm md:text-base border rounded-md shadow-sm
                     bg-theme-surface-elevated
                     text-theme-primary
                     border-theme
                     focus:ring-primary-500 focus:border-accent"
              :class="{ 'border-red-500': errors.payment_method_id }"
              :disabled="loadingMethods"
              required
            >
              <option value="">{{ loadingMethods ? 'Cargando metodos...' : (paymentMethods.length ? 'Seleccionar metodo' : 'No hay metodos de pago activos') }}</option>
              <option v-for="method in paymentMethods" :key="method.id" :value="method.id">
                {{ method.name }}{{ method.commission_percentage > 0 ? ' (comision ' + method.commission_percentage + '%)' : '' }}
              </option>
            </select>
            <p v-if="!loadingMethods && paymentMethods.length === 0" class="mt-1 text-xs text-amber-600">
              No hay metodos de pago activos. Contacta al administrador.
            </p>
            <p v-if="errors.payment_method_id" class="mt-1 text-xs md:text-sm text-red-600">
              {{ errors.payment_method_id[0] }}
            </p>
          </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-3 md:gap-4">
          <div>
            <CurrencyInput
              v-model="formData.amount"
              label="Monto *"
              placeholder="0.00"
              :error="errors.amount"
              required
            />
          </div>

          <div>
            <label class="block text-xs md:text-sm font-medium text-theme-primary mb-1">
              Referencia
            </label>
            <input
              v-model="formData.reference"
              type="text"
              placeholder="Número de operación, voucher, etc."
              class="block w-full px-2 md:px-3 py-2 text-sm md:text-base border rounded-md shadow-sm
                     bg-theme-surface-elevated
                     text-theme-primary
                     border-theme
                     placeholder-theme-secondary
                     focus:ring-primary-500 focus:border-accent"
              :class="{ 'border-red-500': errors.reference }"
            />
            <p v-if="errors.reference" class="mt-1 text-xs md:text-sm text-red-600">
              {{ errors.reference[0] }}
            </p>
          </div>
        </div>

        <div>
          <label class="block text-xs md:text-sm font-medium text-theme-primary mb-1">
            Observaciones
          </label>
        <textarea
          v-model="formData.notes"
          rows="3"
          placeholder="Detalles adicionales del pago..."
          class="block w-full px-2 md:px-3 py-2 text-sm md:text-base border rounded-md shadow-sm
                 bg-theme-surface-elevated
                 text-theme-primary
                 border-theme
                 placeholder-theme-secondary
                 focus:ring-primary-500 focus:border-accent"
          :class="{ 'border-red-500': errors.notes }"
        ></textarea>
          <p v-if="errors.notes" class="mt-1 text-xs md:text-sm text-red-600">
            {{ errors.notes[0] }}
          </p>
        </div>
      </div>

      <!-- Resumen del Pago -->
      <div class="bg-theme-surface border border-theme rounded-lg p-3 md:p-4">
        <h3 class="text-sm md:text-base font-semibold text-theme-primary mb-3">Resumen del Pago</h3>
        <div class="space-y-2 text-xs md:text-sm">
          <div class="flex justify-between">
            <span class="text-theme-secondary">Paciente:</span>
            <span class="font-medium text-theme-primary">
              {{ selectedPatient?.name || 'No seleccionado' }}
            </span>
          </div>
          <div class="flex justify-between">
            <span class="text-theme-secondary">Concepto:</span>
            <span class="font-medium text-theme-primary">
              {{ formData.concept || 'No especificado' }}
            </span>
          </div>
          <div class="flex justify-between">
            <span class="text-theme-secondary">Monto:</span>
            <span class="font-medium text-theme-primary">
              {{ formatCurrency(formData.amount) }}
            </span>
          </div>
          <div class="flex justify-between">
            <span class="text-theme-secondary">Método:</span>
            <span class="font-medium text-theme-primary">
              {{ selectedPaymentMethod?.name || 'No seleccionado' }}
            </span>
          </div>
        </div>
      </div>
    </form>

    <template #footer>
      <!-- Botones responsive: stack en móvil, inline en desktop -->
      <div class="flex flex-col-reverse sm:flex-row gap-2 sm:gap-3">
        <Button
          type="button"
          variant="secondary"
          @click="$emit('close')"
          :disabled="loading"
          class="w-full sm:w-auto px-4 py-2 text-sm md:text-base"
        >
          Cancelar
        </Button>
        <Button
          type="submit"
          variant="primary"
          :loading="loading"
          :disabled="!canSubmit"
          @click="handleSubmit"
          class="w-full sm:w-auto px-4 py-2 text-sm md:text-base"
        >
          <BanknotesIcon class="w-4 h-4 mr-2" />
          Registrar Cobro
        </Button>
      </div>
    </template>
  </Modal>
</template>

<script setup>
import { ref, computed, watch, onMounted } from 'vue'
import { BanknotesIcon } from '@heroicons/vue/24/outline'
import Modal from '@/components/ui/Modal.vue'
import Button from '@/components/ui/Button.vue'
import CurrencyInput from '@/components/ui/CurrencyInput.vue'
import { useApi } from '@/composables/useApi'
import { useTransactions } from '@/composables/useTransactions'
import { useToast } from '@/composables/useToast'

const props = defineProps({
  show: {
    type: Boolean,
    default: false
  },
  selectedPatient: {
    type: Object,
    default: null
  },
  selectedAppointment: {
    type: Object,
    default: null
  },
  pendingPatients: {
    type: Array,
    default: () => []
  }
})

const emit = defineEmits(['close', 'success'])

// Composables
const { get } = useApi()
const { createTransaction } = useTransactions()
const toast = useToast()

// Estado
const loading = ref(false)
const loadingMethods = ref(false)
const errors = ref({})

// Datos del formulario
const formData = ref({
  patient_id: '',
  appointment_id: '',
  concept: '',
  payment_method_id: '',
  amount: 0,
  reference: '',
  notes: ''
})

// Datos de carga
const paymentMethods = ref([])
const patientAppointments = ref([])

// Computed
const selectedPatient = computed(() =>
  props.pendingPatients.find(p => p.id === parseInt(formData.value.patient_id))
)

const selectedPaymentMethod = computed(() =>
  paymentMethods.value.find(pm => pm.id === parseInt(formData.value.payment_method_id))
)

const canSubmit = computed(() => {
  return formData.value.patient_id &&
         formData.value.concept &&
         formData.value.payment_method_id &&
         formData.value.amount > 0
})

// Metodos
const loadPaymentMethods = async () => {
  loadingMethods.value = true
  try {
    // Sprint 2: usamos /payment-methods/active (endpoint publico para
    // todos los autenticados) en lugar de /payment-methods, que esta
    // protegido por role:administrador para uso admin.
    const methodsData = await get('/api/payment-methods/active')
    paymentMethods.value = methodsData.data || []
  } catch (error) {
    toast.error('No se pudieron cargar los metodos de pago. Verifica tu conexion.')
    // Si falla la autenticacion, mostrar mensaje al usuario
    if (error.response?.status === 401) {
      // token expirado, redirigir a login lo maneja el guard global
    }
    paymentMethods.value = []
  } finally {
    loadingMethods.value = false
  }
}

const loadPatientAppointments = async (patientId) => {
  if (!patientId) {
    patientAppointments.value = []
    return
  }

  try {
    // Aquí implementarías la carga de citas del paciente
    // Por ahora lo dejamos vacío
    patientAppointments.value = []
  } catch (error) {
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
    const transactionData = {
      patient_id: formData.value.patient_id,
      appointment_id: formData.value.appointment_id || null,
      description: formData.value.concept,
      payment_method_id: formData.value.payment_method_id,
      amount: formData.value.amount,
      reference_number: formData.value.reference || null,
      notes: formData.value.notes || null,
      type: 'payment'
    }

    const result = await createTransaction(transactionData)

    // Emitir con datos completos para mensaje personalizado
    emit('success', {
      transaction: result,
      patient: selectedPatient.value,
      amount: formData.value.amount,
      concept: formData.value.concept,
      paymentMethod: paymentMethods.value.find(pm => pm.id === formData.value.payment_method_id),
      transactionNumber: result.transaction_number
    })
    emit('close')
    resetForm()
  } catch (error) {

    if (error.response?.data?.errors) {
      errors.value = error.response.data.errors
    } else if (error.response?.data?.message) {
      errors.value = { general: error.response.data.message }
    } else {
      errors.value = { general: error.message || 'Error al registrar el pago' }
    }
  } finally {
    loading.value = false
  }
}

const validateForm = () => {
  const newErrors = {}

  if (!formData.value.patient_id) {
    newErrors.patient_id = ['El paciente es requerido']
  }

  if (!formData.value.concept) {
    newErrors.concept = ['El concepto es requerido']
  }

  if (!formData.value.payment_method_id) {
    newErrors.payment_method_id = ['El método de pago es requerido']
  }

  if (!formData.value.amount || formData.value.amount <= 0) {
    newErrors.amount = ['El monto debe ser mayor a 0']
  }

  errors.value = newErrors
  return Object.keys(newErrors).length === 0
}

const resetForm = () => {
  formData.value = {
    patient_id: '',
    appointment_id: '',
    concept: '',
    payment_method_id: '',
    amount: 0,
    reference: '',
    notes: ''
  }
  errors.value = {}
}

const formatCurrency = (amount) => {
  return new Intl.NumberFormat('es-PE', {
    style: 'currency',
    currency: 'PEN'
  }).format(amount)
}

const formatDate = (date) => {
  return new Date(date).toLocaleDateString('es-PE', {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit'
  })
}

// Watchers
watch(() => formData.value.patient_id, (newPatientId) => {
  if (newPatientId) {
    loadPatientAppointments(newPatientId)
  } else {
    patientAppointments.value = []
  }
})

// Pre-llenar datos del paciente seleccionado
watch(() => props.selectedPatient, (newPatient) => {
  if (newPatient && props.show) {
    formData.value.patient_id = newPatient.id
    formData.value.concept = newPatient.concept || 'Consulta'
    formData.value.amount = newPatient.amount || 0

    // Si viene con appointment, también pre-llenarlo
    if (props.selectedAppointment) {
      formData.value.appointment_id = props.selectedAppointment.id
    }
  }
}, { immediate: true })

// Pre-llenar datos de la cita seleccionada
watch(() => props.selectedAppointment, (newAppointment) => {
  if (newAppointment && props.show) {
    formData.value.appointment_id = newAppointment.id
  }
}, { immediate: true })

// Lifecycle
onMounted(() => {
  if (props.show) {
    loadPaymentMethods()
  }
})

watch(() => props.show, (newShow) => {
  if (newShow) {
    loadPaymentMethods()
    // NO resetear si hay paciente seleccionado
    if (!props.selectedPatient) {
      resetForm()
    }
  }
})
</script>
