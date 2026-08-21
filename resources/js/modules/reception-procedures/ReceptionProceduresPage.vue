<template>
  <AppLayout class="bg-canvas">
    <PageHeader
      title="Catálogo de Procedimientos"
      subtitle="Consulta los procedimientos disponibles y sus precios para orientar al paciente"
      class="mb-6"
    >
      <template #actions>
        <UiButton variant="secondary" @click="goBack">
          <template #icon-left>
            <svg class="w-4 h-4" fill="none" stroke="currentColor"
viewBox="0 0 24 24">
              <path
                stroke-linecap="round"
                stroke-linejoin="round"
                stroke-width="2"
                d="M10 19l-7-7m0 0l7-7m-7 7h18"
              />
            </svg>
          </template>
          Volver
        </UiButton>
      </template>
    </PageHeader>

    <UiCard variant="glass" class="mb-6">
      <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div class="md:col-span-2">
          <label class="block text-sm font-medium text-theme-primary mb-1">Buscar</label>
          <UiInput
            v-model="filters.search"
            type="search"
            placeholder="Buscar por nombre o código..."
            class="w-full"
          >
            <template #prefix>
              <svg
                class="w-4 h-4 text-theme-secondary"
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
            </template>
          </UiInput>
        </div>
        <div>
          <label class="block text-sm font-medium text-theme-primary mb-1">Especialidad</label>
          <UiSelect
            v-model="filters.specialty"
            :options="specialties.map(spec => ({ value: spec.code, label: spec.name }))"
            placeholder="Todas las especialidades"
            label="Especialidad"
          />
        </div>
      </div>
    </UiCard>

    <div v-if="loading" class="py-8 text-center">
      <LoadingSpinner />
    </div>

    <UiEmptyState
      v-else-if="!procedures.length"
      title="Sin resultados"
      description="Ajusta los filtros para ver más procedimientos."
    />

    <div v-else class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
      <UiCard
        v-for="proc in procedures"
        :key="proc.id"
        variant="elevated"
      >
        <div class="flex items-start justify-between gap-2 mb-2">
          <UiBadge variant="primary" size="sm" class="font-mono">
            {{ proc.code }}
          </UiBadge>
          <span class="text-xs text-theme-secondary">{{ proc.specialty_name || 'General' }}</span>
        </div>
        <h3 class="font-semibold text-theme-primary mb-2">
          {{ proc.name }}
        </h3>
        <p v-if="proc.description" class="text-sm text-theme-secondary mb-3 line-clamp-2">
          {{ proc.description }}
        </p>
        <div class="flex items-center justify-between border-t border-hairline pt-3 mt-3">
          <div>
            <div class="text-xs text-theme-secondary">Duración</div>
            <div class="text-sm font-medium text-theme-primary">
              {{ proc.default_duration_minutes }} min
            </div>
          </div>
          <div class="text-right">
            <div class="text-xs text-theme-secondary">Precio</div>
            <div
              class="text-lg font-bold text-systemBlue-600 tabular-nums"
              style="font-feature-settings: var(--font-features-tabular-nums)"
            >
              S/ {{ Number(proc.default_cost).toFixed(2) }}
            </div>
          </div>
        </div>
      </UiCard>
    </div>

    <div v-if="totalPages > 1" class="mt-6 flex justify-center">
      <UiPagination
        :current-page="currentPage"
        :total-pages="totalPages"
        :total="pagination.total"
        @page-change="onPageChange"
      />
    </div>
  </AppLayout>
</template>

<script setup>
import { ref, reactive, onMounted, watch } from 'vue'
import { useRouter } from 'vue-router'
import { useProcedureCatalog } from '../../composables/useProcedureCatalog'
import { useSpecialties } from '../../composables/useSpecialties'
import AppLayout from '../../components/layout/AppLayout.vue'
import UiButton from '../../components/ui/Button.vue'
import UiCard from '../../components/ui/Card.vue'
import UiPagination from '../../components/ui/Pagination.vue'
import LoadingSpinner from '../../components/ui/LoadingSpinner.vue'

const router = useRouter()
const { procedures, loading, pagination, getProcedures, currentPage, totalPages } =
  useProcedureCatalog()
const { specialties, getSpecialties } = useSpecialties()

const filters = reactive({
  search: '',
  specialty: '',
  page: 1,
  per_page: 12,
  is_active: true
})

let searchTimer = null
const onSearchInput = () => {
  clearTimeout(searchTimer)
  searchTimer = setTimeout(() => {
    filters.page = 1
    load()
  }, 350)
}

const load = async () => {
  await getProcedures({ ...filters })
}

const onPageChange = page => {
  filters.page = page
  load()
}

const goBack = () => router.push('/dashboard')

watch(
  () => filters.specialty,
  () => {
    filters.page = 1
    load()
  }
)

watch(() => filters.search, onSearchInput)

onMounted(async () => {
  await getSpecialties(true)
  await load()
})
</script>
