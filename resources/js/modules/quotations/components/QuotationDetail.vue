<template>
  <!-- Static model-value: the parent owns visibility through its v-if. -->
  <UiModal model-value size="xl" :title="modalTitle" @close="emit('close')">
    <div class="space-y-5 bg-canvas" data-quotation-detail>
      <div class="flex flex-wrap items-center justify-between gap-3" data-detail-section="status">
        <UiStatusBadge :variant="statusMeta.variant" :label="statusMeta.label" show-dot />
        <p class="text-sm text-theme-secondary">
          Emitido el
          <span class="font-medium text-label tabular-nums">
            {{ formatQuotationDate(quotation.quotation_date) }}
          </span>
        </p>
      </div>

      <fieldset
        class="space-y-3 rounded-[var(--radius-panel)] border border-hairline p-4"
        data-detail-section="patient"
      >
        <legend class="text-xs font-semibold uppercase tracking-wide text-theme-secondary">
          Paciente
        </legend>
        <dl class="space-y-2 text-sm">
          <div class="flex items-baseline justify-between gap-3">
            <dt class="text-theme-secondary">Nombre</dt>
            <dd class="min-w-0 text-right font-medium text-label">{{ patientName }}</dd>
          </div>
          <div v-if="patientDocument" class="flex items-baseline justify-between gap-3">
            <dt class="text-theme-secondary">Documento</dt>
            <dd class="min-w-0 text-right font-medium text-theme-primary tabular-nums">
              {{ patientDocument }}
            </dd>
          </div>
          <div v-if="patientPhone" class="flex items-baseline justify-between gap-3">
            <dt class="text-theme-secondary">Teléfono</dt>
            <dd class="min-w-0 text-right font-medium text-theme-primary tabular-nums">
              {{ patientPhone }}
            </dd>
          </div>
        </dl>
      </fieldset>

      <fieldset
        class="space-y-3 rounded-[var(--radius-panel)] border border-hairline p-4"
        data-detail-section="validity"
      >
        <legend class="text-xs font-semibold uppercase tracking-wide text-theme-secondary">
          Vigencia
        </legend>
        <dl class="space-y-2 text-sm">
          <div class="flex items-baseline justify-between gap-3">
            <dt class="text-theme-secondary">Válido hasta</dt>
            <dd class="flex items-center justify-end gap-2">
              <span class="font-medium text-label tabular-nums">
                {{ formatQuotationDate(quotation.valid_until) }}
              </span>
              <UiStatusBadge
                v-if="validityBadge"
                :variant="validityBadge.variant"
                :label="validityBadge.label"
                size="sm"
              />
            </dd>
          </div>
          <div v-if="originPlan" class="flex items-baseline justify-between gap-3">
            <dt class="text-theme-secondary">Tratamiento de origen</dt>
            <dd class="min-w-0 text-right font-medium text-theme-primary">{{ originPlan }}</dd>
          </div>
        </dl>
      </fieldset>

      <fieldset class="space-y-3" data-detail-section="items">
        <legend class="text-xs font-semibold uppercase tracking-wide text-theme-secondary">
          Procedimientos
        </legend>
        <UiDataTable
          v-if="items.length"
          :data="items"
          :columns="itemColumns"
          :show-header="false"
          :searchable="false"
          :pagination="false"
        >
          <template #cell-procedure="{ row }">
            <div class="min-w-0">
              <p class="text-sm font-medium text-theme-primary">{{ resolveItemTitle(row) }}</p>
              <p v-if="resolveItemDescription(row)" class="mt-0.5 text-xs text-theme-secondary">
                {{ resolveItemDescription(row) }}
              </p>
              <p
                v-if="row.specialty"
                class="mt-0.5 text-xs font-medium uppercase tracking-wide text-theme-secondary"
              >
                {{ row.specialty }}
              </p>
            </div>
          </template>
          <template #cell-quantity="{ row }">
            <span class="text-sm text-theme-primary tabular-nums">{{ row.quantity }}</span>
          </template>
          <template #cell-unit_price="{ row }">
            <span class="block text-right text-sm text-theme-primary tabular-nums">
              {{ formatCurrency(row.unit_price) }}
            </span>
          </template>
          <template #cell-total_price="{ row }">
            <span class="block text-right text-sm font-medium text-theme-primary tabular-nums">
              {{ formatCurrency(row.total_price) }}
            </span>
          </template>
        </UiDataTable>
        <UiEmptyState
          v-else
          title="Sin procedimientos"
          description="Este presupuesto no tiene procedimientos registrados."
        />
      </fieldset>

      <fieldset
        class="rounded-[var(--radius-panel)] border p-4"
        :style="{ boxShadow: 'var(--elevation-2)', borderColor: 'var(--color-hairline)' }"
        data-detail-section="costs"
      >
        <legend class="text-xs font-semibold uppercase tracking-wide text-theme-secondary">
          Costos
        </legend>
        <dl class="space-y-2 text-sm">
          <div class="flex items-baseline justify-between gap-3">
            <dt class="text-theme-secondary">Subtotal</dt>
            <dd class="font-medium text-theme-primary tabular-nums">
              {{ formatCurrency(quotation.subtotal) }}
            </dd>
          </div>
          <div v-if="discountAmount > 0" class="flex items-baseline justify-between gap-3">
            <dt class="text-theme-secondary">
              Descuento
              <span v-if="discountPercentage > 0" class="text-theme-tertiary">
                ({{ discountPercentage }}%)
              </span>
            </dt>
            <dd class="font-medium text-theme-primary tabular-nums">
              {{ formatCurrency(-discountAmount) }}
            </dd>
          </div>
          <div v-if="taxAmount > 0" class="flex items-baseline justify-between gap-3">
            <dt class="text-theme-secondary">IGV</dt>
            <dd class="font-medium text-theme-primary tabular-nums">
              {{ formatCurrency(taxAmount) }}
            </dd>
          </div>
          <div class="mt-2 flex items-baseline justify-between gap-3 border-t border-hairline pt-3">
            <dt class="font-semibold text-label">Total</dt>
            <dd class="text-lg font-semibold text-label tabular-nums">
              {{ formatCurrency(quotation.total_amount) }}
            </dd>
          </div>
        </dl>
      </fieldset>

      <fieldset
        v-if="hasTerms"
        class="space-y-3 rounded-[var(--radius-panel)] border border-hairline p-4"
        data-detail-section="conditions"
      >
        <legend class="text-xs font-semibold uppercase tracking-wide text-theme-secondary">
          Condiciones
        </legend>
        <p class="whitespace-pre-line text-sm text-theme-primary">
          {{ quotation.terms_conditions }}
        </p>
      </fieldset>

      <fieldset
        v-if="hasNotes"
        class="space-y-3 rounded-[var(--radius-panel)] border border-hairline p-4"
        data-detail-section="notes"
      >
        <legend class="text-xs font-semibold uppercase tracking-wide text-theme-secondary">
          Notas
        </legend>
        <p class="whitespace-pre-line text-sm text-theme-primary">{{ quotation.notes }}</p>
      </fieldset>

      <fieldset v-if="approvals.length" class="space-y-3" data-detail-section="approvals">
        <legend class="text-xs font-semibold uppercase tracking-wide text-theme-secondary">
          Aprobación
        </legend>
        <ul class="space-y-2">
          <li
            v-for="(approval, index) in approvals"
            :key="approval.id ?? index"
            class="rounded-[var(--radius-panel)] border border-hairline p-4"
          >
            <p class="text-sm font-medium text-label tabular-nums">
              {{ formatQuotationDate(approval.approval_date) }}
            </p>
            <p
              v-if="approval.patient_notes"
              class="mt-1 whitespace-pre-line text-sm text-theme-secondary"
            >
              {{ approval.patient_notes }}
            </p>
          </li>
        </ul>
      </fieldset>
    </div>

    <template #footer>
      <UiButton type="button" variant="secondary" data-detail-action="close" @click="emit('close')">
        Cerrar
      </UiButton>
      <UiButton
        v-if="canDownload"
        type="button"
        variant="secondary"
        data-detail-action="pdf"
        @click="emit('download', props.quotation)"
      >
        <template #icon-left>
          <ArrowDownTrayIcon class="h-4 w-4" aria-hidden="true" />
        </template>
        Descargar PDF
      </UiButton>
      <UiButton
        v-if="canEdit"
        type="button"
        variant="primary"
        data-detail-action="edit"
        @click="emit('edit', props.quotation)"
      >
        <template #icon-left>
          <PencilSquareIcon class="h-4 w-4" aria-hidden="true" />
        </template>
        Editar
      </UiButton>
      <UiButton
        v-if="canApprove"
        type="button"
        variant="success"
        data-detail-action="approve"
        @click="emit('approve', props.quotation)"
      >
        <template #icon-left>
          <CheckIcon class="h-4 w-4" aria-hidden="true" />
        </template>
        Aprobar
      </UiButton>
      <UiButton
        v-if="canReject"
        type="button"
        variant="danger"
        data-detail-action="reject"
        @click="emit('reject', props.quotation)"
      >
        <template #icon-left>
          <XMarkIcon class="h-4 w-4" aria-hidden="true" />
        </template>
        Rechazar
      </UiButton>
    </template>
  </UiModal>
</template>

<script setup>
import { computed } from 'vue'
import {
  ArrowDownTrayIcon,
  CheckIcon,
  PencilSquareIcon,
  XMarkIcon
} from '@heroicons/vue/24/outline'
import { useAuth } from '@/composables/useAuth'
import { formatCurrency } from '../../../composables/useFormatters'
import UiButton from '@/components/ui/Button.vue'
import UiDataTable from '@/components/ui/DataTable.vue'
import UiEmptyState from '@/components/ui/EmptyState.vue'
import UiModal from '@/components/ui/Modal.vue'
import UiStatusBadge from '@/components/ui/StatusBadge.vue'
import {
  canApproveQuotation,
  canDownloadQuotation,
  canEditQuotation,
  canRejectQuotation,
  expiryBadge,
  formatQuotationDate,
  getStatusMeta,
  resolveItemDescription,
  resolveItemTitle
} from '../quotationMeta.js'

const props = defineProps({
  quotation: {
    type: Object,
    required: true
  }
})

const emit = defineEmits(['close', 'edit', 'approve', 'reject', 'download'])

const { user } = useAuth()

const role = computed(() => user.value?.role)

const modalTitle = computed(() => `Presupuesto ${props.quotation.quotation_number}`)
const statusMeta = computed(() => getStatusMeta(props.quotation.status))
const validityBadge = computed(() => expiryBadge(props.quotation))

const patientName = computed(() => {
  const { patient } = props.quotation
  if (!patient) return '—'

  const name = [patient.first_name, patient.last_name].filter(Boolean).join(' ').trim()

  return name || '—'
})
const patientDocument = computed(() => props.quotation.patient?.document_number || '')
const patientPhone = computed(() => props.quotation.patient?.phone || '')

const originPlan = computed(() => {
  const { treatment_plan: plan } = props.quotation
  if (!plan) return ''

  return plan.title || plan.plan_number || ''
})

const items = computed(() => props.quotation.items ?? [])
const approvals = computed(() => props.quotation.approvals ?? [])

const itemColumns = [
  { key: 'procedure', label: 'Procedimiento' },
  { key: 'quantity', label: 'Cantidad' },
  { key: 'unit_price', label: 'P. unitario' },
  { key: 'total_price', label: 'Total' }
]

const positiveAmount = value => {
  const amount = Number(value)

  return Number.isFinite(amount) && amount > 0 ? amount : 0
}

const discountAmount = computed(() => positiveAmount(props.quotation.discount_amount))
const discountPercentage = computed(() => positiveAmount(props.quotation.discount_percentage))
const taxAmount = computed(() => positiveAmount(props.quotation.tax_amount))

const hasTerms = computed(() => Boolean(String(props.quotation.terms_conditions || '').trim()))
const hasNotes = computed(() => Boolean(String(props.quotation.notes || '').trim()))

const canDownload = computed(() => canDownloadQuotation(role.value))
const canEdit = computed(() => canEditQuotation(props.quotation, role.value))
const canApprove = computed(() => canApproveQuotation(props.quotation, role.value))
const canReject = computed(() => canRejectQuotation(props.quotation, role.value))
</script>
