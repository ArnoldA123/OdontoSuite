<template>
  <div class="session-list space-y-6 bg-canvas" data-session-list>
    <UiFilterBar :columns="filterColumns" title="Filtros" data-session-filters>
      <UiSelect
        v-model="filters.status"
        :options="statusOptions"
        label="Estado"
        placeholder="Todas"
        data-session-filter="status"
        @update:model-value="applyFilters"
      />

      <UiInput
        v-model="filters.date_from"
        type="date"
        label="Fecha desde"
        data-session-filter="date-from"
        @update:model-value="applyFilters"
      />

      <UiInput
        v-model="filters.date_to"
        type="date"
        label="Fecha hasta"
        data-session-filter="date-to"
        @update:model-value="applyFilters"
      />

      <UiSelect
        v-if="userOptions.length > 0"
        v-model="filters.user_id"
        :options="userOptions"
        label="Usuario"
        placeholder="Todos"
        data-session-filter="user"
        @update:model-value="applyFilters"
      />
    </UiFilterBar>

    <UiDataTable
      :data="sessions"
      :columns="columns"
      :loading="loading"
      :pagination="false"
      :searchable="false"
      :show-header="false"
      row-key="id"
      empty-title="Sin sesiones registradas"
      empty-description="Ajusta los filtros o abre una caja para iniciar el turno."
      data-session-table
    >
      <template #cell-session="{ row }">
        <div class="text-sm font-medium text-theme-primary tabular-nums">#{{ row.id }}</div>
        <div class="text-xs text-theme-secondary">{{ formatDate(row.opened_at) || '—' }}</div>
      </template>

      <template #cell-user="{ row }">
        <span class="text-sm text-theme-primary">{{ row.user?.name || 'Sin usuario' }}</span>
      </template>

      <template #cell-branch="{ row }">
        <span class="text-sm text-theme-primary">{{ row.branch?.name || 'Sin sucursal' }}</span>
      </template>

      <template #cell-opened="{ row }">
        <div class="text-sm text-theme-primary">{{ formatTime(row.opened_at) || '—' }}</div>
        <div class="text-sm text-theme-secondary tabular-nums">
          {{ formatPENLabel(row.opening_amount) }}
        </div>
      </template>

      <template #cell-closed="{ row }">
        <div class="text-sm text-theme-primary">
          {{ row.closed_at ? formatTime(row.closed_at) || '—' : '—' }}
        </div>
        <div class="text-sm text-theme-secondary tabular-nums">
          {{ formatAmount(row.closing_amount) }}
        </div>
      </template>

      <template #cell-difference="{ row }">
        <span
          v-if="hasAmount(row.difference_amount)"
          class="text-sm font-medium tabular-nums"
          :class="differenceClass(row.difference_amount)"
        >
          {{ formatDifference(row.difference_amount) }}
        </span>
        <span v-else class="text-sm text-theme-secondary">—</span>
      </template>

      <template #cell-status="{ row }">
        <UiStatusBadge
          :variant="row.status === 'open' ? 'success' : 'neutral'"
          :label="getStatusText(row.status)"
        />
      </template>
    </UiDataTable>

    <div v-if="pagination && totalPages > 1" data-session-pagination>
      <UiPagination
        :current-page="currentPage"
        :total-pages="totalPages"
        :total="totalItems"
        :per-page="perPage"
        @page-change="onPageChange"
      />
    </div>
  </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue'
import UiDataTable from '@/components/ui/DataTable.vue'
import UiFilterBar from '@/components/ui/FilterBar.vue'
import UiInput from '@/components/ui/Input.vue'
import UiPagination from '@/components/ui/Pagination.vue'
import UiSelect from '@/components/ui/Select.vue'
import UiStatusBadge from '@/components/ui/StatusBadge.vue'
import { useApi } from '@/composables/useApi'
import { formatDate, formatPENLabel, formatTime } from '@/composables/useFormatters'

const props = defineProps({
  sessions: {
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
  }
})

const emit = defineEmits(['refresh'])

const { get } = useApi()

const filters = reactive({
  status: '',
  date_from: '',
  date_to: '',
  user_id: ''
})

const userOptions = ref([])

const statusOptions = [
  { value: '', label: 'Todas' },
  { value: 'open', label: 'Abierta' },
  { value: 'closed', label: 'Cerrada' }
]

const columns = [
  { key: 'session', label: 'Sesión' },
  { key: 'user', label: 'Usuario' },
  { key: 'branch', label: 'Sucursal' },
  { key: 'opened', label: 'Apertura', align: 'right' },
  { key: 'closed', label: 'Cierre', align: 'right' },
  { key: 'difference', label: 'Diferencia', align: 'right' },
  { key: 'status', label: 'Estado' }
]

const filterColumns = computed(() => (userOptions.value.length > 0 ? 4 : 3))

const currentPage = computed(() => Number(props.pagination?.current_page) || 1)
const totalPages = computed(() => Number(props.pagination?.last_page) || 1)
const totalItems = computed(() => Number(props.pagination?.total) || props.sessions.length)
const perPage = computed(() => Number(props.pagination?.per_page) || props.sessions.length || 15)

const activeFilters = computed(() => {
  const payload = {}

  if (filters.status) payload.status = filters.status
  if (filters.date_from) payload.date_from = filters.date_from
  if (filters.date_to) payload.date_to = filters.date_to
  if (filters.user_id) payload.user_id = filters.user_id

  return payload
})

const loadUsers = async () => {
  try {
    const response = await get('/api/users')
    userOptions.value = (response?.data || []).map(user => ({
      value: String(user.id),
      label: user.name
    }))
  } catch {
    userOptions.value = []
  }
}

const applyFilters = () => {
  emit('refresh', { ...activeFilters.value, page: 1 })
}

const onPageChange = page => {
  emit('refresh', { ...activeFilters.value, page })
}

const hasAmount = value => value !== null && value !== undefined

const formatAmount = value => (hasAmount(value) ? formatPENLabel(value) : '—')

const formatDifference = amount => {
  if (amount === 0) return 'Conforme'
  return `${amount > 0 ? '+' : ''}${formatPENLabel(amount)}`
}

const differenceClass = amount => {
  if (amount === 0) return 'text-systemGreen-600'
  return amount > 0 ? 'text-systemBlue-600' : 'text-systemRed-600'
}

const getStatusText = status => {
  const texts = {
    open: 'Abierta',
    closed: 'Cerrada'
  }
  return texts[status] || status
}

onMounted(loadUsers)
</script>
