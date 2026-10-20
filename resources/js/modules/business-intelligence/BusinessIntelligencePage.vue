<template>
  <AppLayout class="bg-canvas">
    <header
      data-bi-hero
      class="mb-6 p-6 md:p-8"
      style="
        background: var(--color-canvas);
        border: 1px solid var(--color-hairline);
        border-radius: var(--radius-panel);
        box-shadow: var(--elevation-2);
      "
    >
      <div class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
        <div class="min-w-0">
          <p
            class="text-xs font-medium uppercase tracking-wide text-theme-secondary"
            data-hero-eyebrow
          >
            Inteligencia de negocio
          </p>
          <h2 class="mt-2 text-4xl font-semibold text-label" data-hero-title>
            Business Intelligence
          </h2>
          <p class="mt-2 text-sm text-theme-secondary" data-hero-caption>
            Citas, pacientes, ingresos y ocupación de la clínica
          </p>
        </div>
        <div class="flex flex-wrap items-center gap-2 md:flex-shrink-0 md:flex-nowrap">
          <UiButton
            variant="secondary"
            :disabled="loading || !!exporting"
            data-hero-refresh
            @click="loadReport"
          >
            <template #icon-left>
              <ArrowPathIcon class="w-4 h-4" aria-hidden="true" />
            </template>
            Actualizar
          </UiButton>
          <UiButton
            variant="secondary"
            size="sm"
            :disabled="loading || exporting === 'excel'"
            data-export-excel
            @click="exportReport('excel')"
          >
            Excel
          </UiButton>
          <UiButton
            variant="secondary"
            size="sm"
            :disabled="loading || exporting === 'csv'"
            data-export-csv
            @click="exportReport('csv')"
          >
            CSV
          </UiButton>
          <UiButton
            variant="secondary"
            size="sm"
            :disabled="loading || exporting === 'pdf'"
            data-export-pdf
            @click="exportReport('pdf')"
          >
            PDF
          </UiButton>
        </div>
      </div>
      <p class="mt-4 text-xs text-theme-secondary" data-period-applied>
        Período aplicado: {{ appliedLabel }}
      </p>
    </header>

    <UiCard variant="glass" class="mb-6" data-bi-filters>
      <template #header>
        <h2 class="text-lg font-semibold text-theme-primary">Filtros del reporte</h2>
      </template>

      <div class="grid grid-cols-1 gap-4 lg:grid-cols-12 lg:items-end">
        <div class="lg:col-span-3">
          <span class="mb-2 block text-sm font-medium text-theme-primary">Período</span>
          <div
            class="inline-flex gap-1 rounded-[var(--radius-control)] p-1"
            style="border: 1px solid var(--color-hairline)"
            role="group"
            aria-label="Presets de período"
          >
            <UiButton
              v-for="preset in presets"
              :key="preset.id"
              :variant="periodPreset === preset.id ? 'primary' : 'ghost'"
              size="sm"
              :aria-pressed="periodPreset === preset.id"
              :data-preset="preset.id"
              @click="selectPreset(preset)"
            >
              {{ preset.label }}
            </UiButton>
          </div>
        </div>

        <div class="lg:col-span-2">
          <UiInput
            id="bi-from"
            v-model="draft.from"
            type="date"
            label="Desde"
            data-filters-from
            @update:model-value="periodPreset = 'custom'"
          />
        </div>

        <div class="lg:col-span-2">
          <UiInput
            id="bi-to"
            v-model="draft.to"
            type="date"
            label="Hasta"
            :error="rangeError"
            data-filters-to
            @update:model-value="periodPreset = 'custom'"
          />
        </div>

        <div class="lg:col-span-2">
          <UiSelect
            v-model="draft.professionalId"
            :options="professionalOptions"
            label="Profesional"
            data-filters-professional
          />
        </div>

        <div class="lg:col-span-2">
          <UiSelect
            v-model="draft.environmentId"
            :options="environmentOptions"
            label="Ambiente"
            data-filters-environment
          />
        </div>

        <div class="lg:col-span-1">
          <UiButton
            class="w-full"
            :disabled="!!rangeError || loading"
            data-filters-apply
            @click="applyFilters"
          >
            Aplicar
          </UiButton>
        </div>
      </div>

      <div
        class="mt-4 flex flex-col gap-3 border-t pt-4 md:flex-row md:items-end md:justify-between"
        style="border-color: var(--color-hairline)"
      >
        <div class="md:w-80">
          <UiSelect
            v-model="selectedReport"
            :options="reportOptions"
            label="Reporte"
            data-filters-report
            @change="applyFilters"
          />
        </div>
        <UiButton variant="secondary" data-filters-reset @click="resetFilters">
          <template #icon-left>
            <ArrowUturnLeftIcon class="w-4 h-4" aria-hidden="true" />
          </template>
          Limpiar filtros
        </UiButton>
      </div>
    </UiCard>

    <div class="space-y-6" :aria-busy="loading">
      <DashboardSectionError
        v-if="error"
        data-state="error"
        role="alert"
        title="No pudimos cargar el reporte"
        :description="error"
        :retry-attrs="{ 'data-retry': '' }"
        @retry="loadReport"
      />

      <section
        v-if="loading"
        aria-label="Cargando reporte"
        aria-busy="true"
        data-bi-loading
        class="space-y-4"
      >
        <div class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-4">
          <UiSkeleton
            v-for="slot in 4"
            :key="`bi-skeleton-${slot}`"
            variant="card"
            animation="wave"
          />
        </div>
        <UiSkeleton variant="table" animation="wave" />
      </section>

      <template v-else-if="!error">
        <section
          v-if="isDashboard"
          aria-label="Resumen del período"
          data-bi-kpis
          class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-4"
        >
          <UiCard
            variant="glass"
            padding="sm"
            data-kpi-card="appointments"
            :style="{ boxShadow: 'var(--elevation-2)', borderColor: 'var(--color-hairline)' }"
          >
            <p class="text-xs font-medium uppercase text-theme-secondary">Citas</p>
            <p
              class="mt-2 text-2xl font-bold leading-none text-label tabular-nums"
              data-kpi-value="appointments"
            >
              {{ formatNumber(dashboard.totalAppointments) }}
            </p>
            <p class="mt-1 text-xs text-theme-secondary" data-kpi-caption="appointments">
              Agendadas en el período
            </p>
          </UiCard>

          <UiCard
            variant="glass"
            padding="sm"
            data-kpi-card="patients"
            :style="{ boxShadow: 'var(--elevation-2)', borderColor: 'var(--color-hairline)' }"
          >
            <p class="text-xs font-medium uppercase text-theme-secondary">Pacientes</p>
            <p
              class="mt-2 text-2xl font-bold leading-none text-label tabular-nums"
              data-kpi-value="patients"
            >
              {{ formatNumber(dashboard.totalPatients) }}
            </p>
            <p class="mt-1 text-xs text-theme-secondary" data-kpi-caption="patients">
              {{ formatNumber(dashboard.newPatients) }} nuevos en el período
            </p>
          </UiCard>

          <UiCard
            variant="glass"
            padding="sm"
            data-kpi-card="revenue"
            :style="{ boxShadow: 'var(--elevation-2)', borderColor: 'var(--color-hairline)' }"
          >
            <p class="text-xs font-medium uppercase text-theme-secondary">Ingresos cobrados</p>
            <p
              class="mt-2 text-2xl font-bold leading-none text-systemGreen-600 tabular-nums"
              data-kpi-value="revenue"
            >
              {{ formatCurrency(dashboard.totalRevenue) }}
            </p>
            <p class="mt-1 text-xs text-theme-secondary" data-kpi-caption="revenue">
              Pagos completados
            </p>
          </UiCard>

          <UiCard
            variant="glass"
            padding="sm"
            data-kpi-card="occupancy"
            :style="{ boxShadow: 'var(--elevation-2)', borderColor: 'var(--color-hairline)' }"
          >
            <p class="text-xs font-medium uppercase text-theme-secondary">Ocupación</p>
            <p
              class="mt-2 text-2xl font-bold leading-none text-label tabular-nums"
              data-kpi-value="occupancy"
            >
              {{ formatPercent(dashboard.occupancyRate) }}
            </p>
            <p class="mt-1 text-xs text-theme-secondary" data-kpi-caption="occupancy">
              {{ formatNumber(dashboard.usedHours) }} /
              {{ formatNumber(dashboard.availableHours) }} h de silla
            </p>
          </UiCard>
        </section>

        <section
          v-if="isDashboard && hasDashboardCharts"
          aria-label="Gráficos del período"
          data-bi-charts
          class="grid grid-cols-1 gap-4 lg:grid-cols-2"
        >
          <UiCard
            v-if="dashboard.appointmentsByDay.length > 0"
            variant="glass"
            data-chart-card="appointments-by-day"
          >
            <template #header>
              <h2 class="text-lg font-semibold text-theme-primary">Citas por Día</h2>
            </template>
            <div class="h-64">
              <canvas
                ref="appointmentsChartRef"
                data-chart="appointments-by-day"
                role="img"
                aria-label="Citas por día"
              />
            </div>
          </UiCard>

          <UiCard
            v-if="dashboard.revenueByMonth.length > 0"
            variant="glass"
            data-chart-card="revenue-by-month"
          >
            <template #header>
              <h2 class="text-lg font-semibold text-theme-primary">Ingresos Cobrados por Mes</h2>
            </template>
            <div class="h-64">
              <canvas
                ref="revenueChartRef"
                data-chart="revenue-by-month"
                role="img"
                aria-label="Ingresos cobrados por mes"
              />
            </div>
          </UiCard>
        </section>

        <section v-if="selectedReport === 'revenue' && revenueSummary" data-revenue-summary>
          <UiCard variant="glass">
            <template #header>
              <h2 class="text-lg font-semibold text-theme-primary">Resumen del período</h2>
            </template>
            <dl class="grid grid-cols-1 gap-4 md:grid-cols-3">
              <div>
                <dt class="text-xs font-medium uppercase text-theme-secondary">
                  Citas completadas
                </dt>
                <dd
                  class="mt-1 text-xl font-semibold text-label tabular-nums"
                  data-revenue-summary="appointments"
                >
                  {{ formatNumber(revenueSummary.appointments_count) }}
                </dd>
              </div>
              <div>
                <dt class="text-xs font-medium uppercase text-theme-secondary">
                  Ingresos cobrados
                </dt>
                <dd
                  class="mt-1 text-xl font-semibold text-label tabular-nums"
                  data-revenue-summary="revenue"
                >
                  {{ formatCurrency(revenueSummary.total_revenue) }}
                </dd>
              </div>
              <div>
                <dt class="text-xs font-medium uppercase text-theme-secondary">
                  Promedio por cita
                </dt>
                <dd
                  class="mt-1 text-xl font-semibold text-label tabular-nums"
                  data-revenue-summary="average"
                >
                  {{ formatCurrency(revenueSummary.average_per_appointment) }}
                </dd>
              </div>
            </dl>
          </UiCard>
        </section>

        <section
          v-if="selectedReport === 'revenue' && porDiaRows.length > 0"
          aria-label="Ingresos por día"
          data-bi-charts
          class="grid grid-cols-1 gap-4"
        >
          <UiCard variant="glass" data-chart-card="revenue-by-day">
            <template #header>
              <h2 class="text-lg font-semibold text-theme-primary">Ingresos Cobrados por Día</h2>
            </template>
            <div class="h-64">
              <canvas
                ref="dailyRevenueChartRef"
                data-chart="revenue-by-day"
                role="img"
                aria-label="Ingresos cobrados por día"
              />
            </div>
          </UiCard>
        </section>

        <UiCard v-if="isEmpty" variant="glass" data-report-empty>
          <UiEmptyState
            title="Sin datos en el período"
            description="No hay actividad registrada para los filtros aplicados."
          >
            <template #action>
              <UiButton variant="secondary" data-widen-period @click="selectPreset(presets[2])">
                Ampliar a 90 días
              </UiButton>
            </template>
          </UiEmptyState>
        </UiCard>

        <template v-else>
          <section
            v-if="isDashboard && dashboard.professionalPerformance.length > 0"
            aria-label="Rendimiento por profesional"
            data-professional-performance
          >
            <h2 class="mb-3 text-lg font-semibold text-theme-primary">
              Rendimiento por Profesional
            </h2>
            <UiDataTable
              :data="dashboard.professionalPerformance"
              :columns="professionalColumns"
              :searchable="false"
              :pagination="false"
              row-key="id"
              data-professional-table
            />
          </section>

          <section v-else-if="!isDashboard" :aria-label="reportTitle" data-report-table-section>
            <div class="mb-3 flex flex-wrap items-center justify-between gap-3">
              <h2 class="text-lg font-semibold text-theme-primary">{{ reportTitle }}</h2>
              <!--
                Revenue is cut four ways over the SAME money (by type, by
                professional, by chair, by day). Showing all four at once in
                one table invited adding the column and quadrupling the total,
                so only one cut is visible at a time.
              -->
              <div
                v-if="selectedReport === 'revenue' && availableRevenueDimensions.length > 1"
                role="group"
                aria-label="Corte de ingresos"
                class="flex flex-wrap gap-1 rounded-[var(--radius-control)] border p-1"
                style="border-color: var(--color-hairline)"
                data-revenue-dimensions
              >
                <UiButton
                  v-for="dimension in availableRevenueDimensions"
                  :key="dimension"
                  size="sm"
                  :variant="revenueDimension === dimension ? 'primary' : 'ghost'"
                  :aria-pressed="revenueDimension === dimension"
                  :data-revenue-dimension="dimension"
                  @click="revenueDimension = dimension"
                >
                  {{ dimension }}
                </UiButton>
              </div>
            </div>
            <UiDataTable
              :data="visibleReportRows"
              :columns="tableColumns"
              row-key="id"
              data-report-table
            />
          </section>
        </template>
      </template>
    </div>
  </AppLayout>
</template>

<script setup>
import { computed, nextTick, onMounted, onUnmounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { ArrowPathIcon, ArrowUturnLeftIcon } from '@heroicons/vue/24/outline'
import { colors } from '../../design-system/tokens.js'
import { useApi } from '../../composables/useApi'
import { formatCurrency } from '../../composables/useFormatters'
import { useToast } from '../../composables/useToast'
import AppLayout from '../../components/layout/AppLayout.vue'
import UiButton from '../../components/ui/Button.vue'
import UiCard from '../../components/ui/Card.vue'
import UiDataTable from '../../components/ui/DataTable.vue'
import UiEmptyState from '../../components/ui/EmptyState.vue'
import UiInput from '../../components/ui/Input.vue'
import UiSelect from '../../components/ui/Select.vue'
import UiSkeleton from '../../components/ui/Skeleton.vue'
import DashboardSectionError from '../dashboard/DashboardSectionError.vue'

const DASHBOARD_ENDPOINT = '/api/reports/dashboard'
const FALLBACK_ERROR = 'Revisa tu conexión y vuelve a intentarlo.'
const MONEY_KEYS = new Set(['price', 'total_spent', 'total_revenue', 'average_per_appointment'])
const EXPORT_EXTENSIONS = { excel: 'xls', csv: 'csv', pdf: 'pdf' }

const EMPTY_DASHBOARD = {
  totalAppointments: 0,
  totalPatients: 0,
  newPatients: 0,
  totalRevenue: 0,
  occupancyRate: 0,
  usedHours: 0,
  availableHours: 0,
  appointmentsByDay: [],
  revenueByMonth: [],
  professionalPerformance: []
}

const presets = [
  { id: '7d', label: '7 días', days: 7 },
  { id: '30d', label: '30 días', days: 30 },
  { id: '90d', label: '90 días', days: 90 }
]

const reportOptions = [
  { value: 'dashboard', label: 'Dashboard General' },
  { value: 'appointments', label: 'Reporte de Citas' },
  { value: 'patients', label: 'Reporte de Pacientes' },
  { value: 'professionals', label: 'Reporte de Profesionales' },
  { value: 'revenue', label: 'Reporte de Ingresos' },
  { value: 'utilization', label: 'Utilización de Ambientes' }
]

const REVENUE_DIMENSIONS = [
  'Por Tipo de Cita',
  'Por Profesional',
  'Por Ambiente',
  'Por Día',
  'Sin cita asociada'
]

const REPORT_TITLES = {
  appointments: 'Reporte de Citas',
  patients: 'Reporte de Pacientes',
  professionals: 'Reporte de Profesionales',
  revenue: 'Reporte de Ingresos',
  utilization: 'Utilización de Ambientes'
}

const professionalColumns = [
  { key: 'name', label: 'Profesional' },
  { key: 'appointments', label: 'Citas', align: 'right' },
  {
    key: 'revenue',
    label: 'Ingresos cobrados',
    align: 'right',
    formatter: value => formatCurrency(value)
  },
  {
    key: 'averagePerAppointment',
    label: 'Promedio por cita',
    align: 'right',
    formatter: value => formatCurrency(value)
  }
]

const toISODate = date => {
  const year = date.getFullYear()
  const month = String(date.getMonth() + 1).padStart(2, '0')
  const day = String(date.getDate()).padStart(2, '0')
  return `${year}-${month}-${day}`
}

const daysBack = days => {
  const date = new Date()
  date.setDate(date.getDate() - days)
  return toISODate(date)
}

const formatShortDate = isoDate => (isoDate || '').split('-').reverse().join('/')

const formatNumber = value =>
  Number(value || 0).toLocaleString('es-PE', { maximumFractionDigits: 1 })

const formatPercent = value => `${formatNumber(value)}%`

const defaultRange = () => ({ from: daysBack(29), to: daysBack(0) })

const router = useRouter()
const { get, download, normalizeError } = useApi()
const toast = useToast()

const draft = ref({ ...defaultRange(), professionalId: '', environmentId: '' })
const applied = ref({ ...draft.value })
const periodPreset = ref('30d')
const appliedPreset = ref('30d')
const selectedReport = ref('dashboard')
const professionals = ref([])
const environments = ref([])
const dashboard = ref({ ...EMPTY_DASHBOARD })
const reportRows = ref([])
const reportColumns = ref([])
const revenueSummary = ref(null)
const loading = ref(false)
const error = ref(null)
const exporting = ref('')

const appointmentsChartRef = ref(null)
const revenueChartRef = ref(null)
const dailyRevenueChartRef = ref(null)

let chartInstances = []
let chartRenderToken = 0
let disposed = false

const isDashboard = computed(() => selectedReport.value === 'dashboard')

const rangeError = computed(() => {
  if (!draft.value.from || !draft.value.to) {
    return ''
  }
  return draft.value.to < draft.value.from
    ? 'La fecha final no puede ser anterior a la inicial.'
    : ''
})

const appliedLabel = computed(() => {
  const preset = presets.find(item => item.id === appliedPreset.value)
  const range = `${formatShortDate(applied.value.from)} — ${formatShortDate(applied.value.to)}`
  return `${range} · ${preset ? preset.label : 'rango manual'}`
})

const professionalOptions = computed(() => [
  { value: '', label: 'Todos los profesionales' },
  ...professionals.value.map(professional => ({
    value: professional.id,
    label: professional.name
  }))
])

const environmentOptions = computed(() => [
  { value: '', label: 'Todos los ambientes' },
  ...environments.value.map(environment => ({
    value: environment.id,
    label: environment.name
  }))
])

const reportTitle = computed(() => REPORT_TITLES[selectedReport.value] || 'Reporte')

const hasDashboardCharts = computed(
  () => dashboard.value.appointmentsByDay.length > 0 || dashboard.value.revenueByMonth.length > 0
)

const porDiaRows = computed(() =>
  selectedReport.value === 'revenue' ? reportRows.value.filter(row => row.type === 'Por Día') : []
)

// D8: the four revenue cuts describe the same money, so they are alternatives,
// never rows of one summable column.
const revenueDimension = ref(REVENUE_DIMENSIONS[0])

const availableRevenueDimensions = computed(() =>
  REVENUE_DIMENSIONS.filter(dimension => reportRows.value.some(row => row.type === dimension))
)

const visibleReportRows = computed(() => {
  if (selectedReport.value === 'revenue') {
    return reportRows.value.filter(row => row.type === revenueDimension.value)
  }
  return reportRows.value
})

const isEmpty = computed(() => {
  if (isDashboard.value) {
    return !hasDashboardCharts.value && dashboard.value.professionalPerformance.length === 0
  }
  return reportRows.value.length === 0
})

const tableColumns = computed(() =>
  reportColumns.value.map(column => {
    const isMoney = MONEY_KEYS.has(column.key)
    return {
      ...column,
      align: isMoney ? 'right' : column.align,
      formatter: isMoney ? value => formatCurrency(value) : undefined
    }
  })
)

const normalizeDashboard = payload => ({
  totalAppointments: Number(payload?.totalAppointments ?? 0),
  totalPatients: Number(payload?.totalPatients ?? 0),
  newPatients: Number(payload?.newPatients ?? 0),
  totalRevenue: Number(payload?.totalRevenue ?? 0),
  occupancyRate: Number(payload?.occupancyRate ?? 0),
  usedHours: Number(payload?.usedHours ?? 0),
  availableHours: Number(payload?.availableHours ?? 0),
  appointmentsByDay: Array.isArray(payload?.appointmentsByDay) ? payload.appointmentsByDay : [],
  revenueByMonth: Array.isArray(payload?.revenueByMonth) ? payload.revenueByMonth : [],
  professionalPerformance: Array.isArray(payload?.professionalPerformance)
    ? payload.professionalPerformance
    : []
})

const appliedParams = () => ({
  start_date: applied.value.from,
  end_date: applied.value.to,
  professional_id: applied.value.professionalId || '',
  environment_id: applied.value.environmentId || ''
})

const clearData = () => {
  dashboard.value = { ...EMPTY_DASHBOARD }
  reportRows.value = []
  reportColumns.value = []
  revenueSummary.value = null
}

const destroyCharts = () => {
  for (const chart of chartInstances) {
    try {
      chart.destroy()
    } catch (chartError) {
      // The instance was already disposed; nothing left to release.
    }
  }
  chartInstances = []
}

const appointmentsChartConfig = () => ({
  type: 'line',
  data: {
    labels: dashboard.value.appointmentsByDay.map(item => item.date),
    datasets: [
      {
        label: 'Citas',
        data: dashboard.value.appointmentsByDay.map(item => Number(item.count || 0)),
        borderColor: colors.systemIndigo[500],
        backgroundColor: `${colors.systemIndigo[500]}1a`,
        fill: true,
        tension: 0.4
      }
    ]
  },
  options: {
    responsive: true,
    maintainAspectRatio: false,
    plugins: { legend: { display: false } },
    scales: {
      y: { beginAtZero: true, ticks: { precision: 0 } }
    }
  }
})

const revenueByMonthChartConfig = () => ({
  type: 'bar',
  data: {
    labels: dashboard.value.revenueByMonth.map(item => item.month),
    datasets: [
      {
        label: 'Ingresos cobrados (S/)',
        data: dashboard.value.revenueByMonth.map(item => Number(item.revenue || 0)),
        backgroundColor: `${colors.systemGreen[500]}cc`,
        borderColor: colors.systemGreen[500],
        borderWidth: 1
      }
    ]
  },
  options: {
    responsive: true,
    maintainAspectRatio: false,
    plugins: { legend: { display: false } },
    scales: {
      y: { beginAtZero: true }
    }
  }
})

const revenueByDayChartConfig = () => ({
  type: 'bar',
  data: {
    labels: porDiaRows.value.map(row => row.category),
    datasets: [
      {
        label: 'Ingresos cobrados (S/)',
        data: porDiaRows.value.map(row => Number(row.total_revenue || 0)),
        backgroundColor: `${colors.accent[500]}cc`,
        borderColor: colors.accent[500],
        borderWidth: 1,
        borderRadius: 6
      }
    ]
  },
  options: {
    responsive: true,
    maintainAspectRatio: false,
    plugins: { legend: { display: false } },
    scales: {
      y: { beginAtZero: true }
    }
  }
})

const chartTargets = () => {
  const targets = []

  if (isDashboard.value) {
    if (appointmentsChartRef.value && dashboard.value.appointmentsByDay.length > 0) {
      targets.push({
        canvas: appointmentsChartRef.value,
        config: appointmentsChartConfig()
      })
    }
    if (revenueChartRef.value && dashboard.value.revenueByMonth.length > 0) {
      targets.push({
        canvas: revenueChartRef.value,
        config: revenueByMonthChartConfig()
      })
    }
    return targets
  }

  if (
    selectedReport.value === 'revenue' &&
    dailyRevenueChartRef.value &&
    porDiaRows.value.length > 0
  ) {
    targets.push({
      canvas: dailyRevenueChartRef.value,
      config: revenueByDayChartConfig()
    })
  }

  return targets
}

const renderCharts = async () => {
  chartRenderToken += 1
  const token = chartRenderToken
  destroyCharts()

  const targets = chartTargets()
  if (targets.length === 0) {
    return
  }

  try {
    const { Chart, registerables } = await import('chart.js')
    Chart.register(...registerables)

    if (disposed || token !== chartRenderToken) {
      return
    }

    for (const target of targets) {
      if (typeof target.canvas?.getContext !== 'function') {
        continue
      }
      const context = target.canvas.getContext('2d')
      if (!context) {
        continue
      }
      chartInstances.push(new Chart(target.canvas, target.config))
    }
  } catch (chartError) {
    // A chart failure must never remove the tabular data below.
  }
}

const reportErrorMessage = err => normalizeError(err, FALLBACK_ERROR) || FALLBACK_ERROR

const loadReport = async () => {
  loading.value = true
  error.value = null

  try {
    if (isDashboard.value) {
      const response = await get(DASHBOARD_ENDPOINT, { params: appliedParams() })
      dashboard.value = normalizeDashboard(response?.data)
      reportRows.value = []
      reportColumns.value = []
      revenueSummary.value = null
    } else {
      const response = await get(`/api/reports/${selectedReport.value}`, {
        params: appliedParams()
      })
      reportRows.value = Array.isArray(response?.data) ? response.data : []
      reportColumns.value = Array.isArray(response?.columns) ? response.columns : []
      revenueSummary.value =
        selectedReport.value === 'revenue' ? (response?.meta?.summary ?? null) : null
      if (selectedReport.value === 'revenue') {
        revenueDimension.value = availableRevenueDimensions.value[0] || REVENUE_DIMENSIONS[0]
      }
      dashboard.value = { ...EMPTY_DASHBOARD }
    }
  } catch (err) {
    error.value = reportErrorMessage(err)
    clearData()
  } finally {
    loading.value = false
    await nextTick()
    await renderCharts()
  }
}

const applyFilters = () => {
  if (rangeError.value) {
    return
  }

  applied.value = {
    from: draft.value.from,
    to: draft.value.to,
    professionalId: draft.value.professionalId,
    environmentId: draft.value.environmentId
  }
  appliedPreset.value = periodPreset.value
  loadReport()
}

const selectPreset = preset => {
  periodPreset.value = preset.id
  draft.value.from = daysBack(preset.days - 1)
  draft.value.to = daysBack(0)
  applyFilters()
}

const resetFilters = () => {
  periodPreset.value = '30d'
  draft.value = { ...defaultRange(), professionalId: '', environmentId: '' }
  applyFilters()
}

const loadFilterOptions = async () => {
  const [professionalsResponse, environmentsResponse] = await Promise.all([
    get('/api/users/active').catch(() => null),
    get('/api/dental-chairs/active').catch(() => null)
  ])

  professionals.value = Array.isArray(professionalsResponse?.data) ? professionalsResponse.data : []
  environments.value = Array.isArray(environmentsResponse?.data) ? environmentsResponse.data : []
}

const saveBlob = (blob, filename) => {
  if (typeof window === 'undefined' || typeof window.URL?.createObjectURL !== 'function') {
    return
  }

  const url = window.URL.createObjectURL(blob)
  const link = document.createElement('a')
  link.href = url
  link.download = filename
  document.body.appendChild(link)
  link.click()
  document.body.removeChild(link)
  window.URL.revokeObjectURL(url)
}

const exportReport = async format => {
  if (exporting.value) {
    return
  }

  exporting.value = format
  const fallbackFilename = `${selectedReport.value}_${applied.value.from}_${applied.value.to}.${EXPORT_EXTENSIONS[format]}`

  try {
    const { blob, filename } = await download(`/api/reports/${selectedReport.value}/export`, {
      params: { format, ...appliedParams() },
      fallbackFilename
    })
    saveBlob(blob, filename || fallbackFilename)
  } catch (err) {
    toast.error(reportErrorMessage(err))
  } finally {
    exporting.value = ''
  }
}

onMounted(async () => {
  const localUser = localStorage.getItem('user')
  if (!localUser) {
    router.push('/login')
    return
  }

  await loadFilterOptions()
  await loadReport()
})

onUnmounted(() => {
  disposed = true
  chartRenderToken += 1
  destroyCharts()
})
</script>
