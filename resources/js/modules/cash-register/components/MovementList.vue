<template>
  <div class="movement-list bg-canvas">
    <!-- Filtros -->
    <div class="mb-6">
      <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div>
          <label class="block text-sm font-medium text-theme-primary mb-1">Fecha Desde</label>
          <input
            v-model="filters.date_from"
            type="date"
            class="block w-full px-3 py-2 border border-hairline rounded-md shadow-sm bg-theme-surface-elevated text-theme-primary focus:outline-none sm:text-sm"
          />
        </div>

        <div>
          <label class="block text-sm font-medium text-theme-primary mb-1">Fecha Hasta</label>
          <input
            v-model="filters.date_to"
            type="date"
            class="block w-full px-3 py-2 border border-hairline rounded-md shadow-sm bg-theme-surface-elevated text-theme-primary focus:outline-none sm:text-sm"
          />
        </div>

        <div>
          <label class="block text-sm font-medium text-theme-primary mb-1">Tipo</label>
          <select
            v-model="filters.type"
            class="block w-full px-3 py-2 border border-hairline rounded-md shadow-sm bg-theme-surface-elevated text-theme-primary focus:outline-none sm:text-sm"
          >
            <option value="">Todos</option>
            <option value="income">Ingreso</option>
            <option value="expense">Egreso</option>
            <option value="opening">Apertura</option>
            <option value="closing">Cierre</option>
          </select>
        </div>
      </div>

      <div class="flex justify-between items-center mt-4">
        <Button
          variant="secondary"
          @click="applyFilters"
          :loading="loading"
        >
          <MagnifyingGlassIcon class="w-4 h-4 mr-2" />
          Filtrar
        </Button>

        <div class="flex space-x-2">
          <Button
            variant="secondary"
            @click="exportToExcel"
            :loading="exporting"
          >
            <DocumentArrowDownIcon class="w-4 h-4 mr-2" />
            Excel
          </Button>

          <Button
            variant="secondary"
            @click="exportToPDF"
            :loading="exporting"
          >
            <DocumentArrowDownIcon class="w-4 h-4 mr-2" />
            PDF
          </Button>
        </div>
      </div>
    </div>

    <!-- Resumen de Totales -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
      <div class="bg-green-50 border border-green-200 rounded-lg p-4">
        <div class="flex items-center">
          <div class="flex-shrink-0">
            <ArrowUpIcon class="w-6 h-6 text-green-600" />
          </div>
          <div class="ml-3">
            <p class="text-sm font-medium text-green-600">Total Ingresos</p>
            <p class="text-2xl font-bold text-green-900 tabular-nums">{{ formatCurrency(totals.income) }}</p>
          </div>
        </div>
      </div>

      <div class="bg-red-50 border border-red-200 rounded-lg p-4">
        <div class="flex items-center">
          <div class="flex-shrink-0">
            <ArrowDownIcon class="w-6 h-6 text-red-600" />
          </div>
          <div class="ml-3">
            <p class="text-sm font-medium text-red-600">Total Egresos</p>
            <p class="text-2xl font-bold text-red-900 tabular-nums">{{ formatCurrency(totals.expense) }}</p>
          </div>
        </div>
      </div>

      <div class="bg-primary-50 border border-primary-200 rounded-lg p-4">
        <div class="flex items-center">
          <div class="flex-shrink-0">
            <BanknotesIcon class="w-6 h-6 text-accent" />
          </div>
          <div class="ml-3">
            <p class="text-sm font-medium text-accent">Apertura</p>
            <p class="text-2xl font-bold text-primary-800 tabular-nums">{{ formatCurrency(totals.opening) }}</p>
          </div>
        </div>
      </div>

      <div class="bg-primary-50 border border-primary-200 rounded-lg p-4">
        <div class="flex items-center">
          <div class="flex-shrink-0">
            <XMarkIcon class="w-6 h-6 text-accent" />
          </div>
          <div class="ml-3">
            <p class="text-sm font-medium text-accent">Cierre</p>
            <p class="text-2xl font-bold text-accent-active tabular-nums">{{ formatCurrency(totals.closing) }}</p>
          </div>
        </div>
      </div>
    </div>

    <!-- Tabla de Movimientos -->
    <div class="bg-theme-surface-elevated shadow-sm rounded-lg overflow-hidden">
      <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-hairline">
          <thead class="bg-theme-surface">
            <tr>
              <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-theme-secondary uppercase tracking-wider">
                Hora
              </th>
              <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-theme-secondary uppercase tracking-wider">
                Tipo
              </th>
              <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-theme-secondary uppercase tracking-wider">
                Descripción
              </th>
              <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-theme-secondary uppercase tracking-wider">
                Referencia
              </th>
              <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-theme-secondary uppercase tracking-wider">
                Monto
              </th>
              <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-theme-secondary uppercase tracking-wider">
                Usuario
              </th>
              <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-theme-secondary uppercase tracking-wider">
                Acciones
              </th>
            </tr>
          </thead>
          <tbody class="bg-theme-surface-elevated divide-y divide-hairline">
            <tr v-if="loading" class="animate-pulse">
              <td colspan="7" class="px-6 py-4 text-center">
                <UiLoadingSpinner size="md" variant="primary" text="Cargando movimientos..." />
              </td>
            </tr>

            <tr v-else-if="movements.length === 0">
              <td colspan="7" class="px-6 py-4 text-center text-theme-secondary">No hay movimientos registrados</td>
            </tr>

            <tr
              v-else
              v-for="movement in movements"
              :key="movement.id"
              class="hover:bg-theme-surface"
            >
              <td class="px-6 py-4 whitespace-nowrap text-sm text-theme-primary">
                {{ formatTime(movement.created_at) }}
              </td>

              <td class="px-6 py-4 whitespace-nowrap">
                <UiStatusBadge :variant="getTypeVariant(movement.type)" :label="getTypeText(movement.type)" />
              </td>

              <td class="px-6 py-4">
                <div class="text-sm text-theme-primary">{{ movement.description }}</div>
                <div v-if="movement.notes" class="text-sm text-theme-secondary">
                  {{ movement.notes }}
                </div>
              </td>

              <td class="px-6 py-4 whitespace-nowrap text-sm text-theme-primary">
                {{ movement.reference || '-' }}
              </td>

              <td
                class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium tabular-nums"
                :class="getAmountClass(movement.type)"
                :aria-label="`${getAmountPrefix(movement.type)}${formatCurrency(movement.amount)} soles`"
              >
                {{ getAmountPrefix(movement.type) }}{{ formatCurrency(movement.amount) }}
              </td>

              <td class="px-6 py-4 whitespace-nowrap text-sm text-theme-primary">
                {{ movement.created_by?.name || 'Sistema' }}
              </td>

              <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                <div class="flex space-x-2">
                  <button
                    @click="viewMovement(movement)"
                    class="text-accent hover:text-primary-700"
                    title="Ver detalle"
                  >
                    <EyeIcon class="w-4 h-4" />
                  </button>

                  <button
                    v-if="canEdit(movement)"
                    @click="editMovement(movement)"
                    class="text-yellow-600 hover:text-yellow-900"
                    title="Editar"
                  >
                    <PencilIcon class="w-4 h-4" />
                  </button>

                  <button
                    v-if="canDelete(movement)"
                    @click="deleteMovement(movement)"
                    class="text-red-600 hover:text-red-900"
                    title="Eliminar"
                  >
                    <TrashIcon class="w-4 h-4" />
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Paginación -->
    <div v-if="pagination && pagination.total > 0" class="mt-6 flex items-center justify-between">
      <div class="text-sm text-theme-primary">
        Mostrando {{ ((pagination.current_page - 1) * pagination.per_page) + 1 }} a
        <span class="tabular-nums">{{ Math.min(pagination.current_page * pagination.per_page, pagination.total) }}</span> de
        <span class="tabular-nums">{{ pagination.total }}</span> resultados
      </div>

      <div class="flex space-x-2">
        <Button
          variant="secondary"
          size="sm"
          @click="loadPage(pagination.current_page - 1)"
          :disabled="pagination.current_page <= 1"
        >
          Anterior
        </Button>

        <span class="px-3 py-2 text-sm text-theme-primary tabular-nums">
          Página {{ pagination.current_page }} de {{ pagination.last_page }}
        </span>

        <Button
          variant="secondary"
          size="sm"
          @click="loadPage(pagination.current_page + 1)"
          :disabled="pagination.current_page >= pagination.last_page"
        >
          Siguiente
        </Button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue'
import Button from '@/components/ui/Button.vue'
import UiStatusBadge from '@/components/ui/StatusBadge.vue'
import UiLoadingSpinner from '@/components/ui/LoadingSpinner.vue'
import { usePermissions } from '@/composables/usePermissions'
import { useConfirm } from '@/composables/useConfirm'
import { formatCurrency } from '@/composables/useFormatters'
import {
  MagnifyingGlassIcon,
  DocumentArrowDownIcon,
  ArrowUpIcon,
  ArrowDownIcon,
  BanknotesIcon,
  XMarkIcon,
  EyeIcon,
  PencilIcon,
  TrashIcon
} from '@heroicons/vue/24/outline'

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

const emit = defineEmits(['refresh', 'edit', 'delete'])

const { can } = usePermissions()

// Estado
const filters = ref({
  date_from: '',
  date_to: '',
  type: ''
})

const exporting = ref(false)

// Computed
const totals = computed(() => {
  // Si tenemos resumen de la sesión, usarlo directamente
  if (props.summary) {
    return {
      income: parseFloat(props.summary.total_income || 0),
      expense: parseFloat(props.summary.total_expenses || 0),
      opening: parseFloat(props.summary.opening_amount || 0),
      closing: 0 // El cierre se calcula al cerrar la sesión
    }
  }

  // Si no hay resumen, calcular desde los movimientos
  const income = props.movements
    .filter(m => ['income', 'opening'].includes(m.type))
    .reduce((sum, m) => {
      const amount = parseFloat(m.amount) || 0
      return sum + amount
    }, 0)

  const expense = props.movements
    .filter(m => ['expense', 'closing'].includes(m.type))
    .reduce((sum, m) => {
      const amount = parseFloat(m.amount) || 0
      return sum + amount
    }, 0)

  const opening = props.movements
    .filter(m => m.type === 'opening')
    .reduce((sum, m) => {
      const amount = parseFloat(m.amount) || 0
      return sum + amount
    }, 0)

  const closing = props.movements
    .filter(m => m.type === 'closing')
    .reduce((sum, m) => {
      const amount = parseFloat(m.amount) || 0
      return sum + amount
    }, 0)

  return {
    income: isNaN(income) ? 0 : income,
    expense: isNaN(expense) ? 0 : expense,
    opening: isNaN(opening) ? 0 : opening,
    closing: isNaN(closing) ? 0 : closing
  }
})

// Métodos
const applyFilters = () => {
  emit('refresh', filters.value)
}

const loadPage = (page) => {
  emit('refresh', { ...filters.value, page })
}

const viewMovement = (movement) => {
  // Implementar vista de detalle
}

const editMovement = (movement) => {
  emit('edit', movement)
}

const deleteMovement = async (movement) => {
  const ok = await confirm({
    title: 'Eliminar movimiento',
    message: '¿Está seguro de eliminar este movimiento?',
    confirmText: 'Eliminar',
    variant: 'danger',
  })
  if (!ok) return
  emit('delete', movement)
}

const exportToExcel = async () => {
  exporting.value = true
  try {
    // Implementar exportación a Excel
  } catch (error) {
  } finally {
    exporting.value = false
  }
}

const exportToPDF = async () => {
  exporting.value = true
  try {
    // Implementar exportación a PDF
  } catch (error) {
  } finally {
    exporting.value = false
  }
}

const formatTime = (dateTime) => {
  return new Date(dateTime).toLocaleTimeString('es-PE', {
    hour: '2-digit',
    minute: '2-digit'
  })
}

// formatCurrency is imported from useFormatters (PAGOS-MNY-002 canonicalization).

const getTypeText = (type) => {
  const texts = {
    income: 'Ingreso',
    expense: 'Egreso',
    opening: 'Apertura',
    closing: 'Cierre',
    withdrawal: 'Retiro',
    deposit: 'Depósito',
    adjustment: 'Ajuste'
  }
  return texts[type] || type
}

const getTypeVariant = (type) => {
  const variants = {
    income: 'success',
    expense: 'error',
    opening: 'info',
    closing: 'info',
    withdrawal: 'warning',
    deposit: 'success',
    adjustment: 'neutral'
  }
  return variants[type] || 'neutral'
}

const getAmountClass = (type) => {
  if (['income', 'opening', 'deposit'].includes(type)) {
    return 'text-green-600'
  } else if (['expense', 'closing', 'withdrawal'].includes(type)) {
    return 'text-red-600'
  }
  return 'text-theme-secondary'
}

const getAmountPrefix = (type) => {
  if (['income', 'opening', 'deposit'].includes(type)) {
    return '+'
  } else if (['expense', 'closing', 'withdrawal'].includes(type)) {
    return '-'
  }
  return ''
}

const canEdit = (movement) => {
  // Solo se pueden editar movimientos que no sean del sistema
  return !['opening', 'closing'].includes(movement.type) && can.value?.manageCashRegister
}

const canDelete = (movement) => {
  // Solo se pueden eliminar movimientos que no sean del sistema
  return !['opening', 'closing'].includes(movement.type) && can.value?.manageCashRegister
}
</script>