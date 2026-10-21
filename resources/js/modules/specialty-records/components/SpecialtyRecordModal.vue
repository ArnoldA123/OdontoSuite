<template>
  <UiModal model-value :title="title" size="lg" @close="close">
    <div class="space-y-6 bg-canvas text-theme-primary" data-specialty-record-form>
      <fieldset class="space-y-4" data-form-section="patient-specialty">
        <legend class="text-xs font-semibold uppercase tracking-wide text-theme-secondary">
          Paciente y especialidad
        </legend>

        <div data-field="patient_id">
          <div
            v-if="lockedPatient"
            class="rounded-[var(--radius-panel)] px-3 py-2"
            style="border: 1px solid var(--color-hairline); background: var(--color-canvas)"
            data-selected-patient
          >
            <p class="text-xs text-theme-secondary">Paciente</p>
            <p class="text-sm font-semibold text-label">{{ patientName }}</p>
            <p v-if="patientMeta" class="text-xs text-theme-secondary tabular-nums">
              {{ patientMeta }}
            </p>
          </div>
          <PatientSelector v-else v-model="selectedPatient" @patient-selected="onPatientSelected" />
          <p v-if="errors.patient_id" class="mt-1 text-xs text-systemRed-700">
            {{ errors.patient_id }}
          </p>
        </div>

        <div data-field="specialty">
          <UiSelect
            v-model="form.specialty"
            :options="specialtyOptions"
            label="Especialidad"
            placeholder="Selecciona una especialidad"
            :disabled="isEdit"
            :error="errors.specialty"
            data-specialty-select
            @change="onSpecialtyChange"
          />
          <p v-if="isEdit" class="mt-1 text-xs text-theme-secondary">
            La especialidad no puede cambiarse en un registro existente.
          </p>
        </div>
      </fieldset>

      <fieldset v-if="form.specialty" class="space-y-4" data-form-section="procedure">
        <legend class="text-xs font-semibold uppercase tracking-wide text-theme-secondary">
          Procedimiento
        </legend>

        <div class="space-y-2" data-field="procedure_id">
          <div v-if="form.procedure_id" class="flex justify-end">
            <UiButton
              type="button"
              variant="ghost"
              size="xs"
              data-procedure-clear
              @click="clearProcedure"
            >
              Quitar
            </UiButton>
          </div>
          <ProcedureCatalogPicker
            v-model="form.procedure_id"
            :specialty="form.specialty"
            data-procedure-picker
          />
          <p v-if="errors.procedure_id" class="text-xs text-systemRed-700">
            {{ errors.procedure_id }}
          </p>
        </div>
      </fieldset>

      <fieldset class="space-y-4" data-form-section="clinical">
        <legend class="text-xs font-semibold uppercase tracking-wide text-theme-secondary">
          Datos clínicos
        </legend>

        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
          <div v-for="field in clinicalFields" :key="field.key" :data-field="field.key">
            <UiSelect
              v-if="field.type === 'dental-piece'"
              v-model="form[field.key]"
              :options="dentalPieceOptions"
              :label="field.label"
              placeholder="Selecciona la pieza"
              :error="errors[field.key]"
              data-dental-piece-select
            />
            <UiSelect
              v-else-if="field.type === 'select'"
              v-model="form[field.key]"
              :options="optionsFor(field)"
              :label="field.label"
              placeholder="Selecciona una opción"
              :error="errors[field.key]"
            />
            <UiInput
              v-else
              v-model="form[field.key]"
              :type="field.type"
              :label="field.label"
              :min="field.min"
              :max="field.type === 'number' ? field.max : undefined"
              :step="field.step"
              :error="errors[field.key]"
            />
          </div>
        </div>

        <p v-if="clinicalFields.length === 0" class="text-sm text-theme-secondary">
          Selecciona una especialidad para ver sus campos clínicos.
        </p>
      </fieldset>

      <fieldset class="space-y-4" data-form-section="notes">
        <legend class="text-xs font-semibold uppercase tracking-wide text-theme-secondary">
          Notas
        </legend>

        <div v-for="field in noteFields" :key="field.key" :data-field="field.key" class="space-y-1">
          <div class="flex items-baseline justify-between gap-3">
            <label class="block text-sm font-medium text-theme-primary">{{ field.label }}</label>
            <span class="text-xs text-theme-secondary tabular-nums">
              {{ fieldLength(field.key) }}/{{ field.max }}
            </span>
          </div>
          <UiTextarea
            v-model="form[field.key]"
            :rows="3"
            :error="!!errors[field.key]"
            :aria-label="field.label"
          />
          <p v-if="errors[field.key]" class="text-xs text-systemRed-700">{{ errors[field.key] }}</p>
        </div>

        <p v-if="noteFields.length === 0" class="text-sm text-theme-secondary">
          Esta especialidad no tiene notas adicionales.
        </p>
      </fieldset>

      <p
        v-if="generalError"
        role="alert"
        data-modal-error
        class="rounded-[var(--radius-panel)] bg-systemRed-50 p-3 text-sm text-systemRed-700"
        style="border: 1px solid var(--color-hairline)"
      >
        {{ generalError }}
      </p>
    </div>

    <template #footer>
      <UiButton
        type="button"
        variant="secondary"
        :disabled="saving"
        data-modal-cancel
        @click="close"
      >
        Cancelar
      </UiButton>
      <UiButton
        type="button"
        :loading="saving"
        :disabled="saving"
        data-modal-submit
        @click="onSubmit"
      >
        {{ isEdit ? 'Actualizar' : 'Crear registro' }}
      </UiButton>
    </template>
  </UiModal>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'
import { useApi } from '@/composables/useApi'
import { useAuth } from '@/composables/useAuth'
import { useErrorHandler } from '@/composables/useErrorHandler'
import { useSpecialtyRecords } from '@/composables/useSpecialtyRecords'
import { useToast } from '@/composables/useToast'
import PatientSelector from '@/components/ui/PatientSelector.vue'
import ProcedureCatalogPicker from '@/components/procedures/ProcedureCatalogPicker.vue'
import UiButton from '@/components/ui/Button.vue'
import UiInput from '@/components/ui/Input.vue'
import UiModal from '@/components/ui/Modal.vue'
import UiSelect from '@/components/ui/Select.vue'
import UiTextarea from '@/components/ui/UiTextarea.vue'
import {
  dateInputValue,
  definitionsForRole,
  formFields,
  getSpecialtyDefinition
} from '../specialtyRecordMeta'

const props = defineProps({
  record: {
    type: Object,
    default: null
  },
  patient: {
    type: Object,
    default: null
  },
  specialty: {
    type: String,
    default: ''
  },
  isEdit: {
    type: Boolean,
    default: false
  }
})

const emit = defineEmits(['close', 'saved'])

const { get } = useApi()
const { user } = useAuth()
const { handleError } = useErrorHandler()
const toast = useToast()
const { createRecord, updateRecord } = useSpecialtyRecords()

const selectedPatient = ref(props.patient ?? null)
const errors = ref({})
const generalError = ref('')
const saving = ref(false)
const dentalPieces = ref([])
const dentalPiecesError = ref(false)

const definition = computed(() => getSpecialtyDefinition(props.specialty))

const title = computed(() =>
  definition.value
    ? `${props.isEdit ? 'Editar' : 'Nuevo'} registro de ${definition.value.label}`
    : 'Registro de especialidad'
)

const specialtyOptions = computed(() =>
  definitionsForRole(user.value?.role).map(entry => ({ value: entry.key, label: entry.label }))
)

const dentalPieceOptions = computed(() =>
  dentalPieces.value.map(piece => ({
    value: String(piece.id),
    label: [piece.fdi_number ? `#${piece.fdi_number}` : '', piece.name ?? '']
      .filter(Boolean)
      .join(' · ')
  }))
)

const loadDentalPieces = async () => {
  try {
    const response = await get('/api/dental-pieces')
    dentalPieces.value = Array.isArray(response?.data) ? response.data : []
    dentalPiecesError.value = false
  } catch {
    dentalPieces.value = []
    dentalPiecesError.value = true
  }
}

const lockedPatient = computed(() => Boolean(props.patient ?? props.record?.patient))

const currentPatient = computed(
  () => selectedPatient.value ?? props.patient ?? props.record?.patient
)

const patientName = computed(() => {
  const patient = currentPatient.value

  if (!patient) return 'Sin paciente'

  return `${patient.first_name ?? ''} ${patient.last_name ?? ''}`.trim()
})

const patientMeta = computed(() => {
  const patient = currentPatient.value

  if (!patient) return ''

  const document = patient.document_number ?? patient.dni ?? ''

  return [document ? `Documento ${document}` : '', patient.phone ?? ''].filter(Boolean).join(' · ')
})

const patientId = computed(
  () => props.patient?.id ?? props.record?.patient_id ?? selectedPatient.value?.id ?? null
)

const text = value => (value === null || value === undefined ? '' : String(value).trim())

const toNumberOrNull = value => {
  const raw = text(value)

  if (raw === '') return null

  const numeric = Number(value)

  return Number.isFinite(numeric) ? numeric : null
}

const toInputValue = (field, value) => {
  if (field.type === 'procedure') return toNumberOrNull(value)
  if (value === null || value === undefined) return ''
  if (field.type === 'date') return dateInputValue(value)

  return String(value)
}

const emptyValue = field => (field.type === 'procedure' ? null : '')

const buildForm = specialty => {
  const values = Object.fromEntries(
    formFields(getSpecialtyDefinition(specialty)).map(field => [field.key, emptyValue(field)])
  )

  if (!props.isEdit) return { specialty: specialty || '', ...values }

  const filled = Object.fromEntries(
    formFields(getSpecialtyDefinition(specialty)).map(field => [
      field.key,
      toInputValue(field, props.record?.[field.key])
    ])
  )

  return { specialty: specialty || '', ...filled }
}

const form = ref(buildForm(props.specialty))

const clinicalFields = computed(() => formFields(definition.value, 'clinical'))
const noteFields = computed(() => formFields(definition.value, 'notes'))

const optionsFor = field =>
  field.required ? field.options : [{ value: '', label: 'Sin definir' }, ...field.options]

const fieldLength = key => text(form.value[key]).length

const clearProcedure = () => {
  form.value.procedure_id = null
}

const onPatientSelected = patient => {
  selectedPatient.value = patient

  if (patient?.id) {
    const next = { ...errors.value }
    delete next.patient_id
    errors.value = next
  }
}

const onSpecialtyChange = specialty => {
  form.value = { ...buildForm(specialty), specialty }
  errors.value = {}

  const needsPieces = formFields(getSpecialtyDefinition(specialty)).some(
    field => field.type === 'dental-piece'
  )

  if (needsPieces && dentalPieces.value.length === 0) loadDentalPieces()
}

const validate = () => {
  const next = {}

  if (!patientId.value) next.patient_id = 'Selecciona un paciente'
  if (!form.value.specialty) next.specialty = 'Selecciona una especialidad'

  formFields(definition.value).forEach(field => {
    const value = text(form.value[field.key])

    if (field.required && value === '') {
      next[field.key] = `${field.label} es obligatorio`
      return
    }

    if (field.type === 'number') {
      if (value === '') return

      const numeric = Number(form.value[field.key])

      if (!Number.isFinite(numeric)) {
        next[field.key] = 'Ingresa un número válido'
        return
      }

      if (field.min !== undefined && numeric < field.min) {
        next[field.key] = `El valor mínimo es ${field.min}`
        return
      }

      if (field.max !== undefined && numeric > field.max) {
        next[field.key] = `El valor máximo es ${field.max}`
      }

      return
    }

    if (field.max && value.length > field.max) {
      next[field.key] = `Máximo ${field.max} caracteres`
    }
  })

  errors.value = next

  return Object.keys(next).length === 0
}

const buildPayload = () => {
  const payload = { specialty: form.value.specialty }

  // PUT resolves the record by specialty and id; `patient_id` belongs to the
  // create contract only. The piece travels with both: the three NOT NULL
  // tables keep it, and edit can correct it.
  if (!props.isEdit) payload.patient_id = patientId.value

  for (const field of definition.value?.formFields ?? []) {
    if (field.type === 'number' || field.type === 'dental-piece' || field.type === 'procedure') {
      payload[field.key] = toNumberOrNull(form.value[field.key])
      continue
    }

    const value = text(form.value[field.key])

    // Status columns are NOT NULL with a database default: an untouched select
    // is left out so the default (create) or the stored value (update) stands.
    if (field.key === definition.value?.statusField && value === '') continue

    payload[field.key] = value === '' ? null : value
  }

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

const close = () => {
  emit('close')
}

onMounted(() => {
  const needsPieces = formFields(definition.value).some(field => field.type === 'dental-piece')

  if (needsPieces) loadDentalPieces()
})

const onSubmit = async () => {
  if (saving.value) return

  generalError.value = ''

  if (!validate()) return

  saving.value = true

  try {
    const saved = props.isEdit
      ? await updateRecord(props.record.id, buildPayload())
      : await createRecord(buildPayload())

    toast.success(props.isEdit ? 'Registro actualizado' : 'Registro creado')
    emit('saved', { record: saved, specialty: form.value.specialty })
  } catch (error) {
    const serverErrors = error?.response?.data?.errors

    if (serverErrors) {
      applyServerErrors(serverErrors)
      handleError(error, 'Revisa los campos marcados')
    } else {
      generalError.value = 'No pudimos guardar el registro. Intenta nuevamente.'
      handleError(error, 'No pudimos guardar el registro')
    }
  } finally {
    saving.value = false
  }
}
</script>
