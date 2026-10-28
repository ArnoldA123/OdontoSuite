<template>
  <UiModal
    :model-value="modelValue"
    title="Registrar Cobro de Paciente"
    :size="activeTab === 'manual' ? 'xl' : 'lg'"
    @update:model-value="handleModelUpdate"
  >
    <div class="space-y-4 bg-canvas md:space-y-6" data-payment-modal>
      <UiTabs
        v-if="showMpTab"
        v-model="activeTab"
        variant="underline"
        :tabs="manualTabs"
        @update:model-value="onTabChange"
      />
      <UiStatusBadge
        v-if="showMpTab && (formData.amount ?? 0) <= 0"
        variant="warning"
        size="sm"
        label="Ingrese monto"
        class="mt-2"
      />

      <div
        v-if="fieldError('general')"
        role="alert"
        class="flex items-start gap-3 rounded-ios border border-hairline bg-theme-surface p-3"
        data-payment-error
      >
        <ExclamationTriangleIcon class="h-5 w-5 shrink-0 text-systemRed-600" aria-hidden="true" />
        <p class="text-sm text-systemRed-600">{{ fieldError('general') }}</p>
      </div>

      <AccountingLockNotice
        v-if="lockBlocked"
        v-model="lockReason"
        :message="lockMessage"
        :error="lockReasonError"
        :loading="loading"
        @retry="retryWithOverride"
      />

      <form
        v-if="activeTab === 'manual'"
        class="space-y-4 md:space-y-6"
        data-payment-form
        @submit.prevent="handleSubmit"
      >
        <UiCard variant="outlined">
          <h3 class="mb-3 text-sm font-semibold text-theme-primary md:text-base">
            Información del paciente
          </h3>
          <div class="grid grid-cols-1 gap-3 md:grid-cols-2 md:gap-4">
            <div>
              <!-- Issue #199: an ad-hoc charge can pick any patient, not only
                   the ones with a pending appointment. Coming from a pending
                   row, the patient stays preset and locked. -->
              <div v-if="isAdHocPayment" data-payment-patient-selector>
                <PatientSelector v-model="adHocPatient" />
                <p v-if="fieldError('patient_id')" class="mt-1 text-sm text-systemRed-600">
                  {{ fieldError('patient_id') }}
                </p>
              </div>
              <template v-else>
                <UiSelect
                  v-model="formData.patient_id"
                  :options="patientOptions"
                  label="Paciente"
                  placeholder="Seleccionar paciente"
                  :disabled="Boolean(selectedPatient)"
                  :error="fieldError('patient_id')"
                  data-payment-patient
                />
                <p v-if="selectedPatient" class="mt-1 text-xs text-theme-secondary">
                  Paciente seleccionado desde cobros pendientes
                </p>
              </template>
            </div>

            <div>
              <UiSelect
                v-model="formData.appointment_id"
                :options="appointmentOptions"
                label="Cita relacionada"
                placeholder="Seleccionar cita (opcional)"
                :disabled="Boolean(selectedAppointment)"
                :error="fieldError('appointment_id')"
                data-payment-appointment
              />
              <p v-if="selectedAppointment" class="mt-1 text-xs text-theme-secondary">
                Cita pendiente de cobro
              </p>
            </div>
          </div>
        </UiCard>

        <UiCard variant="outlined">
          <h3 class="mb-3 text-sm font-semibold text-theme-primary md:text-base">
            Detalles del pago
          </h3>
          <div class="grid grid-cols-1 gap-3 md:grid-cols-2 md:gap-4">
            <UiInput
              v-model="formData.concept"
              label="Concepto"
              placeholder="Ej: Consulta, Tratamiento, etc."
              required
              :error="fieldError('concept')"
              data-payment-concept
            />

            <div>
              <UiSelect
                v-model="formData.payment_method_id"
                :options="paymentMethodOptions"
                label="Método de pago"
                placeholder="Seleccionar método"
                size="md"
                searchable
                :disabled="loadingMethods"
                :error="fieldError('payment_method_id')"
                data-payment-method
              />
              <p
                v-if="!loadingMethods && paymentMethods.length === 0"
                class="mt-1 text-xs text-systemOrange-600"
              >
                No hay métodos de pago activos. Contacta al administrador.
              </p>
            </div>

            <CurrencyInput
              v-model="formData.amount"
              label="Monto"
              placeholder="0.00"
              required
              :error="fieldError('amount')"
              data-payment-amount
            />

            <UiInput
              v-model="formData.reference"
              label="Referencia"
              placeholder="Número de operación, voucher, etc."
              :required="requiresReference"
              :hint="referenceHint"
              :error="fieldError('reference')"
              data-payment-reference
            />
          </div>

          <div class="mt-3 md:mt-4">
            <label class="mb-1 block text-sm font-medium text-theme-primary">Observaciones</label>
            <UiTextarea
              v-model="formData.notes"
              :rows="3"
              placeholder="Detalles adicionales del pago..."
              :error="Boolean(fieldError('notes'))"
              data-payment-notes
            />
            <p v-if="fieldError('notes')" class="mt-1 text-sm text-systemRed-600">
              {{ fieldError('notes') }}
            </p>
          </div>
        </UiCard>

        <UiCard variant="outlined">
          <h3 class="mb-3 text-sm font-semibold text-theme-primary md:text-base">
            Resumen del pago
          </h3>
          <div class="space-y-2 text-sm">
            <div class="flex justify-between gap-3">
              <span class="text-theme-secondary">Paciente:</span>
              <span class="font-medium text-theme-primary">
                {{ summaryPatient?.name || 'No seleccionado' }}
              </span>
            </div>
            <div class="flex justify-between gap-3">
              <span class="text-theme-secondary">Concepto:</span>
              <span class="font-medium text-theme-primary">
                {{ formData.concept || 'No especificado' }}
              </span>
            </div>
            <div class="flex justify-between gap-3">
              <span class="text-theme-secondary">Monto:</span>
              <span class="font-medium tabular-nums text-theme-primary">
                {{ formatCurrency(formData.amount) }}
              </span>
            </div>
            <div class="flex justify-between gap-3">
              <span class="text-theme-secondary">Método:</span>
              <span class="font-medium text-theme-primary">
                {{ selectedPaymentMethod?.name || 'No seleccionado' }}
              </span>
            </div>
          </div>
        </UiCard>
      </form>

      <MercadoPagoCheckout
        v-if="activeTab === 'mercadopago' && pendingTransactionId"
        :transaction-id="pendingTransactionId"
        :amount="formData.amount"
        :description="formData.concept"
        @close="handleMpCancel"
        @success="handleMpSuccess"
      />
    </div>

    <template #footer>
      <div class="flex flex-col-reverse gap-2 sm:flex-row sm:gap-3">
        <UiButton
          type="button"
          variant="secondary"
          data-payment-cancel
          :disabled="loading"
          @click="handleModelUpdate(false)"
        >
          Cancelar
        </UiButton>
        <UiButton
          type="submit"
          variant="primary"
          data-payment-submit
          :loading="loading"
          :disabled="!canSubmit"
          @click="handleSubmit"
        >
          <template #icon-left>
            <BanknotesIcon class="h-4 w-4" aria-hidden="true" />
          </template>
          Registrar Cobro
        </UiButton>
      </div>
    </template>
  </UiModal>
</template>

<script setup>
import { ref, computed, watch, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { BanknotesIcon, ExclamationTriangleIcon } from '@heroicons/vue/24/outline'
import UiModal from '@/components/ui/Modal.vue'
import UiButton from '@/components/ui/Button.vue'
import UiCard from '@/components/ui/Card.vue'
import UiInput from '@/components/ui/Input.vue'
import UiSelect from '@/components/ui/Select.vue'
import UiTabs from '@/components/ui/Tabs.vue'
import UiTextarea from '@/components/ui/UiTextarea.vue'
import UiStatusBadge from '@/components/ui/StatusBadge.vue'
import CurrencyInput from '@/components/ui/CurrencyInput.vue'
import PatientSelector from '@/components/ui/PatientSelector.vue'
import AccountingLockNotice from '@/components/money/AccountingLockNotice.vue'
import { useApi } from '@/composables/useApi'
import { useTransactions } from '@/composables/useTransactions'
import { useToast } from '@/composables/useToast'
import { useAuth } from '@/composables/useAuth'
import { useAccountingLock } from '@/composables/useAccountingLock'
import { formatCurrency, formatDateTime } from '@/composables/useFormatters'
import MercadoPagoCheckout from '@/modules/cash-register/components/MercadoPagoCheckout.vue'

const props = defineProps({
  modelValue: {
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

const emit = defineEmits(['update:modelValue', 'success'])

const { get, post } = useApi()
const { createTransaction } = useTransactions()
const toast = useToast()
const { authLogout } = useAuth()
const router = useRouter()

// #410 (S9) — the accounting freeze is answered by its own notice, on both
// paying doors this modal can reach.
const {
  blocked: lockBlocked,
  message: lockMessage,
  reason: lockReason,
  reasonError: lockReasonError,
  reset: resetLock,
  detect: detectLock,
  applyValidationErrors: applyLockValidationErrors,
  authorize: authorizeLock
} = useAccountingLock()

/**
 * Handle a confirmed 401 from any PaymentModal fetch: show a session-
 * expired toast, drop the local auth state, and force the router back to
 * /login so the auth gate re-evaluates on the next render.
 */
const handleSessionExpired = () => {
  toast.error('Tu sesión expiró. Vuelve a iniciar sesión.')
  authLogout()
  router.push('/login')
}

const loading = ref(false)
const loadingMethods = ref(false)
const errors = ref({})
const paymentMethods = ref([])
const patientAppointments = ref([])

const formData = ref({
  patient_id: '',
  appointment_id: '',
  concept: '',
  payment_method_id: '',
  amount: 0,
  reference: '',
  notes: ''
})

const activeTab = ref('manual')
const pendingTransactionId = ref(null)
const adHocPatient = ref(null)

// #467 — the lock notice belongs to one attempt, and its retry has to resume
// that attempt: the Mercado Pago preparation is not the manual submit.
const retryAction = ref('manual')

// Issue #199: without a pending row there is no preset patient or appointment,
// so the modal is an ad-hoc charge and the patient is picked freely.
const isAdHocPayment = computed(() => !props.selectedPatient && !props.selectedAppointment)

const fieldError = key => {
  const value = errors.value?.[key]
  if (Array.isArray(value)) return value[0] || ''
  return value || ''
}

const patientOptions = computed(() => {
  const options = props.pendingPatients.map(patient => ({
    value: patient.id,
    label: `${patient.name} - ${patient.document_number}`
  }))

  const preset = props.selectedPatient
  if (preset?.id && !options.some(option => option.value === preset.id)) {
    options.unshift({
      value: preset.id,
      label: `${preset.name} - ${preset.document_number ?? ''}`.trim()
    })
  }

  return options
})

const appointmentOptions = computed(() => {
  const options = patientAppointments.value.map(appointment => ({
    value: appointment.id,
    label: `${formatDateTime(appointment.date, { day: 'numeric', month: 'short' })} - ${appointment.appointment_type?.name || 'Consulta'}`
  }))

  const preset = props.selectedAppointment
  if (preset?.id && !options.some(option => option.value === preset.id)) {
    options.unshift({
      value: preset.id,
      label: `${formatDateTime(preset.date, { day: 'numeric', month: 'short' })} - ${preset.appointment_type?.name || 'Consulta'}`
    })
  }

  return options
})

const summaryPatient = computed(() => {
  const selected = props.pendingPatients.find(
    patient => patient.id === Number(formData.value.patient_id)
  )
  return selected || props.selectedPatient || adHocPatient.value || null
})

const selectedPaymentMethod = computed(() =>
  paymentMethods.value.find(method => method.id === Number(formData.value.payment_method_id))
)

// #291: a card, a transfer or a wallet leaves a voucher behind, so the method
// itself decides whether the reference is part of the charge.
const requiresReference = computed(() => selectedPaymentMethod.value?.requires_reference === true)

const referenceHint = computed(() =>
  requiresReference.value ? 'Obligatorio para este método de pago.' : ''
)

const paymentMethodOptions = computed(() =>
  paymentMethods.value.map(method => ({
    value: method.id,
    label: method.name
  }))
)

const canSubmit = computed(
  () =>
    Boolean(formData.value.patient_id) &&
    Boolean(formData.value.concept) &&
    Boolean(formData.value.payment_method_id) &&
    formData.value.amount > 0
)

const showMpTab = computed(
  () =>
    Boolean(formData.value.payment_method_id) &&
    selectedPaymentMethod.value?.gateway_type === 'mercadopago'
)

const manualTabs = computed(() => [
  { id: 'manual', label: 'Cobro Manual' },
  {
    id: 'mercadopago',
    label: selectedPaymentMethod.value?.name || 'Mercado Pago',
    disabled: (formData.value.amount ?? 0) <= 0
  }
])

const handleModelUpdate = value => {
  emit('update:modelValue', value)
}

const onTabChange = newTabId => {
  // #472 — the lock notice belongs to the attempt that produced it: moving to
  // another tab withdraws it, so a later failure cannot retry the other door
  // with a reason the operator wrote for this one.
  resetLock()
  retryAction.value = 'manual'

  if (newTabId === 'mercadopago') {
    activeTab.value = 'manual'
    switchToMercadoPago()
  } else if (newTabId === 'manual') {
    activeTab.value = 'manual'
  }
}

const loadPaymentMethods = async () => {
  loadingMethods.value = true
  try {
    const methodsData = await get('/api/payment-methods/active')
    paymentMethods.value = methodsData.data || []
  } catch (error) {
    if (error.response?.status === 401) {
      handleSessionExpired()
    } else {
      const message =
        error.response?.data?.message ||
        error.response?.data?.meta?.message ||
        'No se pudieron cargar los métodos de pago. Verifica tu conexión.'
      toast.error(message)
    }
    paymentMethods.value = []
  } finally {
    loadingMethods.value = false
  }
}

const loadPatientAppointments = async patientId => {
  if (!patientId) {
    patientAppointments.value = []
    return
  }

  try {
    const response = await get(`/api/patients/${patientId}/appointments`, {
      params: { status: 'pending' }
    })
    patientAppointments.value = response?.data || []
  } catch (error) {
    if (error.response?.status === 401) {
      handleSessionExpired()
    } else {
      const message =
        error.response?.data?.message || 'No se pudieron cargar las citas del paciente.'
      toast.warning(message)
    }
    patientAppointments.value = []
  }
}

const submitManualPayment = async override => {
  const lockOverrideReason = override?.lock_override_reason

  if (props.selectedAppointment?.id) {
    const payload = {
      payment_method_id: formData.value.payment_method_id,
      amount: formData.value.amount,
      description: formData.value.concept
    }

    if (lockOverrideReason) payload.lock_override_reason = lockOverrideReason

    const response = await post(
      `/api/pending-payments/${props.selectedAppointment.id}/pay`,
      payload
    )

    return response?.data ?? null
  }

  return createTransaction({
    patient_id: formData.value.patient_id,
    appointment_id: formData.value.appointment_id || null,
    description: formData.value.concept,
    payment_method_id: formData.value.payment_method_id,
    amount: formData.value.amount,
    reference_number: formData.value.reference || null,
    notes: formData.value.notes || null,
    type: 'payment',
    ...(lockOverrideReason ? { lock_override_reason: lockOverrideReason } : {})
  })
}

const handleSubmit = async override => {
  retryAction.value = 'manual'
  loading.value = true
  errors.value = {}

  if (!validateForm()) {
    loading.value = false
    return
  }

  try {
    const transaction = await submitManualPayment(override)

    resetLock()

    emit('success', {
      transaction,
      patient: summaryPatient.value,
      amount: formData.value.amount,
      concept: formData.value.concept,
      paymentMethod: selectedPaymentMethod.value,
      transactionNumber: transaction?.transaction_number
    })
    emit('update:modelValue', false)
    resetForm()
  } catch (error) {
    if (error.response?.status === 401) {
      handleSessionExpired()
    }

    const apiError = error.response?.data

    if (detectLock(error)) return
    if (applyLockValidationErrors(apiError?.errors)) return

    if (apiError?.errors) {
      // #291: the API names the column (`reference_number`); the form names the
      // input (`reference`). The field error must land where the user types.
      const mapped = { ...apiError.errors }
      if (mapped.reference_number && !mapped.reference) {
        mapped.reference = mapped.reference_number
      }
      errors.value = mapped
    } else if (apiError?.message) {
      errors.value = { general: [apiError.message] }
    } else {
      errors.value = { general: [error.message || 'Error al registrar el pago'] }
    }
  } finally {
    loading.value = false
  }
}

const retryWithOverride = async () => {
  const override = authorizeLock()
  if (!override) return

  errors.value = {}

  if (retryAction.value === 'mercadopago') {
    await switchToMercadoPago(override)
    return
  }

  await handleSubmit(override)
}

const switchToMercadoPago = async override => {
  retryAction.value = 'mercadopago'

  if (!validateForm()) return

  const lockOverrideReason = override?.lock_override_reason

  loading.value = true
  errors.value = {}
  try {
    const transaction = await createTransaction({
      patient_id: formData.value.patient_id,
      appointment_id: formData.value.appointment_id || null,
      description: formData.value.concept,
      payment_method_id: formData.value.payment_method_id,
      amount: formData.value.amount,
      reference_number: null,
      notes: formData.value.notes || null,
      type: 'payment',
      ...(lockOverrideReason ? { lock_override_reason: lockOverrideReason } : {})
    })

    const txId = transaction?.id

    if (txId) {
      resetLock()
      pendingTransactionId.value = txId
      activeTab.value = 'mercadopago'
    } else {
      toast.error('Error al crear la transacción para Mercado Pago')
    }
  } catch (error) {
    if (detectLock(error)) return
    if (applyLockValidationErrors(error.response?.data?.errors)) return

    if (error.response?.data?.errors) {
      errors.value = error.response.data.errors
    } else {
      toast.error(error.response?.data?.message || 'Error al preparar pago con Mercado Pago')
    }
  } finally {
    loading.value = false
  }
}

const handleMpCancel = () => {
  pendingTransactionId.value = null
  activeTab.value = 'manual'
}

const handleMpSuccess = () => {
  toast.success('Cobro con Mercado Pago registrado exitosamente')

  emit('success', {
    transaction: { id: pendingTransactionId.value },
    patient: summaryPatient.value,
    amount: formData.value.amount,
    concept: formData.value.concept,
    paymentMethod: { name: 'Mercado Pago' },
    transactionNumber: pendingTransactionId.value
  })
  emit('update:modelValue', false)
  resetForm()
  pendingTransactionId.value = null
  activeTab.value = 'manual'
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
  adHocPatient.value = null
  errors.value = {}
}

watch(
  () => formData.value.patient_id,
  newPatientId => {
    if (newPatientId) {
      loadPatientAppointments(newPatientId)
    } else {
      patientAppointments.value = []
    }
  }
)

// The ad-hoc patient object is the source; the payload keeps sending the id.
watch(adHocPatient, patient => {
  const id = patient?.id ?? ''
  if (formData.value.patient_id !== id) {
    formData.value.patient_id = id
  }
})

watch(
  () => props.selectedPatient,
  newPatient => {
    if (newPatient && props.modelValue) {
      formData.value.patient_id = newPatient.id
      formData.value.concept = newPatient.concept || 'Consulta'
      formData.value.amount = newPatient.amount || 0

      if (props.selectedAppointment) {
        formData.value.appointment_id = props.selectedAppointment.id
      }
    }
  },
  { immediate: true }
)

watch(
  () => props.selectedAppointment,
  newAppointment => {
    if (newAppointment && props.modelValue) {
      formData.value.appointment_id = newAppointment.id
    }
  },
  { immediate: true }
)

onMounted(() => {
  if (props.modelValue) {
    loadPaymentMethods()
  }
})

watch(
  () => props.modelValue,
  isOpen => {
    // The lock notice belongs to one attempt, not to the modal.
    resetLock()

    if (isOpen) {
      loadPaymentMethods()
      if (!props.selectedPatient) {
        resetForm()
      }
    }
  }
)
</script>
