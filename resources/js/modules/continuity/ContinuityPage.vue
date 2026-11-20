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
           the API publishes instead of recomputing it from the counts. -->
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

      <!-- Loading shape mirrors the two sections so the page does not jump. -->
      <div
        v-if="canViewContinuity && showSkeleton"
        class="space-y-6"
        aria-busy="true"
        data-continuity-loading
      >
        <section
          v-for="section in sections"
          :key="`skeleton-${section.key}`"
          :aria-label="section.title"
        >
          <UiSkeleton
            variant="list"
            animation="wave"
            :aria-label="`Cargando ${section.title.toLowerCase()}`"
          />
        </section>
      </div>

      <template v-else-if="canViewContinuity && (!error || hasRows)">
        <section
          v-for="section in sections"
          :key="section.key"
          :data-continuity-section="section.key"
          :aria-label="section.title"
          class="space-y-3"
        >
          <div class="flex flex-wrap items-baseline gap-3">
            <h3 class="text-xl font-semibold text-label">{{ section.title }}</h3>
            <p class="text-sm text-theme-secondary">{{ section.caption }}</p>
          </div>

          <UiSectionEmpty
            v-if="section.rows.length === 0"
            :motif="false"
            :data-continuity-empty="section.key"
            :title="section.emptyTitle"
            :description="section.emptyDescription"
          />

          <UiDataTable
            v-else
            :data="section.rows"
            :columns="section.columns"
            row-key="id"
            :searchable="false"
            :pagination="false"
            :show-header="false"
            :data-continuity-table="section.key"
          >
            <template #cell-patient="{ row }">
              <span class="font-medium text-theme-primary">{{ patientFullName(row.patient) }}</span>
            </template>

            <template #cell-reason="{ row }">
              <span class="text-theme-primary">{{ section.reasonFor(row) }}</span>
            </template>

            <template #cell-due="{ row }">
              <span class="tabular-nums">{{ formatDate(row.due_date) }}</span>
            </template>

            <template #cell-interval="{ row }">
              <span>{{ intervalLabel(row.interval_months) }}</span>
            </template>

            <template #cell-preferred="{ row }">
              <span class="tabular-nums">
                {{ preferredWindow(row.preferred_from, row.preferred_to) }}
              </span>
            </template>

            <template #cell-duration="{ row }">
              <span class="tabular-nums">
                {{ row.duration_minutes ? `${row.duration_minutes} min` : '—' }}
              </span>
            </template>

            <template #cell-origin="{ row }">
              <span>{{ originLabel(row) }}</span>
            </template>

            <!-- S9 — the row actions exist only with `manage.continuity`: a
                 clinician reads the queue but never materialises it. -->
            <template #actions="{ row }">
              <template v-if="canManageContinuity">
                <UiButton
                  v-if="section.key === 'recalls'"
                  size="sm"
                  variant="primary"
                  data-continuity-schedule="recall"
                  @click.stop="openSchedule('recall', row)"
                >
                  Agendar
                </UiButton>
                <template v-else>
                  <UiButton
                    size="sm"
                    variant="primary"
                    data-continuity-schedule="request"
                    @click.stop="openSchedule('request', row)"
                  >
                    Agendar
                  </UiButton>
                  <UiButton
                    size="sm"
                    variant="secondary"
                    data-continuity-dismiss
                    @click.stop="openDismiss(row)"
                  >
                    Descartar
                  </UiButton>
                </template>
              </template>
            </template>
          </UiDataTable>
        </section>
      </template>

      <!--
        S15 - the commercial pending work the dashboard used to publish. The
        payload key is the visibility contract: a key the role cannot read is
        absent, so its rows never render and no zero is invented for it. The
        section owns its own load and error, so a failure here leaves the two
        queue populations above intact.
      -->
      <section
        v-if="pendingLoading || hasPendingGroups || pendingError"
        data-continuity-section="pending"
        aria-label="Esperando respuesta"
        class="space-y-3"
      >
        <div class="flex flex-wrap items-baseline gap-3">
          <h3 class="text-xl font-semibold text-label">Esperando respuesta</h3>
          <p class="text-sm text-theme-secondary">
            Presupuestos y planes de tratamiento con una decisión pendiente
          </p>
        </div>

        <UiSkeleton
          v-if="pendingLoading"
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
          <UiButton variant="primary" size="sm" data-continuity-pending-retry @click="loadPending">
            Reintentar
          </UiButton>
        </div>

        <UiSectionEmpty
          v-else-if="pendingRows.length === 0"
          :motif="false"
          data-continuity-empty="pending"
          title="Sin respuestas pendientes"
          description="Los presupuestos y planes que esperen una decisión aparecerán aquí."
        />

        <UiDataTable
          v-else
          :data="pendingRows"
          :columns="pendingColumns"
          row-key="key"
          :searchable="false"
          :pagination="false"
          :show-header="false"
          data-continuity-table="pending"
        >
          <template #cell-patient="{ row }">
            <span class="font-medium text-theme-primary">{{ row.patientName }}</span>
          </template>

          <template #cell-type="{ row }">
            <UiBadge variant="neutral" size="sm" data-continuity-pending-type>
              {{ row.typeLabel }}
            </UiBadge>
          </template>

          <template #cell-amount="{ row }">
            <span class="tabular-nums">{{ row.amountLabel }}</span>
          </template>

          <template #cell-status="{ row }">
            <UiBadge :variant="row.statusVariant" size="sm">{{ row.statusLabel }}</UiBadge>
          </template>

          <template #cell-registered="{ row }">
            <span class="tabular-nums">{{ row.dateLabel }}</span>
          </template>

          <template #actions="{ row }">
            <UiButton
              size="sm"
              variant="secondary"
              :aria-label="row.actionAria"
              :data-continuity-pending-view="row.rowHook"
              @click.stop="openPending(row)"
            >
              Ver
            </UiButton>
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

// One line, whether the session reads the whole queue or only the commercial
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

const RECALL_COLUMNS = [
  { key: 'patient', label: 'Paciente' },
  { key: 'reason', label: 'Motivo' },
  { key: 'due', label: 'Vence' },
  { key: 'interval', label: 'Intervalo' }
]

const REQUEST_COLUMNS = [
  { key: 'patient', label: 'Paciente' },
  { key: 'reason', label: 'Motivo' },
  { key: 'preferred', label: 'Fecha preferida' },
  { key: 'duration', label: 'Duración' },
  { key: 'origin', label: 'Origen' }
]

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
    key: 'total',
    label: 'Total en cola',
    caption: 'Recalls y solicitudes',
    value: formatNumber(counts.value.total)
  },
  {
    key: 'compliance',
    label: 'Cumplimiento',
    caption: 'Recalls completados',
    value: compliancePercent(metrics.value?.compliance_rate),
    raw: metrics.value?.compliance_rate ?? ''
  }
])

const sections = computed(() => [
  {
    key: 'recalls',
    title: 'Recalls vencidos',
    caption: 'Vencidos y sin cita agendada',
    columns: RECALL_COLUMNS,
    rows: queue.value.recalls,
    reasonFor: row => row.reason || '—',
    emptyTitle: 'Sin recalls vencidos',
    emptyDescription: 'Los controles vencidos que aún no tienen cita aparecerán aquí.'
  },
  {
    key: 'requests',
    title: 'Solicitudes pendientes',
    caption: 'Conducta pendiente de materializar',
    columns: REQUEST_COLUMNS,
    rows: queue.value.requests,
    reasonFor: row => row.notes || kindLabel(row.kind),
    emptyTitle: 'Sin solicitudes pendientes',
    emptyDescription: 'Las conductas que el profesional deje al cerrar la atención aparecerán aquí.'
  }
])

const hasRows = computed(() => sections.value.some(section => section.rows.length > 0))
const showSkeleton = computed(() => loading.value && !hasRows.value)

// «Esperando respuesta» - the commercial pending work the dashboard used to
// publish. Its own door, its own state: a failure here never unmounts the
// queue above, and Reintentar re-issues only this resource.
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
    actionLabel: 'Ver plan de tratamiento',
    readAmount: item => item?.final_cost
  }
}

const PENDING_STATUS_LABELS = { sent: 'Enviado', viewed: 'Visto', proposed: 'Propuesto' }
const PENDING_STATUS_VARIANTS = { sent: 'info', viewed: 'warning', proposed: 'neutral' }

const pendingColumns = [
  { key: 'patient', label: 'Paciente' },
  { key: 'type', label: 'Tipo' },
  { key: 'amount', label: 'Importe' },
  { key: 'status', label: 'Estado' },
  { key: 'registered', label: 'Registrado' }
]

// Unparseable or missing dates sort last instead of poisoning the merge.
const pendingSortTime = dateTime => {
  const time = new Date(dateTime).getTime()
  return Number.isNaN(time) ? Number.MAX_SAFE_INTEGER : time
}

const pendingQuotations = computed(() => pending.value?.quotations || null)
const pendingTreatmentPlans = computed(() => pending.value?.treatment_plans || null)

// An absent key means the role cannot read that module: its rows never render
// and the section stays hidden while no readable subset exists.
const hasPendingGroups = computed(() =>
  Boolean(pendingQuotations.value || pendingTreatmentPlans.value)
)

const pendingRows = computed(() => {
  const rows = []
  const collect = (entityType, group) => {
    if (!group) return
    const meta = PENDING_TYPE_META[entityType]
    for (const item of group.items || []) {
      const amount = meta.readAmount(item)
      const patientName = item.patient_name || 'Paciente'
      rows.push({
        key: `${entityType}-${item.id}`,
        entityType,
        rowHook: meta.rowHook,
        typeLabel: meta.typeLabel,
        patientName,
        amountLabel: amount === null || amount === undefined ? 'N/D' : formatPENLabel(amount),
        statusLabel: PENDING_STATUS_LABELS[item.status] || item.status,
        statusVariant: PENDING_STATUS_VARIANTS[item.status] || 'neutral',
        dateLabel: formatDate(item.created_at),
        actionAria: `${meta.actionLabel}: ${patientName}`,
        sortTime: pendingSortTime(item.created_at)
      })
    }
  }
  collect('quotations', pendingQuotations.value)
  collect('treatment_plans', pendingTreatmentPlans.value)
  rows.sort((a, b) => a.sortTime - b.sortTime)
  return rows
})

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

// No deep link exists for a quotation or a plan, so each row opens its own
// module list.
const openPending = row => {
  const destination = PENDING_TYPE_META[row.entityType]?.route
  if (destination) router.push(destination)
}

// The desk refreshes both halves: the queue for the continuity desks and the
// commercial answers for everyone who reads them. `load()` is a no-op without
// `view.continuity`, so finance only re-reads its own section.
const refreshAll = () => {
  load()
  loadPending()
}

onMounted(() => {
  refreshAll()
})
</script>
