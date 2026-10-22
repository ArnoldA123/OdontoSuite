<template>
  <AppLayout>
    <div class="space-y-8" data-my-day>
      <!--
        Hero band in the dashboard's surface vocabulary: canvas fill, hairline,
        panel radius and the elevation-2 rung. The page owns its visible title
        (h2, not h1), so the topbar keeps its heading for the outline only.
      -->
      <header
        data-my-day-hero
        class="p-6 md:p-8"
        style="
          background: var(--color-canvas);
          border: 1px solid var(--color-hairline);
          border-radius: var(--radius-panel);
          box-shadow: var(--elevation-2);
        "
      >
        <div class="flex flex-col gap-6 md:flex-row md:items-start md:justify-between">
          <div class="min-w-0">
            <p class="text-xs font-medium uppercase tracking-wide text-theme-secondary">Mi día</p>
            <h2 class="mt-2 text-4xl font-semibold text-label" data-my-day-title>
              {{ getGreeting() }}, {{ firstName }}
            </h2>
            <p
              class="mt-2 text-sm text-theme-secondary"
              style="font-feature-settings: var(--font-features-tabular-nums)"
              data-my-day-date
            >
              {{ todayLabel }}
            </p>
          </div>

          <!-- The three queue counts summarize the three sections below. -->
          <dl class="grid grid-cols-3 gap-3" data-my-day-counts>
            <div
              v-for="stat in statCards"
              :key="stat.key"
              class="min-w-[5.5rem] px-4 py-3 text-center"
              style="
                background: var(--color-system-gray-100);
                border: 1px solid var(--color-hairline);
                border-radius: var(--radius-panel);
              "
              :data-my-day-count="stat.key"
            >
              <dt class="text-xs font-medium uppercase text-theme-secondary">{{ stat.label }}</dt>
              <dd
                class="mt-1 text-2xl font-semibold text-label tabular-nums"
                style="font-feature-settings: var(--font-features-tabular-nums)"
              >
                {{ counts[stat.key] ?? 0 }}
              </dd>
            </div>
          </dl>
        </div>
      </header>

      <!--
        Refresh failure. The composable keeps the last queue mounted, so this
        block reports the stale state and offers the retry instead of clearing
        the desk mid-consultation.
      -->
      <div
        v-if="error"
        data-my-day-error
        role="alert"
        class="flex flex-wrap items-center gap-4 p-4 rounded-ios bg-systemRed-50"
        style="border: 1px solid var(--color-hairline)"
      >
        <ExclamationTriangleIcon
          class="h-8 w-8 flex-shrink-0 text-systemRed-600"
          aria-hidden="true"
        />
        <div class="min-w-0 flex-1">
          <p class="text-sm font-medium text-theme-primary">No pudimos actualizar tu día</p>
          <p class="mt-1 text-sm text-theme-secondary" data-my-day-error-message>{{ error }}</p>
        </div>
        <UiButton variant="primary" size="sm" data-my-day-retry @click="load">Reintentar</UiButton>
      </div>

      <!-- Loading shape mirrors the three sections so the page does not jump. -->
      <div v-if="showSkeleton" class="space-y-6" aria-busy="true" data-my-day-loading>
        <section
          v-for="section in sections"
          :key="`skeleton-${section.key}`"
          :aria-label="section.title"
        >
          <UiSkeleton
            variant="list"
            animation="wave"
            :aria-label="`Cargando ${section.title.toLowerCase()}`"
          />
        </section>
      </div>

      <template v-else-if="!error || hasRows">
        <section
          v-for="section in sections"
          :key="section.key"
          :data-my-day-section="section.key"
          :aria-label="section.title"
          class="space-y-3"
        >
          <div class="flex items-baseline gap-3">
            <h3 class="text-xl font-semibold text-label">{{ section.title }}</h3>
            <p class="text-sm text-theme-secondary">{{ section.caption }}</p>
          </div>

          <UiSectionEmpty
            v-if="section.rows.length === 0"
            :data-my-day-empty="section.key"
            :title="section.emptyTitle"
            :description="section.emptyDescription"
          />

          <div v-else class="grid gap-3">
            <UiCard
              v-for="appointment in section.rows"
              :key="appointment.id"
              variant="flat"
              data-my-day-row
              :data-my-day-row-section="section.key"
            >
              <div class="flex flex-wrap items-center gap-3">
                <p
                  class="w-14 flex-shrink-0 text-sm font-semibold text-label tabular-nums"
                  style="font-feature-settings: var(--font-features-tabular-nums)"
                  data-my-day-time
                >
                  {{ formatCompactTime(appointment.scheduled_at) }}
                </p>
                <div class="min-w-0 flex-1">
                  <p class="truncate font-medium text-label" data-my-day-patient>
                    {{ patientName(appointment) }}
                  </p>
                  <p class="truncate text-sm text-theme-secondary">
                    {{ appointment.appointment_type?.name || 'Consulta' }}
                    <span v-if="chairName(appointment)">· {{ chairName(appointment) }}</span>
                  </p>
                </div>
                <UiStatusBadge
                  :variant="statusBadgeVariant(appointment.status)"
                  :label="statusLabel(appointment.status)"
                  size="sm"
                  show-dot
                />
                <UiButton
                  v-if="actionFor(appointment)"
                  variant="primary"
                  size="sm"
                  :disabled="acting === appointment.id"
                  :data-my-day-action="actionFor(appointment).hook"
                  @click="runAction(appointment)"
                >
                  {{ actionFor(appointment).label }}
                </UiButton>
              </div>
            </UiCard>
          </div>
        </section>
      </template>
    </div>

    <ConsultationWizard
      :appointment="wizardAppointment"
      @completed="onConsultationCompleted"
      @close="wizardAppointment = null"
    />
  </AppLayout>
</template>

<script setup>
import { computed, onMounted, onUnmounted, ref } from 'vue'
import { ExclamationTriangleIcon } from '@heroicons/vue/24/outline'
import AppLayout from '../../components/layout/AppLayout.vue'
import UiButton from '../../components/ui/Button.vue'
import UiCard from '../../components/ui/Card.vue'
import UiSectionEmpty from '../../components/ui/UiSectionEmpty.vue'
import UiSkeleton from '../../components/ui/Skeleton.vue'
import UiStatusBadge from '../../components/ui/StatusBadge.vue'
import ConsultationWizard from '../appointments/ConsultationWizard.vue'
import { statusBadgeVariant, statusLabel } from '../appointments/appointmentStatus'
import { useAuth } from '../../composables/useAuth'
import { useConsultation } from '../../composables/useConsultation'
import { useMyDay } from '../../composables/useMyDay'
import { usePermissions } from '../../composables/usePermissions'
import { formatCompactTime } from '../../composables/useFormatters'

const { user } = useAuth()
const { can } = usePermissions()
const { openForAppointment } = useConsultation()
const {
  queue,
  counts,
  loading,
  error,
  acting,
  load,
  markArrival,
  startAttention,
  subscribe,
  unsubscribe
} = useMyDay()

const wizardAppointment = ref(null)

const getGreeting = () => {
  const hour = new Date().getHours()
  if (hour < 12) return 'Buenos días'
  if (hour < 18) return 'Buenas tardes'
  return 'Buenas noches'
}

const firstName = computed(() => (user.value?.name || '').split(' ')[0] || 'equipo')

const todayLabel = new Date().toLocaleDateString('es-ES', {
  weekday: 'long',
  day: 'numeric',
  month: 'long'
})

const statCards = [
  { key: 'waiting', label: 'En espera' },
  { key: 'attending', label: 'Atendiendo' },
  { key: 'upcoming', label: 'Próximos' }
]

const sections = computed(() => [
  {
    key: 'waiting',
    title: 'En espera',
    caption: 'Pacientes que ya llegaron',
    emptyTitle: 'Sin pacientes en espera',
    emptyDescription: 'Cuando recepción registre una llegada, el paciente aparecerá aquí.',
    rows: queue.value.waiting
  },
  {
    key: 'attending',
    title: 'Atendiendo',
    caption: 'Consultas en curso',
    emptyTitle: 'Sin consultas en curso',
    emptyDescription: 'Inicia la atención de un paciente en espera para verlo aquí.',
    rows: queue.value.attending
  },
  {
    key: 'upcoming',
    title: 'Próximos',
    caption: 'Citas de hoy por llegar',
    emptyTitle: 'Sin citas pendientes hoy',
    emptyDescription: 'Las citas de hoy que aún no llegan aparecerán aquí.',
    rows: queue.value.upcoming
  }
])

const hasRows = computed(() => sections.value.some(section => section.rows.length > 0))
const showSkeleton = computed(() => loading.value && !hasRows.value)

const patientName = appointment => {
  const patient = appointment?.patient
  if (!patient) return 'Paciente'
  if (patient.full_name) return patient.full_name
  const composed = [patient.first_name, patient.last_name].filter(Boolean).join(' ').trim()
  return composed || 'Paciente'
}

const chairName = appointment => appointment?.dental_chair?.name || ''

const continueConsultation = async appointment => {
  wizardAppointment.value = appointment
  await openForAppointment(appointment)
}

// One action per custody state, each behind the permission that door requires.
// The professional's arrival fallback (S4) only appears with `check_in`.
const ACTIONS = {
  arrive: {
    hook: 'arrive',
    label: 'Marcar llegada',
    allowed: () => Boolean(can.checkInAppointment?.value),
    run: appointment => markArrival(appointment)
  },
  start: {
    hook: 'start',
    label: 'Iniciar atención',
    allowed: () => Boolean(can.startConsultation?.value),
    run: appointment => startAttention(appointment)
  },
  continue: {
    hook: 'continue',
    label: 'Continuar consulta',
    allowed: () => Boolean(can.completeConsultation?.value),
    run: appointment => continueConsultation(appointment)
  }
}

const ACTION_BY_STATUS = {
  scheduled: 'arrive',
  confirmed: 'arrive',
  arrived: 'start',
  in_consultation: 'continue',
  in_progress: 'continue'
}

const actionFor = appointment => {
  const action = ACTIONS[ACTION_BY_STATUS[appointment?.status]]
  if (!action || !action.allowed()) return null
  return action
}

const runAction = async appointment => {
  const action = actionFor(appointment)
  if (!action) return
  try {
    await action.run(appointment)
  } catch (thrown) {
    // The composable already surfaced the server message as a toast.
  }
}

const onConsultationCompleted = async () => {
  wizardAppointment.value = null
  await load()
}

onMounted(async () => {
  await load()
  subscribe(user.value?.id)
})

onUnmounted(() => {
  unsubscribe(user.value?.id)
})
</script>
