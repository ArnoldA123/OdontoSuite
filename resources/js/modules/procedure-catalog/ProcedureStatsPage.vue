<template>
  <AppLayout class="bg-canvas">
    <header
      data-procedure-stats-hero
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
          <div class="flex flex-wrap items-center gap-2">
            <p
              class="text-xs font-medium uppercase tracking-wide text-theme-secondary"
              data-hero-eyebrow
            >
              Catálogo
            </p>
            <UiBadge variant="neutral" size="sm" shape="pill" data-role-disclosure>
              Administrador · Finanzas
            </UiBadge>
          </div>
          <h2 class="mt-2 text-4xl font-semibold text-label" data-hero-title>
            Estadísticas del Catálogo
          </h2>
          <p class="mt-2 text-sm text-theme-secondary" data-hero-caption>
            Uso del catálogo por especialidad y procedimientos más frecuentes
          </p>
        </div>
        <div class="flex flex-wrap items-center gap-3 md:flex-shrink-0 md:flex-nowrap">
          <UiButton variant="secondary" data-hero-back @click="goBack">
            <template #icon-left>
              <ArrowLeftIcon class="w-4 h-4" aria-hidden="true" />
            </template>
            Volver al catálogo
          </UiButton>
          <UiButton
            variant="primary"
            data-export-csv
            :disabled="loading || !hasData"
            @click="exportCsv"
          >
            <template #icon-left>
              <ArrowDownTrayIcon class="w-4 h-4" aria-hidden="true" />
            </template>
            Exportar CSV
          </UiButton>
          <UiButton variant="secondary" :disabled="loading" data-hero-refresh @click="applyFilters">
            <template #icon-left>
              <ArrowPathIcon class="w-4 h-4" aria-hidden="true" />
            </template>
            Actualizar
          </UiButton>
        </div>
      </div>
      <p class="mt-4 text-xs text-theme-secondary" data-period-applied>
        Período aplicado: {{ appliedLabel }}
      </p>
    </header>

    <UiCard variant="glass" class="mb-6" data-procedure-stats-filters>
      <template #header>
        <h2 class="text-lg font-semibold text-theme-primary">Filtros</h2>
      </template>
      <div class="grid grid-cols-1 gap-4 lg:grid-cols-12 lg:items-end">
        <div class="lg:col-span-5">
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
            id="procedure-stats-from"
            v-model="draft.from"
            type="date"
            label="Desde"
            data-filters-from
            @update:model-value="periodPreset = 'custom'"
          />
        </div>
        <div class="lg:col-span-2">
          <UiInput
            id="procedure-stats-to"
            v-model="draft.to"
            type="date"
            label="Hasta"
            :error="rangeError"
            data-filters-to
            @update:model-value="periodPreset = 'custom'"
          />
        </div>
        <div class="lg:col-span-1">
          <UiSelect
            v-model="draft.limit"
            :options="limitOptions"
            label="Top N"
            data-filters-limit
          />
        </div>
        <div class="lg:col-span-2">
          <UiButton
            class="w-full"
            :disabled="!!rangeError || loading"
            data-filters-apply
            @click="applyFilters"
          >
            {{ loading ? 'Consultando...' : 'Aplicar filtros' }}
          </UiButton>
        </div>
      </div>
    </UiCard>

    <div
      v-if="error"
      data-state="error"
      role="alert"
      class="mb-6 flex flex-wrap items-center justify-between gap-4 rounded-[var(--radius-panel)] bg-systemRed-50 p-5"
      style="border: 1px solid var(--color-hairline)"
    >
      <div class="flex min-w-0 items-center gap-3">
        <ExclamationTriangleIcon
          class="h-6 w-6 flex-shrink-0 text-systemRed-600"
          aria-hidden="true"
        />
        <div class="min-w-0">
          <p class="text-sm font-medium text-theme-primary">No pudimos cargar las estadísticas</p>
          <p class="text-sm text-theme-secondary">{{ error }}</p>
        </div>
      </div>
      <UiButton variant="primary" size="sm" data-retry @click="loadStats">Reintentar</UiButton>
    </div>

    <section
      v-if="loading"
      aria-label="Cargando resumen"
      aria-busy="true"
      class="mb-6 grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-4"
    >
      <UiSkeleton v-for="slot in 4" :key="slot" variant="card" animation="wave" />
    </section>

    <section
      v-else-if="!error"
      aria-label="Resumen del catálogo"
      class="mb-6 grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-4"
    >
      <UiCard
        variant="glass"
        padding="sm"
        data-kpi-card="total"
        :style="{ boxShadow: 'var(--elevation-2)', borderColor: 'var(--color-hairline)' }"
      >
        <p class="text-xs font-medium uppercase text-theme-secondary">Procedimientos</p>
        <p
          class="mt-2 text-2xl font-bold leading-none text-label tabular-nums"
          data-kpi-value="total"
        >
          {{ formatCount(catalog.total) }}
        </p>
        <p class="mt-1 text-xs text-theme-secondary" data-kpi-caption="total">En el catálogo</p>
      </UiCard>

      <UiCard
        variant="glass"
        padding="sm"
        data-kpi-card="active"
        :style="{ boxShadow: 'var(--elevation-2)', borderColor: 'var(--color-hairline)' }"
      >
        <p class="text-xs font-medium uppercase text-theme-secondary">Activos</p>
        <p
          class="mt-2 text-2xl font-bold leading-none text-systemGreen-600 tabular-nums"
          data-kpi-value="active"
        >
          {{ formatCount(catalog.active) }}
        </p>
        <p class="mt-1 text-xs text-theme-secondary" data-kpi-caption="active">
          Disponibles para uso clínico
        </p>
      </UiCard>

      <UiCard
        variant="glass"
        padding="sm"
        data-kpi-card="inactive"
        :style="{ boxShadow: 'var(--elevation-2)', borderColor: 'var(--color-hairline)' }"
      >
        <p class="text-xs font-medium uppercase text-theme-secondary">Inactivos</p>
        <p
          class="mt-2 text-2xl font-bold leading-none text-label tabular-nums"
          data-kpi-value="inactive"
        >
          {{ formatCount(catalog.inactive) }}
        </p>
        <p class="mt-1 text-xs text-theme-secondary" data-kpi-caption="inactive">
          Fuera del flujo clínico
        </p>
      </UiCard>

      <UiCard
        variant="glass"
        padding="sm"
        data-kpi-card="period-usage"
        :style="{ boxShadow: 'var(--elevation-2)', borderColor: 'var(--color-hairline)' }"
      >
        <p class="text-xs font-medium uppercase text-theme-secondary">Usos del período</p>
        <p
          class="mt-2 text-2xl font-bold leading-none text-label tabular-nums"
          data-kpi-value="period-usage"
        >
          {{ formatCount(periodUsage) }}
        </p>
        <p class="mt-1 text-xs text-theme-secondary" data-kpi-caption="period-usage">
          {{ formatCurrency(periodRevenue) }} en ingresos
        </p>
      </UiCard>
    </section>

    <section
      v-if="!loading && !error && hasData"
      aria-label="Gráficos del período"
      class="mb-6 grid grid-cols-1 gap-4 lg:grid-cols-2"
      data-charts
    >
      <UiCard variant="glass" data-chart-card="usage">
        <template #header>
          <h2 class="text-lg font-semibold text-theme-primary">Usos por procedimiento</h2>
        </template>
        <div class="h-64">
          <canvas
            ref="usageChartRef"
            data-chart="usage"
            role="img"
            aria-label="Usos por procedimiento"
          />
        </div>
      </UiCard>

      <UiCard variant="glass" data-chart-card="specialty">
        <template #header>
          <h2 class="text-lg font-semibold text-theme-primary">Participación por especialidad</h2>
        </template>
        <div class="h-64">
          <canvas
            ref="specialtyChartRef"
            data-chart="specialty"
            role="img"
            aria-label="Participación por especialidad"
          />
        </div>
      </UiCard>
    </section>

    <div v-if="loading" aria-busy="true" class="space-y-6">
      <UiSkeleton variant="card" height="320px" animation="wave" />
      <UiSkeleton variant="table" animation="wave" />
    </div>

    <UiCard v-else-if="!error && !hasData" variant="glass" data-period-empty>
      <UiEmptyState
        title="Sin uso registrado en el período"
        description="Ningún plan de tratamiento del período incluye procedimientos del catálogo."
      >
        <template #action>
          <UiButton variant="secondary" data-widen-period @click="selectPreset(presets[2])">
            Ampliar a 90 días
          </UiButton>
        </template>
      </UiEmptyState>
    </UiCard>

    <template v-else-if="!error">
      <section class="mb-6" aria-label="Procedimientos más usados" data-top-procedures>
        <h2 class="mb-3 text-lg font-semibold text-theme-primary">
          Top {{ topRows.length }} procedimientos más usados
        </h2>
        <!-- The DataTable renders its own framed surface: wrapping it in a Card
             stacks two boxes with the same background. -->
        <UiDataTable
          :data="topRows"
          :columns="columns"
          :searchable="false"
          :pagination="false"
          row-key="key"
          data-top-procedures-table
        >
          <template #cell-rank="{ row }">
            <span class="text-theme-secondary tabular-nums">{{ row.rank }}</span>
          </template>
          <template #cell-code="{ row }">
            <span class="font-mono text-theme-primary">{{ row.code }}</span>
          </template>
          <template #cell-name="{ row }">
            <span class="text-theme-primary">{{ row.name }}</span>
          </template>
          <template #cell-usage_count="{ row }">
            <span class="tabular-nums">{{ formatCount(row.usage_count) }}</span>
          </template>
          <template #cell-total_quantity="{ row }">
            <span class="tabular-nums">{{ formatCount(row.total_quantity) }}</span>
          </template>
          <template #cell-total_revenue="{ row }">
            <span class="tabular-nums">{{ formatCurrency(row.total_revenue) }}</span>
          </template>
        </UiDataTable>
      </section>

      <section aria-label="Distribución por especialidad" data-specialty-distribution>
        <UiCard variant="glass">
          <template #header>
            <h2 class="text-lg font-semibold text-theme-primary">Distribución por especialidad</h2>
          </template>
          <ul class="grid grid-cols-1 gap-4 md:grid-cols-2">
            <li
              v-for="row in specialtyRows"
              :key="row.key"
              class="rounded-[var(--radius-control)] p-4"
              style="
                border: 1px solid var(--color-hairline);
                background: var(--color-surface-elevated);
              "
              data-specialty-row
            >
              <div class="flex items-start justify-between gap-3">
                <div class="min-w-0">
                  <p class="truncate text-sm font-medium text-theme-primary">
                    {{ row.specialty_name || 'Sin especialidad' }}
                  </p>
                  <p class="mt-1 text-xs text-theme-secondary tabular-nums">
                    {{ formatCount(row.usage_count) }} usos ·
                    {{ formatCurrency(row.total_revenue) }}
                  </p>
                </div>
                <UiBadge variant="neutral" size="sm" shape="pill" data-specialty-share>
                  {{ row.shareLabel }}
                </UiBadge>
              </div>
              <div
                class="mt-3 h-1.5 w-full overflow-hidden rounded-full"
                style="background: var(--color-hairline)"
                role="presentation"
              >
                <div
                  class="h-full rounded-full bg-accent"
                  data-specialty-bar
                  :data-specialty-bar-percent="row.barWidth"
                  :style="{ width: `${row.barWidth}%` }"
                />
              </div>
            </li>
          </ul>
        </UiCard>
      </section>
    </template>
  </AppLayout>
</template>

<script setup>
import { computed, nextTick, onMounted, onUnmounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import {
  ArrowDownTrayIcon,
  ArrowLeftIcon,
  ArrowPathIcon,
  ExclamationTriangleIcon
} from '@heroicons/vue/24/outline'
import { colors } from '../../design-system/tokens.js'
import { useApi } from '../../composables/useApi'
import { formatCurrency } from '../../composables/useFormatters'
import AppLayout from '../../components/layout/AppLayout.vue'
import UiBadge from '../../components/ui/Badge.vue'
import UiButton from '../../components/ui/Button.vue'
import UiCard from '../../components/ui/Card.vue'
import UiDataTable from '../../components/ui/DataTable.vue'
import UiEmptyState from '../../components/ui/EmptyState.vue'
import UiInput from '../../components/ui/Input.vue'
import UiSelect from '../../components/ui/Select.vue'
import UiSkeleton from '../../components/ui/Skeleton.vue'
import {
  buildProcedureStatsCsv,
  buildProcedureStatsFilename,
  downloadCsvFile
} from './procedureStatsCsv'

const STATS_ENDPOINT = '/api/admin/procedure-stats'

// Chart.js cannot read CSS custom properties, so the charts consume the same
// token module the rest of the app uses instead of hard-coded hex values.
const CHART_PALETTE = [
  colors.accent[500],
  colors.systemIndigo[500],
  colors.systemOrange[500],
  colors.systemGreen[500],
  colors.systemPurple[500],
  colors.systemPink[500],
  colors.systemYellow[500]
]
const CHART_LABEL_MAX = 28
const CHART_LABEL_MAX_NARROW = 16
const CHART_NARROW_WIDTH = 420
const MIN_VISIBLE_BAR_PERCENT = 2

const presets = [
  { id: '7d', label: '7 días', days: 7 },
  { id: '30d', label: '30 días', days: 30 },
  { id: '90d', label: '90 días', days: 90 }
]

const limitOptions = [5, 10, 20, 50].map(value => ({ value, label: String(value) }))

const columns = [
  { key: 'rank', label: '#' },
  { key: 'code', label: 'Código' },
  { key: 'name', label: 'Procedimiento' },
  { key: 'usage_count', label: 'Usos', align: 'right' },
  { key: 'total_quantity', label: 'Cantidad', align: 'right' },
  { key: 'total_revenue', label: 'Ingresos', align: 'right' }
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

const formatCount = value => Number(value || 0).toLocaleString('es-PE')

const formatShortDate = isoDate => (isoDate || '').split('-').reverse().join('/')

const router = useRouter()
const { get } = useApi()

const draft = ref({ from: daysBack(89), to: daysBack(0), limit: 10 })
const applied = ref({ ...draft.value })
const periodPreset = ref('90d')
const appliedPreset = ref('90d')

const stats = ref(null)
const loading = ref(false)
const error = ref(null)

const usageChartRef = ref(null)
const specialtyChartRef = ref(null)

let usageChart = null
let specialtyChart = null
let chartRenderToken = 0
let disposed = false

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
  return `${range} · ${preset ? preset.label : 'rango manual'} · Top ${applied.value.limit}`
})

const catalog = computed(() => stats.value?.catalog ?? { total: 0, active: 0, inactive: 0 })
const topProcedures = computed(() => stats.value?.top_procedures ?? [])
const bySpecialty = computed(() => stats.value?.by_specialty ?? [])

const periodUsage = computed(() =>
  bySpecialty.value.reduce((total, row) => total + Number(row.usage_count || 0), 0)
)

const periodRevenue = computed(() =>
  bySpecialty.value.reduce((total, row) => total + Number(row.total_revenue || 0), 0)
)

const hasData = computed(() => topProcedures.value.length > 0 || bySpecialty.value.length > 0)

const topRows = computed(() =>
  topProcedures.value.map((row, index) => ({
    ...row,
    rank: index + 1,
    key: row.procedure_id ?? index
  }))
)

// A bar too small to see is still a datum, so a nonzero participation keeps a
// visible floor. The badge reads that same number: a rounded 0 % next to a
// drawn bar would make the two surfaces disagree about one specialty.
const barWidthFor = (sharePct, usage) =>
  usage > 0 ? Math.max(MIN_VISIBLE_BAR_PERCENT, sharePct) : 0

const shareLabelFor = (sharePct, usage) => (usage > 0 && sharePct === 0 ? '<1%' : `${sharePct}%`)

const specialtyRows = computed(() =>
  bySpecialty.value.map(row => {
    const usage = Number(row.usage_count || 0)
    const share = periodUsage.value > 0 ? (usage / periodUsage.value) * 100 : 0
    const sharePct = Math.round(share)
    return {
      ...row,
      key: row.specialty_id ?? 'sin-especialidad',
      sharePct,
      shareLabel: shareLabelFor(sharePct, usage),
      barWidth: barWidthFor(sharePct, usage)
    }
  })
)

const goBack = () => router.push('/procedure-catalog')

const exportCsv = () => {
  if (!hasData.value) {
    return
  }

  const csv = buildProcedureStatsCsv({
    topProcedures: topProcedures.value,
    bySpecialty: bySpecialty.value
  })
  downloadCsvFile(buildProcedureStatsFilename(applied.value.from, applied.value.to), csv)
}

const truncateChartLabel = (label, limit = CHART_LABEL_MAX) => {
  const text = String(label ?? '')
  return text.length > limit ? `${text.slice(0, limit - 1)}…` : text
}

// A narrow canvas cannot reserve room for a long axis label: Chart.js draws it
// past the left edge and the browser clips the text instead of shortening it,
// so the label budget follows the rendered width.
const chartLabelLimitForWidth = width =>
  width > 0 && width < CHART_NARROW_WIDTH ? CHART_LABEL_MAX_NARROW : CHART_LABEL_MAX

const usageChartLabels = width =>
  topProcedures.value.map(row => truncateChartLabel(row.name, chartLabelLimitForWidth(width)))

// Chart.js measures the canvas once when it builds the axis, so a resize that
// crosses the threshold must re-derive the labels from the new width instead of
// keeping the budget of the first paint. Redrawing only on a band change keeps
// the callback from repainting on every resize frame.
const syncUsageChartLabels = (chart, width) => {
  const labels = usageChartLabels(width)
  if (labels.every((label, index) => chart.data.labels[index] === label)) {
    return
  }
  chart.data.labels = labels
  chart.update()
}

const destroyCharts = () => {
  for (const chart of [usageChart, specialtyChart]) {
    if (!chart) {
      continue
    }
    try {
      chart.destroy()
    } catch (chartError) {
      // The instance was already disposed; nothing left to release.
    }
  }
  usageChart = null
  specialtyChart = null
}

const renderCharts = async () => {
  chartRenderToken += 1
  const token = chartRenderToken
  destroyCharts()

  if (!hasData.value) {
    return
  }

  const usageCanvas = usageChartRef.value
  const specialtyCanvas = specialtyChartRef.value
  if (
    typeof usageCanvas?.getContext !== 'function' ||
    typeof specialtyCanvas?.getContext !== 'function'
  ) {
    return
  }

  try {
    const { Chart, registerables } = await import('chart.js')
    Chart.register(...registerables)

    if (disposed || token !== chartRenderToken) {
      return
    }

    const usageContext = usageCanvas.getContext('2d')
    const specialtyContext = specialtyCanvas.getContext('2d')
    if (!usageContext || !specialtyContext) {
      return
    }

    usageChart = new Chart(usageCanvas, {
      type: 'bar',
      data: {
        labels: usageChartLabels(usageCanvas.clientWidth),
        datasets: [
          {
            label: 'Usos',
            data: topProcedures.value.map(row => Number(row.usage_count || 0)),
            backgroundColor: `${colors.accent[500]}cc`,
            borderColor: colors.accent[500],
            borderWidth: 1,
            borderRadius: 6
          }
        ]
      },
      options: {
        indexAxis: 'y',
        responsive: true,
        maintainAspectRatio: false,
        onResize: (chart, size) => syncUsageChartLabels(chart, size.width),
        plugins: { legend: { display: false } },
        scales: {
          x: { beginAtZero: true, ticks: { precision: 0 } },
          y: { ticks: { autoSkip: false } }
        }
      }
    })

    const specialtyLabels = bySpecialty.value.map(row => row.specialty_name || 'Sin especialidad')
    specialtyChart = new Chart(specialtyCanvas, {
      type: 'doughnut',
      data: {
        labels: specialtyLabels,
        datasets: [
          {
            label: 'Usos',
            data: bySpecialty.value.map(row => Number(row.usage_count || 0)),
            backgroundColor: specialtyLabels.map(
              (label, index) => CHART_PALETTE[index % CHART_PALETTE.length]
            ),
            borderColor: colors.background.systemBackground,
            borderWidth: 2
          }
        ]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { display: true, position: 'bottom' } }
      }
    })
  } catch (chartError) {
    // A chart failure must never remove the tabular data below.
  }
}

const loadStats = async () => {
  loading.value = true
  error.value = null

  try {
    const params = new URLSearchParams({
      from: applied.value.from,
      to: applied.value.to,
      limit: String(applied.value.limit)
    })
    const response = await get(`${STATS_ENDPOINT}?${params.toString()}`)
    stats.value = response?.data ?? null
  } catch (err) {
    error.value = err?.response?.data?.message || 'Revisa tu conexión y vuelve a intentarlo.'
    stats.value = null
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
    limit: Number(draft.value.limit)
  }
  appliedPreset.value = periodPreset.value
  loadStats()
}

const selectPreset = preset => {
  periodPreset.value = preset.id
  draft.value.from = daysBack(preset.days - 1)
  draft.value.to = daysBack(0)
  applyFilters()
}

onMounted(loadStats)

onUnmounted(() => {
  disposed = true
  destroyCharts()
})
</script>
