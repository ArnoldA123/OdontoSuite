<template>
  <AppLayout>
    <header
      data-my-procedures-hero
      class="mb-6 p-6 md:p-8"
      style="
        background: var(--color-canvas);
        border: 1px solid var(--color-hairline);
        border-radius: var(--radius-panel);
        box-shadow: var(--elevation-2);
      "
    >
      <button
        type="button"
        class="inline-flex items-center gap-1 text-sm font-medium text-theme-secondary hover:text-theme-primary"
        @click="goBack"
      >
        <ArrowLeftIcon class="w-4 h-4" aria-hidden="true" />
        Volver al panel
      </button>
      <div class="mt-3 flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
        <div class="min-w-0">
          <p class="text-xs font-medium uppercase tracking-wide text-theme-secondary">
            Catálogo clínico
          </p>
          <h1 class="mt-2 text-4xl font-semibold text-label" data-hero-title>Mis Procedimientos</h1>
          <p class="mt-2 text-sm text-theme-secondary">
            Marca tus procedimientos frecuentes para acceder a ellos rápidamente
          </p>
        </div>
        <UiStatusBadge
          variant="info"
          :label="`${favorites.length} favoritos`"
          data-favorites-count
        />
      </div>
    </header>

    <div
      v-if="loadError"
      data-my-procedures-error
      role="alert"
      class="rounded-ios bg-systemRed-50 p-8 text-center"
      style="border: 1px solid var(--color-hairline)"
    >
      <ExclamationTriangleIcon class="mx-auto h-10 w-10 text-systemRed-600" aria-hidden="true" />
      <p class="mt-3 text-base font-medium text-theme-primary">
        No pudimos cargar tus procedimientos
      </p>
      <div class="mt-4">
        <UiButton variant="primary" size="sm" @click="load">Reintentar</UiButton>
      </div>
    </div>

    <div v-else class="space-y-6">
      <section data-favorites-section aria-label="Mis favoritos">
        <div class="mb-3 flex items-baseline gap-3">
          <h2 class="text-2xl font-semibold text-label">Mis favoritos</h2>
          <p class="text-sm text-theme-secondary">Tu acceso rápido, en el orden que elijas</p>
        </div>

        <UiCard variant="glass">
          <div v-if="loading && !favorites.length" class="py-8">
            <LoadingSpinner text="Cargando favoritos..." />
          </div>
          <UiEmptyState
            v-else-if="!favorites.length"
            title="Sin favoritos"
            description="Marca los procedimientos que uses con frecuencia para tenerlos a mano."
          />
          <ul v-else class="divide-y divide-hairline" data-favorites-list>
            <li
              v-for="(favorite, index) in favorites"
              :key="favorite.id"
              data-favorite-row
              class="flex items-center gap-3 py-3 first:pt-0 last:pb-0"
            >
              <span
                class="w-8 flex-shrink-0 text-center text-sm font-semibold text-theme-secondary tabular-nums"
              >
                {{ index + 1 }}
              </span>
              <div class="min-w-0 flex-1">
                <div class="flex flex-wrap items-center gap-2">
                  <span class="font-mono text-xs text-theme-secondary">{{ favorite.code }}</span>
                  <p class="truncate text-sm font-medium text-theme-primary">{{ favorite.name }}</p>
                </div>
                <p class="mt-0.5 text-xs text-theme-secondary">
                  {{ favorite.specialty_name || 'Sin especialidad' }} ·
                  {{ favorite.default_duration_minutes }} min ·
                  {{ formatCurrency(favorite.default_cost) }}
                </p>
              </div>
              <div class="flex flex-shrink-0 items-center gap-1">
                <UiButton
                  variant="ghost"
                  size="sm"
                  :disabled="index === 0"
                  title="Subir"
                  aria-label="Subir favorito"
                  @click="moveFav(index, -1)"
                >
                  <ArrowUpIcon class="h-4 w-4" aria-hidden="true" />
                </UiButton>
                <UiButton
                  variant="ghost"
                  size="sm"
                  :disabled="index === favorites.length - 1"
                  title="Bajar"
                  aria-label="Bajar favorito"
                  @click="moveFav(index, 1)"
                >
                  <ArrowDownIcon class="h-4 w-4" aria-hidden="true" />
                </UiButton>
                <UiButton
                  variant="ghost"
                  size="sm"
                  class="text-systemRed-600 hover:text-systemRed-700"
                  title="Quitar de favoritos"
                  aria-label="Quitar de favoritos"
                  @click="removeFav(favorite)"
                >
                  <TrashIcon class="h-4 w-4" aria-hidden="true" />
                </UiButton>
              </div>
            </li>
          </ul>
        </UiCard>
      </section>

      <section data-suggestions-section aria-label="Sugerencias para ti">
        <div class="mb-3 flex items-baseline gap-3">
          <h2 class="text-2xl font-semibold text-label">Sugerencias para ti</h2>
          <p class="text-sm text-theme-secondary">
            Procedimientos de tus especialidades que aún no marcas
          </p>
        </div>

        <UiCard variant="glass">
          <UiEmptyState
            v-if="!suggested.length"
            title="Sin sugerencias pendientes"
            description="Ya marcaste como favoritos todos los procedimientos de tus especialidades."
          />
          <div v-else class="grid grid-cols-1 gap-3 md:grid-cols-2 xl:grid-cols-3">
            <article
              v-for="procedure in suggested"
              :key="procedure.id"
              data-suggestion-row
              class="rounded-panel border border-hairline bg-theme-surface-elevated p-4"
            >
              <div class="flex items-start justify-between gap-2">
                <div class="min-w-0">
                  <p class="font-mono text-xs text-theme-secondary">{{ procedure.code }}</p>
                  <p class="mt-1 truncate text-sm font-medium text-theme-primary">
                    {{ procedure.name }}
                  </p>
                </div>
                <UiButton
                  variant="secondary"
                  size="sm"
                  class="flex-shrink-0"
                  @click="addFav(procedure)"
                >
                  <template #icon-left>
                    <PlusIcon class="h-4 w-4" aria-hidden="true" />
                  </template>
                  Favorito
                </UiButton>
              </div>
              <p class="mt-2 text-xs text-theme-secondary">
                {{ procedure.specialty_name || 'Sin especialidad' }} ·
                {{ procedure.default_duration_minutes }} min ·
                {{ formatCurrency(procedure.default_cost) }}
              </p>
            </article>
          </div>
        </UiCard>
      </section>

      <section data-explorer-section aria-label="Explorar catálogo">
        <div class="mb-3 flex items-baseline gap-3">
          <h2 class="text-2xl font-semibold text-label">Explorar catálogo</h2>
          <p class="text-sm text-theme-secondary">
            Busca cualquier procedimiento activo y márcalo como favorito
          </p>
        </div>

        <UiCard variant="glass">
          <div class="max-w-xl">
            <UiInput
              v-model="explorerTerm"
              data-explore-search
              placeholder="Buscar por nombre o código..."
              class="w-full"
            >
              <template #prefix>
                <MagnifyingGlassIcon class="w-5 h-5 text-theme-secondary" aria-hidden="true" />
              </template>
            </UiInput>
          </div>

          <div v-if="explorerLoading" class="py-6">
            <LoadingSpinner text="Buscando procedimientos..." />
          </div>
          <div
            v-else-if="explorerError"
            data-explorer-error
            role="alert"
            class="mt-4 rounded-control bg-systemRed-50 p-4 text-sm text-systemRed-700"
          >
            No pudimos buscar en el catálogo. Inténtalo de nuevo.
          </div>
          <UiEmptyState
            v-else-if="explorerTerm.trim() && !explorerResults.length"
            title="Sin resultados"
            description="Ningún procedimiento activo coincide con tu búsqueda."
          />
          <ul v-else-if="explorerResults.length" class="mt-4 divide-y divide-hairline">
            <li
              v-for="procedure in explorerResults"
              :key="procedure.id"
              data-explorer-row
              class="flex items-center gap-3 py-3 first:pt-0 last:pb-0"
            >
              <div class="min-w-0 flex-1">
                <div class="flex flex-wrap items-center gap-2">
                  <span class="font-mono text-xs text-theme-secondary">{{ procedure.code }}</span>
                  <p class="truncate text-sm font-medium text-theme-primary">
                    {{ procedure.name }}
                  </p>
                </div>
                <p class="mt-0.5 text-xs text-theme-secondary">
                  {{ procedure.specialty_name || 'Sin especialidad' }} ·
                  {{ procedure.default_duration_minutes }} min ·
                  {{ formatCurrency(procedure.default_cost) }}
                </p>
              </div>
              <UiStatusBadge
                v-if="isFavorite(procedure.id)"
                variant="success"
                label="Favorito"
                size="sm"
              />
              <UiButton v-else variant="secondary" size="sm" @click="addFav(procedure)">
                <template #icon-left>
                  <PlusIcon class="h-4 w-4" aria-hidden="true" />
                </template>
                Marcar favorito
              </UiButton>
            </li>
          </ul>
          <p v-else class="mt-4 text-sm text-theme-secondary">
            Escribe un nombre o código para buscar en todo el catálogo.
          </p>
        </UiCard>
      </section>
    </div>
  </AppLayout>
</template>

<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import {
  ArrowDownIcon,
  ArrowLeftIcon,
  ArrowUpIcon,
  ExclamationTriangleIcon,
  MagnifyingGlassIcon,
  PlusIcon,
  TrashIcon
} from '@heroicons/vue/24/outline'
import { useApi } from '../../composables/useApi'
import { useProcedureFavorites } from '../../composables/useProcedureFavorites'
import { useToast } from '../../composables/useToast'
import { formatCurrency } from '../../composables/useFormatters'
import AppLayout from '../../components/layout/AppLayout.vue'
import UiButton from '../../components/ui/Button.vue'
import UiCard from '../../components/ui/Card.vue'
import UiEmptyState from '../../components/ui/EmptyState.vue'
import UiInput from '../../components/ui/Input.vue'
import UiStatusBadge from '../../components/ui/StatusBadge.vue'
import LoadingSpinner from '../../components/ui/LoadingSpinner.vue'

const router = useRouter()
const { get } = useApi()
const toast = useToast()
const {
  favorites,
  forMe,
  loading,
  isFavorite,
  getFavorites,
  getForMe,
  addFavorite,
  removeFavorite,
  reorderFavorites
} = useProcedureFavorites()

const loadError = ref(false)
const explorerTerm = ref('')
const explorerResults = ref([])
const explorerLoading = ref(false)
const explorerError = ref(false)

let explorerTimer = null

const suggested = computed(() => forMe.value.filter(procedure => !isFavorite(procedure.id)))

const load = async () => {
  loadError.value = false
  try {
    await Promise.all([getFavorites(), getForMe({ per_page: 100 })])
  } catch {
    loadError.value = true
  }
}

const addFav = async procedure => {
  try {
    await addFavorite(procedure.id)
    toast.success(`"${procedure.name}" agregado a favoritos`)
  } catch {
    toast.error('No se pudo agregar a favoritos')
  }
}

const removeFav = async favorite => {
  try {
    await removeFavorite(favorite.id)
    toast.success(`"${favorite.name}" eliminado de favoritos`)
  } catch {
    toast.error('No se pudo quitar de favoritos')
  }
}

const moveFav = async (index, delta) => {
  const nextIndex = index + delta
  if (nextIndex < 0 || nextIndex >= favorites.value.length) return
  const reordered = [...favorites.value]
  const [moved] = reordered.splice(index, 1)
  reordered.splice(nextIndex, 0, moved)
  favorites.value = reordered
  try {
    await reorderFavorites(reordered.map(favorite => favorite.id))
  } catch {
    toast.error('No se pudo reordenar los favoritos')
    load()
  }
}

const searchCatalog = async term => {
  explorerLoading.value = true
  explorerError.value = false
  try {
    const response = await get(
      `/api/procedure-catalog/search?q=${encodeURIComponent(term)}&limit=20`
    )
    explorerResults.value = response.data || []
  } catch {
    explorerError.value = true
    explorerResults.value = []
  } finally {
    explorerLoading.value = false
  }
}

watch(explorerTerm, value => {
  clearTimeout(explorerTimer)
  const term = value.trim()
  if (!term) {
    explorerResults.value = []
    explorerError.value = false
    explorerLoading.value = false
    return
  }
  explorerTimer = setTimeout(() => searchCatalog(term), 300)
})

const goBack = () => router.push('/dashboard')

onMounted(load)
</script>
