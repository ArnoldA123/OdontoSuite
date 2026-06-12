<template>
  <AppLayout>
    <div class="mb-8 animate-fade-in">
      <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
          <h1 class="text-3xl font-bold text-theme-primary mb-2">
            Catálogo de Procedimientos
          </h1>
          <p class="text-theme-secondary">
            Consulta los procedimientos disponibles y sus precios para orientar al paciente
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
      <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div class="md:col-span-2">
          <label class="block text-sm font-medium text-theme-primary mb-1">Buscar</label>
          <div class="relative">
            <input v-model="filters.search" type="text" placeholder="Buscar por nombre o código..." class="w-full px-3 py-2 pl-9 border border-theme rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-accent bg-theme-surface-elevated text-theme-primary">
            <svg class="w-4 h-4 absolute left-3 top-3 text-theme-secondary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
            </svg>
          </div>
        </div>
        <div>
          <label class="block text-sm font-medium text-theme-primary mb-1">Especialidad</label>
          <select v-model="filters.specialty" class="w-full px-3 py-2 border border-theme rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-accent bg-theme-surface-elevated text-theme-primary">
            <option value="">Todas las especialidades</option>
            <option v-for="spec in specialties" :key="spec.id" :value="spec.code">{{ spec.name }}</option>
          </select>
        </div>
      </div>
    </UiCard>

    <div v-if="loading" class="py-8 text-center">
      <LoadingSpinner />
    </div>

    <div v-else-if="!procedures.length" class="py-12 text-center text-theme-secondary">
      No se encontraron procedimientos con los filtros aplicados
    </div>

    <div v-else class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
      <UiCard v-for="proc in procedures" :key="proc.id" variant="elevated" class="hover-lift transition-shadow">
        <div class="flex items-start justify-between gap-2 mb-2">
          <span class="font-mono text-xs px-2 py-0.5 rounded bg-primary-50 text-primary-700">{{ proc.code }}</span>
          <span class="text-xs text-theme-secondary">{{ proc.specialty_name || 'General' }}</span>
        </div>
        <h3 class="font-semibold text-theme-primary mb-2">{{ proc.name }}</h3>
        <p v-if="proc.description" class="text-sm text-theme-secondary mb-3 line-clamp-2">
          {{ proc.description }}
        </p>
        <div class="flex items-center justify-between border-t border-theme pt-3 mt-3">
          <div>
            <div class="text-xs text-theme-secondary">Duración</div>
            <div class="text-sm font-medium text-theme-primary">{{ proc.default_duration_minutes }} min</div>
          </div>
          <div class="text-right">
            <div class="text-xs text-theme-secondary">Precio</div>
            <div class="text-lg font-bold text-accent">S/ {{ Number(proc.default_cost).toFixed(2) }}</div>
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
const { procedures, loading, pagination, getProcedures, currentPage, totalPages } = useProcedureCatalog()
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

watch(() => filters.specialty, () => {
  filters.page = 1
  load()
})

watch(() => filters.search, onSearchInput)

onMounted(async () => {
  await getSpecialties(true)
  await load()
})
</script>
