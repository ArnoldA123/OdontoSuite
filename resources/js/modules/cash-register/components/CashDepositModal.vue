<template>
  <UiModal
    :model-value="modelValue"
    title="Nuevo depósito"
    size="md"
    @update:model-value="handleModelValue"
  >
    <form
      id="cash-deposit-form"
      data-cash-deposit-form
      class="space-y-4 bg-canvas"
      @submit.prevent="handleSubmit"
    >
      <div
        v-if="generalError"
        role="alert"
        data-cash-deposit-error
        class="rounded-ios border border-systemRed-200 bg-systemRed-50 p-3 text-sm text-systemRed-700"
      >
        {{ generalError }}
      </div>

      <UiStatusBadge variant="info" label="Depósito bancario" size="md" />

      <p
        data-deposit-available
        class="rounded-ios border border-hairline bg-theme-surface p-3 text-sm text-theme-secondary"
      >
        Efectivo disponible en la gaveta:
        <span class="font-semibold text-theme-primary tabular-nums">
          {{ availableCash === null ? 'N/D' : formatCurrency(availableCash) }}
        </span>
      </p>

      <div data-deposit-amount data-field-error="amount">
        <CurrencyInput
          v-model="form.amount"
          label="Monto del depósito"
          placeholder="0.00"
          required
          :disabled="loading"
          :error="fieldError('amount')"
          help="Sale de la gaveta como egreso del turno"
        />
        <p v-if="overAvailable" data-deposit-over-available class="mt-1 text-sm text-systemRed-600">
          El monto supera el efectivo disponible ({{ formatCurrency(availableCash) }}).
        </p>
      </div>

      <div data-deposit-reference data-field-error="reference">
        <UiInput
          :model-value="form.reference"
          label="Referencia bancaria"
          placeholder="Número de operación o voucher"
          required
          :disabled="loading"
          :error="fieldError('reference')"
          :hint="`${form.reference.length}/100 caracteres`"
          @update:model-value="updateReference"
        />
      </div>

      <div data-deposit-date data-field-error="deposited_at">
        <UiInput
          v-model="form.deposited_at"
          type="date"
          label="Fecha del depósito"
          :max="today"
          :disabled="loading"
          :error="fieldError('deposited_at')"
        />
      </div>

      <div data-deposit-session>
        <p
          v-if="fieldError('cash_register_session_id')"
          data-field-error="cash_register_session_id"
          class="text-sm text-systemRed-600"
        >
          {{ fieldError('cash_register_session_id') }}
        </p>
      </div>

      <div>
        <label for="cash-deposit-notes" class="mb-1 block text-sm font-medium text-theme-primary">
          Notas del depósito
        </label>
        <UiTextarea
          id="cash-deposit-notes"
          :model-value="form.notes"
          :rows="3"
          placeholder="Notas adicionales sobre el depósito..."
          :disabled="loading"
          @update:model-value="updateNotes"
        />
        <p data-deposit-notes-counter class="mt-1 text-sm text-theme-secondary">
          {{ form.notes.length }}/1000 caracteres
        </p>
      </div>

      <UiCard variant="flat" padding="md">
        <div class="flex justify-between gap-3 text-sm">
          <span class="text-theme-secondary">Monto a depositar</span>
          <span data-deposit-total class="font-semibold text-theme-primary tabular-nums">
            {{ formatCurrency(form.amount) }}
          </span>
        </div>
      </UiCard>
    </form>

    <template #footer>
      <UiButton variant="secondary" :disabled="loading" data-modal-cancel @click="close">
        Cancelar
      </UiButton>
      <UiButton
        variant="primary"
        :loading="loading"
        :disabled="!canSubmit"
        data-modal-submit
        @click="handleSubmit"
      >
        <template #icon-left>
          <BanknotesIcon class="h-4 w-4" aria-hidden="true" />
        </template>
        Guardar depósito
      </UiButton>
    </template>
  </UiModal>
</template>

<script setup>
import { computed, ref, watch } from 'vue'
import { BanknotesIcon } from '@heroicons/vue/24/outline'
import CurrencyInput from '@/components/ui/CurrencyInput.vue'
import UiButton from '@/components/ui/Button.vue'
import UiCard from '@/components/ui/Card.vue'
import UiInput from '@/components/ui/Input.vue'
import UiModal from '@/components/ui/Modal.vue'
import UiStatusBadge from '@/components/ui/StatusBadge.vue'
import UiTextarea from '@/components/ui/UiTextarea.vue'
import { useApi } from '@/composables/useApi'
import { formatCurrency } from '@/composables/useFormatters'

const props = defineProps({
  modelValue: {
    type: Boolean,
    default: false
  },
  session: {
    type: Object,
    default: null
  },
  availableCash: {
    type: Number,
    default: null
  }
})

const emit = defineEmits(['update:modelValue', 'success'])

const { post } = useApi()

const loading = ref(false)
const errors = ref({})
const today = ref(todayIso())

const buildForm = () => ({
  amount: 0,
  reference: '',
  deposited_at: today.value,
  notes: ''
})

const form = ref(buildForm())

const availableCash = computed(() => {
  const value = props.availableCash
  if (value === null || value === undefined || value === '') return null
  const numeric = Number(value)
  return Number.isFinite(numeric) ? numeric : null
})

const fieldError = field => {
  const value = errors.value[field]
  if (!value) return ''
  return Array.isArray(value) ? value[0] : String(value)
}

const generalError = computed(() => fieldError('general'))

const overAvailable = computed(
  () =>
    availableCash.value !== null &&
    Number(form.value.amount) > availableCash.value &&
    Number(form.value.amount) > 0
)

const canSubmit = computed(() => Number(form.value.amount) > 0 && !loading.value)

function todayIso() {
  const now = new Date()
  const month = String(now.getMonth() + 1).padStart(2, '0')
  const day = String(now.getDate()).padStart(2, '0')
  return `${now.getFullYear()}-${month}-${day}`
}

const handleModelValue = value => emit('update:modelValue', value)

const close = () => emit('update:modelValue', false)

const updateReference = value => {
  form.value.reference = String(value ?? '').slice(0, 100)
}

const updateNotes = value => {
  form.value.notes = String(value ?? '').slice(0, 1000)
}

const resetForm = () => {
  today.value = todayIso()
  form.value = buildForm()
  errors.value = {}
}

// The drawer's cash is the ceiling the UI enforces before asking; the service
// still re-reads the locked session, so this guard never replaces the API.
const validate = () => {
  const next = {}
  const amount = Number(form.value.amount)

  if (!(amount > 0)) {
    next.amount = 'El monto del depósito debe ser mayor que cero.'
  } else if (availableCash.value !== null && amount > availableCash.value) {
    next.amount = `El monto no puede superar el efectivo disponible (${formatCurrency(availableCash.value)}).`
  }

  if (!form.value.reference.trim()) {
    next.reference = 'La referencia bancaria del depósito es requerida.'
  }

  if (!form.value.deposited_at) {
    next.deposited_at = 'La fecha del depósito es requerida.'
  } else if (form.value.deposited_at > today.value) {
    next.deposited_at = 'La fecha del depósito no puede ser futura.'
  }

  errors.value = next
  return Object.keys(next).length === 0
}

const buildPayload = () => ({
  cash_register_session_id: props.session?.id ?? null,
  amount: Number(form.value.amount),
  reference: form.value.reference.trim(),
  deposited_at: form.value.deposited_at,
  notes: form.value.notes.trim() || null
})

const handleError = error => {
  const data = error.response?.data || {}
  const next = {}

  Object.entries(data.errors || {}).forEach(([key, value]) => {
    next[key] = Array.isArray(value) ? value[0] : String(value)
  })

  next.general = data.message || error.message || 'No se pudo registrar el depósito'
  errors.value = next
}

const handleSubmit = async () => {
  if (loading.value) return

  errors.value = {}
  if (!validate()) return

  loading.value = true

  try {
    const result = await post('/api/cash-register/deposits', buildPayload())
    emit('success', result?.data ?? null)
    close()
  } catch (error) {
    handleError(error)
  } finally {
    loading.value = false
  }
}

watch(
  () => props.modelValue,
  value => {
    if (value) resetForm()
  }
)
</script>
