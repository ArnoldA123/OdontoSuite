<template>
  <AppLayout>
    <div class="cash-register-page bg-canvas">
      <!-- Header Section -->
      <PageHeader
        title="Gestión de Caja"
        subtitle="Administra las sesiones de caja y transacciones"
        class="mb-6"
      >
        <template #actions>
          <UiStatusBadge
            :variant="sessionStatusVariant"
            :label="sessionStatusText"
            size="md"
            show-dot
          />
          <UiButton
            v-if="canOpen"
            variant="primary"
            :loading="loading"
            @click="showOpenModal = true"
          >
            <PlusIcon class="w-4 h-4 mr-2" />
            Abrir Caja
          </UiButton>
          <UiButton
            v-if="canClose"
            variant="danger"
            :loading="loading"
            @click="showCloseModal = true"
          >
            <XMarkIcon class="w-4 h-4 mr-2" />
            Cerrar Caja
          </UiButton>
        </template>
      </PageHeader>

      <!-- Dashboard en Tiempo Real -->
      <div
        v-if="hasActiveSession"
        class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6"
      >
        <!-- Monto de Apertura -->
        <UiCard variant="glass">
          <div class="flex items-center">
            <div class="w-12 h-12 bg-systemBlue-100 rounded-xl flex items-center justify-center">
              <BanknotesIcon class="w-6 h-6 text-systemBlue-600" />
            </div>
            <div class="ml-4">
              <p class="text-sm font-medium text-theme-secondary">Apertura</p>
              <p class="text-2xl font-bold text-theme-primary tabular-nums">
                {{ formatCurrency(summary?.opening_amount) }}
              </p>
            </div>
          </div>
        </UiCard>

        <!-- Total Ingresos -->
        <UiCard variant="glass">
          <div class="flex items-center">
            <div class="w-12 h-12 bg-systemGreen-100 rounded-xl flex items-center justify-center">
              <ArrowUpIcon class="w-6 h-6 text-systemGreen-600" />
            </div>
            <div class="ml-4">
              <p class="text-sm font-medium text-theme-secondary">Ingresos</p>
              <p class="text-2xl font-bold text-systemGreen-600 tabular-nums">
                {{ formatCurrency(summary?.total_income) }}
              </p>
            </div>
          </div>
        </UiCard>

        <!-- Total Egresos -->
        <UiCard variant="glass">
          <div class="flex items-center">
            <div class="w-12 h-12 bg-systemRed-100 rounded-xl flex items-center justify-center">
              <ArrowDownIcon class="w-6 h-6 text-systemRed-600" />
            </div>
            <div class="ml-4">
              <p class="text-sm font-medium text-theme-secondary">Egresos</p>
              <p class="text-2xl font-bold text-systemRed-600 tabular-nums">
                {{ formatCurrency(summary?.total_expenses) }}
              </p>
            </div>
          </div>
        </UiCard>

        <!-- Saldo Actual -->
        <UiCard variant="glass">
          <div class="flex items-center">
            <div class="w-12 h-12 bg-systemBlue-100 rounded-xl flex items-center justify-center">
              <BanknotesIcon class="w-6 h-6 text-systemBlue-600" />
            </div>
            <div class="ml-4">
              <p class="text-sm font-medium text-theme-secondary">Saldo Actual</p>
              <p class="text-2xl font-bold text-systemBlue-600 tabular-nums">
                {{ formatCurrency(realTimeTotals?.currentBalance) }}
              </p>
            </div>
          </div>
        </UiCard>
      </div>

      <!-- Tabs de Navegación -->
      <div class="mb-6">
        <UiTabs v-model="activeTab" :tabs="tabs" />
      </div>

      <!-- Contenido de las Tabs -->
      <div class="space-y-6">
        <!-- Tab: Pagos -->
        <div v-if="activeTab === 'payments'">
          <div class="flex justify-between items-center mb-4">
            <h2 class="text-lg font-semibold text-theme-primary">Cobros de Pacientes</h2>
            <UiButton variant="primary" @click="showPaymentModal = true">
              <PlusIcon class="w-4 h-4 mr-2" />
              Registrar Cobro
            </UiButton>
          </div>

          <PendingPaymentsList
            :key="pendingPaymentsKey"
            @payment="handlePaymentFromList"
            @payments-loaded="handlePendingPaymentsLoaded"
          />
        </div>

        <!-- Tab: Cobros -->
        <div v-if="activeTab === 'transactions'">
          <div class="flex justify-between items-center mb-4">
            <h2 class="text-lg font-semibold text-theme-primary">Transacciones</h2>
            <UiButton
              v-if="canCreateTransaction"
              variant="primary"
              :disabled="!hasActiveSession"
              @click="showTransactionModal = true"
            >
              <PlusIcon class="w-4 h-4 mr-2" />
              Nueva Transacción
            </UiButton>
          </div>

          <TransactionList
            :transactions="transactions"
            :loading="loading"
            :pagination="pagination"
            @refresh="loadTransactions"
            @edit="editTransaction"
            @void="voidTransaction"
            @receipt="generateReceipt"
          />
        </div>

        <!-- Tab: Movimientos -->
        <div v-if="activeTab === 'movements'">
          <div class="flex justify-between items-center mb-4">
            <h2 class="text-lg font-semibold text-theme-primary">Movimientos de Caja</h2>
            <UiButton
              v-if="canCreateMovement"
              variant="primary"
              :disabled="!hasActiveSession"
              @click="showMovementModal = true"
            >
              <PlusIcon class="w-4 h-4 mr-2" />
              Nuevo Movimiento
            </UiButton>
          </div>

          <MovementList
            :movements="movements"
            :loading="loading"
            :pagination="movementPagination"
            :summary="summary"
            @refresh="loadMovements"
          />
        </div>

        <!-- Tab: Historial -->
        <div v-if="activeTab === 'history'">
          <div class="flex justify-between items-center mb-4">
            <h2 class="text-lg font-semibold text-theme-primary">Historial de Sesiones</h2>
            <UiButton variant="secondary" :loading="loading" @click="loadSessions">
              <ArrowPathIcon class="w-4 h-4 mr-2" />
              Actualizar
            </UiButton>
          </div>

          <SessionList
            :sessions="sessions"
            :loading="loading"
            :pagination="sessionPagination"
            @refresh="loadSessions"
            @view="viewSession"
          />
        </div>

        <!-- Tab: Reportes -->
        <div v-if="activeTab === 'reports'">
          <div class="flex justify-between items-center mb-4">
            <h2 class="text-lg font-semibold text-theme-primary">Reportes de Caja</h2>
            <UiButton variant="primary" :loading="loading" @click="generateReport">
              <DocumentArrowDownIcon class="w-4 h-4 mr-2" />
              Generar Reporte
            </UiButton>
          </div>

          <CashReports :summary="summary" :loading="loading" @export="exportReport" />
        </div>
      </div>

      <!-- Modales (slice 07 / T-07.19: unify modal contract on v-model) -->
      <OpenCashModal v-model="showOpenModal" @success="handleOpenSuccess" />

      <CloseCashModal
        v-model="showCloseModal"
        :session="currentSession"
        :summary="summary"
        @success="handleCloseSuccess"
      />

      <TransactionModal v-model="showTransactionModal" @success="handleTransactionSuccess" />

      <MovementModal
        v-model="showMovementModal"
        :session="currentSession"
        @success="handleMovementSuccess"
      />

      <ReceiptPreview
        v-model="showReceiptModal"
        :transaction="selectedTransaction"
        @print="handlePrint"
        @download="handleDownload"
      />

      <!-- Modal de Pagos -->
      <PaymentModal
        v-model="showPaymentModal"
        :selected-patient="selectedPaymentPatient"
        :selected-appointment="selectedPaymentAppointment"
        :pending-patients="pendingPatientsForModal"
        @update:model-value="handlePaymentModalClose"
        @success="handlePaymentSuccess"
      />
    </div>
  </AppLayout>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'
import AppLayout from '@/components/layout/AppLayout.vue'
import { useCashRegister } from '@/composables/useCashRegister'
import { useTransactions } from '@/composables/useTransactions'
import { usePermissions } from '@/composables/usePermissions'
import { useToast } from '@/composables/useToast'
import { useApi } from '@/composables/useApi'
import { useConfirm } from '@/composables/useConfirm'
import { formatCurrency } from '@/composables/useFormatters'

// Components
import UiCard from '@/components/ui/Card.vue'
import UiButton from '@/components/ui/Button.vue'
import UiTabs from '@/components/ui/Tabs.vue'
import UiStatusBadge from '@/components/ui/StatusBadge.vue'
import TransactionList from './components/TransactionList.vue'
import MovementList from './components/MovementList.vue'
import SessionList from './components/SessionList.vue'
import CashReports from './components/CashReports.vue'
import PendingPaymentsList from './components/PendingPaymentsList.vue'
import PaymentModal from './components/PaymentModal.vue'
import OpenCashModal from './components/OpenCashModal.vue'
import CloseCashModal from './components/CloseCashModal.vue'
import TransactionModal from './components/TransactionModal.vue'
import MovementModal from './components/MovementModal.vue'
import ReceiptPreview from '@/components/ui/ReceiptPreview.vue'

// Icons
import {
  PlusIcon,
  XMarkIcon,
  ArrowUpIcon,
  ArrowDownIcon,
  BanknotesIcon,
  ArrowPathIcon,
  DocumentArrowDownIcon
} from '@heroicons/vue/24/outline'

// Composables
const {
  currentSession,
  summary,
  loading,
  hasActiveSession,
  isOpen,
  isClosed,
  realTimeTotals,
  loadCurrentSession,
  openSession,
  closeSession,
  getSessions,
  setupWebSocketSubscriptions
} = useCashRegister()

const {
  transactions,
  pagination,
  getTransactions,
  createTransaction,
  voidTransaction: voidTransactionApi,
  generateReceipt: generateReceiptApi
} = useTransactions()

const { createTransaction: canCreateTransaction, createMovement: canCreateMovement } =
  usePermissions()
const { get } = useApi()
const toast = useToast()
const { confirm } = useConfirm()

// Estado
const activeTab = ref('payments')
const showOpenModal = ref(false)
const showCloseModal = ref(false)
const showPaymentModal = ref(false)
const showTransactionModal = ref(false)
const showMovementModal = ref(false)
const showReceiptModal = ref(false)
const selectedTransaction = ref(null)
const selectedPaymentPatient = ref(null)
const selectedPaymentAppointment = ref(null)
const pendingPaymentsList = ref([])
const pendingPaymentsKey = ref(0)
const sessions = ref([])
const sessionPagination = ref(null)
const movements = ref([])
const movementPagination = ref(null)

// Tabs
const tabs = ref([
  { id: 'payments', label: 'Cobros', icon: BanknotesIcon },
  { id: 'transactions', label: 'Transacciones', icon: BanknotesIcon },
  { id: 'movements', label: 'Movimientos', icon: ArrowUpIcon },
  { id: 'history', label: 'Historial', icon: DocumentArrowDownIcon },
  { id: 'reports', label: 'Reportes', icon: DocumentArrowDownIcon }
])

// Computed
// Session state drives the <UiStatusBadge> variant (PR-pagos-05a): the
// hardcoded green/red dot classes are owned by the badge primitive now.
const sessionStatusVariant = computed(() => {
  if (isOpen.value) return 'success'
  if (isClosed.value) return 'error'
  return 'neutral'
})

const sessionStatusText = computed(() => {
  if (isOpen.value) return 'Sesión Abierta'
  if (isClosed.value) return 'Sesión Cerrada'
  return 'Sin Sesión'
})

const canOpen = computed(() => {
  return !hasActiveSession.value && !loading.value
})

const canClose = computed(() => {
  return isOpen.value && !loading.value
})

// Métodos
const loadTransactions = async () => {
  if (!hasActiveSession.value) {
    return
  }

  try {
    loading.value = true

    const response = await getTransactions({
      cash_register_session_id: currentSession.value.id
    })

    transactions.value = response.data || []
    pagination.value = response.meta || null
  } catch (error) {
    toast.error('Error al cargar transacciones')
  } finally {
    loading.value = false
  }
}

const loadMovements = async () => {
  if (!hasActiveSession.value) {
    return
  }

  try {
    const response = await get(`/api/cash-register/sessions/${currentSession.value.id}/movements`)

    movements.value = response.data || []
    movementPagination.value = response.meta || null
  } catch (error) {
    toast.error('Error al cargar movimientos')
  }
}

const loadSessions = async () => {
  try {
    const response = await getSessions()
    sessions.value = response.data || []
    sessionPagination.value = response.meta || null
  } catch (error) {
    toast.error('Error al cargar sesiones')
  }
}

const handleOpenSuccess = session => {
  currentSession.value = session
  showOpenModal.value = false

  toast.success('Sesión de caja abierta exitosamente')

  // Los eventos WebSocket se manejan automáticamente en useCashRegister
  loadTransactions()
  loadMovements()
}

const handleCloseSuccess = async result => {
  currentSession.value = null
  showCloseModal.value = false

  toast.success('Sesión de caja cerrada exitosamente')

  // Los eventos WebSocket se manejan automáticamente en useCashRegister
  await loadSessions()
}

const handleTransactionSuccess = async transaction => {
  toast.success('Transacción registrada exitosamente')

  // Pequeño delay para asegurar que la BD se haya actualizado
  await new Promise(resolve => setTimeout(resolve, 300))

  // Recargar sesión para actualizar el resumen (incluye las tarjetas)
  await loadCurrentSession()

  // Recargar lista de transacciones
  await loadTransactions()
  // Recargar movimientos para mantener consistencia
  await loadMovements()
}

const handleMovementSuccess = async movement => {
  toast.success('Movimiento registrado exitosamente')
  await loadCurrentSession()
  await loadMovements()
}

const handlePaymentSuccess = async paymentData => {
  const { patient, amount, concept, paymentMethod, transactionNumber } = paymentData
  toast.success(
    'Cobro registrado exitosamente\n' +
      `Paciente: ${patient?.name || 'N/A'}\n` +
      `Monto: S/ ${amount}\n` +
      `Concepto: ${concept}\n` +
      `Método: ${paymentMethod?.name || 'N/A'}\n` +
      `N° Transacción: ${transactionNumber}`,
    {
      duration: 7000,
      title: '✓ Cobro Exitoso'
    }
  )

  // Pequeño delay para asegurar que la BD se haya actualizado
  await new Promise(resolve => setTimeout(resolve, 500))

  // Forzar actualización de datos de caja
  await loadCurrentSession()

  // Esperar un tick adicional para asegurar que Vue procese los cambios
  await new Promise(resolve => setTimeout(resolve, 100))

  // Recargar listas
  await loadTransactions()
  await loadMovements()

  reloadPendingPayments()
}

// Método para recargar pagos pendientes
const reloadPendingPayments = () => {
  pendingPaymentsKey.value++
}

const handlePaymentFromList = payment => {
  // Pre-llenar el modal con los datos del pago pendiente
  selectedPaymentPatient.value = {
    id: payment.patient.id,
    name: payment.patient.name,
    document_number: payment.patient.document_number,
    email: payment.patient.email,
    phone: payment.patient.phone,
    concept: payment.concept,
    amount: payment.amount
  }
  selectedPaymentAppointment.value = {
    id: payment.appointment.id,
    date: payment.appointment.date,
    appointment_type: payment.appointment.appointment_type
  }
  showPaymentModal.value = true
}

const handlePendingPaymentsLoaded = payments => {
  pendingPaymentsList.value = payments
}

const pendingPatientsForModal = computed(() => {
  // Extraer pacientes únicos de los pagos pendientes
  const uniquePatients = new Map()
  pendingPaymentsList.value.forEach(payment => {
    if (!uniquePatients.has(payment.patient.id)) {
      uniquePatients.set(payment.patient.id, payment.patient)
    }
  })
  return Array.from(uniquePatients.values())
})

const handlePaymentModalClose = () => {
  // v-model handles the boolean now; only the cleanup of the selected
  // patient/appointment refs remains.
  setTimeout(() => {
    selectedPaymentPatient.value = null
    selectedPaymentAppointment.value = null
  }, 300)
}

const editTransaction = transaction => {
  // Implementar edición de transacción
}

const generateReport = async () => {
  loading.value = true
  try {
    const response = await get('/api/cash-register/reports/period', {
      params: {
        start_date: new Date().toISOString().split('T')[0],
        end_date: new Date().toISOString().split('T')[0]
      }
    })

    summary.value = response.data
    toast.success('Reporte generado exitosamente')
  } catch (error) {
    toast.error('Error al generar reporte')
  } finally {
    loading.value = false
  }
}

const exportReport = async filters => {
  // Este método será llamado por CashReports
}

const voidTransaction = async transaction => {
  const ok = await confirm({
    title: 'Anular transacción',
    message: '¿Está seguro de anular esta transacción?',
    confirmText: 'Anular',
    variant: 'danger'
  })
  if (!ok) return

  try {
    await voidTransactionApi(transaction.id, 'Anulación manual')
    toast.success('Transacción anulada exitosamente')
    loadTransactions()
  } catch (error) {
    toast.error('Error al anular transacción')
  }
}

const generateReceipt = transaction => {
  selectedTransaction.value = transaction
  showReceiptModal.value = true
}

const handlePrint = transaction => {
  toast.info('Imprimiendo comprobante...')
}

const handleDownload = transaction => {
  toast.info('Descargando comprobante...')
}

// formatCurrency is imported from useFormatters (PR-pagos-01 canonicalization).

// Lifecycle
onMounted(async () => {
  await loadCurrentSession()
  if (hasActiveSession.value) {
    await loadTransactions()
    await loadMovements()
  } else {
  }
  await loadSessions()

  // Configurar WebSockets para actualizaciones en tiempo real
  setupWebSocketSubscriptions()
})

onUnmounted(() => {
  // La limpieza de WebSockets se maneja automáticamente en useCashRegister
})
</script>
