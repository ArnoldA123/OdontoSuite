<template>
  <AppLayout>
    <!-- Loading State -->
    <LoadingSpinner v-if="loading" class="min-h-[400px]" size="lg" text="Cargando dashboard..." />

    <!-- Main Content -->
    <div v-else class="space-y-8">
      <!-- Stats Grid -->
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
        <!-- Today's Appointments -->
        <UiCard v-if="can.viewAppointment?.value" variant="glass" hover clickable @click="goToCalendar">
          <div class="flex items-center justify-between">
            <div>
              <p class="text-sm font-medium text-theme-secondary mb-1">Citas Hoy</p>
              <p class="text-3xl font-bold text-theme-primary">{{ stats.today || 0 }}</p>
              <p class="text-xs text-theme-secondary mt-1">{{ getTodayDate() }}</p>
            </div>
            <div class="w-12 h-12 bg-primary-50 rounded-xl flex items-center justify-center">
              <svg class="w-6 h-6 text-accent" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
              </svg>
            </div>
          </div>
        </UiCard>

        <!-- Total Patients -->
        <UiCard variant="glass" hover clickable @click="goToPatients">
          <div class="flex items-center justify-between">
            <div>
              <p class="text-sm font-medium text-theme-secondary mb-1">Pacientes</p>
              <p class="text-3xl font-bold text-theme-primary">{{ stats.total_patients || 0 }}</p>
              <p class="text-xs text-theme-secondary mt-1">Total registrados</p>
            </div>
            <div class="w-12 h-12 bg-success-badge rounded-xl flex items-center justify-center">
              <svg class="w-6 h-6 text-success-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-9a2.5 2.5 0 11-5 0 2.5 2.5 0 015 0z" />
              </svg>
            </div>
          </div>
        </UiCard>

        <!-- Professionals -->
        <UiCard v-if="can.manageUsers?.value" variant="glass" hover clickable @click="goToProfessionals">
          <div class="flex items-center justify-between">
            <div>
              <p class="text-sm font-medium text-theme-secondary mb-1">Profesionales</p>
              <p class="text-3xl font-bold text-theme-primary">{{ stats.total_professionals || 0 }}</p>
              <p class="text-xs text-theme-secondary mt-1">Equipo médico</p>
            </div>
            <div class="w-12 h-12 bg-warning-badge rounded-xl flex items-center justify-center">
              <svg class="w-6 h-6 text-warning-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
              </svg>
            </div>
          </div>
        </UiCard>

        <!-- Total Appointments -->
        <UiCard variant="glass" hover clickable @click="goToCalendar">
          <div class="flex items-center justify-between">
            <div>
              <p class="text-sm font-medium text-theme-secondary mb-1">Total Citas</p>
              <p class="text-3xl font-bold text-theme-primary">{{ stats.total_appointments_this_month || stats.total_appointments || 0 }}</p>
              <p class="text-xs text-theme-secondary mt-1">Este mes</p>
            </div>
            <div class="w-12 h-12 bg-primary-50 rounded-xl flex items-center justify-center">
              <svg class="w-6 h-6 text-accent" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
              </svg>
            </div>
          </div>
        </UiCard>

        <!-- Cash Register Status -->
        <UiCard v-if="can.viewCashRegister?.value" variant="glass" hover clickable @click="goToCashRegister">
          <div class="flex items-center justify-between">
            <div>
              <p class="text-sm font-medium text-theme-secondary mb-1">Estado de Caja</p>
              <p class="text-2xl font-bold" :class="cashStatusClass">{{ cashStatusText }}</p>
              <p class="text-xs text-theme-secondary mt-1">{{ cashBalanceText }}</p>
            </div>
            <div class="w-12 h-12 rounded-xl flex items-center justify-center" :class="cashStatusIconClass">
              <svg class="w-6 h-6" :class="cashStatusIconColor" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
              </svg>
            </div>
          </div>
        </UiCard>
      </div>

      <!-- Quick Actions -->
      <div class="space-y-6">
        <div class="flex items-center justify-between">
          <h2 class="text-xl font-semibold text-theme-primary">Acciones Rápidas</h2>
          <UiButton variant="ghost" size="sm" @click="goToCalendar">
            Ver calendario
            <template #icon-right>
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
              </svg>
            </template>
          </UiButton>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
          <!-- Patients -->
          <UiCard variant="flat" hover clickable @click="goToPatients">
            <div class="flex items-center gap-4">
              <div class="w-10 h-10 bg-primary-50 rounded-lg flex items-center justify-center">
                <svg class="w-5 h-5 text-accent" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-9a2.5 2.5 0 11-5 0 2.5 2.5 0 015 0z" />
                </svg>
              </div>
              <div class="flex-1">
                <h3 class="font-medium text-theme-primary">Pacientes</h3>
                <p class="text-sm text-theme-secondary">Gestionar base de datos</p>
              </div>
              <svg class="w-4 h-4 text-theme-secondary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
              </svg>
            </div>
          </UiCard>

          <!-- New Appointment -->
          <UiCard v-if="can.createAppointment?.value" variant="flat" hover clickable @click="goToNewAppointment">
            <div class="flex items-center gap-4">
              <div class="w-10 h-10 bg-success-badge rounded-lg flex items-center justify-center">
                <svg class="w-5 h-5 text-success-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                </svg>
              </div>
              <div class="flex-1">
                <h3 class="font-medium text-theme-primary">Nueva Cita</h3>
                <p class="text-sm text-theme-secondary">Programar cita médica</p>
              </div>
              <svg class="w-4 h-4 text-theme-secondary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
              </svg>
            </div>
          </UiCard>

          <!-- Professionals -->
          <UiCard v-if="can.manageUsers?.value" variant="flat" hover clickable @click="goToProfessionals">
            <div class="flex items-center gap-4">
              <div class="w-10 h-10 bg-warning-badge rounded-lg flex items-center justify-center">
                <svg class="w-5 h-5 text-warning-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                </svg>
              </div>
              <div class="flex-1">
                <h3 class="font-medium text-theme-primary">Profesionales</h3>
                <p class="text-sm text-theme-secondary">Gestionar equipo</p>
              </div>
              <svg class="w-4 h-4 text-theme-secondary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
              </svg>
            </div>
          </UiCard>

          <!-- Environments -->
          <UiCard v-if="can.manageConfig?.value" variant="flat" hover clickable @click="goToEnvironments">
            <div class="flex items-center gap-4">
              <div class="w-10 h-10 bg-primary-50 rounded-lg flex items-center justify-center">
                <svg class="w-5 h-5 text-accent" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                </svg>
              </div>
              <div class="flex-1">
                <h3 class="font-medium text-theme-primary">Ambientes</h3>
                <p class="text-sm text-theme-secondary">Configurar espacios</p>
              </div>
              <svg class="w-4 h-4 text-theme-secondary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
              </svg>
            </div>
          </UiCard>

          <!-- Business Intelligence -->
          <UiCard v-if="can.viewReports?.value" variant="flat" hover clickable @click="goToBusinessIntelligence">
            <div class="flex items-center gap-4">
              <div class="w-10 h-10 bg-danger-badge rounded-lg flex items-center justify-center">
                <svg class="w-5 h-5 text-error-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                </svg>
              </div>
              <div class="flex-1">
                <h3 class="font-medium text-theme-primary">Reportes</h3>
                <p class="text-sm text-theme-secondary">Análisis y estadísticas</p>
              </div>
              <svg class="w-4 h-4 text-theme-secondary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
              </svg>
            </div>
          </UiCard>
        </div>
      </div>

      <!-- Today's Appointments Preview -->
      <div v-if="todayAppointments.length > 0 && can.viewAppointment?.value" class="space-y-4">
        <div class="flex items-center justify-between">
          <h2 class="text-xl font-semibold text-theme-primary">Citas de Hoy</h2>
          <UiButton variant="ghost" size="sm" @click="goToCalendar">
            Ver todas
            <template #icon-right>
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
              </svg>
            </template>
          </UiButton>
        </div>

        <div class="grid gap-3">
          <UiCard
            v-for="appointment in todayAppointments.slice(0, 3)"
            :key="appointment.id"
            variant="flat"
            class="hover:shadow-soft transition-all duration-200"
          >
            <div class="flex items-center justify-between">
              <div class="flex items-center gap-4">
                <div class="w-10 h-10 bg-primary-50 rounded-lg flex items-center justify-center">
                  <svg class="w-5 h-5 text-accent" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                  </svg>
                </div>
                <div>
                  <p class="font-medium text-theme-primary">{{ appointment.patient?.name || 'Paciente' }}</p>
                  <p class="text-sm text-theme-secondary">{{ formatTime(appointment.scheduled_at) }} - {{ appointment.appointment_type?.name || 'Consulta' }}</p>
                </div>
              </div>
              <UiBadge :variant="getStatusVariant(appointment.status)" size="sm">
                {{ getStatusText(appointment.status) }}
              </UiBadge>
            </div>
          </UiCard>
        </div>
      </div>
    </div>

    <!-- New Appointment Modal -->
    <NewAppointmentModal
      v-model="showNewAppointmentModal"
      @created="handleAppointmentCreated"
    />
  </AppLayout>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import NewAppointmentModal from '../../components/appointments/NewAppointmentModal.vue'
import { useApi } from '../../composables/useApi'
import { useAuth } from '@/composables/useAuth'
import { usePermissions } from '../../composables/usePermissions'
import { useCashRegister } from '../../composables/useCashRegister'
import { useEcho } from '../../composables/useEcho'

const router = useRouter()
const route = useRoute()
const { user, isAuthenticated } = useAuth()
const { get } = useApi()
const { can } = usePermissions()
const { currentSession, summary, hasActiveSession, isOpen, realTimeTotals, loadCurrentSession } = useCashRegister()
const { channel, echo } = useEcho()

// State
const loading = ref(false)
const stats = ref({
  today: 0,
  appointments_today: 0,
  completed_today: 0,
  pending_confirmation: 0,
  this_week: 0,
  total_patients: 0,
  total_appointments: 0,
  total_professionals: 0,
  total_appointment_types: 0,
  total_dental_chairs: 0,
  total_income: 0,
  cash_session: null
})
const todayAppointments = ref([])

// Utility functions
const getGreeting = () => {
  const hour = new Date().getHours()
  if (hour < 12) return 'Buenos días'
  if (hour < 18) return 'Buenas tardes'
  return 'Buenas noches'
}

const getTodayDate = () => {
  return new Date().toLocaleDateString('es-ES', {
    weekday: 'long',
    year: 'numeric',
    month: 'long',
    day: 'numeric'
  })
}

const getRoleLabel = (role) => {
  const labels = {
    administrador: 'Administrador',
    recepcionista: 'Recepcionista',
    odontologo: 'Odontólogo',
    implantologo: 'Implantólogo',
    tecnico_dental: 'Técnico Dental',
    asistente: 'Asistente',
    finanzas: 'Finanzas'
  }
  return labels[role] || role
}

const formatTime = (dateTime) => {
  return new Date(dateTime).toLocaleTimeString('es-ES', {
    hour: '2-digit',
    minute: '2-digit'
  })
}

const getStatusText = (status) => {
  const texts = {
    scheduled: 'Programada',
    confirmed: 'Confirmada',
    in_consultation: 'En Consulta',
    completed: 'Completada',
    cancelled: 'Cancelada',
    no_show: 'No se presentó'
  }
  return texts[status] || status
}

const getStatusVariant = (status) => {
  const variants = {
    scheduled: 'secondary',
    confirmed: 'success',
    in_consultation: 'warning',
    completed: 'primary',
    cancelled: 'error',
    no_show: 'warning'
  }
  return variants[status] || 'secondary'
}

// Navigation functions
const goToCalendar = () => {
  router.push('/calendar')
}

const goToPatients = () => {
  router.push('/patients')
}

const showNewAppointmentModal = ref(false)

const goToNewAppointment = () => {
  showNewAppointmentModal.value = true
}

const handleAppointmentCreated = async (appointmentData) => {
  // Recargar datos del dashboard
  await loadDashboardData()
}

const goToProfessionals = () => {
  router.push('/professionals')
}

const goToCashRegister = () => {
  router.push('/cash-register')
}

// Cash register computed properties
const cashStatusText = computed(() => {
  if (isOpen.value) return 'Abierta'
  if (hasActiveSession.value) return 'Cerrada'
  return 'Sin sesión'
})

const cashStatusClass = computed(() => {
  if (isOpen.value) return 'text-success-600'
  if (hasActiveSession.value) return 'text-error-600'
  return 'text-theme-secondary'
})

const cashStatusIconClass = computed(() => {
  if (isOpen.value) return 'bg-success-badge'
  if (hasActiveSession.value) return 'bg-danger-badge'
  return 'bg-theme-surface'
})

const cashStatusIconColor = computed(() => {
  if (isOpen.value) return 'text-success-600'
  if (hasActiveSession.value) return 'text-error-600'
  return 'text-theme-secondary'
})

const cashBalanceText = computed(() => {
  if (isOpen.value && realTimeTotals.value) {
    return `Saldo: S/ ${formatCurrency(realTimeTotals.value.currentBalance)}`
  }
  if (hasActiveSession.value) {
    return 'Sesión cerrada'
  }
  return 'No hay sesión activa'
})

const formatCurrency = (amount) => {
  return new Intl.NumberFormat('es-PE', {
    style: 'currency',
    currency: 'PEN'
  }).format(amount || 0)
}

const goToEnvironments = () => {
  router.push('/environments')
}

const goToAppointmentTypes = () => {
  router.push('/appointment-types')
}

const goToBusinessIntelligence = () => {
  router.push('/business-intelligence')
}

// Data loading
const loadDashboardData = async () => {
  if (!isAuthenticated.value) {
    router.push('/login')
    return
  }

  loading.value = true
  try {
    const [statsResponse, appointmentsResponse] = await Promise.all([
      get('/api/dashboard/stats'),
      get('/api/dashboard/today')
    ])

    // Mapear datos del backend al formato esperado por el frontend
    const backendStats = statsResponse.data
    stats.value = {
      today: backendStats.appointments_today || 0,
      appointments_today: backendStats.appointments_today || 0,
      completed_today: backendStats.completed_today || 0,
      pending_confirmation: backendStats.pending_confirmation || 0,
      this_week: backendStats.this_week || 0,
      total_patients: backendStats.total_patients || 0,
      total_appointments: backendStats.total_appointments || 0,
      total_appointments_this_month: backendStats.total_appointments_this_month || backendStats.total_appointments || 0,
      total_professionals: backendStats.total_professionals || 0,
      total_appointment_types: backendStats.total_appointment_types || 0,
      total_dental_chairs: backendStats.total_dental_chairs || 0,
      total_income: backendStats.total_income || 0,
      cash_session: backendStats.cash_session || null
    }
    
    todayAppointments.value = appointmentsResponse.data || []
    
    // Cargar sesión de caja si no está cargada
    if (!currentSession.value && hasActiveSession.value === false) {
      await loadCurrentSession()
    }
  } catch (error) {

    if (error.status === 401) {
      router.push('/login')
    }
  } finally {
    loading.value = false
  }
}

// WebSocket subscriptions
let dashboardChannel = null
let appointmentsChannel = null
let cashRegisterChannel = null

// Lifecycle
onMounted(async () => {
  // Verificar si se debe abrir el modal de nueva cita (desde redirección de /appointments/new)
  if (route.query.openAppointmentModal === 'true') {
    showNewAppointmentModal.value = true
    // Limpiar el query param
    router.replace({ query: {} })
  }

  // Cargar sesión de caja primero
  await loadCurrentSession()
  
  // Luego cargar datos del dashboard
  await loadDashboardData()

  // Suscribirse a canales WebSocket
  try {
    // Canal para actualizaciones del dashboard
    dashboardChannel = channel('dashboard-updates')
    if (dashboardChannel) {
      dashboardChannel
        .listen('.dashboard.stats-updated', async (e) => {
          await loadDashboardData()
        })
        .listen('.patient.created', async (e) => {
          await loadDashboardData()
        })
        .listen('.patient.updated', async (e) => {
          await loadDashboardData()
        })
        .listen('.patient.deleted', async (e) => {
          await loadDashboardData()
        })
        .listen('.appointment.created', async (e) => {
          await loadDashboardData()
        })
        .listen('.appointment.updated', async (e) => {
          await loadDashboardData()
        })
        .listen('.appointment.deleted', async (e) => {
          await loadDashboardData()
        })
        .listen('.user.created', async (e) => {
          await loadDashboardData()
        })
        .listen('.user.updated', async (e) => {
          await loadDashboardData()
        })
    }

    // Canal para citas (actualizaciones en tiempo real)
    appointmentsChannel = channel('appointments')
    if (appointmentsChannel) {
      appointmentsChannel
        .listen('.appointment.created', async (e) => {
          // Actualizar lista de citas de hoy si corresponde
          if (e.appointment?.scheduled_at) {
            const appointmentDate = new Date(e.appointment.scheduled_at)
            const today = new Date()
            if (appointmentDate.toDateString() === today.toDateString()) {
              await loadDashboardData()
            }
          }
        })
        .listen('.appointment.updated', async (e) => {
          // Actualizar en la lista si existe
          const index = todayAppointments.value.findIndex(apt => apt.id === e.appointment.id)
          if (index !== -1) {
            todayAppointments.value[index] = e.appointment
          } else {
            await loadDashboardData()
          }
        })
        .listen('.appointment.deleted', async (e) => {
          // Remover de la lista si existe
          todayAppointments.value = todayAppointments.value.filter(
            apt => apt.id !== e.appointment_id
          )
          await loadDashboardData()
        })
    }

    // Canal para caja registradora
    cashRegisterChannel = channel('cash-register')
    if (cashRegisterChannel) {
      cashRegisterChannel
        .listen('.cash-session.opened', async (e) => {
          await loadCurrentSession()
          await loadDashboardData()
        })
        .listen('.cash-session.closed', async (e) => {
          await loadCurrentSession()
          await loadDashboardData()
        })
        .listen('.payment.registered', async (e) => {
          await loadCurrentSession()
          await loadDashboardData()
        })
        .listen('.cash-movement.created', async (e) => {
          await loadCurrentSession()
          await loadDashboardData()
        })
    }
  } catch (error) {
  }
})

onUnmounted(() => {
  // Limpiar suscripciones WebSocket
  if (echo) {
    try {
      echo.leave('dashboard-updates')
      echo.leave('appointments')
      echo.leave('cash-register')
    } catch (e) {
    }
  }
})
</script>

<style scoped>
/* Animations */
@keyframes fadeInUp {
  from {
    opacity: 0;
    transform: translateY(20px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

@keyframes slideInRight {
  from {
    opacity: 0;
    transform: translateX(20px);
  }
  to {
    opacity: 1;
    transform: translateX(0);
  }
}

.animate-fade-in {
  animation: fadeInUp 0.6s ease-out;
}

.animate-slide-in {
  animation: slideInRight 0.4s ease-out;
}

/* Stagger animation for cards */
.stagger-item:nth-child(1) { animation-delay: 0.1s; }
.stagger-item:nth-child(2) { animation-delay: 0.2s; }
.stagger-item:nth-child(3) { animation-delay: 0.3s; }
.stagger-item:nth-child(4) { animation-delay: 0.4s; }

/* Hover effects */
.hover-lift {
  transition: all 0.2s ease-out;
}

.hover-lift:hover {
  transform: translateY(-2px);
  box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
}

/* Glass morphism effect */
.glass-effect {
  background: var(--glass-bg);
  backdrop-filter: var(--glass-backdrop);
  border: 1px solid var(--glass-border);
}

/* Gradient backgrounds */
.gradient-primary {
  background: linear-gradient(135deg, var(--color-accent) 0%, var(--color-accent-hover) 100%);
}

.gradient-success {
  background: linear-gradient(135deg, var(--color-success) 0%, var(--color-success-dark) 100%);
}

.gradient-warning {
  background: linear-gradient(135deg, var(--color-warning) 0%, var(--color-warning-dark) 100%);
}

.gradient-info {
  background: linear-gradient(135deg, var(--color-info) 0%, var(--color-info-dark) 100%);
}

/* Responsive design */
@media (max-width: 640px) {
  .grid-cols-1 {
    grid-template-columns: repeat(1, minmax(0, 1fr));
  }

  .text-2xl {
    font-size: 1.5rem;
  }

  .text-3xl {
    font-size: 2rem;
  }
}

@media (max-width: 768px) {
  .space-y-8 > * + * {
    margin-top: 1.5rem;
  }
}

/* Dark mode support - glass-effect already uses CSS variables */

/* Reduced motion support */
@media (prefers-reduced-motion: reduce) {
  .animate-fade-in,
  .animate-slide-in,
  .hover-lift {
    animation: none;
    transition: none;
  }

  .hover-lift:hover {
    transform: none;
  }
}

/* Focus styles for accessibility */
.focus-visible:focus {
  outline: 2px solid var(--color-primary-500);
  outline-offset: 2px;
}

/* Loading spinner */
@keyframes spin {
  from {
    transform: rotate(0deg);
  }
  to {
    transform: rotate(360deg);
  }
}

.animate-spin {
  animation: spin 1s linear infinite;
}

/* Custom scrollbar */
::-webkit-scrollbar {
  width: 6px;
}

::-webkit-scrollbar-track {
  background: transparent;
}

::-webkit-scrollbar-thumb {
  background: var(--color-border-strong);
  border-radius: 3px;
}

::-webkit-scrollbar-thumb:hover {
  background: var(--color-border-strong);
  opacity: 0.8;
}
</style>


