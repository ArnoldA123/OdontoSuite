<template>
  <div class="fixed inset-0 bg-black bg-black/50 flex items-center justify-center z-50 p-4">
    <div class="bg-theme-surface-elevated rounded-lg shadow-xl max-w-2xl w-full max-h-[90vh] overflow-y-auto">
      <!-- Header -->
      <div class="flex items-center justify-between p-6 border-b border-theme">
        <h2 class="text-xl font-semibold text-theme-primary">
          {{ isEditing ? 'Editar Cita' : 'Nueva Cita' }}
        </h2>
        <button
          @click="$emit('close')"
          class="text-theme-secondary hover:text-theme-primary transition-colors"
        >
          <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
          </svg>
        </button>
      </div>

      <!-- Form -->
      <form @submit.prevent="saveAppointment" class="p-6 space-y-6">
        <!-- Patient Selection -->
        <div>
          <label class="block text-sm font-medium text-theme-primary mb-2">
            Paciente *
          </label>
          <div class="relative">
            <input
              v-model="searchPatient"
              @input="searchPatients"
              type="text"
              placeholder="Buscar paciente..."
              class="w-full border border-theme rounded-lg px-3 py-2 focus:ring-2 focus:ring-primary-500 focus:border-accent"
            />
            <div
              v-if="patientSearchResults.length > 0 && searchPatient"
              class="absolute z-10 w-full mt-1 bg-theme-surface-elevated border border-theme rounded-lg shadow-lg max-h-60 overflow-y-auto"
            >
              <div
                v-for="patient in patientSearchResults"
                :key="patient.id"
                @click="selectPatient(patient)"
                class="px-3 py-2 hover:bg-theme-surface cursor-pointer border-b border-theme last:border-b-0"
              >
                <div class="font-medium text-theme-primary">{{ patient.first_name }} {{ patient.last_name }}</div>
                <div class="text-sm text-theme-secondary">{{ patient.email }} - {{ patient.phone }}</div>
              </div>
            </div>
          </div>
          <div v-if="form.patient_id" class="mt-2 p-3 bg-primary-50 rounded-lg">
            <div class="flex items-center justify-between">
              <div>
                <div class="font-medium text-primary-900">{{ selectedPatientName }}</div>
                <div class="text-sm text-primary-700">{{ selectedPatientEmail }}</div>
              </div>
              <button
                type="button"
                @click="clearPatient"
                class="text-accent hover:text-primary-800"
              >
                Cambiar
              </button>
            </div>
          </div>
        </div>

        <!-- Professional Selection -->
        <div>
          <label class="block text-sm font-medium text-theme-primary mb-2">
            Profesional *
          </label>
          <select
            v-model="form.user_id"
            required
            class="w-full border border-theme rounded-lg px-3 py-2 focus:ring-2 focus:ring-primary-500 focus:border-accent"
          >
            <option value="">Seleccionar profesional</option>
            <option v-for="user in users" :key="user.id" :value="user.id">
              {{ user.name }}
            </option>
          </select>
        </div>

        <!-- Dental Chair -->
        <div>
          <label class="block text-sm font-medium text-theme-primary mb-2">
            Silla Dental *
          </label>
          <select
            v-model="form.dental_chair_id"
            required
            class="w-full border border-theme rounded-lg px-3 py-2 focus:ring-2 focus:ring-primary-500 focus:border-accent"
          >
            <option value="">Seleccionar silla</option>
            <option v-for="chair in dentalChairs" :key="chair.id" :value="chair.id">
              {{ chair.name }} - {{ chair.description }}
            </option>
          </select>
        </div>

        <!-- Appointment Type -->
        <div>
          <label class="block text-sm font-medium text-theme-primary mb-2">
            Tipo de Cita *
          </label>
          <select
            v-model="form.appointment_type_id"
            @change="updateDuration"
            required
            class="w-full border border-theme rounded-lg px-3 py-2 focus:ring-2 focus:ring-primary-500 focus:border-accent"
          >
            <option value="">Seleccionar tipo</option>
            <option v-for="type in appointmentTypes" :key="type.id" :value="type.id">
              {{ type.name }} ({{ type.default_duration_minutes }} min)
            </option>
          </select>
        </div>

        <!-- Date and Time -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium text-theme-primary mb-2">
              Fecha *
            </label>
            <input
              v-model="form.date"
              type="date"
              required
              class="w-full border border-theme rounded-lg px-3 py-2 focus:ring-2 focus:ring-primary-500 focus:border-accent"
            />
          </div>
          <div>
            <label class="block text-sm font-medium text-theme-primary mb-2">
              Hora *
            </label>
            <input
              v-model="form.time"
              type="time"
              required
              class="w-full border border-theme rounded-lg px-3 py-2 focus:ring-2 focus:ring-primary-500 focus:border-accent"
            />
          </div>
        </div>

        <!-- Duration -->
        <div>
          <label class="block text-sm font-medium text-theme-primary mb-2">
            Duración (minutos)
          </label>
          <input
            v-model="form.duration_minutes"
            type="number"
            min="15"
            max="480"
            step="15"
            class="w-full border border-theme rounded-lg px-3 py-2 focus:ring-2 focus:ring-primary-500 focus:border-accent"
          />
        </div>

        <!-- Status (only for editing) -->
        <div v-if="isEditing">
          <label class="block text-sm font-medium text-theme-primary mb-2">
            Estado
          </label>
          <select
            v-model="form.status"
            class="w-full border border-theme rounded-lg px-3 py-2 focus:ring-2 focus:ring-primary-500 focus:border-accent"
          >
            <option value="scheduled">Programada</option>
            <option value="confirmed">Confirmada</option>
            <option value="in_consultation">En Consulta</option>
            <option value="completed">Completada</option>
            <option value="cancelled">Cancelada</option>
            <option value="no_show">No se presentó</option>
          </select>
        </div>

        <!-- Notes -->
        <div>
          <label class="block text-sm font-medium text-theme-primary mb-2">
            Notas
          </label>
          <textarea
            v-model="form.notes"
            rows="3"
            class="w-full border border-theme rounded-lg px-3 py-2 focus:ring-2 focus:ring-primary-500 focus:border-accent"
            placeholder="Notas adicionales sobre la cita..."
          ></textarea>
        </div>

        <!-- Actions -->
        <div class="flex justify-end space-x-3 pt-6 border-t">
          <button
            type="button"
            @click="$emit('close')"
            class="px-4 py-2 text-theme-primary bg-theme-surface hover:bg-theme-surface-elevated rounded-lg font-medium transition-colors"
          >
            Cancelar
          </button>
          <button
            type="submit"
            :disabled="loading"
            class="px-4 py-2 bg-primary-500 hover:bg-primary-600 text-white rounded-lg font-medium transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
          >
            <span v-if="loading">Guardando...</span>
            <span v-else>{{ isEditing ? 'Actualizar' : 'Crear' }} Cita</span>
          </button>
        </div>
      </form>
    </div>
  </div>
</template>

<script>
import { ref, computed, watch, onMounted } from 'vue'
import { useApi } from '../../composables/useApi'
import { useToast } from '../../composables/useToast'

export default {
  name: 'AppointmentModal',
  props: {
    appointment: {
      type: Object,
      default: null
    },
    patients: {
      type: Array,
      default: () => []
    },
    users: {
      type: Array,
      default: () => []
    },
    dentalChairs: {
      type: Array,
      default: () => []
    },
    appointmentTypes: {
      type: Array,
      default: () => []
    }
  },
  emits: ['close', 'saved'],
  setup(props, { emit }) {
    const { get, post, put } = useApi()
    const toast = useToast()

    // Reactive data
    const loading = ref(false)
    const searchPatient = ref('')
    const patientSearchResults = ref([])

    // Form data
    const form = ref({
      patient_id: '',
      user_id: '',
      dental_chair_id: '',
      appointment_type_id: '',
      date: '',
      time: '',
      duration_minutes: 60,
      status: 'scheduled',
      notes: ''
    })

    // Computed
    const isEditing = computed(() => !!props.appointment?.id)

    const selectedPatientName = computed(() => {
      if (!form.value.patient_id) return ''
      const patient = props.patients.find(p => p.id === parseInt(form.value.patient_id))
      return patient ? `${patient.first_name} ${patient.last_name}` : ''
    })

    const selectedPatientEmail = computed(() => {
      if (!form.value.patient_id) return ''
      const patient = props.patients.find(p => p.id === parseInt(form.value.patient_id))
      return patient ? patient.email : ''
    })

    // Methods
    const searchPatients = async () => {
      if (searchPatient.value.length < 2) {
        patientSearchResults.value = []
        return
      }

      try {
        const response = await get(`/api/patients/search?q=${encodeURIComponent(searchPatient.value)}`)
        patientSearchResults.value = response.data
      } catch (error) {
        console.error('Error searching patients:', error)
        patientSearchResults.value = []
      }
    }

    const selectPatient = (patient) => {
      form.value.patient_id = patient.id
      searchPatient.value = ''
      patientSearchResults.value = []
    }

    const clearPatient = () => {
      form.value.patient_id = ''
      searchPatient.value = ''
    }

    const updateDuration = () => {
      const appointmentType = props.appointmentTypes.find(type => type.id === parseInt(form.value.appointment_type_id))
      if (appointmentType) {
        form.value.duration_minutes = appointmentType.default_duration_minutes
      }
    }

    const saveAppointment = async () => {
      if (!form.value.patient_id) {
        toast.warning('Por favor selecciona un paciente', { duration: 4000 })
        return
      }

      loading.value = true

      try {
        const appointmentData = {
          ...form.value,
          scheduled_at: `${form.value.date}T${form.value.time}:00`
        }

        // Remove date and time from the data as they're combined into scheduled_at
        delete appointmentData.date
        delete appointmentData.time

        if (isEditing.value) {
          await put(`/api/appointments/${props.appointment.id}`, appointmentData)
          toast.success(
            'Cita actualizada exitosamente',
            {
              duration: 4000,
              title: '✓ Cita Actualizada'
            }
          )
        } else {
          await post('/api/appointments', appointmentData)
          toast.success(
            'Cita creada exitosamente',
            {
              duration: 4000,
              title: '✓ Cita Creada'
            }
          )
        }

        emit('saved')
        // Los eventos WebSocket se manejan automáticamente desde el backend
      } catch (error) {
        console.error('Error saving appointment:', error)

        // Notificación de error mejorada
        if (error.response?.data?.errors) {
          const errors = error.response.data.errors
          const errorMessages = Object.values(errors).flat()
          toast.error(
            'Errores de validación:\n' + errorMessages.join('\n'),
            {
              duration: 8000,
              title: '✗ Error de Validación'
            }
          )
        } else {
          toast.error(
            error.response?.data?.message || 'Error al guardar la cita. Por favor intenta nuevamente.',
            {
              duration: 6000,
              title: '✗ Error al Guardar'
            }
          )
        }
      } finally {
        loading.value = false
      }
    }

    const initializeForm = () => {
      if (props.appointment) {
        // Editing existing appointment
        form.value = {
          patient_id: props.appointment.patient?.id || '',
          user_id: props.appointment.user?.id || '',
          dental_chair_id: props.appointment.dentalChair?.id || '',
          appointment_type_id: props.appointment.appointmentType?.id || '',
          date: props.appointment.scheduled_at ? props.appointment.scheduled_at.split('T')[0] : '',
          time: props.appointment.scheduled_at ? props.appointment.scheduled_at.split('T')[1].substring(0, 5) : '',
          duration_minutes: props.appointment.duration_minutes || 60,
          status: props.appointment.status || 'scheduled',
          notes: props.appointment.notes || ''
        }
      } else {
        // New appointment
        const today = new Date().toISOString().split('T')[0]
        form.value = {
          patient_id: '',
          user_id: '',
          dental_chair_id: '',
          appointment_type_id: '',
          date: today,
          time: '09:00',
          duration_minutes: 60,
          status: 'scheduled',
          notes: ''
        }
      }
    }

    // Watchers
    watch(() => props.appointment, initializeForm, { immediate: true })

    // Lifecycle
    onMounted(() => {
      initializeForm()
    })

    return {
      loading,
      searchPatient,
      patientSearchResults,
      form,
      isEditing,
      selectedPatientName,
      selectedPatientEmail,
      searchPatients,
      selectPatient,
      clearPatient,
      updateDuration,
      saveAppointment
    }
  }
}
</script>
