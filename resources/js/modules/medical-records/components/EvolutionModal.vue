<template>
  <UiModal model-value title="Nueva evolución" size="xl" @close="emit('close')">
    <form data-evolution-form class="space-y-5" @submit.prevent="submitEvolution">
      <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <UiInput
          v-model="form.evolution_date"
          data-evolution-field="evolution_date"
          label="Fecha de la evolución"
          type="date"
          :max="today"
          required
          :error="fieldError('evolution_date')"
        />

        <UiSelect
          v-model="form.specialty"
          data-evolution-field="specialty"
          label="Especialidad"
          :options="specialtyOptions"
          :hint="specialtyHint"
          :error="fieldError('specialty')"
        />

        <div
          data-evolution-field="requires_follow_up"
          class="flex items-start gap-3 rounded-ios border border-[color:var(--color-hairline)] p-4"
        >
          <input
            id="evolution-requires-follow-up"
            v-model="form.requires_follow_up"
            data-evolution-follow-up-toggle
            type="checkbox"
            class="mt-0.5 h-4 w-4 rounded border-[color:var(--color-hairline)] text-systemBlue-600"
          />
          <label for="evolution-requires-follow-up" class="text-sm font-medium text-theme-primary">
            Requiere seguimiento
            <span class="mt-1 block text-xs font-normal text-theme-secondary">
              Agenda una fecha de control posterior a esta evolución.
            </span>
          </label>
        </div>
      </div>

      <UiInput
        v-model="form.follow_up_date"
        data-evolution-field="follow_up_date"
        label="Fecha de seguimiento"
        type="date"
        :min="followUpMin"
        :disabled="!form.requires_follow_up"
        :hint="
          form.requires_follow_up
            ? 'Debe ser posterior a la fecha de la evolución.'
            : 'Activa «Requiere seguimiento» para elegir la fecha.'
        "
        :error="fieldError('follow_up_date')"
      />

      <fieldset data-evolution-section="soap" class="space-y-3">
        <legend class="text-sm font-semibold text-label">Registro SOAP</legend>
        <p
          v-if="contentError"
          role="alert"
          data-evolution-error
          class="rounded-[var(--radius-panel)] p-3 text-sm text-systemRed-700 bg-systemRed-50"
          style="border: 1px solid var(--color-hairline)"
        >
          {{ contentError }}
        </p>
        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
          <div data-evolution-field="subjective">
            <label
              for="evolution-subjective"
              class="mb-2 block text-sm font-medium text-theme-primary"
            >
              Subjetivo
            </label>
            <UiTextarea
              id="evolution-subjective"
              v-model="form.subjective"
              :rows="3"
              placeholder="Relato del paciente"
            />
            <p v-if="fieldError('subjective')" class="mt-1 text-xs text-error-500">
              {{ fieldError('subjective') }}
            </p>
          </div>

          <div data-evolution-field="objective">
            <label
              for="evolution-objective"
              class="mb-2 block text-sm font-medium text-theme-primary"
            >
              Objetivo
            </label>
            <UiTextarea
              id="evolution-objective"
              v-model="form.objective"
              :rows="3"
              placeholder="Hallazgos del examen clínico"
            />
            <p v-if="fieldError('objective')" class="mt-1 text-xs text-error-500">
              {{ fieldError('objective') }}
            </p>
          </div>

          <div data-evolution-field="assessment">
            <label
              for="evolution-assessment"
              class="mb-2 block text-sm font-medium text-theme-primary"
            >
              Evaluación
            </label>
            <UiTextarea
              id="evolution-assessment"
              v-model="form.assessment"
              :rows="3"
              placeholder="Diagnóstico o impresión clínica"
            />
            <p v-if="fieldError('assessment')" class="mt-1 text-xs text-error-500">
              {{ fieldError('assessment') }}
            </p>
          </div>

          <div data-evolution-field="plan">
            <label for="evolution-plan" class="mb-2 block text-sm font-medium text-theme-primary">
              Plan
            </label>
            <UiTextarea
              id="evolution-plan"
              v-model="form.plan"
              :rows="3"
              placeholder="Conducta y próximos pasos"
            />
            <p v-if="fieldError('plan')" class="mt-1 text-xs text-error-500">
              {{ fieldError('plan') }}
            </p>
          </div>
        </div>
      </fieldset>

      <fieldset data-evolution-section="clinical-detail" class="space-y-4">
        <legend class="text-sm font-semibold text-label">Detalle de la sesión</legend>

        <div data-evolution-field="procedures_performed">
          <label
            for="evolution-procedures"
            class="mb-2 block text-sm font-medium text-theme-primary"
          >
            Procedimientos realizados
          </label>
          <UiTextarea
            id="evolution-procedures"
            v-model="form.procedures_performed"
            :rows="2"
            placeholder="Procedimientos ejecutados en esta consulta"
          />
          <p v-if="fieldError('procedures_performed')" class="mt-1 text-xs text-error-500">
            {{ fieldError('procedures_performed') }}
          </p>
        </div>

        <div data-evolution-field="materials_used">
          <label
            for="evolution-materials"
            class="mb-2 block text-sm font-medium text-theme-primary"
          >
            Materiales utilizados
          </label>
          <UiTextarea
            id="evolution-materials"
            v-model="form.materials_used"
            :rows="2"
            placeholder="Materiales e insumos empleados"
          />
          <p v-if="fieldError('materials_used')" class="mt-1 text-xs text-error-500">
            {{ fieldError('materials_used') }}
          </p>
        </div>

        <div data-evolution-field="prescriptions">
          <label
            for="evolution-prescriptions"
            class="mb-2 block text-sm font-medium text-theme-primary"
          >
            Prescripciones
          </label>
          <UiTextarea
            id="evolution-prescriptions"
            v-model="form.prescriptions"
            :rows="2"
            placeholder="Medicamentos y dosis indicadas"
          />
          <p v-if="fieldError('prescriptions')" class="mt-1 text-xs text-error-500">
            {{ fieldError('prescriptions') }}
          </p>
        </div>

        <div data-evolution-field="recommendations">
          <label
            for="evolution-recommendations"
            class="mb-2 block text-sm font-medium text-theme-primary"
          >
            Recomendaciones
          </label>
          <UiTextarea
            id="evolution-recommendations"
            v-model="form.recommendations"
            :rows="2"
            placeholder="Indicaciones para el paciente"
          />
          <p v-if="fieldError('recommendations')" class="mt-1 text-xs text-error-500">
            {{ fieldError('recommendations') }}
          </p>
        </div>

        <div data-evolution-field="next_appointment_notes">
          <label
            for="evolution-next-notes"
            class="mb-2 block text-sm font-medium text-theme-primary"
          >
            Notas para la próxima cita
          </label>
          <UiTextarea
            id="evolution-next-notes"
            v-model="form.next_appointment_notes"
            :rows="2"
            placeholder="Pendientes y avisos para la siguiente sesión"
          />
          <p v-if="fieldError('next_appointment_notes')" class="mt-1 text-xs text-error-500">
            {{ fieldError('next_appointment_notes') }}
          </p>
        </div>
      </fieldset>

      <fieldset
        data-evolution-section="vital-signs"
        class="space-y-3 rounded-ios border border-[color:var(--color-hairline)] p-4"
      >
        <legend class="px-1 text-sm font-semibold text-label">Signos vitales</legend>
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
          <UiInput
            v-model="form.vital_signs.blood_pressure"
            data-evolution-field="blood_pressure"
            label="Presión arterial"
            placeholder="120/80"
            :error="fieldError('vital_signs.blood_pressure')"
          />
          <UiInput
            v-model="form.vital_signs.heart_rate"
            data-evolution-field="heart_rate"
            label="Frecuencia cardíaca"
            type="number"
            min="30"
            max="200"
            placeholder="72"
            hint="bpm"
            :error="fieldError('vital_signs.heart_rate')"
          />
          <UiInput
            v-model="form.vital_signs.temperature"
            data-evolution-field="temperature"
            label="Temperatura"
            type="number"
            step="0.1"
            min="30"
            max="45"
            placeholder="36.5"
            hint="°C"
            :error="fieldError('vital_signs.temperature')"
          />
          <UiInput
            v-model="form.vital_signs.respiratory_rate"
            data-evolution-field="respiratory_rate"
            label="Frecuencia respiratoria"
            type="number"
            min="8"
            max="40"
            placeholder="18"
            hint="rpm"
            :error="fieldError('vital_signs.respiratory_rate')"
          />
        </div>
      </fieldset>

      <fieldset
        data-evolution-section="clinical-measurements"
        class="space-y-4 rounded-ios border border-[color:var(--color-hairline)] p-4"
      >
        <legend class="px-1 text-sm font-semibold text-label">Mediciones clínicas</legend>
        <p class="text-xs text-theme-secondary">
          Opcionales. La profundidad y la recesión se registran en milímetros; el índice de placa,
          en porcentaje; la movilidad, en grados de Miller (0 a 3).
        </p>
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
          <UiInput
            v-model="form.clinical_measurements.pocket_depth_mm"
            data-evolution-field="pocket_depth_mm"
            label="Profundidad de sondaje"
            type="number"
            step="0.1"
            min="0"
            max="20"
            placeholder="3.5"
            hint="mm"
            :error="fieldError('clinical_measurements.pocket_depth_mm')"
          />
          <UiInput
            v-model="form.clinical_measurements.gingival_recession_mm"
            data-evolution-field="gingival_recession_mm"
            label="Recesión gingival"
            type="number"
            step="0.1"
            min="0"
            max="20"
            placeholder="1.0"
            hint="mm"
            :error="fieldError('clinical_measurements.gingival_recession_mm')"
          />
          <UiInput
            v-model="form.clinical_measurements.plaque_index_percent"
            data-evolution-field="plaque_index_percent"
            label="Índice de placa"
            type="number"
            min="0"
            max="100"
            placeholder="35"
            hint="%"
            :error="fieldError('clinical_measurements.plaque_index_percent')"
          />
          <UiInput
            v-model="form.clinical_measurements.tooth_mobility"
            data-evolution-field="tooth_mobility"
            label="Movilidad dental"
            type="number"
            min="0"
            max="3"
            placeholder="0"
            hint="Grado de Miller (0 a 3)"
            :error="fieldError('clinical_measurements.tooth_mobility')"
          />
          <UiSelect
            v-model="form.clinical_measurements.bleeding_on_probing"
            data-evolution-field="bleeding_on_probing"
            label="Sangrado al sondaje"
            :options="bleedingOptions"
            :error="fieldError('clinical_measurements.bleeding_on_probing')"
          />
        </div>
      </fieldset>

      <div
        class="flex flex-wrap items-center justify-end gap-3 border-t border-[color:var(--color-hairline)] pt-4"
      >
        <UiButton type="button" variant="secondary" :disabled="submitting" @click="emit('close')">
          Cancelar
        </UiButton>
        <UiButton type="submit" :loading="submitting" data-evolution-submit>
          Guardar evolución
        </UiButton>
      </div>
    </form>
  </UiModal>
</template>

<script setup>
import { computed, ref, watch } from 'vue'
import { useMedicalRecords } from '@/composables/useMedicalRecords'
import { useToast } from '@/composables/useToast'
import UiButton from '@/components/ui/Button.vue'
import UiInput from '@/components/ui/Input.vue'
import UiModal from '@/components/ui/Modal.vue'
import UiSelect from '@/components/ui/Select.vue'
import UiTextarea from '@/components/ui/UiTextarea.vue'

const props = defineProps({
  record: {
    type: Object,
    required: true
  },
  specialties: {
    type: Array,
    default: () => []
  }
})

const emit = defineEmits(['close', 'saved'])

const { addEvolution } = useMedicalRecords()
const toast = useToast()

const pad = value => String(value).padStart(2, '0')

const toIsoDate = date => `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`

const today = toIsoDate(new Date())

const addDays = (isoDate, days) => {
  const [year, month, day] = isoDate.split('-').map(Number)
  const base = new Date(year, month - 1, day)

  return toIsoDate(new Date(base.getFullYear(), base.getMonth(), base.getDate() + days))
}

// Tri-state on purpose: an unticked checkbox cannot tell "not recorded" from
// "no bleeding", and both are different clinical findings.
const bleedingOptions = [
  { value: '', label: 'Sin registrar' },
  { value: 'true', label: 'Sí' },
  { value: 'false', label: 'No' }
]

const emptyForm = () => ({
  evolution_date: today,
  specialty: '',
  subjective: '',
  objective: '',
  assessment: '',
  plan: '',
  procedures_performed: '',
  materials_used: '',
  prescriptions: '',
  recommendations: '',
  next_appointment_notes: '',
  requires_follow_up: false,
  follow_up_date: '',
  vital_signs: {
    blood_pressure: '',
    heart_rate: '',
    temperature: '',
    respiratory_rate: ''
  },
  clinical_measurements: {
    pocket_depth_mm: '',
    gingival_recession_mm: '',
    plaque_index_percent: '',
    tooth_mobility: '',
    bleeding_on_probing: ''
  }
})

const form = ref(emptyForm())
const errors = ref({})
const contentError = ref('')
const submitting = ref(false)

watch(
  () => form.value.requires_follow_up,
  enabled => {
    if (!enabled) form.value.follow_up_date = ''
  }
)

const followUpMin = computed(() => addDays(form.value.evolution_date || today, 1))

// The stored code travels as-is: the catalog labels it when it is there and
// an unmapped historical value keeps its own entry instead of being dropped.
const specialtyOptions = computed(() => {
  const options = [
    { value: '', label: 'Automática (según el usuario)' },
    ...props.specialties.map(specialty => ({ value: specialty.code, label: specialty.name }))
  ]
  const current = form.value.specialty

  if (current && !options.some(option => option.value === current)) {
    options.splice(1, 0, { value: current, label: `${current} (actual)` })
  }

  return options
})

const specialtyHint = computed(() =>
  props.specialties.length === 0
    ? 'El catálogo no está disponible; la especialidad se asignará según el usuario.'
    : 'Sólo se aceptan especialidades del catálogo activo.'
)

const fieldError = key => {
  const messages = errors.value?.[key]

  if (!messages) return ''

  return Array.isArray(messages) ? messages[0] || '' : String(messages)
}

const text = value => {
  const trimmed = typeof value === 'string' ? value.trim() : ''

  return trimmed === '' ? null : trimmed
}

// #425: the API refuses an act with no clinical content, so the form refuses
// it first and paints the same reason. These are the blocks that document the
// visit; `specialty`, the dates and the follow-up flag travel with the act but
// do not document it.
const CONTENT_FIELDS = [
  'subjective',
  'objective',
  'assessment',
  'plan',
  'procedures_performed',
  'materials_used',
  'prescriptions',
  'recommendations',
  'next_appointment_notes'
]

const CONTENT_MESSAGE =
  'La evolución debe registrar al menos un bloque SOAP, una indicación o una medición.'

const hasClinicalContent = payload =>
  CONTENT_FIELDS.some(field => Boolean(payload[field])) ||
  payload.vital_signs !== null ||
  payload.clinical_measurements !== null

const numericSign = value => {
  if (value === '' || value === null || value === undefined) return null

  const parsed = Number(value)

  return Number.isFinite(parsed) ? parsed : null
}

const buildVitalSigns = () => {
  const signs = form.value.vital_signs
  const payload = {}
  const bloodPressure = text(signs.blood_pressure)
  const heartRate = numericSign(signs.heart_rate)
  const temperature = numericSign(signs.temperature)
  const respiratoryRate = numericSign(signs.respiratory_rate)

  if (bloodPressure) payload.blood_pressure = bloodPressure
  if (heartRate !== null) payload.heart_rate = heartRate
  if (temperature !== null) payload.temperature = temperature
  if (respiratoryRate !== null) payload.respiratory_rate = respiratoryRate

  return Object.keys(payload).length > 0 ? payload : null
}

const buildClinicalMeasurements = () => {
  const measurements = form.value.clinical_measurements
  const payload = {}
  const pocketDepth = numericSign(measurements.pocket_depth_mm)
  const gingivalRecession = numericSign(measurements.gingival_recession_mm)
  const plaqueIndex = numericSign(measurements.plaque_index_percent)
  const toothMobility = numericSign(measurements.tooth_mobility)

  if (pocketDepth !== null) payload.pocket_depth_mm = pocketDepth
  if (gingivalRecession !== null) payload.gingival_recession_mm = gingivalRecession
  if (plaqueIndex !== null) payload.plaque_index_percent = plaqueIndex
  if (toothMobility !== null) payload.tooth_mobility = toothMobility
  if (measurements.bleeding_on_probing !== '') {
    payload.bleeding_on_probing = measurements.bleeding_on_probing === 'true'
  }

  return Object.keys(payload).length > 0 ? payload : null
}

const buildPayload = () => {
  const payload = {
    evolution_date: form.value.evolution_date || today,
    subjective: text(form.value.subjective),
    objective: text(form.value.objective),
    assessment: text(form.value.assessment),
    plan: text(form.value.plan),
    procedures_performed: text(form.value.procedures_performed),
    materials_used: text(form.value.materials_used),
    prescriptions: text(form.value.prescriptions),
    recommendations: text(form.value.recommendations),
    next_appointment_notes: text(form.value.next_appointment_notes),
    requires_follow_up: form.value.requires_follow_up,
    follow_up_date: form.value.requires_follow_up ? form.value.follow_up_date || null : null,
    vital_signs: buildVitalSigns(),
    clinical_measurements: buildClinicalMeasurements()
  }

  // Only a catalog code travels: an empty select means "the service derives it
  // from the user", and a value the catalog does not know would be rejected.
  const specialty = text(form.value.specialty)

  if (specialty && props.specialties.some(entry => entry.code === specialty)) {
    payload.specialty = specialty
  }

  return payload
}

const submitEvolution = async () => {
  if (submitting.value) return

  const payload = buildPayload()

  if (!hasClinicalContent(payload)) {
    contentError.value = CONTENT_MESSAGE

    return
  }

  submitting.value = true
  contentError.value = ''
  errors.value = {}

  try {
    await addEvolution(props.record.id, payload)
    toast.success('Evolución registrada')

    emit('saved')
  } catch (err) {
    const serverErrors = err?.response?.data?.errors

    if (serverErrors) {
      errors.value = serverErrors
      contentError.value = fieldError('content')
    }

    toast.error(err?.response?.data?.message || 'No pudimos guardar la evolución')
  } finally {
    submitting.value = false
  }
}
</script>
