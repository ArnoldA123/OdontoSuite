<template>
  <AppLayout>
    <!-- Header Section -->
    <PageHeader title="Agenda" subtitle="Gestiona las citas y horarios" class="mb-6 bg-canvas">
      <template #actions>
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
        <UiButton variant="secondary" @click="goBack">
          <template #icon-left>
            <svg class="w-4 h-4" fill="none" stroke="currentColor"
viewBox="0 0 24 24">
              <path
                stroke-linecap="round"
                stroke-linejoin="round"
                stroke-width="2"
                d="M10 19l-7-7m0 0l7-7m-7 7h18"
              />
            </svg>
          </template>
          Volver
        </UiButton>
        <UiButton v-if="can.createAppointment?.value" @click="openNewAppointmentModal">
          <template #icon-left>
            <svg class="w-4 h-4" fill="none" stroke="currentColor"
viewBox="0 0 24 24">
              <path
                stroke-linecap="round"
                stroke-linejoin="round"
                stroke-width="2"
                d="M12 6v6m0 0v6m0-6h6m-6 0H6"
              />
            </svg>
          </template>
          Nueva Cita
        </UiButton>
      </template>
    </PageHeader>

    <!-- View Controls -->
    <UiCard variant="glass" class="mb-6">
      <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div class="flex items-center gap-2">
          <UiButton
            :variant="currentView === 'day' ? 'primary' : 'ghost'"
            size="sm"
            @click="changeView('day')"
          >
            Día
          </UiButton>
          <UiButton
            :variant="currentView === 'week' ? 'primary' : 'ghost'"
            size="sm"
            @click="changeView('week')"
          >
            Semana
          </UiButton>
          <UiButton
            :variant="currentView === 'month' ? 'primary' : 'ghost'"
            size="sm"
            @click="changeView('month')"
          >
            Mes
          </UiButton>
        </div>
        <div class="flex items-center gap-3">
          <UiButton variant="ghost" size="sm" class="p-2" @click="previousPeriod">
            <svg class="w-4 h-4" fill="none" stroke="currentColor"
viewBox="0 0 24 24">
              <path
                stroke-linecap="round"
                stroke-linejoin="round"
                stroke-width="2"
                d="M15 19l-7-7 7-7"
              />
            </svg>
          </UiButton>
          <UiButton
variant="ghost"
size="sm" class="px-4" @click="goToToday">Hoy</UiButton>
          <UiButton variant="ghost" size="sm" class="p-2" @click="nextPeriod">
            <svg class="w-4 h-4" fill="none" stroke="currentColor"
viewBox="0 0 24 24">
              <path
                stroke-linecap="round"
                stroke-linejoin="round"
                stroke-width="2"
                d="M9 5l7 7-7 7"
              />
            </svg>
          </UiButton>
        </div>
      </div>
    </UiCard>

    <!-- Calendar Header -->
    <UiCard variant="glass" class="mb-6">
      <div class="text-center">
        <h2 class="text-2xl font-bold text-theme-primary mb-2">
          {{ currentPeriodTitle }}
        </h2>
        <div class="flex flex-wrap items-center justify-center gap-4 text-sm">
          <UiStatusBadge variant="info" label="Programada" show-dot
data-status="scheduled" />
          <UiStatusBadge variant="success" label="Confirmada" show-dot
data-status="confirmed" />
          <UiStatusBadge variant="warning" label="En Consulta" show-dot
data-status="in_progress" />
          <UiStatusBadge variant="neutral" label="Completada" show-dot
data-status="completed" />
          <UiStatusBadge variant="error" label="Cancelada" show-dot
data-status="cancelled" />
          <UiStatusBadge variant="neutral" label="No se presentó" show-dot
data-status="no_show" />
          <UiStatusBadge variant="warning" label="Reprogramada" data-status="rescheduled" />
        </div>
      </div>
    </UiCard>

    <!-- Calendar Content -->
    <UiCard variant="glass" class="overflow-hidden">
      <!-- Loading State -->
      <div v-if="loading" class="p-12 text-center">
        <LoadingSpinner size="lg" text="Cargando agenda..." />
        <p class="mt-4 text-theme-secondary">Cargando agenda...</p>
      </div>

      <!-- Day View -->
      <div v-else-if="currentView === 'day'" class="p-6">
        <div class="space-y-4">
          <div v-for="hour in dayHours" :key="hour" class="flex items-start gap-4">
            <div class="w-16 text-sm text-theme-secondary font-medium pt-2">
              {{ formatHour(hour) }}
            </div>
            <div class="flex-1 min-h-[60px] border-l-2 border-hairline pl-4">
              <div
                v-for="appointment in getAppointmentsForHour(hour)"
                :key="appointment.id"
                class="mb-3 p-4 rounded-xl border-l-4 shadow-sm transition-all duration-200 cursor-pointer"
                :class="getAppointmentClasses(appointment)"
                @click="selectAppointment(appointment)"
              >
                <div class="space-y-2">
                  <div class="flex items-center justify-between">
                    <div class="font-semibold text-base">
                      {{ appointment.appointment_type?.name || 'Cita' }}
                    </div>
                    <UiStatusBadge
                      size="sm"
                      :variant="getStatusBadgeProps(appointment.status).variant"
                      :label="getStatusText(appointment.status)"
                      :show-dot="getStatusBadgeProps(appointment.status).showDot"
                      :data-status="appointment.status"
                    />
                  </div>
                  <div class="text-sm text-theme-primary">
                    <span class="font-medium">{{ formatTime(appointment.scheduled_at) }}</span>
                    <span v-if="appointment.ends_at" class="text-theme-secondary">
                      - {{ formatTime(appointment.ends_at) }}
                    </span>
                  </div>
                  <div class="grid grid-cols-2 gap-2 text-xs text-theme-secondary">
                    <div>
                      <span class="font-medium">Paciente:</span>
                      {{ appointment.patient?.first_name }} {{ appointment.patient?.last_name }}
                    </div>
                    <div>
                      <span class="font-medium">Profesional:</span>
                      {{ appointment.user?.name }}
                    </div>
                    <div v-if="appointment.dental_chair">
                      <span class="font-medium">Sillón:</span>
                      {{ appointment.dental_chair?.name }}
                    </div>
                    <div v-if="appointment.duration_minutes">
                      <span class="font-medium">Duración:</span>
                      {{ appointment.duration_minutes }} min
                    </div>
                  </div>
                  <div v-if="appointment.notes" class="text-xs text-theme-secondary italic">
                    {{ appointment.notes }}
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Week View -->
      <div v-else-if="currentView === 'week'" class="p-6">
        <div
          class="grid grid-cols-8 gap-1 border border-hairline rounded-lg overflow-hidden tabular-nums"
        >
          <!-- Time column header -->
          <div class="p-2 border-b border-hairline bg-theme-surface" />

          <!-- Day headers -->
          <div
            v-for="day in weekDays"
            :key="day.date"
            class="p-2 border-b border-hairline bg-theme-surface text-center"
          >
            <div class="text-sm font-medium text-theme-primary">
              {{ day.name }}
            </div>
            <div class="text-xs text-theme-secondary">
              {{ day.number }}
            </div>
          </div>

          <!-- Time column -->
          <div class="border-r border-hairline">
            <div
              v-for="hour in weekHours"
              :key="hour"
              class="h-16 border-b border-theme-light flex items-start justify-end pr-2 pt-1"
            >
              <span class="text-xs text-theme-secondary font-medium">{{ formatHour(hour) }}</span>
            </div>
          </div>

          <!-- Day columns -->
          <div
            v-for="day in weekDays"
            :key="day.date"
            class="border-r border-hairline last:border-r-0"
          >
            <div
              v-for="hour in weekHours"
              :key="`${day.date}-${hour}`"
              class="h-16 border-b border-theme-light relative"
            >
              <div
                v-for="appointment in getAppointmentsForDayAndHour(day.date, hour)"
                :key="appointment.id"
                class="absolute inset-1 p-1.5 rounded-lg text-xs cursor-pointer transition-all duration-200 z-10"
                :class="getAppointmentClasses(appointment)"
                @click="selectAppointment(appointment)"
              >
                <div class="font-medium truncate">
                  {{ appointment.appointment_type?.name || 'Cita' }}
                </div>
                <div class="text-xs opacity-75 truncate">
                  {{ formatTime(appointment.scheduled_at) }}
                </div>
                <div class="text-xs opacity-75 truncate">
                  {{ appointment.patient?.first_name }} {{ appointment.patient?.last_name }}
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Month View -->
      <div v-else-if="currentView === 'month'" class="p-6">
        <div class="grid grid-cols-7 gap-1 tabular-nums">
          <!-- Day headers -->
          <div
            v-for="day in weekDayNames"
            :key="day"
            class="p-3 text-center text-sm font-medium text-theme-secondary border-b border-hairline"
          >
            {{ day }}
          </div>

          <!-- Calendar days -->
          <div
            v-for="day in monthDays"
            :key="day.date"
            class="min-h-[120px] border border-hairline p-2 hover:bg-theme-surface transition-colors"
            :class="{
              'bg-theme-surface': !day.isCurrentMonth,
              'bg-systemBlue-50 border-systemBlue-200': day.isToday
            }"
          >
            <div class="flex items-center justify-between mb-2">
              <span
                class="text-sm font-medium"
                :class="{
                  'text-theme-secondary': !day.isCurrentMonth,
                  'text-systemBlue-600': day.isToday,
                  'text-theme-primary': day.isCurrentMonth && !day.isToday
                }"
              >
                {{ day.number }}
              </span>
              <UiStatusBadge
                v-if="day.appointmentCount > 0"
                variant="info"
                size="sm"
                :label="String(day.appointmentCount)"
                :data-status="`count-${day.date}`"
              />
            </div>

            <div class="space-y-1">
              <div
                v-for="appointment in day.appointments"
                :key="appointment.id"
                class="p-1.5 rounded text-xs cursor-pointer hover:shadow-sm transition-all duration-200"
                :class="getAppointmentClasses(appointment)"
                @click="selectAppointment(appointment)"
              >
                <div class="font-medium truncate">
                  {{ appointment.appointment_type?.name || 'Cita' }}
                </div>
                <div class="text-xs opacity-75 truncate">
                  {{ formatTime(appointment.scheduled_at) }}
                </div>
                <div class="text-xs opacity-75 truncate">
                  {{ appointment.patient?.first_name }} {{ appointment.patient?.last_name }}
                </div>
              </div>
              <div
                v-if="day.hasMore"
                class="text-xs text-systemBlue-600 font-medium text-center mt-1"
              >
                +{{ day.appointmentCount - 3 }} más
              </div>
              <div
                v-if="day.appointments.length === 0"
                class="text-xs text-theme-secondary text-center py-2"
              >
                Sin citas
              </div>
            </div>
          </div>
        </div>
      </div>
    </UiCard>

    <!-- Appointment Details Modal -->
    <div
      v-if="selectedAppointment"
      class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center p-4 z-50"
      @click.self="selectedAppointment = null"
    >
      <div
        class="bg-theme-surface-elevated rounded-2xl shadow-2xl max-w-2xl w-full max-h-[90vh] overflow-y-auto"
      >
        <div class="p-6 border-b border-hairline">
          <div class="flex items-center justify-between">
            <h2 class="text-xl font-semibold text-theme-primary">Detalles de la Cita</h2>
            <button
              class="text-theme-secondary hover:text-theme-primary transition-colors"
              @click="selectedAppointment = null"
            >
              <svg class="w-6 h-6" fill="none" stroke="currentColor"
viewBox="0 0 24 24">
                <path
                  stroke-linecap="round"
                  stroke-linejoin="round"
                  stroke-width="2"
                  d="M6 18L18 6M6 6l12 12"
                />
              </svg>
            </button>
          </div>
        </div>
        <div class="p-6 space-y-4">
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
              <label class="text-sm font-medium text-theme-secondary">Paciente</label>
              <p class="text-theme-primary">
                {{ selectedAppointment.patient.first_name }}
                {{ selectedAppointment.patient.last_name }}
              </p>
            </div>
            <div>
              <label class="text-sm font-medium text-theme-secondary">Profesional</label>
              <p class="text-theme-primary">
                {{ selectedAppointment.user.name }}
              </p>
            </div>
          </div>
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
              <label class="text-sm font-medium text-theme-secondary">Fecha y Hora</label>
              <p class="text-theme-primary">
                {{ formatDateTime(selectedAppointment.scheduled_at) }}
              </p>
            </div>
            <div>
              <label class="text-sm font-medium text-theme-secondary">Duración</label>
              <p class="text-theme-primary">{{ selectedAppointment.duration_minutes }} minutos</p>
            </div>
          </div>
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
              <label class="text-sm font-medium text-theme-secondary">Tipo de Cita</label>
              <p class="text-theme-primary">
                {{ selectedAppointment.appointment_type.name }}
              </p>
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
              @click="changeStatus(selectedAppointment, 'confirmed')"
            >
              Confirmar
            </UiButton>
            <UiButton
              v-if="
                selectedAppointment.status === 'confirmed' ||
                selectedAppointment.status === 'scheduled'
              "
              size="sm"
              variant="secondary"
              @click="startConsultation(selectedAppointment)"
            >
              Iniciar Consulta
            </UiButton>
            <UiButton
              v-if="selectedAppointment.status === 'in_consultation'"
              size="sm"
              variant="primary"
              @click="openConsultationWizard(selectedAppointment)"
            >
              Completar Consulta
            </UiButton>
            <UiButton
              v-if="!['cancelled', 'completed'].includes(selectedAppointment.status)"
              size="sm"
              variant="danger"
              @click="changeStatus(selectedAppointment, 'cancelled')"
            >
              Cancelar Cita
            </UiButton>
          </div>
          <div class="flex justify-end gap-3 pt-4">
            <UiButton
              v-if="can.updateAppointment?.value"
              variant="secondary"
              @click="editAppointment(selectedAppointment)"
            >
              Editar
            </UiButton>
            <UiButton
              v-if="can.deleteAppointment?.value"
              variant="danger"
              @click="deleteAppointment(selectedAppointment)"
            >
              Eliminar
            </UiButton>
          </div>
        </div>
      </div>
    </div>

    <!-- New Appointment Modal -->
    <NewAppointmentModal
      v-model="showNewAppointmentModal"
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
import AppLayout from '../../components/layout/AppLayout.vue'
import UiCard from '../../components/ui/Card.vue'
import UiButton from '../../components/ui/Button.vue'
import UiStatusBadge from '../../components/ui/StatusBadge.vue'
import NewAppointmentModal from '../../components/appointments/NewAppointmentModal.vue'
import ConsultationWizard from './ConsultationWizard.vue'
import { useConsultation } from '../../composables/useConsultation'
import { useConfirm } from '../../composables/useConfirm'
import {
  APPOINTMENT_CHIP_BASE_CLASSES,
  statusLabel,
  statusBadgeProps,
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
  addPeriod
} from './calendarDateMath'

export default {
  name: 'CalendarPage',
  components: {
    AppLayout,
    UiCard,
    UiButton,
    UiStatusBadge,
    NewAppointmentModal,
    ConsultationWizard
  },
  setup() {
    const router = useRouter()
    const { get, del } = useApi()
    const { can } = usePermissions()
    const toast = useToast()
    const { channel, echo } = useEcho()
    const { confirm } = useConfirm()

    // can ya es un objeto con propiedades computed, usarlo directamente

    const loading = ref(false)
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

    const getInitialDateForModal = () => {
      if (currentView.value === 'day') {
        const date = new Date(currentDate.value)
        date.setHours(9, 0, 0, 0)
        const pad = n => String(n).padStart(2, '0')
        return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}T${pad(date.getHours())}:${pad(date.getMinutes())}`
      }
      return null
    }

    const openNewAppointmentModal = () => {
      editingAppointment.value = null
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

    const weekDays = computed(() => {
      return buildWeekDays(currentDate.value)
    })

    const monthDays = computed(() => {
      const now = new Date()
      return buildMonthDays(currentDate.value, now).map(cell => {
        const allDayAppointments = appointments.value.filter(apt => {
          const aptInfo = getAppointmentLocalDate(apt)
          if (!aptInfo) return false
          return aptInfo.dateStr === cell.date
        })

        const sortedDayAppointments = allDayAppointments.sort((a, b) => {
          try {
            return new Date(a.scheduled_at) - new Date(b.scheduled_at)
          } catch (e) {
            return 0
          }
        })

        return {
          ...cell,
          appointments: sortedDayAppointments.slice(0, 3),
          appointmentCount: allDayAppointments.length,
          hasMore: allDayAppointments.length > 3
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

    const getAppointmentLabel = apt => {
      const type = apt?.appointment_type?.name || 'Cita'
      const range = formatTimeRange(apt.scheduled_at, apt.ends_at || apt.scheduled_at)
      return `${type} ${range}`
    }

    const changeView = view => {
      currentView.value = view
      loadAppointments()
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

    const getAppointmentsForHour = hour => {
      const dateStr = toLocalDateString(currentDate.value)
      const filtered = appointments.value
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
      return filtered
    }

    const getAppointmentsForDayAndHour = (dateStr, hour) => {
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
      return `${APPOINTMENT_CHIP_BASE_CLASSES} ${statusChipClasses(appointment.status)}`
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
      selectedAppointment.value = appointment
    }

    const editAppointment = appointment => {
      editingAppointment.value = appointment
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

    const formatTime = dateTime => {
      return new Date(dateTime).toLocaleTimeString('es-ES', {
        hour: '2-digit',
        minute: '2-digit'
      })
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
        appointmentsChannel = channel('appointments')
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
      // Limpiar suscripciones WebSocket
      if (echo) {
        try {
          echo.leave('appointments')
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
      weekDays,
      monthDays,
      loadAppointments,
      changeView,
      previousPeriod,
      nextPeriod,
      goToToday,
      getAppointmentsForHour,
      getAppointmentsForDayAndHour,
      getAppointmentClasses,
      getStatusClasses,
      getStatusText,
      getStatusBadgeProps,
      selectAppointment,
      editAppointment,
      changeStatus,
      deleteAppointment,
      getAppointmentLabel,
      formatHour,
      formatTime,
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
