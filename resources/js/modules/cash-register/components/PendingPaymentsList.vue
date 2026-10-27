<template>
  <div class="bg-canvas" data-pending-payments-list>
    <UiFilterBar :columns="4" title="Filtros de cobros">
      <UiInput
        v-model="filters.search"
        type="search"
        label="Buscar paciente"
        placeholder="Nombre o documento"
        data-filter="search"
      />
      <UiInput v-model="filters.date_from" type="date" label="Desde" data-filter="date-from" />
      <UiInput v-model="filters.date_to" type="date" label="Hasta" data-filter="date-to" />
      <div class="flex items-end">
        <UiButton variant="secondary" class="w-full" data-filter-clear @click="clearFilters">
          Limpiar filtros
        </UiButton>
      </div>
    </UiFilterBar>

    <div
      v-if="error"
      role="alert"
      class="mt-4 rounded-ios border border-hairline bg-theme-surface-elevated p-6"
      data-pending-payments-error
    >
      <div class="flex flex-col gap-4 sm:flex-row sm:items-start">
        <ExclamationTriangleIcon class="h-5 w-5 shrink-0 text-systemRed-600" aria-hidden="true" />
        <div class="flex-1">
          <p class="text-sm font-medium text-label">No se pudieron cargar los cobros pendientes</p>
          <p class="mt-1 text-sm text-theme-secondary" data-pending-payments-error-message>
            {{ error }}
          </p>
        </div>
        <UiButton
          variant="secondary"
          size="sm"
          data-pending-payments-retry
          @click="loadPendingPayments"
        >
          <template #icon-left>
            <ArrowPathIcon class="h-4 w-4" aria-hidden="true" />
          </template>
          Reintentar
        </UiButton>
      </div>
    </div>

    <div v-else class="mt-4">
      <UiDataTable
        :data="pageRows"
        :columns="columns"
        :loading="loading"
        :pagination="false"
        :searchable="false"
        :show-header="false"
        row-key="id"
        empty-title="No hay cobros pendientes"
        empty-description="Las citas completadas ya tienen su cobro registrado."
        data-pending-payments-table
      >
        <template #cell-patient="{ row }">
          <div class="min-w-0">
            <p class="truncate text-sm font-medium text-label">{{ row.patient?.name }}</p>
            <p class="truncate text-xs text-theme-secondary">{{ row.patient?.document_number }}</p>
          </div>
        </template>

        <template #cell-appointment="{ row }">
          <p class="text-sm text-theme-primary">
            {{ formatDate(row.appointment?.date, { day: 'numeric', month: 'short' }) || '—' }}
          </p>
          <p class="text-xs text-theme-secondary">
            {{ row.appointment?.appointment_type?.name }}
          </p>
          <p
            v-if="row.appointment?.professional?.name"
            class="text-xs text-theme-secondary"
            data-pending-professional
          >
            {{ row.appointment.professional.name }}
          </p>
        </template>

        <template #cell-concept="{ row }">
          <p class="text-sm text-theme-primary">{{ row.concept }}</p>
        </template>

        <template #cell-amount="{ row }">
          <span class="text-sm font-medium tabular-nums text-label" data-pending-amount>
            {{ formatCurrency(row.amount) }}
          </span>
        </template>

        <template #actions="{ row }">
          <UiButton
            variant="primary"
            size="sm"
            :data-pay-pending="row.id"
            @click="handlePayment(row)"
          >
            <template #icon-left>
              <BanknotesIcon class="h-4 w-4" aria-hidden="true" />
            </template>
            Cobrar
          </UiButton>
        </template>
      </UiDataTable>

      <div v-if="totalPages > 1" class="mt-4" data-pending-payments-pagination>
        <UiPagination
          :current-page="currentPage"
          :total-pages="totalPages"
          :total="total"
          :per-page="perPage"
          @page-change="handlePageChange"
        />
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, watch, onMounted, onBeforeUnmount } from 'vue'
import { ArrowPathIcon, BanknotesIcon, ExclamationTriangleIcon } from '@heroicons/vue/24/outline'
import UiButton from '@/components/ui/Button.vue'
import UiDataTable from '@/components/ui/DataTable.vue'
import UiFilterBar from '@/components/ui/FilterBar.vue'
import UiInput from '@/components/ui/Input.vue'
import UiPagination from '@/components/ui/Pagination.vue'
import { useApi } from '@/composables/useApi'
import { formatCurrency, formatDate } from '@/composables/useFormatters'

const emit = defineEmits(['payment', 'paymentsLoaded'])

const { get } = useApi()

const columns = [
  { key: 'patient', label: 'Paciente' },
  { key: 'appointment', label: 'Cita' },
  { key: 'concept', label: 'Concepto' },
  { key: 'amount', label: 'Saldo' }
]

const CASH_PROFILE_MESSAGE =
  'Esta sección requiere un perfil de caja para consultar los cobros pendientes.'

const payments = ref([])
const meta = ref(null)
const loading = ref(false)
const error = ref('')
const currentPage = ref(1)
const perPage = ref(10)

const filters = ref({
  search: '',
  date_from: '',
  date_to: ''
})

let filterTimer = null

const serverPaginated = computed(() => Number(meta.value?.last_page ?? 0) > 0)

const total = computed(() => Number(meta.value?.total ?? payments.value.length))

const totalPages = computed(() => {
  if (serverPaginated.value) {
    return Number(meta.value.last_page)
  }

  return Math.max(1, Math.ceil(payments.value.length / perPage.value))
})

const pageRows = computed(() => {
  if (serverPaginated.value) {
    return payments.value
  }

  const start = (currentPage.value - 1) * perPage.value
  return payments.value.slice(start, start + perPage.value)
})

const resolveErrorMessage = err => {
  const status = err?.response?.status
  if (status === 401 || status === 403) {
    return CASH_PROFILE_MESSAGE
  }

  return (
    err?.response?.data?.message ||
    err?.response?.data?.meta?.message ||
    'No se pudieron cargar los cobros pendientes. Intenta nuevamente.'
  )
}

const loadPendingPayments = async () => {
  loading.value = true
  error.value = ''

  try {
    const response = await get('/api/cash-register/pending-payments', {
      params: {
        search: filters.value.search || undefined,
        date_from: filters.value.date_from || undefined,
        date_to: filters.value.date_to || undefined,
        page: currentPage.value,
        per_page: perPage.value
      }
    })

    payments.value = response?.data || []
    meta.value = response?.meta || null

    if (serverPaginated.value) {
      currentPage.value = Number(meta.value.current_page || currentPage.value)
    }

    emit('paymentsLoaded', payments.value)
  } catch (err) {
    payments.value = []
    meta.value = null
    error.value = resolveErrorMessage(err)
    emit('paymentsLoaded', [])
  } finally {
    loading.value = false
  }
}

const handlePageChange = page => {
  currentPage.value = page
  loadPendingPayments()
}

const handlePayment = payment => {
  emit('payment', payment)
}

const clearFilters = () => {
  filters.value = {
    search: '',
    date_from: '',
    date_to: ''
  }
}

watch(
  filters,
  () => {
    currentPage.value = 1

    if (filterTimer) {
      clearTimeout(filterTimer)
    }

    filterTimer = setTimeout(() => {
      filterTimer = null
      loadPendingPayments()
    }, 300)
  },
  { deep: true }
)

onMounted(loadPendingPayments)

onBeforeUnmount(() => {
  if (filterTimer) {
    clearTimeout(filterTimer)
  }
})
</script>
