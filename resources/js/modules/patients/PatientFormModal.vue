<template>
  <UiModal
    model-value
    :title="mode === 'edit' ? 'Editar Paciente' : 'Nuevo Paciente'"
    size="xl"
    @close="emit('close')"
  >
    <form data-patient-form class="space-y-4" novalidate @submit.prevent="submitPatient">
      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <UiInput
          v-model="form.first_name"
          data-patient-field="first_name"
          :error="errors.first_name"
          label="Nombre"
          placeholder="Ingresa el nombre"
          required
        />
        <UiInput
          v-model="form.last_name"
          data-patient-field="last_name"
          :error="errors.last_name"
          label="Apellido"
          placeholder="Ingresa el apellido"
          required
        />
      </div>
      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <UiInput
          v-model="form.document_number"
          data-patient-field="document_number"
          :error="errors.document_number"
          label="DNI"
          placeholder="Ingresa el DNI"
        />
        <UiInput
          v-model="form.email"
          data-patient-field="email"
          :error="errors.email"
          label="Email"
          type="email"
          placeholder="correo@ejemplo.com"
        />
      </div>
      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <UiInput
          v-model="form.phone"
          data-patient-field="phone"
          :error="errors.phone"
          label="Teléfono"
          placeholder="+51 999 999 999"
        />
        <UiInput
          v-model="form.birth_date"
          data-patient-field="birth_date"
          :error="errors.birth_date"
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
            :error="errors.gender"
            placeholder="Seleccionar"
            class="w-full"
          />
          <p v-if="errors.gender" class="mt-1 text-sm text-error-500">{{ errors.gender }}</p>
        </div>
        <div data-patient-field="is_active">
          <label class="block text-sm font-medium text-theme-primary mb-1">Estado</label>
          <UiSelect
            v-model="form.is_active"
            :options="statusOptions"
            :error="errors.is_active"
            placeholder="Seleccionar"
            class="w-full"
          />
          <p v-if="errors.is_active" class="mt-1 text-sm text-error-500">{{ errors.is_active }}</p>
        </div>
      </div>
      <div data-patient-field="branch_id">
        <label class="block text-sm font-medium text-theme-primary mb-1">Sucursal</label>
        <UiSelect
          v-model="form.branch_id"
          :options="branchOptions"
          :error="errors.branch_id"
          placeholder="Sin sucursal"
          class="w-full"
        />
        <p v-if="errors.branch_id" class="mt-1 text-sm text-error-500">{{ errors.branch_id }}</p>
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
          :error="errors.emergency_contact_name"
          label="Contacto de Emergencia"
          placeholder="Nombre del contacto"
        />
        <UiInput
          v-model="form.emergency_contact_phone"
          data-patient-field="emergency_contact_phone"
          :error="errors.emergency_contact_phone"
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
          :error="Boolean(errors.medical_history)"
          class="w-full"
        />
        <p v-if="errors.medical_history" class="mt-1 text-sm text-error-500">
          {{ errors.medical_history }}
        </p>
      </div>
      <div data-patient-field="allergies">
        <label class="block text-sm font-medium text-theme-primary mb-1">Alergias</label>
        <UiTextarea
          v-model="form.allergies"
          placeholder="Lista de alergias conocidas"
          :rows="3"
          :error="Boolean(errors.allergies)"
          class="w-full"
        />
        <p v-if="errors.allergies" class="mt-1 text-sm text-error-500">{{ errors.allergies }}</p>
      </div>
      <div data-patient-field="notes">
        <label class="block text-sm font-medium text-theme-primary mb-1">Notas</label>
        <UiTextarea
          v-model="form.notes"
          placeholder="Notas adicionales"
          :rows="3"
          :error="Boolean(errors.notes)"
          class="w-full"
        />
        <p v-if="errors.notes" class="mt-1 text-sm text-error-500">{{ errors.notes }}</p>
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
import { ref, watch, onMounted } from 'vue'
import { useApi } from '../../composables/useApi'
import { useToast } from '../../composables/useToast'
import { mapFieldErrors, pickValidationMessage } from '../../composables/validationErrors'
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
const { get } = useApi()
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

const branchOptions = ref([{ value: '', label: 'Sin sucursal' }])

const loadBranches = async () => {
  try {
    const response = await get('/api/branches/active')
    const branches = (response?.data || []).map(branch => ({
      value: branch.id,
      label: branch.name
    }))
    branchOptions.value = [{ value: '', label: 'Sin sucursal' }, ...branches]
  } catch {
    branchOptions.value = [{ value: '', label: 'Sin sucursal' }]
  }
}

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
  is_active: true,
  branch_id: ''
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
  is_active: patient.is_active !== undefined ? patient.is_active : true,
  branch_id: patient.branch_id ?? ''
})

const trimStringFields = formValues => {
  const payload = { ...formValues }

  Object.keys(payload).forEach(key => {
    if (typeof payload[key] === 'string') {
      payload[key] = payload[key].trim()
    }
  })

  payload.branch_id =
    payload.branch_id === '' || payload.branch_id === null || payload.branch_id === undefined
      ? null
      : Number(payload.branch_id)

  return payload
}

const form = ref(emptyForm())
const submitting = ref(false)
const errors = ref({})

// #403: every field the API validates declares itself here so a 422 lands on
// its input instead of only on a toast.
const FORM_FIELDS = [
  'first_name',
  'last_name',
  'document_number',
  'email',
  'phone',
  'birth_date',
  'gender',
  'address',
  'emergency_contact_name',
  'emergency_contact_phone',
  'medical_history',
  'allergies',
  'notes',
  'is_active',
  'branch_id'
]

const errorValue = field => String(form.value[field] ?? '')
const errorSnapshot = ref({})

const setErrors = next => {
  errors.value = next
  errorSnapshot.value = Object.fromEntries(
    Object.keys(next).map(field => [field, errorValue(field)])
  )
}

watch(
  form,
  value => {
    for (const field of Object.keys(errors.value)) {
      if (String(value[field] ?? '') !== errorSnapshot.value[field]) delete errors.value[field]
    }
  },
  { deep: true }
)

const validateRequired = () => {
  const next = {}

  if (!String(form.value.first_name ?? '').trim()) next.first_name = 'El nombre es obligatorio.'
  if (!String(form.value.last_name ?? '').trim()) next.last_name = 'El apellido es obligatorio.'

  setErrors(next)
  return Object.keys(next).length === 0
}

onMounted(loadBranches)

watch(
  () => props.patient,
  patient => {
    form.value = patient ? patientToForm(patient) : emptyForm()
    errors.value = {}
    errorSnapshot.value = {}
  },
  { immediate: true }
)

const submitPatient = async () => {
  if (!validateRequired()) {
    toast.warning('Revisa los campos marcados antes de guardar.', {
      title: 'Datos incompletos'
    })
    return
  }

  submitting.value = true
  try {
    const payload = trimStringFields(form.value)
    const response =
      props.mode === 'edit'
        ? await put(`/api/patients/${props.patient.id}`, payload)
        : await post('/api/patients', payload)

    setErrors({})
    emit('saved', { mode: props.mode, patient: response?.data || payload })
    emit('close')
  } catch (error) {
    const body = error.response?.data
    const fieldErrors = mapFieldErrors(body?.errors, FORM_FIELDS)
    setErrors(fieldErrors)

    const errorMsg = pickValidationMessage(
      body,
      props.mode === 'edit' ? 'Error al actualizar paciente' : 'Error al crear paciente'
    )

    toast.error(errorMsg, {
      duration: 8000,
      title: props.mode === 'edit' ? '✗ Error al Actualizar Paciente' : '✗ Error al Crear Paciente'
    })
  } finally {
    submitting.value = false
  }
}
</script>
