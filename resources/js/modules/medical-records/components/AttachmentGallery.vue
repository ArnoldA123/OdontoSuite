<template>
  <div class="attachment-gallery">
    <div class="gallery-header">
      <h3 class="gallery-title">Archivos Adjuntos</h3>
      <div class="gallery-actions">
        <button v-if="canUpload" class="btn btn-primary" @click="$emit('upload')">
          <PlusIcon class="w-4 h-4 mr-2" />
          Subir Archivo
        </button>
      </div>
    </div>

    <div v-if="attachments.length > 0" class="gallery-filters">
      <div class="filter-group">
        <label class="filter-label">Filtrar por tipo:</label>
        <select v-model="selectedCategory" class="filter-select">
          <option value="">Todos</option>
          <option value="image">Imágenes</option>
          <option value="document">Documentos</option>
          <option value="xray">Radiografías</option>
          <option value="other">Otros</option>
        </select>
      </div>
    </div>

    <div v-if="loading" class="gallery-loading">
      <div class="loading-spinner" />
      <p>Cargando archivos...</p>
    </div>

    <div v-else-if="filteredAttachments.length === 0" class="gallery-empty">
      <DocumentIcon class="w-12 h-12 text-theme-secondary mx-auto mb-4" />
      <p class="text-theme-secondary">
        {{
          attachments.length === 0
            ? 'No hay archivos adjuntos'
            : 'No se encontraron archivos con el filtro seleccionado'
        }}
      </p>
    </div>

    <div v-else class="gallery-grid">
      <div
        v-for="attachment in filteredAttachments"
        :key="attachment.id"
        class="attachment-card"
        @click="showPreview(attachment)"
      >
        <div class="attachment-preview">
          <img
            v-if="isImage(attachment)"
            :src="attachment.url"
            :alt="attachment.description"
            class="preview-image"
          />
          <div v-else class="preview-icon">
            <DocumentIcon v-if="attachment.file_type === 'document'" class="w-8 h-8" />
            <PhotoIcon v-else-if="attachment.file_type === 'xray'" class="w-8 h-8" />
            <DocumentTextIcon v-else class="w-8 h-8" />
          </div>
        </div>

        <div class="attachment-info">
          <h4 class="attachment-name">
            {{ attachment.filename }}
          </h4>
          <p class="attachment-description">
            {{ attachment.description || 'Sin descripción' }}
          </p>
          <div class="attachment-meta">
            <span class="attachment-type">{{ getFileTypeLabel(attachment.file_type) }}</span>
            <span class="attachment-date">{{ formatDate(attachment.created_at) }}</span>
          </div>
        </div>

        <div class="attachment-actions">
          <button class="action-btn" title="Descargar" @click.stop="downloadAttachment(attachment)">
            <ArrowDownTrayIcon class="w-4 h-4" />
          </button>

          <!-- AI Analysis Button for radiographies -->
          <AiAnalysisButton
            v-if="attachment.category === 'radiografia' && canAnalyzeWithAi"
            :attachment-id="attachment.id"
            :attachment="attachment"
            class="ai-analysis-wrapper"
            @analysis-completed="handleAnalysisCompleted"
            @view-analysis="viewAnalysis"
          />

          <button
            v-if="canDelete"
            class="action-btn action-btn-danger"
            title="Eliminar"
            @click.stop="deleteAttachment(attachment)"
          >
            <TrashIcon class="w-4 h-4" />
          </button>
        </div>
      </div>
    </div>

    <!-- Modal de preview -->
    <div v-if="previewAttachment" class="preview-modal" @click="closePreview">
      <div class="preview-content" @click.stop>
        <button class="preview-close" @click="closePreview">
          <XMarkIcon class="w-6 h-6" />
        </button>

        <div class="preview-body">
          <img
            v-if="isImage(previewAttachment)"
            :src="previewAttachment.url"
            :alt="previewAttachment.description"
            class="preview-image-full"
          />
          <div v-else class="preview-document">
            <DocumentIcon class="w-16 h-16 text-theme-secondary mx-auto mb-4" />
            <p class="text-theme-secondary">
              {{ previewAttachment.filename }}
            </p>
            <button class="btn btn-primary mt-4" @click="downloadAttachment(previewAttachment)">
              <ArrowDownTrayIcon class="w-4 h-4 mr-2" />
              Descargar
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue'
import { useMedicalRecords } from '@/composables/useMedicalRecords'
import { usePermissions } from '@/composables/usePermissions'
import { useConfirm } from '@/composables/useConfirm'
import AiAnalysisButton from '@/modules/ai-analysis/components/AiAnalysisButton.vue'
import {
  PlusIcon,
  DocumentIcon,
  PhotoIcon,
  DocumentTextIcon,
  ArrowDownTrayIcon,
  TrashIcon,
  XMarkIcon
} from '@heroicons/vue/24/outline'

const props = defineProps({
  attachments: {
    type: Array,
    default: () => []
  },
  loading: {
    type: Boolean,
    default: false
  }
})

const emit = defineEmits(['upload', 'delete', 'analysis-completed', 'view-analysis'])

const { deleteAttachment: removeAttachment } = useMedicalRecords()
const { can } = usePermissions()

const selectedCategory = ref('')
const previewAttachment = ref(null)

const canUpload = computed(() => can('medical-records.attachments.create'))
const canDelete = computed(() => can('medical-records.attachments.delete'))
const canAnalyzeWithAi = computed(() => can('ai-analysis.analyze'))

const filteredAttachments = computed(() => {
  if (!selectedCategory.value) return props.attachments
  return props.attachments.filter(attachment => attachment.file_type === selectedCategory.value)
})

const isImage = attachment => {
  return (
    attachment.file_type === 'image' || attachment.filename.match(/\.(jpg|jpeg|png|gif|webp)$/i)
  )
}

const getFileTypeLabel = type => {
  const labels = {
    image: 'Imagen',
    document: 'Documento',
    xray: 'Radiografía',
    other: 'Otro'
  }
  return labels[type] || 'Archivo'
}

const formatDate = date => {
  if (!date) return 'N/A'
  return new Date(date).toLocaleDateString('es-ES', {
    year: 'numeric',
    month: 'short',
    day: 'numeric'
  })
}

const showPreview = attachment => {
  previewAttachment.value = attachment
}

const closePreview = () => {
  previewAttachment.value = null
}

const downloadAttachment = attachment => {
  const link = document.createElement('a')
  link.href = attachment.url
  link.download = attachment.filename
  link.click()
}

const deleteAttachment = async attachment => {
  const ok = await confirm({
    title: 'Eliminar archivo adjunto',
    message: '¿Estás seguro de que quieres eliminar este archivo?',
    confirmText: 'Eliminar',
    variant: 'danger'
  })
  if (ok) {
    try {
      await removeAttachment(attachment.id)
      emit('delete', attachment)
    } catch (err) {}
  }
}

const handleAnalysisCompleted = analysis => {
  // Emit event to parent component if needed
  emit('analysis-completed', analysis)
}

const viewAnalysis = analysis => {
  // Emit event to parent component if needed
  emit('view-analysis', analysis)
}
</script>

<style scoped>
.attachment-gallery {
  @apply space-y-4;
}

.gallery-header {
  @apply flex items-center justify-between;
}

.gallery-title {
  @apply text-lg font-medium text-theme-primary;
}

.gallery-actions {
  @apply flex items-center space-x-2;
}

.gallery-filters {
  @apply flex items-center space-x-4;
}

.filter-group {
  @apply flex items-center space-x-2;
}

.filter-label {
  @apply text-sm font-medium text-theme-primary;
}

.filter-select {
  @apply px-3 py-1 border border-theme rounded-lg text-sm bg-theme-surface-elevated text-theme-primary;
}

.gallery-loading {
  @apply flex flex-col items-center justify-center py-8;
}

.loading-spinner {
  @apply animate-spin rounded-full h-8 w-8 border-b-2 border-primary-600 mb-2;
}

.gallery-empty {
  @apply text-center py-8;
}

.gallery-grid {
  @apply grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4;
}

.attachment-card {
  @apply bg-theme-surface-elevated border border-theme rounded-lg p-4 hover-lift transition-shadow cursor-pointer;
}

.attachment-preview {
  @apply w-full h-32 bg-theme-surface rounded-lg mb-3 flex items-center justify-center overflow-hidden;
}

.preview-image {
  @apply w-full h-full object-cover;
}

.preview-icon {
  @apply text-theme-secondary;
}

.attachment-info {
  @apply space-y-2;
}

.attachment-name {
  @apply text-sm font-medium text-theme-primary truncate;
}

.attachment-description {
  @apply text-xs text-theme-secondary line-clamp-2;
}

.attachment-meta {
  @apply flex items-center justify-between text-xs text-theme-secondary;
}

.attachment-actions {
  @apply flex items-center space-x-1 mt-3;
}

.ai-analysis-wrapper {
  @apply flex-shrink-0;
}

.action-btn {
  @apply p-1 text-theme-secondary hover:text-theme-primary transition-colors;
}

.action-btn-danger {
  @apply hover:text-red-600;
}

.preview-modal {
  @apply fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4;
}

.preview-content {
  @apply bg-theme-surface-elevated rounded-lg shadow-xl max-w-4xl w-full max-h-[90vh] overflow-hidden relative;
}

.preview-close {
  @apply absolute top-4 right-4 p-2 text-theme-secondary hover:text-theme-primary transition-colors z-10;
}

.preview-body {
  @apply p-6;
}

.preview-image-full {
  @apply max-w-full max-h-[70vh] object-contain mx-auto;
}

.preview-document {
  @apply text-center py-8;
}

.btn {
  @apply inline-flex items-center px-4 py-2 text-sm font-medium rounded-lg transition-colors;
}

.btn-primary {
  @apply bg-primary-600 text-white hover:bg-primary-700;
}
</style>
