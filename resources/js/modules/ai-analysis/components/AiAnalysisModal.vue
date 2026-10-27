<template>
  <UiModal model-value size="xl" :labelledby="titleId" @close="emit('close')">
    <template #header>
      <div class="flex w-full items-start justify-between gap-4">
        <div class="min-w-0">
          <div :id="titleId">
            <p
              class="flex items-center gap-2 text-xs font-medium uppercase tracking-wide text-theme-secondary"
            >
              <CpuChipIcon class="h-4 w-4" aria-hidden="true" />
              Análisis de IA
            </p>
            <h2 data-ai-detail-patient class="mt-2 truncate text-2xl font-semibold text-label">
              {{ patientName }}
            </h2>
          </div>
          <div class="mt-2 flex flex-wrap items-center gap-2">
            <span
              data-ai-detail-category
              class="inline-flex items-center rounded-full bg-theme-surface px-2.5 py-0.5 text-xs font-medium text-theme-secondary"
            >
              {{ categoryLabel }}
            </span>
            <UiStatusPill
              :status="analysis.status"
              :variant="statusVariant"
              size="sm"
              data-ai-detail-status
            >
              {{ statusLabel }}
            </UiStatusPill>
          </div>
        </div>
        <UiButton
          variant="ghost"
          size="sm"
          aria-label="Cerrar"
          data-ai-detail-close
          @click="emit('close')"
        >
          <XMarkIcon class="h-5 w-5" aria-hidden="true" />
        </UiButton>
      </div>
    </template>

    <div data-ai-detail class="space-y-6">
      <p
        data-ai-assistive-notice
        class="rounded-[var(--radius-control)] bg-theme-surface p-4 text-sm leading-6 text-theme-primary"
        style="border: 1px solid var(--color-hairline)"
      >
        {{ assistiveNotice }}
      </p>

      <figure
        class="overflow-hidden rounded-[var(--radius-panel)] border border-[var(--color-hairline)]"
      >
        <img
          v-if="hasImage"
          data-ai-image
          :src="imageUrl"
          :alt="imageAlt"
          class="max-h-[55vh] w-full bg-theme-surface object-contain"
        />
        <div
          v-else
          data-ai-image-fallback
          class="flex min-h-[14rem] flex-col items-center justify-center gap-3 bg-theme-surface"
        >
          <PhotoIcon class="h-10 w-10 text-theme-secondary" aria-hidden="true" />
          <p class="text-sm text-theme-secondary">Imagen no disponible</p>
        </div>
      </figure>

      <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
        <section
          data-ai-section="findings"
          class="rounded-[var(--radius-panel)] border border-[var(--color-hairline)] bg-theme-surface-elevated p-5 shadow-[var(--elevation-2)]"
        >
          <h3 class="text-lg font-semibold text-label">Hallazgos</h3>
          <ul
            v-if="findings.length"
            class="mt-3 list-disc space-y-2 pl-5 text-sm leading-6 text-theme-primary"
          >
            <li v-for="(finding, index) in findings" :key="`finding-${index}`">
              {{ finding }}
            </li>
          </ul>
          <p v-else class="mt-3 text-sm text-theme-secondary">Sin hallazgos reportados.</p>
        </section>

        <section
          data-ai-section="recommendations"
          class="rounded-[var(--radius-panel)] border border-[var(--color-hairline)] bg-theme-surface-elevated p-5 shadow-[var(--elevation-2)]"
        >
          <h3 class="text-lg font-semibold text-label">Recomendaciones</h3>
          <ul
            v-if="recommendations.length"
            class="mt-3 list-disc space-y-2 pl-5 text-sm leading-6 text-theme-primary"
          >
            <li v-for="(recommendation, index) in recommendations" :key="`recommendation-${index}`">
              {{ recommendation }}
            </li>
          </ul>
          <p v-else class="mt-3 text-sm text-theme-secondary">Sin recomendaciones registradas.</p>
        </section>
      </div>

      <section
        data-ai-section="metadata"
        class="rounded-[var(--radius-panel)] border border-[var(--color-hairline)] bg-theme-surface-elevated p-5 shadow-[var(--elevation-2)]"
      >
        <h3 class="text-lg font-semibold text-label">Metadatos</h3>
        <dl class="mt-3 grid grid-cols-1 gap-4 sm:grid-cols-2">
          <div>
            <dt class="text-xs font-medium uppercase tracking-wide text-theme-secondary">
              Confianza
            </dt>
            <dd data-ai-confidence class="mt-1 text-sm font-medium text-theme-primary tabular-nums">
              <template v-if="hasConfidence">
                {{ confidenceScore }}%
                <span class="font-normal text-theme-secondary">· {{ confidenceLabel }}</span>
              </template>
              <template v-else>—</template>
            </dd>
          </div>
          <div>
            <dt class="text-xs font-medium uppercase tracking-wide text-theme-secondary">Modelo</dt>
            <dd data-ai-meta="model" class="mt-1 text-sm text-theme-primary">{{ modelUsed }}</dd>
          </div>
          <div>
            <dt class="text-xs font-medium uppercase tracking-wide text-theme-secondary">Tokens</dt>
            <dd data-ai-meta="tokens" class="mt-1 text-sm text-theme-primary tabular-nums">
              {{ tokensUsed }}
            </dd>
          </div>
          <div>
            <dt class="text-xs font-medium uppercase tracking-wide text-theme-secondary">Fecha</dt>
            <dd data-ai-meta="date" class="mt-1 text-sm text-theme-primary tabular-nums">
              {{ createdAt }}
            </dd>
          </div>
          <div>
            <dt class="text-xs font-medium uppercase tracking-wide text-theme-secondary">
              Solicitado por
            </dt>
            <dd data-ai-meta="requested-by" class="mt-1 text-sm text-theme-primary">
              {{ requestedByName }}
            </dd>
          </div>
          <div>
            <dt class="text-xs font-medium uppercase tracking-wide text-theme-secondary">
              Aviso al paciente
            </dt>
            <dd data-ai-meta="notice-date" class="mt-1 text-sm text-theme-primary tabular-nums">
              {{ noticeAcknowledgedAt }}
            </dd>
          </div>
          <div>
            <dt class="text-xs font-medium uppercase tracking-wide text-theme-secondary">
              Declarado por
            </dt>
            <dd data-ai-meta="notice-by" class="mt-1 text-sm text-theme-primary">
              {{ noticeAcknowledgedByName }}
            </dd>
          </div>
          <div>
            <dt class="text-xs font-medium uppercase tracking-wide text-theme-secondary">
              Costo estimado
            </dt>
            <dd data-ai-meta="cost" class="mt-1 text-sm text-theme-primary tabular-nums">
              {{ estimatedCost }}
            </dd>
          </div>
        </dl>
      </section>

      <section
        data-ai-review
        class="rounded-[var(--radius-panel)] border border-[var(--color-hairline)] bg-theme-surface-elevated p-5 shadow-[var(--elevation-2)]"
      >
        <h3 class="text-lg font-semibold text-label">Revisión</h3>

        <div
          v-if="analysis.reviewed"
          data-ai-review-outcome
          class="mt-3 rounded-[var(--radius-control)] bg-theme-surface p-4"
        >
          <div class="flex flex-wrap items-center gap-2">
            <span class="text-sm font-medium text-theme-primary">Decisión</span>
            <span
              data-ai-review-decision-label
              class="inline-flex items-center rounded-full bg-theme-surface-elevated px-2.5 py-0.5 text-xs font-medium text-theme-primary"
            >
              {{ reviewDecisionLabel }}
            </span>
          </div>
          <p data-ai-review-notes-value class="mt-3 text-sm text-theme-primary">
            {{ reviewNotesText }}
          </p>
          <div
            class="mt-3 flex flex-wrap items-center justify-between gap-2 text-xs text-theme-secondary"
          >
            <span data-ai-review-reviewer>{{ reviewerName }}</span>
            <span data-ai-review-date class="tabular-nums">{{ reviewedAt }}</span>
          </div>
        </div>

        <form
          v-else-if="canReview && !isVoided"
          data-ai-review-form
          class="mt-3 space-y-4"
          @submit.prevent="submitReview"
        >
          <div>
            <label class="mb-1 block text-sm font-medium text-theme-primary">Decisión</label>
            <UiSelect
              v-model="reviewForm.decision"
              :options="decisionOptions"
              label="Decisión"
              placeholder="Selecciona una decisión"
              data-ai-review-decision
            />
          </div>
          <div>
            <div class="mb-1 flex items-center justify-between">
              <label class="text-sm font-medium text-theme-primary">Notas de revisión</label>
              <span data-ai-review-notes-count class="text-xs text-theme-secondary tabular-nums">
                {{ reviewForm.notes.length }} / 1000
              </span>
            </div>
            <UiTextarea
              v-model="reviewForm.notes"
              :rows="3"
              maxlength="1000"
              placeholder="Observaciones sobre el análisis"
              data-ai-review-notes
            />
          </div>
          <div class="flex justify-end">
            <UiButton
              type="submit"
              variant="primary"
              size="md"
              :disabled="!reviewForm.decision"
              data-ai-review-submit
            >
              Guardar revisión
            </UiButton>
          </div>
        </form>

        <p v-else-if="isVoided" data-ai-review-voided class="mt-3 text-sm text-theme-secondary">
          Este análisis está anulado; no admite revisión.
        </p>

        <p v-else data-ai-review-denied class="mt-3 text-sm text-theme-secondary">
          No tienes permiso para revisar este análisis.
        </p>
      </section>

      <section
        v-if="isVoided || canVoid"
        data-ai-void-section
        class="rounded-[var(--radius-panel)] border border-[var(--color-hairline)] bg-theme-surface-elevated p-5 shadow-[var(--elevation-2)]"
      >
        <h3 class="text-lg font-semibold text-label">Anulación</h3>

        <div
          v-if="isVoided"
          data-ai-void-trace
          class="mt-3 rounded-[var(--radius-control)] bg-theme-surface p-4"
        >
          <dl class="grid grid-cols-1 gap-4 sm:grid-cols-3">
            <div>
              <dt class="text-xs font-medium uppercase tracking-wide text-theme-secondary">
                Anulado por
              </dt>
              <dd data-ai-voided-by class="mt-1 text-sm text-theme-primary">
                {{ voidedByName }}
              </dd>
            </div>
            <div>
              <dt class="text-xs font-medium uppercase tracking-wide text-theme-secondary">
                Fecha de anulación
              </dt>
              <dd data-ai-voided-at class="mt-1 text-sm text-theme-primary tabular-nums">
                {{ voidedAt }}
              </dd>
            </div>
            <div>
              <dt class="text-xs font-medium uppercase tracking-wide text-theme-secondary">
                Motivo
              </dt>
              <dd data-ai-void-reason-value class="mt-1 text-sm text-theme-primary">
                {{ voidReasonText }}
              </dd>
            </div>
          </dl>
        </div>

        <div v-else class="mt-3 space-y-3">
          <div class="flex items-center justify-between">
            <label class="text-sm font-medium text-theme-primary">Motivo de la anulación</label>
            <span data-ai-void-reason-count class="text-xs text-theme-secondary tabular-nums">
              {{ voidForm.reason.length }} / 255
            </span>
          </div>
          <UiTextarea
            v-model="voidForm.reason"
            :rows="3"
            maxlength="255"
            placeholder="Describe por qué se anula este análisis"
            data-ai-void-reason
          />
          <div class="flex justify-end">
            <UiButton
              variant="danger"
              size="sm"
              :disabled="!voidForm.reason.trim()"
              data-ai-void
              @click="requestVoid"
            >
              <template #icon-left>
                <NoSymbolIcon class="h-4 w-4" aria-hidden="true" />
              </template>
              Anular análisis
            </UiButton>
          </div>
        </div>
      </section>
    </div>
  </UiModal>
</template>

<script setup>
import { computed, reactive } from 'vue'
import { CpuChipIcon, NoSymbolIcon, PhotoIcon, XMarkIcon } from '@heroicons/vue/24/outline'
import { useConfirm } from '../../../composables/useConfirm'
import { usePermissions } from '../../../composables/usePermissions'
import UiButton from '../../../components/ui/Button.vue'
import UiModal from '../../../components/ui/Modal.vue'
import UiSelect from '../../../components/ui/Select.vue'
import UiStatusPill from '../../../components/ui/StatusPill.vue'
import UiTextarea from '../../../components/ui/UiTextarea.vue'

const props = defineProps({
  analysis: {
    type: Object,
    required: true
  }
})

const emit = defineEmits(['close', 'review', 'void'])

// The consumer supplies its own `#header`, so the modal cannot name the dialog
// by itself. The heading block is what the reader already sees.
const titleId = computed(() => `ai-analysis-title-${props.analysis.id}`)

const { can } = usePermissions()
const { confirm } = useConfirm()

const CATEGORY_LABELS = {
  radiografia: 'Radiografía',
  foto_clinica: 'Foto clínica'
}

const STATUS_LABELS = {
  pending: 'Pendiente',
  processing: 'Procesando',
  completed: 'Completado',
  failed: 'Fallido'
}

const STATUS_VARIANTS = {
  pending: 'warning',
  processing: 'info',
  completed: 'success',
  failed: 'error'
}

const REVIEW_DECISION_LABELS = {
  accepted: 'Aceptado',
  rejected: 'Rechazado',
  partial: 'Parcial'
}

const decisionOptions = [
  { value: '', label: 'Selecciona una decisión' },
  { value: 'accepted', label: 'Aceptado' },
  { value: 'rejected', label: 'Rechazado' },
  { value: 'partial', label: 'Parcial' }
]

const reviewForm = reactive({
  decision: '',
  notes: ''
})

const voidForm = reactive({
  reason: ''
})

const canReview = computed(() => Boolean(can['ai-analysis.review']?.value))
const canVoid = computed(() => Boolean(can['ai-analysis.void']?.value))
const isVoided = computed(() => props.analysis.voided === true)

const attachment = computed(() => props.analysis.clinical_attachment ?? null)
const patientName = computed(() => {
  const { patient } = props.analysis ?? {}

  if (!patient) return 'Paciente no disponible'
  if (patient.full_name) return patient.full_name

  const composed = [patient.first_name, patient.last_name].filter(Boolean).join(' ')

  return composed || 'Paciente no disponible'
})
const categoryLabel = computed(() => CATEGORY_LABELS[attachment.value?.category] || '—')
const hasImage = computed(() => Boolean(attachment.value?.file_path))
const imageUrl = computed(() => `/storage/${attachment.value.file_path}`)
const imageAlt = computed(() => attachment.value?.original_name || 'Imagen del análisis')

const statusLabel = computed(
  () => STATUS_LABELS[props.analysis.status] || props.analysis.status || '—'
)
const statusVariant = computed(() => STATUS_VARIANTS[props.analysis.status] || 'neutral')

const toText = item => {
  if (item === null || item === undefined) return ''
  if (typeof item === 'string') return item.trim()
  if (typeof item === 'object') {
    return String(item.description || item.finding || item.text || '').trim()
  }
  return String(item).trim()
}

const toTextList = value => (Array.isArray(value) ? value.map(toText).filter(Boolean) : [])

const findings = computed(() => toTextList(props.analysis.findings))
const recommendations = computed(() => toTextList(props.analysis.recommendations))

const hasConfidence = computed(() => {
  const score = props.analysis.confidence_score
  return score !== null && score !== undefined && score !== ''
})
const confidenceScore = computed(() => props.analysis.confidence_score)
const confidenceLabel = computed(() => {
  const score = Number(props.analysis.confidence_score)
  if (Number.isNaN(score)) return ''
  if (score >= 90) return 'Alta'
  if (score >= 70) return 'Media'
  return 'Baja'
})

const modelUsed = computed(() => props.analysis.model_used || '—')
const tokensUsed = computed(() => {
  const tokens = props.analysis.tokens_used
  return tokens === null || tokens === undefined || tokens === '' ? '—' : tokens
})
const requestedByName = computed(() => props.analysis.requested_by?.name || '—')

// #296 — the assistive notice travels in the payload; the literal is the
// fallback for a payload built before the field existed.
const ASSISTIVE_NOTICE_FALLBACK =
  'Apoyo a la decisión asistido por IA. No sustituye el diagnóstico profesional.'
const assistiveNotice = computed(() => props.analysis.assistive_notice || ASSISTIVE_NOTICE_FALLBACK)

const dateFormatter = new Intl.DateTimeFormat('es-ES', {
  day: '2-digit',
  month: 'short',
  year: 'numeric',
  hour: '2-digit',
  minute: '2-digit'
})

const formatDate = value => {
  if (!value) return '—'
  const date = new Date(value)
  return Number.isNaN(date.getTime()) ? '—' : dateFormatter.format(date)
}

const createdAt = computed(() => formatDate(props.analysis.created_at))
const reviewedAt = computed(() => formatDate(props.analysis.reviewed_at))
const voidedAt = computed(() => formatDate(props.analysis.voided_at))
const noticeAcknowledgedAt = computed(() => formatDate(props.analysis.notice_acknowledged_at))
const estimatedCost = computed(() => formatUsd(props.analysis.estimated_cost))
const reviewDecisionLabel = computed(
  () => REVIEW_DECISION_LABELS[props.analysis.review_decision] || '—'
)
const reviewNotesText = computed(() => props.analysis.review_notes || 'Sin notas')
const reviewerName = computed(() => props.analysis.reviewed_by?.name || '—')
const voidedByName = computed(() => props.analysis.voided_by?.name || '—')
const voidReasonText = computed(() => props.analysis.void_reason || '—')

const noticeAcknowledgedByName = computed(() => props.analysis.notice_acknowledged_by?.name || '—')

const usdFormatter = new Intl.NumberFormat('en-US', {
  style: 'currency',
  currency: 'USD',
  minimumFractionDigits: 4,
  maximumFractionDigits: 4
})

const formatUsd = value => {
  if (value === null || value === undefined || value === '') return '—'
  const numeric = Number(value)
  return Number.isNaN(numeric) ? '—' : usdFormatter.format(numeric)
}

const submitReview = () => {
  if (!reviewForm.decision) return
  emit('review', props.analysis.id, reviewForm.decision, reviewForm.notes)
}

const requestVoid = async () => {
  const reason = voidForm.reason.trim()
  if (!reason) return

  const confirmed = await confirm({
    title: 'Anular análisis',
    message:
      '¿Anular este análisis? Se conserva la traza de revisión y el motivo queda registrado.',
    confirmText: 'Anular',
    variant: 'danger'
  })
  if (confirmed) emit('void', props.analysis.id, reason)
}
</script>
