<template>
  <Modal
    :show="show"
    title="Cerrar Sesión de Caja"
    size="lg"
    @close="$emit('close')"
  >
    <div class="space-y-6">
      <!-- Resumen de la Sesión -->
      <div class="bg-theme-surface border border-theme rounded-lg p-4">
        <h3 class="text-lg font-semibold text-theme-primary mb-3">Resumen de la Sesión</h3>
        <div class="grid grid-cols-2 gap-4 text-sm">
          <div>
            <span class="text-theme-secondary">Monto de Apertura:</span>
            <span class="font-semibold ml-2 text-theme-primary">{{ formatCurrency(session?.opening_amount) }}</span>
          </div>
          <div>
            <span class="text-theme-secondary">Total Ingresos:</span>
            <span class="font-semibold ml-2 text-green-600">{{ formatCurrency(summary?.total_income) }}</span>
          </div>
          <div>
            <span class="text-theme-secondary">Total Egresos:</span>
            <span class="font-semibold ml-2 text-red-600">{{ formatCurrency(summary?.total_expenses) }}</span>
          </div>
          <div>
            <span class="text-theme-secondary">Monto Esperado:</span>
            <span class="font-semibold ml-2 text-theme-primary">{{ formatCurrency(summary?.expected_amount) }}</span>
          </div>
        </div>
      </div>

      <!-- Arqueo -->
      <div class="space-y-4">
        <h3 class="text-lg font-semibold text-theme-primary">Arqueo de Caja</h3>

        <ValidatedForm
          ref="form"
          :schema="validationSchema"
          @submit="handleSubmit"
        >
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
                <label class="block text-sm font-medium text-theme-primary mb-1">Tarjeta de Débito</label>
                <CurrencyInput
                  v-model="arqueo.tarjeta_debito"
                  placeholder="0.00"
                  :min="0"
                  @input="calculateArqueoTotal"
                />
              </div>

              <div>
                <label class="block text-sm font-medium text-theme-primary mb-1">Tarjeta de Crédito</label>
                <CurrencyInput
                  v-model="arqueo.tarjeta_credito"
                  placeholder="0.00"
                  :min="0"
                  @input="calculateArqueoTotal"
                />
              </div>

              <div>
                <label class="block text-sm font-medium text-theme-primary mb-1">Transferencia</label>
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
          <div class="bg-primary-50 border border-primary-200 rounded-lg p-4">
            <div class="flex justify-between items-center">
              <span class="text-lg font-semibold text-primary-900">Total del Arqueo:</span>
              <span class="text-xl font-bold text-primary-900">{{ formatCurrency(arqueoTotal) }}</span>
            </div>
          </div>

          <!-- Diferencia -->
          <div v-if="diferencia !== 0" class="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
            <div class="flex justify-between items-center">
              <span class="text-lg font-semibold text-yellow-900">Diferencia:</span>
              <span
                class="text-xl font-bold"
                :class="diferencia > 0 ? 'text-green-600' : 'text-red-600'"
              >
                {{ formatCurrency(Math.abs(diferencia)) }}
                {{ diferencia > 0 ? '(Sobrante)' : '(Faltante)' }}
              </span>
            </div>
          </div>

          <!-- Notas de Cierre -->
          <div>
            <label class="block text-sm font-medium text-theme-primary mb-1">
              Notas de Cierre
            </label>
            <textarea
              v-model="formData.closing_notes"
              :class="inputClasses"
              :disabled="loading"
              rows="3"
              placeholder="Notas adicionales sobre el cierre de caja..."
              maxlength="500"
            ></textarea>
            <p class="mt-1 text-sm text-theme-secondary">
              {{ formData.closing_notes?.length || 0 }}/500 caracteres
            </p>
          </div>

          <!-- Justificación de Diferencia -->
          <div v-if="Math.abs(diferencia) > 0.01" class="bg-red-50 border border-red-200 rounded-lg p-4">
            <label class="block text-sm font-medium text-red-700 mb-2">
              Justificación de la Diferencia
            </label>
            <textarea
              v-model="formData.difference_justification"
              :class="inputClasses"
              :disabled="loading"
              rows="2"
              placeholder="Explique la razón de la diferencia..."
              maxlength="500"
            ></textarea>
          </div>

          <!-- Resumen de Cierre -->
          <div class="mt-6 p-4 bg-primary-50 rounded-lg border border-primary-200">
            <h4 class="text-sm font-semibold text-primary-900 mb-3">
              Resumen de Cierre
            </h4>
            <div class="space-y-2 text-sm">
              <div class="flex justify-between">
                <span class="text-theme-secondary">Total Transacciones:</span>
                <span class="font-medium text-theme-primary">{{ summary?.transactions_count || 0 }}</span>
              </div>
              <div class="flex justify-between">
                <span class="text-theme-secondary">Total Movimientos:</span>
                <span class="font-medium text-theme-primary">{{ summary?.movements_count || 0 }}</span>
              </div>
              <div class="flex justify-between">
                <span class="text-theme-secondary">Duración de Sesión:</span>
                <span class="font-medium text-theme-primary">{{ sessionDuration }}</span>
              </div>
            </div>
          </div>

          <!-- Checkbox para generar reporte automático -->
          <div class="mt-4">
            <label class="flex items-center">
              <input
                v-model="formData.generate_report"
                type="checkbox"
                class="rounded border-theme text-accent focus:ring-primary-500"
                checked
              />
              <span class="ml-2 text-sm text-theme-primary">
                Generar reporte PDF automáticamente al cerrar
              </span>
            </label>
          </div>

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
              >
                <XMarkIcon class="w-4 h-4 mr-2" />
                Cerrar Caja
              </Button>
            </div>
          </template>
        </ValidatedForm>
      </div>
    </div>
  </Modal>
</template>

<script setup>
import { ref, computed, watch, onMounted } from 'vue'
import Modal from '@/components/ui/Modal.vue'
import Button from '@/components/ui/Button.vue'
import CurrencyInput from '@/components/ui/CurrencyInput.vue'
import ValidatedForm from '@/components/ValidatedForm.vue'
import { useCashRegister } from '@/composables/useCashRegister'
import { useToast } from '@/composables/useToast'
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

// Validación
const validationSchema = {
  closing_amount: {
    required: true,
    min: 0,
    message: 'El monto de cierre es requerido'
  }
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
  const base = 'block w-full px-3 py-2 border rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-accent sm:text-sm bg-theme-surface-elevated text-theme-primary'
  return loading.value ? `${base} cursor-not-allowed opacity-50` : `${base} border-theme`
})

// Métodos
const calculateArqueoTotal = () => {
  // Auto-completar el monto de cierre con el total del arqueo
  formData.value.closing_amount = arqueoTotal.value
}

const handleSubmit = async (data) => {
  loading.value = true
  errors.value = {}

  try {
    const submitData = {
      ...data,
      session_id: props.session?.id,
      arqueo: arqueo.value
    }

    const result = await closeSession(submitData)

    // Generar reporte PDF si está marcado
    if (formData.value.generate_report) {
      await generateClosureReport(result.session_id)
    }

    // Notificación de éxito
    toast.success(
      `Caja cerrada exitosamente\n` +
      `Monto final: S/ ${data.closing_amount}\n` +
      `Diferencia: S/ ${data.closing_amount - (props.session?.opening_amount || 0)}`,
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
    toast.error(
      error.response?.data?.message || 'Error al cerrar la caja',
      {
        duration: 7000,
        title: '✗ Error al Cerrar Caja'
      }
    )

    if (error.response?.data?.errors) {
      errors.value = error.response.data.errors
    } else {
      errors.value = { general: error.message || 'Error al cerrar la sesión de caja' }
    }
  } finally {
    loading.value = false
  }
}

const generateClosureReport = async (sessionId) => {
  try {
    const response = await fetch(`/api/cash-register/sessions/${sessionId}/closure-report`, {
      method: 'GET',
      headers: {
        'Authorization': `Bearer ${localStorage.getItem('auth_token')}`,
        'Accept': 'application/pdf'
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
    console.error('Error generando reporte:', error)
    toast.error('Error al generar reporte PDF')
  }
}

const formatCurrency = (amount) => {
  return new Intl.NumberFormat('es-PE', {
    style: 'currency',
    currency: 'PEN'
  }).format(amount || 0)
}

// Watch
watch(() => props.show, (newValue) => {
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
})
</script>

