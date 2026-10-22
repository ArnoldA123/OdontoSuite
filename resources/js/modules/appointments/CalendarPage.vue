<template>
  <AppLayout>
    <!-- Hero band: dashboard hero grammar (canvas + hairline + radius-panel + elevation-2) -->
    <header
      data-calendar-shell="hero"
      class="relative overflow-hidden p-6 md:p-8"
      style="
        background: var(--color-canvas);
        border: 1px solid var(--color-hairline);
        border-radius: var(--radius-panel);
        box-shadow: var(--elevation-2);
      "
    >
      <DentalMotifLayer variant="hero" data-calendar-motif />
      <div class="relative flex items-start justify-between gap-3">
        <p class="text-xs font-semibold uppercase tracking-widest text-theme-secondary">Agenda</p>
        <span
          class="inline-flex items-center gap-2 px-3 py-1.5 rounded-full bg-systemGreen-50 text-systemGreen-700 text-xs font-medium"
          aria-label="Actualizaciones en tiempo real activas"
          title="Actualizaciones en tiempo real activas"
        >
          <span
            class="w-2 h-2 rounded-full bg-systemGreen-500 animate-pulse-subtle"
            aria-hidden="true"
          />
          En vivo
        </span>
      </div>
      <p class="relative mt-3 text-4xl font-semibold text-label">
        {{ currentPeriodTitle }}
      </p>
      <p
        class="relative mt-2 text-base leading-relaxed text-theme-secondary"
        style="
          font-feature-settings:
            'tnum' 1,
            'lnum' 1;
        "
      >
        {{ loadedAppointmentsCaption }}
      </p>
      <div class="relative mt-6 flex flex-wrap items-center gap-3">
        <div
          class="inline-flex items-center gap-1 rounded-full border border-hairline bg-theme-surface-elevated p-1"
          role="group"
          aria-label="Vista del calendario"
          data-calendar-view-switcher
        >
          <UiButton
            size="sm"
            :variant="currentView === 'day' ? 'primary' : 'ghost'"
            :aria-pressed="currentView === 'day'"
            data-calendar-view="day"
            @click="changeView('day')"
          >
            Día
          </UiButton>
          <UiButton
            size="sm"
            :variant="currentView === 'week' ? 'primary' : 'ghost'"
            :aria-pressed="currentView === 'week'"
            data-calendar-view="week"
            @click="changeView('week')"
          >
            Semana
          </UiButton>
          <UiButton
            size="sm"
            :variant="currentView === 'month' ? 'primary' : 'ghost'"
            :aria-pressed="currentView === 'month'"
            data-calendar-view="month"
            @click="changeView('month')"
          >
            Mes
          </UiButton>
        </div>
        <div class="inline-flex items-center gap-1">
          <UiButton
            variant="ghost"
            size="sm"
            aria-label="Periodo anterior"
            data-calendar-nav="prev"
            @click="previousPeriod"
          >
            <template #icon-left>
              <ChevronLeftIcon class="w-4 h-4" aria-hidden="true" />
            </template>
          </UiButton>
          <UiButton variant="ghost" size="sm" data-calendar-nav="today" @click="goToToday">
            Hoy
          </UiButton>
          <UiButton
            variant="ghost"
            size="sm"
            aria-label="Periodo siguiente"
            data-calendar-nav="next"
            @click="nextPeriod"
          >
            <template #icon-left>
              <ChevronRightIcon class="w-4 h-4" aria-hidden="true" />
            </template>
          </UiButton>
        </div>
        <div class="flex items-center gap-3 sm:ml-auto">
          <UiButton variant="secondary" size="sm" data-calendar-action="back" @click="goBack">
            <template #icon-left>
              <ArrowLeftIcon class="w-4 h-4" aria-hidden="true" />
            </template>
            Volver
          </UiButton>
          <UiButton
            v-if="can.createAppointment?.value"
            data-calendar-action="new-appointment"
            @click="openNewAppointmentModal"
          >
            <template #icon-left>
              <PlusIcon class="w-4 h-4" aria-hidden="true" />
            </template>
            Nueva cita
          </UiButton>
        </div>
      </div>
    </header>

    <!-- Calendar Content: quiet surface (canvas + hairline + radius-panel + elevation-2) -->
    <section
      data-calendar-shell="grid"
      class="overflow-hidden bg-canvas"
      style="
        border: 1px solid var(--color-hairline);
        border-radius: var(--radius-panel);
        box-shadow: var(--elevation-2);
      "
    >
      <!-- Legend: registry-driven status pills as the surface's header strip -->
      <div
        class="flex flex-wrap items-center gap-2 border-b border-hairline bg-theme-surface px-6 py-4"
        data-calendar-legend
      >
        <UiBadge
          v-for="status in legendStatuses"
          :key="status"
          size="sm"
          :variant="statusBadgeVariant(status)"
          :data-status="status"
        >
          {{ statusLabel(status) }}
        </UiBadge>
      </div>
      <!-- Loading State -->
      <div v-if="loading" class="p-12 text-center">
        <LoadingSpinner size="lg" text="Cargando agenda..." />
        <p class="mt-4 text-theme-secondary">Cargando agenda...</p>
      </div>

      <!-- Empty State (D1/D9) -->
      <div
        v-else-if="appointments.length === 0"
        data-calendar-empty
        class="relative overflow-hidden p-6 text-center"
        style="
          background: var(--color-canvas);
          border: 1px solid var(--color-hairline);
          border-radius: var(--radius-panel);
        "
      >
        <DentalMotifLayer variant="box" data-calendar-empty-motif />
        <div class="relative">
          <CalendarDaysIcon class="mx-auto mb-3 h-8 w-8 text-theme-secondary" aria-hidden="true" />
          <p class="text-sm font-medium text-theme-primary">{{ emptyStateCopy.title }}</p>
          <p class="mx-auto mt-1 max-w-md text-xs text-theme-secondary">
            {{ emptyStateCopy.description }}
          </p>
          <UiButton
            v-if="can.createAppointment?.value"
            size="sm"
            class="mt-4"
            data-calendar-action="empty-new-appointment"
            @click="openNewAppointmentModal"
          >
            Nueva cita
          </UiButton>
        </div>
      </div>

      <!-- Day View -->
      <div v-else-if="currentView === 'day'" class="p-6">
        <div
          class="overflow-hidden bg-canvas"
          style="border: 1px solid var(--color-hairline); border-radius: var(--radius-panel)"
        >
          <div
            v-for="hour in dayHours"
            :key="hour"
            class="flex items-stretch border-b border-hairline last:border-b-0"
          >
            <div
              class="w-16 shrink-0 border-r border-hairline bg-theme-surface px-3 py-2 text-right text-xs text-theme-secondary"
              style="font-feature-settings: 'tnum' 1"
            >
              {{ formatHour(hour) }}
            </div>
            <div
              class="relative min-h-[64px] flex-1 px-3 py-2"
              :class="dropTargetClasses(`${dayDateString}-${hour}`)"
              :data-calendar-drop-target="`${dayDateString}-${hour}`"
              :data-calendar-drop-active="dropHoverSlot === `${dayDateString}-${hour}` ? '' : null"
            >
              <button
                v-if="can.createAppointment?.value"
                type="button"
                class="absolute inset-0 w-full transition-colors hover:bg-theme-surface [&:focus-visible]:[box-shadow:var(--focus-ring-default)]"
                :data-calendar-slot="`${dayDateString}-${hour}`"
                :aria-label="`Crear cita el ${dayDateString} a las ${formatHour(hour)}`"
                @click="openSlotCreate(dayDateString, hour)"
              ></button>
              <div class="pointer-events-none relative space-y-2">
                <button
                  v-for="appointment in appointmentsForSlot(dayDateString, hour)"
                  :key="appointment.id"
                  type="button"
                  class="pointer-events-auto block w-full px-3 py-2 text-left transition-shadow hover:shadow-medium [&:focus-visible]:[box-shadow:var(--focus-ring-default)]"
                  :class="getAppointmentClasses(appointment)"
                  style="border-radius: var(--radius-panel)"
                  :title="chipTitle(appointment)"
                  :data-calendar-appointment="appointment.id"
                  :data-calendar-draggable="appointment.id"
                  :data-calendar-drag-state="
                    draggingAppointmentId === appointment.id ? 'active' : null
                  "
                  @pointerdown="onChipPointerDown($event, appointment)"
                  @click="selectAppointment(appointment)"
                >
                  <span class="flex items-start justify-between gap-3">
                    <span class="min-w-0">
                      <span
                        class="block truncate text-xs text-theme-primary"
                        style="font-feature-settings: 'tnum' 1"
                      >
                        {{ chipTimeRange(appointment) }}
                      </span>
                      <span class="block truncate text-sm font-semibold text-theme-primary">
                        {{ appointment.patient?.first_name }} {{ appointment.patient?.last_name }}
                      </span>
                      <span
                        v-if="appointmentMetaLine(appointment)"
                        class="block truncate text-xs text-theme-secondary"
                      >
                        {{ appointmentMetaLine(appointment) }}
                      </span>
                    </span>
                    <UiStatusBadge
                      size="sm"
                      :variant="getStatusBadgeProps(appointment.status).variant"
                      :label="getStatusText(appointment.status)"
                      :show-dot="getStatusBadgeProps(appointment.status).showDot"
                      :data-status="appointment.status"
                    />
                  </span>
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Week View -->
      <div v-else-if="currentView === 'week'" class="p-6">
        <div
          class="overflow-hidden bg-canvas"
          style="border: 1px solid var(--color-hairline); border-radius: var(--radius-panel)"
        >
          <div class="grid grid-cols-8">
            <!-- Header row -->
            <div class="border-b border-r border-hairline bg-theme-surface" />
            <div
              v-for="day in weekDays"
              :key="day.date"
              class="border-b border-r border-hairline bg-theme-surface px-2 py-2 text-center"
            >
              <div class="text-xs font-semibold uppercase tracking-widest text-theme-secondary">
                {{ day.name }}
              </div>
              <div
                class="text-sm font-medium text-theme-primary"
                style="font-feature-settings: 'tnum' 1"
              >
                {{ day.number }}
              </div>
            </div>

            <!-- Hour rows: quiet hairline grid, one slot button per day+hour cell -->
            <template v-for="hour in weekHours" :key="hour">
              <div
                class="border-b border-r border-hairline bg-theme-surface px-2 py-1 text-right text-xs text-theme-secondary"
                style="font-feature-settings: 'tnum' 1"
              >
                {{ formatHour(hour) }}
              </div>
              <div
                v-for="day in weekDays"
                :key="`${day.date}-${hour}`"
                class="relative h-16 border-b border-r border-hairline"
                :class="dropTargetClasses(`${day.date}-${hour}`)"
                :data-calendar-slot-day="day.date"
                :data-calendar-drop-target="`${day.date}-${hour}`"
                :data-calendar-drop-active="dropHoverSlot === `${day.date}-${hour}` ? '' : null"
              >
                <button
                  v-if="can.createAppointment?.value"
                  type="button"
                  class="absolute inset-0 w-full transition-colors hover:bg-theme-surface [&:focus-visible]:[box-shadow:var(--focus-ring-default)]"
                  :data-calendar-slot="`${day.date}-${hour}`"
                  :aria-label="`Crear cita el ${day.date} a las ${formatHour(hour)}`"
                  @click="openSlotCreate(day.date, hour)"
                ></button>
                <div class="pointer-events-none absolute inset-1 flex flex-col gap-1">
                  <button
                    v-for="appointment in appointmentsForSlot(day.date, hour)"
                    :key="appointment.id"
                    type="button"
                    class="pointer-events-auto min-h-0 flex-1 overflow-hidden px-1.5 py-1 text-left transition-shadow hover:shadow-medium [&:focus-visible]:[box-shadow:var(--focus-ring-default)]"
                    :class="getAppointmentClasses(appointment)"
                    style="border-radius: var(--radius-panel)"
                    :title="chipTitle(appointment)"
                    :data-calendar-appointment="appointment.id"
                    :data-calendar-draggable="appointment.id"
                    :data-calendar-drag-state="
                      draggingAppointmentId === appointment.id ? 'active' : null
                    "
                    @pointerdown="onChipPointerDown($event, appointment)"
                    @click="selectAppointment(appointment)"
                  >
                    <span
                      class="block truncate text-xs font-semibold text-theme-primary"
                      style="font-feature-settings: 'tnum' 1"
                    >
                      {{ chipTimeRange(appointment) }} · {{ appointment.patient?.first_name }}
                      {{ appointment.patient?.last_name }}
                    </span>
                    <span
                      v-if="appointmentMetaLine(appointment)"
                      class="block truncate text-xs text-theme-secondary"
                    >
                      {{ appointmentMetaLine(appointment) }}
                    </span>
                  </button>
                </div>
              </div>
            </template>
          </div>
        </div>
      </div>

      <!-- Month View -->
      <div v-else-if="currentView === 'month'" class="p-6">
        <div
          data-calendar-month-grid
          class="overflow-hidden bg-canvas"
          style="border: 1px solid var(--color-hairline); border-radius: var(--radius-panel)"
        >
          <!-- Weekday header row: same Spanish short names as the week view -->
          <div class="grid grid-cols-7 border-b border-hairline bg-theme-surface">
            <div
              v-for="name in weekDayNames"
              :key="name"
              data-calendar-month-weekday
              class="border-r border-hairline px-2 py-2 text-center text-xs font-semibold uppercase tracking-widest text-theme-secondary last:border-r-0"
            >
              {{ name }}
            </div>
          </div>

          <!-- 42 day cells (6 rows): hairline separators, registry chips, "N más" drill-down -->
          <div class="grid grid-cols-7">
            <div
              v-for="day in monthDays"
              :key="day.date"
              class="flex min-h-[112px] flex-col gap-1 border-b border-r border-hairline p-1.5"
              :class="monthCellClasses(day)"
              :data-calendar-month-day="day.date"
            >
              <div class="flex items-center justify-between gap-1">
                <button
                  type="button"
                  class="flex h-6 min-w-6 items-center justify-center rounded-full px-1.5 text-xs font-medium tabular-nums transition-colors [&:focus-visible]:[box-shadow:var(--focus-ring-default)]"
                  :class="monthDayNumberClasses(day)"
                  :data-calendar-day-link="day.date"
                  :aria-label="`Ver ${day.date} en la vista día`"
                  @click="drillDownToDate(day.date)"
                >
                  {{ day.number }}
                </button>
              </div>

              <div class="flex min-h-0 flex-1 flex-col gap-1">
                <button
                  v-for="appointment in day.appointments"
                  :key="appointment.id"
                  type="button"
                  class="flex w-full items-center gap-1.5 px-1.5 py-1 text-left transition-shadow hover:shadow-medium [&:focus-visible]:[box-shadow:var(--focus-ring-default)]"
                  :class="getAppointmentClasses(appointment)"
                  style="border-radius: var(--radius-panel)"
                  :title="chipTitle(appointment)"
                  :data-calendar-appointment="appointment.id"
                  @click="selectAppointment(appointment)"
                >
                  <span
                    class="shrink-0 text-xs font-semibold text-theme-primary"
                    style="font-feature-settings: 'tnum' 1"
                  >
                    {{ chipTimeRange(appointment) }}
                  </span>
                  <span class="min-w-0 flex-1 truncate text-xs text-theme-primary">
                    {{ appointment.patient?.first_name }} {{ appointment.patient?.last_name }}
                  </span>
                </button>

                <button
                  v-if="day.overflowCount > 0"
                  type="button"
                  class="w-full rounded-md px-1.5 py-0.5 text-left text-xs font-medium text-accent-700 transition-colors hover:bg-theme-surface [&:focus-visible]:[box-shadow:var(--focus-ring-default)]"
                  :data-calendar-more="day.date"
                  :data-calendar-more-count="day.overflowCount"
                  :aria-label="`Ver las ${day.overflowCount} citas de ${day.date} en la vista día`"
                  @click="drillDownToDate(day.date)"
                >
                  +{{ day.overflowCount }} más
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- Appointment Details Modal -->
    <UiModal
      :model-value="!!selectedAppointment"
      title="Detalles de la Cita"
      size="lg"
      @update:model-value="closeDetails"
      @close="closeDetails"
    >
      <div v-if="selectedAppointment" data-calendar-detail class="space-y-4">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div>
            <label class="text-sm font-medium text-theme-secondary">Paciente</label>
            <p class="text-theme-primary">
              {{ selectedAppointment.patient?.first_name }}
              {{ selectedAppointment.patient?.last_name }}
            </p>
          </div>
          <div>
            <label class="text-sm font-medium text-theme-secondary">Profesional</label>
            <p class="text-theme-primary">
              {{ selectedAppointment.user?.name }}
            </p>
          </div>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div>
            <label class="text-sm font-medium text-theme-secondary">Fecha y Hora</label>
            <p class="text-theme-primary" style="font-feature-settings: 'tnum' 1">
              {{ formatDateTime(selectedAppointment.scheduled_at) }}
            </p>
            <p class="text-sm text-theme-secondary" style="font-feature-settings: 'tnum' 1">
              {{ appointmentTimeRange(selectedAppointment) }}
            </p>
          </div>
          <div>
            <label class="text-sm font-medium text-theme-secondary">Duración</label>
            <p class="text-theme-primary">{{ selectedAppointment.duration_minutes }} minutos</p>
          </div>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div v-if="selectedAppointment.appointment_type?.name">
            <label class="text-sm font-medium text-theme-secondary">Tipo de Cita</label>
            <p class="text-theme-primary">
              {{ selectedAppointment.appointment_type.name }}
            </p>
          </div>
          <div v-if="selectedAppointment.dental_chair?.name">
            <label class="text-sm font-medium text-theme-secondary">Sillón Dental</label>
            <p class="text-theme-primary">Sillón {{ selectedAppointment.dental_chair.name }}</p>
          </div>
          <div>
            <label class="text-sm font-medium text-theme-secondary">Estado</label>
            <UiStatusBadge
              :variant="getStatusBadgeProps(selectedAppointment.status).variant"
              :label="getStatusText(selectedAppointment.status)"
              :show-dot="getStatusBadgeProps(selectedAppointment.status).showDot"
              :data-status="selectedAppointment.status"
            />
          </div>
        </div>
        <div v-if="selectedAppointment.notes">
          <label class="text-sm font-medium text-theme-secondary">Notas</label>
          <p class="text-theme-primary">
            {{ selectedAppointment.notes }}
          </p>
        </div>
        <div v-if="selectedAppointment.treatment_notes">
          <label class="text-sm font-medium text-theme-secondary">Notas de Tratamiento</label>
          <p class="text-theme-primary">
            {{ selectedAppointment.treatment_notes }}
          </p>
        </div>
        <div class="flex flex-wrap gap-2 pt-2 border-t border-hairline">
          <UiButton
            v-if="selectedAppointment.status === 'scheduled'"
            size="sm"
            variant="secondary"
            data-calendar-action="status-confirmed"
            @click="changeStatus(selectedAppointment, 'confirmed')"
          >
            Confirmar
          </UiButton>
          <UiButton
            v-if="
              can.checkInAppointment?.value &&
              (selectedAppointment.status === 'confirmed' ||
                selectedAppointment.status === 'scheduled')
            "
            size="sm"
            variant="secondary"
            data-calendar-action="check-in"
            @click="startConsultation(selectedAppointment)"
          >
            Iniciar Consulta
          </UiButton>
          <UiButton
            v-if="
              can.completeConsultation?.value && selectedAppointment.status === 'in_consultation'
            "
            size="sm"
            variant="primary"
            data-calendar-action="complete-consultation"
            @click="openConsultationWizard(selectedAppointment)"
          >
            Completar Consulta
          </UiButton>
          <UiButton
            v-if="!['cancelled', 'completed'].includes(selectedAppointment.status)"
            size="sm"
            variant="danger"
            data-calendar-action="status-cancelled"
            @click="changeStatus(selectedAppointment, 'cancelled')"
          >
            Cancelar Cita
          </UiButton>
        </div>
        <div class="flex justify-end gap-3 pt-4">
          <UiButton
            v-if="can.editAppointment?.value"
            variant="secondary"
            data-calendar-action="edit"
            @click="editAppointment(selectedAppointment)"
          >
            Editar
          </UiButton>
          <UiButton
            v-if="can.deleteAppointment?.value"
            variant="danger"
            data-calendar-action="delete"
            @click="deleteAppointment(selectedAppointment)"
          >
            Eliminar
          </UiButton>
        </div>
      </div>
    </UiModal>

    <!-- New Appointment Modal -->
    <NewAppointmentModal
      v-model="showNewAppointmentModal"
      :initial-date="newAppointmentPrefill"
      :appointment="editingAppointment"
      @created="handleAppointmentSaved"
      @updated="handleAppointmentSaved"
    />

    <ConsultationWizard
      :appointment="wizardAppointment"
      @completed="onConsultationCompleted"
      @close="wizardAppointment = null"
    />
  </AppLayout>
</template>

<script>
import { ref, computed, watch, onMounted, onUnmounted } from 'vue'
import { useRouter } from 'vue-router'
import { useApi } from '../../composables/useApi'
import { usePermissions } from '../../composables/usePermissions'
import { useToast } from '../../composables/useToast'
import { useEcho } from '../../composables/useEcho'
import { useReducedMotion } from '../../composables/useReducedMotion'
import AppLayout from '../../components/layout/AppLayout.vue'
import UiButton from '../../components/ui/Button.vue'
import UiBadge from '../../components/ui/Badge.vue'
import UiStatusBadge from '../../components/ui/StatusBadge.vue'
import UiModal from '../../components/ui/Modal.vue'
import DentalMotifLayer from '../../components/ui/DentalMotifLayer.vue'
import {
  ArrowLeftIcon,
  CalendarDaysIcon,
  ChevronLeftIcon,
  ChevronRightIcon,
  PlusIcon
} from '@heroicons/vue/24/outline'
import NewAppointmentModal from '../../components/appointments/NewAppointmentModal.vue'
import ConsultationWizard from './ConsultationWizard.vue'
import { useConsultation } from '../../composables/useConsultation'
import { useConfirm } from '../../composables/useConfirm'
import { formatCompactTimeRange } from '../../composables/useFormatters'
import {
  APPOINTMENT_CHIP_BASE_CLASSES,
  APPOINTMENT_STATUS_KEYS,
  statusLabel,
  statusBadgeProps,
  statusBadgeVariant,
  statusChipClasses,
  statusToneClasses
} from './appointmentStatus'
import {
  WEEK_DAY_NAMES,
  buildHours,
  toLocalDateString,
  weekDays as buildWeekDays,
  monthDays as buildMonthDays,
  periodTitle,
  dateRangeForView,
  addPeriod,
  splitMonthCellAppointments
} from './calendarDateMath'
import {
  DRAG_THRESHOLD_PX,
  resolveDropSlot,
  slotKeyToScheduledAt,
  addMinutesToLocal,
  canDropOn
} from './dragDropMath'

export default {
  name: 'CalendarPage',
  components: {
    AppLayout,
    UiButton,
    UiBadge,
    UiStatusBadge,
    UiModal,
    DentalMotifLayer,
    ArrowLeftIcon,
    CalendarDaysIcon,
    ChevronLeftIcon,
    ChevronRightIcon,
    PlusIcon,
    NewAppointmentModal,
    ConsultationWizard
  },
  setup() {
    const router = useRouter()
    const { get, del, patch, normalizeError } = useApi()
    const { can } = usePermissions()
    const toast = useToast()
    const { privateChannel, echo } = useEcho()
    const { confirm } = useConfirm()
    const reducedMotion = useReducedMotion()

    // can ya es un objeto con propiedades computed, usarlo directamente

    // T7: starts true so the D1/D9 empty block never flashes before the
    // first range load (empty state must never appear while loading).
    const loading = ref(true)
    const currentView = ref('week')
    const currentDate = ref(new Date())
    const appointments = ref([])
    const selectedAppointment = ref(null)
    const showNewAppointmentModal = ref(false)
    const wizardAppointment = ref(null)
    const { checkIn, openForAppointment } = useConsultation()

    const startConsultation = async appointment => {
      try {
        await checkIn(appointment)
        await loadAppointments()
        selectedAppointment.value = null
      } catch (e) {
        // toast ya se mostró en el composable
      }
    }

    const openConsultationWizard = async appointment => {
      // #280: closing a consultation writes clinical records. The backend
      // answers 403 without `complete.appointment`; do not offer the flow.
      if (!can.completeConsultation?.value) {
        toast.error('No tienes permisos para completar la consulta')
        return
      }
      wizardAppointment.value = appointment
      selectedAppointment.value = null
      await openForAppointment(appointment)
    }

    const onConsultationCompleted = async payload => {
      wizardAppointment.value = null
      if (payload?.quotation_generated && payload?.quotation) {
        toast.info(`Cotización ${payload.quotation.quotation_number} lista en /quotations`, 6000)
      }
      await loadAppointments()
    }
    const editingAppointment = ref(null)
    const newAppointmentPrefill = ref(null)

    const getAppointmentLocalDate = appointment => {
      if (!appointment || !appointment.scheduled_at) return null
      const d = new Date(appointment.scheduled_at)
      if (isNaN(d.getTime())) return null
      return {
        dateStr: toLocalDateString(d),
        hour: d.getHours(),
        date: d
      }
    }

    const getInitialDateForModal = (dateStr = null, hour = null) => {
      const pad = n => String(n).padStart(2, '0')
      if (dateStr && Number.isInteger(hour)) {
        return `${dateStr}T${pad(hour)}:00`
      }
      if (currentView.value === 'day') {
        const date = new Date(currentDate.value)
        date.setHours(9, 0, 0, 0)
        return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}T${pad(date.getHours())}:${pad(date.getMinutes())}`
      }
      return null
    }

    const openNewAppointmentModal = () => {
      editingAppointment.value = null
      newAppointmentPrefill.value = getInitialDateForModal()
      showNewAppointmentModal.value = true
    }

    const openSlotCreate = (dateStr, hour) => {
      editingAppointment.value = null
      newAppointmentPrefill.value = getInitialDateForModal(dateStr, hour)
      showNewAppointmentModal.value = true
    }

    const handleAppointmentSaved = async () => {
      editingAppointment.value = null
      await loadAppointments()
    }

    const dayHours = buildHours()

    const weekHours = buildHours()

    const weekDayNames = WEEK_DAY_NAMES

    const currentPeriodTitle = computed(() => {
      return periodTitle(currentDate.value, currentView.value)
    })

    // `in_progress` es el alias legacy en BD de `in_consultation` (misma
    // entrada en el registro): la leyenda muestra una píldora por estado.
    const legendStatuses = APPOINTMENT_STATUS_KEYS.filter(key => key !== 'in_consultation')

    const loadedAppointmentsCaption = computed(() => {
      const count = appointments.value.length
      if (count === 0) return 'Sin citas cargadas en el rango actual'
      return count === 1
        ? '1 cita cargada en el rango actual'
        : `${count} citas cargadas en el rango actual`
    })

    const dayDateString = computed(() => toLocalDateString(currentDate.value))

    const weekDays = computed(() => {
      return buildWeekDays(currentDate.value)
    })

    const monthDays = computed(() => {
      const now = new Date()
      return buildMonthDays(currentDate.value, now).map(cell => {
        const dayAppointments = appointments.value
          .filter(apt => getAppointmentLocalDate(apt)?.dateStr === cell.date)
          .sort((a, b) => new Date(a.scheduled_at) - new Date(b.scheduled_at))
        const { visible, overflowCount } = splitMonthCellAppointments(dayAppointments)

        return {
          ...cell,
          appointments: visible,
          overflowCount
        }
      })
    })

    const getDateRangeForCurrentView = () => {
      return dateRangeForView(currentDate.value, currentView.value)
    }

    const loadAppointments = async () => {
      loading.value = true
      try {
        const dateRange = getDateRangeForCurrentView()

        const response = await get('/api/appointments', {
          params: {
            start_date: dateRange.start_date,
            end_date: dateRange.end_date,
            per_page: 1000 // Obtener todas las citas del rango sin paginación
          }
        })

        // La API devuelve { data: [...], meta: {...} }
        // useApi ya parsea el JSON, por lo que response.data es el array directamente
        appointments.value = response?.data || []
      } catch (error) {
        toast.error('Error al cargar las citas')
        appointments.value = []
      } finally {
        loading.value = false
      }
    }

    const formatTimeRange = (start, end) => {
      const startStr = new Date(start).toLocaleTimeString('es-PE', {
        hour: '2-digit',
        minute: '2-digit'
      })
      const endStr = new Date(end).toLocaleTimeString('es-PE', {
        hour: '2-digit',
        minute: '2-digit'
      })
      return `${startStr}-${endStr}`
    }

    const appointmentTimeRange = appointment => {
      return formatTimeRange(
        appointment.scheduled_at,
        appointment.ends_at || appointment.scheduled_at
      )
    }

    // T8b defect 3 — chips render the compact `HH:mm - HH:mm` (all three
    // views) with a `title` carrying the full patient + locale range; the
    // details modal keeps the full locale format through
    // `appointmentTimeRange` above.
    const chipTimeRange = appointment =>
      formatCompactTimeRange(
        appointment.scheduled_at,
        appointment.ends_at || appointment.scheduled_at
      )

    const chipTitle = appointment => {
      const name =
        `${appointment.patient?.first_name || ''} ${appointment.patient?.last_name || ''}`.trim()
      return `${name || 'Cita'} · ${appointmentTimeRange(appointment)}`
    }

    const appointmentMetaLine = appointment => {
      const parts = []
      if (appointment?.user?.name) parts.push(appointment.user.name)
      if (appointment?.dental_chair?.name) parts.push(`Sillón ${appointment.dental_chair.name}`)
      return parts.join(' · ')
    }

    const getAppointmentLabel = apt => {
      const type = apt?.appointment_type?.name || 'Cita'
      return `${type} ${appointmentTimeRange(apt)}`
    }

    const changeView = view => {
      currentView.value = view
      loadAppointments()
    }

    // D8 month drill-down: "N más" (and the day-number header) jump straight
    // to the already-redesigned Day view for that date.
    const drillDownToDate = dateStr => {
      const [year, month, day] = dateStr.split('-').map(Number)
      currentDate.value = new Date(year, month - 1, day)
      changeView('day')
    }

    const monthCellClasses = day => {
      if (day.isToday) return 'bg-accent-50'
      if (!day.isCurrentMonth) return 'bg-theme-surface'
      return ''
    }

    const monthDayNumberClasses = day => {
      if (day.isToday) return 'bg-accent-500 text-white font-semibold'
      if (!day.isCurrentMonth) return 'text-theme-secondary hover:bg-theme-surface'
      return 'text-theme-primary hover:bg-theme-surface'
    }

    const previousPeriod = () => {
      currentDate.value = addPeriod(currentDate.value, currentView.value, -1)
      loadAppointments()
    }

    const nextPeriod = () => {
      currentDate.value = addPeriod(currentDate.value, currentView.value, 1)
      loadAppointments()
    }

    const goToToday = () => {
      currentDate.value = new Date()
      loadAppointments()
    }

    const appointmentsForSlot = (dateStr, hour) => {
      return appointments.value
        .filter(apt => {
          const aptInfo = getAppointmentLocalDate(apt)
          if (!aptInfo) return false
          return aptInfo.dateStr === dateStr && aptInfo.hour === hour
        })
        .sort((a, b) => {
          try {
            return new Date(a.scheduled_at) - new Date(b.scheduled_at)
          } catch (e) {
            return 0
          }
        })
    }

    const getAppointmentClasses = appointment => {
      return `${APPOINTMENT_CHIP_BASE_CLASSES} ${statusChipClasses(appointment.status)} ${dragChipClasses(appointment)}`.trim()
    }

    const getStatusClasses = status => {
      return statusToneClasses(status)
    }

    const getStatusText = status => {
      return statusLabel(status)
    }

    const getStatusBadgeProps = status => {
      return statusBadgeProps(status)
    }

    const selectAppointment = appointment => {
      if (suppressNextClick) {
        suppressNextClick = false
        return
      }
      selectedAppointment.value = appointment
    }

    // T7: UiModal owns the details chrome (backdrop, Escape, focus trap);
    // every close path funnels through clearing the selection.
    const closeDetails = () => {
      selectedAppointment.value = null
    }

    // T7 D1/D9 — quiet empty-state copy per view (one short title + one
    // precise sentence), rendered instead of the grids when the loaded range
    // has zero appointments.
    const emptyStateCopy = computed(() => {
      const copy = {
        day: {
          title: 'Sin citas este día',
          description: 'La agenda de este día está libre. Crea una cita para empezar.'
        },
        week: {
          title: 'Sin citas esta semana',
          description: 'Ninguna cita en la semana cargada. Crea una cita para empezar.'
        },
        month: {
          title: 'Sin citas este mes',
          description: 'Ninguna cita en el mes cargado. Crea una cita para empezar.'
        }
      }
      return copy[currentView.value]
    })

    // T5 drag-and-drop reschedule: press + DRAG_THRESHOLD_PX of travel on a
    // Day/Week chip starts a drag; below the threshold the native click still
    // opens the details modal. Slot buttons are never drag sources.
    const DRAG_CHIP_MOTION_CLASSES =
      'cursor-grabbing opacity-70 -translate-y-0.5 transition-[transform,opacity] duration-normal'
    const DRAG_CHIP_REDUCED_MOTION_CLASSES =
      'cursor-grabbing opacity-70 ring-2 ring-inset ring-accent-500'
    const DRAG_CHIP_IDLE_CLASSES = 'cursor-grab'
    const DROP_TARGET_HOVER_CLASSES = 'bg-accent-50 ring-2 ring-inset ring-accent-500'

    const draggingAppointmentId = ref(null)
    const dropHoverSlot = ref(null)
    let dragSession = null
    let suppressNextClick = false

    const dragChipClasses = appointment => {
      if (currentView.value === 'month' || !can.editAppointment?.value) return ''
      if (draggingAppointmentId.value === appointment.id) {
        return reducedMotion.value ? DRAG_CHIP_REDUCED_MOTION_CLASSES : DRAG_CHIP_MOTION_CLASSES
      }
      return DRAG_CHIP_IDLE_CLASSES
    }

    const dropTargetClasses = slotKey =>
      dropHoverSlot.value === slotKey ? DROP_TARGET_HOVER_CLASSES : ''

    const collectDropTargets = () =>
      Array.from(document.querySelectorAll('[data-calendar-drop-target]')).map(element => ({
        key: element.getAttribute('data-calendar-drop-target'),
        rect: element.getBoundingClientRect()
      }))

    const attachDragListeners = () => {
      window.addEventListener('pointermove', onDragPointerMove)
      window.addEventListener('pointerup', onDragPointerUp)
      window.addEventListener('pointercancel', onDragPointerCancel)
      window.addEventListener('keydown', onDragKeyDown, true)
    }

    const detachDragListeners = () => {
      window.removeEventListener('pointermove', onDragPointerMove)
      window.removeEventListener('pointerup', onDragPointerUp)
      window.removeEventListener('pointercancel', onDragPointerCancel)
      window.removeEventListener('keydown', onDragKeyDown, true)
    }

    const onChipPointerDown = (event, appointment) => {
      if (!can.editAppointment?.value) return
      if (typeof event.button === 'number' && event.button !== 0) return
      suppressNextClick = false
      dragSession = {
        appointment,
        startX: event.clientX,
        startY: event.clientY,
        active: false,
        dropTargets: []
      }
      attachDragListeners()
    }

    const startDrag = session => {
      session.active = true
      session.dropTargets = collectDropTargets()
      draggingAppointmentId.value = session.appointment.id
      document.body.style.userSelect = 'none'
    }

    const endDrag = ({ suppressClick = false } = {}) => {
      const session = dragSession
      dragSession = null
      detachDragListeners()
      draggingAppointmentId.value = null
      dropHoverSlot.value = null
      document.body.style.userSelect = ''
      if (suppressClick) {
        suppressNextClick = true
        setTimeout(() => {
          suppressNextClick = false
        }, 0)
      }
      return session
    }

    const onDragPointerMove = event => {
      const session = dragSession
      if (!session) return
      if (!session.active) {
        const moved = Math.hypot(event.clientX - session.startX, event.clientY - session.startY)
        if (moved < DRAG_THRESHOLD_PX) return
        startDrag(session)
      }
      if (event.cancelable) event.preventDefault()
      dropHoverSlot.value = resolveDropSlot(session.dropTargets, event.clientX, event.clientY)
    }

    const onDragPointerUp = event => {
      const session = dragSession
      if (!session) return
      if (!session.active) {
        endDrag()
        return
      }
      const slotKey = resolveDropSlot(session.dropTargets, event.clientX, event.clientY)
      endDrag({ suppressClick: true })
      if (!slotKey) return
      // Own-slot drop is a no-op and stays silent (T5 contract: no request,
      // no feedback).
      const current = getAppointmentLocalDate(session.appointment)
      if (current && slotKey === `${current.dateStr}-${current.hour}`) return
      if (!canDropOn(slotKey, session.appointment)) {
        // T8b investigation: drops onto past slots are rejected CLIENT-side
        // by `canDropOn` before any request — in the real-browser pass that
        // read as a silent cancel (armed drag, no PATCH, no toast). Announce
        // the rule as a quiet info toast; other rejections (defensive
        // unresolvable slots) keep the silent rollback.
        const target = slotKeyToScheduledAt(slotKey)
        const targetTime = target ? new Date(target).getTime() : NaN
        if (Number.isFinite(targetTime) && targetTime < Date.now() + 60 * 1000) {
          toast.info('No se puede mover a un horario pasado')
        }
        return
      }
      rescheduleAppointment(session.appointment, slotKey)
    }

    const onDragPointerCancel = () => {
      const session = dragSession
      if (!session) return
      endDrag({ suppressClick: session.active })
    }

    const onDragKeyDown = event => {
      if (event.key !== 'Escape') return
      const session = dragSession
      if (!session) return
      endDrag({ suppressClick: session.active })
    }

    const conflictToastMessage = conflict => {
      const other = conflict.conflicting_appointment
      const blocker =
        conflict.type === 'chair_conflict'
          ? `El sillón ${other?.dental_chair?.name || 'asignado'} ya está ocupado`
          : 'El profesional ya tiene una cita en ese horario'
      const detail =
        other?.patient?.full_name && other?.scheduled_at
          ? `${other.patient.full_name} (${formatTimeRange(other.scheduled_at, other.ends_at || other.scheduled_at)})`
          : other?.patient?.full_name || ''
      return detail ? `${blocker}. Conflicto con ${detail}.` : `${blocker}.`
    }

    const rescheduleAppointment = async (appointment, slotKey) => {
      const scheduledAt = slotKeyToScheduledAt(slotKey)
      if (!scheduledAt) return
      const durationMinutes = Number(appointment.duration_minutes) || 60
      const original = {
        scheduled_at: appointment.scheduled_at,
        ends_at: appointment.ends_at
      }
      appointment.scheduled_at = scheduledAt
      appointment.ends_at = addMinutesToLocal(scheduledAt, durationMinutes)
      try {
        await patch(`/api/appointments/${appointment.id}/reschedule`, {
          scheduled_at: scheduledAt,
          duration_minutes: durationMinutes
        })
        toast.success('Cita reprogramada')
      } catch (error) {
        appointment.scheduled_at = original.scheduled_at
        appointment.ends_at = original.ends_at
        const conflict = error?.response?.data?.conflict
        if (conflict) {
          toast.error(conflictToastMessage(conflict))
        } else {
          toast.error(normalizeError(error, 'No se pudo reprogramar la cita'))
        }
      }
    }

    const editAppointment = appointment => {
      editingAppointment.value = appointment
      newAppointmentPrefill.value = null
      showNewAppointmentModal.value = true
      selectedAppointment.value = null
    }

    const changeStatus = async (appointment, newStatus) => {
      try {
        // Slice 01 / T-01.5 (API-018): use the dedicated PATCH route for
        // status updates instead of PUT to /appointments/{id} (which only
        // persists `notes` on completed appointments and silently ignores
        // `status`).
        const { patch } = useApi()
        await patch(`/api/appointments/${appointment.id}/status`, { status: newStatus })
        toast.success('Estado actualizado')
        await loadAppointments()
        selectedAppointment.value = null
      } catch (error) {
        const errorMessage = error?.response?.data?.message || 'Error al cambiar el estado'
        toast.error(errorMessage)
      }
    }

    const deleteAppointment = async appointment => {
      const ok = await confirm({
        title: 'Eliminar cita',
        message: '¿Estás seguro de que quieres eliminar esta cita?',
        confirmText: 'Eliminar',
        variant: 'danger'
      })
      if (ok) {
        try {
          await del(`/api/appointments/${appointment.id}`)
          toast.success('Cita eliminada exitosamente')
          await loadAppointments()
          selectedAppointment.value = null
        } catch (error) {
          const errorMessage = error?.response?.data?.message || 'Error al eliminar la cita'
          toast.error(errorMessage)
        }
      }
    }

    const formatHour = hour => {
      return `${hour.toString().padStart(2, '0')}:00`
    }

    const formatDateTime = dateTime => {
      return new Date(dateTime).toLocaleString('es-ES', {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
      })
    }

    const goBack = () => {
      router.back()
    }

    // Método para manejar evento de cita creada
    // WebSocket subscriptions
    let appointmentsChannel = null

    watch(showNewAppointmentModal, isOpen => {
      if (!isOpen) {
        editingAppointment.value = null
      }
    })

    onMounted(() => {
      loadAppointments()

      // Suscribirse a canales WebSocket para actualizaciones en tiempo real
      try {
        appointmentsChannel = privateChannel('appointments')
        if (appointmentsChannel) {
          appointmentsChannel
            .listen('.appointment.created', async () => {
              await loadAppointments()
              toast.success('Nueva cita agregada')
            })
            .listen('.appointment.updated', async () => {
              await loadAppointments()
              toast.success('Cita actualizada')
            })
            .listen('.appointment.deleted', async e => {
              // Remover la cita de la lista
              appointments.value = appointments.value.filter(apt => apt.id !== e.appointment_id)
              if (selectedAppointment.value?.id === e.appointment_id) {
                selectedAppointment.value = null
              }
              toast.success('Cita eliminada')
            })
        }
      } catch (error) {
        // WebSocket no disponible, se sigue con polling manual
      }
    })

    onUnmounted(() => {
      // Drag en curso: no dejar listeners de ventana huérfanos al desmontar.
      if (dragSession) {
        endDrag()
      } else {
        detachDragListeners()
      }

      // Limpiar suscripciones WebSocket
      if (echo) {
        try {
          echo.leave('private-appointments')
        } catch (e) {
          // Error al limpiar suscripción, se ignora en desmontaje
        }
      }
    })

    return {
      can,
      loading,
      currentView,
      currentDate,
      appointments,
      selectedAppointment,
      showNewAppointmentModal,
      wizardAppointment,
      editingAppointment,
      openNewAppointmentModal,
      startConsultation,
      openConsultationWizard,
      onConsultationCompleted,
      dayHours,
      weekHours,
      weekDayNames,
      currentPeriodTitle,
      legendStatuses,
      loadedAppointmentsCaption,
      statusLabel,
      statusBadgeVariant,
      weekDays,
      monthDays,
      monthCellClasses,
      monthDayNumberClasses,
      drillDownToDate,
      loadAppointments,
      changeView,
      previousPeriod,
      nextPeriod,
      goToToday,
      appointmentsForSlot,
      dayDateString,
      appointmentTimeRange,
      chipTimeRange,
      chipTitle,
      appointmentMetaLine,
      openSlotCreate,
      newAppointmentPrefill,
      getAppointmentClasses,
      getStatusClasses,
      getStatusText,
      getStatusBadgeProps,
      selectAppointment,
      closeDetails,
      emptyStateCopy,
      draggingAppointmentId,
      dropHoverSlot,
      dropTargetClasses,
      onChipPointerDown,
      editAppointment,
      changeStatus,
      deleteAppointment,
      getAppointmentLabel,
      formatHour,
      formatDateTime,
      goBack,
      getInitialDateForModal,
      handleAppointmentSaved,
      toLocalDateString,
      getAppointmentLocalDate
    }
  }
}
</script>
