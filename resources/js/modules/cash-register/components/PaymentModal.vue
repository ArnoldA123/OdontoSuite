<template>
  <Modal
    :model-value="modelValue"
    title="Registrar Cobro de Paciente"
    :size="activeTab === 'manual' ? 'xl' : 'lg'"
    @update:model-value="$emit('update:modelValue', $event)"
    @close="$emit('update:modelValue', false)"
    class="overflow-y-auto"
  >
    <!-- Tabs: Manual / Mercado Pago (solo si el metodo seleccionado tiene gateway) -->
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
    <form v-if="activeTab === 'manual'" @submit.prevent="handleSubmit" class="space-y-4 md:space-y-6 bg-canvas">
      <!-- Información del Paciente -->
      <div class="bg-canvas border border-hairline rounded-lg p-3 md:p-4">
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
                     bg-canvas
                     text-theme-primary
                     border-hairline
                     disabled:bg-canvas disabled:opacity-50
                     disabled:cursor-not-allowed"
              :class="{ 'border-systemRed-500 ring-1 ring-systemRed-200': errors.patient_id }"
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
            <p v-if="errors.patient_id" class="mt-1 text-xs md:text-sm text-systemRed-600">
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
                     bg-canvas
                     text-theme-primary
                     border-hairline"
              :class="{ 'border-systemRed-500 ring-1 ring-systemRed-200': errors.appointment_id }"
            >
              <option value="">Seleccionar cita (opcional)</option>
              <option v-for="appointment in patientAppointments" :key="appointment.id" :value="appointment.id">
                {{ formatDate(appointment.date) }} - {{ appointment.appointment_type?.name }}
              </option>
            </select>
            <p v-if="errors.appointment_id" class="mt-1 text-xs md:text-sm text-systemRed-600">
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
                     bg-canvas
                     text-theme-primary
                     border-hairline
                     placeholder-theme-secondary"
              :class="{ 'border-systemRed-500 ring-1 ring-systemRed-200': errors.concept }"
              required
            />
            <p v-if="errors.concept" class="mt-1 text-xs md:text-sm text-systemRed-600">
              {{ errors.concept[0] }}
            </p>
          </div>

          <div>
            <label class="block text-xs md:text-sm font-medium text-theme-primary mb-1">
              Metodo de Pago *
            </label>
            <UiSelect
              v-model="formData.payment_method_id"
              :options="paymentMethodOptions"
              placeholder="Seleccionar metodo"
              size="md"
              searchable
              :error="errors.payment_method_id"
              :disabled="loadingMethods"
            />
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
                     bg-canvas
                     text-theme-primary
                     border-hairline
                     placeholder-theme-secondary"
              :class="{ 'border-systemRed-500 ring-1 ring-systemRed-200': errors.reference }"
            />
            <p v-if="errors.reference" class="mt-1 text-xs md:text-sm text-systemRed-600">
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
                 bg-canvas
                 text-theme-primary
                 border-hairline
                 placeholder-theme-secondary"
          :class="{ 'border-systemRed-500 ring-1 ring-systemRed-200': errors.notes }"
        ></textarea>
          <p v-if="errors.notes" class="mt-1 text-xs md:text-sm text-systemRed-600">
            {{ errors.notes[0] }}
          </p>
        </div>
      </div>

      <!-- Resumen del Pago -->
      <div class="bg-canvas border border-hairline rounded-lg p-3 md:p-4">
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

    <!-- Mercado Pago Checkout (Sprint 3, plan #11) -->
    <MercadoPagoCheckout
      v-if="activeTab === 'mercadopago' && pendingTransactionId"
      :transaction-id="pendingTransactionId"
      :amount="formData.amount"
      :description="formData.concept"
      @close="handleMpCancel"
      @success="handleMpSuccess"
    />

    <template #footer>
      <!-- Botones responsive: stack en móvil, inline en desktop -->
      <div class="flex flex-col-reverse sm:flex-row gap-2 sm:gap-3">
        <Button
          type="button"
          variant="secondary"
          @click="$emit('update:modelValue', false)"
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
import { useRouter } from 'vue-router'
import { BanknotesIcon } from '@heroicons/vue/24/outline'
import Modal from '@/components/ui/Modal.vue'
import Button from '@/components/ui/Button.vue'
import CurrencyInput from '@/components/ui/CurrencyInput.vue'
import UiTabs from '@/components/ui/Tabs.vue'
import UiStatusBadge from '@/components/ui/StatusBadge.vue'
import { useApi } from '@/composables/useApi'
import { useTransactions } from '@/composables/useTransactions'
import { useToast } from '@/composables/useToast'
import { useAuth } from '@/composables/useAuth'
import { formatCurrency } from '@/composables/useFormatters'
import MercadoPagoCheckout from '@/modules/cash-register/components/MercadoPagoCheckout.vue'

const props = defineProps({
  modelValue: {
    type: Boolean,
    default: false
  },
  // Alias kept for backward compatibility — slice 07 unifies the modal
  // contract on modelValue, but we accept `show` as a fallback so existing
  // callers do not break during the transition.
  show: {
    type: Boolean,
    default: undefined
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

const emit = defineEmits(['update:modelValue', 'close', 'success'])

// Resolve the actual open-state. modelValue wins when explicitly bound;
// otherwise fall back to the legacy `show` prop.
const isOpen = computed(() =>
  props.modelValue !== undefined ? props.modelValue : !!props.show
)

// Composables
const { get } = useApi()
const { createTransaction } = useTransactions()
const toast = useToast()
// Slice 09 / UXF-021: 401 must tear down the session and bounce to /login
// instead of leaving the user staring at a half-broken modal.
const { authLogout } = useAuth()
const router = useRouter()

/**
 * Handle a confirmed 401 from any PaymentModal fetch: show a session-
 * expired toast, drop the local auth state, and force the router back to
 * /login so the auth gate re-evaluates on the next render.
 *
 * Idempotent: the toast + logout + push is cheap to repeat across the
 * three 401 sites (loadPaymentMethods, loadPatientAppointments, handleSubmit).
 */
const handleSessionExpired = () => {
  toast.error('Tu sesión expiró. Vuelve a iniciar sesión.')
  authLogout()
  router.push('/login')
}

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

// Mercado Pago (Sprint 3, plan #11)
const activeTab = ref('manual')
const pendingTransactionId = ref(null)

const switchToMercadoPago = async () => {
  if (!validateForm()) return

  loading.value = true
  errors.value = {}
  try {
    // Crear transaccion local como pendiente
    const transactionData = {
      patient_id: formData.value.patient_id,
      appointment_id: formData.value.appointment_id || null,
      description: formData.value.concept,
      // Slice 02 / T-02.3 — API-045 fix: payment_method_id must be sent in the
      // MP-preference transaction too, otherwise backend StoreTransactionRequest
      // either drops the field (if nullable) or rejects the call. We send the
      // same id selected in the UI as for manual capture.
      payment_method_id: formData.value.payment_method_id,
      amount: formData.value.amount,
      reference_number: null,
      notes: formData.value.notes || null,
      type: 'payment'
    }

    const result = await createTransaction(transactionData)
    const txId = result?.id || result?.data?.id

    if (txId) {
      pendingTransactionId.value = txId
      activeTab.value = 'mercadopago'
    } else {
      toast.error('Error al crear la transaccion para Mercado Pago')
    }
  } catch (error) {
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
    patient: selectedPatient.value,
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

// Sprint 3 fix: la tab MP solo se muestra cuando el metodo seleccionado
// tiene gateway_type = 'mercadopago' (configurado en settings/payment-methods)
const showMpTab = computed(() => {
  if (!formData.value.payment_method_id) return false
  const method = paymentMethods.value.find(
    m => m.id === parseInt(formData.value.payment_method_id)
  )
  return method?.gateway_type === 'mercadopago'
})

// Sprint 4: transformar payment methods al formato UiSelect
const paymentMethodOptions = computed(() =>
  paymentMethods.value.map(m => ({
    value: m.id,
    label: m.name,
    description: m.commission_percentage > 0
      ? `Comision: ${m.commission_percentage}%`
      : (m.description || '')
  }))
)

// PR-pagos-04 / design §3.1 — Manual / Mercado Pago tab definitions.
// The MercadoPago tab carries `disabled = (amount <= 0)`; the visual
// hint <UiStatusBadge variant="warning" label="Ingrese monto" /> is
// rendered when the tab is gated. Activation still flows through
// `switchToMercadoPago` (which validates the form) so the 401 redirect
// code path in useCashRegister is preserved.
const manualTabs = computed(() => [
  { id: 'manual', label: 'Cobro Manual' },
  {
    id: 'mercadopago',
    label: selectedPaymentMethod.value?.name || 'Mercado Pago',
    disabled: (formData.value.amount ?? 0) <= 0
  }
])

// PR-pagos-04 / design §3.1 — tab change handler. The Mercado Pago tab
// MUST run `switchToMercadoPago` (which validates the form and creates
// a pending transaction); Manual is a direct switch. The activeTab is
// reset before the MP flow so the validateForm early-return can fall
// back to the manual tab cleanly.
const onTabChange = (newTabId) => {
  if (newTabId === 'mercadopago') {
    activeTab.value = 'manual'
    switchToMercadoPago()
  } else if (newTabId === 'manual') {
    activeTab.value = 'manual'
  }
}

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
    // Surface the backend message so the user knows the failure shape
    // (e.g. 401 Sesión expirada) instead of a generic "verifica tu conexion".
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

const loadPatientAppointments = async (patientId) => {
  if (!patientId) {
    patientAppointments.value = []
    return
  }

  try {
    // Carga real desde backend; el endpoint devuelve solo las citas
    // pendientes/sin-pago del paciente para mantener el selector acotado.
    const response = await get(`/api/patients/${patientId}/appointments`, {
      params: { status: 'pending' }
    })
    patientAppointments.value = response?.data || []
  } catch (error) {
    // No silenciar: si la lista falla, el usuario debe saber que el selector
    // se queda vacío por un error de red, no por falta de citas.
    console.error('[PaymentModal] loadPatientAppointments failed', error)
    if (error.response?.status === 401) {
      handleSessionExpired()
    } else {
      const message =
        error.response?.data?.message ||
        'No se pudieron cargar las citas del paciente.'
      toast.warning(message)
    }
    patientAppointments.value = []
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
    emit('update:modelValue', false)
    resetForm()
  } catch (error) {
    // 401 — surface it; do not let a silent catch hide a session expiry.
    if (error.response?.status === 401) {
      handleSessionExpired()
    }

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
  if (newPatient && isOpen.value) {
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
  if (newAppointment && isOpen.value) {
    formData.value.appointment_id = newAppointment.id
  }
}, { immediate: true })

// Lifecycle
onMounted(() => {
  if (isOpen.value) {
    loadPaymentMethods()
  }
})

watch(isOpen, (newOpen) => {
  if (newOpen) {
    loadPaymentMethods()
    // NO resetear si hay paciente seleccionado
    if (!props.selectedPatient) {
      resetForm()
    }
  }
})
</script>
