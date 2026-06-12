<template>
  <AppLayout>
    <!-- Header Section -->
    <div class="mb-8 animate-fade-in">
      <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
          <h1 class="text-3xl font-bold text-theme-primary mb-2">Agenda</h1>
          <p class="text-theme-secondary">Gestiona las citas y horarios</p>
        </div>
        <div class="flex flex-col sm:flex-row gap-3">
          <UiButton
            variant="secondary"
            @click="goBack"
            class="flex items-center gap-2"
          >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
            </svg>
            Volver
          </UiButton>
          <UiButton
            v-if="can.createAppointment?.value"
            @click="openNewAppointmentModal"
            class="flex items-center gap-2"
          >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
            </svg>
            Nueva Cita
          </UiButton>
        </div>
      </div>
    </div>

    <!-- View Controls -->
    <UiCard variant="glass" class="mb-6">
      <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div class="flex items-center gap-2">
          <UiButton
            @click="changeView('day')"
            :variant="currentView === 'day' ? 'primary' : 'ghost'"
            size="sm"
          >
            Día
          </UiButton>
          <UiButton
            @click="changeView('week')"
            :variant="currentView === 'week' ? 'primary' : 'ghost'"
            size="sm"
          >
                Semana
          </UiButton>
          <UiButton
                @click="changeView('month')"
            :variant="currentView === 'month' ? 'primary' : 'ghost'"
            size="sm"
          >
            Mes
          </UiButton>
            </div>
        <div class="flex items-center gap-3">
          <UiButton
            variant="ghost"
            size="sm"
                  @click="previousPeriod"
            class="p-2"
                >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                  </svg>
          </UiButton>
          <UiButton
            variant="ghost"
            size="sm"
            @click="goToToday"
            class="px-4"
          >
            Hoy
          </UiButton>
          <UiButton
            variant="ghost"
            size="sm"
                  @click="nextPeriod"
            class="p-2"
                >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                  </svg>
          </UiButton>
              </div>
            </div>
    </UiCard>

    <!-- Calendar Header -->
    <UiCard variant="glass" class="mb-6">
      <div class="text-center">
        <h2 class="text-2xl font-bold text-theme-primary mb-2">{{ currentPeriodTitle }}</h2>
        <div class="flex items-center justify-center gap-4 text-sm text-theme-secondary">
          <div class="flex items-center gap-2">
            <div class="w-3 h-3 rounded-full bg-accent"></div>
            <span>Programada</span>
          </div>
          <div class="flex items-center gap-2">
            <div class="w-3 h-3 rounded-full bg-green-500"></div>
            <span>Confirmada</span>
        </div>
          <div class="flex items-center gap-2">
            <div class="w-3 h-3 rounded-full bg-yellow-500"></div>
            <span>En Consulta</span>
          </div>
          <div class="flex items-center gap-2">
            <div class="w-3 h-3 rounded-full bg-theme-secondary"></div>
            <span>Completada</span>
          </div>
          <div class="flex items-center gap-2">
            <div class="w-3 h-3 rounded-full bg-red-500"></div>
            <span>Cancelada</span>
          </div>
        </div>
      </div>
    </UiCard>

    <!-- Calendar Content -->
    <UiCard variant="glass" class="overflow-hidden">
            <!-- Loading State -->
      <div v-if="loading" class="p-12 text-center">
        <div class="inline-block animate-spin rounded-full h-12 w-12 border-4 border-purple-200 border-t-purple-600"></div>
        <p class="mt-4 text-theme-secondary">Cargando agenda...</p>
            </div>

              <!-- Day View -->
      <div v-else-if="currentView === 'day'" class="p-6">
        <div class="space-y-4">
          <div
            v-for="hour in dayHours"
            :key="hour"
            class="flex items-start gap-4"
          >
            <div class="w-16 text-sm text-theme-secondary font-medium pt-2">
              {{ formatHour(hour) }}
                </div>
            <div class="flex-1 min-h-[60px] border-l-2 border-theme pl-4">
              <div
                v-for="appointment in getAppointmentsForHour(hour)"
                :key="appointment.id"
                class="mb-3 p-4 rounded-xl border-l-4 shadow-sm hover:shadow-md transition-all duration-200 cursor-pointer"
                :class="getAppointmentClasses(appointment)"
                @click="selectAppointment(appointment)"
              >
                <div class="space-y-2">
                  <div class="flex items-center justify-between">
                    <div class="font-semibold text-base">
                      {{ appointment.appointment_type?.name || 'Cita' }}
                    </div>
                    <div class="text-xs px-2 py-1 rounded-full" :class="getStatusClasses(appointment.status)">
                      {{ getStatusText(appointment.status) }}
                    </div>
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
        <div class="grid grid-cols-8 gap-1 border border-theme rounded-lg overflow-hidden">
          <!-- Time column header -->
          <div class="p-2 border-b border-theme bg-theme-surface"></div>
          
          <!-- Day headers -->
          <div
            v-for="day in weekDays"
            :key="day.date"
            class="p-2 border-b border-theme bg-theme-surface text-center"
          >
            <div class="text-sm font-medium text-theme-primary">{{ day.name }}</div>
            <div class="text-xs text-theme-secondary">{{ day.number }}</div>
          </div>

          <!-- Time column -->
          <div class="border-r border-theme">
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
            class="border-r border-theme last:border-r-0"
          >
            <div
              v-for="hour in weekHours"
              :key="`${day.date}-${hour}`"
              class="h-16 border-b border-theme-light relative"
            >
              <div
                v-for="appointment in getAppointmentsForDayAndHour(day.date, hour)"
                :key="appointment.id"
                class="absolute inset-1 p-1.5 rounded-lg text-xs cursor-pointer hover:shadow-md transition-all duration-200 z-10"
                :class="getAppointmentClasses(appointment)"
                @click="selectAppointment(appointment)"
              >
                <div class="font-medium truncate">{{ appointment.appointment_type?.name || 'Cita' }}</div>
                <div class="text-xs opacity-75 truncate">{{ formatTime(appointment.scheduled_at) }}</div>
                <div class="text-xs opacity-75 truncate">{{ appointment.patient?.first_name }} {{ appointment.patient?.last_name }}</div>
              </div>
            </div>
          </div>
        </div>
      </div>

              <!-- Month View -->
      <div v-else-if="currentView === 'month'" class="p-6">
                <div class="grid grid-cols-7 gap-1">
          <!-- Day headers -->
          <div
            v-for="day in weekDayNames"
            :key="day"
            class="p-3 text-center text-sm font-medium text-theme-secondary border-b border-theme"
          >
            {{ day }}
    </div>

                  <!-- Calendar days -->
                  <div
                    v-for="day in monthDays"
                    :key="day.date"
            class="min-h-[120px] border border-theme p-2 hover:bg-theme-surface transition-colors"
            :class="{
              'bg-theme-surface': !day.isCurrentMonth,
              'bg-primary-50 border-primary-200': day.isToday
            }"
          >
            <div class="flex items-center justify-between mb-2">
              <span
                class="text-sm font-medium"
                :class="{
                  'text-theme-secondary': !day.isCurrentMonth,
                  'text-accent': day.isToday,
                  'text-theme-primary': day.isCurrentMonth && !day.isToday
                }"
              >
                {{ day.number }}
              </span>
              <span
                v-if="day.appointmentCount > 0"
                class="text-xs bg-primary-50 text-accent px-2 py-1 rounded-full"
              >
                {{ day.appointmentCount }}
                      </span>
                    </div>

                    <div class="space-y-1">
                      <div
                        v-for="appointment in day.appointments"
                        :key="appointment.id"
                        class="p-1.5 rounded text-xs cursor-pointer hover:shadow-sm transition-all duration-200"
                        :class="getAppointmentClasses(appointment)"
                        @click="selectAppointment(appointment)"
                      >
                        <div class="font-medium truncate">{{ appointment.appointment_type?.name || 'Cita' }}</div>
                        <div class="text-xs opacity-75 truncate">{{ formatTime(appointment.scheduled_at) }}</div>
                        <div class="text-xs opacity-75 truncate">{{ appointment.patient?.first_name }} {{ appointment.patient?.last_name }}</div>
                      </div>
                      <div
                        v-if="day.hasMore"
                        class="text-xs text-accent font-medium text-center mt-1"
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
      <div class="bg-theme-surface-elevated rounded-2xl shadow-2xl max-w-2xl w-full max-h-[90vh] overflow-y-auto">
        <div class="p-6 border-b border-theme">
          <div class="flex items-center justify-between">
            <h2 class="text-xl font-semibold text-theme-primary">Detalles de la Cita</h2>
            <button
              @click="selectedAppointment = null"
              class="text-theme-secondary hover:text-theme-primary transition-colors"
            >
              <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
              </svg>
            </button>
          </div>
        </div>
        <div class="p-6 space-y-4">
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
              <label class="text-sm font-medium text-theme-secondary">Paciente</label>
              <p class="text-theme-primary">{{ selectedAppointment.patient.first_name }} {{ selectedAppointment.patient.last_name }}</p>
            </div>
            <div>
              <label class="text-sm font-medium text-theme-secondary">Profesional</label>
              <p class="text-theme-primary">{{ selectedAppointment.user.name }}</p>
            </div>
          </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div>
              <label class="text-sm font-medium text-theme-secondary">Fecha y Hora</label>
              <p class="text-theme-primary">{{ formatDateTime(selectedAppointment.scheduled_at) }}</p>
              </div>
              <div>
              <label class="text-sm font-medium text-theme-secondary">Duración</label>
              <p class="text-theme-primary">{{ selectedAppointment.duration_minutes }} minutos</p>
              </div>
          </div>
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div>
              <label class="text-sm font-medium text-theme-secondary">Tipo de Cita</label>
              <p class="text-theme-primary">{{ selectedAppointment.appointment_type.name }}</p>
            </div>
            <div>
              <label class="text-sm font-medium text-theme-secondary">Estado</label>
              <span
                class="px-3 py-1 text-xs font-semibold rounded-full"
                :class="getStatusClasses(selectedAppointment.status)"
              >
                {{ getStatusText(selectedAppointment.status) }}
              </span>
            </div>
          </div>
          <div v-if="selectedAppointment.notes">
            <label class="text-sm font-medium text-theme-secondary">Notas</label>
            <p class="text-theme-primary">{{ selectedAppointment.notes }}</p>
          </div>
          <div v-if="selectedAppointment.treatment_notes">
            <label class="text-sm font-medium text-theme-secondary">Notas de Tratamiento</label>
            <p class="text-theme-primary">{{ selectedAppointment.treatment_notes }}</p>
          </div>
          <div class="flex flex-wrap gap-2 pt-2 border-t border-theme">
            <UiButton
              v-if="selectedAppointment.status === 'scheduled'"
              size="sm"
              variant="secondary"
              @click="changeStatus(selectedAppointment, 'confirmed')"
            >
              Confirmar
            </UiButton>
            <UiButton
              v-if="selectedAppointment.status === 'confirmed' || selectedAppointment.status === 'scheduled'"
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
import UiInput from '../../components/ui/Input.vue'
import UiSelect from '../../components/ui/Select.vue'
import NewAppointmentModal from '../../components/appointments/NewAppointmentModal.vue'
import ConsultationWizard from './ConsultationWizard.vue'
import { useConsultation } from '../../composables/useConsultation'

export default {
  name: 'CalendarPage',
  components: {
    AppLayout,
    UiCard,
    UiButton,
    UiInput,
    UiSelect,
    NewAppointmentModal,
    ConsultationWizard
  },
  setup() {
    const router = useRouter()
    const { get, post, put, del } = useApi()
    const { can } = usePermissions()
    const toast = useToast()
    const { channel, echo } = useEcho()

    // can ya es un objeto con propiedades computed, usarlo directamente

    const loading = ref(false)
    const currentView = ref('week')
    const currentDate = ref(new Date())
    const appointments = ref([])
    const selectedAppointment = ref(null)
    const showNewAppointmentModal = ref(false)
    const wizardAppointment = ref(null)
    const { checkIn, openForAppointment } = useConsultation()

    const startConsultation = async (appointment) => {
      try {
        await checkIn(appointment)
        await loadAppointments()
        selectedAppointment.value = null
      } catch (e) {
        // toast ya se mostró en el composable
      }
    }

    const openConsultationWizard = async (appointment) => {
      wizardAppointment.value = appointment
      selectedAppointment.value = null
      await openForAppointment(appointment)
    }

    const onConsultationCompleted = async (payload) => {
      wizardAppointment.value = null
      if (payload?.quotation_generated && payload?.quotation) {
        toast.info(`Cotización ${payload.quotation.quotation_number} lista en /quotations`, 6000)
      }
      await loadAppointments()
    }
    const editingAppointment = ref(null)

    const toLocalDateString = (date) => {
      const d = date instanceof Date ? date : new Date(date)
      const year = d.getFullYear()
      const month = String(d.getMonth() + 1).padStart(2, '0')
      const day = String(d.getDate()).padStart(2, '0')
      return `${year}-${month}-${day}`
    }

    const getAppointmentLocalDate = (appointment) => {
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
        return date.toISOString().slice(0, 16)
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

    const dayHours = computed(() => {
      const hours = []
      for (let i = 6; i <= 21; i++) {
        hours.push(i)
      }
      return hours
    })

    const weekHours = computed(() => {
      const hours = []
      for (let i = 6; i <= 21; i++) {
        hours.push(i)
      }
      return hours
    })

    const weekDayNames = ['Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb', 'Dom']

    const currentPeriodTitle = computed(() => {
      const date = currentDate.value
      switch (currentView.value) {
        case 'day':
          return date.toLocaleDateString('es-ES', {
            weekday: 'long',
            year: 'numeric',
            month: 'long',
            day: 'numeric'
          })
        case 'week':
          const startOfWeek = new Date(date)
          startOfWeek.setDate(date.getDate() - date.getDay() + 1)
          const endOfWeek = new Date(startOfWeek)
          endOfWeek.setDate(startOfWeek.getDate() + 6)
          return `${startOfWeek.toLocaleDateString('es-ES', { day: 'numeric', month: 'short' })} - ${endOfWeek.toLocaleDateString('es-ES', { day: 'numeric', month: 'short', year: 'numeric' })}`
        case 'month':
          return date.toLocaleDateString('es-ES', { year: 'numeric', month: 'long' })
        default:
          return ''
      }
    })

    const weekDays = computed(() => {
      const date = currentDate.value
      const startOfWeek = new Date(date)
      startOfWeek.setDate(date.getDate() - date.getDay() + 1)

      const days = []
      for (let i = 0; i < 7; i++) {
        const day = new Date(startOfWeek)
        day.setDate(startOfWeek.getDate() + i)
        days.push({
          date: toLocalDateString(day),
          name: weekDayNames[i],
          number: day.getDate()
        })
      }
      return days
    })

    const monthDays = computed(() => {
      const date = currentDate.value
      const year = date.getFullYear()
      const month = date.getMonth()

      const firstDay = new Date(year, month, 1)
      const lastDay = new Date(year, month + 1, 0)
      const startDate = new Date(firstDay)
      startDate.setDate(firstDay.getDate() - firstDay.getDay() + 1)

      const days = []
      const now = new Date()
      const currentDateStr = toLocalDateString(now)

      for (let i = 0; i < 42; i++) {
        const day = new Date(startDate)
        day.setDate(startDate.getDate() + i)
        const dayStr = toLocalDateString(day)
        const isToday = dayStr === currentDateStr

        const allDayAppointments = appointments.value.filter(apt => {
          const aptInfo = getAppointmentLocalDate(apt)
          if (!aptInfo) return false
          return aptInfo.dateStr === dayStr
        })

        const sortedDayAppointments = allDayAppointments.sort((a, b) => {
          try {
            return new Date(a.scheduled_at) - new Date(b.scheduled_at)
          } catch (e) {
            return 0
          }
        })

        days.push({
          date: dayStr,
          number: day.getDate(),
          isCurrentMonth: day.getMonth() === month,
          isToday: isToday,
          appointments: sortedDayAppointments.slice(0, 3),
          appointmentCount: allDayAppointments.length,
          hasMore: allDayAppointments.length > 3
        })
      }
      return days
    })

    const getDateRangeForCurrentView = () => {
      const date = currentDate.value
      if (currentView.value === 'day') {
        const start = new Date(date)
        const end = new Date(date)
        return {
          start_date: toLocalDateString(start),
          end_date: toLocalDateString(end)
        }
      }
      if (currentView.value === 'week') {
        const startOfWeek = new Date(date)
        startOfWeek.setDate(date.getDate() - date.getDay() + 1)
        const endOfWeek = new Date(startOfWeek)
        endOfWeek.setDate(startOfWeek.getDate() + 6)
        return {
          start_date: toLocalDateString(startOfWeek),
          end_date: toLocalDateString(endOfWeek)
        }
      }
      const firstDay = new Date(date.getFullYear(), date.getMonth(), 1)
      const startDate = new Date(firstDay)
      startDate.setDate(firstDay.getDate() - firstDay.getDay() + 1)
      const lastDay = new Date(startDate)
      lastDay.setDate(startDate.getDate() + 41)
      return {
        start_date: toLocalDateString(startDate),
        end_date: toLocalDateString(lastDay)
      }
    }

    const loadAppointments = async () => {
      loading.value = true
      try {
        const { start_date, end_date } = getDateRangeForCurrentView()
        
        const response = await get('/api/appointments', {
          params: {
            start_date,
            end_date,
            per_page: 1000 // Obtener todas las citas del rango sin paginación
          }
        })
        
        
        // La API devuelve { data: [...], meta: {...} }
        // useApi ya parsea el JSON, por lo que response.data es el array directamente
        appointments.value = response?.data || []
        
        
        if (appointments.value.length === 0) {
        }
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

    const getAppointmentLabel = (apt) => {
      const type = apt?.appointment_type?.name || 'Cita'
      const range = formatTimeRange(apt.scheduled_at, apt.ends_at || apt.scheduled_at)
      return `${type} ${range}`
    }

    const changeView = (view) => {
      currentView.value = view
      loadAppointments()
    }

    const previousPeriod = () => {
      const date = new Date(currentDate.value)
      switch (currentView.value) {
        case 'day':
          date.setDate(date.getDate() - 1)
          break
        case 'week':
          date.setDate(date.getDate() - 7)
          break
        case 'month':
          date.setMonth(date.getMonth() - 1)
          break
      }
      currentDate.value = date
      loadAppointments()
    }

    const nextPeriod = () => {
      const date = new Date(currentDate.value)
      switch (currentView.value) {
        case 'day':
          date.setDate(date.getDate() + 1)
          break
        case 'week':
          date.setDate(date.getDate() + 7)
          break
        case 'month':
          date.setMonth(date.getMonth() + 1)
          break
      }
      currentDate.value = date
      loadAppointments()
    }

    const goToToday = () => {
      currentDate.value = new Date()
      loadAppointments()
    }

    const getAppointmentsForHour = (hour) => {
      const dateStr = toLocalDateString(currentDate.value)
      const filtered = appointments.value.filter(apt => {
        const aptInfo = getAppointmentLocalDate(apt)
        if (!aptInfo) return false
        return aptInfo.dateStr === dateStr && aptInfo.hour === hour
      }).sort((a, b) => {
        try {
          return new Date(a.scheduled_at) - new Date(b.scheduled_at)
        } catch (e) {
          return 0
        }
      })
      return filtered
    }

    const getAppointmentsForDayAndHour = (dateStr, hour) => {
      return appointments.value.filter(apt => {
        const aptInfo = getAppointmentLocalDate(apt)
        if (!aptInfo) return false
        return aptInfo.dateStr === dateStr && aptInfo.hour === hour
      }).sort((a, b) => {
        try {
          return new Date(a.scheduled_at) - new Date(b.scheduled_at)
        } catch (e) {
          return 0
        }
      })
    }

    const getAppointmentClasses = (appointment) => {
      const baseClasses = 'bg-theme-surface-elevated border border-theme'
      const statusClasses = {
        scheduled: 'border-primary-200 bg-primary-50',
        confirmed: 'border-success bg-success-badge',
        in_consultation: 'border-warning bg-warning-badge',
        completed: 'border-theme bg-theme-surface',
        cancelled: 'border-danger bg-danger-badge',
        no_show: 'border-warning bg-warning-badge',
        rescheduled: 'border-primary-200 bg-primary-50'
      }
      return `${baseClasses} ${statusClasses[appointment.status] || statusClasses.scheduled}`
    }

    const getStatusClasses = (status) => {
      const classes = {
        scheduled: 'bg-primary-50 text-primary-700',
        confirmed: 'bg-success-badge',
        in_consultation: 'bg-warning-badge',
        completed: 'bg-theme-surface text-theme-secondary',
        cancelled: 'bg-danger-badge',
        no_show: 'bg-warning-badge',
        rescheduled: 'bg-primary-50 text-primary-700'
      }
      return classes[status] || classes.scheduled
    }

    const getStatusText = (status) => {
      const texts = {
        scheduled: 'Programada',
        confirmed: 'Confirmada',
        in_consultation: 'En Consulta',
        completed: 'Completada',
        cancelled: 'Cancelada',
        no_show: 'No se presentó',
        rescheduled: 'Reprogramada'
      }
      return texts[status] || status
    }

    const selectAppointment = (appointment) => {
      selectedAppointment.value = appointment
    }


    const editAppointment = (appointment) => {
      editingAppointment.value = appointment
      showNewAppointmentModal.value = true
      selectedAppointment.value = null
    }

    const changeStatus = async (appointment, newStatus) => {
      try {
        await put(`/api/appointments/${appointment.id}`, { status: newStatus })
        toast.success('Estado actualizado')
        await loadAppointments()
        selectedAppointment.value = null
      } catch (error) {
        const errorMessage = error?.response?.data?.message || 'Error al cambiar el estado'
        toast.error(errorMessage)
      }
    }

    const deleteAppointment = async (appointment) => {
      if (confirm(`¿Estás seguro de que quieres eliminar esta cita?`)) {
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


    const formatHour = (hour) => {
      return `${hour.toString().padStart(2, '0')}:00`
    }

    const formatTime = (dateTime) => {
      return new Date(dateTime).toLocaleTimeString('es-ES', {
        hour: '2-digit',
        minute: '2-digit'
      })
    }

    const formatDateTime = (dateTime) => {
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

    watch(showNewAppointmentModal, (isOpen) => {
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
            .listen('.appointment.created', async (e) => {
              await loadAppointments()
              toast.success('Nueva cita agregada')
            })
            .listen('.appointment.updated', async () => {
              await loadAppointments()
              toast.success('Cita actualizada')
            })
            .listen('.appointment.deleted', async (e) => {
              // Remover la cita de la lista
              appointments.value = appointments.value.filter(apt => apt.id !== e.appointment_id)
              if (selectedAppointment.value?.id === e.appointment_id) {
                selectedAppointment.value = null
              }
              toast.success('Cita eliminada')
            })
        }
      } catch (error) {
      }
    })

    onUnmounted(() => {
      // Limpiar suscripciones WebSocket
      if (echo) {
        try {
          echo.leave('appointments')
        } catch (e) {
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

<style scoped>
/* Responsive design */
@media (max-width: 640px) {
  .grid-cols-1 {
    grid-template-columns: repeat(1, minmax(0, 1fr));
  }
}
</style>
