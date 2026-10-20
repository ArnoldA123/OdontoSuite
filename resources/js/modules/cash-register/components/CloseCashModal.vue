<template>
  <UiModal
    :model-value="modelValue"
    title="Cerrar sesión de caja"
    size="lg"
    @update:model-value="handleModelValue"
  >
    <div class="space-y-6 bg-canvas">
      <div
        v-if="generalError"
        role="alert"
        data-close-cash-error
        class="rounded-ios border border-systemRed-200 bg-systemRed-50 p-3 text-sm text-systemRed-700"
      >
        {{ generalError }}
      </div>

      <UiCard variant="flat" padding="md">
        <h3 class="mb-3 text-lg font-semibold text-theme-primary">Resumen de la sesión</h3>
        <div class="grid grid-cols-2 gap-4 text-sm">
          <div>
            <span class="text-theme-secondary">Monto de apertura:</span>
            <span class="ml-2 font-semibold text-theme-primary tabular-nums">
              {{ formatCurrency(session?.opening_amount) }}
            </span>
          </div>
          <div>
            <span class="text-theme-secondary">Total ingresos:</span>
            <span class="ml-2 font-semibold text-systemGreen-600 tabular-nums">
              {{ formatCurrency(summary?.total_income) }}
            </span>
          </div>
          <div>
            <span class="text-theme-secondary">Total egresos:</span>
            <span class="ml-2 font-semibold text-systemRed-600 tabular-nums">
              {{ formatCurrency(summary?.total_expenses) }}
            </span>
          </div>
          <div>
            <span class="text-theme-secondary">Saldo esperado:</span>
            <span data-close-expected class="ml-2 font-semibold text-theme-primary tabular-nums">
              {{ formatCurrency(summary?.expected_amount) }}
            </span>
          </div>
        </div>
      </UiCard>

      <form
        id="close-cash-form"
        data-close-cash-form
        class="space-y-6"
        @submit.prevent="handleSubmit"
      >
        <div>
          <h3 class="text-lg font-semibold text-theme-primary">Arqueo de caja</h3>
          <p class="mt-1 text-sm text-theme-secondary">
            Registra el total contado. El desglose por método de pago es opcional y no modifica el
            conteo.
          </p>
        </div>

        <div data-close-counted>
          <CurrencyInput
            v-model="formData.closing_amount"
            label="Monto real en caja (contado)"
            placeholder="0.00"
            required
            :disabled="loading"
            :error="fieldError('closing_amount')"
            help="Efectivo y comprobantes contados en caja"
          />
        </div>

        <UiCard variant="flat" padding="md">
          <div class="flex flex-wrap items-center justify-between gap-2">
            <h4 class="text-sm font-semibold text-theme-primary">Desglose por método de pago</h4>
            <UiStatusBadge
              v-if="arqueoTotal > 0"
              :variant="arqueoReconciled ? 'success' : 'warning'"
              size="sm"
              :label="arqueoReconciled ? 'Coincide con el conteo' : 'Pendiente de cuadrar'"
            />
          </div>

          <div class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-2">
            <div v-for="line in ARQUEO_LINES" :key="line.key" :data-arqueo="line.key">
              <CurrencyInput
                v-model="arqueo[line.key]"
                :label="line.label"
                placeholder="0.00"
                :disabled="loading"
              />
            </div>
          </div>

          <div class="mt-4 flex items-center justify-between border-t border-hairline pt-3 text-sm">
            <span class="text-theme-secondary">Total del desglose</span>
            <span data-arqueo-total class="font-semibold text-theme-primary tabular-nums">
              {{ formatCurrency(arqueoTotal) }}
            </span>
          </div>

          <p data-arqueo-gap class="mt-2 text-sm" :class="arqueoGapTone">
            {{ arqueoGapLabel }}
          </p>
        </UiCard>

        <UiCard variant="flat" padding="md">
          <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
              <p class="text-sm font-semibold text-theme-primary">
                Diferencia contra el saldo esperado
              </p>
              <p class="mt-1 text-sm text-theme-secondary">
                Conteo menos saldo esperado de la caja.
              </p>
            </div>
            <UiStatusBadge
              :variant="differenceVariant"
              :label="differenceLabel"
              size="md"
              data-close-difference
            />
          </div>
        </UiCard>

        <div>
          <label for="close-cash-notes" class="mb-1 block text-sm font-medium text-theme-primary">
            Notas de cierre
          </label>
          <UiTextarea
            id="close-cash-notes"
            :model-value="formData.closing_notes"
            data-close-notes
            :rows="3"
            placeholder="Notas adicionales sobre el cierre de caja..."
            :disabled="loading"
            @update:model-value="updateClosingNotes"
          />
          <p class="mt-1 text-sm text-theme-secondary">
            {{ formData.closing_notes.length }}/500 caracteres
          </p>
        </div>

        <UiCard variant="flat" padding="md">
          <h4 class="mb-3 text-sm font-semibold text-theme-primary">Resumen de cierre</h4>
          <div class="space-y-2 text-sm">
            <div class="flex justify-between gap-3">
              <span class="text-theme-secondary">Total transacciones</span>
              <span class="font-medium text-theme-primary tabular-nums">
                {{ summary?.transactions_count || 0 }}
              </span>
            </div>
            <div class="flex justify-between gap-3">
              <span class="text-theme-secondary">Total movimientos</span>
              <span class="font-medium text-theme-primary tabular-nums">
                {{ summary?.movements_count || 0 }}
              </span>
            </div>
            <div class="flex justify-between gap-3">
              <span class="text-theme-secondary">Duración de la sesión</span>
              <span class="font-medium text-theme-primary">{{ sessionDuration }}</span>
            </div>
          </div>
        </UiCard>

        <label class="flex items-center gap-2">
          <input
            v-model="formData.generate_report"
            type="checkbox"
            class="h-4 w-4 rounded border-hairline"
          />
          <span class="text-sm text-theme-primary">Descargar el reporte de cierre en PDF</span>
        </label>
      </form>
    </div>

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
          <XMarkIcon class="h-4 w-4" aria-hidden="true" />
        </template>
        Cerrar caja
      </UiButton>
    </template>
  </UiModal>
</template>

<script setup>
import { computed, ref, watch } from 'vue'
import { XMarkIcon } from '@heroicons/vue/24/outline'
import CurrencyInput from '@/components/ui/CurrencyInput.vue'
import UiButton from '@/components/ui/Button.vue'
import UiCard from '@/components/ui/Card.vue'
import UiModal from '@/components/ui/Modal.vue'
import UiStatusBadge from '@/components/ui/StatusBadge.vue'
import UiTextarea from '@/components/ui/UiTextarea.vue'
import { useApi } from '@/composables/useApi'
import { useCashRegister } from '@/composables/useCashRegister'
import { useToast } from '@/composables/useToast'
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
  summary: {
    type: Object,
    default: null
  }
})

const emit = defineEmits(['update:modelValue', 'success'])

const ARQUEO_LINES = [
  { key: 'efectivo', label: 'Efectivo' },
  { key: 'tarjeta_debito', label: 'Tarjeta de débito' },
  { key: 'tarjeta_credito', label: 'Tarjeta de crédito' },
  { key: 'transferencia', label: 'Transferencia' },
  { key: 'otros', label: 'Otros' }
]

const { download } = useApi()
const { closeSession } = useCashRegister()
const toast = useToast()

const loading = ref(false)
const errors = ref({})

const formData = ref({
  closing_amount: 0,
  closing_notes: '',
  generate_report: true
})

const arqueo = ref({
  efectivo: 0,
  tarjeta_debito: 0,
  tarjeta_credito: 0,
  transferencia: 0,
  otros: 0
})

const roundMoney = value => Math.round((Number(value) || 0) * 100) / 100

const fieldError = field => {
  const value = errors.value[field]
  if (!value) return ''
  return Array.isArray(value) ? value[0] : String(value)
}

const generalError = computed(() => fieldError('general'))

const arqueoTotal = computed(() =>
  roundMoney(ARQUEO_LINES.reduce((sum, line) => sum + (Number(arqueo.value[line.key]) || 0), 0))
)

const arqueoGap = computed(() =>
  roundMoney((Number(formData.value.closing_amount) || 0) - arqueoTotal.value)
)

const arqueoReconciled = computed(() => arqueoTotal.value > 0 && arqueoGap.value === 0)

const arqueoGapTone = computed(() => {
  if (!arqueoTotal.value) return 'text-theme-secondary'
  return arqueoReconciled.value ? 'text-systemGreen-600' : 'text-systemYellow-700'
})

const arqueoGapLabel = computed(() => {
  if (!arqueoTotal.value) return 'Sin desglose: el conteo es el único total.'

  if (arqueoGap.value === 0) return 'El desglose coincide con el conteo.'

  if (arqueoGap.value > 0) {
    return `Faltan ${formatCurrency(arqueoGap.value)} por asignar en el desglose.`
  }

  return `El desglose supera el conteo en ${formatCurrency(Math.abs(arqueoGap.value))}.`
})

const difference = computed(() =>
  roundMoney(
    (Number(formData.value.closing_amount) || 0) - (Number(props.summary?.expected_amount) || 0)
  )
)

const differenceVariant = computed(() => (difference.value < 0 ? 'error' : 'success'))

const differenceLabel = computed(() => {
  if (difference.value === 0) return 'Cuadra'

  const kind = difference.value > 0 ? 'Sobrante' : 'Faltante'
  return `${kind}: ${formatCurrency(Math.abs(difference.value))}`
})

const canSubmit = computed(() => Number(formData.value.closing_amount) > 0 && !loading.value)

const sessionDuration = computed(() => {
  if (!props.session?.opened_at) return 'N/A'

  const opened = new Date(props.session.opened_at)
  if (Number.isNaN(opened.getTime())) return 'N/A'

  const elapsedMinutes = Math.max(0, Math.floor((Date.now() - opened.getTime()) / (1000 * 60)))
  return `${Math.floor(elapsedMinutes / 60)}h ${elapsedMinutes % 60}m`
})

const handleModelValue = value => emit('update:modelValue', value)

const close = () => emit('update:modelValue', false)

const updateClosingNotes = value => {
  formData.value.closing_notes = value.slice(0, 500)
}

const resetForm = () => {
  formData.value = {
    closing_amount: 0,
    closing_notes: '',
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

const validate = () => {
  const next = {}
  const amount = Number(formData.value.closing_amount)

  if (amount < 0.01) {
    next.closing_amount = 'El monto contado debe ser mayor a 0'
  }

  errors.value = next
  return Object.keys(next).length === 0
}

const buildPayload = () => ({
  session_id: props.session?.id,
  closing_amount: Number(formData.value.closing_amount),
  closing_notes: formData.value.closing_notes.trim(),
  arqueo: Object.fromEntries(
    ARQUEO_LINES.map(line => [line.key, Number(arqueo.value[line.key]) || 0])
  )
})

const handleError = error => {
  const data = error.response?.data || {}
  const next = {}

  Object.entries(data.errors || {}).forEach(([key, value]) => {
    next[key] = Array.isArray(value) ? value[0] : String(value)
  })

  next.general =
    next.session || next.general || data.message || error.message || 'No se pudo cerrar la caja'
  delete next.session
  errors.value = next
}

const saveBlob = (blob, filename) => {
  const url = URL.createObjectURL(blob)
  const link = document.createElement('a')
  link.href = url
  link.setAttribute('download', filename)
  document.body.appendChild(link)
  link.click()
  link.remove()
  URL.revokeObjectURL(url)
}

const generateClosureReport = async sessionId => {
  if (!sessionId) return

  try {
    const blob = await download(`/api/cash-register/sessions/${sessionId}/closure-report`)
    saveBlob(blob, `cierre-caja-${sessionId}.pdf`)
  } catch {
    toast.error('No se pudo descargar el reporte de cierre')
  }
}

const handleSubmit = async () => {
  if (loading.value) return

  errors.value = {}
  if (!validate()) return

  loading.value = true

  try {
    const result = await closeSession(buildPayload())

    if (formData.value.generate_report) {
      await generateClosureReport(result?.id || props.session?.id)
    }

    emit('success', result)
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
