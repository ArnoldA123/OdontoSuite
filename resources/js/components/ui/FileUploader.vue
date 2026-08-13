<template>
  <div class="file-uploader">
    <div
      class="upload-area"
      :class="[isDragOver ? 'drag-over' : '', hasFiles ? 'has-files' : '']"
      @dragover.prevent="handleDragOver"
      @dragleave.prevent="handleDragLeave"
      @drop.prevent="handleDrop"
      @click="triggerFileInput"
    >
      <input
        ref="fileInput"
        type="file"
        :multiple="multiple"
        :accept="acceptedTypes"
        class="hidden"
        @change="handleFileSelect"
      />

      <div class="upload-content">
        <div v-if="!hasFiles" class="upload-placeholder">
          <CloudArrowUpIcon class="w-12 h-12 text-theme-secondary mx-auto mb-4" />
          <div class="text-lg font-medium text-theme-primary mb-2">
            {{ placeholder }}
          </div>
          <div class="text-sm text-theme-secondary mb-4">
            Arrastra archivos aquí o haz clic para seleccionar
          </div>
          <div class="text-xs text-theme-secondary">
            Formatos permitidos: {{ acceptedTypesText }}
          </div>
        </div>

        <div v-else class="upload-files">
          <div class="text-lg font-medium text-theme-primary mb-4">
            Archivos seleccionados ({{ files.length }})
          </div>
          <div class="space-y-2">
            <div v-for="(file, index) in files" :key="index" class="file-item">
              <div class="flex items-center justify-between p-3 bg-theme-surface rounded-ios">
                <div class="flex items-center space-x-3">
                  <div class="file-icon">
                    <DocumentIcon v-if="isDocument(file)" class="w-5 h-5 text-accent" />
                    <PhotoIcon v-else-if="isImage(file)" class="w-5 h-5 text-green-500" />
                    <DocumentTextIcon v-else class="w-5 h-5 text-theme-secondary" />
                  </div>
                  <div>
                    <div class="font-medium text-theme-primary">
                      {{ file.name }}
                    </div>
                    <div class="text-sm text-theme-secondary">
                      {{ formatFileSize(file.size) }}
                    </div>
                  </div>
                </div>
                <button
                  class="text-red-500 hover:text-red-700 transition-colors"
                  @click="removeFile(index)"
                >
                  <XMarkIcon class="w-5 h-5" />
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Preview de imágenes -->
    <div v-if="showPreviews && imageFiles.length > 0" class="mt-4">
      <div class="text-sm font-medium text-theme-primary mb-2">Vista previa:</div>
      <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <div v-for="(file, index) in imageFiles" :key="index" class="relative group">
          <img
            :src="file.preview"
            :alt="file.name"
            class="w-full h-24 object-cover rounded-ios border border-theme"
          />
          <button
            class="absolute -top-2 -right-2 bg-red-500 text-white rounded-full p-1 opacity-0 group-hover:opacity-100 transition-opacity"
            @click="removeFile(getFileIndex(file))"
          >
            <XMarkIcon class="w-4 h-4" />
          </button>
        </div>
      </div>
    </div>

    <!-- Botones de acción -->
    <div v-if="hasFiles" class="flex justify-between mt-4">
      <button
        class="px-4 py-2 text-sm text-theme-secondary hover:text-theme-primary transition-colors"
        @click="clearAll"
      >
        Limpiar todos
      </button>
      <button
        class="px-4 py-2 text-sm text-primary-600 hover:text-primary-800 transition-colors"
        @click="triggerFileInput"
      >
        Agregar más archivos
      </button>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, watch } from 'vue'
import {
  CloudArrowUpIcon,
  DocumentIcon,
  PhotoIcon,
  DocumentTextIcon,
  XMarkIcon
} from '@heroicons/vue/24/outline'

const props = defineProps({
  modelValue: {
    type: Array,
    default: () => []
  },
  multiple: {
    type: Boolean,
    default: true
  },
  acceptedTypes: {
    type: String,
    default: 'image/*,.pdf,.doc,.docx'
  },
  maxFiles: {
    type: Number,
    default: 10
  },
  maxSize: {
    type: Number,
    default: 10 * 1024 * 1024 // 10MB
  },
  placeholder: {
    type: String,
    default: 'Subir archivos'
  },
  showPreviews: {
    type: Boolean,
    default: true
  }
})

const emit = defineEmits(['update:modelValue', 'change', 'error'])

// Estado reactivo
const files = ref([...props.modelValue])
const isDragOver = ref(false)
const fileInput = ref(null)

// Computed
const hasFiles = computed(() => files.value.length > 0)
const imageFiles = computed(() => files.value.filter(file => isImage(file)))
const acceptedTypesText = computed(() => {
  const types = props.acceptedTypes.split(',')
  return types
    .map(type => {
      if (type === 'image/*') return 'Imágenes'
      if (type === '.pdf') return 'PDF'
      if (type === '.doc,.docx') return 'Word'
      return type
    })
    .join(', ')
})

// Métodos
const triggerFileInput = () => {
  fileInput.value?.click()
}

const handleFileSelect = event => {
  const selectedFiles = Array.from(event.target.files)
  addFiles(selectedFiles)
}

const handleDragOver = event => {
  isDragOver.value = true
}

const handleDragLeave = event => {
  isDragOver.value = false
}

const handleDrop = event => {
  isDragOver.value = false
  const droppedFiles = Array.from(event.dataTransfer.files)
  addFiles(droppedFiles)
}

const addFiles = newFiles => {
  const validFiles = []
  const errors = []

  for (const file of newFiles) {
    // Validar tamaño
    if (file.size > props.maxSize) {
      errors.push(
        `El archivo ${file.name} es demasiado grande (máximo ${formatFileSize(props.maxSize)})`
      )
      continue
    }

    // Validar tipo
    if (!isValidFileType(file)) {
      errors.push(`El archivo ${file.name} no es de un tipo permitido`)
      continue
    }

    // Validar límite de archivos
    if (files.value.length + validFiles.length >= props.maxFiles) {
      errors.push(`No se pueden agregar más de ${props.maxFiles} archivos`)
      break
    }

    // Crear preview para imágenes
    if (isImage(file)) {
      file.preview = URL.createObjectURL(file)
    }

    validFiles.push(file)
  }

  if (errors.length > 0) {
    emit('error', errors)
  }

  if (validFiles.length > 0) {
    files.value.push(...validFiles)
    emitChange()
  }
}

const removeFile = index => {
  const file = files.value[index]

  // Limpiar preview si es imagen
  if (file.preview) {
    URL.revokeObjectURL(file.preview)
  }

  files.value.splice(index, 1)
  emitChange()
}

const clearAll = () => {
  // Limpiar todos los previews
  files.value.forEach(file => {
    if (file.preview) {
      URL.revokeObjectURL(file.preview)
    }
  })

  files.value = []
  emitChange()
}

const getFileIndex = file => {
  return files.value.findIndex(f => f === file)
}

const isValidFileType = file => {
  const acceptedTypes = props.acceptedTypes.split(',')
  return acceptedTypes.some(type => {
    if (type.startsWith('.')) {
      return file.name.toLowerCase().endsWith(type.toLowerCase())
    }
    if (type.endsWith('/*')) {
      const baseType = type.slice(0, -2)
      return file.type.startsWith(baseType)
    }
    return file.type === type
  })
}

const isImage = file => {
  return file.type.startsWith('image/')
}

const isDocument = file => {
  return file.type.includes('pdf') || file.type.includes('document')
}

const formatFileSize = bytes => {
  if (bytes === 0) return '0 Bytes'
  const k = 1024
  const sizes = ['Bytes', 'KB', 'MB', 'GB']
  const i = Math.floor(Math.log(bytes) / Math.log(k))
  return `${parseFloat((bytes / Math.pow(k, i)).toFixed(2))} ${sizes[i]}`
}

const emitChange = () => {
  emit('update:modelValue', [...files.value])
  emit('change', [...files.value])
}

// Watchers
watch(
  () => props.modelValue,
  newValue => {
    files.value = [...newValue]
  }
)
</script>

<style scoped>
.upload-area {
  @apply border-2 border-dashed border-theme rounded-ios p-8 text-center cursor-pointer transition-all duration-200;
}

.upload-area:hover {
  @apply border-accent bg-primary-50;
}

.upload-area.drag-over {
  @apply border-accent bg-primary-100;
}

.upload-area.has-files {
  @apply border-solid border-theme bg-theme-surface;
}

.upload-content {
  @apply w-full;
}

.upload-placeholder {
  @apply flex flex-col items-center;
}

.upload-files {
  @apply w-full;
}

.file-item {
  @apply w-full;
}

.file-icon {
  @apply flex-shrink-0;
}
</style>
