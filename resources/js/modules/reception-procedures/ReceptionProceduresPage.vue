<template>
  <AppLayout>
    <header
      data-reception-hero
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
          <p class="text-xs font-medium uppercase tracking-wide text-theme-secondary">Recepción</p>
          <h2 class="mt-2 text-4xl font-semibold text-label" data-hero-title>
            Catálogo de Procedimientos
          </h2>
          <p class="mt-2 text-sm text-theme-secondary">
            Consulta precios y duración para orientar al paciente
          </p>
        </div>
        <UiButton variant="secondary" @click="goBack">
          <template #icon-left>
            <ArrowLeftIcon class="w-4 h-4" aria-hidden="true" />
          </template>
          Volver
        </UiButton>
      </div>
    </header>

    <section aria-label="Resumen de la página" class="mb-6 grid grid-cols-1 gap-4 md:grid-cols-3">
      <UiCard
        variant="glass"
        padding="sm"
        data-kpi-card="total"
        :style="{ boxShadow: 'var(--elevation-2)', borderColor: 'var(--color-hairline)' }"
      >
        <p class="text-xs font-medium uppercase text-theme-secondary">Procedimientos activos</p>
        <p class="mt-2 text-3xl font-bold text-label tabular-nums leading-none">
          {{ pagination.total ?? 0 }}
        </p>
        <p class="mt-1 text-xs text-theme-secondary">En el catálogo completo</p>
      </UiCard>
      <UiCard
        variant="glass"
        padding="sm"
        data-kpi-card="avg-price"
        :style="{ boxShadow: 'var(--elevation-2)', borderColor: 'var(--color-hairline)' }"
      >
        <p class="text-xs font-medium uppercase text-theme-secondary">Precio medio</p>
        <p class="mt-2 text-3xl font-bold text-label tabular-nums leading-none">
          {{ formatCurrency(pageAverageCost) }}
        </p>
        <p class="mt-1 text-xs text-theme-secondary">De los procedimientos visibles</p>
      </UiCard>
      <UiCard
        variant="glass"
        padding="sm"
        data-kpi-card="avg-duration"
        :style="{ boxShadow: 'var(--elevation-2)', borderColor: 'var(--color-hairline)' }"
      >
        <p class="text-xs font-medium uppercase text-theme-secondary">Duración media</p>
        <p class="mt-2 text-3xl font-bold text-label tabular-nums leading-none">
          {{ pageAverageDuration }} min
        </p>
        <p class="mt-1 text-xs text-theme-secondary">De los procedimientos visibles</p>
      </UiCard>
    </section>

    <UiCard variant="glass" class="mb-6">
      <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
        <div class="md:col-span-2">
          <UiInput
            v-model="filters.q"
            data-reception-search
            type="search"
            label="Buscar"
            placeholder="Buscar por nombre o código..."
            class="w-full"
          >
            <template #prefix>
              <MagnifyingGlassIcon class="w-5 h-5 text-theme-secondary" aria-hidden="true" />
            </template>
          </UiInput>
        </div>
        <div>
          <label class="mb-1 block text-sm font-medium text-theme-primary">Especialidad</label>
          <UiSelect
            v-model="filters.specialty"
            :options="specialtyOptions"
            placeholder="Todas las especialidades"
            label="Especialidad"
          />
        </div>
      </div>
    </UiCard>

    <div v-if="loading" class="py-10 text-center">
      <LoadingSpinner text="Cargando procedimientos..." />
    </div>

    <div
      v-else-if="loadError"
      data-reception-error
      role="alert"
      class="rounded-ios bg-systemRed-50 p-10 text-center"
      style="border: 1px solid var(--color-hairline)"
    >
      <ExclamationTriangleIcon
        class="mx-auto h-12 w-12 mb-4 text-systemRed-600"
        aria-hidden="true"
      />
      <p class="text-base font-medium text-theme-primary">
        No pudimos cargar el catálogo de procedimientos
      </p>
      <p class="mt-1 max-w-md mx-auto text-sm text-theme-secondary">
        Revisa tu conexión e inténtalo de nuevo.
      </p>
      <div class="mt-6">
        <UiButton variant="primary" size="md" data-retry-reception @click="load">
          Reintentar
        </UiButton>
      </div>
    </div>

    <UiEmptyState
      v-else-if="!procedures.length"
      title="Sin resultados"
      description="Ajusta los filtros para ver más procedimientos."
    />

    <div v-else class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
      <UiCard
        v-for="procedure in procedures"
        :key="procedure.id"
        variant="glass"
        data-procedure-card
      >
        <div class="flex items-start justify-between gap-2">
          <UiBadge variant="primary" size="sm" class="tabular-nums">{{ procedure.code }}</UiBadge>
          <span class="text-xs text-theme-secondary">
            {{ procedure.specialty_name || 'General' }}
          </span>
        </div>
        <h3 class="mt-3 text-base font-semibold text-label">{{ procedure.name }}</h3>
        <p class="mt-1 line-clamp-2 text-sm text-theme-secondary">
          {{ procedure.description || 'Sin descripción registrada' }}
        </p>
        <div class="mt-4 flex items-end justify-between gap-3 border-t border-hairline pt-3">
          <div>
            <p class="text-xs text-theme-secondary">Duración</p>
            <p class="text-sm font-medium text-theme-primary tabular-nums">
              {{ procedure.default_duration_minutes }} min
            </p>
          </div>
          <div class="text-right">
            <p class="text-xs text-theme-secondary">Precio</p>
            <p
              class="text-lg font-bold text-systemBlue-600 tabular-nums"
              style="font-feature-settings: var(--font-features-tabular-nums)"
            >
              {{ formatCurrency(procedure.default_cost) }}
            </p>
          </div>
        </div>
      </UiCard>
    </div>

    <div v-if="!loading && !loadError && totalPages > 1" class="mt-6 flex justify-center">
      <Pagination
        :current-page="currentPage"
        :total-pages="totalPages"
        :total="pagination.total"
        @page-change="onPageChange"
      />
    </div>
  </AppLayout>
</template>

<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import {
  ArrowLeftIcon,
  ExclamationTriangleIcon,
  MagnifyingGlassIcon
} from '@heroicons/vue/24/outline'
import { useProcedureCatalog } from '../../composables/useProcedureCatalog'
import { useSpecialties } from '../../composables/useSpecialties'
import { formatCurrency } from '../../composables/useFormatters'
import AppLayout from '../../components/layout/AppLayout.vue'
import UiBadge from '../../components/ui/Badge.vue'
import UiButton from '../../components/ui/Button.vue'
import UiCard from '../../components/ui/Card.vue'
import UiEmptyState from '../../components/ui/EmptyState.vue'
import UiInput from '../../components/ui/Input.vue'
import UiSelect from '../../components/ui/Select.vue'
import LoadingSpinner from '../../components/ui/LoadingSpinner.vue'
import Pagination from '../../components/ui/Pagination.vue'

const router = useRouter()
const { procedures, loading, pagination, getProcedures, currentPage, totalPages } =
  useProcedureCatalog()
const { specialties, getSpecialties } = useSpecialties()

const loadError = ref(false)
const filters = reactive({
  q: '',
  specialty: '',
  page: 1,
  per_page: 12,
  is_active: true
})

const specialtyOptions = computed(() => [
  { value: '', label: 'Todas las especialidades' },
  ...specialties.value.map(specialty => ({ value: specialty.code, label: specialty.name }))
])

const pageAverageCost = computed(() => {
  if (!procedures.value.length) return 0
  const total = procedures.value.reduce(
    (sum, procedure) => sum + Number(procedure.default_cost || 0),
    0
  )
  return total / procedures.value.length
})

const pageAverageDuration = computed(() => {
  if (!procedures.value.length) return 0
  const total = procedures.value.reduce(
    (sum, procedure) => sum + Number(procedure.default_duration_minutes || 0),
    0
  )
  return Math.round(total / procedures.value.length)
})

let searchTimer = null

const load = async () => {
  loadError.value = false
  try {
    await getProcedures({ ...filters })
  } catch {
    loadError.value = true
  }
}

const onSearchInput = () => {
  clearTimeout(searchTimer)
  searchTimer = setTimeout(() => {
    filters.page = 1
    load()
  }, 350)
}

const onPageChange = page => {
  filters.page = page
  load()
}

watch(() => filters.q, onSearchInput)
watch(
  () => filters.specialty,
  () => {
    filters.page = 1
    load()
  }
)

const goBack = () => router.push('/dashboard')

onMounted(async () => {
  await getSpecialties(true)
  await load()
})
</script>
