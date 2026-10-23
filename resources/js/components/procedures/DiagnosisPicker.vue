<template>
  <div class="space-y-3">
    <div class="relative">
      <UiInput
        id="cw-diagnosis-search"
        v-model="search"
        type="search"
        label="Buscar diagnóstico (CIE-10)"
        placeholder="Código o nombre del diagnóstico…"
        :error="searchError"
        data-diagnosis-search
        @update:model-value="onSearchInput"
      />
      <ul
        v-if="results.length"
        class="absolute z-20 left-0 right-0 mt-1 bg-theme-surface-elevated border border-hairline rounded-lg shadow-lg max-h-56 overflow-y-auto"
        data-diagnosis-results
      >
        <li v-for="opt in results" :key="opt.id">
          <button
            type="button"
            class="w-full text-left px-3 py-2 hover:bg-systemBlue-50"
            data-diagnosis-option
            @click="addDiagnosis(opt)"
          >
            <span class="font-mono text-xs text-theme-secondary">{{ opt.cie_code }}</span>
            <span class="block text-sm text-theme-primary">{{ opt.name }}</span>
          </button>
        </li>
      </ul>
      <p v-if="searching" class="mt-1 text-xs text-theme-secondary">Buscando en el catálogo…</p>
      <p v-else-if="term.length > 0 && term.length < 2" class="mt-1 text-xs text-theme-secondary">
        Escribe al menos dos caracteres.
      </p>
    </div>

    <div v-if="modelValue.length" class="space-y-2">
      <div
        v-for="(row, idx) in modelValue"
        :key="row.diagnosis_catalog_id"
        class="p-3 border border-hairline rounded-lg space-y-2"
        data-diagnosis-row
      >
        <div class="flex items-start justify-between gap-3">
          <div class="min-w-0">
            <p class="text-sm font-medium text-theme-primary">
              <span class="font-mono text-xs text-theme-secondary mr-2">{{ row.cie_code }}</span>
              {{ row.name }}
            </p>
            <p class="text-xs text-theme-secondary mt-1">Diagnóstico #{{ idx + 1 }}</p>
          </div>
          <button
            type="button"
            class="text-xs text-systemRed-600 hover:underline"
            data-diagnosis-remove
            @click="removeDiagnosis(idx)"
          >
            Quitar
          </button>
        </div>
        <UiSelect
          :model-value="row.type"
          :options="typeOptions"
          label="Tipo de diagnóstico"
          size="sm"
          @update:model-value="setType(idx, $event)"
        />
      </div>
    </div>
    <p v-else class="text-xs text-theme-secondary">
      Agrega al menos un diagnóstico codificado contra el catálogo CIE-10.
    </p>
  </div>
</template>

<script setup>
import { computed, ref } from 'vue'
import { useApi } from '../../composables/useApi'
import UiInput from '../ui/Input.vue'
import UiSelect from '../ui/Select.vue'

// F6 (#286): the close codes its diagnoses against the CIE-10 catalogue, so the
// wizard reads `GET /api/diagnoses` and hands back rows of
// { diagnosis_catalog_id, cie_code, name, type }. The code and the name travel
// with the row only to label it: the record freezes them again on the server.
const props = defineProps({
  modelValue: { type: Array, default: () => [] }
})

const emit = defineEmits(['update:modelValue'])

const DEFAULT_TYPE = 'definitive'

const typeOptions = [
  { value: 'definitive', label: 'Definitivo' },
  { value: 'presumptive', label: 'Presuntivo' },
  { value: 'rule_out', label: 'A descartar' }
]

const { get } = useApi()

const search = ref('')
const results = ref([])
const searching = ref(false)
const searchError = ref('')

// A stale response must not overwrite the results of a newer term, so every
// keystroke carries a sequence number and only the latest one is applied.
let requestSeq = 0

const term = computed(() => search.value.trim())

const emitRows = rows => emit('update:modelValue', rows)

const onSearchInput = async value => {
  const query = String(value ?? '').trim()
  requestSeq += 1
  const seq = requestSeq
  searchError.value = ''

  if (query.length < 2) {
    results.value = []
    searching.value = false
    return
  }

  searching.value = true
  try {
    const response = await get('/api/diagnoses', {
      params: { q: query, per_page: 20 }
    })
    if (seq !== requestSeq) return
    const chosen = new Set(props.modelValue.map(row => row.diagnosis_catalog_id))
    results.value = (response?.data ?? []).filter(opt => !chosen.has(opt.id))
  } catch (error) {
    if (seq !== requestSeq) return
    results.value = []
    searchError.value = 'No se pudo leer el catálogo de diagnósticos'
  } finally {
    if (seq === requestSeq) searching.value = false
  }
}

const addDiagnosis = opt => {
  if (!opt?.id) return
  if (props.modelValue.some(row => row.diagnosis_catalog_id === opt.id)) return

  emitRows([
    ...props.modelValue,
    {
      diagnosis_catalog_id: opt.id,
      cie_code: opt.cie_code,
      name: opt.name,
      type: DEFAULT_TYPE
    }
  ])
  search.value = ''
  results.value = []
}

const removeDiagnosis = idx => {
  emitRows(props.modelValue.filter((row, index) => index !== idx))
}

const setType = (idx, type) => {
  emitRows(
    props.modelValue.map((row, index) =>
      index === idx ? { ...row, type: type ?? DEFAULT_TYPE } : row
    )
  )
}
</script>
