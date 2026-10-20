<template>
  <div class="space-y-4 bg-canvas" data-transaction-list>
    <UiFilterBar :columns="4" data-transaction-filters>
      <div class="grid grid-cols-2 gap-4">
        <div>
          <label
            for="transaction-date-from"
            class="mb-1 block text-sm font-medium text-theme-primary"
          >
            Desde
          </label>
          <UiInput
            id="transaction-date-from"
            v-model="filters.date_from"
            type="date"
            data-filter="date_from"
          />
        </div>
        <div>
          <label
            for="transaction-date-to"
            class="mb-1 block text-sm font-medium text-theme-primary"
          >
            Hasta
          </label>
          <UiInput
            id="transaction-date-to"
            v-model="filters.date_to"
            type="date"
            data-filter="date_to"
          />
        </div>
      </div>

      <div>
        <label for="transaction-type" class="mb-1 block text-sm font-medium text-theme-primary">
          Tipo
        </label>
        <UiSelect
          id="transaction-type"
          v-model="filters.type"
          :options="typeOptions"
          placeholder="Todos"
          data-filter="type"
        />
      </div>

      <div>
        <label
          for="transaction-payment-method"
          class="mb-1 block text-sm font-medium text-theme-primary"
        >
          Método de pago
        </label>
        <UiSelect
          id="transaction-payment-method"
          v-model="filters.payment_method_id"
          :options="paymentMethodOptions"
          placeholder="Todos"
          data-filter="payment_method_id"
        />
      </div>

      <div class="flex items-end">
        <UiButton variant="secondary" data-filter-apply @click="applyFilters">
          <template #icon-left>
            <MagnifyingGlassIcon class="w-4 h-4" aria-hidden="true" />
          </template>
          Filtrar
        </UiButton>
      </div>
    </UiFilterBar>

    <UiDataTable
      :data="transactions"
      :columns="columns"
      :loading="loading"
      :pagination="false"
      :searchable="false"
      :show-header="false"
      row-key="id"
      empty-title="Sin transacciones"
      empty-description="No hay transacciones registradas para los filtros aplicados"
      data-transaction-table
    >
      <template #cell-time="{ row }">
        <span class="tabular-nums">{{ formatTime(row.created_at) }}</span>
      </template>

      <template #cell-patient="{ row }">
        <div class="text-sm font-medium text-theme-primary">{{ patientName(row) }}</div>
        <div class="text-sm text-theme-secondary">
          {{ row.patient?.document_number || row.patient?.dni || '—' }}
        </div>
      </template>

      <template #cell-description="{ row }">
        <div class="text-sm text-theme-primary">{{ row.description }}</div>
        <div v-if="row.reference_number" class="text-sm text-theme-secondary">
          Ref: {{ row.reference_number }}
        </div>
      </template>

      <template #cell-payment_method="{ row }">
        {{ row.payment_method?.name || '—' }}
      </template>

      <template #cell-amount="{ row }">
        <div
          class="text-right text-sm font-medium tabular-nums"
          :class="isIncomeType(row.type) ? 'text-systemGreen-600' : 'text-systemRed-600'"
        >
          {{ isIncomeType(row.type) ? '+' : '-' }}{{ formatCurrency(row.amount) }}
        </div>
        <div v-if="hasDiscount(row)" class="text-right text-xs text-theme-secondary tabular-nums">
          Descuento: -{{ formatCurrency(row.discount_amount) }}
        </div>
      </template>

      <template #cell-status="{ row }">
        <UiStatusBadge :variant="getStatusVariant(row.status)" :label="getStatusText(row.status)" />
      </template>

      <template #actions="{ row }">
        <UiButton
          variant="ghost"
          size="sm"
          data-row-action="receipt"
          @click.stop="emitReceipt(row)"
        >
          <template #icon-left>
            <DocumentTextIcon class="w-4 h-4" aria-hidden="true" />
          </template>
          Comprobante
        </UiButton>
        <UiButton
          v-if="canVoid && row.status === 'completed'"
          variant="ghost"
          size="sm"
          class="text-systemRed-700"
          data-row-action="void"
          @click.stop="emitVoid(row)"
        >
          <template #icon-left>
            <XMarkIcon class="w-4 h-4" aria-hidden="true" />
          </template>
          Anular
        </UiButton>
      </template>
    </UiDataTable>

    <UiPagination
      v-if="pagination && pagination.last_page > 1"
      :current-page="pagination.current_page"
      :total-pages="pagination.last_page"
      :total="pagination.total"
      :per-page="pagination.per_page"
      @page-change="loadPage"
    />
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useApi } from '@/composables/useApi'
import { formatCurrency } from '@/composables/useFormatters'
import UiButton from '@/components/ui/Button.vue'
import UiDataTable from '@/components/ui/DataTable.vue'
import UiFilterBar from '@/components/ui/FilterBar.vue'
import UiInput from '@/components/ui/Input.vue'
import UiPagination from '@/components/ui/Pagination.vue'
import UiSelect from '@/components/ui/Select.vue'
import UiStatusBadge from '@/components/ui/StatusBadge.vue'
import { DocumentTextIcon, MagnifyingGlassIcon, XMarkIcon } from '@heroicons/vue/24/outline'

defineProps({
  transactions: {
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
  canVoid: {
    type: Boolean,
    default: false
  }
})

const emit = defineEmits(['refresh', 'void', 'receipt'])

const { get } = useApi()

const filters = ref({
  date_from: '',
  date_to: '',
  type: '',
  payment_method_id: ''
})

const paymentMethods = ref([])

const typeOptions = [
  { label: 'Todos', value: '' },
  { label: 'Ingreso', value: 'payment' },
  { label: 'Egreso', value: 'refund' }
]

const paymentMethodOptions = computed(() => [
  { label: 'Todos', value: '' },
  ...paymentMethods.value.map(method => ({ label: method.name, value: method.id }))
])

const columns = [
  { key: 'time', label: 'Hora' },
  { key: 'patient', label: 'Paciente' },
  { key: 'description', label: 'Descripción' },
  { key: 'payment_method', label: 'Método' },
  { key: 'amount', label: 'Monto' },
  { key: 'status', label: 'Estado' }
]

const loadPaymentMethods = async () => {
  try {
    const response = await get('/api/payment-methods/active')
    paymentMethods.value = response.data || []
  } catch {
    paymentMethods.value = []
  }
}

const appliedFilters = () => {
  const applied = {}
  if (filters.value.date_from) applied.date_from = filters.value.date_from
  if (filters.value.date_to) applied.date_to = filters.value.date_to
  if (filters.value.type) applied.type = filters.value.type
  if (filters.value.payment_method_id !== '' && filters.value.payment_method_id !== null) {
    applied.payment_method_id = filters.value.payment_method_id
  }
  return applied
}

const applyFilters = () => {
  emit('refresh', appliedFilters())
}

const loadPage = page => {
  emit('refresh', { ...appliedFilters(), page })
}

const emitVoid = row => {
  emit('void', row)
}

const emitReceipt = row => {
  emit('receipt', row)
}

const patientName = row => {
  const name = [row.patient?.name, row.patient?.last_name].filter(Boolean).join(' ')
  return name || '—'
}

const hasDiscount = row => Number(row.discount_amount) > 0

const isIncomeType = type => type === 'payment'

const formatTime = dateTime => {
  return new Date(dateTime).toLocaleTimeString('es-PE', {
    hour: '2-digit',
    minute: '2-digit'
  })
}

const getStatusText = status => {
  const texts = {
    pending: 'Pendiente',
    completed: 'Completada',
    failed: 'Fallida',
    cancelled: 'Cancelada',
    voided: 'Anulada'
  }
  return texts[status] || status
}

const getStatusVariant = status => {
  const variants = {
    pending: 'warning',
    completed: 'success',
    failed: 'error',
    cancelled: 'neutral',
    voided: 'error'
  }
  return variants[status] || 'neutral'
}

onMounted(() => {
  loadPaymentMethods()
})
</script>
