<template>
  <Modal
    :model-value="show"
    title="Registrar Movimiento de Caja"
    size="md"
    @update:model-value="$emit('close')"
    @close="$emit('close')"
  >
    <form
      @submit.prevent="handleSubmit"
    >
      <div class="space-y-4 bg-canvas">
        <!-- Estado (Ingreso / Egreso / Retiro / Depósito / Ajuste) -->
        <UiStatusBadge
          :variant="getTypeVariant(formData.type)"
          :label="getTypeText(formData.type)"
          size="md"
        />
        <!-- Tipo de Movimiento -->
        <div>
          <label class="block text-sm font-medium text-theme-primary mb-1">
            Tipo de Movimiento <span class="text-red-500">*</span>
          </label>
          <select
            v-model="formData.type"
            :class="inputClasses"
            :disabled="loading"
          >
            <option value="income">Ingreso</option>
            <option value="expense">Egreso</option>
            <option value="withdrawal">Retiro</option>
            <option value="deposit">Depósito</option>
            <option value="adjustment">Ajuste</option>
          </select>
        </div>

        <!-- Concepto -->
        <div>
          <label class="block text-sm font-medium text-theme-primary mb-1">
            Concepto <span class="text-red-500">*</span>
          </label>
          <input
            v-model="formData.description"
            type="text"
            :class="inputClasses"
            :disabled="loading"
            placeholder="Descripción del movimiento..."
          />
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

        <!-- Referencia -->
        <div>
          <label class="block text-sm font-medium text-theme-primary mb-1">
            Referencia
          </label>
          <input
            v-model="formData.reference"
            type="text"
            :class="inputClasses"
            :disabled="loading"
            placeholder="Número de referencia, voucher, etc."
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
            placeholder="Notas adicionales sobre el movimiento..."
            maxlength="500"
          ></textarea>
          <p class="mt-1 text-sm text-theme-secondary">
            {{ formData.notes?.length || 0 }}/500 caracteres
          </p>
        </div>

        <!-- Resumen -->
        <div class="bg-theme-surface border border-hairline rounded-lg p-4">
          <h3 class="text-sm font-semibold text-theme-primary mb-2">Resumen del Movimiento</h3>
          <div class="space-y-1 text-sm">
            <div class="flex justify-between">
              <span class="text-theme-secondary">Tipo:</span>
              <span class="font-medium text-theme-primary">{{ getTypeText(formData.type) }}</span>
            </div>
            <div class="flex justify-between">
              <span class="text-theme-secondary">Concepto:</span>
              <span class="font-medium text-theme-primary">{{ formData.description || 'No especificado' }}</span>
            </div>
            <div class="flex justify-between">
              <span class="text-theme-secondary">Monto:</span>
              <span class="font-bold text-lg tabular-nums" :class="getAmountClass(formData.type)">
                {{ getAmountPrefix(formData.type) }}{{ formatCurrency(formData.amount) }}
              </span>
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
          Registrar Movimiento
        </Button>
      </div>
    </template>
  </Modal>
</template>

<script setup>
import { ref, computed } from 'vue'
import Modal from '@/components/ui/Modal.vue'
import Button from '@/components/ui/Button.vue'
import UiStatusBadge from '@/components/ui/StatusBadge.vue'
import CurrencyInput from '@/components/ui/CurrencyInput.vue'
import { useApi } from '@/composables/useApi'
import { formatCurrency } from '@/composables/useFormatters'
import { PlusIcon } from '@heroicons/vue/24/outline'

const props = defineProps({
  show: {
    type: Boolean,
    default: false
  },
  session: {
    type: Object,
    default: null
  }
})

const emit = defineEmits(['close', 'success'])

const { post } = useApi()

// Estado
const loading = ref(false)
const errors = ref({})

// Formulario
const formData = ref({
  type: 'income',
  amount: 0,
  description: '',
  reference: '',
  notes: ''
})

// Validación simple
const validateForm = () => {
  let valid = true
  if (!formData.value.type) {
    errors.value.type = 'El tipo de movimiento es requerido'
    valid = false
  }
  if (!formData.value.amount || formData.value.amount <= 0) {
    errors.value.amount = 'El monto debe ser mayor a 0'
    valid = false
  }
  if (!formData.value.description) {
    errors.value.description = 'La descripción es requerida'
    valid = false
  }
  return valid
}

// Computed
const canSubmit = computed(() => {
  return formData.value.type &&
         formData.value.amount > 0 &&
         formData.value.description &&
         !loading.value
})

const inputClasses = computed(() => {
  const base = 'block w-full px-3 py-2 border border-hairline rounded-md shadow-sm bg-theme-surface-elevated text-theme-primary focus:outline-none sm:text-sm'
  return loading.value ? `${base} bg-theme-surface cursor-not-allowed opacity-50` : `${base}`
})

// Métodos
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
      cash_register_session_id: props.session?.id
    }

    const response = await post('/api/cash-movements', submitData)
    emit('success', response.data)
    emit('close')
  } catch (error) {
    if (error.response?.data?.errors) {
      errors.value = error.response.data.errors
    } else {
      errors.value = { general: error.message || 'Error al registrar el movimiento' }
    }
  } finally {
    loading.value = false
  }
}

// formatCurrency is imported from useFormatters (PAGOS-MNY-002 / PR-pagos-01).

const getTypeText = (type) => {
  const texts = {
    income: 'Ingreso',
    expense: 'Egreso',
    withdrawal: 'Retiro',
    deposit: 'Depósito',
    adjustment: 'Ajuste'
  }
  return texts[type] || type
}

const getTypeVariant = (type) => {
  const variants = {
    income: 'success',
    deposit: 'success',
    expense: 'error',
    withdrawal: 'error',
    adjustment: 'neutral'
  }
  return variants[type] || 'neutral'
}

const getAmountClass = (type) => {
  if (['income', 'deposit'].includes(type)) {
    return 'text-systemGreen-600'
  } else if (['expense', 'withdrawal'].includes(type)) {
    return 'text-systemRed-600'
  }
  return 'text-theme-secondary'
}

const getAmountPrefix = (type) => {
  if (['income', 'deposit'].includes(type)) {
    return '+'
  } else if (['expense', 'withdrawal'].includes(type)) {
    return '-'
  }
  return ''
}
</script>
