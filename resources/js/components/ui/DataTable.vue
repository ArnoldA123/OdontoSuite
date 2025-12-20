<template>
  <div class="data-table-container">
    <!-- Header with search and actions -->
    <div v-if="showHeader" class="data-table-header">
      <div class="flex items-center justify-between mb-4">
        <!-- Search -->
        <div v-if="searchable" class="flex-1 max-w-md">
          <UiInput
            v-model="searchQuery"
            placeholder="Buscar..."
            type="search"
            size="sm"
            class="w-full"
          >
            <template #prefix>
              <svg class="w-4 h-4 text-theme-secondary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
              </svg>
            </template>
          </UiInput>
        </div>

        <!-- Actions -->
        <div class="flex items-center gap-2 ml-4">
          <slot name="actions" />
        </div>
      </div>
    </div>

    <!-- Table -->
    <div class="data-table-wrapper">
      <table class="data-table">
        <!-- Header -->
        <thead class="data-table-header">
          <tr>
            <th v-if="selectable" class="data-table-cell-select">
              <UiInput
                v-model="selectAll"
                type="checkbox"
                :indeterminate="isIndeterminate"
                @change="toggleSelectAll"
              />
            </th>
            <th
              v-for="column in columns"
              :key="column.key"
              :class="getHeaderCellClasses(column)"
              @click="handleSort(column)"
            >
              <div class="flex items-center gap-2">
                <span>{{ column.label }}</span>
                <svg
                  v-if="column.sortable"
                  class="w-4 h-4 transition-transform duration-200"
                  :class="getSortIconClasses(column)"
                  fill="none"
                  stroke="currentColor"
                  viewBox="0 0 24 24"
                >
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16V4m0 0L3 8m4-4l4 4m6 0v12m0 0l4-4m-4 4l-4-4" />
                </svg>
              </div>
            </th>
            <th v-if="$slots.actions" class="data-table-cell-actions">Acciones</th>
          </tr>
        </thead>

        <!-- Body -->
        <tbody class="data-table-body">
          <!-- Loading state -->
          <tr v-if="loading">
            <td :colspan="totalColumns" class="data-table-cell-loading">
              <div class="flex items-center justify-center py-8">
                <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-accent"></div>
                <span class="ml-3 text-theme-secondary">Cargando...</span>
              </div>
            </td>
          </tr>

          <!-- Empty state -->
          <tr v-else-if="filteredData.length === 0">
            <td :colspan="totalColumns" class="data-table-cell-empty">
              <UiEmptyState
                :title="emptyTitle"
                :description="emptyDescription"
                :icon="emptyIcon"
              >
                <template #action>
                  <slot name="empty-action" />
                </template>
              </UiEmptyState>
            </td>
          </tr>

          <!-- Data rows -->
          <tr
            v-else
            v-for="(row, index) in paginatedData"
            :key="getRowKey(row, index)"
            :class="getRowClasses(row, index)"
            @click="handleRowClick(row, index)"
          >
            <!-- Selection checkbox -->
            <td v-if="selectable" class="data-table-cell-select">
              <UiInput
                v-model="selectedRows"
                :value="getRowKey(row, index)"
                type="checkbox"
                @change="handleRowSelect(row, index)"
              />
            </td>

            <!-- Data cells -->
            <td
              v-for="column in columns"
              :key="column.key"
              :class="getCellClasses(column)"
            >
              <slot
                :name="`cell-${column.key}`"
                :row="row"
                :value="getCellValue(row, column)"
                :column="column"
              >
                {{ formatCellValue(row, column) }}
              </slot>
            </td>

            <!-- Actions cell -->
            <td v-if="$slots.actions" class="data-table-cell-actions">
              <div class="flex items-center gap-2">
                <slot name="actions" :row="row" :index="index" />
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Pagination -->
    <div v-if="pagination && totalPages > 1" class="data-table-pagination">
      <div class="flex items-center justify-between">
        <div class="text-sm text-theme-secondary">
          Mostrando {{ startItem }}-{{ endItem }} de {{ totalItems }} elementos
        </div>

        <div class="flex items-center gap-2">
          <UiButton
            variant="ghost"
            size="sm"
            :disabled="currentPage === 1"
            @click="goToPage(currentPage - 1)"
          >
            <template #icon-left>
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
              </svg>
            </template>
            Anterior
          </UiButton>

          <div class="flex items-center gap-1">
            <UiButton
              v-for="page in visiblePages"
              :key="page"
              :variant="page === currentPage ? 'primary' : 'ghost'"
              size="sm"
              @click="goToPage(page)"
            >
              {{ page }}
            </UiButton>
          </div>

          <UiButton
            variant="ghost"
            size="sm"
            :disabled="currentPage === totalPages"
            @click="goToPage(currentPage + 1)"
          >
            Siguiente
            <template #icon-right>
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
              </svg>
            </template>
          </UiButton>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, watch } from 'vue'
import UiInput from './Input.vue'
import UiButton from './Button.vue'
import UiEmptyState from './EmptyState.vue'

const props = defineProps({
  data: {
    type: Array,
    default: () => []
  },
  columns: {
    type: Array,
    required: true
  },
  loading: {
    type: Boolean,
    default: false
  },
  selectable: {
    type: Boolean,
    default: false
  },
  searchable: {
    type: Boolean,
    default: true
  },
  pagination: {
    type: Boolean,
    default: true
  },
  pageSize: {
    type: Number,
    default: 10
  },
  showHeader: {
    type: Boolean,
    default: true
  },
  emptyTitle: {
    type: String,
    default: 'No hay datos'
  },
  emptyDescription: {
    type: String,
    default: 'No se encontraron elementos para mostrar'
  },
  emptyIcon: {
    type: String,
    default: 'inbox'
  },
  rowKey: {
    type: String,
    default: 'id'
  },
  clickable: {
    type: Boolean,
    default: false
  }
})

const emit = defineEmits(['row-click', 'row-select', 'sort', 'page-change'])

// State
const searchQuery = ref('')
const currentPage = ref(1)
const sortField = ref('')
const sortDirection = ref('asc')
const selectedRows = ref([])
const selectAll = ref(false)

// Computed
const totalColumns = computed(() => {
  let count = props.columns.length
  if (props.selectable) count++
  if (props.$slots?.actions) count++
  return count
})

const filteredData = computed(() => {
  let data = [...props.data]

  // Search filter
  if (searchQuery.value && props.searchable) {
    const query = searchQuery.value.toLowerCase()
    data = data.filter(row => {
      return props.columns.some(column => {
        const value = getCellValue(row, column)
        return String(value).toLowerCase().includes(query)
      })
    })
  }

  // Sort
  if (sortField.value) {
    data.sort((a, b) => {
      const aVal = getCellValue(a, { key: sortField.value })
      const bVal = getCellValue(b, { key: sortField.value })

      if (aVal < bVal) return sortDirection.value === 'asc' ? -1 : 1
      if (aVal > bVal) return sortDirection.value === 'asc' ? 1 : -1
      return 0
    })
  }

  return data
})

const totalItems = computed(() => filteredData.value.length)
const totalPages = computed(() => Math.ceil(totalItems.value / props.pageSize))

const paginatedData = computed(() => {
  if (!props.pagination) return filteredData.value

  const start = (currentPage.value - 1) * props.pageSize
  const end = start + props.pageSize
  return filteredData.value.slice(start, end)
})

const startItem = computed(() => (currentPage.value - 1) * props.pageSize + 1)
const endItem = computed(() => Math.min(currentPage.value * props.pageSize, totalItems.value))

const visiblePages = computed(() => {
  const pages = []
  const total = totalPages.value
  const current = currentPage.value

  if (total <= 7) {
    for (let i = 1; i <= total; i++) {
      pages.push(i)
    }
  } else {
    if (current <= 4) {
      for (let i = 1; i <= 5; i++) pages.push(i)
      pages.push('...')
      pages.push(total)
    } else if (current >= total - 3) {
      pages.push(1)
      pages.push('...')
      for (let i = total - 4; i <= total; i++) pages.push(i)
    } else {
      pages.push(1)
      pages.push('...')
      for (let i = current - 1; i <= current + 1; i++) pages.push(i)
      pages.push('...')
      pages.push(total)
    }
  }

  return pages
})

const isIndeterminate = computed(() => {
  const selectedCount = selectedRows.value.length
  return selectedCount > 0 && selectedCount < filteredData.value.length
})

// Methods
const getRowKey = (row, index) => {
  return row[props.rowKey] || index
}

const getCellValue = (row, column) => {
  if (typeof column.key === 'function') {
    return column.key(row)
  }
  return row[column.key]
}

const formatCellValue = (row, column) => {
  const value = getCellValue(row, column)
  if (column.formatter && typeof column.formatter === 'function') {
    return column.formatter(value, row)
  }
  return value
}

const getHeaderCellClasses = (column) => [
  'data-table-header-cell',
  column.sortable ? 'cursor-pointer hover:bg-theme-surface' : '',
  column.align ? `text-${column.align}` : 'text-left'
]

const getSortIconClasses = (column) => {
  if (sortField.value !== column.key) return 'text-theme-secondary'
  return sortDirection.value === 'asc' ? 'text-accent rotate-180' : 'text-accent'
}

const getRowClasses = (row, index) => [
  'data-table-row',
  props.clickable ? 'cursor-pointer hover:bg-theme-surface' : '',
  index % 2 === 0 ? 'bg-theme-surface-elevated' : 'bg-theme-surface'
]

const getCellClasses = (column) => [
  'data-table-cell',
  column.align ? `text-${column.align}` : 'text-left'
]

const handleSort = (column) => {
  if (!column.sortable) return

  if (sortField.value === column.key) {
    sortDirection.value = sortDirection.value === 'asc' ? 'desc' : 'asc'
  } else {
    sortField.value = column.key
    sortDirection.value = 'asc'
  }

  emit('sort', { field: sortField.value, direction: sortDirection.value })
}

const handleRowClick = (row, index) => {
  if (props.clickable) {
    emit('row-click', { row, index })
  }
}

const handleRowSelect = (row, index) => {
  const key = getRowKey(row, index)
  const isSelected = selectedRows.value.includes(key)

  if (isSelected) {
    selectedRows.value = selectedRows.value.filter(k => k !== key)
  } else {
    selectedRows.value.push(key)
  }

  emit('row-select', { row, index, selected: !isSelected })
}

const toggleSelectAll = () => {
  if (selectAll.value) {
    selectedRows.value = filteredData.value.map((row, index) => getRowKey(row, index))
  } else {
    selectedRows.value = []
  }
}

const goToPage = (page) => {
  if (page >= 1 && page <= totalPages.value) {
    currentPage.value = page
    emit('page-change', page)
  }
}

// Watch for data changes
watch(() => props.data, () => {
  currentPage.value = 1
  selectedRows.value = []
  selectAll.value = false
})

watch(selectedRows, (newSelection) => {
  selectAll.value = newSelection.length === filteredData.value.length && filteredData.value.length > 0
})
</script>

<style scoped>
.data-table-container {
  @apply bg-theme-surface-elevated rounded-lg border border-theme overflow-hidden;
}

.data-table-header {
  @apply px-6 py-4 border-b border-theme;
}

.data-table-wrapper {
  @apply overflow-x-auto;
}

.data-table {
  @apply w-full;
}

.data-table-header-cell {
  @apply px-6 py-3 text-left text-xs font-medium text-theme-secondary uppercase tracking-wider border-b border-theme;
}

.data-table-body {
  @apply divide-y divide-theme;
}

.data-table-row {
  @apply transition-colors duration-150;
}

.data-table-cell {
  @apply px-6 py-4 text-sm text-theme-primary;
}

.data-table-cell-select {
  @apply w-12 px-4 py-4;
}

.data-table-cell-actions {
  @apply w-32 px-6 py-4;
}

.data-table-cell-loading {
  @apply text-center;
}

.data-table-cell-empty {
  @apply text-center;
}

.data-table-pagination {
  @apply px-6 py-4 border-t border-theme bg-theme-surface;
}

/* Responsive adjustments */
@media (max-width: 640px) {
  .data-table-header-cell,
  .data-table-cell {
    @apply px-4 py-3;
  }

  .data-table-cell-actions {
    @apply w-24;
  }
}
</style>
