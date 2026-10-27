<template>
  <div class="space-y-6 bg-canvas" data-movement-list>
    <section aria-label="Totales de movimientos" class="grid grid-cols-1 gap-4 md:grid-cols-3">
      <UiCard
        v-for="total in totals"
        :key="total.key"
        variant="glass"
        padding="sm"
        :data-movement-total="total.key"
        :style="{ boxShadow: 'var(--elevation-2)', borderColor: 'var(--color-hairline)' }"
      >
        <p class="text-xs font-medium uppercase text-theme-secondary">{{ total.label }}</p>
        <p
          class="mt-2 text-2xl font-bold leading-none text-label tabular-nums"
          data-movement-total-value
        >
          {{ total.value }}
        </p>
        <p class="mt-1 text-xs text-theme-secondary">{{ total.caption }}</p>
      </UiCard>
    </section>

    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
      <p class="text-xs font-medium uppercase tracking-wide text-theme-secondary">
        Movimientos del turno
      </p>
      <UiButton
        variant="secondary"
        size="sm"
        :loading="loading"
        data-movement-refresh
        @click="reload"
      >
        <template #icon-left>
          <ArrowPathIcon class="h-4 w-4" aria-hidden="true" />
        </template>
        Actualizar
      </UiButton>
    </div>

    <UiDataTable
      :data="movements"
      :columns="columns"
      :loading="loading"
      :pagination="false"
      searchable
      empty-title="Sin movimientos"
      empty-description="Los movimientos del turno aparecerán aquí."
      row-key="id"
      data-movement-table
    >
      <template #cell-created_at="{ value }">
        {{ formatTime(value) || '—' }}
      </template>

      <template #cell-type="{ row }">
        <UiStatusBadge :variant="typeVariant(row.type)" :label="typeLabel(row.type)" />
      </template>

      <template #cell-description="{ row }">
        <p class="text-sm text-theme-primary">{{ row.description }}</p>
        <p v-if="row.notes" class="text-xs text-theme-secondary">{{ row.notes }}</p>
      </template>

      <template #cell-reference="{ value }">
        {{ value || '—' }}
      </template>

      <template #cell-amount="{ row }">
        <span
          class="tabular-nums"
          :class="amountTone(row.type)"
          :aria-label="`${amountPrefix(row.type)}${formatCurrency(row.amount)} soles`"
        >
          {{ amountPrefix(row.type) }}{{ formatCurrency(row.amount) }}
        </span>
      </template>

      <template #cell-created_by="{ row }">
        {{ row.created_by?.name || 'Sistema' }}
      </template>
    </UiDataTable>

    <div v-if="pagination && pagination.last_page > 1" data-movement-pagination>
      <UiPagination
        :current-page="pagination.current_page"
        :total-pages="pagination.last_page"
        :total="pagination.total"
        :per-page="pagination.per_page"
        @page-change="loadPage"
      />
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import UiButton from '@/components/ui/Button.vue'
import UiCard from '@/components/ui/Card.vue'
import UiDataTable from '@/components/ui/DataTable.vue'
import UiPagination from '@/components/ui/Pagination.vue'
import UiStatusBadge from '@/components/ui/StatusBadge.vue'
import { formatCurrency, formatTime } from '@/composables/useFormatters'
import { ArrowPathIcon } from '@heroicons/vue/24/outline'

const props = defineProps({
  movements: {
    type: Array,
    default: () => []
  },
  loading: {
    type: Boolean,
    default: false
  },
  pagination: {
    type: Object,
    default: null
  },
  summary: {
    type: Object,
    default: null
  }
})

const emit = defineEmits(['refresh'])

const columns = [
  { key: 'created_at', label: 'Hora' },
  { key: 'type', label: 'Tipo' },
  { key: 'description', label: 'Descripción' },
  { key: 'reference', label: 'Referencia' },
  { key: 'amount', label: 'Monto', align: 'right' },
  { key: 'created_by', label: 'Usuario' }
]

const TYPE_LABELS = {
  income: 'Ingreso',
  expense: 'Egreso',
  opening: 'Apertura',
  closing: 'Cierre',
  withdrawal: 'Retiro',
  deposit: 'Depósito',
  adjustment: 'Ajuste'
}

const TYPE_VARIANTS = {
  income: 'success',
  expense: 'error',
  opening: 'info',
  closing: 'info',
  withdrawal: 'warning',
  deposit: 'success',
  adjustment: 'neutral'
}

const totals = computed(() => [
  {
    key: 'cash_in',
    label: 'Ingresos de efectivo',
    value: props.summary ? formatCurrency(props.summary.cash_in) : 'N/D',
    caption: 'Cobros y depósitos'
  },
  {
    key: 'cash_out',
    label: 'Egresos de efectivo',
    value: props.summary ? formatCurrency(props.summary.cash_out) : 'N/D',
    caption: 'Gastos y retiros'
  },
  {
    key: 'net_flow',
    label: 'Flujo neto',
    value: props.summary ? formatCurrency(props.summary.net_flow) : 'N/D',
    caption: 'Entradas menos salidas'
  }
])

const reload = () => emit('refresh')

const loadPage = page => emit('refresh', { page })

const typeLabel = type => TYPE_LABELS[type] || type

const typeVariant = type => TYPE_VARIANTS[type] || 'neutral'

const isInflow = type => ['income', 'opening', 'deposit'].includes(type)

const isOutflow = type => ['expense', 'closing', 'withdrawal'].includes(type)

const amountTone = type => {
  if (isInflow(type)) return 'text-systemGreen-600'
  if (isOutflow(type)) return 'text-systemRed-600'
  return 'text-theme-secondary'
}

const amountPrefix = type => {
  if (isInflow(type)) return '+'
  if (isOutflow(type)) return '-'
  return ''
}
</script>
