<template>
  <div class="space-y-4" data-attachment-gallery>
    <div class="flex flex-wrap items-start justify-between gap-3">
      <div class="min-w-0">
        <h3 class="text-lg font-semibold text-label">Archivos adjuntos</h3>
        <p class="mt-1 text-sm text-theme-secondary">
          Radiografías, fotos clínicas y documentos del expediente
        </p>
        <p class="mt-1 text-xs text-theme-secondary" data-attachments-scope>
          Los adjuntos pertenecen al expediente completo del paciente; no se limitan a la historia
          activa.
        </p>
      </div>
      <UiButton
        v-if="canUpload && attachments.length > 0"
        variant="secondary"
        size="sm"
        class="flex-none whitespace-nowrap"
        data-upload-attachment
        @click="emit('upload')"
      >
        <template #icon-left>
          <CloudArrowUpIcon class="w-4 h-4" aria-hidden="true" />
        </template>
        Subir adjunto
      </UiButton>
    </div>

    <LoadingSpinner v-if="loading" class="p-12" size="lg" text="Cargando adjuntos..." />

    <UiSectionEmpty
      v-else-if="attachments.length === 0"
      data-state="empty-attachments"
      title="Sin archivos adjuntos"
      description="Sube radiografías, fotos clínicas o documentos para completar el expediente del paciente."
    >
      <UiButton v-if="canUpload" variant="primary" size="sm" @click="emit('upload')">
        <template #icon-left>
          <CloudArrowUpIcon class="w-4 h-4" aria-hidden="true" />
        </template>
        Subir el primer adjunto
      </UiButton>
    </UiSectionEmpty>

    <template v-else>
      <div class="flex flex-wrap items-center justify-between gap-3">
        <p class="text-xs font-medium uppercase text-theme-secondary" data-attachment-count>
          {{ filteredAttachments.length }} de {{ attachments.length }}
        </p>
        <UiSelect
          v-model="categoryFilter"
          :options="categoryOptions"
          size="sm"
          class="w-full sm:w-56"
          data-category-filter
          aria-label="Filtrar adjuntos por categoría"
        />
      </div>

      <UiSectionEmpty
        v-if="filteredAttachments.length === 0"
        data-state="empty-attachments-filter"
        title="Sin adjuntos en esta categoría"
        description="Prueba con otra categoría o vuelve a ver todos los archivos del expediente."
      >
        <UiButton
          variant="secondary"
          size="sm"
          data-clear-category-filter
          @click="categoryFilter = 'all'"
        >
          Ver todas
        </UiButton>
      </UiSectionEmpty>

      <div
        v-else
        class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4"
        data-attachment-grid
      >
        <UiCard
          v-for="attachment in filteredAttachments"
          :key="attachment.id"
          variant="glass"
          padding="none"
          class="flex h-full flex-col"
          :data-attachment-id="attachment.id"
          :data-attachment-category="attachment.category"
        >
          <button
            v-if="isImage(attachment)"
            type="button"
            class="block h-36 w-full overflow-hidden bg-theme-surface"
            :aria-label="`Ver vista previa de ${attachment.original_name}`"
            data-attachment-preview-trigger
            @click="openPreview(attachment)"
          >
            <img
              :src="fileUrl(attachment)"
              :alt="attachment.original_name"
              loading="lazy"
              class="h-full w-full object-cover"
            />
          </button>
          <div
            v-else
            class="flex h-36 w-full items-center justify-center bg-theme-surface"
            data-attachment-type-icon
          >
            <component
              :is="fileTypeIcon(attachment.file_type)"
              class="h-10 w-10 text-theme-secondary"
              aria-hidden="true"
            />
          </div>

          <div class="flex h-full flex-1 flex-col gap-3 border-t border-hairline p-4">
            <div class="min-w-0">
              <p class="truncate text-sm font-medium text-label" :title="attachment.original_name">
                {{ attachment.original_name }}
              </p>
              <p
                v-if="attachment.description"
                class="mt-1 line-clamp-2 text-xs text-theme-secondary"
              >
                {{ attachment.description }}
              </p>
            </div>

            <div class="flex flex-wrap items-center gap-2">
              <StatusBadge
                :label="categoryLabel(attachment.category)"
                :variant="categoryVariant(attachment.category)"
                size="sm"
              />
              <span class="text-xs text-theme-secondary tabular-nums">
                {{ attachment.file_size_human || '—' }}
              </span>
            </div>

            <div
              class="mt-auto flex items-center justify-between gap-2 border-t border-hairline pt-3"
            >
              <p class="text-xs text-theme-secondary tabular-nums" data-attachment-date>
                <span v-if="formatDate(attachment.created_at)">
                  Subido el {{ formatDate(attachment.created_at) }}
                </span>
                <span v-else>Sin fecha</span>
              </p>
              <div class="flex flex-none items-center gap-1">
                <a
                  :href="fileUrl(attachment)"
                  :download="attachment.original_name"
                  :aria-label="`Descargar ${attachment.original_name}`"
                  class="inline-flex h-8 w-8 items-center justify-center rounded-ios text-theme-secondary transition-colors hover:bg-theme-surface hover:text-theme-primary focus-visible:outline-none focus-visible:shadow-[var(--focus-ring-default)]"
                  data-download-attachment
                >
                  <ArrowDownTrayIcon class="w-4 h-4" aria-hidden="true" />
                </a>
                <UiButton
                  v-if="canDelete"
                  variant="icon"
                  size="sm"
                  :aria-label="`Eliminar ${attachment.original_name}`"
                  data-delete-attachment
                  @click="askDelete(attachment)"
                >
                  <template #icon-left>
                    <TrashIcon class="w-4 h-4" aria-hidden="true" />
                  </template>
                </UiButton>
              </div>
            </div>
          </div>
        </UiCard>
      </div>
    </template>

    <UiModal
      v-if="previewAttachment"
      model-value
      size="xl"
      :title="previewAttachment.original_name"
      data-attachment-preview
      @close="closePreview"
    >
      <div class="space-y-4">
        <img
          :src="fileUrl(previewAttachment)"
          :alt="previewAttachment.original_name"
          class="mx-auto max-h-[70vh] w-auto max-w-full rounded-ios object-contain"
        />
        <div
          class="flex flex-wrap items-center justify-between gap-2 border-t border-hairline pt-3"
        >
          <p class="text-xs text-theme-secondary tabular-nums">
            {{ categoryLabel(previewAttachment.category) }}
            <span aria-hidden="true">·</span>
            {{ previewAttachment.file_size_human || '—' }}
            <span aria-hidden="true">·</span>
            {{ formatDate(previewAttachment.created_at) || 'Sin fecha' }}
          </p>
          <a
            :href="fileUrl(previewAttachment)"
            :download="previewAttachment.original_name"
            class="inline-flex items-center gap-2 rounded-ios px-3 py-1.5 text-sm font-medium text-accent transition-colors hover:bg-theme-surface focus-visible:outline-none focus-visible:shadow-[var(--focus-ring-default)]"
          >
            <ArrowDownTrayIcon class="w-4 h-4" aria-hidden="true" />
            Descargar
          </a>
        </div>
      </div>
    </UiModal>
  </div>
</template>

<script setup>
import { computed, ref } from 'vue'
import {
  ArrowDownTrayIcon,
  CloudArrowUpIcon,
  DocumentIcon,
  DocumentTextIcon,
  PaperClipIcon,
  PhotoIcon,
  TrashIcon
} from '@heroicons/vue/24/outline'
import { formatDate } from '@/composables/useFormatters'
import { useConfirm } from '@/composables/useConfirm'
import { usePermissions } from '@/composables/usePermissions'
import UiButton from '@/components/ui/Button.vue'
import UiCard from '@/components/ui/Card.vue'
import UiModal from '@/components/ui/Modal.vue'
import UiSectionEmpty from '@/components/ui/UiSectionEmpty.vue'
import UiSelect from '@/components/ui/Select.vue'
import LoadingSpinner from '@/components/ui/LoadingSpinner.vue'
import StatusBadge from '@/components/ui/StatusBadge.vue'

const props = defineProps({
  attachments: {
    type: Array,
    default: () => []
  },
  record: {
    type: Object,
    default: null
  },
  loading: {
    type: Boolean,
    default: false
  }
})

const emit = defineEmits(['upload', 'delete'])

const { can } = usePermissions()
const { confirm } = useConfirm()

const canUpload = computed(() => can.uploadAttachment?.value === true)
const canDelete = computed(() => can.deleteAttachment?.value === true)

const CATEGORY_META = {
  radiografia: { label: 'Radiografía', variant: 'info' },
  foto_clinica: { label: 'Foto clínica', variant: 'success' },
  documento: { label: 'Documento', variant: 'neutral' },
  otro: { label: 'Otro', variant: 'warning' },
  general: { label: 'General', variant: 'neutral' }
}

const FILE_TYPE_ICONS = {
  image: PhotoIcon,
  pdf: DocumentTextIcon,
  document: DocumentIcon,
  other: PaperClipIcon
}

const categoryOptions = [
  { value: 'all', label: 'Todas' },
  { value: 'radiografia', label: 'Radiografía' },
  { value: 'foto_clinica', label: 'Foto clínica' },
  { value: 'documento', label: 'Documento' },
  { value: 'otro', label: 'Otro' }
]

const categoryFilter = ref('all')
const previewAttachment = ref(null)

const filteredAttachments = computed(() => {
  if (categoryFilter.value === 'all') return props.attachments

  return props.attachments.filter(attachment => attachment.category === categoryFilter.value)
})

const categoryMeta = category => CATEGORY_META[category] || { label: category || 'Sin categoría' }

const categoryLabel = category => categoryMeta(category).label
const categoryVariant = category => categoryMeta(category).variant || 'neutral'
const fileTypeIcon = fileType => FILE_TYPE_ICONS[fileType] || FILE_TYPE_ICONS.other

const fileUrl = attachment => `/storage/${attachment.file_path}`

const isImage = attachment => (attachment?.mime_type || '').startsWith('image/')

const openPreview = attachment => {
  if (!isImage(attachment)) return

  previewAttachment.value = attachment
}

const closePreview = () => {
  previewAttachment.value = null
}

const askDelete = async attachment => {
  if (!canDelete.value) return

  const ok = await confirm({
    title: 'Eliminar adjunto',
    message: `¿Eliminar «${attachment.original_name}» del expediente? El archivo dejará de estar disponible.`,
    confirmText: 'Eliminar',
    variant: 'danger'
  })

  if (ok) {
    emit('delete', attachment)
  }
}
</script>
