<template>
  <div class="modal-overlay" @click="closeModal">
    <div class="modal-content" @click.stop>
      <div class="modal-header">
        <h2 class="modal-title">Subir Archivo</h2>
        <button class="modal-close" @click="closeModal">
          <XMarkIcon class="w-6 h-6" />
        </button>
      </div>

      <form class="modal-body" @submit.prevent="handleSubmit">
        <div
          class="upload-area"
          :class="{ 'drag-over': isDragOver }"
          @dragover.prevent="handleDragOver"
          @dragleave.prevent="handleDragLeave"
          @drop.prevent="handleDrop"
        >
          <div v-if="!selectedFile" class="upload-placeholder">
            <CloudArrowUpIcon class="w-12 h-12 text-theme-secondary mx-auto mb-4" />
            <p class="text-lg font-medium text-theme-primary">Arrastra y suelta tu archivo aquí</p>
            <p class="text-sm text-theme-secondary">o</p>
            <button type="button" class="btn btn-outline mt-2" @click="selectFile">
              Seleccionar archivo
            </button>
            <input
              ref="fileInput"
              type="file"
              class="hidden"
              accept="image/*,.pdf,.doc,.docx,.txt"
              @change="handleFileSelect"
            />
          </div>

          <div v-else class="file-preview">
            <div class="preview-container">
              <img
                v-if="isImage(selectedFile)"
                :src="filePreview"
                :alt="selectedFile.name"
                class="preview-image"
              />
              <div v-else class="preview-icon">
                <DocumentIcon class="w-12 h-12 text-theme-secondary" />
              </div>
            </div>
            <div class="file-info">
              <h4 class="file-name">
                {{ selectedFile.name }}
              </h4>
              <p class="file-size">
                {{ formatFileSize(selectedFile.size) }}
              </p>
            </div>
            <button type="button" class="remove-btn" @click="removeFile">
              <XMarkIcon class="w-4 h-4" />
            </button>
          </div>
        </div>

        <div class="form-section">
          <h3 class="section-title">Información del Archivo</h3>

          <div class="form-group">
            <label class="form-label">Categoría *</label>
            <select
              v-model="form.category"
              class="form-input"
              :class="{ 'border-red-500': errors.category }"
              required
            >
              <option value="">Seleccionar categoría</option>
              <option value="image">Imagen</option>
              <option value="document">Documento</option>
              <option value="xray">Radiografía</option>
              <option value="other">Otro</option>
            </select>
            <p v-if="errors.category" class="form-error">
              {{ errors.category }}
            </p>
          </div>

          <div class="form-group">
            <label class="form-label">Descripción</label>
            <textarea
              v-model="form.description"
              class="form-textarea"
              rows="3"
              placeholder="Descripción del archivo..."
            />
          </div>

          <div class="form-group">
            <label class="form-label">Fecha del Archivo</label>
            <input v-model="form.file_date" type="date" class="form-input" >
          </div>

          <div class="form-group">
            <label class="form-label">Notas Adicionales</label>
            <textarea
              v-model="form.notes"
              class="form-textarea"
              rows="2"
              placeholder="Notas adicionales sobre el archivo..."
            />
          </div>
        </div>
      </form>

      <div class="modal-footer">
        <button type="button" class="btn btn-outline" :disabled="loading"
@click="closeModal">
          Cancelar
        </button>
        <button
          type="submit"
          class="btn btn-primary"
          :disabled="loading || !selectedFile"
          @click="handleSubmit"
        >
          <CloudArrowUpIcon class="w-4 h-4 mr-2" />
          {{ loading ? 'Subiendo...' : 'Subir Archivo' }}
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue'
import { useMedicalRecords } from '@/composables/useMedicalRecords'
import { XMarkIcon, CloudArrowUpIcon, DocumentIcon } from '@heroicons/vue/24/outline'

const props = defineProps({
  patient: {
    type: Object,
    required: true
  }
})

const emit = defineEmits(['close', 'uploaded'])

const { addAttachment, loading } = useMedicalRecords()

const fileInput = ref(null)
const selectedFile = ref(null)
const filePreview = ref(null)
const isDragOver = ref(false)

const form = ref({
  category: '',
  description: '',
  file_date: '',
  notes: ''
})

const errors = ref({})

const isImage = file => {
  return file.type.startsWith('image/')
}

const formatFileSize = bytes => {
  if (bytes === 0) return '0 Bytes'
  const k = 1024
  const sizes = ['Bytes', 'KB', 'MB', 'GB']
  const i = Math.floor(Math.log(bytes) / Math.log(k))
  return `${parseFloat((bytes / Math.pow(k, i)).toFixed(2))} ${sizes[i]}`
}

const selectFile = () => {
  fileInput.value?.click()
}

const handleFileSelect = event => {
  const file = event.target.files[0]
  if (file) {
    setSelectedFile(file)
  }
}

const handleDragOver = event => {
  event.preventDefault()
  isDragOver.value = true
}

const handleDragLeave = event => {
  event.preventDefault()
  isDragOver.value = false
}

const handleDrop = event => {
  event.preventDefault()
  isDragOver.value = false

  const { files } = event.dataTransfer
  if (files.length > 0) {
    setSelectedFile(files[0])
  }
}

const setSelectedFile = file => {
  selectedFile.value = file

  if (isImage(file)) {
    const reader = new FileReader()
    reader.onload = e => {
      filePreview.value = e.target.result
    }
    reader.readAsDataURL(file)
  }
}

const removeFile = () => {
  selectedFile.value = null
  filePreview.value = null
  if (fileInput.value) {
    fileInput.value.value = ''
  }
}

const handleSubmit = async () => {
  try {
    errors.value = {}

    if (!selectedFile.value) {
      errors.value.file = 'Debes seleccionar un archivo'
      return
    }

    if (!form.value.category) {
      errors.value.category = 'La categoría es obligatoria'
      return
    }

    const formData = new FormData()
    formData.append('file', selectedFile.value)
    formData.append('patient_id', props.patient.id)
    formData.append('category', form.value.category)
    formData.append('description', form.value.description)
    formData.append('file_date', form.value.file_date)
    formData.append('notes', form.value.notes)

    await addAttachment(formData)
    emit('uploaded')
    closeModal()
  } catch (err) {
    if (err.response?.data?.errors) {
      errors.value = err.response.data.errors
    }
  }
}

const closeModal = () => {
  emit('close')
}
</script>

<style scoped>
.modal-overlay {
  @apply fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4;
}

.modal-content {
  @apply bg-theme-surface-elevated rounded-lg shadow-xl max-w-2xl w-full max-h-[90vh] overflow-y-auto;
}

.modal-header {
  @apply flex items-center justify-between p-6 border-b border-theme;
}

.modal-title {
  @apply text-xl font-semibold text-theme-primary;
}

.modal-close {
  @apply p-2 text-theme-secondary hover:text-theme-primary transition-colors;
}

.modal-body {
  @apply p-6;
}

.upload-area {
  @apply border-2 border-dashed border-theme rounded-lg p-8 text-center transition-colors;
}

.upload-area.drag-over {
  @apply border-primary-500 bg-primary-50;
}

.upload-placeholder {
  @apply space-y-2;
}

.file-preview {
  @apply flex items-center space-x-4 p-4 bg-theme-surface rounded-lg;
}

.preview-container {
  @apply flex-shrink-0;
}

.preview-image {
  @apply w-16 h-16 object-cover rounded-lg;
}

.preview-icon {
  @apply w-16 h-16 flex items-center justify-center;
}

.file-info {
  @apply flex-1 min-w-0;
}

.file-name {
  @apply text-sm font-medium text-theme-primary truncate;
}

.file-size {
  @apply text-xs text-theme-secondary;
}

.remove-btn {
  @apply p-1 text-theme-secondary hover:text-theme-primary transition-colors;
}

.form-section {
  @apply mt-6 space-y-4;
}

.section-title {
  @apply text-lg font-medium text-theme-primary border-b border-theme pb-2;
}

.form-group {
  @apply space-y-2;
}

.form-label {
  @apply block text-sm font-medium text-theme-primary;
}

.form-input {
  @apply block w-full px-3 py-2 border border-theme rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent bg-theme-surface-elevated text-theme-primary;
}

.form-textarea {
  @apply block w-full px-3 py-2 border border-theme rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent resize-none bg-theme-surface-elevated text-theme-primary;
}

.form-error {
  @apply text-sm text-red-600;
}

.modal-footer {
  @apply flex items-center justify-end space-x-3 p-6 border-t border-theme;
}

.btn {
  @apply inline-flex items-center px-4 py-2 text-sm font-medium rounded-lg transition-colors;
}

.btn-outline {
  @apply border border-theme text-theme-primary hover:bg-theme-surface;
}

.btn-primary {
  @apply bg-primary-600 text-white hover:bg-primary-700;
}
</style>
