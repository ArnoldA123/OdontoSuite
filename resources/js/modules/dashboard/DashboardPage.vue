<template>
  <AppLayout>
    <!-- Loading State: the first frame is the skeleton, never an empty
         surface. It mirrors the two-card strip so the page does not jump. -->
    <template v-if="loading">
      <div class="space-y-12" aria-busy="true" aria-live="polite">
        <section aria-label="Cargando resumen">
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <UiSkeleton
              v-for="i in 2"
              :key="`stat-skel-${i}`"
              variant="card"
              animation="wave"
              :aria-label="`Cargando tarjeta ${i}`"
            />
          </div>
        </section>
      </div>
    </template>

    <!-- Main Content -->
    <div v-else data-dashboard-content class="space-y-12" :aria-busy="refreshing">
      <!--
        Canonical page header: the shared PageHeader component, kicker `HOY`,
        the greeting as the page title and the date as its description. The
        refresh rides the shared secondary action, so every destination opens
        with the same control. The wrapper only carries the entrance spring;
        the header itself owns no anatomy of its own.
      -->
      <div
        ref="greetingSection"
        data-dashboard-header
        data-reveal="greeting"
        :style="revealStyle('--spring-dash-greeting-o')"
      >
        <PageHeader
          kicker="HOY"
          :title="`${getGreeting()}, ${firstName}`"
          :subtitle="getTodayDate()"
          style="
            font-feature-settings:
              'tnum' 1,
              'lnum' 1;
          "
        >
          <template #actions>
            <!-- T3 manual refresh. The header keeps the current content
                 mounted while the request is in flight and reports aria-busy. -->
            <UiButton
              variant="secondary"
              data-refresh-button
              :disabled="refreshing"
              @click="loadDashboardData"
            >
              <template #icon-left>
                <ArrowPathIcon
                  class="w-4 h-4"
                  :class="{ 'animate-spin motion-reduce:animate-none': refreshing }"
                  aria-hidden="true"
                />
              </template>
              Actualizar
            </UiButton>
          </template>
        </PageHeader>
      </div>

      <!--
        Una sola confirmación por cita creada. El modal ya no confirma: esta
        línea es la única voz, con el resumen de lo agendado y la acción que
        abre la agenda del día creado.
      -->
      <div
        v-if="createdConfirmation"
        class="flex flex-wrap items-center gap-3 rounded-ios border border-hairline bg-systemGreen-50 px-4 py-3"
        role="status"
        data-appointment-confirmation
        :data-appointment-confirmation-id="createdConfirmation.id"
      >
        <CheckCircleIcon class="h-5 w-5 shrink-0 text-systemGreen-700" aria-hidden="true" />
        <p class="min-w-0 flex-1 text-sm text-theme-primary">
          <span class="font-semibold">Cita agendada</span>
          <span v-if="createdConfirmation.summary">· {{ createdConfirmation.summary }}</span>
        </p>
        <UiButton
          variant="secondary"
          size="sm"
          data-appointment-confirmation-action
          @click="openCreatedAppointmentInAgenda"
        >
          Ver en la agenda
        </UiButton>
        <UiButton
          variant="ghost"
          size="sm"
          data-appointment-confirmation-dismiss
          @click="createdConfirmation = null"
        >
          Cerrar
        </UiButton>
      </div>

      <!--
        Stats error state (T3). A non-401 failure of dashboard/stats reports
        HERE, in its own space under the hero band: the destinations below
        are static and stay mounted, and Reintentar re-fetches ONLY the
        summary through retryStats(); the 401 case still redirects to /login.
      -->
      <div
        v-if="statsError"
        data-state="error-stats"
        role="alert"
        class="rounded-ios p-10 text-center bg-systemRed-50"
        style="border: 1px solid var(--color-hairline)"
      >
        <ExclamationTriangleIcon
          class="mx-auto h-12 w-12 mb-4 text-systemRed-600"
          aria-hidden="true"
        />
        <p class="text-base font-medium text-theme-primary">No pudimos cargar el resumen</p>
        <p class="text-sm text-theme-secondary mt-1 max-w-md mx-auto">
          Revisa tu conexión e inténtalo de nuevo. Si el problema continúa, vuelve a intentarlo en
          unos minutos.
        </p>
        <div class="mt-6">
          <UiButton variant="primary" size="md" data-retry-stats @click="retryStats">
            Reintentar
          </UiButton>
        </div>
      </div>

      <!--
        Block "Hoy" - the dashboard no longer lists appointments: it publishes
        the day count the summary owns plus the drawer balance. Both cards are
        static reference surfaces (no click affordance); the cash card keeps
        its single contextual action.
      -->
      <section
        v-if="canViewAppointments || canViewCashRegister"
        aria-label="Hoy"
        data-dashboard-block="hoy"
        class="space-y-6"
      >
        <div class="flex items-baseline gap-3">
          <h2 class="text-2xl font-semibold text-label">Hoy</h2>
          <p class="text-sm text-theme-secondary">Lo que ocurre hoy en la clínica</p>
        </div>

        <div
          ref="kpiSection"
          data-reveal="kpi"
          class="grid grid-cols-1 md:grid-cols-2 gap-4"
          :style="revealStyle('--spring-dash-kpi-o')"
        >
          <!-- Citas Hoy: today's appointment count, published by the summary. -->
          <UiCard
            v-if="canViewAppointments"
            variant="glass"
            padding="sm"
            data-stat-card="appointments-today"
            data-priority="primary"
            class="relative h-full flex flex-col"
            :style="{ boxShadow: 'var(--elevation-2)', borderColor: 'var(--color-hairline)' }"
          >
            <div class="flex items-start justify-between gap-3">
              <div class="min-w-0 flex-1">
                <p class="text-xs font-medium text-theme-secondary uppercase whitespace-nowrap">
                  Citas Hoy
                </p>
                <p
                  class="mt-2 text-2xl font-bold text-label tabular-nums leading-none truncate"
                  style="
                    font-feature-settings:
                      'tnum' 1,
                      'lnum' 1;
                  "
                  aria-live="polite"
                  data-stat="appointments-today"
                >
                  {{ citasHoyDisplay }}
                </p>
                <p
                  class="mt-1 text-xs text-theme-secondary truncate"
                  data-kpi-caption="appointments-today"
                >
                  Agenda del día
                </p>
              </div>
            </div>
          </UiCard>

          <!--
            Saldo de Caja: the single cash surface (WU2 / D4). State line,
            session balance and the one cash CTA live here; everything
            cash-visible reads the useCashRegister state.
          -->
          <UiCard
            v-if="canViewCashRegister"
            variant="glass"
            padding="sm"
            data-stat-card="cash-balance"
            data-priority="secondary"
            class="relative h-full flex flex-col"
            :style="{ boxShadow: 'var(--elevation-2)', borderColor: 'var(--color-hairline)' }"
          >
            <div class="flex items-start justify-between gap-3">
              <div class="min-w-0 flex-1">
                <p class="text-xs font-medium text-theme-secondary uppercase whitespace-nowrap">
                  Saldo de Caja
                </p>
                <p
                  class="mt-1 text-xs font-medium"
                  :class="cashStateToneClass"
                  :data-cash-state="cashStateKey"
                >
                  {{ cashStateLabel }}
                </p>
                <p
                  class="mt-2 text-2xl font-bold text-label tabular-nums leading-none truncate"
                  style="
                    font-feature-settings:
                      'tnum' 1,
                      'lnum' 1;
                  "
                  data-stat="cash-balance"
                >
                  {{ cashKpiBalance }}
                </p>
                <p
                  class="mt-1 text-xs text-theme-secondary truncate"
                  data-kpi-caption="cash-balance"
                >
                  {{ cashKpiCaption }}
                </p>
                <div class="mt-3">
                  <UiButton variant="ghost" size="sm" @click="goToCashRegister">Ir a Caja</UiButton>
                </div>
              </div>
            </div>
          </UiCard>
        </div>
      </section>

      <!--
        Block "Dónde seguir" - the three destinations of the daily work. Each
        card is a link, carries one line of what it resolves and no counter:
        the page points at the desks instead of duplicating their content.
      -->
      <section
        v-if="destinations.length > 0"
        aria-label="Dónde seguir"
        data-dashboard-block="destinations"
        class="space-y-4"
      >
        <div class="flex items-baseline gap-3">
          <h2 class="text-2xl font-semibold text-label">Dónde seguir</h2>
          <p class="text-sm text-theme-secondary">Los destinos del trabajo diario</p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
          <RouterLink
            v-for="destination in destinations"
            :key="destination.key"
            :to="destination.to"
            :data-dashboard-destination="destination.key"
            class="block rounded-ios focus:outline-none focus-visible:ring-2 focus-visible:ring-systemBlue-500 focus-visible:ring-offset-2"
          >
            <UiCard
              variant="flat"
              hover
              padding="sm"
              class="h-full"
              :style="{
                border: '1px solid var(--color-hairline)',
                boxShadow: 'var(--elevation-2)'
              }"
            >
              <p class="text-base font-semibold text-label">{{ destination.title }}</p>
              <p class="mt-1 text-sm text-theme-secondary">{{ destination.description }}</p>
            </UiCard>
          </RouterLink>
        </div>
      </section>
    </div>

    <!-- New Appointment Modal: /appointments/new redirects here. -->
    <NewAppointmentModal v-model="showNewAppointmentModal" @created="handleAppointmentCreated" />
  </AppLayout>
</template>

<script setup>
import { ref, computed, nextTick, onMounted, onUnmounted } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { ArrowPathIcon, CheckCircleIcon, ExclamationTriangleIcon } from '@heroicons/vue/24/outline'
import NewAppointmentModal from '../../components/appointments/NewAppointmentModal.vue'
import PageHeader from '@/components/layout/PageHeader.vue'
import { useApi } from '../../composables/useApi'
import { useAuth } from '@/composables/useAuth'
// HOTFIX-DASH-009 / T4 - per-section staggered springs, consumed.
// Each visible section gets its own useSpring with a distinct cssVar so the
// entrance animations cannot collide on the same CSS custom property.
import { useSpring } from '../../composables/useSpring'
import { prefersReducedMotion } from '../../composables/useSpringMath'
import { usePermissions } from '../../composables/usePermissions'
import { useCashRegister } from '../../composables/useCashRegister'
import { useEcho } from '../../composables/useEcho'
import { markLocalAppointmentEcho } from '../../composables/useWebSocketNotifications'
import { formatDate, formatPENLabel, formatTime } from '@/composables/useFormatters'

const router = useRouter()
const route = useRoute()
const { user, isAuthenticated } = useAuth()
const { get } = useApi()
const { can } = usePermissions()
const { currentSession, hasActiveSession, isOpen, realTimeTotals, loadCurrentSession } =
  useCashRegister()
const { privateChannel, echo } = useEcho()

// #345 - the cash register is a permission-gated surface: a role without
// `view.cash_register` cannot read the session (the API answers 403 by
// design), so the dashboard must not ask for it. The same flag gates the
// card, the load and the channel subscription that refreshes it.
const canViewCashRegister = computed(() => can.viewCashRegister?.value === true)
// #378: every role reaches the dashboard, but the appointments channel only
// authorizes the desks that read the agenda, so finance must not ask for it.
const canViewAppointments = computed(() => can.viewAppointment?.value === true)

// State
// #387 - the first frame is the skeleton. Starting `false` painted the
// content branch before the summary resolved.
const loading = ref(true)
const hasLoaded = ref(false)
const refreshing = ref(false)
const statsError = ref(false)
const summary = ref({})

// T4 - per-section staggered springs (2 sections, 60ms stagger). Each spring
// targets a distinct CSS custom property so the entrances never collide.
const greetingSpring = useSpring({
  damping: 1.0,
  response: 0.35,
  cssVar: '--spring-dash-greeting-o'
})
const kpiSpring = useSpring({
  damping: 1.0,
  response: 0.35,
  cssVar: '--spring-dash-kpi-o'
})

const greetingSection = ref(null)
const kpiSection = ref(null)

// T4 - section reveal rule. The spring writes a 0..1 progress into its
// cssVar and this binding turns it into the entrance: opacity 0 -> 1 plus
// an 8px translate-up. The `, 1` fallback renders every section in its
// FINAL state whenever no spring is attached - reduced motion, or a section
// that mounts after the one-shot entrance already ran.
const revealStyle = cssVar => ({
  opacity: `var(${cssVar}, 1)`,
  transform: `translateY(calc((1 - var(${cssVar}, 1)) * 8px))`
})

// One-shot entrance for the first successful content render. Reduced motion
// never attaches: the fallback above already holds the final state.
let entrancePlayed = false
const playEntrance = () => {
  if (entrancePlayed) return
  entrancePlayed = true
  if (prefersReducedMotion()) return

  if (greetingSection.value) greetingSpring.attach(greetingSection.value)
  if (kpiSection.value) kpiSpring.attach(kpiSection.value)

  setTimeout(() => greetingSpring.set(1), 0)
  setTimeout(() => kpiSpring.set(1), 60)
}

// T4 - KPI count-up. The headline number counts 0 -> value on the first data
// payload, then hands the display back to the canonical source value, so
// refreshes update instantly instead of replaying the entrance.
const countUpAllowed = () => {
  if (prefersReducedMotion()) return false
  try {
    return window.matchMedia('(prefers-reduced-motion: no-preference)').matches === true
  } catch (_e) {
    return false
  }
}

const createCountUp = (readTarget, format = value => Math.round(value)) => {
  const active = ref(false)
  const { value: springValue, set: setSpringTarget } = useSpring({
    damping: 1.0,
    response: 0.35,
    onSettle: () => {
      active.value = false
    }
  })
  const display = computed(() =>
    active.value ? format(springValue.value) : format(readTarget() || 0)
  )
  const start = () => {
    active.value = true
    setSpringTarget(readTarget() || 0)
  }
  return { display, start }
}

// The day count comes from the summary endpoint, which already applies the
// effective branch scope. There is no second list to disagree with.
const citasHoy = computed(() => {
  const value = Number(summary.value?.appointments_today)
  return Number.isFinite(value) ? value : 0
})

const citasHoyKpi = createCountUp(() => citasHoy.value)

// While the summary is down the card reports "N/D" instead of a fabricated
// zero, bypassing the count-up display.
const citasHoyDisplay = computed(() => (statsError.value ? 'N/D' : citasHoyKpi.display.value))

let countUpPlayed = false
const startCitasCountUp = () => {
  if (countUpPlayed) return
  countUpPlayed = true
  if (!countUpAllowed()) return
  citasHoyKpi.start()
}

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
  return formatDate(new Date(), {
    weekday: 'long',
    year: 'numeric',
    month: 'long',
    day: 'numeric'
  })
}

/**
 * The three destinations of the daily work. Each card declares the read
 * permission its own screen demands, so the dashboard never offers a desk
 * the session cannot open. Pendientes answers two grants: the continuity desk
 * reaches it through `view.continuity` and finance through `view.quotation`.
 */
const DESTINATIONS = [
  {
    key: 'agenda',
    to: '/calendar',
    title: 'Agenda',
    description: 'Citas, horarios y sillones del día.',
    permission: 'viewCalendar'
  },
  {
    key: 'my-day',
    to: '/my-day',
    title: 'Mi jornada',
    description: 'Tu cola de atención del día.',
    permission: 'viewMyDay'
  },
  {
    key: 'pending',
    to: '/continuity',
    title: 'Pendientes',
    description: 'Recalls vencidos, solicitudes de continuidad y respuestas comerciales.',
    anyPermissions: ['viewContinuity', 'viewQuotation']
  }
]

const destinations = computed(() =>
  DESTINATIONS.filter(destination =>
    destination.anyPermissions
      ? destination.anyPermissions.some(key => can[key]?.value === true)
      : can[destination.permission]?.value === true
  )
)

// Navigation functions
const showNewAppointmentModal = ref(false)

// Una sola confirmación por cita creada, con la misma acción que la agenda.
// El modal no confirma y esta página no confirma dos veces: el aviso vive aquí
// y su botón abre el día que la cita ocupa.
const createdConfirmation = ref(null)

const dayOfCreated = payload => {
  const raw = payload?.date || payload?.scheduled_at || ''
  const match = String(raw).match(/^(\d{4})-(\d{2})-(\d{2})/)
  return match ? `${match[1]}-${match[2]}-${match[3]}` : ''
}

const handleAppointmentCreated = async payload => {
  const id = payload?.id ?? payload?.data?.id ?? null

  // La confirmación de esta pantalla es la única: el eco de la difusión del
  // shell se descarta con el id que se acaba de escribir.
  markLocalAppointmentEcho(id)

  createdConfirmation.value = {
    id,
    date: dayOfCreated(payload),
    summary: payload?.summary || ''
  }

  // The new appointment changes today's count; the summary is the only
  // visible reader, so a single refresh is enough.
  await loadDashboardData()
}

const openCreatedAppointmentInAgenda = () => {
  const date = createdConfirmation.value?.date
  router.push(date ? { path: '/calendar', query: { date } } : '/calendar')
}

const goToCashRegister = () => {
  router.push('/cash-register')
}

// WU2 / D4 - the cash card is the single cash surface. The state key rides
// only as the data-cash-state hook value; the visible line and its tone are
// always Spanish (a raw key wired to a primitive's status map would print
// English on the page):
//   - open        -> label "Abierta",     green token
//   - closed      -> label "Cerrada",     red token
//   - no_session  -> label "Sin sesión",  neutral theme token
const cashStateKey = computed(() => {
  if (isOpen.value) return 'open'
  if (hasActiveSession.value) return 'closed'
  return 'no_session'
})

const cashStateLabel = computed(() => {
  if (isOpen.value) return 'Abierta'
  if (hasActiveSession.value) return 'Cerrada'
  return 'Sin sesión'
})

const cashStateToneClass = computed(() => {
  if (isOpen.value) return 'text-systemGreen-600'
  if (hasActiveSession.value) return 'text-systemRed-600'
  return 'text-theme-secondary'
})

// WU2 - the balance and the caption read the SAME useCashRegister state as
// the state line (single source, R2). No session summary means "N/D", never
// a fabricated S/ 0.00.
const cashKpiBalance = computed(() =>
  realTimeTotals.value ? formatPENLabel(realTimeTotals.value.currentBalance) : 'N/D'
)

const cashKpiCaption = computed(() => {
  const openedAt = currentSession.value?.opened_at
  return isOpen.value && openedAt ? `Apertura ${formatTime(openedAt)}` : 'Sin sesión abierta'
})

// Data loading

/**
 * T3 - fetch the summary resource without throwing. A 401 is surfaced as
 * `unauthorized` so the caller keeps the /login redirect; any other failure
 * is surfaced as `ok: false` so the page renders its own error state instead
 * of swallowing the failure and painting zeros as real data.
 */
const fetchSummary = async () => {
  try {
    const response = await get('/api/dashboard/stats')
    const data = response?.data
    return {
      ok: true,
      unauthorized: false,
      data: data && typeof data === 'object' && !Array.isArray(data) ? data : {}
    }
  } catch (error) {
    return { ok: false, unauthorized: error?.status === 401, data: {} }
  }
}

/**
 * #387 - the cash session joins the load batch instead of gating it.
 * Already-loaded sessions are not re-fetched, and the permission gate of
 * #345 stays: a role without `view.cash_register` never asks for it.
 */
const loadCashSession = () => {
  if (!canViewCashRegister.value || currentSession.value) return Promise.resolve()
  return loadCurrentSession()
}

const loadDashboardData = async () => {
  if (!isAuthenticated.value) {
    router.push('/login')
    return
  }

  // T3 - only the first load owns the skeleton. Later loads (manual refresh,
  // WebSocket bursts, post-create reload) keep the current content mounted
  // and surface the lightweight in-flight indicator instead.
  if (hasLoaded.value) {
    refreshing.value = true
  } else {
    loading.value = true
  }

  try {
    const [summaryResult] = await Promise.all([fetchSummary(), loadCashSession()])

    if (summaryResult.unauthorized) {
      router.push('/login')
    } else if (summaryResult.ok) {
      summary.value = summaryResult.data
      statsError.value = false
      startCitasCountUp()
    } else {
      statsError.value = true
    }
  } finally {
    loading.value = false
    refreshing.value = false
    hasLoaded.value = true
  }

  // T4 - the section reveals play only on the first successful content
  // render, armed by the render itself and not by the summary's outcome.
  if (!entrancePlayed) {
    await nextTick()
    playEntrance()
  }
}

/**
 * #388 - summary-only retry. Re-fetches just /api/dashboard/stats so a failed
 * summary recovers its own block without re-fetching or unmounting the
 * destinations. 401 keeps the standard /login redirect.
 */
const retryStats = async () => {
  const result = await fetchSummary()
  if (result.unauthorized) {
    router.push('/login')
    return
  }
  if (result.ok) {
    summary.value = result.data
    statsError.value = false
  } else {
    statsError.value = true
  }
}

// WebSocket subscriptions
let dashboardChannel = null
let patientsChannel = null
let appointmentsChannel = null
let cashRegisterChannel = null

// A burst of events (a payment plus its appointment update) must not hit the
// API once per event: coalesce them into a single trailing-edge fetch. This
// 300ms debounce is load-bearing - do not change the timing without updating
// the apply-progress evidence.
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

// Lifecycle
onMounted(async () => {
  // Verificar si se debe abrir el modal de nueva cita (desde redirección)
  if (route.query.openAppointmentModal === 'true') {
    showNewAppointmentModal.value = true
    router.replace({ query: {} })
  }

  // Cargar los datos del dashboard. #387: la caja ya viaja dentro del lote
  // paralelo, así que no se pide antes ni bloquea al resumen.
  await loadDashboardData()

  // Suscribirse a canales WebSocket (Reverb is often not running locally;
  // connection errors here are expected and harmless - error handling is
  // inside useEcho).
  try {
    // Canal privado: el handshake de /api/broadcasting/auth verifica el rol
    // antes de entregar citas, pacientes y transacciones (#183).
    dashboardChannel = privateChannel('dashboard-updates')
    if (dashboardChannel) {
      dashboardChannel
        .listen('.dashboard.stats-updated', () => debouncedLoadDashboardData())
        .listen('.appointment.created', () => debouncedLoadDashboardData())
        .listen('.appointment.updated', () => debouncedLoadDashboardData())
        .listen('.appointment.deleted', () => debouncedLoadDashboardData())
        .listen('.user.created', () => debouncedLoadDashboardData())
        .listen('.user.updated', () => debouncedLoadDashboardData())
    }

    // Los eventos de paciente viajan por el canal privado `patients`
    // (llevan datos clínicos), no por `dashboard-updates`.
    patientsChannel = privateChannel('patients')
    if (patientsChannel) {
      patientsChannel
        .listen('.patient.created', () => debouncedLoadDashboardData())
        .listen('.patient.updated', () => debouncedLoadDashboardData())
        .listen('.patient.deleted', () => debouncedLoadDashboardData())
    }

    appointmentsChannel = canViewAppointments.value ? privateChannel('appointments') : null
    if (appointmentsChannel) {
      appointmentsChannel
        .listen('.appointment.created', () => debouncedLoadDashboardData())
        .listen('.appointment.updated', () => debouncedLoadDashboardData())
        .listen('.appointment.deleted', () => debouncedLoadDashboardData())
    }

    // #345 - the channel publishes the desk's money, so a role without
    // view.cash_register is not authorized to subscribe either.
    if (canViewCashRegister.value) {
      cashRegisterChannel = privateChannel('cash-register')
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
    }
  } catch (error) {
    // Reverb unreacheable in dev is expected.
  }
})

onUnmounted(() => {
  // Clear any pending debounced fetch on unmount so we don't fire against a
  // torn-down component.
  if (dashboardDebounceTimer !== null) {
    clearTimeout(dashboardDebounceTimer)
    dashboardDebounceTimer = null
  }

  if (echo) {
    try {
      // Private channels are registered with the `private-` prefix on the wire,
      // so the leave must name the wire channel.
      echo.leave('private-dashboard-updates')
      echo.leave('private-patients')
      echo.leave('private-appointments')
      echo.leave('private-cash-register')
    } catch (e) {
      // teardown is best-effort
    }
  }
})
</script>
