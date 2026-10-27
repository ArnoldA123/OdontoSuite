<template>
  <div class="space-y-6" data-consolidated>
    <UiCard variant="glass" data-consolidated-summary>
      <template #header>
        <div class="flex flex-wrap items-baseline justify-between gap-2">
          <h2 class="text-lg font-semibold text-theme-primary">Consolidado por Sede</h2>
          <p class="text-xs text-theme-secondary" data-consolidated-period>
            {{ report.period.label }}
          </p>
        </div>
      </template>
      <dl class="grid grid-cols-1 gap-4 md:grid-cols-3">
        <div>
          <dt class="text-xs font-medium uppercase text-theme-secondary">Ingresos netos</dt>
          <dd
            class="mt-1 text-xl font-semibold text-label tabular-nums"
            data-consolidated-summary="net"
          >
            {{ formatCurrency(report.summary.net) }}
          </dd>
        </div>
        <div>
          <dt class="text-xs font-medium uppercase text-theme-secondary">Devoluciones</dt>
          <dd
            class="mt-1 text-xl font-semibold text-label tabular-nums"
            data-consolidated-summary="refunds"
          >
            {{ formatCurrency(report.summary.refunds) }}
          </dd>
        </div>
        <div>
          <dt class="text-xs font-medium uppercase text-theme-secondary">Atenciones pagadas</dt>
          <dd
            class="mt-1 text-xl font-semibold text-label tabular-nums"
            data-consolidated-summary="paid-appointments"
          >
            {{ formatNumber(report.summary.paid_appointments) }}
          </dd>
        </div>
      </dl>
    </UiCard>

    <UiCard variant="glass" data-consolidated-by-branch>
      <template #header>
        <h2 class="text-lg font-semibold text-theme-primary">Ingresos por Sede</h2>
      </template>
      <UiDataTable
        :data="branchRows"
        :columns="branchColumns"
        :searchable="false"
        :pagination="false"
        row-key="id"
        empty-title="Sin ingresos por sede"
        empty-description="No hay movimientos atribuibles a una sede en el período."
        data-consolidated-branch-table
      />
    </UiCard>

    <UiCard variant="glass" data-consolidated-by-professional>
      <template #header>
        <h2 class="text-lg font-semibold text-theme-primary">Ingresos por Profesional</h2>
      </template>
      <UiDataTable
        :data="professionalRows"
        :columns="professionalColumns"
        :searchable="false"
        :pagination="false"
        row-key="id"
        empty-title="Sin ingresos por profesional"
        empty-description="No hay movimientos atribuibles a un profesional en el período."
        data-consolidated-professional-table
      />
    </UiCard>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import UiCard from '../../components/ui/Card.vue'
import UiDataTable from '../../components/ui/DataTable.vue'
import { formatCurrency, formatNumber } from '../../composables/useFormatters'

const props = defineProps({
  report: {
    type: Object,
    required: true
  }
})

const moneyColumns = [
  { key: 'net', label: 'Ingresos netos', align: 'right', formatter: formatCurrency },
  { key: 'refunds', label: 'Devoluciones', align: 'right', formatter: formatCurrency },
  { key: 'paid_appointments', label: 'Atenciones pagadas', align: 'right' }
]

const branchColumns = [{ key: 'branch', label: 'Sede' }, ...moneyColumns]

const professionalColumns = [{ key: 'professional', label: 'Profesional' }, ...moneyColumns]

const branchRows = computed(() =>
  props.report.by_branch.map(row => ({
    ...row,
    id: row.branch_id ?? 'sin-sede'
  }))
)

const professionalRows = computed(() =>
  props.report.by_professional.map(row => ({
    ...row,
    id: row.professional_id ?? 'sin-cita'
  }))
)
</script>
