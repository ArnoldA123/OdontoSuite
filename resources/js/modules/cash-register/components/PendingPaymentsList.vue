<template>
  <div class="pending-payments-list">
    <div class="mb-4">
      <h3 class="text-lg font-medium text-theme-primary mb-2">
        Pagos Pendientes
      </h3>
      <p class="text-sm text-theme-secondary">
        Pacientes con citas completadas que requieren pago
      </p>
    </div>

    <!-- Filtros -->
    <div class="mb-6 bg-theme-surface-elevated rounded-lg border border-theme p-4">
      <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <div>
          <label class="block text-sm font-medium text-theme-primary mb-1">
            Buscar Paciente
          </label>
          <input
            v-model="filters.search"
            type="text"
            placeholder="Nombre o documento..."
            class="block w-full px-3 py-2 border border-theme rounded-md shadow-sm bg-theme-surface-elevated text-theme-primary placeholder-theme-secondary focus:ring-primary-500 focus:border-accent"
          />
        </div>

        <div>
          <label class="block text-sm font-medium text-theme-primary mb-1">
            Fecha Desde
          </label>
          <input
            v-model="filters.date_from"
            type="date"
            class="block w-full px-3 py-2 border border-theme rounded-md shadow-sm bg-theme-surface-elevated text-theme-primary focus:ring-primary-500 focus:border-accent"
          />
        </div>

        <div>
          <label class="block text-sm font-medium text-theme-primary mb-1">
            Fecha Hasta
          </label>
          <input
            v-model="filters.date_to"
            type="date"
            class="block w-full px-3 py-2 border border-theme rounded-md shadow-sm bg-theme-surface-elevated text-theme-primary focus:ring-primary-500 focus:border-accent"
          />
        </div>

        <div class="flex items-end">
          <Button
            variant="secondary"
            @click="clearFilters"
            class="w-full"
          >
            Limpiar
          </Button>
        </div>
      </div>
    </div>

    <!-- Lista de Pagos Pendientes -->
    <div class="bg-theme-surface-elevated rounded-lg border border-theme overflow-hidden">
      <div v-if="loading" class="p-8 text-center">
        <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-accent mx-auto"></div>
        <p class="mt-2 text-sm text-theme-secondary">Cargando pagos pendientes...</p>
      </div>

      <div v-else-if="filteredPayments.length === 0" class="p-8 text-center">
        <BanknotesIcon class="mx-auto h-12 w-12 text-theme-secondary" />
        <h3 class="mt-2 text-sm font-medium text-theme-primary">No hay pagos pendientes</h3>
        <p class="mt-1 text-sm text-theme-secondary">
          Todos los pagos han sido registrados o no hay citas completadas.
        </p>
      </div>

      <div v-else class="overflow-x-auto">
        <table class="min-w-full divide-y divide-theme">
          <thead class="bg-theme-surface">
            <tr>
              <th class="px-6 py-3 text-left text-xs font-medium text-theme-secondary uppercase tracking-wider">
                Paciente
              </th>
              <th class="px-6 py-3 text-left text-xs font-medium text-theme-secondary uppercase tracking-wider">
                Cita
              </th>
              <th class="px-6 py-3 text-left text-xs font-medium text-theme-secondary uppercase tracking-wider">
                Tratamiento
              </th>
              <th class="px-6 py-3 text-left text-xs font-medium text-theme-secondary uppercase tracking-wider">
                Monto
              </th>
              <th class="px-6 py-3 text-left text-xs font-medium text-theme-secondary uppercase tracking-wider">
                Estado
              </th>
              <th class="px-6 py-3 text-right text-xs font-medium text-theme-secondary uppercase tracking-wider">
                Acciones
              </th>
            </tr>
          </thead>
          <tbody class="bg-theme-surface-elevated divide-y divide-theme">
            <tr v-for="payment in paginatedPayments" :key="payment.id" class="hover:bg-theme-surface">
              <td class="px-6 py-4 whitespace-nowrap">
                <div class="flex items-center">
                  <div class="flex-shrink-0 h-10 w-10">
                    <div class="h-10 w-10 rounded-full bg-primary-100 flex items-center justify-center">
                      <span class="text-sm font-medium text-primary-800">
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
              <td class="px-6 py-4 whitespace-nowrap">
                <div class="text-sm font-medium text-theme-primary">
                  {{ formatCurrency(payment.amount) }}
                </div>
              </td>
              <td class="px-6 py-4 whitespace-nowrap">
                <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-yellow-100 text-yellow-800">
                  Pendiente
                </span>
              </td>
              <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                <div class="flex justify-end space-x-2">
                  <Button
                    variant="primary"
                    size="sm"
                    @click="handlePayment(payment)"
                  >
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
      <div v-if="totalPages > 1" class="bg-theme-surface-elevated px-4 py-3 border-t border-theme sm:px-6">
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
                Mostrando {{ (currentPage - 1) * itemsPerPage + 1 }} a
                {{ Math.min(currentPage * itemsPerPage, filteredPayments.length) }} de
                {{ filteredPayments.length }} resultados
              </p>
            </div>
            <div>
              <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px">
                <Button
                  variant="secondary"
                  size="sm"
                  :disabled="currentPage === 1"
                  @click="currentPage = currentPage - 1"
                  class="rounded-l-md"
                >
                  Anterior
                </Button>
                <Button
                  variant="secondary"
                  size="sm"
                  :disabled="currentPage === totalPages"
                  @click="currentPage = currentPage + 1"
                  class="rounded-r-md"
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
import { useApi } from '@/composables/useApi'

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
    filtered = filtered.filter(payment =>
      payment.patient.name.toLowerCase().includes(search) ||
      payment.patient.document_number.toLowerCase().includes(search)
    )
  }

  if (filters.value.date_from) {
    filtered = filtered.filter(payment =>
      new Date(payment.appointment.date) >= new Date(filters.value.date_from)
    )
  }

  if (filters.value.date_to) {
    filtered = filtered.filter(payment =>
      new Date(payment.appointment.date) <= new Date(filters.value.date_to)
    )
  }

  return filtered
})

const totalPages = computed(() =>
  Math.ceil(filteredPayments.value.length / itemsPerPage.value)
)

const paginatedPayments = computed(() => {
  const start = (currentPage.value - 1) * itemsPerPage.value
  const end = start + itemsPerPage.value
  return filteredPayments.value.slice(start, end)
})

// Métodos
const loadPendingPayments = async () => {
  console.log('Cargando pagos pendientes...')
  loading.value = true
  try {
    // Usar endpoint original autenticado
    const response = await get('/api/cash-register/pending-payments', { params: filters.value })
    payments.value = response.data || []
    console.log('Pagos pendientes cargados:', payments.value.length)
    // Emitir los pagos cargados
    emit('payments-loaded', payments.value)
  } catch (error) {
    console.error('Error cargando pagos pendientes:', error)
    // En caso de error, mostrar lista vacía
    payments.value = []
    emit('payments-loaded', [])
  } finally {
    loading.value = false
  }
}

const handlePayment = (payment) => {
  console.log('Enviando datos del pago:', payment)
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

const getInitials = (name) => {
  if (!name) return '??'
  return name.split(' ').map(n => n[0]).join('').toUpperCase()
}

const formatCurrency = (amount) => {
  return new Intl.NumberFormat('es-PE', {
    style: 'currency',
    currency: 'PEN'
  }).format(amount)
}

const formatDate = (date) => {
  return new Date(date).toLocaleDateString('es-PE', {
    year: 'numeric',
    month: 'short',
    day: 'numeric'
  })
}

// Watchers
watch(filters, () => {
  currentPage.value = 1
}, { deep: true })

// Lifecycle
onMounted(() => {
  console.log('PendingPaymentsList montado, cargando pagos pendientes...')
  loadPendingPayments()
})

// Watch para recargar cuando cambie la key del componente
watch(() => props.key, () => {
  console.log('Key del componente cambió, recargando pagos pendientes...')
  loadPendingPayments()
})
</script>
