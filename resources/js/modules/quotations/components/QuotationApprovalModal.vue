<template>
  <UiModal model-value title="Aprobar presupuesto" size="md" @close="emit('close')">
    <div class="space-y-5 bg-canvas" data-quotation-approval>
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

      <p class="text-sm text-theme-secondary">
        Al aprobar se registra la fecha y el usuario que aprueba.
      </p>

      <div>
        <label for="quotation-approval-notes" class="mb-1 block text-sm font-medium text-label">
          Nota de aprobación (opcional)
        </label>
        <UiTextarea
          id="quotation-approval-notes"
          v-model="notes"
          :rows="3"
          :disabled="saving"
          data-modal-field="notes"
        />
      </div>

      <p v-if="error" class="text-sm text-error-500" role="alert" data-modal-error>{{ error }}</p>
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
        variant="success"
        :loading="saving"
        data-modal-action="approve"
        @click="onApprove"
      >
        <template #icon-left>
          <CheckIcon class="h-4 w-4" aria-hidden="true" />
        </template>
        Aprobar presupuesto
      </UiButton>
    </template>
  </UiModal>
</template>

<script setup>
import { computed, ref } from 'vue'
import { CheckIcon } from '@heroicons/vue/24/outline'
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

const emit = defineEmits(['close', 'approved'])

const { approveQuotation } = useQuotations()

const notes = ref('')
const saving = ref(false)
const error = ref('')

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

const onApprove = async () => {
  if (saving.value) return

  saving.value = true
  error.value = ''

  try {
    const trimmed = notes.value.trim()
    const payload = {}
    if (trimmed) payload.notes = trimmed

    const updated = await approveQuotation(props.quotation.id, payload)
    emit('approved', updated)
    emit('close')
  } catch (err) {
    error.value = err.response?.data?.message || 'No pudimos aprobar el presupuesto.'
  } finally {
    saving.value = false
  }
}
</script>
