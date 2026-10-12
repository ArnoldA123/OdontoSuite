<template>
  <UiModal
    model-value
    :title="mode === 'edit' ? 'Editar Paciente' : 'Nuevo Paciente'"
    size="xl"
    @close="emit('close')"
  >
    <form data-patient-form class="space-y-4" @submit.prevent="submitPatient">
      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <UiInput
          v-model="form.first_name"
          data-patient-field="first_name"
          label="Nombre"
          placeholder="Ingresa el nombre"
          required
        />
        <UiInput
          v-model="form.last_name"
          data-patient-field="last_name"
          label="Apellido"
          placeholder="Ingresa el apellido"
          required
        />
      </div>
      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <UiInput
          v-model="form.document_number"
          data-patient-field="document_number"
          label="DNI"
          placeholder="Ingresa el DNI"
        />
        <UiInput
          v-model="form.email"
          data-patient-field="email"
          label="Email"
          type="email"
          placeholder="correo@ejemplo.com"
        />
      </div>
      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <UiInput
          v-model="form.phone"
          data-patient-field="phone"
          label="Teléfono"
          placeholder="+51 999 999 999"
        />
        <UiInput
          v-model="form.birth_date"
          data-patient-field="birth_date"
          label="Fecha de Nacimiento"
          type="date"
        />
      </div>
      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div data-patient-field="gender">
          <label class="block text-sm font-medium text-theme-primary mb-1">Género</label>
          <UiSelect
            v-model="form.gender"
            :options="genderOptions"
            placeholder="Seleccionar"
            class="w-full"
          />
        </div>
        <div data-patient-field="is_active">
          <label class="block text-sm font-medium text-theme-primary mb-1">Estado</label>
          <UiSelect
            v-model="form.is_active"
            :options="statusOptions"
            placeholder="Seleccionar"
            class="w-full"
          />
        </div>
      </div>
      <UiInput
        v-model="form.address"
        data-patient-field="address"
        label="Dirección"
        placeholder="Ingresa la dirección"
      />
      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <UiInput
          v-model="form.emergency_contact_name"
          data-patient-field="emergency_contact_name"
          label="Contacto de Emergencia"
          placeholder="Nombre del contacto"
        />
        <UiInput
          v-model="form.emergency_contact_phone"
          data-patient-field="emergency_contact_phone"
          label="Teléfono de Emergencia"
          placeholder="+51 999 999 999"
        />
      </div>
      <div data-patient-field="medical_history">
        <label class="block text-sm font-medium text-theme-primary mb-1">Historial Médico</label>
        <UiTextarea
          v-model="form.medical_history"
          placeholder="Alergias, condiciones médicas, etc."
          :rows="3"
          class="w-full"
        />
      </div>
      <div data-patient-field="allergies">
        <label class="block text-sm font-medium text-theme-primary mb-1">Alergias</label>
        <UiTextarea
          v-model="form.allergies"
          placeholder="Lista de alergias conocidas"
          :rows="3"
          class="w-full"
        />
      </div>
      <div data-patient-field="notes">
        <label class="block text-sm font-medium text-theme-primary mb-1">Notas</label>
        <UiTextarea v-model="form.notes" placeholder="Notas adicionales" :rows="3" class="w-full" />
      </div>
      <div class="flex justify-end gap-3 pt-4">
        <UiButton type="button" variant="secondary" @click="emit('close')">Cancelar</UiButton>
        <UiButton type="submit" :loading="submitting">
          {{ mode === 'edit' ? 'Actualizar Paciente' : 'Crear Paciente' }}
        </UiButton>
      </div>
    </form>
  </UiModal>
</template>

<script setup>
import { ref, watch } from 'vue'
import { useApi } from '../../composables/useApi'
import { useToast } from '../../composables/useToast'
import UiButton from '../../components/ui/Button.vue'
import UiInput from '../../components/ui/Input.vue'
import UiSelect from '../../components/ui/Select.vue'
import UiModal from '../../components/ui/Modal.vue'
import UiTextarea from '../../components/ui/UiTextarea.vue'

const props = defineProps({
  mode: {
    type: String,
    default: 'create',
    validator: value => ['create', 'edit'].includes(value)
  },
  patient: {
    type: Object,
    default: null
  }
})

const emit = defineEmits(['close', 'saved'])

const { post, put } = useApi()
const toast = useToast()

const genderOptions = [
  { value: '', label: 'Seleccionar' },
  { value: 'male', label: 'Masculino' },
  { value: 'female', label: 'Femenino' },
  { value: 'other', label: 'Otro' }
]

const statusOptions = [
  { value: true, label: 'Activo' },
  { value: false, label: 'Inactivo' }
]

const emptyForm = () => ({
  first_name: '',
  last_name: '',
  document_number: '',
  email: '',
  phone: '',
  birth_date: '',
  gender: '',
  address: '',
  emergency_contact_name: '',
  emergency_contact_phone: '',
  medical_history: '',
  allergies: '',
  notes: '',
  is_active: true
})

const patientToForm = patient => ({
  first_name: patient.first_name || '',
  last_name: patient.last_name || '',
  document_number: patient.document_number || '',
  email: patient.email || '',
  phone: patient.phone || '',
  birth_date: patient.birth_date ? patient.birth_date.split('T')[0] : '',
  gender: patient.gender || '',
  address: patient.address || '',
  emergency_contact_name: patient.emergency_contact_name || '',
  emergency_contact_phone: patient.emergency_contact_phone || '',
  medical_history: patient.medical_history || '',
  allergies: patient.allergies || '',
  notes: patient.notes || '',
  is_active: patient.is_active !== undefined ? patient.is_active : true
})

const form = ref(emptyForm())
const submitting = ref(false)

watch(
  () => props.patient,
  patient => {
    form.value = patient ? patientToForm(patient) : emptyForm()
  },
  { immediate: true }
)

const submitPatient = async () => {
  submitting.value = true
  try {
    const payload = { ...form.value }
    const response =
      props.mode === 'edit'
        ? await put(`/api/patients/${props.patient.id}`, payload)
        : await post('/api/patients', payload)

    emit('saved', { mode: props.mode, patient: response?.data || payload })
    emit('close')
  } catch (error) {
    const errorMsg =
      error.response?.data?.message ||
      (props.mode === 'edit' ? 'Error al actualizar paciente' : 'Error al crear paciente')
    const errors = error.response?.data?.errors
    let details = ''
    if (errors) {
      details = `\n${Object.values(errors).flat().join('\n')}`
    }
    toast.error(`${errorMsg}${details}`, {
      duration: 8000,
      title: props.mode === 'edit' ? '✗ Error al Actualizar Paciente' : '✗ Error al Crear Paciente'
    })
  } finally {
    submitting.value = false
  }
}
</script>
