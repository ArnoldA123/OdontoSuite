<template>
  <div class="transaction-list bg-canvas">
    <!-- Filtros -->
    <div class="mb-6">
      <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
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
            <option value="payment">Ingreso</option>
            <option value="refund">Egreso</option>
          </select>
        </div>

        <div>
          <label class="block text-sm font-medium text-theme-primary mb-1">Método de Pago</label>
          <select
            v-model="filters.payment_method_id"
            class="block w-full px-3 py-2 border border-hairline rounded-md shadow-sm bg-theme-surface-elevated text-theme-primary focus:outline-none sm:text-sm"
          >
            <option value="">Todos</option>
            <option
              v-for="method in paymentMethods"
              :key="method.id"
              :value="method.id"
            >
              {{ method.name }}
            </option>
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

    <!-- Tabla de Transacciones -->
    <div class="bg-theme-surface-elevated shadow-sm rounded-lg overflow-hidden">
      <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-hairline">
          <thead class="bg-theme-surface">
            <tr>
              <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-theme-secondary uppercase tracking-wider">
                Hora
              </th>
              <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-theme-secondary uppercase tracking-wider">
                Paciente
              </th>
              <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-theme-secondary uppercase tracking-wider">
                Descripción
              </th>
              <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-theme-secondary uppercase tracking-wider">
                Método
              </th>
              <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-theme-secondary uppercase tracking-wider">
                Monto
              </th>
              <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-theme-secondary uppercase tracking-wider">
                Estado
              </th>
              <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-theme-secondary uppercase tracking-wider">
                Acciones
              </th>
            </tr>
          </thead>
          <tbody class="bg-theme-surface-elevated divide-y divide-hairline">
            <tr v-if="loading" class="animate-pulse">
              <td colspan="7" class="px-6 py-4 text-center">
                <div class="flex justify-center">
                  <div class="animate-spin rounded-full h-6 w-6 border-b-2 border-accent"></div>
                </div>
              </td>
            </tr>

            <tr v-else-if="transactions.length === 0">
              <td colspan="7" class="px-6 py-4 text-center text-theme-secondary">No hay transacciones registradas</td>
            </tr>

            <tr
              v-else
              v-for="transaction in transactions"
              :key="transaction.id"
              class="hover:bg-theme-surface"
            >
              <td class="px-6 py-4 whitespace-nowrap text-sm text-theme-primary">
                {{ formatTime(transaction.created_at) }}
              </td>

              <td class="px-6 py-4 whitespace-nowrap">
                <div class="text-sm font-medium text-theme-primary">
                  {{ transaction.patient?.name }} {{ transaction.patient?.last_name }}
                </div>
                <div class="text-sm text-theme-secondary">
                  {{ transaction.patient?.dni }}
                </div>
              </td>

              <td class="px-6 py-4">
                <div class="text-sm text-theme-primary">{{ transaction.description }}</div>
                <div v-if="transaction.reference_number" class="text-sm text-theme-secondary">
                  Ref: {{ transaction.reference_number }}
                </div>
              </td>

              <td class="px-6 py-4 whitespace-nowrap text-sm text-theme-primary">
                {{ transaction.payment_method?.name }}
              </td>

              <td
                class="px-6 py-4 whitespace-nowrap text-right" :aria-label="`${isIncomeType(transaction.type) ? '+' : '-'}${formatCurrency(transaction.amount)} soles`"
              >
                <div
                  class="text-sm font-medium tabular-nums"
                  :class="isIncomeType(transaction.type) ? 'text-systemGreen-600' : 'text-systemRed-600'"
                >
                  {{ isIncomeType(transaction.type) ? '+' : '-' }}{{ formatCurrency(transaction.amount) }}
                </div>
                <div
                  v-if="transaction.discount_amount > 0"
                  class="text-xs text-theme-secondary tabular-nums"
                >
                  Descuento: -{{ formatCurrency(transaction.discount_amount) }}
                </div>
              </td>

              <td class="px-6 py-4 whitespace-nowrap">
                <UiStatusBadge :variant="getStatusVariant(transaction.status)" :label="getStatusText(transaction.status)" />
              </td>

              <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                <div class="flex space-x-2">
                  <button
                    @click="viewTransaction(transaction)"
                    class="text-systemBlue-600 hover:text-systemBlue-700"
                    title="Ver detalle"
                  >
                    <EyeIcon class="w-4 h-4" />
                  </button>

                  <button
                    @click="generateReceipt(transaction)"
                    class="text-systemGreen-600 hover:text-systemGreen-700"
                    title="Generar comprobante"
                  >
                    <DocumentTextIcon class="w-4 h-4" />
                  </button>

                  <button
                    v-if="canVoid && transaction.status === 'completed'"
                    @click="voidTransaction(transaction)"
                    class="text-systemRed-600 hover:text-systemRed-700"
                    title="Anular transacción"
                  >
                    <XMarkIcon class="w-4 h-4" />
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
import { ref, computed, onMounted } from 'vue'
import Button from '@/components/ui/Button.vue'
import UiStatusBadge from '@/components/ui/StatusBadge.vue'
import { useTransactions } from '@/composables/useTransactions'
import { usePermissions } from '@/composables/usePermissions'
import { useApi } from '@/composables/useApi'
import { useConfirm } from '@/composables/useConfirm'
import { formatCurrency } from '@/composables/useFormatters'
import {
  MagnifyingGlassIcon,
  DocumentArrowDownIcon,
  EyeIcon,
  DocumentTextIcon,
  XMarkIcon
} from '@heroicons/vue/24/outline'

const props = defineProps({
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
  }
})

const emit = defineEmits(['refresh', 'edit', 'void', 'receipt'])

const { get } = useApi()
const { canVoid } = usePermissions()
const { voidTransaction: voidTransactionApi } = useTransactions()

// Estado
const filters = ref({
  date_from: '',
  date_to: '',
  type: '',
  payment_method_id: ''
})

const paymentMethods = ref([])
const exporting = ref(false)

// Métodos
const loadPaymentMethods = async () => {
  try {
    const response = await get('/api/payment-methods')
    paymentMethods.value = response.data || []
  } catch (error) {
  }
}

const applyFilters = () => {
  emit('refresh', filters.value)
}

const loadPage = (page) => {
  emit('refresh', { ...filters.value, page })
}

const viewTransaction = (transaction) => {
  // Implementar vista de detalle
}

const generateReceipt = (transaction) => {
  emit('receipt', transaction)
}

const voidTransaction = async (transaction) => {
  const ok = await confirm({
    title: 'Anular transacción',
    message: '¿Está seguro de anular esta transacción?',
    confirmText: 'Anular',
    variant: 'danger',
  })
  if (!ok) return

  try {
    await voidTransactionApi(transaction.id, 'Anulación manual')
    emit('refresh')
  } catch (error) {
  }
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

const getStatusText = (status) => {
  const texts = {
    pending: 'Pendiente',
    completed: 'Completada',
    failed: 'Fallida',
    cancelled: 'Cancelada',
    voided: 'Anulada'
  }
  return texts[status] || status
}

const getStatusVariant = (status) => {
  const variants = {
    pending: 'warning',
    completed: 'success',
    failed: 'error',
    cancelled: 'neutral',
    voided: 'error'
  }
  return variants[status] || 'neutral'
}

// Helper para determinar si un tipo de transacción es ingreso
const isIncomeType = (type) => {
  // 'payment' = ingreso, 'refund' = egreso, otros = egreso
  return type === 'payment'
}

// Lifecycle
onMounted(() => {
  loadPaymentMethods()
})
</script>