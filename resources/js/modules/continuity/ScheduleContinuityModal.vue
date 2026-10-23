<template>
  <UiModal
    :model-value="modelValue"
    size="lg"
    data-schedule-modal
    @update:model-value="value => emit('update:modelValue', value)"
    @close="emit('update:modelValue', false)"
  >
    <template #header>
      <div class="min-w-0">
        <h2 class="text-lg font-semibold text-theme-primary" data-schedule-title>{{ title }}</h2>
        <p class="mt-1 text-sm text-theme-secondary" data-schedule-patient>
          {{ patientFullName(row?.patient) }}
        </p>
      </div>
    </template>

    <form :id="formId" class="space-y-4 bg-canvas" data-schedule-form @submit.prevent="submit">
      <div
        v-if="errorMessage"
        role="alert"
        data-schedule-error="message"
        class="rounded-ios border border-systemRed-200 bg-systemRed-50 px-4 py-3 text-sm text-systemRed-700"
      >
        {{ errorMessage }}
      </div>

      <p v-if="errors.status" class="text-sm text-systemRed-600" data-schedule-error="status">
        {{ errors.status }}
      </p>

      <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
        <div data-sc-scheduled-at>
          <UiInput
            v-model="form.scheduled_at"
            label="Fecha y hora *"
            type="datetime-local"
            :disabled="loadingOptions"
          />
          <p
            v-if="errors.scheduled_at"
            class="mt-1 text-xs text-systemRed-600"
            data-schedule-error="scheduled_at"
          >
            {{ errors.scheduled_at }}
          </p>
        </div>

        <div data-sc-duration>
          <UiInput
            v-model="form.duration_minutes"
            label="Duración (minutos) *"
            type="number"
            min="15"
            max="480"
            step="5"
            :disabled="loadingOptions"
          />
          <p
            v-if="errors.duration_minutes"
            class="mt-1 text-xs text-systemRed-600"
            data-schedule-error="duration_minutes"
          >
            {{ errors.duration_minutes }}
          </p>
        </div>
      </div>

      <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
        <div data-sc-user>
          <label class="mb-1 block text-sm font-medium text-theme-primary">
            Profesional
            <span class="text-systemRed-600">*</span>
          </label>
          <UiSelect
            v-model="form.user_id"
            :options="professionalOptions"
            :disabled="loadingOptions"
            label="Profesional"
            placeholder="Seleccionar profesional"
            searchable
          />
          <p
            v-if="errors.user_id"
            class="mt-1 text-xs text-systemRed-600"
            data-schedule-error="user_id"
          >
            {{ errors.user_id }}
          </p>
        </div>

        <div data-sc-chair>
          <label class="mb-1 block text-sm font-medium text-theme-primary">
            Sillón dental
            <span class="text-systemRed-600">*</span>
          </label>
          <UiSelect
            v-model="form.dental_chair_id"
            :options="chairOptions"
            :disabled="loadingOptions"
            label="Sillón dental"
            placeholder="Seleccionar sillón"
            searchable
          />
          <p
            v-if="errors.dental_chair_id"
            class="mt-1 text-xs text-systemRed-600"
            data-schedule-error="dental_chair_id"
          >
            {{ errors.dental_chair_id }}
          </p>
        </div>
      </div>

      <div data-sc-type>
        <label class="mb-1 block text-sm font-medium text-theme-primary">
          Tipo de cita
          <span v-if="isRecall" class="text-systemRed-600">*</span>
        </label>
        <UiSelect
          v-model="form.appointment_type_id"
          :options="appointmentTypeOptions"
          :disabled="loadingOptions"
          label="Tipo de cita"
          placeholder="Seleccionar tipo"
          searchable
        />
        <p v-if="!isRecall" class="mt-1 text-xs text-theme-secondary">
          Si no eliges uno, la cita usa el tipo de la solicitud.
        </p>
        <p
          v-if="errors.appointment_type_id"
          class="mt-1 text-xs text-systemRed-600"
          data-schedule-error="appointment_type_id"
        >
          {{ errors.appointment_type_id }}
        </p>
      </div>

      <div data-sc-notes>
        <UiInput
          v-model="form.notes"
          label="Notas"
          type="textarea"
          :rows="3"
          placeholder="Indicaciones para la cita"
          :disabled="loadingOptions"
        />
      </div>

      <p v-if="optionsError" class="text-sm text-systemRed-600" data-schedule-options-error>
        {{ optionsError }}
      </p>
    </form>

    <template #footer>
      <UiButton variant="secondary" :disabled="submitting" data-schedule-cancel @click="close">
        Cancelar
      </UiButton>
      <UiButton
        type="submit"
        :form="formId"
        :loading="submitting"
        :disabled="!canSubmit"
        data-schedule-submit
        @click="submit"
      >
        Agendar
      </UiButton>
    </template>
  </UiModal>
</template>

<script setup>
import { computed, ref, watch } from 'vue'
import { useApi } from '../../composables/useApi'
import { useContinuity } from '../../composables/useContinuity'
import UiButton from '../../components/ui/Button.vue'
import UiInput from '../../components/ui/Input.vue'
import UiModal from '../../components/ui/Modal.vue'
import UiSelect from '../../components/ui/Select.vue'
import {
  defaultRecallSlot,
  patientFullName,
  toApiDateTime,
  toDateTimeLocal
} from './continuityMeta'

// The two schedule doors validate professional and chair (and the recall also
// demands a type), so a rejection is always reportable on the field the server
// names. Anything else falls back to the envelope message.
const props = defineProps({
  modelValue: { type: Boolean, default: false },
  kind: {
    type: String,
    default: 'request',
    validator: value => ['request', 'recall'].includes(value)
  },
  row: { type: Object, default: null }
})

const emit = defineEmits(['update:modelValue', 'scheduled'])

// The two schedule doors validate professional and chair (and the recall also
// demands a type), so a rejection is always reportable on the field the server
// names. Anything else falls back to the envelope message.
const FIELD_ERRORS = [
  'scheduled_at',
  'user_id',
  'dental_chair_id',
  'appointment_type_id',
  'duration_minutes',
  'status'
]

const { get } = useApi()
const { schedule } = useContinuity()

const isRecall = computed(() => props.kind === 'recall')
const title = computed(() =>
  isRecall.value ? 'Agendar cita de control' : 'Agendar cita de continuidad'
)
const formId = computed(() => `continuity-${props.kind}-schedule-form`)

const professionals = ref([])
const chairs = ref([])
const appointmentTypes = ref([])
const loadingOptions = ref(false)
const optionsError = ref('')
const submitting = ref(false)
const errors = ref({})
const errorMessage = ref('')

const form = ref({
  scheduled_at: '',
  user_id: '',
  dental_chair_id: '',
  appointment_type_id: '',
  duration_minutes: 30,
  notes: ''
})

const professionalOptions = computed(() =>
  professionals.value.map(professional => ({ value: professional.id, label: professional.name }))
)
const chairOptions = computed(() =>
  chairs.value.map(chair => ({ value: chair.id, label: chair.name }))
)
const appointmentTypeOptions = computed(() =>
  appointmentTypes.value.map(type => ({ value: type.id, label: type.name }))
)

const isFilled = value => value !== '' && value !== null && value !== undefined

const canSubmit = computed(() => {
  const {
    scheduled_at: scheduledAt,
    user_id: userId,
    dental_chair_id: chairId,
    appointment_type_id: typeId,
    duration_minutes: duration
  } = form.value

  if (!isFilled(scheduledAt) || !isFilled(userId) || !isFilled(chairId)) return false
  if (!(Number(duration) > 0)) return false
  if (isRecall.value && !isFilled(typeId)) return false

  return true
})

const prefill = () => {
  const current = props.row
  if (!current) return

  form.value = {
    scheduled_at: isRecall.value
      ? defaultRecallSlot(current.due_date)
      : toDateTimeLocal(current.preferred_from),
    user_id: '',
    dental_chair_id: '',
    appointment_type_id: isRecall.value ? '' : (current.appointment_type_id ?? ''),
    duration_minutes: isRecall.value ? 30 : current.duration_minutes || 30,
    notes: isRecall.value ? '' : (current.notes ?? '')
  }

  errors.value = {}
  errorMessage.value = ''
}

const loadOptions = async () => {
  loadingOptions.value = true
  optionsError.value = ''
  try {
    const [usersResponse, chairsResponse, typesResponse] = await Promise.all([
      get('/api/users/active'),
      get('/api/dental-chairs/active'),
      get('/api/appointment-types/active')
    ])
    professionals.value = usersResponse?.data ?? []
    chairs.value = chairsResponse?.data ?? []
    appointmentTypes.value = typesResponse?.data ?? []
  } catch (thrown) {
    optionsError.value =
      thrown?.response?.data?.message || 'No se pudieron cargar los profesionales y sillones.'
  } finally {
    loadingOptions.value = false
  }
}

const applyServerErrors = thrown => {
  const data = thrown?.response?.data || {}
  const serverErrors = data.errors || {}
  const next = {}

  FIELD_ERRORS.forEach(field => {
    const messages = serverErrors[field]
    if (Array.isArray(messages) && messages[0]) next[field] = messages[0]
  })

  errors.value = next
  errorMessage.value = Object.keys(next).length
    ? ''
    : data.message || thrown?.message || 'No se pudo agendar la cita de continuidad.'
}

// Both doors take the same slot; only the type is optional on a request, where
// the server falls back to the one the professional left.
const payload = () => {
  const data = {
    scheduled_at: toApiDateTime(form.value.scheduled_at),
    user_id: form.value.user_id,
    dental_chair_id: form.value.dental_chair_id,
    duration_minutes: Number(form.value.duration_minutes)
  }

  if (isFilled(form.value.appointment_type_id)) {
    data.appointment_type_id = form.value.appointment_type_id
  }

  const notes = (form.value.notes || '').trim()
  if (notes) data.notes = notes

  return data
}

const close = () => emit('update:modelValue', false)

const submit = async () => {
  if (submitting.value || !props.row?.id || !canSubmit.value) return

  submitting.value = true
  errors.value = {}
  errorMessage.value = ''
  try {
    await schedule(props.kind, props.row, payload())
    emit('scheduled', props.row)
    close()
  } catch (thrown) {
    // A rejected slot leaves the row in the queue: the composable only
    // refreshes on success, so the form keeps every value for the retry.
    applyServerErrors(thrown)
  } finally {
    submitting.value = false
  }
}

// One load per open: the lookups refresh when the desk opens the form, while a
// row change with the modal already open only re-prefills it.
let wasOpen = false

watch(
  [() => props.modelValue, () => props.row, () => props.kind],
  () => {
    const open = props.modelValue
    if (open) {
      prefill()
      if (!wasOpen) loadOptions()
    }
    wasOpen = open
  },
  { immediate: true }
)
</script>
