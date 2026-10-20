<template>
  <UiModal
    :model-value="modelValue"
    title="Registrar movimiento de caja"
    size="md"
    @update:model-value="handleModelValue"
  >
    <form id="movement-form" class="space-y-4 bg-canvas" @submit.prevent="handleSubmit">
      <div
        v-if="errors.general"
        role="alert"
        data-movement-error
        class="rounded-ios border border-systemRed-200 bg-systemRed-50 p-3 text-sm text-systemRed-700"
      >
        {{ errors.general }}
      </div>

      <UiSelect
        v-model="form.type"
        data-field="type"
        label="Tipo de movimiento"
        :options="TYPE_OPTIONS"
        :error="fieldError('type')"
        :disabled="loading"
      />

      <UiSelect
        v-model="form.concept"
        data-field="concept"
        label="Concepto"
        :options="CONCEPT_OPTIONS"
        :error="fieldError('concept')"
        :disabled="loading"
      />

      <UiInput
        v-model="form.description"
        data-field="description"
        label="Descripción"
        placeholder="Detalle del movimiento..."
        :error="fieldError('description')"
        :disabled="loading"
        required
      />

      <CurrencyInput
        v-model="form.amount"
        data-field="amount"
        label="Monto"
        placeholder="0.00"
        :error="fieldError('amount')"
        :disabled="loading"
        required
      />

      <UiInput
        v-model="form.reference"
        data-field="reference"
        label="Referencia"
        placeholder="Número de referencia, voucher, etc."
        :disabled="loading"
      />

      <div data-field="notes">
        <label for="movement-notes" class="mb-1 block text-sm font-medium text-theme-primary">
          Notas
        </label>
        <UiTextarea
          id="movement-notes"
          v-model="form.notes"
          :rows="3"
          placeholder="Notas adicionales sobre el movimiento..."
          :disabled="loading"
        />
        <p class="mt-1 text-sm text-theme-secondary">{{ form.notes.length }}/500 caracteres</p>
      </div>

      <div class="rounded-ios border border-hairline bg-theme-surface p-4">
        <h3 class="mb-2 text-sm font-semibold text-theme-primary">Resumen del movimiento</h3>
        <div class="space-y-1 text-sm">
          <div class="flex justify-between">
            <span class="text-theme-secondary">Tipo</span>
            <span class="font-medium text-theme-primary">{{ typeLabel(form.type) }}</span>
          </div>
          <div class="flex justify-between">
            <span class="text-theme-secondary">Concepto</span>
            <span class="font-medium text-theme-primary">{{ conceptLabel(form.concept) }}</span>
          </div>
          <div class="flex justify-between">
            <span class="text-theme-secondary">Monto</span>
            <span class="text-lg font-bold tabular-nums" :class="amountTone(form.type)">
              {{ amountPrefix(form.type) }}{{ formatCurrency(form.amount) }}
            </span>
          </div>
        </div>
      </div>
    </form>

    <template #footer>
      <UiButton variant="secondary" :disabled="loading" @click="close">Cancelar</UiButton>
      <UiButton
        variant="primary"
        :loading="loading"
        :disabled="!canSubmit"
        data-modal-submit
        @click="handleSubmit"
      >
        <template #icon-left>
          <PlusIcon class="h-4 w-4" aria-hidden="true" />
        </template>
        Registrar movimiento
      </UiButton>
    </template>
  </UiModal>
</template>

<script setup>
import { computed, ref, watch } from 'vue'
import UiButton from '@/components/ui/Button.vue'
import UiInput from '@/components/ui/Input.vue'
import UiModal from '@/components/ui/Modal.vue'
import UiSelect from '@/components/ui/Select.vue'
import UiTextarea from '@/components/ui/UiTextarea.vue'
import CurrencyInput from '@/components/ui/CurrencyInput.vue'
import { useApi } from '@/composables/useApi'
import { formatCurrency } from '@/composables/useFormatters'
import { PlusIcon } from '@heroicons/vue/24/outline'

const props = defineProps({
  modelValue: {
    type: Boolean,
    default: false
  },
  session: {
    type: Object,
    default: null
  }
})

const emit = defineEmits(['update:modelValue', 'success'])

const { post } = useApi()

const TYPE_OPTIONS = [
  { label: 'Ingreso', value: 'income' },
  { label: 'Egreso', value: 'expense' },
  { label: 'Retiro', value: 'withdrawal' },
  { label: 'Depósito', value: 'deposit' },
  { label: 'Ajuste', value: 'adjustment' }
]

const CONCEPT_OPTIONS = [
  { label: 'Saldo de apertura', value: 'opening_balance' },
  { label: 'Devolución de venta', value: 'sale_refund' },
  { label: 'Retiro', value: 'withdrawal' },
  { label: 'Depósito', value: 'deposit' },
  { label: 'Ajuste', value: 'adjustment' }
]

const TYPE_LABELS = {
  income: 'Ingreso',
  expense: 'Egreso',
  withdrawal: 'Retiro',
  deposit: 'Depósito',
  adjustment: 'Ajuste'
}

const loading = ref(false)
const errors = ref({})

const form = ref({
  type: 'income',
  concept: 'deposit',
  amount: 0,
  description: '',
  reference: '',
  notes: ''
})

const canSubmit = computed(
  () =>
    Boolean(form.value.type) &&
    Boolean(form.value.concept) &&
    Number(form.value.amount) > 0 &&
    Boolean(form.value.description.trim()) &&
    !loading.value
)

const normalizeFieldError = value => (Array.isArray(value) ? value[0] : value) || ''

const fieldError = key => normalizeFieldError(errors.value[key])

const typeLabel = type => TYPE_LABELS[type] || type

const conceptLabel = concept => {
  const option = CONCEPT_OPTIONS.find(item => item.value === concept)
  return option?.label || '—'
}

const isInflow = type => ['income', 'deposit'].includes(type)

const isOutflow = type => ['expense', 'withdrawal'].includes(type)

const amountTone = type => {
  if (isInflow(type)) return 'text-systemGreen-600'
  if (isOutflow(type)) return 'text-systemRed-600'
  return 'text-theme-secondary'
}

const amountPrefix = type => {
  if (isInflow(type)) return '+'
  if (isOutflow(type)) return '-'
  return ''
}

const handleModelValue = value => emit('update:modelValue', value)

watch(
  () => props.modelValue,
  value => {
    if (value) errors.value = {}
  }
)

const close = () => emit('update:modelValue', false)

const validate = () => {
  const next = {}

  if (!form.value.type) next.type = 'Selecciona el tipo de movimiento'
  if (!form.value.concept) next.concept = 'Selecciona el concepto'
  if (!form.value.description.trim()) next.description = 'La descripción es requerida'
  if (!form.value.amount || Number(form.value.amount) <= 0) {
    next.amount = 'El monto debe ser mayor a 0'
  }

  errors.value = next
  return Object.keys(next).length === 0
}

const buildPayload = () => {
  const payload = {
    cash_register_session_id: props.session?.id,
    concept: form.value.concept,
    type: form.value.type,
    amount: Number(form.value.amount),
    description: form.value.description.trim(),
    reference: form.value.reference.trim() || null,
    notes: form.value.notes.trim() || null
  }

  if (props.session?.branch_id) {
    payload.branch_id = props.session.branch_id
  }

  return payload
}

const handleError = error => {
  const data = error.response?.data || {}
  const next = {}

  Object.entries(data.errors || {}).forEach(([key, value]) => {
    next[key] = normalizeFieldError(value)
  })

  next.general = data.message || error.message || 'No se pudo registrar el movimiento'
  errors.value = next
}

const handleSubmit = async () => {
  if (loading.value) return

  errors.value = {}
  if (!validate()) return

  loading.value = true

  try {
    const response = await post('/api/cash-movements', buildPayload())
    emit('success', response.data)
    close()
  } catch (error) {
    handleError(error)
  } finally {
    loading.value = false
  }
}
</script>
