<template>
  <AppLayout>
    <div class="mb-8 animate-fade-in">
      <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
          <h1 class="text-3xl font-bold text-theme-primary mb-2">
            Mis Procedimientos
          </h1>
          <p class="text-theme-secondary">
            Marca tus procedimientos frecuentes como favoritos para acceder a ellos rapidamente
          </p>
        </div>
        <div class="flex gap-3">
          <UiButton variant="secondary" class="flex items-center gap-2" @click="goBack">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
            </svg>
            Volver
          </UiButton>
        </div>
      </div>
    </div>

    <UiCard variant="glass" class="mb-6">
      <h2 class="text-lg font-semibold text-theme-primary mb-3 flex items-center gap-2">
        <span class="text-yellow-500">⭐</span>
        Mis favoritos
        <span class="text-sm font-normal text-theme-secondary">
          ({{ favorites.length }})
        </span>
      </h2>

      <div v-if="loading && !favorites.length" class="py-4 text-center">
        <LoadingSpinner />
      </div>

      <div v-else-if="!favorites.length" class="py-6 text-center text-sm text-theme-secondary border-2 border-dashed border-theme rounded-lg">
        Aún no tienes favoritos. Marca los procedimientos que uses frecuentemente para acceder a ellos rapidamente.
      </div>

      <div v-else class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
        <div v-for="(fav, index) in favorites" :key="fav.id" class="border border-theme rounded-lg p-3 bg-theme-surface-elevated flex items-start justify-between gap-2">
          <div class="flex-1 min-w-0">
            <div class="flex items-center gap-2">
              <span class="font-mono text-xs text-theme-secondary">
                {{ fav.code }}
              </span>
              <span class="text-xs px-2 py-0.5 rounded-full bg-primary-50 text-primary-700">
                #{{ index + 1 }}
              </span>
            </div>
            <div class="font-medium text-theme-primary truncate">
              {{ fav.name }}
            </div>
            <div class="text-xs text-theme-secondary">
              {{ fav.specialty_name || 'Sin especialidad' }} ·
              {{ fav.default_duration_minutes }} min ·
              S/ {{ Number(fav.default_cost).toFixed(2) }}
            </div>
          </div>
          <div class="flex flex-col gap-1">
            <button type="button" :disabled="index === 0" class="p-1 text-theme-secondary hover:text-theme-primary disabled:opacity-30" title="Subir" @click="moveFav(index, -1)">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7" />
              </svg>
            </button>
            <button type="button" :disabled="index === favorites.length - 1" class="p-1 text-theme-secondary hover:text-theme-primary disabled:opacity-30" title="Bajar" @click="moveFav(index, 1)">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
              </svg>
            </button>
            <button type="button" class="p-1 text-red-500 hover:text-red-700" title="Quitar" @click="removeFav(fav)">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
              </svg>
            </button>
          </div>
        </div>
      </div>
    </UiCard>

    <UiCard variant="glass">
      <h2 class="text-lg font-semibold text-theme-primary mb-3">
        Explorar catalogo
      </h2>
      <p class="text-sm text-theme-secondary mb-4">
        Busca y marca como favorito cualquier procedimiento del catalogo. Si tienes especialidades asignadas, veras primero las tuyas.
      </p>

      <div class="relative mb-4">
        <input v-model="search" type="text" placeholder="Buscar por nombre o codigo..." class="w-full px-3 py-2 pl-9 border border-theme rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-accent bg-theme-surface-elevated text-theme-primary">
        <svg class="w-4 h-4 absolute left-3 top-3 text-theme-secondary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
        </svg>
      </div>

      <div v-if="loading && !forMe.length" class="py-4 text-center">
        <LoadingSpinner />
      </div>

      <div v-else class="max-h-[500px] overflow-y-auto border border-theme rounded-lg divide-y divide-theme">
        <div v-for="proc in filteredForMe" :key="proc.id" class="px-3 py-2 flex items-center justify-between gap-3 hover:bg-theme-surface">
          <div class="flex-1 min-w-0">
            <div class="flex items-center gap-2">
              <span class="font-mono text-xs text-theme-secondary">{{ proc.code }}</span>
              <span v-if="proc.is_favorite" class="text-yellow-500 text-xs">⭐ Favorito</span>
            </div>
            <div class="text-sm font-medium text-theme-primary truncate">
              {{ proc.name }}
            </div>
            <div class="text-xs text-theme-secondary">
              {{ proc.specialty_name || 'Sin especialidad' }} ·
              {{ proc.default_duration_minutes }} min ·
              S/ {{ Number(proc.default_cost).toFixed(2) }}
            </div>
          </div>
          <UiButton v-if="!proc.is_favorite" variant="secondary" size="sm" :disabled="loading" @click="addFav(proc)">
            Marcar favorito
          </UiButton>
        </div>
        <div v-if="!filteredForMe.length" class="px-3 py-4 text-center text-sm text-theme-secondary">
          Sin resultados
        </div>
      </div>
    </UiCard>
  </AppLayout>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useProcedureFavorites } from '../../composables/useProcedureFavorites'
import { useToast } from '../../composables/useToast'
import AppLayout from '../../components/layout/AppLayout.vue'
import UiButton from '../../components/ui/Button.vue'
import UiCard from '../../components/ui/Card.vue'
import LoadingSpinner from '../../components/ui/LoadingSpinner.vue'

const router = useRouter()
const toast = useToast()
const {
  favorites,
  forMe,
  loading,
  getFavorites,
  getForMe,
  addFavorite,
  removeFavorite,
  reorderFavorites
} = useProcedureFavorites()

const search = ref('')

const filteredForMe = computed(() => {
  if (!search.value) return forMe.value
  const term = search.value.toLowerCase()
  return forMe.value.filter(p =>
    p.name.toLowerCase().includes(term) ||
    p.code.toLowerCase().includes(term)
  )
})

const load = async () => {
  await Promise.all([getFavorites(), getForMe({ per_page: 100 })])
}

const addFav = async proc => {
  try {
    await addFavorite(proc.id)
    toast.success(`"${proc.name}" agregado a favoritos`)
  } catch (err) {
    toast.error('No se pudo agregar a favoritos')
  }
}

const removeFav = async fav => {
  try {
    await removeFavorite(fav.id)
    toast.success(`"${fav.name}" eliminado de favoritos`)
  } catch (err) {
    toast.error('No se pudo quitar de favoritos')
  }
}

const moveFav = async (index, delta) => {
  const newIndex = index + delta
  if (newIndex < 0 || newIndex >= favorites.value.length) return
  const reordered = [...favorites.value]
  const [moved] = reordered.splice(index, 1)
  reordered.splice(newIndex, 0, moved)
  favorites.value = reordered
  try {
    await reorderFavorites(reordered.map(f => f.id))
  } catch (err) {
    toast.error('No se pudo reordenar')
    load()
  }
}

const goBack = () => router.push('/dashboard')

onMounted(load)
</script>
