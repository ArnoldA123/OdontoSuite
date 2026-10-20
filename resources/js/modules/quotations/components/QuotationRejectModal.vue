<template>
  <UiModal model-value title="Rechazar presupuesto" size="md" @close="emit('close')">
    <div class="space-y-5 bg-canvas" data-quotation-reject>
      <div class="space-y-3 rounded-lg border border-hairline p-4" data-modal-summary>
        <div class="flex items-center justify-between gap-3">
          <p class="text-xs font-semibold uppercase tracking-wide text-theme-secondary">
            Presupuesto
          </p>
          <UiStatusBadge :variant="status.variant" :label="status.label" size="sm" />
        </div>
        <dl class="space-y-1.5 text-sm">
          <div class="flex items-baseline justify-between gap-3">
            <dt class="text-theme-secondary">Número</dt>
            <dd class="text-right font-medium text-theme-primary tabular-nums">
              {{ quotationNumber }}
            </dd>
          </div>
          <div class="flex items-baseline justify-between gap-3">
            <dt class="text-theme-secondary">Paciente</dt>
            <dd class="text-right font-medium text-theme-primary">{{ patientName }}</dd>
          </div>
          <div class="flex items-baseline justify-between gap-3">
            <dt class="text-theme-secondary">Total</dt>
            <dd class="text-right font-semibold text-theme-primary tabular-nums">
              {{ formatCurrency(quotation.total_amount) }}
            </dd>
          </div>
        </dl>
      </div>

      <div>
        <div class="mb-1 flex items-baseline justify-between gap-3">
          <label for="quotation-reject-reason" class="block text-sm font-medium text-label">
            Motivo del rechazo
          </label>
          <span class="text-xs text-theme-secondary tabular-nums">{{ reason.length }}/500</span>
        </div>
        <UiTextarea
          id="quotation-reject-reason"
          v-model="reason"
          :rows="3"
          :disabled="saving"
          :error="Boolean(fieldError)"
          maxlength="500"
          required
          data-modal-field="reason"
        />
      </div>

      <p v-if="displayError" class="text-sm text-error-500" role="alert" data-modal-error>
        {{ displayError }}
      </p>
    </div>

    <template #footer>
      <UiButton
        type="button"
        variant="secondary"
        :disabled="saving"
        data-modal-action="cancel"
        @click="emit('close')"
      >
        Cancelar
      </UiButton>
      <UiButton
        type="button"
        variant="danger"
        :disabled="saving"
        :loading="saving"
        data-modal-action="reject"
        @click="onReject"
      >
        <template #icon-left>
          <XMarkIcon class="h-4 w-4" aria-hidden="true" />
        </template>
        Rechazar presupuesto
      </UiButton>
    </template>
  </UiModal>
</template>

<script setup>
import { computed, ref, watch } from 'vue'
import { XMarkIcon } from '@heroicons/vue/24/outline'
import { useQuotations } from '@/composables/useQuotations'
import { formatCurrency } from '../../../composables/useFormatters'
import { getStatusMeta } from '../quotationMeta.js'
import UiButton from '@/components/ui/Button.vue'
import UiModal from '@/components/ui/Modal.vue'
import UiStatusBadge from '@/components/ui/StatusBadge.vue'
import UiTextarea from '@/components/ui/UiTextarea.vue'

const props = defineProps({
  quotation: {
    type: Object,
    required: true
  }
})

const emit = defineEmits(['close', 'rejected'])

const { rejectQuotation } = useQuotations()

const reason = ref('')
const saving = ref(false)
const fieldError = ref('')
const submitError = ref('')

const status = computed(() => getStatusMeta(props.quotation?.status))

const quotationNumber = computed(() =>
  props.quotation?.quotation_number ? `#${props.quotation.quotation_number}` : '—'
)

const patientName = computed(() => {
  const patient = props.quotation?.patient
  if (!patient) return '—'

  const name = `${patient.first_name || ''} ${patient.last_name || ''}`.trim()

  return name || '—'
})

const displayError = computed(() => fieldError.value || submitError.value)

watch(reason, value => {
  if (value.trim()) fieldError.value = ''
})

const onReject = async () => {
  if (saving.value) return

  submitError.value = ''

  const trimmed = reason.value.trim()
  if (!trimmed) {
    fieldError.value = 'Indica el motivo del rechazo.'
    return
  }

  fieldError.value = ''
  saving.value = true

  try {
    const updated = await rejectQuotation(props.quotation.id, trimmed)
    emit('rejected', updated)
    emit('close')
  } catch (err) {
    submitError.value = err.response?.data?.message || 'No pudimos rechazar el presupuesto.'
  } finally {
    saving.value = false
  }
}
</script>
