<template>
  <AppLayout>
    <div class="space-y-8" data-continuity>
      <!--
        Canonical page header: one component, one anatomy. Kicker names the
        domain, the title the desk and the description states which
        populations the session can read.
      -->
      <PageHeader kicker="Pendientes" title="Pendientes" :subtitle="headerDescription">
        <template #actions>
          <UiButton
            variant="secondary"
            :disabled="loading || pendingLoading"
            data-continuity-refresh
            @click="refreshAll"
          >
            <template #icon-left>
              <ArrowPathIcon class="h-4 w-4" aria-hidden="true" />
            </template>
            Actualizar
          </UiButton>
        </template>
      </PageHeader>

      <!-- One card per headline number; the compliance card formats the rate
           the API publishes instead of recomputing it from the counts.
           «Pacientes con pendientes» counts grouped rows, so a patient with a
           control and a quotation is one patient, not two. -->
      <section
        v-if="canViewContinuity"
        class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4"
        aria-label="Resumen de la cola"
        data-continuity-kpis
      >
        <UiCard
          v-for="kpi in kpis"
          :key="kpi.key"
          variant="glass"
          padding="sm"
          :data-kpi-card="kpi.key"
          :style="{ boxShadow: 'var(--elevation-2)', borderColor: 'var(--color-hairline)' }"
        >
          <p class="text-xs font-medium uppercase text-theme-secondary">{{ kpi.label }}</p>
          <p
            class="mt-2 text-2xl font-bold leading-none text-label tabular-nums"
            style="font-feature-settings: var(--font-features-tabular-nums)"
            data-kpi-value
            :data-kpi-raw="kpi.raw"
          >
            {{ kpi.value }}
          </p>
          <p class="mt-1 text-xs text-theme-secondary">{{ kpi.caption }}</p>
        </UiCard>
      </section>

      <!--
        Refresh failure. The composable keeps the last queue mounted, so this
        block reports the stale state and offers the retry instead of blanking
        the desk reception is working from.
      -->
      <div
        v-if="canViewContinuity && error"
        data-continuity-error
        role="alert"
        class="flex flex-wrap items-center gap-4 p-4 rounded-ios bg-systemRed-50"
        style="border: 1px solid var(--color-hairline)"
      >
        <ExclamationTriangleIcon
          class="h-8 w-8 flex-shrink-0 text-systemRed-600"
          aria-hidden="true"
        />
        <div class="min-w-0 flex-1">
          <p class="text-sm font-medium text-theme-primary">No pudimos actualizar la cola</p>
          <p class="mt-1 text-sm text-theme-secondary" data-continuity-error-message>{{ error }}</p>
        </div>
        <UiButton variant="primary" size="sm" data-continuity-retry @click="load">
          Reintentar
        </UiButton>
      </div>

      <!-- Loading shape mirrors the desk so the page does not jump. -->
      <div
        v-if="canViewContinuity && showSkeleton"
        class="space-y-6"
        aria-busy="true"
        data-continuity-loading
      >
        <UiSkeleton variant="list" animation="wave" aria-label="Cargando pendientes" />
      </div>

      <!--
        One table, one row per patient. The four sources of the desk —overdue
        controls, continuity requests and the commercial answers— used to paint
        one table each, so the same patient could show up four times with their
        actions scattered. The grouping lives in `pendingRows`.
      -->
      <section
        v-else-if="showDesk"
        data-continuity-section="pending"
        aria-label="Pendientes por paciente"
        class="space-y-3"
      >
        <div class="flex flex-wrap items-baseline gap-3">
          <h3 class="text-xl font-semibold text-label">Pendientes por paciente</h3>
          <p class="text-sm text-theme-secondary">
            Una fila por paciente con sus controles, solicitudes y respuestas pendientes
          </p>
        </div>

        <!--
          State of the commercial half. Its payload key is the visibility
          contract —a key the role cannot read is absent— and it owns its load
          and error, so a failure here never unmounts the rows the continuity
          desks already have on screen.
        -->
        <div
          v-if="pendingLoading || hasPendingGroups || pendingError"
          data-continuity-pending-state
          class="space-y-3"
        >
          <UiSkeleton
            v-if="pendingLoading && pendingRows.length === 0"
            variant="list"
            animation="wave"
            aria-label="Cargando respuestas pendientes"
            data-continuity-pending-loading
          />

          <div
            v-else-if="pendingError"
            data-continuity-pending-error
            role="alert"
            class="flex flex-wrap items-center gap-4 p-4 rounded-ios bg-systemRed-50"
            style="border: 1px solid var(--color-hairline)"
          >
            <ExclamationTriangleIcon
              class="h-8 w-8 flex-shrink-0 text-systemRed-600"
              aria-hidden="true"
            />
            <div class="min-w-0 flex-1">
              <p class="text-sm font-medium text-theme-primary">
                No pudimos cargar las respuestas pendientes
              </p>
              <p class="mt-1 text-sm text-theme-secondary" data-continuity-pending-error-message>
                {{ pendingError }}
              </p>
            </div>
            <UiButton
              variant="primary"
              size="sm"
              data-continuity-pending-retry
              @click="loadPending"
            >
              Reintentar
            </UiButton>
          </div>
        </div>

        <UiSectionEmpty
          v-if="isDeskEmpty"
          :motif="false"
          data-continuity-empty="pending"
          title="Sin pendientes por paciente"
          description="Los controles vencidos, las solicitudes de continuidad y las respuestas que esperen una decisión aparecerán aquí."
        />

        <UiDataTable
          v-else-if="pendingRows.length > 0"
          :data="pendingRows"
          :columns="columns"
          row-key="key"
          :searchable="false"
          :pagination="false"
          :show-header="false"
          data-continuity-table="pending"
        >
          <template #cell-patient="{ row }">
            <span class="font-medium text-theme-primary" data-continuity-patient>
              {{ row.patientName }}
            </span>
          </template>

          <template #cell-pending="{ row }">
            <div class="flex flex-wrap items-center gap-1.5">
              <UiBadge
                v-for="label in row.labels"
                :key="label.key"
                :variant="label.variant"
                size="sm"
                :data-continuity-label="label.key"
              >
                {{ label.label }}
              </UiBadge>
            </div>
          </template>

          <!-- The detail names the pending that asks for action first: an
               overdue control, then a conduct, then the commercial answers;
               inside a source the oldest reference date wins. -->
          <template #cell-detail="{ row }">
            <div class="min-w-0 max-w-sm">
              <p class="truncate text-theme-primary" :title="row.detail.detailLabel">
                {{ row.detail.detailLabel }}
              </p>
              <p class="mt-0.5 text-xs text-theme-secondary tabular-nums">
                {{ row.detail.detailMeta }}
                <span v-if="row.moreCount > 0">· +{{ row.moreCount }} más</span>
              </p>
            </div>
          </template>

          <!--
            One action per pending, never one per priority: a patient with a
            control and a quotation keeps both doors. The write actions exist
            only with `manage.continuity`; a clinician reads the desk but never
            materialises it. The commercial answers are read-only and gated by
            their payload key instead.
          -->
          <template #actions="{ row: entry }">
            <template v-for="row in entry.items" :key="row.key">
              <UiButton
                v-if="canManageContinuity && row.source === 'recall'"
                size="sm"
                variant="primary"
                data-continuity-schedule="recall"
                @click.stop="openSchedule('recall', row.payload)"
              >
                Agendar control
              </UiButton>
              <template v-else-if="canManageContinuity && row.source === 'request'">
                <UiButton
                  size="sm"
                  variant="primary"
                  data-continuity-schedule="request"
                  @click.stop="openSchedule('request', row.payload)"
                >
                  Agendar
                </UiButton>
                <UiButton
                  size="sm"
                  variant="secondary"
                  data-continuity-dismiss
                  @click.stop="openDismiss(row.payload)"
                >
                  Descartar
                </UiButton>
              </template>
              <UiButton
                v-else-if="row.source === 'quotation' || row.source === 'plan'"
                size="sm"
                variant="secondary"
                :aria-label="row.actionAria"
                :data-continuity-pending-view="row.rowHook"
                @click.stop="openPending(row)"
              >
                {{ row.actionLabel }}
              </UiButton>
            </template>
          </template>
        </UiDataTable>
      </section>
    </div>

    <!-- Both writes live at page level: they close on success and the
         composable already refreshed the queue behind them. -->
    <ScheduleContinuityModal
      v-model="scheduleOpen"
      :kind="actionKind"
      :row="selected"
      @scheduled="closeActions"
    />
    <DismissContinuityModal v-model="dismissOpen" :row="selected" @dismissed="closeActions" />
  </AppLayout>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { ArrowPathIcon, ExclamationTriangleIcon } from '@heroicons/vue/24/outline'
import AppLayout from '../../components/layout/AppLayout.vue'
import PageHeader from '../../components/layout/PageHeader.vue'
import UiBadge from '../../components/ui/Badge.vue'
import UiButton from '../../components/ui/Button.vue'
import UiCard from '../../components/ui/Card.vue'
import UiDataTable from '../../components/ui/DataTable.vue'
import UiSectionEmpty from '../../components/ui/UiSectionEmpty.vue'
import UiSkeleton from '../../components/ui/Skeleton.vue'
import DismissContinuityModal from './DismissContinuityModal.vue'
import ScheduleContinuityModal from './ScheduleContinuityModal.vue'
import { useApi } from '../../composables/useApi'
import { formatNumber, formatPENLabel } from '../../composables/useFormatters'
import { useContinuity } from '../../composables/useContinuity'
import { usePermissions } from '../../composables/usePermissions'
import {
  compliancePercent,
  formatDate,
  intervalLabel,
  kindLabel,
  originLabel,
  patientFullName,
  preferredWindow
} from './continuityMeta'

const router = useRouter()
const { get } = useApi()
const { queue, metrics, loading, error, load } = useContinuity()
const { can } = usePermissions()

const canViewContinuity = computed(() => Boolean(can.viewContinuity?.value))
const canManageContinuity = computed(() => Boolean(can.manageContinuity?.value))

// One line, whether the session reads the whole desk or only the commercial
// half of it.
const headerDescription = computed(() =>
  canViewContinuity.value
    ? 'Recalls vencidos, solicitudes de continuidad y presupuestos o planes esperando respuesta.'
    : 'Presupuestos y planes de tratamiento esperando respuesta.'
)

// The selected row and the door it opens. `actionKind` survives a dismissal
// because the schedule modal stays closed until its own button opens it.
const selected = ref(null)
const actionKind = ref('request')
const scheduleOpen = ref(false)
const dismissOpen = ref(false)

const openSchedule = (kind, row) => {
  actionKind.value = kind
  selected.value = row
  scheduleOpen.value = true
}

const openDismiss = row => {
  selected.value = row
  dismissOpen.value = true
}

const closeActions = () => {
  scheduleOpen.value = false
  dismissOpen.value = false
}

// «Esperando respuesta» - the commercial pending work the dashboard used to
// publish. Its own door, its own state: a failure here never unmounts the
// queue rows, and Reintentar re-issues only this resource.
const pending = ref({})
const pendingLoading = ref(false)
const pendingError = ref(null)

const PENDING_TYPE_META = {
  quotations: {
    typeLabel: 'Presupuesto',
    rowHook: 'quotations',
    route: '/quotations',
    actionLabel: 'Ver presupuesto',
    readAmount: item => item?.total_amount
  },
  treatment_plans: {
    typeLabel: 'Plan de tratamiento',
    rowHook: 'treatment-plans',
    route: '/treatment-plans',
    actionLabel: 'Ver plan',
    readAmount: item => item?.final_cost
  }
}

const PENDING_STATUS_LABELS = { sent: 'Enviado', viewed: 'Visto', proposed: 'Propuesto' }

// The chip vocabulary of the grouped row, one per source. `order` is the
// reading order of the labels inside a row, not a priority for the actions.
const ROW_SOURCES = {
  recall: { label: 'Control', variant: 'warning', order: 0 },
  request: { label: 'Solicitud', variant: 'info', order: 1 },
  quotation: { label: 'Presupuesto', variant: 'neutral', order: 2 },
  plan: { label: 'Plan', variant: 'indigo', order: 3 }
}

const pendingQuotations = computed(() => pending.value?.quotations || null)
const pendingTreatmentPlans = computed(() => pending.value?.treatment_plans || null)

// An absent key means the role cannot read that module: its rows never render
// and no zero is invented for it.
const hasPendingGroups = computed(() =>
  Boolean(pendingQuotations.value || pendingTreatmentPlans.value)
)

// Unparseable or missing dates sort last instead of poisoning the merge.
const pendingSortTime = dateTime => {
  const time = new Date(dateTime).getTime()
  return Number.isNaN(time) ? Number.MAX_SAFE_INTEGER : time
}

// `patient_id` identifies the patient; older payloads (and the queue rows that
// already publish it) fall back to the embedded patient, then to the display
// name, so a row never disappears for lack of the column.
const patientKeyFor = (patientId, patient, patientName) => {
  const id = patientId ?? patient?.id
  if (id !== null && id !== undefined && id !== '') return `id:${id}`

  const name = patientName || patient?.full_name
  return name ? `name:${name}` : 'sin-paciente'
}

const recallPending = recall => ({
  key: `recall-${recall.id}`,
  source: 'recall',
  order: ROW_SOURCES.recall.order,
  patientKey: patientKeyFor(recall.patient_id, recall.patient),
  patientName: patientFullName(recall.patient),
  detailLabel: recall.reason || 'Control',
  detailMeta: [`Vence ${formatDate(recall.due_date)}`, intervalLabel(recall.interval_months)].join(
    ' · '
  ),
  sortTime: pendingSortTime(recall.due_date),
  payload: recall
})

const requestPending = request => ({
  key: `request-${request.id}`,
  source: 'request',
  order: ROW_SOURCES.request.order,
  patientKey: patientKeyFor(request.patient_id, request.patient),
  patientName: patientFullName(request.patient),
  detailLabel: request.notes || kindLabel(request.kind),
  detailMeta: [preferredWindow(request.preferred_from, request.preferred_to), originLabel(request)]
    .filter(Boolean)
    .join(' · '),
  sortTime: pendingSortTime(request.preferred_from),
  payload: request
})

const commercialPending = (entityType, item) => {
  const meta = PENDING_TYPE_META[entityType]
  const amount = meta.readAmount(item)
  const amountLabel = amount === null || amount === undefined ? 'N/D' : formatPENLabel(amount)
  const patientName = item.patient_name || 'Paciente'
  const statusLabel = PENDING_STATUS_LABELS[item.status] || item.status

  return {
    key: `${entityType}-${item.id}`,
    source: entityType === 'quotations' ? 'quotation' : 'plan',
    order: ROW_SOURCES[entityType === 'quotations' ? 'quotation' : 'plan'].order,
    entityType,
    rowHook: meta.rowHook,
    actionLabel: meta.actionLabel,
    actionAria: `${meta.actionLabel}: ${patientName}`,
    patientKey: patientKeyFor(item.patient_id, item.patient, patientName),
    patientName,
    detailLabel: entityType === 'treatment_plans' ? item.title || meta.typeLabel : meta.typeLabel,
    detailMeta: [formatDate(item.created_at), statusLabel, amountLabel].filter(Boolean).join(' · '),
    sortTime: pendingSortTime(item.created_at)
  }
}

const labelsFor = items => {
  const seen = new Map()
  for (const item of items) {
    if (!seen.has(item.source))
      seen.set(item.source, { key: item.source, ...ROW_SOURCES[item.source] })
  }
  return [...seen.values()].sort((a, b) => a.order - b.order)
}

// One row per patient: the four sources merge by `patientKey` and each row
// keeps every pending, so no action is lost when a patient has several.
const buildPendingRows = () => {
  const groups = new Map()

  const add = item => {
    if (!item) return
    const group = groups.get(item.patientKey) || {
      key: `patient-${item.patientKey}`,
      patientKey: item.patientKey,
      patientName: '',
      items: []
    }

    // A row is named by the first source that knows the patient; a bare
    // «Paciente» never wins over a real name.
    if (!group.patientName && item.patientName && item.patientName !== 'Paciente') {
      group.patientName = item.patientName
    }

    group.items.push(item)
    groups.set(item.patientKey, group)
  }

  for (const recall of queue.value.recalls) add(recallPending(recall))
  for (const request of queue.value.requests) add(requestPending(request))
  for (const item of pendingQuotations.value?.items || [])
    add(commercialPending('quotations', item))
  for (const item of pendingTreatmentPlans.value?.items || []) {
    add(commercialPending('treatment_plans', item))
  }

  const patientRows = []

  for (const group of groups.values()) {
    const rows = group.items
    // Two stable orders: oldest reference date first inside every source, then
    // the sources in the order that asks for action first — an overdue control,
    // then the conduct, then the commercial answers. The detail is the first
    // row and every item keeps its own action.
    rows.sort((a, b) => a.sortTime - b.sortTime)
    rows.sort((a, b) => a.order - b.order)

    patientRows.push({
      key: group.key,
      patientKey: group.patientKey,
      patientName: group.patientName || 'Paciente',
      labels: labelsFor(rows),
      detail: rows[0],
      items: rows,
      moreCount: Math.max(0, rows.length - 1)
    })
  }

  return patientRows
}

const pendingRows = computed(() => buildPendingRows())

const counts = computed(() => queue.value.counts || { recalls: 0, requests: 0, total: 0 })

const kpis = computed(() => [
  {
    key: 'recalls',
    label: 'Recalls vencidos',
    caption: 'Sin cita agendada',
    value: formatNumber(counts.value.recalls)
  },
  {
    key: 'requests',
    label: 'Solicitudes',
    caption: 'Pendientes de materializar',
    value: formatNumber(counts.value.requests)
  },
  {
    key: 'patients',
    label: 'Pacientes con pendientes',
    caption: 'Una fila por paciente',
    value: formatNumber(pendingRows.value.length)
  },
  {
    key: 'compliance',
    label: 'Cumplimiento',
    caption: 'Recalls completados',
    value: compliancePercent(metrics.value?.compliance_rate),
    raw: metrics.value?.compliance_rate ?? ''
  }
])

const columns = [
  { key: 'patient', label: 'Paciente' },
  { key: 'pending', label: 'Pendientes' },
  { key: 'detail', label: 'Detalle' }
]

// The desk renders for the continuity desks (queue) and for the roles that only
// read the commercial answers (finance). A session with neither grant gets no
// desk at all, and a failing queue leaves its own error block in charge.
const showDesk = computed(() => {
  if (pendingRows.value.length > 0) return true
  if (error.value) return false

  return canViewContinuity.value || hasPendingGroups.value || Boolean(pendingError.value)
})

const showSkeleton = computed(() => loading.value && pendingRows.value.length === 0 && !error.value)

// No empty state while a read is in flight or explained by its own error.
const isDeskEmpty = computed(
  () =>
    pendingRows.value.length === 0 &&
    !loading.value &&
    !pendingLoading.value &&
    !error.value &&
    !pendingError.value
)

const pendingMessage = thrown =>
  thrown?.response?.data?.message || thrown?.message || 'No se pudo cargar la respuesta comercial'

const loadPending = async () => {
  pendingLoading.value = true
  pendingError.value = null

  try {
    const response = await get('/api/dashboard/pending')
    const data = response?.data
    pending.value = data && typeof data === 'object' && !Array.isArray(data) ? data : {}
  } catch (thrown) {
    pendingError.value = pendingMessage(thrown)
  } finally {
    pendingLoading.value = false
  }
}

// No deep link exists for a quotation or a plan, so each answer opens its own
// module list.
const openPending = row => {
  const destination = PENDING_TYPE_META[row.entityType]?.route
  if (destination) router.push(destination)
}

// The desk refreshes both halves: the queue for the continuity desks and the
// commercial answers for everyone who reads them. `load()` is a no-op without
// `view.continuity`, so finance only re-reads its own half.
const refreshAll = () => {
  load()
  loadPending()
}

onMounted(() => {
  refreshAll()
})
</script>
