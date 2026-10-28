<template>
  <UiModal
    :model-value="modelValue"
    title="Nuevo cargo"
    size="md"
    data-patient-charge-modal
    @update:model-value="value => emit('update:modelValue', value)"
    @close="close"
  >
    <form class="space-y-4" novalidate @submit.prevent="submit">
      <!-- The charge is opened from the patient's own record: the patient is
           fixed, not re-selected. -->
      <div>
        <p class="block text-sm font-medium text-theme-primary mb-1">Paciente</p>
        <p
          class="rounded-ios px-3 py-2 text-sm text-theme-primary"
          style="background: var(--color-canvas); border: 1px solid var(--color-hairline)"
          data-patient-charge-patient
        >
          {{ patientName }}
        </p>
        <p
          v-if="errors.patient_id"
          role="alert"
          class="mt-1 text-sm font-medium text-systemRed-600"
          data-patient-charge-error="patient_id"
        >
          {{ errors.patient_id }}
        </p>
      </div>

      <div>
        <CurrencyInput v-model="form.amount" label="Importe" required data-patient-charge-amount />
        <p
          v-if="errors.amount"
          role="alert"
          class="mt-1 text-sm font-medium text-systemRed-600"
          data-patient-charge-error="amount"
        >
          {{ errors.amount }}
        </p>
      </div>

      <div>
        <UiInput
          id="patient-charge-description"
          v-model="form.description"
          label="Descripción"
          placeholder="Ej: Corona metal-cerámica"
          maxlength="255"
          required
          data-patient-charge-description
        />
        <p
          v-if="errors.description"
          role="alert"
          class="mt-1 text-sm font-medium text-systemRed-600"
          data-patient-charge-error="description"
        >
          {{ errors.description }}
        </p>
      </div>

      <div>
        <UiInput
          id="patient-charge-charged-at"
          v-model="form.charged_at"
          type="date"
          label="Fecha del cargo"
          :max="today"
          data-patient-charge-charged-at
        />
        <p
          v-if="errors.charged_at"
          role="alert"
          class="mt-1 text-sm font-medium text-systemRed-600"
          data-patient-charge-error="charged_at"
        >
          {{ errors.charged_at }}
        </p>
      </div>

      <div>
        <label for="patient-charge-notes" class="block text-sm font-medium text-theme-primary mb-1">
          Notas
        </label>
        <UiTextarea
          id="patient-charge-notes"
          v-model="form.notes"
          :rows="3"
          maxlength="1000"
          placeholder="Detalle del cargo acordado en mostrador…"
          data-patient-charge-notes
        />
        <p
          v-if="errors.notes"
          role="alert"
          class="mt-1 text-sm font-medium text-systemRed-600"
          data-patient-charge-error="notes"
        >
          {{ errors.notes }}
        </p>
      </div>

      <p
        v-if="errors.message"
        role="alert"
        class="rounded-ios bg-systemRed-50 p-3 text-sm font-medium text-systemRed-600"
        data-patient-charge-error="message"
      >
        {{ errors.message }}
      </p>
    </form>

    <template #footer>
      <div class="flex justify-end gap-3">
        <UiButton variant="secondary" data-patient-charge-cancel @click="close">Cancelar</UiButton>
        <UiButton variant="primary" :loading="saving" data-patient-charge-submit @click="submit">
          Guardar cargo
        </UiButton>
      </div>
    </template>
  </UiModal>
</template>

<script setup>
import { computed, ref, watch } from 'vue'
import UiModal from '../../../components/ui/Modal.vue'
import UiButton from '../../../components/ui/Button.vue'
import UiInput from '../../../components/ui/Input.vue'
import UiTextarea from '../../../components/ui/UiTextarea.vue'
import CurrencyInput from '../../../components/ui/CurrencyInput.vue'
import { useApi } from '../../../composables/useApi'

const props = defineProps({
  modelValue: { type: Boolean, default: false },
  patient: { type: Object, default: null }
})

const emit = defineEmits(['update:modelValue', 'success'])

const { post } = useApi()

const pad = value => String(value).padStart(2, '0')

const dateStamp = date => `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`

// The page mounts this modal once and may keep it all day: the ceiling and
// the default date are refreshed every time the counter opens it.
const today = ref(dateStamp(new Date()))

const saving = ref(false)
const errors = ref({})

const patientName = computed(() => {
  const current = props.patient
  if (!current) return '—'
  const composed = [current.first_name, current.last_name].filter(Boolean).join(' ').trim()
  return composed || '—'
})

const form = ref({
  amount: 0,
  description: '',
  charged_at: today.value,
  notes: ''
})

const resetForm = () => {
  today.value = dateStamp(new Date())
  errors.value = {}
  form.value = {
    amount: 0,
    description: '',
    charged_at: today.value,
    notes: ''
  }
}

const close = () => emit('update:modelValue', false)

// The counter's form is the first filter: an incomplete charge never reaches
// the API, and the backend still owns the authoritative messages.
const validate = () => {
  const next = {}
  const current = form.value
  const amount = Number(current.amount)

  if (!Number.isFinite(amount) || amount <= 0) {
    next.amount = 'El importe del cargo debe ser mayor que cero.'
  }
  if (!current.description?.trim()) {
    next.description = 'La descripción del cargo es requerida.'
  }
  if (current.charged_at && current.charged_at > today.value) {
    next.charged_at = 'La fecha del cargo no puede ser futura.'
  }
  if ((current.notes?.length ?? 0) > 1000) {
    next.notes = 'Las notas no pueden exceder 1000 caracteres.'
  }

  errors.value = next
  return Object.keys(next).length === 0
}

const buildPayload = () => ({
  patient_id: props.patient?.id ?? null,
  amount: Number(form.value.amount),
  description: form.value.description.trim(),
  charged_at: form.value.charged_at || null,
  notes: form.value.notes?.trim() || null
})

// A rejected charge is never a local row: the sentence lands in its field and
// the modal stays open with what the counter typed.
const applyErrors = thrown => {
  const data = thrown?.response?.data
  const fieldErrors = data?.errors
  const next = {}

  if (fieldErrors && typeof fieldErrors === 'object') {
    ;['amount', 'description', 'charged_at', 'patient_id'].forEach(field => {
      const messages = fieldErrors[field]
      const sentence = Array.isArray(messages) ? messages[0] : messages
      if (sentence) next[field] = String(sentence)
    })
  }

  next.message = data?.message || 'No se pudo registrar el cargo.'

  errors.value = next
}

const submit = async () => {
  if (saving.value) return
  if (!validate()) return
  if (!props.patient?.id) {
    errors.value = { patient_id: 'El paciente es requerido.' }
    return
  }

  saving.value = true
  try {
    const response = await post('/api/patient-charges', buildPayload())
    emit('success', response?.data ?? null)
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
  },
  { immediate: true }
)
</script>
