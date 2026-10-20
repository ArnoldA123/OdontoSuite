<template>
  <UiModal
    :model-value="modelValue"
    :title="isRefund ? 'Registrar Egreso' : 'Registrar Ingreso'"
    size="lg"
    @update:model-value="close"
  >
    <form data-transaction-form class="space-y-4 bg-canvas" @submit.prevent="handleSubmit">
      <div
        v-if="generalError"
        role="alert"
        data-modal-general-error
        class="rounded-ios border border-systemRed-200 bg-systemRed-50 px-4 py-3 text-sm text-systemRed-700"
      >
        {{ generalError }}
      </div>

      <UiStatusBadge
        :variant="isRefund ? 'error' : 'success'"
        :label="isRefund ? 'Egreso' : 'Ingreso'"
        size="md"
      />

      <div data-modal-field="patient_id" data-field-error="patient_id">
        <label class="mb-1 block text-sm font-medium text-theme-primary">
          Paciente
          <span class="text-systemRed-600">*</span>
        </label>
        <div class="relative">
          <UiInput
            v-model="patientSearch"
            type="search"
            data-modal-patient-search
            placeholder="Buscar paciente por nombre, DNI o email..."
            :error="fieldError('patient_id')"
            @update:model-value="searchPatients"
            @focus="showPatientResults = true"
          />
          <div v-if="searchingPatients" class="absolute inset-y-0 right-0 flex items-center pr-3">
            <UiLoadingSpinner
              size="xs"
              variant="primary"
              :centered="false"
              aria-label="Buscando pacientes"
            />
          </div>
        </div>

        <div
          v-if="showPatientResults && patientResults.length > 0"
          class="absolute z-10 mt-1 max-h-60 w-full overflow-auto rounded-ios border border-hairline bg-theme-surface-elevated shadow-lg"
        >
          <div
            v-for="patient in patientResults"
            :key="patient.id"
            data-patient-result
            class="cursor-pointer border-b border-hairline px-4 py-2 last:border-b-0 hover:bg-theme-surface"
            @click="selectPatient(patient)"
          >
            <div class="font-medium text-theme-primary">
              {{ patient.name }} {{ patient.last_name }}
            </div>
            <div class="text-sm text-theme-secondary">
              {{ patient.document_number || patient.dni }} - {{ patient.email }}
            </div>
          </div>
        </div>

        <UiCard v-if="selectedPatient" variant="flat" padding="sm" class="mt-2">
          <div class="flex items-center justify-between">
            <div>
              <div class="font-medium text-theme-primary">
                {{ selectedPatient.name }} {{ selectedPatient.last_name }}
              </div>
              <div class="text-sm text-theme-secondary">
                {{ selectedPatient.document_number || selectedPatient.dni }} -
                {{ selectedPatient.email }}
              </div>
            </div>
            <UiButton
              type="button"
              variant="icon"
              size="sm"
              aria-label="Quitar paciente"
              @click="clearPatient"
            >
              <template #icon-left>
                <XMarkIcon class="w-4 h-4" aria-hidden="true" />
              </template>
            </UiButton>
          </div>
        </UiCard>
      </div>

      <div data-modal-field="type">
        <label class="mb-1 block text-sm font-medium text-theme-primary">
          Tipo de transacción
          <span class="text-systemRed-600">*</span>
        </label>
        <UiSelect v-model="formData.type" :options="typeOptions" :disabled="loading" />
      </div>

      <div v-if="!isRefund" data-modal-field="appointment_id">
        <label class="mb-1 block text-sm font-medium text-theme-primary">
          Cita o plan de tratamiento
        </label>
        <UiSelect
          v-model="formData.appointment_id"
          :options="appointmentOptions"
          placeholder="Seleccionar cita"
          :disabled="loading || !selectedPatient"
        />
        <p
          v-if="fieldError('appointment_id')"
          data-field-error="appointment_id"
          class="mt-1 text-sm text-systemRed-600"
        >
          {{ fieldError('appointment_id') }}
        </p>
      </div>

      <UiInput
        v-else
        v-model="formData.description"
        label="Concepto"
        placeholder="Descripción del egreso..."
        :disabled="loading"
        :error="fieldError('description')"
      />

      <div data-modal-field="payment_method_id">
        <label class="mb-1 block text-sm font-medium text-theme-primary">
          Método de pago
          <span class="text-systemRed-600">*</span>
        </label>
        <UiSelect
          v-model="formData.payment_method_id"
          :options="paymentMethodOptions"
          placeholder="Seleccionar método de pago"
          :disabled="loading"
        />
        <p
          v-if="fieldError('payment_method_id')"
          data-field-error="payment_method_id"
          class="mt-1 text-sm text-systemRed-600"
        >
          {{ fieldError('payment_method_id') }}
        </p>
      </div>

      <div data-modal-field="amount" data-field-error="amount">
        <CurrencyInput
          v-model="formData.amount"
          label="Monto"
          placeholder="0.00"
          required
          data-modal-amount
          :disabled="loading"
          :error="fieldError('amount')"
        />
      </div>

      <UiInput
        v-if="needsReference"
        v-model="formData.reference_number"
        label="Número de referencia"
        placeholder="Número de operación, voucher, etc."
        :disabled="loading"
        :error="fieldError('reference_number')"
      />

      <div>
        <label class="mb-1 block text-sm font-medium text-theme-primary">Notas</label>
        <UiTextarea
          v-model="formData.notes"
          :rows="3"
          :disabled="loading"
          placeholder="Notas adicionales sobre la transacción..."
          maxlength="500"
        />
        <p class="mt-1 text-sm text-theme-secondary">
          {{ formData.notes?.length || 0 }}/500 caracteres
        </p>
      </div>

      <div class="rounded-ios border border-hairline bg-theme-surface p-4">
        <div class="flex items-center justify-between">
          <span class="text-sm font-semibold text-theme-primary">Total a registrar</span>
          <span class="text-lg font-bold text-theme-primary tabular-nums">
            {{ formatCurrency(formData.amount) }}
          </span>
        </div>
      </div>
    </form>

    <template #footer>
      <div class="flex justify-end gap-3">
        <UiButton
          type="button"
          variant="secondary"
          data-modal-cancel
          :disabled="loading"
          @click="close"
        >
          Cancelar
        </UiButton>
        <UiButton
          type="submit"
          variant="primary"
          data-modal-submit
          :loading="loading"
          :disabled="!canSubmit"
          @click="handleSubmit"
        >
          <template #icon-left>
            <PlusIcon class="w-4 h-4" aria-hidden="true" />
          </template>
          {{ isRefund ? 'Registrar devolución' : 'Registrar pago' }}
        </UiButton>
      </div>
    </template>
  </UiModal>
</template>

<script setup>
import { ref, computed, watch, onMounted } from 'vue'
import UiButton from '@/components/ui/Button.vue'
import UiCard from '@/components/ui/Card.vue'
import UiInput from '@/components/ui/Input.vue'
import UiLoadingSpinner from '@/components/ui/LoadingSpinner.vue'
import UiModal from '@/components/ui/Modal.vue'
import UiSelect from '@/components/ui/Select.vue'
import UiStatusBadge from '@/components/ui/StatusBadge.vue'
import UiTextarea from '@/components/ui/UiTextarea.vue'
import CurrencyInput from '@/components/ui/CurrencyInput.vue'
import { useTransactions } from '@/composables/useTransactions'
import { useApi } from '@/composables/useApi'
import { formatCurrency } from '@/composables/useFormatters'
import { PlusIcon, XMarkIcon } from '@heroicons/vue/24/outline'

const props = defineProps({
  modelValue: {
    type: Boolean,
    default: false
  },
  type: {
    type: String,
    default: 'payment',
    validator: v => ['payment', 'refund'].includes(v)
  }
})

const emit = defineEmits(['update:modelValue', 'success'])

const { createTransaction } = useTransactions()
const { get } = useApi()

const loading = ref(false)
const errors = ref({})
const patientSearch = ref('')
const showPatientResults = ref(false)
const searchingPatients = ref(false)
const patientResults = ref([])
const selectedPatient = ref(null)
const patientAppointments = ref([])
const paymentMethods = ref([])

const buildForm = () => ({
  patient_id: null,
  appointment_id: null,
  treatment_plan_id: null,
  payment_method_id: null,
  type: props.type,
  amount: 0,
  description: '',
  notes: '',
  reference_number: ''
})

const formData = ref(buildForm())

const isRefund = computed(() => formData.value.type === 'refund')

const typeOptions = [
  { label: 'Ingreso', value: 'payment' },
  { label: 'Egreso', value: 'refund' }
]

const appointmentOptions = computed(() =>
  patientAppointments.value.map(appointment => ({
    label: formatAppointment(appointment),
    value: appointment.id
  }))
)

const paymentMethodOptions = computed(() =>
  paymentMethods.value.map(method => ({ label: method.name, value: method.id }))
)

const fieldError = field => {
  const value = errors.value[field]
  if (!value) return ''
  return Array.isArray(value) ? value[0] : String(value)
}

const generalError = computed(() => fieldError('general'))

const needsReference = computed(() => {
  const method = paymentMethods.value.find(m => m.id === formData.value.payment_method_id)
  if (!method) return false
  return /tarjeta|transferencia/i.test(method.name || '')
})

const canSubmit = computed(() => {
  return (
    !!selectedPatient.value &&
    !!formData.value.payment_method_id &&
    formData.value.amount > 0 &&
    !loading.value
  )
})

const close = () => {
  emit('update:modelValue', false)
}

const loadPaymentMethods = async () => {
  try {
    const response = await get('/api/payment-methods/active')
    paymentMethods.value = response.data || []
  } catch {
    paymentMethods.value = []
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
    showPatientResults.value = patientResults.value.length > 0
  } catch {
    patientResults.value = []
  } finally {
    searchingPatients.value = false
  }
}

const selectPatient = async patient => {
  selectedPatient.value = patient
  formData.value.patient_id = patient.id
  patientSearch.value = `${patient.name} ${patient.last_name}`
  showPatientResults.value = false
  await loadPatientAppointments(patient.id)
}

const clearPatient = () => {
  selectedPatient.value = null
  formData.value.patient_id = null
  patientSearch.value = ''
  patientAppointments.value = []
}

const loadPatientAppointments = async patientId => {
  try {
    const response = await get('/api/appointments', {
      params: { patient_id: patientId, status: 'scheduled' }
    })
    patientAppointments.value = response.data || []
  } catch {
    patientAppointments.value = []
  }
}

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
  if (isRefund.value && !formData.value.description) {
    errors.value.description = 'El concepto es requerido'
    valid = false
  }
  return valid
}

const buildDescription = () => {
  if (isRefund.value) return formData.value.description
  const appointment = patientAppointments.value.find(a => a.id === formData.value.appointment_id)
  return appointment?.description || 'Pago de servicios'
}

const handleSubmit = async () => {
  errors.value = {}

  if (!validateForm()) {
    return
  }

  loading.value = true
  try {
    const result = await createTransaction({
      ...formData.value,
      patient_id: selectedPatient.value.id,
      description: buildDescription()
    })
    emit('success', result)
    close()
  } catch (error) {
    const data = error.response?.data
    if (data?.errors) {
      errors.value = {
        ...data.errors,
        general: data.message || 'No se pudo registrar la transacción'
      }
    } else {
      errors.value = {
        general: data?.message || error.message || 'Error al registrar la transacción'
      }
    }
  } finally {
    loading.value = false
  }
}

const formatAppointment = appointment => {
  const typeName = appointment.appointment_type?.name || 'Cita'
  const date = new Date(appointment.scheduled_at).toLocaleDateString('es-PE')
  return `${typeName} - ${date}`
}

const resetForm = () => {
  formData.value = buildForm()
  selectedPatient.value = null
  patientSearch.value = ''
  patientResults.value = []
  patientAppointments.value = []
  errors.value = {}
}

watch(
  () => props.modelValue,
  isOpen => {
    if (isOpen) resetForm()
  }
)

watch(
  () => formData.value.type,
  () => {
    formData.value.appointment_id = null
    formData.value.description = ''
  }
)

onMounted(() => {
  loadPaymentMethods()
})
</script>
