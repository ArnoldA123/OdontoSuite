<template>
  <UiModal v-if="open" @close="$emit('close')">
    <template #header>
      <h3 class="text-lg font-semibold text-theme-primary">Importar procedimientos desde CSV</h3>
    </template>

    <div class="space-y-4">
      <p class="text-sm text-theme-secondary">
        Sube un archivo CSV con el siguiente encabezado (la primera fila debe ser el header):
      </p>
      <code
        class="block p-3 bg-theme-surface-elevated text-xs rounded overflow-x-auto text-theme-primary"
      >
        code,name,description,specialty_code,default_cost,default_duration_minutes,materials_needed,requires_anesthesia,requires_radiographs,contraindications,post_procedure_care,is_active
      </code>
      <ul class="text-xs text-theme-secondary list-disc pl-5 space-y-1">
        <li>
          <strong>code</strong>
          (obligatorio): si existe, actualiza. Si no, crea.
        </li>
        <li>
          <strong>specialty_code</strong>
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
          Errores por fila se reportan en
          <code>failed_rows</code>
          , no abortan el batch.
        </li>
      </ul>

      <div
        class="border-2 border-dashed border-theme rounded-lg p-6 text-center cursor-pointer hover:border-primary-500 transition-colors"
        :class="{ 'border-primary-500 bg-primary-50': dragging }"
        @dragover.prevent="dragging = true"
        @dragleave.prevent="dragging = false"
        @drop.prevent="onDrop"
        @click="fileInput?.click()"
      >
        <input
          ref="fileInput"
          type="file"
          accept=".csv,.txt"
          class="hidden"
          @change="onFileChange"
        />
        <p v-if="!file" class="text-sm text-theme-secondary">
          Arrastra un archivo CSV aquí o haz click para seleccionar
        </p>
        <p v-else class="text-sm text-theme-primary font-medium">
          {{ file.name }} ({{ (file.size / 1024).toFixed(1) }} KB)
        </p>
      </div>

      <div
        v-if="result"
        class="p-4 rounded-lg border"
        :class="
          result.errors > 0
            ? 'border-warning-100 bg-warning-50'
            : 'border-success-100 bg-success-50'
        "
      >
        <p class="text-sm text-theme-primary font-medium">
          {{ result.inserted }} insertados, {{ result.updated }} actualizados,
          {{ result.errors }} errores.
        </p>
        <details v-if="result.failed_rows?.length" class="mt-2">
          <summary class="text-xs text-theme-secondary cursor-pointer">
            Ver filas con error ({{ result.failed_rows.length }})
          </summary>
          <ul class="mt-2 text-xs space-y-1 max-h-40 overflow-y-auto">
            <li v-for="fr in result.failed_rows" :key="fr.row" class="text-theme-primary">
              Fila {{ fr.row }}: {{ fr.errors.join(', ') }}
            </li>
          </ul>
        </details>
      </div>

      <div v-if="error" class="p-4 rounded-lg border border-red-200 bg-red-50 text-red-700 text-sm">
        {{ error }}
      </div>
    </div>

    <template #footer>
      <div class="flex justify-end gap-2">
        <UiButton variant="secondary" @click="$emit('close')">
          {{ result ? 'Cerrar' : 'Cancelar' }}
        </UiButton>
        <UiButton v-if="!result" :disabled="!file || uploading" @click="upload">
          {{ uploading ? 'Importando...' : 'Importar' }}
        </UiButton>
      </div>
    </template>
  </UiModal>
</template>

<script setup>
import { ref } from 'vue'
import { useApi } from '@/composables/useApi'

const props = defineProps({
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

const onFileChange = e => {
  file.value = e.target.files?.[0] || null
  result.value = null
  error.value = null
}

const onDrop = e => {
  dragging.value = false
  file.value = e.dataTransfer.files?.[0] || null
  result.value = null
  error.value = null
}

const upload = async () => {
  if (!file.value) return
  uploading.value = true
  error.value = null
  try {
    const fd = new FormData()
    fd.append('file', file.value)
    const response = await post('/api/admin/procedure-catalog/import', fd, {
      headers: { 'Content-Type': 'multipart/form-data' }
    })
    result.value = response.data || null
    emit('imported', result.value)
  } catch (err) {
    error.value = err.response?.data?.message || 'Error al importar'
  } finally {
    uploading.value = false
  }
}
</script>
