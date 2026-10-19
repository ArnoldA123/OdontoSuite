<template>
  <UiModal
    :model-value="open"
    title="Importar procedimientos desde CSV"
    size="lg"
    @close="emit('close')"
  >
    <div class="space-y-5">
      <section>
        <h3 class="text-base font-semibold text-label">Formato esperado</h3>
        <p class="mt-1 text-sm text-theme-secondary">
          La primera fila debe ser el encabezado. Cada fila se valida por separado.
        </p>
        <code
          class="mt-3 block overflow-x-auto rounded-control border border-hairline bg-theme-surface-elevated p-3 text-xs text-theme-primary"
        >
          code,name,description,specialty_code,default_cost,default_duration_minutes,materials_needed,requires_anesthesia,requires_radiographs,contraindications,post_procedure_care,is_active
        </code>
        <ul class="mt-3 list-disc space-y-1 pl-5 text-xs text-theme-secondary">
          <li>
            <strong class="text-theme-primary">code</strong>
            (obligatorio): si existe, actualiza; si no, crea.
          </li>
          <li>
            <strong class="text-theme-primary">specialty_code</strong>
            (opcional): código de la especialidad (FK).
          </li>
          <li>
            Booleanos:
            <code>0/1</code>
            ,
            <code>true/false</code>
            ,
            <code>si/no</code>
            .
          </li>
          <li>
            Los errores por fila se reportan en
            <code>failed_rows</code>
            y no abortan el lote.
          </li>
        </ul>
      </section>

      <button
        type="button"
        data-import-dropzone
        class="w-full rounded-panel border-2 border-dashed border-theme p-8 text-center transition-colors hover:border-accent focus-visible:outline-none focus-visible:shadow-[var(--focus-ring-default)]"
        :class="{ 'border-accent bg-theme-surface': dragging }"
        @dragover.prevent="dragging = true"
        @dragleave.prevent="dragging = false"
        @drop.prevent="onDrop"
        @click="fileInput?.click()"
      >
        <ArrowUpTrayIcon class="mx-auto h-8 w-8 text-theme-secondary" aria-hidden="true" />
        <p v-if="!file" class="mt-3 text-sm text-theme-secondary">
          Arrastra un archivo CSV aquí o haz click para seleccionar
        </p>
        <p v-else class="mt-3 text-sm font-medium text-theme-primary">
          {{ file.name }} ({{ (file.size / 1024).toFixed(1) }} KB)
        </p>
        <input
          ref="fileInput"
          type="file"
          accept=".csv,.txt"
          class="hidden"
          @change="onFileChange"
        />
      </button>

      <div
        v-if="result"
        data-import-result
        class="rounded-panel border border-hairline bg-theme-surface-elevated p-4"
      >
        <div class="flex flex-wrap items-center gap-2">
          <UiStatusBadge
            :variant="result.errors > 0 ? 'warning' : 'success'"
            :label="result.errors > 0 ? 'Importación con observaciones' : 'Importación completa'"
            size="sm"
          />
          <p class="text-sm text-theme-primary">
            {{ result.inserted }} insertados, {{ result.updated }} actualizados,
            {{ result.errors }} errores.
          </p>
        </div>
        <details v-if="result.failed_rows?.length" class="mt-3">
          <summary class="cursor-pointer text-xs text-theme-secondary">
            Ver filas con error ({{ result.failed_rows.length }})
          </summary>
          <ul class="mt-2 max-h-40 space-y-1 overflow-y-auto text-xs">
            <li v-for="row in result.failed_rows" :key="row.row" class="text-theme-primary">
              Fila {{ row.row }}: {{ row.errors.join(', ') }}
            </li>
          </ul>
        </details>
      </div>

      <div
        v-if="error"
        data-import-error
        role="alert"
        class="rounded-control border border-systemRed-200 bg-systemRed-50 p-4 text-sm text-systemRed-700"
      >
        {{ error }}
      </div>
    </div>

    <template #footer>
      <div class="flex justify-end gap-2">
        <UiButton variant="secondary" @click="emit('close')">
          {{ result ? 'Cerrar' : 'Cancelar' }}
        </UiButton>
        <UiButton
          v-if="!result"
          :disabled="!file || uploading"
          :loading="uploading"
          @click="upload"
        >
          Importar
        </UiButton>
      </div>
    </template>
  </UiModal>
</template>

<script setup>
import { ref } from 'vue'
import { ArrowUpTrayIcon } from '@heroicons/vue/24/outline'
import { useApi } from '@/composables/useApi'
import UiButton from '../ui/Button.vue'
import UiModal from '../ui/Modal.vue'
import UiStatusBadge from '../ui/StatusBadge.vue'

defineProps({
  open: { type: Boolean, default: false }
})
const emit = defineEmits(['close', 'imported'])

const { post } = useApi()
const fileInput = ref(null)
const file = ref(null)
const dragging = ref(false)
const uploading = ref(false)
const result = ref(null)
const error = ref(null)

const onFileChange = event => {
  file.value = event.target.files?.[0] || null
  result.value = null
  error.value = null
}

const onDrop = event => {
  dragging.value = false
  file.value = event.dataTransfer.files?.[0] || null
  result.value = null
  error.value = null
}

const upload = async () => {
  if (!file.value) return
  uploading.value = true
  error.value = null
  try {
    const formData = new FormData()
    formData.append('file', file.value)
    const response = await post('/api/admin/procedure-catalog/import', formData, {
      headers: { 'Content-Type': 'multipart/form-data' }
    })
    result.value = response.data || null
    emit('imported', result.value)
  } catch (err) {
    error.value = err.response?.data?.message || 'Error al importar el archivo CSV'
  } finally {
    uploading.value = false
  }
}
</script>
