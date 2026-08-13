<template>
  <div class="file-upload">
    <!-- Área de Drop -->
    <div
      class="drop-zone"
      :class="[{ dragging: isDragging }]"
      @drop.prevent="handleDrop"
      @dragover.prevent="isDragging = true"
      @dragleave="isDragging = false"
    >
      <!-- Vista Previa si hay imagen -->
      <div v-if="previewUrl" class="preview-container">
        <img :src="previewUrl" class="preview-image" >
        <button class="clear-btn" @click="clearFile">
          <XMarkIcon class="w-5 h-5" />
        </button>
      </div>

      <!-- Área de Upload -->
      <div v-else class="upload-prompt">
        <PhotoIcon class="w-12 h-12 text-theme-secondary mx-auto mb-3" />
        <p class="text-lg font-medium mb-2">
Arrastra tu imagen aquí o haz clic para seleccionar
</p>
        <p class="text-sm text-theme-secondary mb-4">
Formatos: JPG, PNG, DICOM (Max 20MB)
</p>
        <input
          ref="fileInput"
          type="file"
          :accept="accept"
          class="hidden"
          @change="handleFileInput"
        />
        <UiButton variant="secondary" @click="$refs.fileInput.click()">
          Seleccionar Archivo
        </UiButton>
      </div>
    </div>

    <!-- Info del archivo -->
    <div v-if="file" class="file-info">
      <DocumentIcon class="w-5 h-5" />
      <span>{{ file.name }}</span>
      <span class="text-theme-secondary">({{ formatFileSize(file.size) }})</span>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, watch } from 'vue'
import { PhotoIcon, DocumentIcon, XMarkIcon } from '@heroicons/vue/24/outline'
import UiButton from '@/components/ui/Button.vue'

const props = defineProps({
  accept: {
    type: String,
    default: 'image/*'
  },
  maxSize: {
    type: Number,
    default: 20 * 1024 * 1024 // 20MB
  },
  preview: {
    type: Boolean,
    default: true
  }
})

const emit = defineEmits(['file-selected', 'file-cleared'])

const file = ref(null)
const previewUrl = ref('')
const isDragging = ref(false)
const fileInput = ref(null)

const handleDrop = event => {
  isDragging.value = false
  const { files } = event.dataTransfer
  if (files.length > 0) {
    handleFile(files[0])
  }
}

const handleFileInput = event => {
  const { files } = event.target
  if (files.length > 0) {
    handleFile(files[0])
  }
}

const handleFile = selectedFile => {
  // Validar tamaño
  if (selectedFile.size > props.maxSize) {
    alert(`El archivo es demasiado grande. Máximo ${formatFileSize(props.maxSize)}`)
    return
  }

  // Validar tipo
  if (!selectedFile.type.startsWith('image/')) {
    alert('Solo se permiten archivos de imagen')
    return
  }

  file.value = selectedFile

  // Crear preview si está habilitado
  if (props.preview && selectedFile.type.startsWith('image/')) {
    const reader = new FileReader()
    reader.onload = e => {
      previewUrl.value = e.target.result
    }
    reader.readAsDataURL(selectedFile)
  }

  emit('file-selected', selectedFile)
}

const clearFile = () => {
  file.value = null
  previewUrl.value = ''
  if (fileInput.value) {
    fileInput.value.value = ''
  }
  emit('file-cleared')
}

const formatFileSize = bytes => {
  if (bytes === 0) return '0 Bytes'
  const k = 1024
  const sizes = ['Bytes', 'KB', 'MB', 'GB']
  const i = Math.floor(Math.log(bytes) / Math.log(k))
  return `${parseFloat((bytes / Math.pow(k, i)).toFixed(2))} ${sizes[i]}`
}

// Limpiar preview cuando se cambie el archivo
watch(file, newFile => {
  if (!newFile && previewUrl.value) {
    URL.revokeObjectURL(previewUrl.value)
    previewUrl.value = ''
  }
})
</script>

<style scoped>
.file-upload {
  @apply w-full;
}

.drop-zone {
  @apply border-2 border-dashed border-theme rounded-ios p-8 text-center transition-colors cursor-pointer;
}

.drop-zone:hover,
.drop-zone.dragging {
  @apply border-accent bg-primary-50;
}

.preview-container {
  @apply relative;
}

.preview-image {
  @apply w-full h-48 object-cover rounded-ios;
}

.clear-btn {
  @apply absolute top-2 right-2 bg-error-500 text-white rounded-full p-1 hover:bg-error-600 transition-colors;
}

.upload-prompt {
  @apply flex flex-col items-center;
}

.file-info {
  @apply flex items-center gap-2 mt-3 p-3 bg-theme-surface rounded-ios;
}
</style>
