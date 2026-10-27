<template>
  <div class="cash-reports space-y-6 bg-canvas">
    <UiFilterBar title="Período del reporte" :columns="4">
      <UiInput
        id="cash-reports-start"
        v-model="draft.start"
        type="date"
        label="Desde"
        data-report-start
      />
      <UiInput
        id="cash-reports-end"
        v-model="draft.end"
        type="date"
        label="Hasta"
        data-report-end
      />

      <div class="flex flex-col justify-end">
        <span class="mb-2 text-sm font-medium text-theme-primary">Rangos rápidos</span>
        <div class="flex flex-wrap items-center gap-2">
          <UiButton
            v-for="preset in presets"
            :key="preset.days"
            variant="secondary"
            size="sm"
            :data-preset="preset.days"
            @click="applyPreset(preset.days)"
          >
            {{ preset.label }}
          </UiButton>
        </div>
      </div>

      <div class="flex items-end">
        <UiButton
          variant="primary"
          data-report-apply
          :loading="loading"
          full-width
          @click="applyFilters"
        >
          <template #icon-left>
            <ArrowPathIcon class="w-4 h-4" aria-hidden="true" />
          </template>
          Aplicar
        </UiButton>
      </div>
    </UiFilterBar>

    <div
      v-if="error"
      role="alert"
      data-report-error
      class="flex flex-col gap-3 p-4 sm:flex-row sm:items-center sm:justify-between"
      style="
        background: var(--color-canvas);
        border: 1px solid var(--color-hairline);
        border-radius: var(--radius-panel);
        box-shadow: var(--elevation-1);
      "
    >
      <p class="text-sm text-systemRed-600">{{ error }}</p>
      <UiButton variant="secondary" size="sm" data-report-retry @click="loadReport">
        Reintentar
      </UiButton>
    </div>

    <section
      aria-label="Resumen del período"
      class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-4"
    >
      <UiCard
        variant="glass"
        padding="sm"
        :style="{ boxShadow: 'var(--elevation-2)', borderColor: 'var(--color-hairline)' }"
      >
        <p class="text-xs font-medium uppercase text-theme-secondary">Sesiones</p>
        <p
          class="mt-2 text-2xl font-bold leading-none text-label tabular-nums"
          data-report-kpi="sessions"
        >
          {{ kpiSessions }}
        </p>
        <p class="mt-1 text-xs text-theme-secondary">Turnos del período</p>
      </UiCard>

      <UiCard
        variant="glass"
        padding="sm"
        :style="{ boxShadow: 'var(--elevation-2)', borderColor: 'var(--color-hairline)' }"
      >
        <p class="text-xs font-medium uppercase text-theme-secondary">Apertura</p>
        <p
          class="mt-2 text-2xl font-bold leading-none text-label tabular-nums"
          data-report-kpi="opening"
        >
          {{ kpiOpening }}
        </p>
        <p class="mt-1 text-xs text-theme-secondary">Monto inicial acumulado</p>
      </UiCard>

      <UiCard
        variant="glass"
        padding="sm"
        :style="{ boxShadow: 'var(--elevation-2)', borderColor: 'var(--color-hairline)' }"
      >
        <p class="text-xs font-medium uppercase text-theme-secondary">Cierre</p>
        <p
          class="mt-2 text-2xl font-bold leading-none text-label tabular-nums"
          data-report-kpi="closing"
        >
          {{ kpiClosing }}
        </p>
        <p class="mt-1 text-xs text-theme-secondary">Monto contado al cierre</p>
      </UiCard>

      <UiCard
        variant="glass"
        padding="sm"
        :style="{ boxShadow: 'var(--elevation-2)', borderColor: 'var(--color-hairline)' }"
      >
        <p class="text-xs font-medium uppercase text-theme-secondary">Diferencia</p>
        <p
          class="mt-2 text-2xl font-bold leading-none text-label tabular-nums"
          data-report-kpi="difference"
        >
          {{ kpiDifference }}
        </p>
        <p class="mt-1 text-xs text-theme-secondary">Arqueo acumulado</p>
      </UiCard>
    </section>

    <UiCard
      variant="glass"
      padding="sm"
      data-sales-line
      :style="{ boxShadow: 'var(--elevation-2)', borderColor: 'var(--color-hairline)' }"
    >
      <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <p class="text-xs font-medium uppercase text-theme-secondary">Ventas del período</p>
        <div class="flex flex-wrap items-center gap-x-8 gap-y-2">
          <div class="flex items-baseline gap-2">
            <span class="text-xs text-theme-secondary">Cobros</span>
            <span class="text-sm font-semibold text-label tabular-nums" data-report-kpi="income">
              {{ kpiIncome }}
            </span>
          </div>
          <div class="flex items-baseline gap-2">
            <span class="text-xs text-theme-secondary">Egresos</span>
            <span class="text-sm font-semibold text-label tabular-nums" data-report-kpi="expenses">
              {{ kpiExpenses }}
            </span>
          </div>
        </div>
      </div>
    </UiCard>

    <section aria-label="Conciliación" data-report-reconciliation class="space-y-3">
      <h3 class="text-base font-semibold text-label">Conciliación</h3>
      <div class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-4">
        <UiCard
          v-for="card in reconciliationCards"
          :key="card.key"
          variant="glass"
          padding="sm"
          :data-reconciliation="card.key"
          :style="{ boxShadow: 'var(--elevation-2)', borderColor: 'var(--color-hairline)' }"
        >
          <p class="text-xs font-medium uppercase text-theme-secondary">{{ card.label }}</p>
          <p
            class="mt-2 text-2xl font-bold leading-none text-label tabular-nums"
            :data-reconciliation-value="card.key"
          >
            {{ card.value }}
          </p>
          <p class="mt-1 text-xs text-theme-secondary">
            <template v-if="card.count !== null">
              <span :data-reconciliation-count="card.key">{{ card.count }}</span>
              operaciones ·
            </template>
            {{ card.caption }}
          </p>
        </UiCard>
      </div>
    </section>

    <template v-if="!error">
      <section aria-label="Participación por método de pago" class="space-y-3">
        <h3 class="text-base font-semibold text-label">Participación por método de pago</h3>
        <UiDataTable
          data-report-table="payment-methods"
          :data="paymentMethods"
          :columns="paymentMethodColumns"
          :loading="loading"
          :pagination="false"
          :searchable="false"
          :show-header="false"
          row-key="payment_method"
          empty-title="Sin operaciones en el período"
          empty-description="No se registraron cobros dentro del período aplicado."
        >
          <template #cell-count="{ value }">
            <span class="tabular-nums">{{ value ?? 0 }}</span>
          </template>
          <template #cell-total="{ value }">
            <span class="tabular-nums" :aria-label="`${formatCurrency(value)} soles`">
              {{ formatCurrency(value) }}
            </span>
          </template>
          <template #cell-average="{ value }">
            <span class="tabular-nums" :aria-label="`${formatCurrency(value)} soles`">
              {{ formatCurrency(value) }}
            </span>
          </template>
          <template #cell-percentage="{ value }">
            <span class="tabular-nums">{{ formatPercentage(value) }}</span>
          </template>
        </UiDataTable>
      </section>

      <section aria-label="Resumen diario" class="space-y-3">
        <h3 class="text-base font-semibold text-label">Resumen diario</h3>
        <UiDataTable
          data-report-table="daily"
          :data="dailySummary"
          :columns="dailyColumns"
          :loading="loading"
          :pagination="false"
          :searchable="false"
          :show-header="false"
          row-key="date"
          empty-title="Sin sesiones en el período"
          empty-description="No se registraron sesiones dentro del período aplicado."
        >
          <template #cell-date="{ value }">
            <span class="tabular-nums">{{ formatReportDate(value) }}</span>
          </template>
          <template #cell-sessions_count="{ value }">
            <span class="tabular-nums">{{ value ?? 0 }}</span>
          </template>
          <template #cell-total_opening="{ value }">
            <span class="tabular-nums" :aria-label="`${formatCurrency(value)} soles`">
              {{ formatCurrency(value) }}
            </span>
          </template>
          <template #cell-total_closing="{ value }">
            <span class="tabular-nums" :aria-label="`${formatCurrency(value)} soles`">
              {{ formatCurrency(value) }}
            </span>
          </template>
          <template #cell-total_difference="{ value }">
            <span class="tabular-nums" :aria-label="`${formatCurrency(value)} soles`">
              {{ formatCurrency(value) }}
            </span>
          </template>
        </UiDataTable>
      </section>

      <section
        v-if="branchSummary.length || userSummary.length"
        aria-label="Desglose del período"
        class="grid grid-cols-1 gap-6 lg:grid-cols-2"
      >
        <div v-if="branchSummary.length" class="space-y-3">
          <h3 class="text-base font-semibold text-label">Sesiones por sucursal</h3>
          <UiDataTable
            data-report-table="summary-branches"
            :data="branchSummary"
            :columns="branchColumns"
            :pagination="false"
            :searchable="false"
            :show-header="false"
            row-key="branch"
          >
            <template #cell-sessions_count="{ value }">
              <span class="tabular-nums">{{ value ?? 0 }}</span>
            </template>
            <template #cell-total_opening="{ value }">
              <span class="tabular-nums" :aria-label="`${formatCurrency(value)} soles`">
                {{ formatCurrency(value) }}
              </span>
            </template>
            <template #cell-total_closing="{ value }">
              <span class="tabular-nums" :aria-label="`${formatCurrency(value)} soles`">
                {{ formatCurrency(value) }}
              </span>
            </template>
            <template #cell-total_difference="{ value }">
              <span class="tabular-nums" :aria-label="`${formatCurrency(value)} soles`">
                {{ formatCurrency(value) }}
              </span>
            </template>
          </UiDataTable>
        </div>

        <div v-if="userSummary.length" class="space-y-3">
          <h3 class="text-base font-semibold text-label">Sesiones por usuario</h3>
          <UiDataTable
            data-report-table="summary-users"
            :data="userSummary"
            :columns="userColumns"
            :pagination="false"
            :searchable="false"
            :show-header="false"
            row-key="user"
          >
            <template #cell-sessions_count="{ value }">
              <span class="tabular-nums">{{ value ?? 0 }}</span>
            </template>
            <template #cell-total_opening="{ value }">
              <span class="tabular-nums" :aria-label="`${formatCurrency(value)} soles`">
                {{ formatCurrency(value) }}
              </span>
            </template>
            <template #cell-total_closing="{ value }">
              <span class="tabular-nums" :aria-label="`${formatCurrency(value)} soles`">
                {{ formatCurrency(value) }}
              </span>
            </template>
            <template #cell-total_difference="{ value }">
              <span class="tabular-nums" :aria-label="`${formatCurrency(value)} soles`">
                {{ formatCurrency(value) }}
              </span>
            </template>
          </UiDataTable>
        </div>
      </section>
    </template>

    <div
      v-if="exportError"
      role="alert"
      data-export-error
      class="flex flex-col gap-3 p-4 sm:flex-row sm:items-center sm:justify-between"
      style="
        background: var(--color-canvas);
        border: 1px solid var(--color-hairline);
        border-radius: var(--radius-panel);
        box-shadow: var(--elevation-1);
      "
    >
      <p class="text-sm text-systemRed-600">{{ exportError }}</p>
      <UiButton variant="secondary" size="sm" data-export-retry @click="retryExport">
        Reintentar
      </UiButton>
    </div>

    <section aria-label="Exportar reporte" class="flex flex-col gap-3 sm:flex-row sm:justify-end">
      <UiButton
        variant="secondary"
        data-export="csv"
        :loading="exportingFormat === 'csv'"
        :disabled="exportingFormat !== null"
        @click="exportReport('csv')"
      >
        <template #icon-left>
          <ArrowDownTrayIcon class="w-4 h-4" aria-hidden="true" />
        </template>
        CSV
      </UiButton>
      <UiButton
        variant="secondary"
        data-export="pdf"
        :loading="exportingFormat === 'pdf'"
        :disabled="exportingFormat !== null"
        @click="exportReport('pdf')"
      >
        <template #icon-left>
          <ArrowDownTrayIcon class="w-4 h-4" aria-hidden="true" />
        </template>
        PDF
      </UiButton>
      <UiButton
        variant="secondary"
        data-export="excel"
        :loading="exportingFormat === 'excel'"
        :disabled="exportingFormat !== null"
        @click="exportReport('excel')"
      >
        <template #icon-left>
          <ArrowDownTrayIcon class="w-4 h-4" aria-hidden="true" />
        </template>
        Excel
      </UiButton>
    </section>
  </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'
import UiDataTable from '@/components/ui/DataTable.vue'
import { formatCurrency, formatDate } from '@/composables/useFormatters'
import { useApi } from '@/composables/useApi'
import { ArrowDownTrayIcon, ArrowPathIcon } from '@heroicons/vue/24/outline'

const { get, download } = useApi()

const presets = [
  { days: 7, label: '7 días' },
  { days: 30, label: '30 días' },
  { days: 90, label: '90 días' }
]

const paymentMethodColumns = [
  { key: 'payment_method', label: 'Método' },
  { key: 'count', label: 'Operaciones', sortable: true, align: 'right' },
  { key: 'total', label: 'Total', sortable: true, align: 'right' },
  { key: 'average', label: 'Promedio', sortable: true, align: 'right' },
  { key: 'percentage', label: 'Participación', sortable: true, align: 'right' }
]

const dailyColumns = [
  { key: 'date', label: 'Fecha' },
  { key: 'sessions_count', label: 'Sesiones', sortable: true, align: 'right' },
  { key: 'total_opening', label: 'Apertura', sortable: true, align: 'right' },
  { key: 'total_closing', label: 'Cierre', sortable: true, align: 'right' },
  { key: 'total_difference', label: 'Diferencia', sortable: true, align: 'right' }
]

const branchColumns = [
  { key: 'branch', label: 'Sucursal' },
  { key: 'sessions_count', label: 'Sesiones', sortable: true, align: 'right' },
  { key: 'total_opening', label: 'Apertura', sortable: true, align: 'right' },
  { key: 'total_closing', label: 'Cierre', sortable: true, align: 'right' },
  { key: 'total_difference', label: 'Diferencia', sortable: true, align: 'right' }
]

const userColumns = [
  { key: 'user', label: 'Usuario' },
  { key: 'sessions_count', label: 'Sesiones', sortable: true, align: 'right' },
  { key: 'total_opening', label: 'Apertura', sortable: true, align: 'right' },
  { key: 'total_closing', label: 'Cierre', sortable: true, align: 'right' },
  { key: 'total_difference', label: 'Diferencia', sortable: true, align: 'right' }
]

const EXPORT_EXTENSIONS = { csv: 'csv', pdf: 'pdf', excel: 'xls' }

const formatDateInput = date => {
  const year = date.getFullYear()
  const month = String(date.getMonth() + 1).padStart(2, '0')
  const day = String(date.getDate()).padStart(2, '0')
  return `${year}-${month}-${day}`
}

const today = new Date()
const defaultEnd = formatDateInput(today)
const defaultStart = formatDateInput(
  new Date(today.getFullYear(), today.getMonth(), today.getDate() - 29)
)

const draft = ref({ start: defaultStart, end: defaultEnd })
const applied = ref({ start: defaultStart, end: defaultEnd })
const report = ref(null)
const loading = ref(true)
const error = ref('')
const exportingFormat = ref(null)
const exportError = ref('')
const lastExportFormat = ref(null)

const ready = computed(() => report.value !== null && !loading.value && !error.value)

const moneyOrFallback = value => {
  const numeric = Number(value)
  return Number.isFinite(numeric) ? formatCurrency(numeric) : 'N/D'
}

const kpiSessions = computed(() => {
  if (!ready.value) return 'N/D'
  const count = Number(report.value.sessions_count)
  return Number.isFinite(count) ? String(report.value.sessions_count) : 'N/D'
})

const kpiValue = key => (ready.value ? moneyOrFallback(report.value[key]) : 'N/D')

const kpiOpening = computed(() => kpiValue('total_opening_amount'))
const kpiClosing = computed(() => kpiValue('total_closing_amount'))
const kpiDifference = computed(() => kpiValue('total_difference'))
const kpiIncome = computed(() => kpiValue('total_income'))
const kpiExpenses = computed(() => kpiValue('total_expenses'))

const paymentMethods = computed(() => report.value?.payment_methods_analysis ?? [])
const dailySummary = computed(() => report.value?.daily_summary ?? [])
const branchSummary = computed(() => report.value?.summary_by_branch ?? [])
const userSummary = computed(() => report.value?.summary_by_user ?? [])

const notAvailable = 'N/D'

// A missing bucket (older payload, filtered field) must render `N/D` instead of
// `S/ 0.00`: `Number(null)` is 0, so the guard checks for nullish first.
const countOrFallback = value => {
  if (value === null || value === undefined || value === '') return notAvailable
  const numeric = Number(value)
  return Number.isFinite(numeric) ? String(numeric) : notAvailable
}

const currencyOrFallback = value => {
  if (value === null || value === undefined || value === '') return notAvailable
  const numeric = Number(value)
  return Number.isFinite(numeric) ? formatCurrency(numeric) : notAvailable
}

const reconciliationCards = computed(() => {
  const source = ready.value ? report.value?.reconciliation : null

  const buckets = [
    { key: 'cash', label: 'Efectivo', caption: 'neto de devoluciones' },
    { key: 'electronic', label: 'Electrónico', caption: 'medios electrónicos' },
    { key: 'deposits', label: 'Depósitos', caption: 'al banco en el período' }
  ]

  const cards = buckets.map(({ key, label, caption }) => ({
    key,
    label,
    caption,
    count: countOrFallback(source?.[key]?.count),
    value: currencyOrFallback(source?.[key]?.total)
  }))

  cards.push({
    key: 'pending',
    label: 'Pendiente de depósito',
    caption: 'efectivo del período menos depósitos',
    count: null,
    value: currencyOrFallback(source?.pending_deposit)
  })

  return cards
})

const messageOf = (err, fallback) => err?.response?.data?.message || err?.message || fallback

const loadReport = async () => {
  loading.value = true
  error.value = ''
  try {
    const response = await get('/api/cash-register/reports/period', {
      params: { start_date: applied.value.start, end_date: applied.value.end }
    })
    report.value = response.data ?? null
  } catch (err) {
    report.value = null
    error.value = messageOf(err, 'No se pudo cargar el reporte de caja.')
  } finally {
    loading.value = false
  }
}

const applyFilters = () => {
  if (!draft.value.start || !draft.value.end) {
    error.value = 'Selecciona la fecha de inicio y la fecha de fin.'
    return
  }
  if (draft.value.start > draft.value.end) {
    error.value = 'La fecha de inicio no puede ser posterior a la fecha de fin.'
    return
  }
  applied.value = { start: draft.value.start, end: draft.value.end }
  loadReport()
}

const applyPreset = days => {
  const end = new Date()
  const start = new Date(end.getFullYear(), end.getMonth(), end.getDate() - (days - 1))
  draft.value = { start: formatDateInput(start), end: formatDateInput(end) }
  applyFilters()
}

const formatPercentage = value => {
  const numeric = Number(value)
  const safe = Number.isFinite(numeric) ? numeric : 0
  return `${Number(safe.toFixed(1))}%`
}

const formatReportDate = value => formatDate(value) || String(value ?? '')

const saveBlob = (blob, filename) => {
  const url = URL.createObjectURL(blob)
  const link = document.createElement('a')
  link.href = url
  link.setAttribute('download', filename)
  document.body.appendChild(link)
  link.click()
  link.remove()
  URL.revokeObjectURL(url)
}

const exportReport = async format => {
  if (exportingFormat.value) {
    return
  }
  exportingFormat.value = format
  exportError.value = ''
  lastExportFormat.value = format
  try {
    const { blob, filename } = await download(`/api/cash-register/reports/export/${format}`, {
      method: 'POST',
      data: {
        start_date: applied.value.start,
        end_date: applied.value.end,
        report_type: 'period'
      },
      fallbackFilename: `caja-${applied.value.start}-${applied.value.end}.${EXPORT_EXTENSIONS[format]}`
    })
    saveBlob(blob, filename)
  } catch (err) {
    exportError.value = messageOf(err, 'No se pudo exportar el reporte.')
  } finally {
    exportingFormat.value = null
  }
}

const retryExport = () => {
  if (lastExportFormat.value) {
    exportReport(lastExportFormat.value)
  }
}

onMounted(loadReport)
</script>
