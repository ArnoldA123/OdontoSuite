<template>
  <div class="space-y-3">
    <div v-if="loading && !procedures.length" class="py-4 text-center">
      <LoadingSpinner />
    </div>

    <template v-else>
      <div class="relative">
        <input
          v-model="search"
          type="text"
          placeholder="Buscar por nombre o código..."
          class="w-full px-3 py-2 pl-9 border border-theme rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-accent bg-theme-surface-elevated text-theme-primary"
        >
        <svg
          class="w-4 h-4 absolute left-3 top-3 text-theme-secondary"
          fill="none"
          stroke="currentColor"
          viewBox="0 0 24 24"
        >
          <path
            stroke-linecap="round"
            stroke-linejoin="round"
            stroke-width="2"
            d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"
          />
        </svg>
      </div>
      <div class="max-h-72 overflow-y-auto border border-theme rounded-lg divide-y divide-theme">
        <button
          v-for="proc in filteredProcedures"
          :key="proc.id"
          type="button"
          class="w-full px-3 py-2 text-left hover:bg-theme-surface transition-colors flex items-start justify-between gap-2"
          :class="modelValue === proc.id ? 'bg-accent text-white hover:bg-accent' : ''"
          @click="select(proc)"
        >
          <div class="flex-1 min-w-0">
            <div class="flex items-center gap-2">
              <span class="font-mono text-xs opacity-70">{{ proc.code }}</span>
            </div>
            <div class="text-sm font-medium truncate">
              {{ proc.name }}
            </div>
            <div class="text-xs opacity-70">
              {{ proc.specialty_name || 'Sin especialidad' }} ·
              {{ proc.default_duration_minutes }} min · S/
              {{ Number(proc.default_cost).toFixed(2) }}
            </div>
          </div>
        </button>
        <div
          v-if="!filteredProcedures.length"
          class="px-3 py-4 text-center text-sm text-theme-secondary"
        >
          Sin resultados
        </div>
      </div>

      <div v-if="totalPages > 1" class="flex justify-center pt-2">
        <UiPagination
          :current-page="currentPage"
          :total-pages="totalPages"
          :total="pagination.total"
          :per-page="pagination.per_page"
          @page-change="onPageChange"
        />
      </div>
    </template>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import { useProcedureCatalog } from '../../composables/useProcedureCatalog'
import UiPagination from '../ui/Pagination.vue'
import LoadingSpinner from '../ui/LoadingSpinner.vue'

const props = defineProps({
  modelValue: { type: [Number, null], default: null },
  specialty: { type: String, default: '' }
})

const emit = defineEmits(['update:modelValue', 'select'])

const { procedures, loading, pagination, currentPage, totalPages, getProcedures } =
  useProcedureCatalog()

const search = ref('')

const filteredProcedures = computed(() => {
  if (!search.value) return procedures.value
  const term = search.value.toLowerCase()
  return procedures.value.filter(
    p => p.name.toLowerCase().includes(term) || p.code.toLowerCase().includes(term)
  )
})

const select = proc => {
  emit('update:modelValue', proc.id)
  emit('select', proc)
}

const onPageChange = page => {
  getProcedures({ page, per_page: 12, is_active: true, specialty: props.specialty || '' })
}

let searchTimer = null
const onSearchInput = () => {
  clearTimeout(searchTimer)
  searchTimer = setTimeout(() => {
    getProcedures({ page: 1, per_page: 12, is_active: true, specialty: props.specialty || '' })
  }, 350)
}

watch(() => search.value, onSearchInput)
watch(
  () => props.specialty,
  () => {
    getProcedures({ page: 1, per_page: 12, is_active: true, specialty: props.specialty || '' })
  }
)

onMounted(() => {
  getProcedures({ page: 1, per_page: 12, is_active: true, specialty: props.specialty || '' })
})
</script>
