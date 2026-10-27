<template>
  <AppLayout>
    <div class="space-y-8" data-continuity>
      <!--
        Hero band in the dashboard's surface vocabulary: canvas fill, hairline,
        panel radius and the elevation-2 rung. The page owns its visible title
        (h2, not h1), so the topbar keeps its heading for the outline only.
      -->
      <header
        data-continuity-hero
        class="p-6 md:p-8"
        style="
          background: var(--color-canvas);
          border: 1px solid var(--color-hairline);
          border-radius: var(--radius-panel);
          box-shadow: var(--elevation-2);
        "
      >
        <div class="flex flex-col gap-6 md:flex-row md:items-start md:justify-between">
          <div class="min-w-0">
            <p class="text-xs font-medium uppercase tracking-wide text-theme-secondary">
              Continuidad
            </p>
            <h2 class="mt-2 text-4xl font-semibold text-label" data-continuity-title>
              Cola de continuidad
            </h2>
            <p class="mt-2 text-sm text-theme-secondary">
              Recalls vencidos y la conducta que dejó el profesional al cerrar la atención.
            </p>
          </div>

          <UiButton variant="secondary" :disabled="loading" data-continuity-refresh @click="load">
            <template #icon-left>
              <ArrowPathIcon class="h-4 w-4" aria-hidden="true" />
            </template>
            Actualizar
          </UiButton>
        </div>
      </header>

      <!-- One card per headline number; the compliance card formats the rate
           the API publishes instead of recomputing it from the counts. -->
      <section
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
        v-if="error"
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
      <div v-if="showSkeleton" class="space-y-6" aria-busy="true" data-continuity-loading>
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

      <template v-else-if="!error || hasRows">
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
import { ArrowPathIcon, ExclamationTriangleIcon } from '@heroicons/vue/24/outline'
import AppLayout from '../../components/layout/AppLayout.vue'
import UiButton from '../../components/ui/Button.vue'
import UiCard from '../../components/ui/Card.vue'
import UiDataTable from '../../components/ui/DataTable.vue'
import UiSectionEmpty from '../../components/ui/UiSectionEmpty.vue'
import UiSkeleton from '../../components/ui/Skeleton.vue'
import DismissContinuityModal from './DismissContinuityModal.vue'
import ScheduleContinuityModal from './ScheduleContinuityModal.vue'
import { formatNumber } from '../../composables/useFormatters'
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

const { queue, metrics, loading, error, load } = useContinuity()
const { can } = usePermissions()

const canManageContinuity = computed(() => Boolean(can.manageContinuity?.value))

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

onMounted(load)
</script>
