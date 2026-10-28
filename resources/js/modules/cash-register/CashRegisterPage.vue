<template>
  <AppLayout>
    <div class="space-y-6">
      <header
        data-caja-hero
        class="p-6 md:p-8"
        style="
          background: var(--color-canvas);
          border: 1px solid var(--color-hairline);
          border-radius: var(--radius-panel);
          box-shadow: var(--elevation-2);
        "
      >
        <div class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
          <div class="min-w-0">
            <p
              class="text-xs font-medium uppercase tracking-wide text-theme-secondary"
              data-hero-eyebrow
            >
              Operación
            </p>
            <h2 class="mt-2 text-4xl font-semibold text-label" data-hero-title>Caja</h2>
            <p class="mt-2 text-sm text-theme-secondary" data-hero-caption>
              Sesiones, cobros y movimientos del turno
            </p>
          </div>
          <div class="flex flex-wrap items-center gap-3 md:justify-end">
            <UiStatusBadge
              data-hero-state
              :variant="sessionStatusVariant"
              :label="sessionStatusText"
              size="md"
              show-dot
            />
            <UiButton
              v-if="canOpen"
              variant="primary"
              data-hero-action="open"
              @click="showOpenModal = true"
            >
              <template #icon-left>
                <PlusIcon class="w-4 h-4" aria-hidden="true" />
              </template>
              Abrir caja
            </UiButton>
            <UiButton
              v-if="canClose"
              variant="danger"
              data-hero-action="close"
              @click="showCloseModal = true"
            >
              <template #icon-left>
                <LockClosedIcon class="w-4 h-4" aria-hidden="true" />
              </template>
              Cerrar caja
            </UiButton>
            <UiButton
              v-if="canDeposit"
              variant="secondary"
              data-hero-action="deposit"
              @click="showDepositModal = true"
            >
              <template #icon-left>
                <BanknotesIcon class="w-4 h-4" aria-hidden="true" />
              </template>
              Depositar
            </UiButton>
          </div>
        </div>
      </header>

      <div
        v-if="loadError"
        role="alert"
        data-caja-error
        class="flex flex-col gap-3 p-4 sm:flex-row sm:items-center sm:justify-between"
        style="
          background: var(--color-canvas);
          border: 1px solid var(--color-hairline);
          border-radius: var(--radius-panel);
          box-shadow: var(--elevation-1);
        "
      >
        <p class="text-sm text-systemRed-600">No se pudo cargar la caja: {{ loadError }}</p>
        <UiButton variant="secondary" size="sm" data-caja-retry @click="reloadSession">
          Reintentar
        </UiButton>
      </div>

      <section
        aria-label="Resumen de caja"
        class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-4"
      >
        <UiCard
          variant="glass"
          padding="sm"
          data-kpi-card="opening"
          :style="{ boxShadow: 'var(--elevation-2)', borderColor: 'var(--color-hairline)' }"
        >
          <p class="text-xs font-medium uppercase text-theme-secondary">Apertura</p>
          <p
            class="mt-2 text-2xl font-bold leading-none text-label tabular-nums"
            data-kpi-value="opening"
          >
            {{ kpiOpening }}
          </p>
          <p class="mt-1 text-xs text-theme-secondary" data-kpi-caption="opening">Monto inicial</p>
        </UiCard>

        <UiCard
          variant="glass"
          padding="sm"
          data-kpi-card="income"
          :style="{ boxShadow: 'var(--elevation-2)', borderColor: 'var(--color-hairline)' }"
        >
          <p class="text-xs font-medium uppercase text-theme-secondary">Cobros</p>
          <p
            class="mt-2 text-2xl font-bold leading-none text-label tabular-nums"
            data-kpi-value="income"
          >
            {{ kpiIncome }}
          </p>
          <p class="mt-1 text-xs text-theme-secondary" data-kpi-caption="income">Ventas cobradas</p>
        </UiCard>

        <UiCard
          variant="glass"
          padding="sm"
          data-kpi-card="expenses"
          :style="{ boxShadow: 'var(--elevation-2)', borderColor: 'var(--color-hairline)' }"
        >
          <p class="text-xs font-medium uppercase text-theme-secondary">Egresos</p>
          <p
            class="mt-2 text-2xl font-bold leading-none text-label tabular-nums"
            data-kpi-value="expenses"
          >
            {{ kpiExpenses }}
          </p>
          <p class="mt-1 text-xs text-theme-secondary" data-kpi-caption="expenses">Reembolsos</p>
        </UiCard>

        <UiCard
          variant="glass"
          padding="sm"
          data-kpi-card="expected"
          :style="{ boxShadow: 'var(--elevation-2)', borderColor: 'var(--color-hairline)' }"
        >
          <p class="text-xs font-medium uppercase text-theme-secondary">Saldo esperado</p>
          <p
            class="mt-2 text-2xl font-bold leading-none text-label tabular-nums"
            data-kpi-value="expected"
          >
            {{ kpiExpected }}
          </p>
          <p class="mt-1 text-xs text-theme-secondary" data-kpi-caption="expected">
            Apertura + movimientos
          </p>
        </UiCard>
      </section>

      <UiCard
        v-if="isClosed"
        data-caja-closing
        variant="glass"
        padding="md"
        :style="{ boxShadow: 'var(--elevation-2)', borderColor: 'var(--color-hairline)' }"
      >
        <div class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
          <div class="min-w-0">
            <p class="text-xs font-medium uppercase tracking-wide text-theme-secondary">
              Cierre de caja
            </p>
            <p class="mt-1 text-sm text-theme-secondary" data-caja-closing-closed-at>
              Cerrada el {{ kpiClosedAt }}
            </p>
          </div>
          <dl class="grid grid-cols-1 gap-4 sm:grid-cols-3 sm:gap-8">
            <div>
              <dt class="text-xs font-medium uppercase text-theme-secondary">Contado</dt>
              <dd
                class="mt-1 text-xl font-semibold leading-none text-label tabular-nums"
                data-caja-closing-amount
              >
                {{ kpiClosingAmount }}
              </dd>
            </div>
            <div>
              <dt class="text-xs font-medium uppercase text-theme-secondary">Esperado</dt>
              <dd
                class="mt-1 text-xl font-semibold leading-none text-label tabular-nums"
                data-caja-closing-expected
              >
                {{ kpiClosingExpected }}
              </dd>
            </div>
            <div>
              <dt class="text-xs font-medium uppercase text-theme-secondary">Diferencia</dt>
              <dd
                class="mt-1 text-xl font-semibold leading-none text-label tabular-nums"
                data-caja-closing-difference
              >
                {{ kpiClosingDifference }}
              </dd>
            </div>
          </dl>
        </div>
      </UiCard>

      <UiTabs v-model="activeTab" :tabs="tabs" data-caja-tabs />

      <div v-if="activeTab === 'payments'">
        <CashSectionHeader
          title="Cobros de pacientes"
          caption="Citas completadas con saldo pendiente"
        >
          <template #actions>
            <UiButton variant="primary" data-section-action="payment" @click="openPaymentModal()">
              <template #icon-left>
                <PlusIcon class="w-4 h-4" aria-hidden="true" />
              </template>
              Registrar cobro
            </UiButton>
          </template>
        </CashSectionHeader>

        <PendingPaymentsList
          :key="pendingPaymentsKey"
          @payment="handlePaymentFromList"
          @payments-loaded="handlePendingPaymentsLoaded"
        />
      </div>

      <div v-if="activeTab === 'transactions'">
        <CashSectionHeader title="Transacciones" caption="Cobros y reembolsos de la sesión">
          <template #actions>
            <UiButton
              v-if="canCreateTransaction"
              variant="primary"
              data-section-action="transaction"
              :disabled="!isOpen"
              @click="showTransactionModal = true"
            >
              <template #icon-left>
                <PlusIcon class="w-4 h-4" aria-hidden="true" />
              </template>
              Nueva transacción
            </UiButton>
          </template>
        </CashSectionHeader>

        <TransactionList
          :transactions="transactions"
          :loading="transactionsLoading"
          :pagination="pagination"
          :can-void="canVoid"
          @refresh="loadTransactions"
          @void="voidTransaction"
          @receipt="downloadReceipt"
        />
      </div>

      <div v-if="activeTab === 'movements'">
        <CashSectionHeader
          title="Movimientos de caja"
          caption="Entradas, salidas y retiros del turno"
        >
          <template #actions>
            <UiButton
              v-if="canCreateMovement"
              variant="primary"
              data-section-action="movement"
              :disabled="!isOpen"
              @click="showMovementModal = true"
            >
              <template #icon-left>
                <PlusIcon class="w-4 h-4" aria-hidden="true" />
              </template>
              Nuevo movimiento
            </UiButton>
          </template>
        </CashSectionHeader>

        <MovementList
          :movements="movements"
          :loading="movementsLoading"
          :pagination="movementPagination"
          :summary="summary"
          @refresh="loadMovements"
        />
      </div>

      <div v-if="activeTab === 'history'">
        <CashSectionHeader title="Historial de sesiones" caption="Turnos abiertos y cerrados">
          <template #actions>
            <UiButton
              variant="secondary"
              data-section-action="refresh-sessions"
              :loading="sessionsLoading"
              @click="loadSessions()"
            >
              <template #icon-left>
                <ArrowPathIcon class="w-4 h-4" aria-hidden="true" />
              </template>
              Actualizar
            </UiButton>
          </template>
        </CashSectionHeader>

        <SessionList
          :sessions="sessions"
          :loading="sessionsLoading"
          :pagination="sessionPagination"
          @refresh="loadSessions"
        />
      </div>

      <div v-if="activeTab === 'reports'">
        <CashSectionHeader
          title="Reportes de caja"
          caption="Cierre y ventas por período"
        ></CashSectionHeader>

        <CashReports />
      </div>

      <OpenCashModal v-model="showOpenModal" @success="handleOpenSuccess" />

      <CloseCashModal
        v-model="showCloseModal"
        :session="currentSession"
        :summary="summary"
        :deposits-count="depositsCount"
        :deposits-total="depositsTotal"
        @success="handleCloseSuccess"
      />

      <CashDepositModal
        v-model="showDepositModal"
        :session="currentSession"
        :available-cash="depositAvailableCash"
        @success="handleDepositSuccess"
      />

      <TransactionModal v-model="showTransactionModal" @success="handleTransactionSuccess" />

      <MovementModal
        v-model="showMovementModal"
        :session="currentSession"
        @success="handleMovementSuccess"
      />

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
import { ref, computed, onMounted } from 'vue'
import AppLayout from '@/components/layout/AppLayout.vue'
import { useCashRegister } from '@/composables/useCashRegister'
import { useTransactions } from '@/composables/useTransactions'
import { usePermissions } from '@/composables/usePermissions'
import { useToast } from '@/composables/useToast'
import { useApi } from '@/composables/useApi'
import { useConfirm } from '@/composables/useConfirm'
import { formatCurrency, formatDateTime } from '@/composables/useFormatters'

import UiButton from '@/components/ui/Button.vue'
import UiCard from '@/components/ui/Card.vue'
import UiTabs from '@/components/ui/Tabs.vue'
import UiStatusBadge from '@/components/ui/StatusBadge.vue'
import CashSectionHeader from './components/CashSectionHeader.vue'
import TransactionList from './components/TransactionList.vue'
import MovementList from './components/MovementList.vue'
import SessionList from './components/SessionList.vue'
import CashReports from './components/CashReports.vue'
import PendingPaymentsList from './components/PendingPaymentsList.vue'
import PaymentModal from './components/PaymentModal.vue'
import OpenCashModal from './components/OpenCashModal.vue'
import CloseCashModal from './components/CloseCashModal.vue'
import CashDepositModal from './components/CashDepositModal.vue'
import TransactionModal from './components/TransactionModal.vue'
import MovementModal from './components/MovementModal.vue'

import { PlusIcon, LockClosedIcon, BanknotesIcon, ArrowPathIcon } from '@heroicons/vue/24/outline'

const {
  currentSession,
  summary,
  closing,
  loading: sessionLoading,
  error: loadError,
  isOpen,
  isClosed,
  loadCurrentSession,
  getSessions
} = useCashRegister()

const {
  transactions,
  pagination,
  getTransactions,
  voidTransaction: voidTransactionApi
} = useTransactions()

const { can } = usePermissions()
const { get, download } = useApi()
const toast = useToast()
const { confirm } = useConfirm()

const activeTab = ref('transactions')
const showOpenModal = ref(false)
const showCloseModal = ref(false)
const showDepositModal = ref(false)
const showPaymentModal = ref(false)
const showTransactionModal = ref(false)
const showMovementModal = ref(false)
const transactionsLoading = ref(false)
const movementsLoading = ref(false)
const sessionsLoading = ref(false)
const selectedPaymentPatient = ref(null)
const selectedPaymentAppointment = ref(null)
const pendingPaymentsList = ref([])
const pendingPaymentsKey = ref(0)
const sessions = ref([])
const sessionPagination = ref(null)
const movements = ref([])
const movementPagination = ref(null)
const deposits = ref([])

const canCreateTransaction = computed(() => can.createTransaction.value)
const canCreateMovement = computed(() => can.createMovement.value)
const canVoid = computed(() => can.voidTransaction.value)
const canViewReports = computed(() => can.viewCashReports.value)
const canViewPendingPayments = computed(() => can.collectPendingPayments.value)

const tabs = computed(() => {
  const items = []
  if (canViewPendingPayments.value) items.push({ id: 'payments', label: 'Cobros' })
  items.push({ id: 'transactions', label: 'Transacciones' })
  items.push({ id: 'movements', label: 'Movimientos' })
  items.push({ id: 'history', label: 'Historial' })
  if (canViewReports.value) items.push({ id: 'reports', label: 'Reportes' })
  return items
})

if (canViewPendingPayments.value) {
  activeTab.value = 'payments'
}

const sessionStatusVariant = computed(() => {
  if (isOpen.value) return 'success'
  if (isClosed.value) return 'error'
  return 'neutral'
})

const sessionStatusText = computed(() => {
  if (isOpen.value) return 'Sesión abierta'
  if (isClosed.value) return 'Sesión cerrada'
  return 'Sin sesión'
})

const canOpen = computed(() => !isOpen.value && !sessionLoading.value)
const canClose = computed(() => isOpen.value && !sessionLoading.value)
// #320 (S6): registering a deposit is counter work, so the permission is what
// keeps clinical desks out of this button. #407 adds the other half: only an
// open drawer takes deposits, because a closed session is history.
const canDeposit = computed(() => isOpen.value && can.manageCashDeposit.value)

const roundMoney = value => Math.round((Number(value) || 0) * 100) / 100

// The drawer's cash is the deposit ceiling: the modal blocks above it and the
// service re-reads the locked session, so the number is a hint, not the rule.
const depositAvailableCash = computed(() => summary.value?.expected_amount ?? null)

const depositsTotal = computed(() =>
  roundMoney(deposits.value.reduce((sum, deposit) => sum + (Number(deposit?.amount) || 0), 0))
)

const depositsCount = computed(() => deposits.value.length)

const kpiOpening = computed(() =>
  summary.value ? formatCurrency(summary.value.opening_amount) : 'N/D'
)
const kpiIncome = computed(() =>
  summary.value ? formatCurrency(summary.value.total_income) : 'N/D'
)
const kpiExpenses = computed(() =>
  summary.value ? formatCurrency(summary.value.total_expenses) : 'N/D'
)
const kpiExpected = computed(() =>
  summary.value ? formatCurrency(summary.value.expected_amount) : 'N/D'
)

// #407: the closing block is the only read of a closed drawer on this screen.
const closingMoney = value =>
  value === null || value === undefined ? 'N/D' : formatCurrency(value)
const kpiClosingAmount = computed(() => closingMoney(closing.value?.closing_amount))
const kpiClosingExpected = computed(() => closingMoney(closing.value?.expected_amount))
const kpiClosingDifference = computed(() => closingMoney(closing.value?.difference_amount))
const kpiClosedAt = computed(() =>
  closing.value?.closed_at ? formatDateTime(closing.value.closed_at) : 'N/D'
)

const pendingPatientsForModal = computed(() => {
  const uniquePatients = new Map()
  pendingPaymentsList.value.forEach(payment => {
    if (!uniquePatients.has(payment.patient.id)) {
      uniquePatients.set(payment.patient.id, payment.patient)
    }
  })
  return Array.from(uniquePatients.values())
})

const reloadSession = async () => {
  await loadCurrentSession()
  if (isOpen.value) {
    await Promise.all([loadTransactions(), loadMovements(), loadDeposits()])
  } else {
    deposits.value = []
  }
}

const loadTransactions = async (filters = {}) => {
  if (!isOpen.value) {
    return
  }

  transactionsLoading.value = true
  try {
    const response = await getTransactions({
      cash_register_session_id: currentSession.value.id,
      ...filters
    })
    transactions.value = response.data || []
    pagination.value = response.meta || null
  } catch (error) {
    toast.error('Error al cargar transacciones')
  } finally {
    transactionsLoading.value = false
  }
}

const loadMovements = async (filters = {}) => {
  if (!isOpen.value) {
    return
  }

  movementsLoading.value = true
  try {
    const response = await get(`/api/cash-register/sessions/${currentSession.value.id}/movements`, {
      params: filters
    })
    movements.value = response.data || []
    movementPagination.value = response.meta || null
  } catch (error) {
    toast.error('Error al cargar movimientos')
  } finally {
    movementsLoading.value = false
  }
}

const loadDeposits = async () => {
  if (!isOpen.value) {
    deposits.value = []
    return
  }

  try {
    const response = await get('/api/cash-register/deposits', {
      params: {
        cash_register_session_id: currentSession.value.id,
        per_page: 100
      }
    })
    const rows = Array.isArray(response?.data) ? response.data : []
    deposits.value = rows.filter(row => row && typeof row === 'object')
  } catch {
    deposits.value = []
    toast.error('Error al cargar los depósitos del turno')
  }
}

const sessionFilters = ref({})

const loadSessions = async (filters = sessionFilters.value) => {
  sessionFilters.value = filters
  sessionsLoading.value = true
  try {
    const response = await getSessions(filters)
    sessions.value = response.data || []
    sessionPagination.value = response.meta || null
  } catch (error) {
    toast.error('Error al cargar sesiones')
  } finally {
    sessionsLoading.value = false
  }
}

const handleOpenSuccess = session => {
  showOpenModal.value = false

  if (session) {
    currentSession.value = session
  }

  toast.success('Sesión de caja abierta exitosamente')

  reloadSession()
}

const handleCloseSuccess = async () => {
  showCloseModal.value = false
  toast.success('Sesión de caja cerrada exitosamente')
  // The next session owns its own deposits; the closed one is history.
  deposits.value = []
  await Promise.all([loadCurrentSession(), loadSessions()])
}

const handleDepositSuccess = async () => {
  showDepositModal.value = false
  toast.success('Depósito registrado exitosamente')
  await Promise.all([loadCurrentSession(), loadDeposits()])
}

const handleTransactionSuccess = async () => {
  toast.success('Transacción registrada exitosamente')
  await reloadSession()
}

const handleMovementSuccess = async () => {
  toast.success('Movimiento registrado exitosamente')
  await Promise.all([loadCurrentSession(), loadMovements()])
}

const handlePaymentSuccess = async paymentData => {
  const { patient, amount, concept, paymentMethod, transactionNumber } = paymentData
  toast.success(
    'Cobro registrado exitosamente\n' +
      `Paciente: ${patient?.name || 'N/A'}\n` +
      `Monto: ${formatCurrency(amount)}\n` +
      `Concepto: ${concept}\n` +
      `Método: ${paymentMethod?.name || 'N/A'}\n` +
      `N° Transacción: ${transactionNumber}`,
    {
      duration: 7000,
      title: 'Cobro registrado'
    }
  )

  await reloadSession()
  pendingPaymentsKey.value++
}

const openPaymentModal = () => {
  selectedPaymentPatient.value = null
  selectedPaymentAppointment.value = null
  showPaymentModal.value = true
}

const handlePaymentFromList = payment => {
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

const handlePaymentModalClose = () => {
  setTimeout(() => {
    selectedPaymentPatient.value = null
    selectedPaymentAppointment.value = null
  }, 300)
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
    await reloadSession()
  } catch (error) {
    toast.error('Error al anular transacción')
  }
}

const downloadReceipt = async transaction => {
  try {
    const { blob } = await download(`/api/transactions/${transaction.id}/receipt`, {
      method: 'POST'
    })
    const url = URL.createObjectURL(blob)
    window.open(url, '_blank', 'noopener')
    setTimeout(() => URL.revokeObjectURL(url), 60000)
  } catch (error) {
    toast.error('No se pudo descargar el comprobante')
  }
}

onMounted(async () => {
  await loadCurrentSession()
  if (isOpen.value) {
    await Promise.all([loadTransactions(), loadMovements(), loadDeposits()])
  }
  await loadSessions()
})
</script>
