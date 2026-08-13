<template>
  <div class="pending-payments-list bg-canvas">
    <div class="mb-4">
      <h3 class="text-lg font-medium text-theme-primary mb-2">
Pagos Pendientes
</h3>
      <p class="text-sm text-theme-secondary">
Pacientes con citas completadas que requieren pago
</p>
    </div>

    <!-- Filtros -->
    <div class="mb-6 bg-theme-surface-elevated rounded-lg border border-hairline p-4">
      <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <div>
          <label class="block text-sm font-medium text-theme-primary mb-1">Buscar Paciente</label>
          <input
            v-model="filters.search"
            type="text"
            placeholder="Nombre o documento..."
            class="block w-full px-3 py-2 border border-hairline rounded-md shadow-sm bg-theme-surface-elevated text-theme-primary placeholder-theme-secondary focus:outline-none sm:text-sm"
          />
        </div>

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

        <div class="flex items-end">
          <Button variant="secondary" class="w-full" @click="clearFilters">
Limpiar
</Button>
        </div>
      </div>
    </div>

    <!-- Lista de Pagos Pendientes -->
    <div class="bg-theme-surface-elevated rounded-lg border border-hairline overflow-hidden">
      <div v-if="loading" class="p-8 text-center">
        <UiLoadingSpinner size="md" variant="primary" text="Cargando pagos pendientes..." />
      </div>

      <div v-else-if="filteredPayments.length === 0" class="p-8 text-center">
        <BanknotesIcon class="mx-auto h-12 w-12 text-theme-secondary" />
        <h3 class="mt-2 text-sm font-medium text-theme-primary">No hay pagos pendientes</h3>
        <p class="mt-1 text-sm text-theme-secondary">
          Todos los pagos han sido registrados o no hay citas completadas.
        </p>
      </div>

      <div v-else class="overflow-x-auto">
        <table class="min-w-full divide-y divide-hairline">
          <thead class="bg-theme-surface">
            <tr>
              <th
                scope="col"
                class="px-6 py-3 text-left text-xs font-medium text-theme-secondary uppercase tracking-wider"
              >
                Paciente
              </th>
              <th
                scope="col"
                class="px-6 py-3 text-left text-xs font-medium text-theme-secondary uppercase tracking-wider"
              >
                Cita
              </th>
              <th
                scope="col"
                class="px-6 py-3 text-left text-xs font-medium text-theme-secondary uppercase tracking-wider"
              >
                Tratamiento
              </th>
              <th
                scope="col"
                class="px-6 py-3 text-left text-xs font-medium text-theme-secondary uppercase tracking-wider"
              >
                Monto
              </th>
              <th
                scope="col"
                class="px-6 py-3 text-left text-xs font-medium text-theme-secondary uppercase tracking-wider"
              >
                Estado
              </th>
              <th
                scope="col"
                class="px-6 py-3 text-right text-xs font-medium text-theme-secondary uppercase tracking-wider"
              >
                Acciones
              </th>
            </tr>
          </thead>
          <tbody class="bg-theme-surface-elevated divide-y divide-hairline">
            <tr
              v-for="payment in paginatedPayments"
              :key="payment.id"
              class="hover:bg-theme-surface"
            >
              <td class="px-6 py-4 whitespace-nowrap">
                <div class="flex items-center">
                  <div class="flex-shrink-0 h-10 w-10">
                    <div
                      class="h-10 w-10 rounded-full bg-systemBlue-100 flex items-center justify-center"
                    >
                      <span class="text-sm font-medium text-systemBlue-700">
                        {{ getInitials(payment.patient.name) }}
                      </span>
                    </div>
                  </div>
                  <div class="ml-4">
                    <div class="text-sm font-medium text-theme-primary">
                      {{ payment.patient.name }}
                    </div>
                    <div class="text-sm text-theme-secondary">
                      {{ payment.patient.document_number }}
                    </div>
                  </div>
                </div>
              </td>
              <td class="px-6 py-4 whitespace-nowrap">
                <div class="text-sm text-theme-primary">
                  {{ formatDate(payment.appointment.date) }}
                </div>
                <div class="text-sm text-theme-secondary">
                  {{ payment.appointment.appointment_type?.name }}
                </div>
              </td>
              <td class="px-6 py-4 whitespace-nowrap">
                <div class="text-sm text-theme-primary">
                  {{ payment.treatment_plan?.name || 'Consulta' }}
                </div>
                <div class="text-sm text-theme-secondary">
                  {{ payment.concept }}
                </div>
              </td>
              <td
                class="px-6 py-4 whitespace-nowrap tabular-nums"
                :aria-label="`Monto pendiente ${formatCurrency(payment.amount)} soles`"
              >
                <div class="text-sm font-medium text-theme-primary">
                  {{ formatCurrency(payment.amount) }}
                </div>
              </td>
              <td class="px-6 py-4 whitespace-nowrap">
                <UiStatusBadge variant="warning" label="Pendiente" />
              </td>
              <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                <div class="flex justify-end space-x-2">
                  <Button variant="primary" size="sm" @click="handlePayment(payment)">
                    <BanknotesIcon class="w-4 h-4 mr-1" />
                    Cobrar
                  </Button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Paginación -->
      <div
        v-if="totalPages > 1"
        class="bg-theme-surface-elevated px-4 py-3 border-t border-hairline sm:px-6"
      >
        <div class="flex items-center justify-between">
          <div class="flex-1 flex justify-between sm:hidden">
            <Button
              variant="secondary"
              size="sm"
              :disabled="currentPage === 1"
              @click="currentPage = currentPage - 1"
            >
              Anterior
            </Button>
            <Button
              variant="secondary"
              size="sm"
              :disabled="currentPage === totalPages"
              @click="currentPage = currentPage + 1"
            >
              Siguiente
            </Button>
          </div>
          <div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
            <div>
              <p class="text-sm text-theme-primary">
                Mostrando
                <span class="tabular-nums">{{ (currentPage - 1) * itemsPerPage + 1 }}</span>
                a
                <span class="tabular-nums">
                  {{ Math.min(currentPage * itemsPerPage, filteredPayments.length) }}
                </span>
                de
                <span class="tabular-nums">{{ filteredPayments.length }}</span>
                resultados
              </p>
            </div>
            <div>
              <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px">
                <Button
                  variant="secondary"
                  size="sm"
                  :disabled="currentPage === 1"
                  class="rounded-l-md"
                  @click="currentPage = currentPage - 1"
                >
                  Anterior
                </Button>
                <Button
                  variant="secondary"
                  size="sm"
                  :disabled="currentPage === totalPages"
                  class="rounded-r-md"
                  @click="currentPage = currentPage + 1"
                >
                  Siguiente
                </Button>
              </nav>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import { BanknotesIcon } from '@heroicons/vue/24/outline'
import Button from '@/components/ui/Button.vue'
import UiStatusBadge from '@/components/ui/StatusBadge.vue'
import UiLoadingSpinner from '@/components/ui/LoadingSpinner.vue'
import { useApi } from '@/composables/useApi'
import { formatCurrency } from '@/composables/useFormatters'

const props = defineProps({
  key: {
    type: [String, Number],
    default: 0
  }
})

const emit = defineEmits(['payment', 'payments-loaded'])

// Composables
const { get } = useApi()

// Estado
const loading = ref(false)
const payments = ref([])
const currentPage = ref(1)
const itemsPerPage = ref(10)

// Filtros
const filters = ref({
  search: '',
  date_from: '',
  date_to: ''
})

// Computed
const filteredPayments = computed(() => {
  let filtered = payments.value

  if (filters.value.search) {
    const search = filters.value.search.toLowerCase()
    filtered = filtered.filter(
      payment =>
        payment.patient.name.toLowerCase().includes(search) ||
        payment.patient.document_number.toLowerCase().includes(search)
    )
  }

  if (filters.value.date_from) {
    filtered = filtered.filter(
      payment => new Date(payment.appointment.date) >= new Date(filters.value.date_from)
    )
  }

  if (filters.value.date_to) {
    filtered = filtered.filter(
      payment => new Date(payment.appointment.date) <= new Date(filters.value.date_to)
    )
  }

  return filtered
})

const totalPages = computed(() => Math.ceil(filteredPayments.value.length / itemsPerPage.value))

const paginatedPayments = computed(() => {
  const start = (currentPage.value - 1) * itemsPerPage.value
  const end = start + itemsPerPage.value
  return filteredPayments.value.slice(start, end)
})

// Métodos
const loadPendingPayments = async () => {
  loading.value = true
  try {
    // Usar endpoint original autenticado
    const response = await get('/api/cash-register/pending-payments', { params: filters.value })
    payments.value = response.data || []
    // Emitir los pagos cargados
    emit('payments-loaded', payments.value)
  } catch (error) {
    // En caso de error, mostrar lista vacía
    payments.value = []
    emit('payments-loaded', [])
  } finally {
    loading.value = false
  }
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
  currentPage.value = 1
}

const getInitials = name => {
  if (!name) return '??'
  return name
    .split(' ')
    .map(n => n[0])
    .join('')
    .toUpperCase()
}

// formatCurrency is imported from useFormatters (PAGOS-MNY-002 canonicalization).

const formatDate = date => {
  return new Date(date).toLocaleDateString('es-PE', {
    year: 'numeric',
    month: 'short',
    day: 'numeric'
  })
}

// Watchers
watch(
  filters,
  () => {
    currentPage.value = 1
  },
  { deep: true }
)

// Lifecycle
onMounted(() => {
  loadPendingPayments()
})

// Watch para recargar cuando cambie la key del componente
watch(
  () => props.key,
  () => {
    loadPendingPayments()
  }
)
</script>
