<template>
  <UiModal
    model-value
    :title="isEdit ? 'Editar especialidad' : 'Nueva especialidad'"
    size="lg"
    @close="emit('close')"
  >
    <div class="space-y-6 bg-canvas" :data-specialty-form="isEdit ? 'edit' : 'create'">
      <fieldset class="space-y-4" data-form-section="identification">
        <legend class="text-xs font-semibold uppercase tracking-wide text-theme-secondary">
          Identificación
        </legend>
        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
          <div data-field="code">
            <UiInput
              v-model="form.code"
              label="Código"
              required
              maxlength="50"
              :disabled="isEdit || saving"
              placeholder="ej. cirugia_oral"
              :hint="isEdit ? CODE_EDIT_HINT : CODE_HINT"
              :error="errors.code"
              class="w-full"
            />
          </div>
          <div data-field="name">
            <UiInput
              v-model="form.name"
              label="Nombre visible"
              required
              maxlength="100"
              :disabled="saving"
              placeholder="ej. Cirugía oral"
              :error="errors.name"
              class="w-full"
            />
          </div>
        </div>
      </fieldset>

      <fieldset class="space-y-3" data-form-section="detail">
        <legend class="text-xs font-semibold uppercase tracking-wide text-theme-secondary">
          Detalle
        </legend>
        <div data-field="description">
          <UiTextarea
            v-model="form.description"
            :rows="3"
            aria-label="Descripción"
            placeholder="Alcance clínico de la especialidad"
            :disabled="saving"
          />
          <p v-if="errors.description" class="text-xs text-error-500">
            {{ errors.description }}
          </p>
        </div>
      </fieldset>

      <fieldset class="space-y-4" data-form-section="status">
        <legend class="text-xs font-semibold uppercase tracking-wide text-theme-secondary">
          Estado
        </legend>
        <div data-field="status">
          <p class="mb-1 block text-sm font-medium text-theme-primary">Estado</p>
          <UiSelect
            v-model="statusValue"
            :options="STATUS_FORM_OPTIONS"
            label="Estado"
            placeholder="Seleccionar estado"
            :disabled="saving"
            :error="errors.is_active"
          />
        </div>
        <p class="text-xs text-theme-secondary" data-delete-contract>
          {{ DELETE_CONTRACT_NOTICE }}
        </p>
        <p v-if="isEdit" class="text-xs text-theme-secondary" data-usage-summary>
          {{ getUsageSummary(props.specialty) }}
        </p>
      </fieldset>
    </div>

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
        {{ isEdit ? 'Actualizar especialidad' : 'Crear especialidad' }}
      </UiButton>
    </template>
  </UiModal>
</template>

<script setup>
import { computed, reactive, ref } from 'vue'
import { useApi } from '../../composables/useApi'
import { useToast } from '../../composables/useToast'
import {
  CODE_EDIT_HINT,
  CODE_FORMAT,
  CODE_HINT,
  DELETE_CONTRACT_NOTICE,
  STATUS_FORM_OPTIONS,
  getUsageSummary
} from './specialtyMeta'
import UiButton from '../../components/ui/Button.vue'
import UiInput from '../../components/ui/Input.vue'
import UiModal from '../../components/ui/Modal.vue'
import UiSelect from '../../components/ui/Select.vue'
import UiTextarea from '../../components/ui/UiTextarea.vue'

const props = defineProps({
  specialty: { type: Object, default: null }
})

const emit = defineEmits(['close', 'saved'])

const { post, put } = useApi()
const toast = useToast()

const isEdit = computed(() => Boolean(props.specialty))

const createFormState = specialty => ({
  code: specialty?.code || '',
  name: specialty?.name || '',
  description: specialty?.description || '',
  is_active: specialty ? Boolean(specialty.is_active) : true
})

const form = reactive(createFormState(props.specialty))
const errors = ref({})
const saving = ref(false)

const statusValue = computed({
  get: () => (form.is_active ? 'active' : 'inactive'),
  set: value => {
    form.is_active = value === 'active'
  }
})

const validate = () => {
  const nextErrors = {}
  const code = form.code.trim()

  if (!isEdit.value && !code) {
    nextErrors.code = 'Ingresa el código de la especialidad.'
  } else if (!isEdit.value && !CODE_FORMAT.test(code)) {
    nextErrors.code = 'Usa minúsculas, números y guiones bajos. Por ejemplo: cirugia_oral.'
  }

  if (!form.name.trim()) {
    nextErrors.name = 'Ingresa el nombre de la especialidad.'
  }

  errors.value = nextErrors
  return Object.keys(nextErrors).length === 0
}

const buildPayload = () => {
  const payload = {
    name: form.name.trim(),
    description: form.description.trim() || null,
    is_active: form.is_active
  }

  if (!isEdit.value) {
    payload.code = form.code.trim()
  }

  return payload
}

const applyServerErrors = error => {
  const responseErrors = error.response?.data?.errors

  if (responseErrors && typeof responseErrors === 'object') {
    errors.value = Object.fromEntries(
      Object.entries(responseErrors).map(([field, messages]) => [
        field,
        Array.isArray(messages) ? messages[0] : messages
      ])
    )
    return
  }

  toast.error(error.response?.data?.message || 'No se pudo guardar la especialidad.')
}

const onSubmit = async () => {
  if (saving.value) return
  if (!validate()) return

  saving.value = true
  try {
    const response = isEdit.value
      ? await put(`/api/specialties/${props.specialty.id}`, buildPayload())
      : await post('/api/specialties', buildPayload())

    emit('saved', response?.data ?? response)
  } catch (error) {
    applyServerErrors(error)
  } finally {
    saving.value = false
  }
}
</script>
