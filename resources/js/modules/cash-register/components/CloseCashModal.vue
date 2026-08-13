<template>
  <Modal
    :model-value="show"
    title="Cerrar Sesión de Caja"
    size="lg"
    @update:model-value="$emit('close')"
    @close="$emit('close')"
  >
    <div class="space-y-6 bg-canvas">
      <!-- Resumen de la Sesión -->
      <UiCard variant="flat" padding="md">
        <h3 class="text-lg font-semibold text-theme-primary mb-3">Resumen de la Sesión</h3>
        <div class="grid grid-cols-2 gap-4 text-sm">
          <div>
            <span class="text-theme-secondary">Monto de Apertura:</span>
            <span
              class="font-semibold ml-2 text-theme-primary tabular-nums"
              :aria-label="`${session?.opening_amount || 0} soles`"
            >
              {{ formatCurrency(session?.opening_amount) }}
            </span>
          </div>
          <div>
            <span class="text-theme-secondary">Total Ingresos:</span>
            <span
              class="font-semibold ml-2 text-systemGreen-600 tabular-nums"
              :aria-label="`${summary?.total_income || 0} soles`"
            >
              {{ formatCurrency(summary?.total_income) }}
            </span>
          </div>
          <div>
            <span class="text-theme-secondary">Total Egresos:</span>
            <span
              class="font-semibold ml-2 text-systemRed-600 tabular-nums"
              :aria-label="`${summary?.total_expenses || 0} soles`"
            >
              {{ formatCurrency(summary?.total_expenses) }}
            </span>
          </div>
          <div>
            <span class="text-theme-secondary">Monto Esperado:</span>
            <span
              class="font-semibold ml-2 text-theme-primary tabular-nums"
              :aria-label="`${summary?.expected_amount || 0} soles`"
            >
              {{ formatCurrency(summary?.expected_amount) }}
            </span>
          </div>
        </div>
      </UiCard>

      <!-- Arqueo -->
      <div class="space-y-4">
        <h3 class="text-lg font-semibold text-theme-primary">Arqueo de Caja</h3>

        <form @submit.prevent="handleSubmit">
          <!-- Monto de Cierre -->
          <div class="mb-4">
            <CurrencyInput
              v-model="formData.closing_amount"
              label="Monto Real en Caja"
              placeholder="0.00"
              :required="true"
              :min="0"
              :precision="2"
              :error="errors.closing_amount"
              help="Ingrese el monto real contado en caja"
              input-class="tabular-nums"
            />
          </div>

          <!-- Desglose por Método de Pago -->
          <div class="space-y-3">
            <h4 class="text-md font-medium text-theme-primary">Desglose por Método de Pago</h4>

            <div class="grid grid-cols-2 gap-4">
              <div>
                <label class="block text-sm font-medium text-theme-primary mb-1">Efectivo</label>
                <CurrencyInput
                  v-model="arqueo.efectivo"
                  placeholder="0.00"
                  :min="0"
                  @input="calculateArqueoTotal"
                />
              </div>

              <div>
                <label class="block text-sm font-medium text-theme-primary mb-1">
                  Tarjeta de Débito
                </label>
                <CurrencyInput
                  v-model="arqueo.tarjeta_debito"
                  placeholder="0.00"
                  :min="0"
                  @input="calculateArqueoTotal"
                />
              </div>

              <div>
                <label class="block text-sm font-medium text-theme-primary mb-1">
                  Tarjeta de Crédito
                </label>
                <CurrencyInput
                  v-model="arqueo.tarjeta_credito"
                  placeholder="0.00"
                  :min="0"
                  @input="calculateArqueoTotal"
                />
              </div>

              <div>
                <label class="block text-sm font-medium text-theme-primary mb-1">
                  Transferencia
                </label>
                <CurrencyInput
                  v-model="arqueo.transferencia"
                  placeholder="0.00"
                  :min="0"
                  @input="calculateArqueoTotal"
                />
              </div>

              <div>
                <label class="block text-sm font-medium text-theme-primary mb-1">Otros</label>
                <CurrencyInput
                  v-model="arqueo.otros"
                  placeholder="0.00"
                  :min="0"
                  @input="calculateArqueoTotal"
                />
              </div>
            </div>
          </div>

          <!-- Total del Arqueo -->
          <UiCard variant="flat" padding="md">
            <div class="flex justify-between items-center">
              <span class="text-lg font-semibold text-theme-primary">Total del Arqueo:</span>
              <span
                class="text-xl font-bold text-theme-primary tabular-nums"
                :aria-label="`${arqueoTotal} soles`"
              >
                {{ formatCurrency(arqueoTotal) }}
              </span>
            </div>
          </UiCard>

          <!-- Diferencia -->
          <UiStatusBadge
            v-if="diferencia !== 0"
            :variant="diferencia > 0 ? 'success' : 'error'"
            :label="`Diferencia: ${formatCurrency(Math.abs(diferencia))} ${diferencia > 0 ? '(Sobrante)' : '(Faltante)'}`"
            size="md"
          />

          <!-- Notas de Cierre -->
          <div>
            <label class="block text-sm font-medium text-theme-primary mb-1">Notas de Cierre</label>
            <textarea
              v-model="formData.closing_notes"
              :class="inputClasses"
              :disabled="loading"
              rows="3"
              placeholder="Notas adicionales sobre el cierre de caja..."
              maxlength="500"
            />
            <p class="mt-1 text-sm text-theme-secondary">
              {{ formData.closing_notes?.length || 0 }}/500 caracteres
            </p>
          </div>

          <!-- Justificación de Diferencia -->
          <div v-if="Math.abs(diferencia) > 0.01" class="p-4">
            <label class="block text-sm font-medium text-systemRed-700 mb-2">
              Justificación de la Diferencia
            </label>
            <textarea
              v-model="formData.difference_justification"
              :class="inputClasses"
              :disabled="loading"
              rows="2"
              placeholder="Explique la razón de la diferencia..."
              maxlength="500"
            />
          </div>

          <!-- Resumen de Cierre -->
          <UiCard variant="flat" padding="md">
            <h4 class="text-sm font-semibold text-theme-primary mb-3">
Resumen de Cierre
</h4>
            <div class="space-y-2 text-sm">
              <div class="flex justify-between">
                <span class="text-theme-secondary">Total Transacciones:</span>
                <span
                  class="font-medium text-theme-primary tabular-nums"
                  :aria-label="`${summary?.transactions_count || 0} transacciones`"
                >
                  {{ summary?.transactions_count || 0 }}
                </span>
              </div>
              <div class="flex justify-between">
                <span class="text-theme-secondary">Total Movimientos:</span>
                <span
                  class="font-medium text-theme-primary tabular-nums"
                  :aria-label="`${summary?.movements_count || 0} movimientos`"
                >
                  {{ summary?.movements_count || 0 }}
                </span>
              </div>
              <div class="flex justify-between">
                <span class="text-theme-secondary">Duración de Sesión:</span>
                <span class="font-medium text-theme-primary">{{ sessionDuration }}</span>
              </div>
            </div>
          </UiCard>

          <!-- Checkbox para generar reporte automático -->
          <div class="mt-4">
            <label class="flex items-center">
              <input
                v-model="formData.generate_report"
                type="checkbox"
                class="rounded border-hairline text-systemBlue-600"
                checked
              />
              <span class="ml-2 text-sm text-theme-primary">
                Generar reporte PDF automáticamente al cerrar
              </span>
            </label>
          </div>
        </form>
      </div>
    </div>

    <template #footer>
      <div class="flex justify-end space-x-3">
        <Button
type="button"
variant="secondary" :disabled="loading" @click="$emit('close')"
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
          <XMarkIcon class="w-4 h-4 mr-2" />
          Cerrar Caja
        </Button>
      </div>
    </template>
  </Modal>
</template>

<script setup>
import { ref, computed, watch, onMounted } from 'vue'
import Modal from '@/components/ui/Modal.vue'
import Button from '@/components/ui/Button.vue'
import CurrencyInput from '@/components/ui/CurrencyInput.vue'
import UiCard from '@/components/ui/Card.vue'
import UiStatusBadge from '@/components/ui/StatusBadge.vue'
import { useCashRegister } from '@/composables/useCashRegister'
import { useToast } from '@/composables/useToast'
import { formatCurrency } from '@/composables/useFormatters'
import { XMarkIcon } from '@heroicons/vue/24/outline'

const props = defineProps({
  show: {
    type: Boolean,
    default: false
  },
  session: {
    type: Object,
    default: null
  },
  summary: {
    type: Object,
    default: null
  }
})

const emit = defineEmits(['close', 'success'])

const { closeSession } = useCashRegister()
const toast = useToast()

// Estado
const loading = ref(false)
const errors = ref({})

// Formulario
const formData = ref({
  session_id: null,
  closing_amount: 0,
  closing_notes: '',
  difference_justification: '',
  generate_report: true
})

// Arqueo
const arqueo = ref({
  efectivo: 0,
  tarjeta_debito: 0,
  tarjeta_credito: 0,
  transferencia: 0,
  otros: 0
})

// Validación simple
const validateForm = () => {
  let valid = true
  if (
    formData.value.closing_amount === null ||
    formData.value.closing_amount === undefined ||
    formData.value.closing_amount === ''
  ) {
    errors.value.closing_amount = 'El monto de cierre es requerido'
    valid = false
  } else if (formData.value.closing_amount < 0) {
    errors.value.closing_amount = 'El monto no puede ser negativo'
    valid = false
  }
  return valid
}

// Computed
const arqueoTotal = computed(() => {
  return Object.values(arqueo.value).reduce((sum, value) => sum + (parseFloat(value) || 0), 0)
})

const diferencia = computed(() => {
  return (formData.value.closing_amount || 0) - (props.summary?.expected_amount || 0)
})

const canSubmit = computed(() => {
  return formData.value.closing_amount > 0 && !loading.value
})

const sessionDuration = computed(() => {
  if (!props.session?.opened_at) return 'N/A'
  const opened = new Date(props.session.opened_at)
  const now = new Date()
  const hours = Math.floor((now - opened) / (1000 * 60 * 60))
  const minutes = Math.floor(((now - opened) % (1000 * 60 * 60)) / (1000 * 60))
  return `${hours}h ${minutes}m`
})

const inputClasses = computed(() => {
  const base =
    'block w-full px-3 py-2 border rounded-md shadow-sm sm:text-sm bg-theme-surface-elevated text-theme-primary'
  return loading.value ? `${base} cursor-not-allowed opacity-50` : `${base} border-hairline`
})

// Métodos
const calculateArqueoTotal = () => {
  // Auto-completar el monto de cierre con el total del arqueo
  formData.value.closing_amount = arqueoTotal.value
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
      session_id: props.session?.id,
      arqueo: arqueo.value
    }

    const result = await closeSession(submitData)

    // Generar reporte PDF si está marcado
    if (formData.value.generate_report) {
      await generateClosureReport(result.id || props.session?.id)
    }

    // Notificación de éxito
    toast.success(
      'Caja cerrada exitosamente\n' +
        `Monto final: S/ ${formData.value.closing_amount}\n` +
        `Diferencia: S/ ${formData.value.closing_amount - (props.session?.opening_amount || 0)}`,
      {
        duration: 6000,
        title: '✓ Caja Cerrada'
      }
    )

    emit('success', result)
    emit('close')

    // Los eventos WebSocket se manejan automáticamente desde el backend
  } catch (error) {
    // Notificación de error
    toast.error(error.response?.data?.message || 'Error al cerrar la caja', {
      duration: 7000,
      title: '✗ Error al Cerrar Caja'
    })

    if (error.response?.data?.errors) {
      errors.value = error.response.data.errors
    } else {
      errors.value = { general: error.message || 'Error al cerrar la sesión de caja' }
    }
  } finally {
    loading.value = false
  }
}

const generateClosureReport = async sessionId => {
  try {
    const response = await fetch(`/api/cash-register/sessions/${sessionId}/closure-report`, {
      method: 'GET',
      headers: {
        Authorization: `Bearer ${localStorage.getItem('auth_token')}`,
        Accept: 'application/pdf'
      }
    })

    if (response.ok) {
      const blob = await response.blob()
      const url = window.URL.createObjectURL(blob)
      const link = document.createElement('a')
      link.href = url
      link.setAttribute('download', `cierre-caja-${sessionId}.pdf`)
      document.body.appendChild(link)
      link.click()
      link.remove()
      window.URL.revokeObjectURL(url)
    }
  } catch (error) {
    toast.error('Error al generar reporte PDF')
  }
}

// formatCurrency is imported from useFormatters (PR-pagos-01 canonicalization).

// Watch
watch(
  () => props.show,
  newValue => {
    if (newValue && props.session) {
      // Reset form when modal opens
      formData.value = {
        session_id: props.session.id,
        closing_amount: 0,
        closing_notes: '',
        difference_justification: '',
        generate_report: true
      }

      arqueo.value = {
        efectivo: 0,
        tarjeta_debito: 0,
        tarjeta_credito: 0,
        transferencia: 0,
        otros: 0
      }

      errors.value = {}
    }
  }
)
</script>
