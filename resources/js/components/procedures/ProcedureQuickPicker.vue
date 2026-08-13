<template>
  <div class="space-y-3">
    <div v-if="loading" class="py-4 text-center">
      <LoadingSpinner />
    </div>

    <template v-else>
      <div v-if="favorites.length" class="space-y-2">
        <p class="text-xs uppercase text-theme-secondary flex items-center gap-1">
          <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20">
            <path
              d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.286 3.957a1 1 0 00.95.69h4.162c.969 0 1.371 1.24.588 1.81l-3.367 2.446a1 1 0 00-.364 1.118l1.286 3.957c.3.921-.755 1.688-1.539 1.118l-3.366-2.446a1 1 0 00-1.176 0l-3.366 2.446c-.783.57-1.838-.197-1.539-1.118l1.286-3.957a1 1 0 00-.364-1.118L2.05 9.384c-.783-.57-.38-1.81.588-1.81h4.162a1 1 0 00.95-.69l1.286-3.957z"
            />
          </svg>
          Mis favoritos
        </p>
        <div class="flex flex-wrap gap-2">
          <button
            v-for="fav in favorites"
            :key="`fav-${fav.id}`"
            type="button"
            class="px-3 py-2 rounded-lg border text-sm transition-colors text-left"
            :class="
              modelValue === fav.id
                ? 'border-accent bg-accent text-white'
                : 'border-theme bg-theme-surface-elevated text-theme-primary hover:border-accent'
            "
            @click="select(fav)"
          >
            <div class="font-medium">
              {{ fav.code }}
            </div>
            <div class="text-xs opacity-80">
              {{ fav.name }}
            </div>
          </button>
        </div>
      </div>

      <div v-if="forMe.length" class="space-y-2">
        <p class="text-xs uppercase text-theme-secondary">
          {{ favorites.length ? 'Todos mis procedimientos' : 'Procedimientos disponibles' }}
        </p>
        <div class="relative">
          <input
            v-model="search"
            type="text"
            placeholder="Buscar por nombre o codigo..."
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
        <div class="max-h-64 overflow-y-auto border border-theme rounded-lg divide-y divide-theme">
          <button
            v-for="proc in filteredCatalog"
            :key="proc.id"
            type="button"
            class="w-full px-3 py-2 text-left hover:bg-theme-surface transition-colors flex items-start justify-between gap-2"
            :class="modelValue === proc.id ? 'bg-accent text-white hover:bg-accent' : ''"
            @click="select(proc)"
          >
            <div class="flex-1 min-w-0">
              <div class="flex items-center gap-2">
                <span class="font-mono text-xs opacity-70">{{ proc.code }}</span>
                <span
                  v-if="proc.is_favorite"
                  :class="modelValue === proc.id ? 'text-white' : 'text-yellow-500'"
                >
                  ⭐
                </span>
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
            v-if="!filteredCatalog.length"
            class="px-3 py-4 text-center text-sm text-theme-secondary"
          >
            Sin resultados
          </div>
        </div>
      </div>

      <div
        v-if="!favorites.length && !forMe.length"
        class="text-center py-6 text-sm text-theme-secondary"
      >
        No hay procedimientos disponibles
      </div>
    </template>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import { useProcedureFavorites } from '../../composables/useProcedureFavorites'
import LoadingSpinner from '../ui/LoadingSpinner.vue'

const props = defineProps({
  modelValue: { type: [Number, null], default: null },
  specialty: { type: String, default: '' }
})

const emit = defineEmits(['update:modelValue', 'select'])

const { favorites, forMe, loading, getFavorites, getForMe } = useProcedureFavorites()

const search = ref('')

const filteredCatalog = computed(() => {
  if (!search.value) return forMe.value
  const term = search.value.toLowerCase()
  return forMe.value.filter(
    p => p.name.toLowerCase().includes(term) || p.code.toLowerCase().includes(term)
  )
})

const select = proc => {
  emit('update:modelValue', proc.id)
  emit('select', proc)
}

const load = async () => {
  await Promise.all([
    getFavorites(),
    getForMe(props.specialty ? { specialty: props.specialty } : {})
  ])
}

watch(
  () => props.specialty,
  () => load()
)

onMounted(load)
</script>
