<template>
  <UiModal
    :model-value="modelValue"
    :title="title"
    size="lg"
    data-lab-case-form
    @update:model-value="value => emit('update:modelValue', value)"
    @close="close"
  >
    <form class="space-y-4" novalidate @submit.prevent="submit">
      <!--
        A preset patient is not a choice: the case is opened from the patient's
        record or from the closure of the attention, so the form fixes the
        patient instead of offering the directory again.
      -->
      <div v-if="fixedPatient">
        <p class="block text-sm font-medium text-theme-primary mb-1">Paciente</p>
        <p
          class="rounded-ios px-3 py-2 text-sm text-theme-primary"
          style="background: var(--color-canvas); border: 1px solid var(--color-hairline)"
          data-lab-case-form-patient-fixed
        >
          {{ fixedPatientName }}
        </p>
        <p
          v-if="errors.patient_id"
          role="alert"
          class="mt-1 text-sm font-medium text-systemRed-600"
          data-lab-case-form-error="patient_id"
        >
          {{ errors.patient_id }}
        </p>
      </div>
      <div v-else>
        <PatientSelector
          :model-value="selectedPatient"
          data-lab-case-form-patient
          @update:model-value="onPatientSelected"
        />
        <p
          v-if="errors.patient_id"
          role="alert"
          class="mt-1 text-sm font-medium text-systemRed-600"
          data-lab-case-form-error="patient_id"
        >
          {{ errors.patient_id }}
        </p>
      </div>

      <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
        <div>
          <UiSelect
            v-model="form.professional_id"
            :options="professionalOptions"
            label="Profesional"
            placeholder="Sin asignar"
            searchable
            data-lab-case-form-professional
          />
          <p
            v-if="errors.professional_id"
            role="alert"
            class="mt-1 text-sm font-medium text-systemRed-600"
            data-lab-case-form-error="professional_id"
          >
            {{ errors.professional_id }}
          </p>
        </div>

        <div>
          <UiSelect
            v-model="form.technician_id"
            :options="technicianOptions"
            label="Técnico"
            placeholder="Sin asignar"
            searchable
            data-lab-case-form-technician
          />
          <p
            v-if="errors.technician_id"
            role="alert"
            class="mt-1 text-sm font-medium text-systemRed-600"
            data-lab-case-form-error="technician_id"
          >
            {{ errors.technician_id }}
          </p>
        </div>
      </div>

      <div>
        <UiSelect
          v-model="form.dental_piece_id"
          :options="pieceOptions"
          label="Pieza"
          placeholder="Sin pieza"
          searchable
          data-lab-case-form-piece
        />
        <p
          v-if="errors.dental_piece_id"
          role="alert"
          class="mt-1 text-sm font-medium text-systemRed-600"
          data-lab-case-form-error="dental_piece_id"
        >
          {{ errors.dental_piece_id }}
        </p>
      </div>

      <div>
        <UiInput
          id="lab-case-work"
          v-model="form.work"
          label="Trabajo"
          placeholder="Ej: Corona metal-cerámica"
          required
          data-lab-case-form-work
        />
        <p
          v-if="errors.work"
          role="alert"
          class="mt-1 text-sm font-medium text-systemRed-600"
          data-lab-case-form-error="work"
        >
          {{ errors.work }}
        </p>
      </div>

      <div>
        <UiInput
          id="lab-case-material"
          v-model="form.material"
          label="Material"
          placeholder="Ej: Zirconio"
          data-lab-case-form-material
        />
        <p
          v-if="errors.material"
          role="alert"
          class="mt-1 text-sm font-medium text-systemRed-600"
          data-lab-case-form-error="material"
        >
          {{ errors.material }}
        </p>
      </div>

      <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
        <div>
          <UiInput
            id="lab-case-sent-at"
            v-model="form.sent_at"
            type="date"
            label="Enviado al taller"
            required
            data-lab-case-form-sent-at
          />
          <p
            v-if="errors.sent_at"
            role="alert"
            class="mt-1 text-sm font-medium text-systemRed-600"
            data-lab-case-form-error="sent_at"
          >
            {{ errors.sent_at }}
          </p>
        </div>

        <div>
          <UiInput
            id="lab-case-promised-at"
            v-model="form.promised_at"
            type="date"
            label="Prometido"
            required
            data-lab-case-form-promised-at
          />
          <p
            v-if="errors.promised_at"
            role="alert"
            class="mt-1 text-sm font-medium text-systemRed-600"
            data-lab-case-form-error="promised_at"
          >
            {{ errors.promised_at }}
          </p>
        </div>
      </div>

      <div>
        <label for="lab-case-notes" class="block text-sm font-medium text-theme-primary mb-1">
          Notas
        </label>
        <UiTextarea
          id="lab-case-notes"
          v-model="form.notes"
          :rows="3"
          placeholder="Indicaciones del taller, color, ajustes pendientes…"
          data-lab-case-form-notes
        />
        <p
          v-if="errors.notes"
          role="alert"
          class="mt-1 text-sm font-medium text-systemRed-600"
          data-lab-case-form-error="notes"
        >
          {{ errors.notes }}
        </p>
      </div>

      <p
        v-if="errors.message"
        role="alert"
        class="rounded-ios bg-systemRed-50 p-3 text-sm font-medium text-systemRed-600"
        data-lab-case-form-error="message"
      >
        {{ errors.message }}
      </p>
    </form>

    <template #footer>
      <div class="flex justify-end gap-3">
        <UiButton variant="secondary" data-lab-case-form-cancel @click="close">Cancelar</UiButton>
        <UiButton variant="primary" :loading="saving" data-lab-case-form-submit @click="submit">
          {{ submitLabel }}
        </UiButton>
      </div>
    </template>
  </UiModal>
</template>

<script setup>
import { computed, ref, watch } from 'vue'
import UiModal from '../../components/ui/Modal.vue'
import UiButton from '../../components/ui/Button.vue'
import UiInput from '../../components/ui/Input.vue'
import UiSelect from '../../components/ui/Select.vue'
import UiTextarea from '../../components/ui/UiTextarea.vue'
import PatientSelector from '../../components/ui/PatientSelector.vue'
import { useApi } from '../../composables/useApi'
import { useToast } from '../../composables/useToast'
import {
  normalizeValidationErrors,
  pickValidationMessage
} from '../../composables/validationErrors'
import { patientFullName } from './labCaseMeta'

const props = defineProps({
  modelValue: { type: Boolean, default: false },
  labCase: { type: Object, default: null },
  patientId: { type: [Number, String], default: null },
  // The owning surface already knows the patient's name; without it the form
  // would need a request just to print the fixed line.
  patientName: { type: String, default: '' }
})

const emit = defineEmits(['update:modelValue', 'saved'])

const { get, post, put } = useApi()
const toast = useToast()

const saving = ref(false)
const errors = ref({})
const users = ref([])
const pieces = ref([])
const selectedPatient = ref(null)

const isEdit = computed(() => Boolean(props.labCase?.id))

const title = computed(() =>
  isEdit.value ? 'Editar caso de laboratorio' : 'Nuevo caso de laboratorio'
)

const submitLabel = computed(() => (isEdit.value ? 'Guardar cambios' : 'Guardar caso'))

const fixedPatient = computed(() =>
  Boolean(props.patientId || props.labCase?.patient_id || props.labCase?.patient?.id)
)

const fixedPatientName = computed(() => {
  if (props.patientName) return props.patientName
  return patientFullName(props.labCase?.patient)
})

const emptyOption = (label, value = null) => [{ label, value }]

const professionalOptions = computed(() => [
  ...emptyOption('Sin asignar'),
  ...users.value
    .filter(user => ['odontologo', 'implantologo'].includes(user.role))
    .map(user => ({ label: user.name, value: user.id }))
])

const technicianOptions = computed(() => [
  ...emptyOption('Sin asignar'),
  ...users.value
    .filter(user => user.role === 'tecnico_dental')
    .map(user => ({ label: user.name, value: user.id }))
])

const pieceOptions = computed(() => [
  ...emptyOption('Sin pieza'),
  ...pieces.value
    .filter(piece => piece.is_active !== false)
    .map(piece => ({ label: String(piece.fdi_number), value: piece.id }))
])

const today = () => {
  const now = new Date()
  const month = String(now.getMonth() + 1).padStart(2, '0')
  const day = String(now.getDate()).padStart(2, '0')
  return `${now.getFullYear()}-${month}-${day}`
}

// `sent_at`/`promised_at` are dates, not instants: the payload may carry the
// ISO datetime of the cast, and only the day belongs in a date input.
const dateOnly = value => {
  if (!value) return ''
  const match = String(value).match(/^\d{4}-\d{2}-\d{2}/)
  return match ? match[0] : ''
}

const form = ref({
  patient_id: null,
  professional_id: null,
  technician_id: null,
  dental_piece_id: null,
  work: '',
  material: '',
  sent_at: today(),
  promised_at: today(),
  notes: ''
})

const resetForm = () => {
  errors.value = {}
  selectedPatient.value = null

  const current = props.labCase

  if (current?.id) {
    form.value = {
      patient_id: current.patient_id ?? current.patient?.id ?? null,
      professional_id: current.professional_id ?? current.professional?.id ?? null,
      technician_id: current.technician_id ?? current.technician?.id ?? null,
      dental_piece_id: current.dental_piece_id ?? current.dental_piece?.id ?? null,
      work: current.work ?? '',
      material: current.material ?? '',
      sent_at: dateOnly(current.sent_at),
      promised_at: dateOnly(current.promised_at),
      notes: current.notes ?? ''
    }
    return
  }

  form.value = {
    patient_id: props.patientId ?? null,
    professional_id: null,
    technician_id: null,
    dental_piece_id: null,
    work: '',
    material: '',
    sent_at: today(),
    promised_at: today(),
    notes: ''
  }
}

const loadCatalogs = async () => {
  try {
    const [usersResponse, piecesResponse] = await Promise.all([
      get('/api/users/active'),
      get('/api/dental-pieces')
    ])
    users.value = Array.isArray(usersResponse?.data) ? usersResponse.data : []
    pieces.value = Array.isArray(piecesResponse?.data) ? piecesResponse.data : []
  } catch (thrown) {
    // The form stays usable with the free fields; the selects stay empty and
    // the API still validates whatever is sent.
    users.value = []
    pieces.value = []
  }
}

const onPatientSelected = patient => {
  selectedPatient.value = patient
  form.value.patient_id = patient?.id ?? null
}

const close = () => emit('update:modelValue', false)

const validate = () => {
  const next = {}
  const current = form.value

  if (!current.patient_id) next.patient_id = 'Selecciona un paciente.'
  if (!current.work?.trim()) next.work = 'El trabajo a realizar es obligatorio.'
  if (!current.sent_at) next.sent_at = 'La fecha de envío es obligatoria.'
  if (!current.promised_at) next.promised_at = 'La fecha prometida es obligatoria.'
  if (current.sent_at && current.promised_at && current.promised_at < current.sent_at) {
    next.promised_at = 'La fecha prometida no puede ser anterior a la fecha de envío.'
  }

  errors.value = next
  return Object.keys(next).length === 0
}

const buildPayload = () => ({
  patient_id: form.value.patient_id,
  professional_id: form.value.professional_id || null,
  technician_id: form.value.technician_id || null,
  dental_piece_id: form.value.dental_piece_id || null,
  work: form.value.work.trim(),
  material: form.value.material?.trim() || null,
  sent_at: form.value.sent_at,
  promised_at: form.value.promised_at,
  notes: form.value.notes?.trim() || null
})

// The 422 carries one Spanish sentence per field; the form prints each in its
// own place. A key the form does not paint (the refusal of an already placed
// case, for example) and any other rejection land in the notice so the server's
// reason is never swallowed.
const FORM_FIELDS = [
  'patient_id',
  'professional_id',
  'technician_id',
  'dental_piece_id',
  'work',
  'material',
  'sent_at',
  'promised_at',
  'notes'
]

const applyErrors = thrown => {
  const data = thrown?.response?.data
  const normalized = normalizeValidationErrors(data?.errors)
  const next = {}
  const unpainted = []

  Object.entries(normalized).forEach(([field, sentence]) => {
    if (FORM_FIELDS.includes(field)) next[field] = sentence
    else unpainted.push(sentence)
  })

  if (unpainted.length > 0 || Object.keys(next).length === 0) {
    next.message =
      unpainted[0] || pickValidationMessage(data, 'No se pudo guardar el caso de laboratorio.')
  }

  errors.value = next
}

const submit = async () => {
  if (saving.value) return
  if (!validate()) return

  saving.value = true
  try {
    const body = buildPayload()
    const response = isEdit.value
      ? await put(`/api/lab-cases/${props.labCase.id}`, body)
      : await post('/api/lab-cases', body)

    toast.success(
      response?.meta?.message ||
        (isEdit.value ? 'Caso de laboratorio actualizado.' : 'Caso de laboratorio registrado.')
    )
    emit('saved', response?.data ?? null)
    close()
  } catch (thrown) {
    applyErrors(thrown)
  } finally {
    saving.value = false
  }
}

watch(
  () => props.modelValue,
  isOpen => {
    if (!isOpen) return
    resetForm()
    loadCatalogs()
  },
  { immediate: true }
)
</script>
