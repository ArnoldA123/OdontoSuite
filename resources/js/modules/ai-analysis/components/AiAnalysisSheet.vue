<template>
  <UiSheet
    :model-value="modelValue"
    title="Nuevo análisis de IA"
    size="lg"
    position="right"
    :closable="!submitting"
    :close-on-backdrop="!submitting"
    :close-on-escape="!submitting"
    @update:model-value="emit('update:modelValue', $event)"
    @close="closeSheet"
  >
    <div data-ai-analysis-sheet>
      <form data-sheet-form class="space-y-6" @submit.prevent="handleSubmit">
        <section data-sheet-step="patient" class="space-y-2">
          <h3 class="flex items-center gap-2 text-sm font-semibold text-theme-primary">
            <span
              data-step-number
              class="flex h-6 w-6 items-center justify-center rounded-full text-xs font-semibold text-theme-primary"
              style="background: var(--color-canvas); border: 1px solid var(--color-hairline)"
            >
              1
            </span>
            Paciente
          </h3>
          <PatientSelector :model-value="patient" @update:model-value="onPatientSelected" />
          <p
            v-if="stepErrors.patient"
            role="alert"
            data-step-error="patient"
            class="text-sm text-systemRed-600"
          >
            {{ stepErrors.patient }}
          </p>
        </section>

        <section data-sheet-step="image" class="space-y-2">
          <h3 class="flex items-center gap-2 text-sm font-semibold text-theme-primary">
            <span
              data-step-number
              class="flex h-6 w-6 items-center justify-center rounded-full text-xs font-semibold text-theme-primary"
              style="background: var(--color-canvas); border: 1px solid var(--color-hairline)"
            >
              2
            </span>
            Imagen
          </h3>
          <!-- The primitive keeps its picker chrome; the sheet owns the size/type copy. -->
          <FileUpload
            accept="image/*"
            preview
            :max-size="Number.POSITIVE_INFINITY"
            @file-selected="onFileSelected"
            @file-cleared="onFileCleared"
          />
          <p
            v-if="stepErrors.image"
            role="alert"
            data-step-error="image"
            class="text-sm text-systemRed-600"
          >
            {{ stepErrors.image }}
          </p>
        </section>

        <section data-sheet-step="category" class="space-y-2">
          <h3 class="flex items-center gap-2 text-sm font-semibold text-theme-primary">
            <span
              data-step-number
              class="flex h-6 w-6 items-center justify-center rounded-full text-xs font-semibold text-theme-primary"
              style="background: var(--color-canvas); border: 1px solid var(--color-hairline)"
            >
              3
            </span>
            Tipo de imagen
          </h3>
          <UiSelect
            v-model="category"
            :options="categoryOptions"
            label="Tipo de imagen"
            data-sheet-category
          />
        </section>

        <section data-sheet-step="description" class="space-y-2">
          <h3>
            <label
              for="ai-analysis-description"
              class="flex items-center gap-2 text-sm font-semibold text-theme-primary"
            >
              <span
                data-step-number
                class="flex h-6 w-6 items-center justify-center rounded-full text-xs font-semibold text-theme-primary"
                style="background: var(--color-canvas); border: 1px solid var(--color-hairline)"
              >
                4
              </span>
              Descripción (Opcional)
            </label>
          </h3>
          <UiTextarea
            id="ai-analysis-description"
            v-model="description"
            data-sheet-description
            maxlength="500"
            :rows="3"
            placeholder="Ej: Radiografía panorámica, dolor en molar inferior derecho..."
          />
          <p data-description-counter class="text-right text-xs text-theme-secondary">
            {{ description.length }}/500
          </p>

          <div
            class="flex items-start gap-3 rounded-[var(--radius-control)] bg-theme-surface p-3"
            style="border: 1px solid var(--color-hairline)"
          >
            <input
              id="ai-analysis-notice"
              v-model="noticeAcknowledged"
              data-sheet-notice
              type="checkbox"
              class="mt-0.5 h-4 w-4 flex-shrink-0"
              style="accent-color: var(--color-accent)"
            />
            <label for="ai-analysis-notice" class="text-sm leading-6 text-theme-primary">
              Informé al paciente que el resultado es apoyo asistido por IA y no sustituye el
              diagnóstico profesional.
            </label>
          </div>
        </section>

        <p
          v-if="errorMessage"
          role="alert"
          data-sheet-error
          class="rounded-[var(--radius-control)] bg-systemRed-50 p-3 text-sm text-systemRed-600"
        >
          {{ errorMessage }}
        </p>

        <div class="flex flex-wrap items-center justify-end gap-3">
          <UiButton
            variant="secondary"
            type="button"
            :disabled="submitting"
            data-sheet-cancel
            @click="closeSheet"
          >
            Cancelar
          </UiButton>
          <UiButton type="submit" :disabled="!canSubmit" :loading="submitting" data-sheet-submit>
            <template #icon-left>
              <CpuChipIcon class="h-5 w-5" aria-hidden="true" />
            </template>
            Analizar con IA
          </UiButton>
        </div>
      </form>
    </div>
  </UiSheet>
</template>

<script setup>
import { computed, ref, watch } from 'vue'
import { CpuChipIcon } from '@heroicons/vue/24/outline'
import UiButton from '../../../components/ui/Button.vue'
import UiSelect from '../../../components/ui/Select.vue'
import UiSheet from '../../../components/ui/Sheet.vue'
import UiTextarea from '../../../components/ui/UiTextarea.vue'
import FileUpload from '../../../components/ui/FileUpload.vue'
import PatientSelector from '../../../components/ui/PatientSelector.vue'

const props = defineProps({
  modelValue: { type: Boolean, default: false },
  submitting: { type: Boolean, default: false },
  errorMessage: { type: String, default: '' }
})

const emit = defineEmits(['update:modelValue', 'close', 'submit'])

const MAX_FILE_BYTES = 20 * 1024 * 1024

const categoryOptions = [
  { value: 'radiografia', label: 'Radiografía' },
  { value: 'foto_clinica', label: 'Foto clínica' }
]

const patient = ref(null)
const file = ref(null)
const category = ref('radiografia')
const description = ref('')
const noticeAcknowledged = ref(false)
const stepErrors = ref({ patient: '', image: '' })

const canSubmit = computed(
  () => Boolean(patient.value && file.value && noticeAcknowledged.value) && !props.submitting
)

const closeSheet = () => {
  emit('update:modelValue', false)
  emit('close')
}

const resetForm = () => {
  patient.value = null
  file.value = null
  category.value = 'radiografia'
  description.value = ''
  noticeAcknowledged.value = false
  stepErrors.value = { patient: '', image: '' }
}

const validateFile = candidate => {
  if (candidate.size > MAX_FILE_BYTES) return 'La imagen supera los 20 MB.'
  if (!String(candidate.type || '').startsWith('image/')) return 'Solo se aceptan imágenes.'
  return ''
}

const onPatientSelected = selected => {
  patient.value = selected || null
  stepErrors.value = { ...stepErrors.value, patient: '' }
}

const onFileSelected = selected => {
  const error = validateFile(selected)
  if (error) {
    file.value = null
    stepErrors.value = { ...stepErrors.value, image: error }
    return
  }

  file.value = selected
  stepErrors.value = { ...stepErrors.value, image: '' }
}

const onFileCleared = () => {
  file.value = null
}

const handleSubmit = () => {
  if (props.submitting) return

  const nextErrors = {
    patient: patient.value ? '' : 'Selecciona un paciente.',
    image: file.value ? '' : 'Selecciona una imagen.'
  }
  stepErrors.value = nextErrors
  if (nextErrors.patient || nextErrors.image) return

  emit('submit', {
    patientId: patient.value.id,
    file: file.value,
    category: category.value,
    description: description.value.trim(),
    noticeAcknowledged: true
  })
}

watch(
  () => props.modelValue,
  open => {
    if (!open) resetForm()
  }
)
</script>
