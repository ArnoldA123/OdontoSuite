<template>
  <div
    data-plan-detail
    role="dialog"
    aria-modal="true"
    :aria-labelledby="headingId"
    class="fixed inset-0 z-50 flex items-end justify-center overflow-y-auto bg-black/50 p-0 backdrop-blur-sm sm:items-center sm:p-4"
    @click="closeModal"
  >
    <div
      class="flex max-h-[92vh] w-full max-w-4xl flex-col overflow-hidden rounded-[var(--radius-panel)]"
      style="
        background: var(--color-surface-elevated);
        border: 1px solid var(--color-hairline);
        box-shadow: var(--elevation-2);
      "
      @click.stop
    >
      <!-- Header band: identity first, then the status and the plan flags. -->
      <header
        class="flex flex-wrap items-start justify-between gap-4 p-6"
        style="border-bottom: 1px solid var(--color-hairline)"
      >
        <div class="min-w-0">
          <p class="text-xs font-medium uppercase tracking-wide text-theme-secondary">
            Plan de tratamiento
          </p>
          <h2 :id="headingId" class="mt-2 text-2xl font-semibold text-label" data-detail-title>
            {{ plan.title }}
          </h2>
          <div class="mt-3 flex flex-wrap items-center gap-2">
            <span class="font-mono text-sm text-theme-secondary tabular-nums" data-detail-number>
              {{ plan.plan_number || 'Sin número' }}
            </span>
            <PlanStatusBadge :status="plan.status" />
            <span
              v-if="plan.is_overdue"
              data-flag="overdue"
              class="inline-flex items-center rounded-full bg-systemRed-50 px-2.5 py-0.5 text-xs font-medium text-systemRed-600"
            >
              Vencido
            </span>
            <span
              v-if="plan.is_urgent"
              data-flag="urgent"
              class="inline-flex items-center rounded-full bg-systemRed-50 px-2.5 py-0.5 text-xs font-medium text-systemRed-600"
            >
              Urgente
            </span>
            <span
              v-if="plan.requires_anesthesia"
              data-flag="anesthesia"
              class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium text-theme-secondary"
              style="border: 1px solid var(--color-hairline)"
            >
              Requiere anestesia
            </span>
          </div>
        </div>

        <UiButton
          variant="icon"
          size="sm"
          aria-label="Cerrar detalle del plan"
          data-action="close-icon"
          @click="closeModal"
        >
          <template #icon-left>
            <XMarkIcon class="h-5 w-5" aria-hidden="true" />
          </template>
        </UiButton>
      </header>

      <!-- Body: one block per information group, same order as before. -->
      <div class="flex-1 space-y-4 overflow-y-auto p-6" data-detail-body>
        <!-- Paciente -->
        <section
          class="rounded-[var(--radius-control)] bg-canvas p-4"
          style="background: var(--color-canvas); border: 1px solid var(--color-hairline)"
          data-detail-section="patient"
        >
          <h3 class="text-xs font-semibold uppercase tracking-wide text-theme-secondary">
            Paciente
          </h3>
          <dl class="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-3">
            <div class="min-w-0">
              <dt class="text-xs font-medium text-theme-secondary">Nombre</dt>
              <dd class="mt-1 truncate text-sm text-theme-primary" data-detail-field="patient_name">
                {{ patientName }}
              </dd>
            </div>
            <div class="min-w-0">
              <dt class="text-xs font-medium text-theme-secondary">Email</dt>
              <dd
                class="mt-1 truncate text-sm text-theme-primary"
                data-detail-field="patient_email"
              >
                {{ plan.patient?.email || 'No especificado' }}
              </dd>
            </div>
            <div class="min-w-0">
              <dt class="text-xs font-medium text-theme-secondary">Teléfono</dt>
              <dd
                class="mt-1 text-sm text-theme-primary tabular-nums"
                data-detail-field="patient_phone"
              >
                {{ plan.patient?.phone || 'No especificado' }}
              </dd>
            </div>
          </dl>
        </section>

        <!-- Detalles del plan -->
        <section
          class="rounded-[var(--radius-control)] p-4"
          style="border: 1px solid var(--color-hairline)"
          data-detail-section="plan"
        >
          <h3 class="text-xs font-semibold uppercase tracking-wide text-theme-secondary">
            Detalles del Plan
          </h3>
          <dl class="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-2">
            <div class="min-w-0 sm:col-span-2">
              <dt class="text-xs font-medium text-theme-secondary">Descripción</dt>
              <dd
                class="mt-1 whitespace-pre-wrap text-sm text-theme-primary"
                data-detail-field="description"
              >
                {{ plan.description || 'Sin descripción' }}
              </dd>
            </div>
            <div class="min-w-0">
              <dt class="text-xs font-medium text-theme-secondary">Duración estimada</dt>
              <dd class="mt-1 text-sm text-theme-primary tabular-nums" data-detail-field="weeks">
                {{ durationLabel }}
              </dd>
            </div>
            <div class="min-w-0">
              <dt class="text-xs font-medium text-theme-secondary">Fases</dt>
              <dd class="mt-1 text-sm text-theme-primary tabular-nums" data-detail-field="phases">
                {{ phaseLabel }}
              </dd>
            </div>
            <div class="min-w-0">
              <dt class="text-xs font-medium text-theme-secondary">Fecha de inicio</dt>
              <dd
                class="mt-1 text-sm text-theme-primary tabular-nums"
                data-detail-field="start_date"
              >
                {{ formatDate(plan.start_date) }}
              </dd>
            </div>
            <div class="min-w-0">
              <dt class="text-xs font-medium text-theme-secondary">Fecha de fin</dt>
              <dd class="mt-1 text-sm text-theme-primary tabular-nums" data-detail-field="end_date">
                {{ formatDate(plan.end_date) }}
              </dd>
            </div>
            <div class="min-w-0">
              <dt class="text-xs font-medium text-theme-secondary">Requiere anestesia</dt>
              <dd
                class="mt-1 text-sm font-medium"
                :class="plan.requires_anesthesia ? 'text-systemRed-600' : 'text-theme-secondary'"
                data-detail-field="anesthesia"
              >
                {{ plan.requires_anesthesia ? 'Sí' : 'No' }}
              </dd>
            </div>
            <div class="min-w-0">
              <dt class="text-xs font-medium text-theme-secondary">Tratamiento urgente</dt>
              <dd
                class="mt-1 text-sm font-medium"
                :class="plan.is_urgent ? 'text-systemRed-600' : 'text-theme-secondary'"
                data-detail-field="urgent"
              >
                {{ plan.is_urgent ? 'Sí' : 'No' }}
              </dd>
            </div>
          </dl>
        </section>

        <!-- Procedimientos: horizontal scroll keeps the columns readable on mobile. -->
        <section
          class="rounded-[var(--radius-control)] p-4"
          style="border: 1px solid var(--color-hairline)"
          data-detail-section="procedures"
        >
          <h3 class="text-xs font-semibold uppercase tracking-wide text-theme-secondary">
            Procedimientos
          </h3>
          <div v-if="items.length" class="mt-3 overflow-x-auto">
            <table class="w-full min-w-[640px] text-sm">
              <caption class="sr-only">Procedimientos del plan</caption>
              <thead>
                <tr
                  class="text-xs font-medium uppercase tracking-wide text-theme-secondary"
                  style="border-bottom: 1px solid var(--color-hairline)"
                >
                  <th scope="col" class="px-3 py-2 text-left">Procedimiento</th>
                  <th scope="col" class="px-3 py-2 text-left">Fase</th>
                  <th scope="col" class="px-3 py-2 text-right">Cantidad</th>
                  <th scope="col" class="px-3 py-2 text-right">Costo unit.</th>
                  <th scope="col" class="px-3 py-2 text-right">Total</th>
                </tr>
              </thead>
              <tbody>
                <tr
                  v-for="item in items"
                  :key="item.id"
                  data-detail-item
                  style="border-bottom: 1px solid var(--color-hairline)"
                >
                  <td class="px-3 py-3">
                    <p class="font-medium text-theme-primary">
                      {{ item.procedure_name || item.description || 'Procedimiento' }}
                    </p>
                    <p
                      v-if="item.procedure_description || item.specialty || item.category"
                      class="mt-1 text-xs text-theme-secondary"
                    >
                      {{ item.procedure_description || item.specialty || item.category }}
                    </p>
                  </td>
                  <td class="px-3 py-3 text-theme-secondary tabular-nums">
                    {{ item.phase_number ? `Fase ${item.phase_number}` : '—' }}
                  </td>
                  <td class="px-3 py-3 text-right text-theme-primary tabular-nums">
                    {{ item.quantity }}
                  </td>
                  <td class="px-3 py-3 text-right text-theme-primary tabular-nums">
                    {{ formatCurrency(item.unit_cost) }}
                  </td>
                  <td class="px-3 py-3 text-right font-medium text-theme-primary tabular-nums">
                    {{ formatCurrency(itemTotal(item)) }}
                  </td>
                </tr>
              </tbody>
              <tfoot>
                <tr
                  class="bg-canvas"
                  style="
                    background: var(--color-canvas);
                    border-top: 1px solid var(--color-hairline);
                  "
                >
                  <td colspan="4" class="px-3 py-3 text-right font-semibold text-theme-primary">
                    Total
                  </td>
                  <td
                    class="px-3 py-3 text-right font-semibold text-theme-primary tabular-nums"
                    data-detail-total
                  >
                    {{ formatCurrency(plan.final_cost ?? plan.total_cost) }}
                  </td>
                </tr>
              </tfoot>
            </table>
          </div>
          <p v-else class="mt-3 text-sm text-theme-secondary" data-detail-empty="procedures">
            No hay procedimientos en este plan
          </p>
        </section>

        <!-- Notas -->
        <section
          v-if="plan.notes || plan.patient_notes"
          class="rounded-[var(--radius-control)] p-4"
          style="border: 1px solid var(--color-hairline)"
          data-detail-section="notes"
        >
          <h3 class="text-xs font-semibold uppercase tracking-wide text-theme-secondary">Notas</h3>
          <div class="mt-3 space-y-3">
            <div
              v-if="plan.notes"
              class="rounded-[var(--radius-control)] bg-canvas p-3"
              style="background: var(--color-canvas); border: 1px solid var(--color-hairline)"
              data-detail-field="notes"
            >
              <h4 class="text-xs font-medium text-theme-secondary">Notas internas</h4>
              <p class="mt-1 whitespace-pre-wrap text-sm text-theme-primary">{{ plan.notes }}</p>
            </div>
            <div
              v-if="plan.patient_notes"
              class="rounded-[var(--radius-control)] bg-canvas p-3"
              style="background: var(--color-canvas); border: 1px solid var(--color-hairline)"
              data-detail-field="patient_notes"
            >
              <h4 class="text-xs font-medium text-theme-secondary">Notas para el paciente</h4>
              <p class="mt-1 whitespace-pre-wrap text-sm text-theme-primary">
                {{ plan.patient_notes }}
              </p>
            </div>
          </div>
        </section>

        <!-- Presupuestos asociados -->
        <section
          v-if="quotations.length"
          class="rounded-[var(--radius-control)] p-4"
          style="border: 1px solid var(--color-hairline)"
          data-detail-section="quotations"
        >
          <h3 class="text-xs font-semibold uppercase tracking-wide text-theme-secondary">
            Presupuestos Asociados
          </h3>
          <ul class="mt-3 space-y-2">
            <li
              v-for="quotation in quotations"
              :key="quotation.id"
              class="flex flex-wrap items-center justify-between gap-3 rounded-[var(--radius-control)] bg-canvas p-3"
              style="background: var(--color-canvas); border: 1px solid var(--color-hairline)"
              data-detail-quotation
            >
              <div class="min-w-0">
                <p class="text-sm font-medium text-theme-primary tabular-nums">
                  {{ quotation.quotation_number || `Presupuesto #${quotation.id}` }}
                </p>
                <p class="mt-1 text-xs text-theme-secondary tabular-nums">
                  {{ formatDate(quotation.quotation_date) }}
                </p>
              </div>
              <div class="flex items-center gap-3">
                <span class="text-sm font-semibold text-theme-primary tabular-nums">
                  {{ formatCurrency(quotation.total_amount ?? quotation.total) }}
                </span>
                <QuotationStatusBadge :status="quotation.status" />
              </div>
            </li>
          </ul>
        </section>
      </div>

      <!-- Footer: audit line plus the three actions the page wires. -->
      <footer
        class="flex flex-wrap items-center justify-between gap-4 p-6"
        style="border-top: 1px solid var(--color-hairline)"
      >
        <p class="text-xs text-theme-secondary" data-detail-created-by>
          Creado el {{ formatDateTime(plan.created_at) }} por {{ createdByLabel }}
        </p>
        <div class="flex flex-wrap items-center gap-3">
          <UiButton variant="secondary" data-action="edit" @click="emit('edit', plan)">
            <template #icon-left>
              <PencilIcon class="h-4 w-4" aria-hidden="true" />
            </template>
            Editar
          </UiButton>
          <UiButton variant="secondary" data-action="duplicate" @click="emit('duplicate', plan)">
            <template #icon-left>
              <DocumentDuplicateIcon class="h-4 w-4" aria-hidden="true" />
            </template>
            Duplicar
          </UiButton>
          <UiButton variant="primary" data-action="close" @click="closeModal">Cerrar</UiButton>
        </div>
      </footer>
    </div>
  </div>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted } from 'vue'
import { DocumentDuplicateIcon, PencilIcon, XMarkIcon } from '@heroicons/vue/24/outline'
import { formatCurrency } from '@/composables/useFormatters'
import PlanStatusBadge from './PlanStatusBadge.vue'
import QuotationStatusBadge from '@/modules/quotations/components/QuotationStatusBadge.vue'

const props = defineProps({
  plan: {
    type: Object,
    required: true
  }
})

const emit = defineEmits(['close', 'edit', 'duplicate'])

const headingId = 'treatment-plan-detail-title'

const items = computed(() => (Array.isArray(props.plan.items) ? props.plan.items : []))
const quotations = computed(() =>
  Array.isArray(props.plan.quotations) ? props.plan.quotations : []
)

const patientName = computed(() => {
  const patient = props.plan.patient || {}
  const full = [patient.first_name, patient.last_name].filter(Boolean).join(' ').trim()

  return full || patient.full_name || patient.name || 'Paciente no registrado'
})

const createdByLabel = computed(() => {
  const author = props.plan.created_by || {}
  const full = [author.first_name, author.last_name].filter(Boolean).join(' ').trim()

  return full || author.name || 'Usuario no registrado'
})

const durationLabel = computed(() =>
  props.plan.estimated_duration_weeks ? `${props.plan.estimated_duration_weeks} semanas` : '—'
)

/**
 * Phases may arrive as the plan's JSON array or only as the distinct phase
 * numbers of its items; the detail counts whichever the payload carries.
 */
const phaseLabel = computed(() => {
  if (Array.isArray(props.plan.phases) && props.plan.phases.length) {
    return String(props.plan.phases.length)
  }
  if (typeof props.plan.phases === 'number') return String(props.plan.phases)

  const phases = new Set(items.value.map(item => item.phase_number).filter(Boolean))

  return phases.size ? String(phases.size) : 'No especificadas'
})

const itemTotal = item =>
  item.total_cost ?? Number(item.quantity || 0) * Number(item.unit_cost || 0)

const closeModal = () => emit('close')

const formatDate = date => {
  if (!date) return 'No especificada'
  const parsed = new Date(date)
  if (Number.isNaN(parsed.getTime())) return 'No especificada'

  return parsed.toLocaleDateString('es-PE', {
    year: 'numeric',
    month: 'long',
    day: 'numeric'
  })
}

const formatDateTime = date => {
  if (!date) return '—'
  const parsed = new Date(date)
  if (Number.isNaN(parsed.getTime())) return '—'

  return parsed.toLocaleDateString('es-PE', {
    year: 'numeric',
    month: 'long',
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit'
  })
}

// Escape closes the overlay: the dialog owns its own dismissal so it stays
// usable when mounted outside the page keyboard handler.
const onKeydown = event => {
  if (event.key === 'Escape') closeModal()
}

onMounted(() => document.addEventListener('keydown', onKeydown))
onBeforeUnmount(() => document.removeEventListener('keydown', onKeydown))
</script>
