<template>
  <div class="session-list">
    <!-- Filtros -->
    <div class="mb-6">
      <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <div>
          <label class="block text-sm font-medium text-theme-primary mb-1">Fecha Desde</label>
          <input
            v-model="filters.date_from"
            type="date"
            class="block w-full px-3 py-2 border border-theme rounded-md shadow-sm bg-theme-surface-elevated text-theme-primary focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-accent sm:text-sm"
          />
        </div>

        <div>
          <label class="block text-sm font-medium text-theme-primary mb-1">Fecha Hasta</label>
          <input
            v-model="filters.date_to"
            type="date"
            class="block w-full px-3 py-2 border border-theme rounded-md shadow-sm bg-theme-surface-elevated text-theme-primary focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-accent sm:text-sm"
          />
        </div>

        <div>
          <label class="block text-sm font-medium text-theme-primary mb-1">Estado</label>
          <select
            v-model="filters.status"
            class="block w-full px-3 py-2 border border-theme rounded-md shadow-sm bg-theme-surface-elevated text-theme-primary focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-accent sm:text-sm"
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
            class="block w-full px-3 py-2 border border-theme rounded-md shadow-sm bg-theme-surface-elevated text-theme-primary focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-accent sm:text-sm"
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
        <table class="min-w-full divide-y divide-theme">
          <thead class="bg-theme-surface">
            <tr>
              <th class="px-6 py-3 text-left text-xs font-medium text-theme-secondary uppercase tracking-wider">
                Sesión
              </th>
              <th class="px-6 py-3 text-left text-xs font-medium text-theme-secondary uppercase tracking-wider">
                Usuario
              </th>
              <th class="px-6 py-3 text-left text-xs font-medium text-theme-secondary uppercase tracking-wider">
                Sucursal
              </th>
              <th class="px-6 py-3 text-left text-xs font-medium text-theme-secondary uppercase tracking-wider">
                Apertura
              </th>
              <th class="px-6 py-3 text-left text-xs font-medium text-theme-secondary uppercase tracking-wider">
                Cierre
              </th>
              <th class="px-6 py-3 text-left text-xs font-medium text-theme-secondary uppercase tracking-wider">
                Diferencia
              </th>
              <th class="px-6 py-3 text-left text-xs font-medium text-theme-secondary uppercase tracking-wider">
                Estado
              </th>
              <th class="px-6 py-3 text-left text-xs font-medium text-theme-secondary uppercase tracking-wider">
                Acciones
              </th>
            </tr>
          </thead>
          <tbody class="bg-theme-surface-elevated divide-y divide-theme">
            <tr v-if="loading" class="animate-pulse">
              <td colspan="8" class="px-6 py-4 text-center">
                <div class="flex justify-center">
                  <div class="animate-spin rounded-full h-6 w-6 border-b-2 border-accent"></div>
                </div>
              </td>
            </tr>

            <tr v-else-if="sessions.length === 0">
              <td colspan="8" class="px-6 py-4 text-center text-theme-secondary">
                No hay sesiones registradas
              </td>
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

              <td class="px-6 py-4 whitespace-nowrap">
                <div class="text-sm text-theme-primary">
                  {{ formatTime(session.opened_at) }}
                </div>
                <div class="text-sm text-theme-secondary">
                  S/ {{ formatCurrency(session.opening_amount) }}
                </div>
              </td>

              <td class="px-6 py-4 whitespace-nowrap">
                <div v-if="session.closed_at" class="text-sm text-theme-primary">
                  {{ formatTime(session.closed_at) }}
                </div>
                <div v-else class="text-sm text-theme-secondary">
                  -
                </div>
                <div v-if="session.closing_amount" class="text-sm text-theme-secondary">
                  S/ {{ formatCurrency(session.closing_amount) }}
                </div>
              </td>

              <td class="px-6 py-4 whitespace-nowrap">
                <div v-if="session.difference_amount !== null" class="text-sm font-medium">
                  <span
                    :class="session.difference_amount === 0 ? 'text-green-600' :
                           session.difference_amount > 0 ? 'text-accent' : 'text-red-600'"
                  >
                    {{ session.difference_amount === 0 ? 'Conforme' :
                       session.difference_amount > 0 ? '+' : '' }}{{ formatCurrency(session.difference_amount) }}
                  </span>
                </div>
                <div v-else class="text-sm text-theme-secondary">
                  -
                </div>
              </td>

              <td class="px-6 py-4 whitespace-nowrap">
                <span
                  class="inline-flex px-2 py-1 text-xs font-semibold rounded-full"
                  :class="getStatusClass(session.status)"
                >
                  {{ getStatusText(session.status) }}
                </span>
              </td>

              <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                <div class="flex space-x-2">
                  <button
                    @click="viewSession(session)"
                    class="text-accent hover:text-primary-700"
                    title="Ver detalle"
                  >
                    <EyeIcon class="w-4 h-4" />
                  </button>

                  <button
                    v-if="session.status === 'closed'"
                    @click="generateReport(session)"
                    class="text-green-600 hover:text-green-900"
                    title="Generar reporte"
                  >
                    <DocumentTextIcon class="w-4 h-4" />
                  </button>

                  <button
                    v-if="canReopen(session)"
                    @click="reopenSession(session)"
                    class="text-yellow-600 hover:text-yellow-900"
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
        {{ Math.min(pagination.current_page * pagination.per_page, pagination.total) }} de
        {{ pagination.total }} resultados
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

        <span class="px-3 py-2 text-sm text-theme-primary">
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
import { usePermissions } from '@/composables/usePermissions'
import { useApi } from '@/composables/useApi'
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
const { can } = usePermissions()

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

const reopenSession = (session) => {
  if (!confirm('¿Está seguro de reabrir esta sesión de caja?')) return
  emit('reopen', session)
}

const canReopen = (session) => {
  return session.status === 'closed' && can.value.manageCashRegister
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

const formatCurrency = (amount) => {
  return new Intl.NumberFormat('es-PE', {
    style: 'currency',
    currency: 'PEN'
  }).format(amount || 0)
}

const getStatusText = (status) => {
  const texts = {
    open: 'Abierta',
    closed: 'Cerrada'
  }
  return texts[status] || status
}

const getStatusClass = (status) => {
  const classes = {
    open: 'bg-green-100 text-green-800',
    closed: 'bg-theme-surface text-theme-secondary'
  }
  return classes[status] || 'bg-theme-surface text-theme-secondary'
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
