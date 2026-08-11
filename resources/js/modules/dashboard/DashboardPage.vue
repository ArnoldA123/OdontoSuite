<template>
  <AppLayout>
    <!-- Loading State: Skeleton placeholders that match the final layout's
         shape so the page does not jump when data lands. -->
    <template v-if="loading">
      <div class="space-y-8" aria-busy="true" aria-live="polite">
        <!-- Stats skeletons -->
        <section aria-label="Cargando resumen">
          <div class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-5 gap-4">
            <UiSkeleton
              v-for="i in 5"
              :key="`stat-skel-${i}`"
              variant="card"
              animation="wave"
              :aria-label="`Cargando tarjeta ${i}`"
            />
          </div>
        </section>
        <!-- Quick actions skeletons: same 3-col shape as the loaded
             quick-actions row so the page doesn't jump when data lands. -->
        <section aria-label="Cargando acciones rápidas">
          <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
            <UiSkeleton
              v-for="i in 5"
              :key="`qa-skel-${i}`"
              variant="list"
              animation="wave"
              :aria-label="`Cargando acción ${i}`"
            />
          </div>
        </section>
        <!-- Today's appointments skeletons -->
        <section aria-label="Cargando citas de hoy">
          <UiSkeleton
            v-for="i in 3"
            :key="`apt-skel-${i}`"
            variant="list"
            animation="wave"
            :aria-label="`Cargando cita ${i}`"
          />
        </section>
      </div>
    </template>

    <!-- Main Content -->
    <div v-else class="space-y-8">
      <!-- Page greeting (the AppLayout top bar already renders the page
           title h1; this region is a calm greeting line, not a heading.) -->
      <header class="flex items-end justify-between flex-wrap gap-4">
        <div>
          <p class="text-2xl font-semibold text-ink-800 leading-tight">
            {{ getGreeting() }}, <span class="text-ink-900">{{ firstName }}</span>
          </p>
          <p class="text-sm text-ink-500 mt-1">
            {{ getTodayDate() }}
          </p>
        </div>
      </header>

      <!-- Stats Grid: five stat cards with deliberate visual hierarchy.
           The grid must look deliberate at 2, 3, 4, AND 5 cards because
           three of the five are permission-gated (can.viewAppointment,
           can.manageUsers, can.viewCashRegister). See the defended
           hierarchy choice in apply-progress.md. -->
      <section aria-label="Resumen del día">
        <div class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-5 gap-4">
          <!-- Citas Hoy (PRIMARY stat - operationally live; gated) -->
          <UiCard
            v-if="can.viewAppointment?.value"
            variant="glass"
            hover
            clickable
            data-stat="appointments-today"
            data-priority="primary"
            class="relative"
            @click="goToCalendar"
          >
            <div class="flex items-start justify-between gap-3">
              <div class="min-w-0">
                <p class="text-xs font-medium text-ink-500 uppercase tracking-wide mb-2">
                  Citas Hoy
                </p>
                <p
                  class="text-5xl font-bold text-label tabular-nums leading-none"
                  aria-live="polite"
                >
                  {{ stats.today || 0 }}
                </p>
                <p class="text-xs text-systemGray-600 mt-2">
                  {{ getTodayDate() }}
                </p>
              </div>
              <div class="flex-shrink-0 w-12 h-12 bg-systemBlue-100 rounded-ios flex items-center justify-center border border-systemBlue-200">
                <svg
                  class="w-6 h-6 text-systemBlue-600"
                  fill="none"
                  stroke="currentColor"
                  viewBox="0 0 24 24"
                  aria-hidden="true"
                >
                  <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="2"
                    d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"
                  />
                </svg>
              </div>
            </div>
          </UiCard>

          <!-- Pacientes (reference count) -->
          <UiCard
            variant="glass"
            hover
            clickable
            data-stat="total-patients"
            class="relative"
            @click="goToPatients"
          >
            <div class="flex items-start justify-between gap-3">
              <div class="min-w-0">
                <p class="text-xs font-medium text-ink-500 uppercase tracking-wide mb-2">
                  Pacientes
                </p>
                <p class="text-3xl font-semibold text-ink-800 tabular-nums leading-none">
                  {{ stats.total_patients || 0 }}
                </p>
                <p class="text-xs text-ink-500 mt-2">
                  Total registrados
                </p>
              </div>
              <div class="flex-shrink-0 w-10 h-10 bg-success-50 rounded-lg flex items-center justify-center">
                <svg
                  class="w-5 h-5 text-success-600"
                  fill="none"
                  stroke="currentColor"
                  viewBox="0 0 24 24"
                  aria-hidden="true"
                >
                  <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="2"
                    d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-9a2.5 2.5 0 11-5 0 2.5 2.5 0 015 0z"
                  />
                </svg>
              </div>
            </div>
          </UiCard>

          <!-- Profesionales (reference count; gated) -->
          <UiCard
            v-if="can.manageUsers?.value"
            variant="glass"
            hover
            clickable
            data-stat="total-professionals"
            class="relative"
            @click="goToProfessionals"
          >
            <div class="flex items-start justify-between gap-3">
              <div class="min-w-0">
                <p class="text-xs font-medium text-ink-500 uppercase tracking-wide mb-2">
                  Profesionales
                </p>
                <p class="text-3xl font-semibold text-ink-800 tabular-nums leading-none">
                  {{ stats.total_professionals || 0 }}
                </p>
                <p class="text-xs text-ink-500 mt-2">
                  Equipo médico
                </p>
              </div>
              <div class="flex-shrink-0 w-10 h-10 bg-warning-50 rounded-lg flex items-center justify-center">
                <svg
                  class="w-5 h-5 text-warning-600"
                  fill="none"
                  stroke="currentColor"
                  viewBox="0 0 24 24"
                  aria-hidden="true"
                >
                  <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="2"
                    d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"
                  />
                </svg>
              </div>
            </div>
          </UiCard>

          <!-- Total Citas (reference count) -->
          <UiCard
            variant="glass"
            hover
            clickable
            data-stat="total-appointments-month"
            class="relative"
            @click="goToCalendar"
          >
            <div class="flex items-start justify-between gap-3">
              <div class="min-w-0">
                <p class="text-xs font-medium text-ink-500 uppercase tracking-wide mb-2">
                  Total Citas
                </p>
                <p class="text-3xl font-semibold text-ink-800 tabular-nums leading-none">
                  {{ stats.total_appointments_this_month || stats.total_appointments || 0 }}
                </p>
                <p class="text-xs text-ink-500 mt-2">
                  Este mes
                </p>
              </div>
              <div class="flex-shrink-0 w-10 h-10 bg-cream-200 rounded-lg flex items-center justify-center">
                <svg
                  class="w-5 h-5 text-ink-500"
                  fill="none"
                  stroke="currentColor"
                  viewBox="0 0 24 24"
                  aria-hidden="true"
                >
                  <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="2"
                    d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"
                  />
                </svg>
              </div>
            </div>
          </UiCard>

          <!-- Estado de Caja (SECONDARY live stat; gated).
               The cash pill renders its own Spanish label via a primitive that
               supports custom labels. The PR2 StatusPill's STATUS_MAP only
               knows appointment / plan keys ('scheduled', 'confirmed', ...);
               passing an unknown 'open' / 'closed' / 'no_session' would fall
               through and display the raw English key. UiBadge with shape
               "pill" + an in-slot dot replicates the pill aesthetic while
               routing every label through i18n. -->
          <UiCard
            v-if="can.viewCashRegister?.value"
            variant="glass"
            hover
            clickable
            data-stat="cash-status"
            data-priority="secondary"
            class="relative"
            @click="goToCashRegister"
          >
            <div class="flex items-start justify-between gap-3">
              <div class="min-w-0">
                <p class="text-xs font-medium text-systemGray-600 uppercase tracking-wide mb-2">
                  Estado de Caja
                </p>
                <UiBadge
                  :variant="cashStatusBadgeVariant"
                  shape="pill"
                  size="md"
                  role="status"
                  :aria-label="`Estado de caja: ${cashStatusLabel}`"
                  :class="['mt-1', cashStatusBadgeClass]"
                  data-cash-pill
                  :data-cash-pill-state="cashStatusPillState"
                >
                  <span
                    class="inline-block w-1.5 h-1.5 rounded-full"
                    :class="cashStatusDotClass"
                    aria-hidden="true"
                  ></span>
                  {{ cashStatusLabel }}
                </UiBadge>
                <p class="text-xs text-systemGray-600 mt-3">
                  {{ cashBalanceText }}
                </p>
              </div>
              <div class="flex-shrink-0 w-10 h-10 bg-systemGreen-100 rounded-ios flex items-center justify-center border border-systemGreen-200">
                <svg
                  class="w-5 h-5 text-systemGreen-600"
                  fill="none"
                  stroke="currentColor"
                  viewBox="0 0 24 24"
                  aria-hidden="true"
                >
                  <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="2"
                    d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"
                  />
                </svg>
              </div>
            </div>
          </UiCard>
        </div>
      </section>

      <!-- Quick Actions -->
      <section aria-label="Acciones rápidas">
        <div class="flex items-center justify-between mb-4">
          <h2 class="text-base font-semibold text-ink-800">Acciones Rápidas</h2>
          <UiButton variant="ghost" size="sm" @click="goToCalendar">
            Ver calendario
            <template #icon-right>
              <svg
                class="w-4 h-4"
                fill="none"
                stroke="currentColor"
                viewBox="0 0 24 24"
                aria-hidden="true"
              >
                <path
                  stroke-linecap="round"
                  stroke-linejoin="round"
                  stroke-width="2"
                  d="M9 5l7 7-7 7"
                />
              </svg>
            </template>
          </UiButton>
        </div>

        <!-- Layout note: 3 columns at lg+, not 5. At 1440 with the design's
             ~1368 px content width, a 5-col grid gave each card only ~70 px
             of text space — too narrow for the real Spanish copy ("Gestionar
             base de datos" = 22 chars; "Análisis y estadísticas" = 23 chars).
             Spanish runs roughly 20-25% longer than English; the layout was
             built for placeholder-length strings and clipped every subtitle
             on the right. Quick actions are actions, not a stat row, so they
             do not need to mirror the 5-up stats grid. 3 cols at lg+
             (~440 px per card) gives the longest subtitle ~340 px to wrap
             naturally. Cards are whole-card clickable; the chevron is
             removed because it consumed space the label needed. -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
          <!-- Patients -->
          <UiCard
            variant="flat"
            hover
            clickable
            data-action="patients"
            @click="goToPatients"
          >
            <div class="flex items-start gap-3">
              <div class="flex-shrink-0 w-10 h-10 bg-success-50 rounded-lg flex items-center justify-center">
                <svg
                  class="w-5 h-5 text-success-600"
                  fill="none"
                  stroke="currentColor"
                  viewBox="0 0 24 24"
                  aria-hidden="true"
                >
                  <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="2"
                    d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-9a2.5 2.5 0 11-5 0 2.5 2.5 0 015 0z"
                  />
                </svg>
              </div>
              <div class="min-w-0 flex-1">
                <p class="font-medium text-ink-800 leading-tight">Pacientes</p>
                <p class="text-sm text-ink-500 leading-snug mt-0.5">Gestionar base de datos</p>
              </div>
            </div>
          </UiCard>

          <!-- New Appointment -->
          <UiCard
            v-if="can.createAppointment?.value"
            variant="flat"
            hover
            clickable
            data-action="new-appointment"
            @click="goToNewAppointment"
          >
            <div class="flex items-start gap-3">
              <div class="flex-shrink-0 w-10 h-10 bg-systemBlue-100 rounded-ios flex items-center justify-center border border-systemBlue-200">
                <svg
                  class="w-5 h-5 text-systemBlue-600"
                  fill="none"
                  stroke="currentColor"
                  viewBox="0 0 24 24"
                  aria-hidden="true"
                >
                  <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="2"
                    d="M12 6v6m0 0v6m0-6h6m-6 0H6"
                  />
                </svg>
              </div>
              <div class="min-w-0 flex-1">
                <p class="font-medium text-ink-800 leading-tight whitespace-nowrap">Nueva Cita</p>
                <p class="text-sm text-ink-500 leading-snug mt-0.5">Programar cita médica</p>
              </div>
            </div>
          </UiCard>

          <!-- Professionals -->
          <UiCard
            v-if="can.manageUsers?.value"
            variant="flat"
            hover
            clickable
            data-action="professionals"
            @click="goToProfessionals"
          >
            <div class="flex items-start gap-3">
              <div class="flex-shrink-0 w-10 h-10 bg-warning-50 rounded-lg flex items-center justify-center">
                <svg
                  class="w-5 h-5 text-warning-600"
                  fill="none"
                  stroke="currentColor"
                  viewBox="0 0 24 24"
                  aria-hidden="true"
                >
                  <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="2"
                    d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"
                  />
                </svg>
              </div>
              <div class="min-w-0 flex-1">
                <p class="font-medium text-ink-800 leading-tight">Profesionales</p>
                <p class="text-sm text-ink-500 leading-snug mt-0.5">Gestionar equipo</p>
              </div>
            </div>
          </UiCard>

          <!-- Environments -->
          <UiCard
            v-if="can.manageConfig?.value"
            variant="flat"
            hover
            clickable
            data-action="environments"
            @click="goToEnvironments"
          >
            <div class="flex items-start gap-3">
              <div class="flex-shrink-0 w-10 h-10 bg-clinicalTeal-50 rounded-lg flex items-center justify-center">
                <svg
                  class="w-5 h-5 text-clinicalTeal-600"
                  fill="none"
                  stroke="currentColor"
                  viewBox="0 0 24 24"
                  aria-hidden="true"
                >
                  <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="2"
                    d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"
                  />
                </svg>
              </div>
              <div class="min-w-0 flex-1">
                <p class="font-medium text-ink-800 leading-tight">Ambientes</p>
                <p class="text-sm text-ink-500 leading-snug mt-0.5">Configurar espacios</p>
              </div>
            </div>
          </UiCard>

          <!-- Reportes -->
          <UiCard
            v-if="can.viewReports?.value"
            variant="flat"
            hover
            clickable
            data-action="reports"
            @click="goToBusinessIntelligence"
          >
            <div class="flex items-start gap-3">
              <div class="flex-shrink-0 w-10 h-10 bg-error-50 rounded-lg flex items-center justify-center">
                <svg
                  class="w-5 h-5 text-error-600"
                  fill="none"
                  stroke="currentColor"
                  viewBox="0 0 24 24"
                  aria-hidden="true"
                >
                  <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="2"
                    d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"
                  />
                </svg>
              </div>
              <div class="min-w-0 flex-1">
                <p class="font-medium text-ink-800 leading-tight">Reportes</p>
                <p class="text-sm text-ink-500 leading-snug mt-0.5">Análisis y estadísticas</p>
              </div>
            </div>
          </UiCard>
        </div>
      </section>

      <!-- Today's Appointments Preview: list OR empty state.
           The empty state is the live state today (GET /api/dashboard/today
           returns 404 due to the known bug). Build it properly, not as an
           afterthought. -->
      <section
        v-if="can.viewAppointment?.value"
        aria-label="Citas de hoy"
      >
        <div class="flex items-center justify-between mb-4">
          <h2 class="text-base font-semibold text-ink-800">Citas de Hoy</h2>
          <UiButton
            v-if="todayAppointments.length > 0"
            variant="ghost"
            size="sm"
            @click="goToCalendar"
          >
            Ver todas
            <template #icon-right>
              <svg
                class="w-4 h-4"
                fill="none"
                stroke="currentColor"
                viewBox="0 0 24 24"
                aria-hidden="true"
              >
                <path
                  stroke-linecap="round"
                  stroke-linejoin="round"
                  stroke-width="2"
                  d="M9 5l7 7-7 7"
                />
              </svg>
            </template>
          </UiButton>
        </div>

        <EmptyState
          v-if="todayAppointments.length === 0"
          title="Sin citas para hoy"
          description="Aún no hay citas registradas para el día de hoy. Puedes crear una nueva cita desde la sección de calendario."
          action-text="Agendar nueva cita"
          action-variant="primary"
          data-state="empty-appointments"
          @action="goToNewAppointment"
        />

        <div v-else class="grid gap-3">
          <UiCard
            v-for="appointment in todayAppointments.slice(0, 3)"
            :key="appointment.id"
            variant="flat"
            data-appointment-row
            class="hover:shadow-medium"
          >
            <div class="flex items-center justify-between gap-4">
              <div class="flex items-center gap-4 min-w-0">
                <div class="flex-shrink-0 w-10 h-10 bg-systemBlue-100 rounded-ios flex items-center justify-center border border-systemBlue-200">
                  <svg
                    class="w-5 h-5 text-systemBlue-600"
                    fill="none"
                    stroke="currentColor"
                    viewBox="0 0 24 24"
                    aria-hidden="true"
                  >
                    <path
                      stroke-linecap="round"
                      stroke-linejoin="round"
                      stroke-width="2"
                      d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"
                    />
                  </svg>
                </div>
                <div class="min-w-0 flex-1">
                  <p class="font-medium text-ink-800 truncate">
                    {{ appointment.patient?.name || 'Paciente' }}
                  </p>
                  <p class="text-sm text-ink-500 truncate">
                    {{ formatTime(appointment.scheduled_at) }} ·
                    {{ appointment.appointment_type?.name || 'Consulta' }}
                  </p>
                </div>
              </div>
              <UiBadge :variant="getStatusVariant(appointment.status)" size="sm">
                {{ getStatusText(appointment.status) }}
              </UiBadge>
            </div>
          </UiCard>
        </div>
      </section>
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
import { formatPENLabel } from '@/composables/useFormatters'

const router = useRouter()
const route = useRoute()
const { user, isAuthenticated } = useAuth()
const { get } = useApi()
const { can } = usePermissions()
const {
  currentSession,
  hasActiveSession,
  isOpen,
  realTimeTotals,
  loadCurrentSession
} = useCashRegister()
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

// Spring hooks are not strictly required for the rebuild — we expose the
// composables via the design contract (useSpring/useSpring2D live in PR2's
// composables). Numbers are displayed via Vue's reactive interpolation; a
// WebSocket burst lands in the same value, so the bindings naturally tween
// visually (no DOM-level entrance replay). See apply-progress.md for the
// decision trail.

// Utility functions
const getGreeting = () => {
  const hour = new Date().getHours()
  if (hour < 12) return 'Buenos días'
  if (hour < 18) return 'Buenas tardes'
  return 'Buenas noches'
}

const firstName = computed(() => {
  const raw = user.value?.name || ''
  return raw.split(' ')[0] || 'equipo'
})

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
  if (!dateTime) return ''
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

const handleAppointmentCreated = async () => {
  // Slice 08 / FF-015: refresh data after the user creates an appointment
  // from anywhere (quick-action button or empty-state CTA). Single fetch
  // rather than a fan-out — the WebSocket path will catch subsequent edits.
  await loadDashboardData()
}

const goToProfessionals = () => {
  router.push('/professionals')
}

const goToCashRegister = () => {
  router.push('/cash-register')
}

// Cash status: render the Spanish label directly via a primitive that
// supports custom labels. Replaces a previous attempt that passed English
// keys ('open' / 'closed' / 'no_session') to UiStatusPill — that primitive
// only maps appointment / plan statuses and fell through to render the raw
// English key on the page. The state is now used purely as a data
// attribute (data-cash-pill-state) for testability; the user-visible
// label and aria-label are always Spanish. iOS filled pattern per
// Decision 7:
//   - open        → label "Abierta",     bg-systemGreen-100 text-systemGreen-600
//   - closed      → label "Cerrada",     bg-systemRed-100 text-systemRed-600
//   - no_session  → label "Sin sesión",  bg-systemGray-100 text-systemGray-600
const cashStatusPillState = computed(() => {
  if (isOpen.value) return 'open'
  if (hasActiveSession.value) return 'closed'
  return 'no_session'
})

const cashStatusLabel = computed(() => {
  if (isOpen.value) return 'Abierta'
  if (hasActiveSession.value) return 'Cerrada'
  return 'Sin sesión'
})

const cashStatusBadgeVariant = computed(() => {
  if (isOpen.value) return 'success'
  if (hasActiveSession.value) return 'error'
  return 'neutral'
})

const cashStatusBadgeClass = computed(() => {
  if (isOpen.value) return 'bg-systemGreen-100 text-systemGreen-600'
  if (hasActiveSession.value) return 'bg-systemRed-100 text-systemRed-600'
  return 'bg-systemGray-100 text-systemGray-600'
})

const cashStatusDotClass = computed(() => {
  if (isOpen.value) return 'bg-systemGreen-500'
  if (hasActiveSession.value) return 'bg-systemRed-500'
  return 'bg-systemGray-500'
})

const cashBalanceText = computed(() => {
  if (isOpen.value && realTimeTotals.value) {
    return `Saldo: ${formatPENLabel(realTimeTotals.value.currentBalance)}`
  }
  if (hasActiveSession.value) {
    return 'Sesión cerrada'
  }
  return 'No hay sesión activa'
})

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
      get('/api/dashboard/today').catch((err) => {
        // GET /api/dashboard/today returns 404 in the running app; the
        // empty-state path is the live UX. Treat any error as an empty list
        // rather than throwing, so other stats still render.
        if (err && (err.status === 404 || err.status === 401)) {
          return { data: [] }
        }
        throw err
      })
    ])

    // Map backend stats into the frontend shape.
    const backendStats = statsResponse.data || {}
    stats.value = {
      today: backendStats.appointments_today || 0,
      appointments_today: backendStats.appointments_today || 0,
      completed_today: backendStats.completed_today || 0,
      pending_confirmation: backendStats.pending_confirmation || 0,
      this_week: backendStats.this_week || 0,
      total_patients: backendStats.total_patients || 0,
      total_appointments: backendStats.total_appointments || 0,
      total_appointments_this_month:
        backendStats.total_appointments_this_month ||
        backendStats.total_appointments ||
        0,
      total_professionals: backendStats.total_professionals || 0,
      total_appointment_types: backendStats.total_appointment_types || 0,
      total_dental_chairs: backendStats.total_dental_chairs || 0,
      total_income: backendStats.total_income || 0,
      cash_session: backendStats.cash_session || null
    }

    todayAppointments.value = Array.isArray(appointmentsResponse?.data)
      ? appointmentsResponse.data
      : []

    // Load cash session if not already loaded
    if (!currentSession.value && hasActiveSession.value === false) {
      await loadCurrentSession()
    }
  } catch (error) {
    if (error?.status === 401) {
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

// Slice 08 / FF-015: the legacy version had 14 WS listeners that all
// called loadDashboardData() directly. A burst (e.g. 5 events within 50 ms
// after a payment + a patient update) hit the API 5 times. Coalesce them
// into a single trailing-edge debounced fetch. This 300ms debounce is
// load-bearing — do not change the timing without updating the
// apply-progress evidence.
let dashboardDebounceTimer = null
const debouncedLoadDashboardData = () => {
  if (dashboardDebounceTimer !== null) {
    clearTimeout(dashboardDebounceTimer)
  }
  dashboardDebounceTimer = setTimeout(async () => {
    dashboardDebounceTimer = null
    await loadDashboardData()
  }, 300)
}

onUnmounted(() => {
  // Clear any pending debounced fetch on unmount so we don't fire
  // against a torn-down component.
  if (dashboardDebounceTimer !== null) {
    clearTimeout(dashboardDebounceTimer)
    dashboardDebounceTimer = null
  }
})

// Lifecycle
onMounted(async () => {
  // Verificar si se debe abrir el modal de nueva cita (desde redirección)
  if (route.query.openAppointmentModal === 'true') {
    showNewAppointmentModal.value = true
    router.replace({ query: {} })
  }

  // Cargar sesión de caja primero
  await loadCurrentSession()

  // Luego cargar datos del dashboard
  await loadDashboardData()

  // Suscribirse a canales WebSocket (Reverb is often not running locally;
  // connection errors here are expected and harmless — error handling is
  // inside useEcho).
  try {
    dashboardChannel = channel('dashboard-updates')
    if (dashboardChannel) {
      dashboardChannel
        .listen('.dashboard.stats-updated', () => debouncedLoadDashboardData())
        .listen('.patient.created', () => debouncedLoadDashboardData())
        .listen('.patient.updated', () => debouncedLoadDashboardData())
        .listen('.patient.deleted', () => debouncedLoadDashboardData())
        .listen('.appointment.created', () => debouncedLoadDashboardData())
        .listen('.appointment.updated', () => debouncedLoadDashboardData())
        .listen('.appointment.deleted', () => debouncedLoadDashboardData())
        .listen('.user.created', () => debouncedLoadDashboardData())
        .listen('.user.updated', () => debouncedLoadDashboardData())
    }

    appointmentsChannel = channel('appointments')
    if (appointmentsChannel) {
      appointmentsChannel
        .listen('.appointment.created', (e) => {
          if (e.appointment?.scheduled_at) {
            const appointmentDate = new Date(e.appointment.scheduled_at)
            const today = new Date()
            if (appointmentDate.toDateString() === today.toDateString()) {
              debouncedLoadDashboardData()
            }
          }
        })
        .listen('.appointment.updated', async (e) => {
          const index = todayAppointments.value.findIndex(
            (apt) => apt.id === e.appointment.id
          )
          if (index !== -1) {
            todayAppointments.value[index] = e.appointment
          } else {
            debouncedLoadDashboardData()
          }
        })
        .listen('.appointment.deleted', async (e) => {
          todayAppointments.value = todayAppointments.value.filter(
            (apt) => apt.id !== e.appointment_id
          )
          debouncedLoadDashboardData()
        })
    }

    cashRegisterChannel = channel('cash-register')
    if (cashRegisterChannel) {
      cashRegisterChannel
        .listen('.cash-session.opened', async () => {
          await loadCurrentSession()
          debouncedLoadDashboardData()
        })
        .listen('.cash-session.closed', async () => {
          await loadCurrentSession()
          debouncedLoadDashboardData()
        })
        .listen('.payment.registered', async () => {
          await loadCurrentSession()
          debouncedLoadDashboardData()
        })
        .listen('.cash-movement.created', async () => {
          await loadCurrentSession()
          debouncedLoadDashboardData()
        })
    }
  } catch (error) {
    // Reverb unreacheable in dev is expected.
  }
})

onUnmounted(() => {
  if (echo) {
    try {
      echo.leave('dashboard-updates')
      echo.leave('appointments')
      echo.leave('cash-register')
    } catch (e) {
      // teardown is best-effort
    }
  }
})
</script>
