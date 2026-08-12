<template>
  <div class="session-list bg-canvas">
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
          <label class="block text-sm font-medium text-theme-primary mb-1">Estado</label>
          <select
            v-model="filters.status"
            class="block w-full px-3 py-2 border border-hairline rounded-md shadow-sm bg-theme-surface-elevated text-theme-primary focus:outline-none sm:text-sm"
          >
            <option value="">Todos</option>
            <option value="open">Abierta</option>
            <option value="closed">Cerrada</option>
          </select>
        </div>

        <div>
          <label class="block text-sm font-medium text-theme-primary mb-1">Usuario</label>
          <select
            v-model="filters.user_id"
            class="block w-full px-3 py-2 border border-hairline rounded-md shadow-sm bg-theme-surface-elevated text-theme-primary focus:outline-none sm:text-sm"
          >
            <option value="">Todos</option>
            <option
              v-for="user in users"
              :key="user.id"
              :value="user.id"
            >
              {{ user.name }}
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

    <!-- Tabla de Sesiones -->
    <div class="bg-theme-surface-elevated shadow-sm rounded-lg overflow-hidden">
      <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-hairline">
          <thead class="bg-theme-surface">
            <tr>
              <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-theme-secondary uppercase tracking-wider">
                Sesión
              </th>
              <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-theme-secondary uppercase tracking-wider">
                Usuario
              </th>
              <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-theme-secondary uppercase tracking-wider">
                Sucursal
              </th>
              <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-theme-secondary uppercase tracking-wider">
                Apertura
              </th>
              <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-theme-secondary uppercase tracking-wider">
                Cierre
              </th>
              <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-theme-secondary uppercase tracking-wider">
                Diferencia
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
              <td colspan="8" class="px-6 py-4 text-center">
                <div class="flex justify-center">
                  <UiLoadingSpinner size="md" variant="primary" text="Cargando sesiones..." />
                </div>
              </td>
            </tr>

            <tr v-else-if="sessions.length === 0">
              <td colspan="8" class="px-6 py-4 text-center text-theme-secondary">No hay sesiones registradas</td>
            </tr>

            <tr
              v-else
              v-for="session in sessions"
              :key="session.id"
              class="hover:bg-theme-surface"
            >
              <td class="px-6 py-4 whitespace-nowrap">
                <div class="text-sm font-medium text-theme-primary">
                  #{{ session.id }}
                </div>
                <div class="text-sm text-theme-secondary">
                  {{ formatDate(session.opened_at) }}
                </div>
              </td>

              <td class="px-6 py-4 whitespace-nowrap text-sm text-theme-primary">
                {{ session.user?.name }}
              </td>

              <td class="px-6 py-4 whitespace-nowrap text-sm text-theme-primary">
                {{ session.branch?.name }}
              </td>

              <td
                class="px-6 py-4 whitespace-nowrap text-right" :aria-label="`Monto apertura ${formatPENLabel(session.opening_amount)} soles`"
              >
                <div class="text-sm text-theme-primary">
                  {{ formatTime(session.opened_at) }}
                </div>
                <div class="text-sm text-theme-secondary tabular-nums">
                  {{ formatPENLabel(session.opening_amount) }}
                </div>
              </td>

              <td
                class="px-6 py-4 whitespace-nowrap text-right" :aria-label="session.closing_amount ? `Monto cierre ${formatPENLabel(session.closing_amount)} soles` : 'Sin monto de cierre'"
              >
                <div v-if="session.closed_at" class="text-sm text-theme-primary">
                  {{ formatTime(session.closed_at) }}
                </div>
                <div v-else class="text-sm text-theme-secondary">
                  -
                </div>
                <div
                  v-if="session.closing_amount"
                  class="text-sm text-theme-secondary tabular-nums"
                >
                  {{ formatPENLabel(session.closing_amount) }}
                </div>
              </td>

              <td
                class="px-6 py-4 whitespace-nowrap text-right" :aria-label="session.difference_amount !== null ? `Diferencia ${formatPENLabel(session.difference_amount)} soles` : 'Sin diferencia'"
              >
                <div v-if="session.difference_amount !== null">
                  <span
                    class="text-sm font-medium tabular-nums"
                    :class="session.difference_amount === 0 ? 'text-systemGreen-600' :
                           session.difference_amount > 0 ? 'text-systemBlue-600' : 'text-systemRed-600'"
                  >
                    {{ session.difference_amount === 0 ? 'Conforme' :
                       session.difference_amount > 0 ? '+' : '' }}{{ formatPENLabel(session.difference_amount) }}
                  </span>
                </div>
                <div v-else class="text-sm text-theme-secondary">
                  -
                </div>
              </td>

              <td class="px-6 py-4 whitespace-nowrap">
                <UiStatusBadge
                  :variant="session.status === 'open' ? 'success' : 'neutral'"
                  :label="getStatusText(session.status)"
                />
              </td>

              <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                <div class="flex space-x-2">
                  <button
                    @click="viewSession(session)"
                    class="text-systemBlue-600 hover:text-systemBlue-700"
                    title="Ver detalle"
                  >
                    <EyeIcon class="w-4 h-4" />
                  </button>

                  <button
                    v-if="session.status === 'closed'"
                    @click="generateReport(session)"
                    class="text-systemGreen-600 hover:text-systemGreen-700"
                    title="Generar reporte"
                  >
                    <DocumentTextIcon class="w-4 h-4" />
                  </button>

                  <button
                    v-if="canReopen(session)"
                    @click="reopenSession(session)"
                    class="text-systemYellow-600 hover:text-systemYellow-700"
                    title="Reabrir sesión"
                  >
                    <ArrowPathIcon class="w-4 h-4" />
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
import { useApi } from '@/composables/useApi'
import { useConfirm } from '@/composables/useConfirm'
import { formatPENLabel } from '@/composables/useFormatters'
import {
  MagnifyingGlassIcon,
  DocumentArrowDownIcon,
  EyeIcon,
  DocumentTextIcon,
  ArrowPathIcon
} from '@heroicons/vue/24/outline'

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

const emit = defineEmits(['refresh', 'view', 'reopen'])

const { get } = useApi()
const { manageCashRegister } = usePermissions()

// Estado
const filters = ref({
  date_from: '',
  date_to: '',
  status: '',
  user_id: ''
})

const users = ref([])
const exporting = ref(false)

// Métodos
const loadUsers = async () => {
  try {
    const response = await get('/api/users/active')
    users.value = response.data || []
  } catch (error) {
  }
}

const applyFilters = () => {
  emit('refresh', filters.value)
}

const loadPage = (page) => {
  emit('refresh', { ...filters.value, page })
}

const viewSession = (session) => {
  emit('view', session)
}

const generateReport = (session) => {
  // Implementar generación de reporte
}

const reopenSession = async (session) => {
  const ok = await confirm({
    title: 'Reabrir sesión de caja',
    message: '¿Está seguro de reabrir esta sesión de caja?',
    confirmText: 'Reabrir',
    variant: 'danger',
  })
  if (!ok) return
  emit('reopen', session)
}

const canReopen = (session) => {
  return session.status === 'closed' && manageCashRegister.value
}

const formatDate = (dateTime) => {
  return new Date(dateTime).toLocaleDateString('es-PE', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric'
  })
}

const formatTime = (dateTime) => {
  return new Date(dateTime).toLocaleTimeString('es-PE', {
    hour: '2-digit',
    minute: '2-digit'
  })
}

// formatPENLabel is imported from useFormatters (PAGOS-MNY-002 canonicalization).

const getStatusText = (status) => {
  const texts = {
    open: 'Abierta',
    closed: 'Cerrada'
  }
  return texts[status] || status
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
</script>