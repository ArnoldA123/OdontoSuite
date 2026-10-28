<template>
  <UiModal model-value :title="modalTitle" size="lg" @close="emit('close')">
    <form
      id="medical-record-form"
      novalidate
      class="space-y-6 text-theme-primary"
      data-medical-record-form
      @submit.prevent="onSubmit"
    >
      <fieldset class="space-y-3" data-form-section="patient-date">
        <legend class="text-xs font-semibold uppercase tracking-wide text-theme-secondary">
          Paciente y fecha
        </legend>

        <div data-field="patient_id">
          <div
            v-if="patientLocked"
            class="rounded-ios px-3 py-2"
            style="border: 1px solid var(--color-hairline); background: var(--color-canvas)"
            data-selected-patient
          >
            <p class="text-xs text-theme-secondary">Paciente</p>
            <p class="text-sm font-semibold text-label">{{ selectedPatientLabel }}</p>
            <p v-if="selectedPatientDocument" class="text-xs text-theme-secondary tabular-nums">
              {{ selectedPatientDocument }}
            </p>
          </div>
          <PatientSelector v-else v-model="selectedPatient" @patient-selected="onPatientSelected" />
          <p v-if="fieldError('patient_id')" class="mt-1 text-xs text-systemRed-700">
            {{ fieldError('patient_id') }}
          </p>
        </div>

        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
          <div data-field="first_visit_date">
            <UiInput
              v-model="form.first_visit_date"
              type="date"
              label="Primera visita"
              :max="today"
              :error="fieldError('first_visit_date')"
              hint="La fecha no puede ser futura."
            />
          </div>
          <div data-field="record_number">
            <UiInput
              :model-value="recordNumberLabel"
              label="Número de historia"
              readonly
              hint="El número lo genera el sistema al guardar."
            />
          </div>
        </div>

        <div data-field="chief_complaint">
          <div class="flex items-baseline justify-between gap-3">
            <label
              for="medical-record-chief-complaint"
              class="mb-2 block text-sm font-medium text-theme-primary"
            >
              Motivo de consulta
            </label>
            <span class="text-xs text-theme-secondary tabular-nums">
              {{ fieldLength('chief_complaint') }}/{{ chiefComplaintField.max }}
            </span>
          </div>
          <UiTextarea
            id="medical-record-chief-complaint"
            v-model="form.chief_complaint"
            :rows="2"
            :maxlength="chiefComplaintField.max"
            :error="!!fieldError('chief_complaint')"
            placeholder="Dolor, control, urgencia..."
            aria-label="Motivo de consulta"
          />
          <p v-if="fieldError('chief_complaint')" class="mt-1 text-xs text-systemRed-700">
            {{ fieldError('chief_complaint') }}
          </p>
        </div>
      </fieldset>

      <fieldset class="space-y-3" data-form-section="history">
        <legend class="text-xs font-semibold uppercase tracking-wide text-theme-secondary">
          Antecedentes
        </legend>
        <div
          v-for="field in antecedentFields"
          :key="field.key"
          :data-field="field.key"
          class="space-y-1"
        >
          <div class="flex items-baseline justify-between gap-3">
            <label
              :for="`medical-record-${field.key}`"
              class="mb-2 block text-sm font-medium text-theme-primary"
            >
              {{ field.label }}
            </label>
            <span class="text-xs text-theme-secondary tabular-nums">
              {{ fieldLength(field.key) }}/{{ field.max }}
            </span>
          </div>
          <UiTextarea
            :id="`medical-record-${field.key}`"
            v-model="form[field.key]"
            :rows="field.rows"
            :maxlength="field.max"
            :error="!!fieldError(field.key)"
            :placeholder="field.placeholder"
            :aria-label="field.label"
          />
          <p v-if="fieldError(field.key)" class="text-xs text-systemRed-700">
            {{ fieldError(field.key) }}
          </p>
        </div>
      </fieldset>

      <fieldset class="space-y-3" data-form-section="examination">
        <legend class="text-xs font-semibold uppercase tracking-wide text-theme-secondary">
          Examen y diagnóstico
        </legend>

        <div class="space-y-2" data-field-group="vital-signs">
          <p class="text-sm font-medium text-theme-primary">Signos vitales</p>
          <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div v-for="field in vitalSignFields" :key="field.key" :data-field="field.key">
              <UiInput
                v-model="form.vital_signs[field.key]"
                :type="field.type"
                :label="field.label"
                :placeholder="field.placeholder"
                :min="field.min"
                :max="field.max"
                :step="field.step"
                :error="fieldError(`vital_signs.${field.key}`)"
              />
              <p v-if="field.suffix" class="text-xs text-theme-secondary">{{ field.suffix }}</p>
            </div>
          </div>
        </div>

        <div
          v-for="field in examinationFields"
          :key="field.key"
          :data-field="field.key"
          class="space-y-1"
        >
          <div class="flex items-baseline justify-between gap-3">
            <label
              :for="`medical-record-${field.key}`"
              class="mb-2 block text-sm font-medium text-theme-primary"
            >
              {{ field.label }}
            </label>
            <span class="text-xs text-theme-secondary tabular-nums">
              {{ fieldLength(field.key) }}/{{ field.max }}
            </span>
          </div>
          <UiTextarea
            :id="`medical-record-${field.key}`"
            v-model="form[field.key]"
            :rows="field.rows"
            :maxlength="field.max"
            :error="!!fieldError(field.key)"
            :placeholder="field.placeholder"
            :aria-label="field.label"
          />
          <p v-if="fieldError(field.key)" class="text-xs text-systemRed-700">
            {{ fieldError(field.key) }}
          </p>
        </div>
      </fieldset>

      <fieldset class="space-y-3" data-form-section="notes">
        <legend class="text-xs font-semibold uppercase tracking-wide text-theme-secondary">
          Notas
        </legend>
        <div v-for="field in noteFields" :key="field.key" :data-field="field.key" class="space-y-1">
          <div class="flex items-baseline justify-between gap-3">
            <label
              :for="`medical-record-${field.key}`"
              class="mb-2 block text-sm font-medium text-theme-primary"
            >
              {{ field.label }}
            </label>
            <span class="text-xs text-theme-secondary tabular-nums">
              {{ fieldLength(field.key) }}/{{ field.max }}
            </span>
          </div>
          <UiTextarea
            :id="`medical-record-${field.key}`"
            v-model="form[field.key]"
            :rows="field.rows"
            :maxlength="field.max"
            :error="!!fieldError(field.key)"
            :placeholder="field.placeholder"
            :aria-label="field.label"
          />
          <p v-if="fieldError(field.key)" class="text-xs text-systemRed-700">
            {{ fieldError(field.key) }}
          </p>
        </div>
      </fieldset>

      <p
        v-if="generalError"
        role="alert"
        data-modal-error
        class="rounded-[var(--radius-panel)] p-3 text-sm text-systemRed-700 bg-systemRed-50"
        style="border: 1px solid var(--color-hairline)"
      >
        {{ generalError }}
      </p>
    </form>

    <template #footer>
      <UiButton
        type="button"
        variant="secondary"
        :disabled="saving"
        data-modal-cancel
        @click="emit('close')"
      >
        Cancelar
      </UiButton>
      <UiButton type="button" :loading="saving" data-modal-submit @click="onSubmit">
        Guardar historia
      </UiButton>
    </template>
  </UiModal>
</template>

<script setup>
import { computed, ref } from 'vue'
import UiModal from '@/components/ui/Modal.vue'
import UiButton from '@/components/ui/Button.vue'
import UiInput from '@/components/ui/Input.vue'
import UiTextarea from '@/components/ui/UiTextarea.vue'
import PatientSelector from '@/components/ui/PatientSelector.vue'
import { useMedicalRecords } from '@/composables/useMedicalRecords'
import { useToast } from '@/composables/useToast'

const props = defineProps({
  patient: {
    type: Object,
    default: null
  }
})

const emit = defineEmits(['close', 'saved'])

const { createRecord, loading } = useMedicalRecords()
const toast = useToast()

const chiefComplaintField = {
  key: 'chief_complaint',
  max: 1000
}

const antecedentFields = [
  {
    key: 'medical_history',
    label: 'Antecedentes médicos',
    max: 2000,
    rows: 3,
    placeholder: 'Enfermedades previas, hospitalizaciones, cirugías'
  },
  {
    key: 'dental_history',
    label: 'Antecedentes dentales',
    max: 2000,
    rows: 3,
    placeholder: 'Tratamientos odontológicos previos'
  },
  {
    key: 'allergies',
    label: 'Alergias',
    max: 1000,
    rows: 2,
    placeholder: 'Medicamentos, materiales, látex'
  },
  {
    key: 'medications',
    label: 'Medicamentos',
    max: 1000,
    rows: 2,
    placeholder: 'Fármacos de uso actual'
  },
  {
    key: 'systemic_conditions',
    label: 'Condiciones sistémicas',
    max: 1000,
    rows: 2,
    placeholder: 'Diabetes, hipertensión, anticoagulación'
  },
  {
    key: 'family_history',
    label: 'Antecedentes familiares',
    max: 1000,
    rows: 2,
    placeholder: 'Enfermedades hereditarias relevantes'
  },
  {
    key: 'social_history',
    label: 'Antecedentes sociales',
    max: 1000,
    rows: 2,
    placeholder: 'Hábitos, ocupación, tabaquismo'
  }
]

const examinationFields = [
  {
    key: 'clinical_examination',
    label: 'Examen clínico',
    max: 2000,
    rows: 3,
    placeholder: 'Hallazgos extraorales e intraorales'
  },
  {
    key: 'diagnosis',
    label: 'Diagnóstico',
    max: 1000,
    rows: 2,
    placeholder: 'Diagnóstico presuntivo o definitivo'
  },
  {
    key: 'treatment_plan',
    label: 'Plan de tratamiento',
    max: 2000,
    rows: 3,
    placeholder: 'Secuencia de tratamiento acordada'
  }
]

const noteFields = [
  {
    key: 'notes',
    label: 'Notas',
    max: 1000,
    rows: 3,
    placeholder: 'Indicaciones y observaciones del equipo'
  }
]

const vitalSignFields = [
  { key: 'blood_pressure', label: 'Presión arterial', type: 'text', placeholder: '120/80' },
  {
    key: 'heart_rate',
    label: 'Frecuencia cardíaca',
    type: 'number',
    placeholder: '72',
    min: 30,
    max: 200,
    step: 1,
    suffix: 'lpm'
  },
  {
    key: 'temperature',
    label: 'Temperatura',
    type: 'number',
    placeholder: '36.5',
    min: 30,
    max: 45,
    step: 0.1,
    suffix: '°C'
  },
  {
    key: 'respiratory_rate',
    label: 'Frecuencia respiratoria',
    type: 'number',
    placeholder: '16',
    min: 8,
    max: 40,
    step: 1,
    suffix: 'rpm'
  }
]

const vitalSignRanges = [
  { key: 'heart_rate', min: 30, max: 200, message: 'Entre 30 y 200 lpm' },
  { key: 'temperature', min: 30, max: 45, message: 'Entre 30 y 45 °C' },
  { key: 'respiratory_rate', min: 8, max: 40, message: 'Entre 8 y 40 rpm' }
]

const textFields = [chiefComplaintField, ...antecedentFields, ...examinationFields, ...noteFields]

const localToday = () => {
  const now = new Date()
  const month = String(now.getMonth() + 1).padStart(2, '0')
  const day = String(now.getDate()).padStart(2, '0')

  return `${now.getFullYear()}-${month}-${day}`
}

const today = localToday()

const toDateInput = value => {
  if (!value) return ''

  const match = String(value).match(/^(\d{4}-\d{2}-\d{2})/)

  return match ? match[1] : ''
}

const text = value => (value === null || value === undefined ? '' : String(value))

const emptyVitalSigns = () => ({
  blood_pressure: '',
  heart_rate: '',
  temperature: '',
  respiratory_rate: ''
})

const toForm = record => ({
  first_visit_date: toDateInput(record?.first_visit_date),
  chief_complaint: text(record?.chief_complaint),
  medical_history: text(record?.medical_history),
  dental_history: text(record?.dental_history),
  allergies: text(record?.allergies),
  medications: text(record?.medications),
  systemic_conditions: text(record?.systemic_conditions),
  family_history: text(record?.family_history),
  social_history: text(record?.social_history),
  clinical_examination: text(record?.clinical_examination),
  diagnosis: text(record?.diagnosis),
  treatment_plan: text(record?.treatment_plan),
  notes: text(record?.notes),
  vital_signs: {
    ...emptyVitalSigns(),
    ...Object.fromEntries(
      Object.entries(record?.vital_signs || {}).map(([key, value]) => [key, text(value)])
    )
  }
})

const form = ref(toForm())
const selectedPatient = ref(props.patient || null)
const errors = ref({})
const generalError = ref('')
const saving = ref(false)

const modalTitle = computed(() => 'Nueva historia clínica')

// A create opened from the workspace already carries the selected patient and
// does not ask for it again.
const patientLocked = computed(() => !!props.patient)

const patientId = computed(() => selectedPatient.value?.id ?? props.patient?.id ?? null)

const selectedPatientLabel = computed(() => {
  const patient = selectedPatient.value

  if (!patient) return 'Sin paciente'

  return `${patient.first_name ?? ''} ${patient.last_name ?? ''}`.trim()
})

const selectedPatientDocument = computed(() => {
  const document = selectedPatient.value?.document_number

  return document ? `DNI ${document}` : ''
})

const recordNumberLabel = computed(() => 'Se asignará al guardar')

const fieldError = key => errors.value[key] || ''
const fieldLength = key => text(form.value[key]).trim().length

const onPatientSelected = patient => {
  selectedPatient.value = patient

  if (patient?.id) {
    const next = { ...errors.value }
    delete next.patient_id
    errors.value = next
  }
}

const validate = () => {
  const next = {}

  if (!patientId.value) {
    next.patient_id = 'Selecciona un paciente'
  }

  if (form.value.first_visit_date && form.value.first_visit_date > today) {
    next.first_visit_date = 'La fecha de primera visita no puede ser futura'
  }

  textFields.forEach(field => {
    if (fieldLength(field.key) > field.max) {
      next[field.key] = `Máximo ${field.max} caracteres`
    }
  })

  if (text(form.value.vital_signs.blood_pressure).trim().length > 20) {
    next['vital_signs.blood_pressure'] = 'Máximo 20 caracteres'
  }

  vitalSignRanges.forEach(field => {
    const raw = form.value.vital_signs[field.key]

    if (text(raw).trim() === '') return

    const value = Number(raw)

    if (!Number.isFinite(value) || value < field.min || value > field.max) {
      next[`vital_signs.${field.key}`] = field.message
    }
  })

  errors.value = next

  return Object.keys(next).length === 0
}

const toNumberOrNull = value => {
  if (text(value).trim() === '') return null

  const parsed = Number(value)

  return Number.isFinite(parsed) ? parsed : null
}

const buildVitalSigns = () => {
  const signs = {
    blood_pressure: text(form.value.vital_signs.blood_pressure).trim(),
    heart_rate: toNumberOrNull(form.value.vital_signs.heart_rate),
    temperature: toNumberOrNull(form.value.vital_signs.temperature),
    respiratory_rate: toNumberOrNull(form.value.vital_signs.respiratory_rate)
  }

  const filled = Object.values(signs).some(value => value !== null && value !== '')

  return filled ? signs : null
}

const trimmed = key => text(form.value[key]).trim()

const buildPayload = () => {
  const payload = {
    first_visit_date: form.value.first_visit_date || null,
    chief_complaint: trimmed('chief_complaint'),
    medical_history: trimmed('medical_history'),
    dental_history: trimmed('dental_history'),
    allergies: trimmed('allergies'),
    medications: trimmed('medications'),
    systemic_conditions: trimmed('systemic_conditions'),
    family_history: trimmed('family_history'),
    social_history: trimmed('social_history'),
    vital_signs: buildVitalSigns(),
    clinical_examination: trimmed('clinical_examination'),
    diagnosis: trimmed('diagnosis'),
    treatment_plan: trimmed('treatment_plan'),
    notes: trimmed('notes')
  }

  payload.patient_id = patientId.value

  return payload
}

const applyServerErrors = bag => {
  errors.value = Object.fromEntries(
    Object.entries(bag).map(([key, value]) => [
      key,
      Array.isArray(value) ? value[0] : String(value)
    ])
  )
}

const onSubmit = async () => {
  if (saving.value || loading.value) return

  generalError.value = ''

  if (!validate()) return

  saving.value = true

  try {
    const saved = await createRecord(buildPayload())

    toast.success('Historia clínica creada')
    emit('saved', saved)
  } catch (error) {
    const serverErrors = error?.response?.data?.errors

    if (serverErrors) {
      applyServerErrors(serverErrors)
    } else {
      generalError.value =
        error?.response?.data?.message || 'No se pudo guardar la historia clínica.'
    }
  } finally {
    saving.value = false
  }
}
</script>
