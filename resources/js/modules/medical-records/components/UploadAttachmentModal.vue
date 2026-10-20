<template>
  <UiModal
    model-value
    title="Subir adjunto"
    size="lg"
    :closable="!uploading"
    :close-on-backdrop="!uploading"
    :close-on-escape="!uploading"
    @close="emit('close')"
  >
    <form class="space-y-5" data-upload-attachment-form @submit.prevent="submit">
      <div class="rounded-ios border border-hairline bg-theme-surface px-4 py-3">
        <p class="text-xs font-medium uppercase text-theme-secondary">Paciente</p>
        <p class="mt-1 text-sm font-medium text-label" data-upload-patient>{{ patientName }}</p>
      </div>

      <div>
        <label for="attachment-file" class="block text-sm font-medium text-label mb-1">
          Archivo
          <span class="text-theme-secondary">*</span>
        </label>
        <div
          class="rounded-ios border-2 border-dashed px-4 py-6 text-center transition-colors"
          :class="
            isDragOver ? 'border-accent bg-theme-surface' : 'border-hairline bg-theme-surface'
          "
          data-upload-dropzone
          @dragover.prevent="isDragOver = true"
          @dragleave.prevent="isDragOver = false"
          @drop.prevent="onDrop"
        >
          <input
            id="attachment-file"
            ref="fileInput"
            type="file"
            class="hidden"
            accept="image/*,.pdf,.doc,.docx"
            data-upload-file-input
            @change="onFileInput"
          />

          <div v-if="!selectedFile" class="space-y-2">
            <CloudArrowUpIcon class="mx-auto h-10 w-10 text-theme-secondary" aria-hidden="true" />
            <p class="text-sm font-medium text-label">Arrastra y suelta el archivo aquí</p>
            <p class="text-xs text-theme-secondary">o selecciónalo desde tu equipo</p>
            <UiButton
              type="button"
              variant="secondary"
              size="sm"
              class="mt-2"
              data-upload-pick-file
              @click="openFilePicker"
            >
              <template #icon-left>
                <FolderOpenIcon class="w-4 h-4" aria-hidden="true" />
              </template>
              Seleccionar archivo
            </UiButton>
          </div>

          <div v-else class="flex items-center gap-3 text-left">
            <img
              v-if="filePreview"
              :src="filePreview"
              :alt="selectedFile.name"
              class="h-16 w-16 flex-none rounded-ios object-cover"
            />
            <div
              v-else
              class="flex h-16 w-16 flex-none items-center justify-center rounded-ios bg-theme-surface-elevated"
            >
              <component
                :is="fileTypeIcon(selectedFile)"
                class="h-8 w-8 text-theme-secondary"
                aria-hidden="true"
              />
            </div>
            <div class="min-w-0 flex-1">
              <p class="truncate text-sm font-medium text-label" data-upload-file-name>
                {{ selectedFile.name }}
              </p>
              <p class="mt-1 text-xs text-theme-secondary tabular-nums">
                {{ formatFileSize(selectedFile.size) }}
              </p>
            </div>
            <UiButton
              type="button"
              variant="icon"
              size="sm"
              aria-label="Quitar archivo seleccionado"
              data-upload-clear-file
              @click="clearFile"
            >
              <template #icon-left>
                <XMarkIcon class="w-4 h-4" aria-hidden="true" />
              </template>
            </UiButton>
          </div>
        </div>
        <p v-if="fieldError('file')" class="mt-1 text-xs text-error-500" data-upload-error="file">
          {{ fieldError('file') }}
        </p>
        <p v-else class="mt-1 text-xs text-theme-secondary">Imágenes, PDF o Word. Máximo 10 MB.</p>
      </div>

      <div>
        <label class="block text-sm font-medium text-label mb-1">
          Categoría
          <span class="text-theme-secondary">*</span>
        </label>
        <UiSelect
          v-model="form.category"
          :options="categoryOptions"
          placeholder="Seleccionar categoría"
          class="w-full"
          data-upload-category
        />
        <p
          v-if="fieldError('category')"
          class="mt-1 text-xs text-error-500"
          data-upload-error="category"
        >
          {{ fieldError('category') }}
        </p>
      </div>

      <div>
        <label class="block text-sm font-medium text-label mb-1">
          Descripción
          <span class="font-normal text-theme-secondary">(opcional)</span>
        </label>
        <UiTextarea
          v-model="form.description"
          :rows="3"
          placeholder="Detalle clínico del archivo..."
          class="w-full"
          data-upload-description
        />
        <p
          v-if="fieldError('description')"
          class="mt-1 text-xs text-error-500"
          data-upload-error="description"
        >
          {{ fieldError('description') }}
        </p>
      </div>

      <label
        class="flex items-start gap-3 rounded-ios border border-hairline px-4 py-3"
        data-upload-private
      >
        <input
          v-model="form.is_private"
          type="checkbox"
          class="mt-0.5 h-4 w-4 flex-none rounded border-hairline"
          style="accent-color: var(--color-accent-500)"
        />
        <span class="min-w-0">
          <span class="block text-sm font-medium text-label">Marcar como privado</span>
          <span class="block text-xs text-theme-secondary">
            Solo el personal clínico podrá ver este archivo.
          </span>
        </span>
      </label>

      <div class="flex flex-wrap items-center justify-end gap-3 border-t border-hairline pt-4">
        <UiButton
          type="button"
          variant="secondary"
          :disabled="uploading"
          data-upload-cancel
          @click="emit('close')"
        >
          Cancelar
        </UiButton>
        <UiButton type="submit" :loading="uploading" data-upload-submit>
          <template #icon-left>
            <CloudArrowUpIcon class="w-4 h-4" aria-hidden="true" />
          </template>
          {{ uploading ? 'Subiendo...' : 'Subir adjunto' }}
        </UiButton>
      </div>
    </form>
  </UiModal>
</template>

<script setup>
import { computed, ref } from 'vue'
import {
  CloudArrowUpIcon,
  DocumentIcon,
  DocumentTextIcon,
  FolderOpenIcon,
  PaperClipIcon,
  PhotoIcon,
  XMarkIcon
} from '@heroicons/vue/24/outline'
import { useMedicalRecords } from '@/composables/useMedicalRecords'
import { useToast } from '@/composables/useToast'
import UiButton from '@/components/ui/Button.vue'
import UiModal from '@/components/ui/Modal.vue'
import UiSelect from '@/components/ui/Select.vue'
import UiTextarea from '@/components/ui/UiTextarea.vue'

const props = defineProps({
  patient: {
    type: Object,
    required: true
  }
})

const emit = defineEmits(['close', 'uploaded'])

const MAX_FILE_BYTES = 10 * 1024 * 1024

const { uploadAttachment } = useMedicalRecords()
const toast = useToast()

const categoryOptions = [
  { value: 'radiografia', label: 'Radiografía' },
  { value: 'foto_clinica', label: 'Foto clínica' },
  { value: 'documento', label: 'Documento' },
  { value: 'otro', label: 'Otro' }
]

const form = ref({
  category: '',
  description: '',
  is_private: false
})

const errors = ref({})
const fileInput = ref(null)
const selectedFile = ref(null)
const filePreview = ref('')
const isDragOver = ref(false)
const uploading = ref(false)

const patientName = computed(() => {
  const patient = props.patient || {}
  const full = `${patient.first_name || ''} ${patient.last_name || ''}`.trim()

  return full || patient.full_name || `Paciente #${patient.id}`
})

const fieldError = key => {
  const error = errors.value?.[key]

  if (Array.isArray(error)) return error[0] || ''
  if (typeof error === 'string') return error

  return ''
}

const fileTypeIcon = file => {
  if (file.type.startsWith('image/')) return PhotoIcon
  if (file.type === 'application/pdf') return DocumentTextIcon
  if (file.type.includes('word') || file.type.includes('document')) return DocumentIcon

  return PaperClipIcon
}

const formatFileSize = bytes => {
  if (!bytes) return '0 B'

  const units = ['B', 'KB', 'MB', 'GB']
  let size = bytes
  let unit = 0

  while (size >= 1024 && unit < units.length - 1) {
    size /= 1024
    unit += 1
  }

  return `${Number(size.toFixed(2))} ${units[unit]}`
}

const openFilePicker = () => {
  fileInput.value?.click()
}

const resetFileState = () => {
  selectedFile.value = null
  filePreview.value = ''
  isDragOver.value = false

  if (fileInput.value) {
    fileInput.value.value = ''
  }
}

const setFile = file => {
  delete errors.value.file

  if (file.size > MAX_FILE_BYTES) {
    errors.value.file = 'El archivo supera el límite de 10 MB. Elige uno más ligero.'
    resetFileState()

    return
  }

  selectedFile.value = file

  if (file.type.startsWith('image/')) {
    const reader = new FileReader()
    reader.onload = event => {
      filePreview.value = event.target?.result || ''
    }
    reader.readAsDataURL(file)
  } else {
    filePreview.value = ''
  }
}

const onFileInput = event => {
  const file = event.target.files?.[0]

  if (file) setFile(file)
}

const onDrop = event => {
  isDragOver.value = false

  const file = event.dataTransfer?.files?.[0]

  if (file) setFile(file)
}

const clearFile = () => {
  resetFileState()
  delete errors.value.file
}

const submit = async () => {
  if (uploading.value) return

  errors.value = {}

  if (!selectedFile.value) {
    errors.value.file = 'Selecciona el archivo que quieres subir.'
  } else if (selectedFile.value.size > MAX_FILE_BYTES) {
    errors.value.file = 'El archivo supera el límite de 10 MB. Elige uno más ligero.'
  }

  if (!form.value.category) {
    errors.value.category = 'Selecciona una categoría.'
  }

  if (Object.keys(errors.value).length > 0) return

  const payload = new FormData()
  payload.append('file', selectedFile.value)
  payload.append('patient_id', props.patient.id)
  payload.append('category', form.value.category)
  payload.append('description', form.value.description)
  payload.append('is_private', form.value.is_private ? '1' : '0')

  uploading.value = true

  try {
    await uploadAttachment(payload)
    toast.success(`Adjunto subido: ${selectedFile.value.name}`)
    emit('uploaded')
  } catch (err) {
    const apiErrors = err?.response?.data?.errors

    if (apiErrors && typeof apiErrors === 'object') {
      errors.value = apiErrors
    } else {
      const message = err?.response?.data?.message || 'No pudimos subir el archivo.'
      errors.value.file = message
      toast.error(message)
    }
  } finally {
    uploading.value = false
  }
}
</script>
