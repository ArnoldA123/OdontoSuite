<template>
  <div
    v-if="modelValue"
    class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center p-4 z-50"
    @click.self="closeModal"
  >
    <div class="bg-theme-surface-elevated rounded-2xl shadow-2xl max-w-2xl w-full max-h-[90vh] overflow-y-auto">
      <div class="p-6 border-b border-theme">
        <div class="flex items-center justify-between">
          <h2 class="text-xl font-semibold text-theme-primary">{{ modalTitle }}</h2>
          <button
            @click="closeModal"
            class="text-theme-secondary hover:text-theme-primary transition-colors"
          >
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>
        </div>
      </div>
      <div class="p-6">
        <!-- Loading State -->
        <div v-if="loadingData" class="text-center py-8">
          <div class="inline-block animate-spin rounded-full h-8 w-8 border-4 border-purple-200 border-t-purple-600"></div>
          <p class="mt-4 text-theme-secondary">Cargando datos...</p>
        </div>

        <!-- Form -->
        <form v-else @submit.prevent class="space-y-4" @keydown.enter.prevent>
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <UiSelect
              v-model="form.patient_id"
              :options="patientOptions"
              label="Paciente"
              placeholder="Seleccionar paciente"
              required
              searchable
              clearable
            />
            <UiSelect
              v-model="form.user_id"
              :options="professionalOptions"
              label="Profesional"
              placeholder="Seleccionar profesional"
              required
              searchable
              clearable
            />
          </div>
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <UiInput
              v-model="form.scheduled_at"
              label="Fecha y Hora"
              type="datetime-local"
              required
            />
            <UiInput
              v-model.number="form.duration_minutes"
              label="Duración (minutos)"
              type="number"
              min="15"
              max="480"
              step="5"
              placeholder="Ej. 30"
              required
            />
            <UiSelect
              v-model="form.appointment_type_id"
              :options="appointmentTypeOptions"
              label="Tipo de Cita"
              placeholder="Seleccionar tipo"
              required
              searchable
              clearable
            />
          </div>
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <UiSelect
              v-model="form.dental_chair_id"
              :options="dentalChairOptions"
              label="Sillón Dental"
              placeholder="Seleccionar sillón"
              required
              searchable
              clearable
            />
            <UiSelect
              v-model="form.status"
              :options="statusOptions"
              label="Estado"
              required
            />
          </div>
          <UiInput
            v-model="form.notes"
            label="Notas"
            placeholder="Notas adicionales sobre la cita"
            type="textarea"
          />
          <div class="flex justify-end gap-3 pt-4">
            <UiButton
              type="button"
              variant="secondary"
              @click="closeModal"
            >
              Cancelar
            </UiButton>
            <UiButton
              type="button"
              :loading="creating"
              @click="saveAppointment"
            >
              {{ submitButtonText }}
            </UiButton>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, watch, onMounted, onUnmounted } from 'vue'
import { useApi } from '../../composables/useApi'
import { useOptionsTransform } from '../../composables/useOptionsTransform'
import { useToast } from '../../composables/useToast'
import { useEcho } from '../../composables/useEcho'
import UiButton from '../ui/Button.vue'
import UiInput from '../ui/Input.vue'
import UiSelect from '../ui/Select.vue'
import ProcedureQuickPicker from '../procedures/ProcedureQuickPicker.vue'

const props = defineProps({
  modelValue: {
    type: Boolean,
    default: false
  },
  initialDate: {
    type: String,
    default: null
  },
  appointment: {
    type: Object,
    default: null
  }
})

const emit = defineEmits(['update:modelValue', 'created', 'updated'])

const { get, post, put } = useApi()
const { transformPatients, transformProfessionals, transformAppointmentTypes, transformDentalChairs } = useOptionsTransform()
const toast = useToast()
const { channel, echo } = useEcho()

const loadingData = ref(false)
const creating = ref(false)
const patients = ref([])
const professionals = ref([])
const appointmentTypes = ref([])
const dentalChairs = ref([])

const form = ref({
  patient_id: '',
  user_id: '',
  scheduled_at: '',
  duration_minutes: null,
  appointment_type_id: '',
  procedure_id: null,
  selected_procedure: null,
  final_amount: null,
  dental_chair_id: '',
  status: 'scheduled',
  notes: ''
})

// Opciones transformadas para UiSelect
const patientOptions = computed(() => transformPatients(patients.value))
const professionalOptions = computed(() => transformProfessionals(professionals.value))
const appointmentTypeOptions = computed(() => transformAppointmentTypes(appointmentTypes.value))
const dentalChairOptions = computed(() => transformDentalChairs(dentalChairs.value))

// Opciones estáticas para estado
const statusOptions = computed(() => [
  { value: 'scheduled', label: 'Programada' },
  { value: 'confirmed', label: 'Confirmada' },
  { value: 'in_consultation', label: 'En Consulta' },
  { value: 'completed', label: 'Completada' },
  { value: 'cancelled', label: 'Cancelada' },
  { value: 'no_show', label: 'No se presentó' },
  { value: 'rescheduled', label: 'Reprogramada' }
])

const isEditMode = computed(() => !!props.appointment?.id)
const modalTitle = computed(() => (isEditMode.value ? 'Editar Cita' : 'Nueva Cita'))
const submitButtonText = computed(() => (isEditMode.value ? 'Guardar Cambios' : 'Crear Cita'))
const selectedProcedure = computed(() => form.value.selected_procedure)
const procedureSpecialtyFilter = computed(() => {
  const user = professionals.value.find(p => p.id === form.value.user_id)
  return user?.specialty || ''
})

const onProcedureSelected = proc => {
  form.value.selected_procedure = proc
  if (proc?.default_duration_minutes) {
    form.value.duration_minutes = proc.default_duration_minutes
  }
  if (proc?.default_cost) {
    form.value.final_amount = Number(proc.default_cost)
  }
}

const toDatetimeLocal = (isoString) => {
  if (!isoString) return ''
  const d = new Date(isoString)
  if (isNaN(d.getTime())) return ''
  const year = d.getFullYear()
  const month = String(d.getMonth() + 1).padStart(2, '0')
  const day = String(d.getDate()).padStart(2, '0')
  const hours = String(d.getHours()).padStart(2, '0')
  const minutes = String(d.getMinutes()).padStart(2, '0')
  return `${year}-${month}-${day}T${hours}:${minutes}`
}

const populateFormFromAppointment = (appointment) => {
  if (!appointment) return
  form.value = {
    patient_id: appointment.patient_id ?? appointment.patient?.id ?? '',
    user_id: appointment.user_id ?? appointment.user?.id ?? '',
    scheduled_at: toDatetimeLocal(appointment.scheduled_at),
    duration_minutes: appointment.duration_minutes ?? null,
    appointment_type_id: appointment.appointment_type_id ?? appointment.appointment_type?.id ?? '',
    procedure_id: appointment.procedure_id ?? null,
    selected_procedure: appointment.procedure ?? null,
    final_amount: appointment.final_amount ?? null,
    dental_chair_id: appointment.dental_chair_id ?? appointment.dental_chair?.id ?? '',
    status: appointment.status || 'scheduled',
    notes: appointment.notes || ''
  }
}

const closeModal = () => {
  emit('update:modelValue', false)
}

const resetForm = () => {
  if (props.appointment) {
    populateFormFromAppointment(props.appointment)
    return
  }
  form.value = {
    patient_id: '',
    user_id: '',
    scheduled_at: props.initialDate || '',
    duration_minutes: null,
    appointment_type_id: '',
    procedure_id: null,
    selected_procedure: null,
    final_amount: null,
    dental_chair_id: '',
    status: 'scheduled',
    notes: ''
  }
}

const loadData = async () => {
  loadingData.value = true
  try {
    // Cargar todos los pacientes (obtener todas las páginas si es necesario)
    let allPatients = []
    let currentPage = 1
    let hasMorePages = true
    
    while (hasMorePages) {
      const patientsRes = await get(`/api/patients?per_page=100&page=${currentPage}`)
      const pagePatients = patientsRes?.data || []
      allPatients = [...allPatients, ...pagePatients]
      
      // Verificar si hay más páginas
      const totalPages = patientsRes?.meta?.last_page || 1
      hasMorePages = currentPage < totalPages
      currentPage++
    }

    // Cargar otros datos
    const [professionalsRes, typesRes, chairsRes] = await Promise.all([
      get('/api/users/active'),
      get('/api/appointment-types/active'),
      get('/api/dental-chairs/active')
    ])

    patients.value = allPatients
    professionals.value = professionalsRes?.data || []
    appointmentTypes.value = typesRes?.data || []
    dentalChairs.value = chairsRes?.data || []

    // Verificar si hay datos vacíos y notificar
    if (patients.value.length === 0) {
      console.warn('No se encontraron pacientes')
    }
    if (professionals.value.length === 0) {
      toast.warning('No se encontraron profesionales activos')
    }
    if (appointmentTypes.value.length === 0) {
      toast.warning('No se encontraron tipos de cita activos')
    }
    if (dentalChairs.value.length === 0) {
      toast.warning('No se encontraron sillas dentales activas')
    }
  } catch (error) {
    console.error('Error loading data:', error)
    toast.error('Error al cargar los datos. Por favor, recarga la página.')
  } finally {
    loadingData.value = false
  }
}

const formatScheduledAtForApi = () => {
  if (!form.value.scheduled_at) return null
  const localDate = new Date(form.value.scheduled_at)
  if (isNaN(localDate.getTime())) return null
  const year = localDate.getFullYear()
  const month = String(localDate.getMonth() + 1).padStart(2, '0')
  const day = String(localDate.getDate()).padStart(2, '0')
  const hours = String(localDate.getHours()).padStart(2, '0')
  const minutes = String(localDate.getMinutes()).padStart(2, '0')
  const seconds = String(localDate.getSeconds()).padStart(2, '0')
  return `${year}-${month}-${day}T${hours}:${minutes}:${seconds}`
}

const saveAppointment = async () => {
  creating.value = true
  try {
    // Validaciones básicas en UI
    if (!form.value.patient_id) {
      toast.error('Debes seleccionar un paciente')
      creating.value = false
      return
    }
    if (!form.value.user_id) {
      toast.error('Debes seleccionar un profesional')
      creating.value = false
      return
    }
    if (!form.value.scheduled_at) {
      toast.error('Debes seleccionar una fecha y hora')
      creating.value = false
      return
    }
    if (!form.value.duration_minutes || form.value.duration_minutes <= 0) {
      toast.error('Debes ingresar la duración en minutos (mínimo 15)')
      creating.value = false
      return
    }
    if (!form.value.appointment_type_id) {
      toast.error('Debes seleccionar un tipo de cita')
      creating.value = false
      return
    }
    if (!form.value.dental_chair_id) {
      toast.error('Debes seleccionar un sillón dental')
      creating.value = false
      return
    }

    const scheduledAtISO = formatScheduledAtForApi()
    if (!scheduledAtISO) {
      toast.error('La fecha y hora seleccionada no es válida')
      creating.value = false
      return
    }

    const appointmentData = {
      ...form.value,
      scheduled_at: scheduledAtISO
    }
    delete appointmentData.selected_procedure
    if (!appointmentData.procedure_id) {
      delete appointmentData.procedure_id
    }
    if (!appointmentData.final_amount) {
      delete appointmentData.final_amount
    }

    if (!isEditMode.value) {
      const now = new Date()
      const scheduledDate = new Date(scheduledAtISO)
      const minutesDiff = Math.round((scheduledDate - now) / 60000)
      if (minutesDiff < 1) {
        toast.error(`La fecha y hora debe ser al menos 1 minuto en el futuro. Diferencia actual: ${minutesDiff} minutos`)
        creating.value = false
        return
      }
    }

    let response
    if (isEditMode.value) {
      response = await put(`/api/appointments/${props.appointment.id}`, appointmentData)
      toast.success('Cita actualizada exitosamente')
      emit('updated', response?.data || form.value)
    } else {
      response = await post('/api/appointments', appointmentData)
      toast.success('Cita creada exitosamente')
      emit('created', response?.data || form.value)
    }

    resetForm()
    closeModal()
  } catch (error) {
    console.error('Error saving appointment:', error)
    const errorMessage = error?.response?.data?.message || (isEditMode.value ? 'Error al actualizar la cita' : 'Error al crear la cita')
    const errors = error?.response?.data?.errors
    if (errors) {
      const errorMessages = Object.values(errors).flat()
      toast.error('Errores de validación:\n' + errorMessages.join('\n'))
    } else {
      toast.error(errorMessage)
    }
  } finally {
    creating.value = false
  }
}

// Cargar datos cuando el modal se abre
watch(() => props.modelValue, (isOpen) => {
  if (isOpen) {
    resetForm()
    loadData()
  }
})

watch(() => props.appointment, () => {
  if (props.modelValue && props.appointment) {
    populateFormFromAppointment(props.appointment)
  }
})

// Prellenar fecha inicial si se proporciona
watch(() => props.initialDate, (newDate) => {
  if (newDate && props.modelValue) {
    form.value.scheduled_at = newDate
  }
}, { immediate: true })

// WebSocket subscriptions para actualizar lista de pacientes en tiempo real
let patientsChannel = null

onMounted(() => {
  if (props.modelValue) {
    loadData()
  }

  // Suscribirse a canales WebSocket para actualizaciones en tiempo real
  try {
    patientsChannel = channel('patients')
    if (patientsChannel) {
      patientsChannel
        .listen('.patient.created', async (e) => {
          console.log('Patient created via WebSocket:', e.patient)
          // Recargar todos los pacientes para incluir el nuevo
          await loadData()
        })
        .listen('.patient.updated', async (e) => {
          console.log('Patient updated via WebSocket:', e.patient)
          // Actualizar el paciente en la lista si existe
          const index = patients.value.findIndex(p => p.id === e.patient.id)
          if (index !== -1) {
            patients.value[index] = e.patient
          } else {
            // Si no existe, recargar todos
            await loadData()
          }
        })
        .listen('.patient.deleted', async (e) => {
          console.log('Patient deleted via WebSocket:', e.patient_id)
          // Remover el paciente de la lista
          patients.value = patients.value.filter(p => p.id !== e.patient_id)
        })
    }
  } catch (error) {
    console.error('Error setting up WebSocket subscriptions:', error)
  }
})

onUnmounted(() => {
  // Limpiar suscripciones WebSocket
  if (echo) {
    try {
      echo.leave('patients')
    } catch (e) {
      console.error('Error leaving patients channel:', e)
    }
  }
})
</script>

