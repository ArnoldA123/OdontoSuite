<template>
  <UiModal
    :model-value="modelValue"
    title="Descartar solicitud"
    size="md"
    data-dismiss-modal
    @update:model-value="value => emit('update:modelValue', value)"
    @close="emit('update:modelValue', false)"
  >
    <form :id="formId" data-dismiss-form @submit.prevent="submit">
      <p v-if="row" class="text-sm font-medium text-theme-primary" data-dismiss-patient>
        {{ patientFullName(row.patient) }}
      </p>
      <p class="mt-1 text-sm text-theme-secondary">
        La solicitud saldrá de la cola de continuidad y el motivo quedará registrado con su autor y
        su fecha. Esta acción no se puede deshacer.
      </p>

      <div class="mt-4">
        <label
          for="continuity-dismiss-reason"
          class="mb-1 block text-sm font-medium text-theme-primary"
        >
          Motivo
          <span class="text-systemRed-600">*</span>
        </label>
        <UiTextarea
          id="continuity-dismiss-reason"
          v-model="reason"
          data-dismiss-reason
          :rows="3"
          maxlength="1000"
          :error="Boolean(error)"
          placeholder="Por qué se descarta esta solicitud"
          @update:model-value="error = ''"
        />
        <p v-if="error" class="mt-1 text-sm text-systemRed-600" data-dismiss-error>{{ error }}</p>
      </div>
    </form>

    <template #footer>
      <UiButton variant="secondary" :disabled="submitting" data-dismiss-cancel @click="close">
        Cancelar
      </UiButton>
      <UiButton
        type="submit"
        :form="formId"
        :loading="submitting"
        :disabled="!canSubmit"
        data-dismiss-submit
        @click="submit"
      >
        Descartar
      </UiButton>
    </template>
  </UiModal>
</template>

<script setup>
import { computed, ref, watch } from 'vue'
import { useContinuity } from '../../composables/useContinuity'
import UiButton from '../../components/ui/Button.vue'
import UiModal from '../../components/ui/Modal.vue'
import UiTextarea from '../../components/ui/UiTextarea.vue'
import { patientFullName } from './continuityMeta'

const props = defineProps({
  modelValue: { type: Boolean, default: false },
  row: { type: Object, default: null }
})

const emit = defineEmits(['update:modelValue', 'dismissed'])

const { dismissRequest } = useContinuity()

const formId = 'continuity-dismiss-form'

const reason = ref('')
const error = ref('')
const submitting = ref(false)

const canSubmit = computed(() => reason.value.trim() !== '')

const close = () => emit('update:modelValue', false)

const serverMessage = thrown => {
  const data = thrown?.response?.data
  const fieldMessages = data?.errors?.reason
  if (Array.isArray(fieldMessages) && fieldMessages[0]) return fieldMessages[0]
  return data?.message || thrown?.message || 'No se pudo descartar la solicitud.'
}

// S4/S12 — the reason is mandatory, so a blank form never reaches the API. The
// submit button starts disabled; a submit event (Enter on the textarea) still
// reports the rule instead of failing silently.
const submit = async () => {
  if (submitting.value) return

  if (!canSubmit.value) {
    error.value = 'El motivo es obligatorio.'
    return
  }

  submitting.value = true
  error.value = ''
  try {
    await dismissRequest(props.row, reason.value.trim())
    emit('dismissed', props.row)
    close()
  } catch (thrown) {
    error.value = serverMessage(thrown)
  } finally {
    submitting.value = false
  }
}

watch(
  [() => props.modelValue, () => props.row],
  () => {
    if (!props.modelValue) return
    reason.value = ''
    error.value = ''
  },
  { immediate: true }
)
</script>
