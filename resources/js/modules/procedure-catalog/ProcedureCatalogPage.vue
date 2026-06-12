<template>
  <AppLayout>
    <div class="mb-8 animate-fade-in">
      <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
          <h1 class="text-3xl font-bold text-theme-primary mb-2">
            Catálogo de Procedimientos
          </h1>
          <p class="text-theme-secondary">
            Gestiona los procedimientos clínicos disponibles en la clínica
          </p>
        </div>
        <div class="flex gap-3">
          <UiButton variant="secondary" class="flex items-center gap-2" @click="goBack">
            <svg
              class="w-4 h-4"
              fill="none"
              stroke="currentColor"
              viewBox="0 0 24 24"
            >
              <path
                stroke-linecap="round"
                stroke-linejoin="round"
                stroke-width="2"
                d="M10 19l-7-7m0 0l7-7m-7 7h18"
              />
            </svg>
            Volver
          </UiButton>
          <UiButton class="flex items-center gap-2" @click="openCreate">
            <svg
              class="w-4 h-4"
              fill="none"
              stroke="currentColor"
              viewBox="0 0 24 24"
            >
              <path
                stroke-linecap="round"
                stroke-linejoin="round"
                stroke-width="2"
                d="M12 6v6m0 0v6m0-6h6m-6 0H6"
              />
            </svg>
            Nuevo Procedimiento
          </UiButton>
          <UiButton variant="secondary" class="flex items-center gap-2" @click="showImportModal = true">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.9 5 5 0 019.9-1A5.5 5.5 0 0118 16H7z" />
            </svg>
            Importar CSV
          </UiButton>
        </div>
      </div>
    </div>

    <UiCard variant="glass" class="mb-6">
      <div class="flex flex-col lg:flex-row gap-4">
        <div class="flex-1">
          <UiInput
            v-model="searchQuery"
            placeholder="Buscar por nombre, código o descripción..."
            class="w-full"
            @input="onSearch"
          >
            <template #prefix>
              <svg
                class="w-5 h-5 text-theme-secondary"
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
        <div class="flex gap-3">
          <select
            v-model="specialtyFilter"
            class="w-48 px-3 py-2 border border-theme rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-accent bg-theme-surface-elevated text-theme-primary"
            @change="onFilter"
          >
            <option value="">Todas las especialidades</option>
            <option v-for="s in specialties" :key="s.id" :value="s.code">
              {{ s.name }}
            </option>
          </select>
          <select
            v-model="statusFilter"
            class="w-40 px-3 py-2 border border-theme rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-accent bg-theme-surface-elevated text-theme-primary"
            @change="onFilter"
          >
            <option value="">Todos los estados</option>
            <option value="true">Activos</option>
            <option value="false">Inactivos</option>
          </select>
        </div>
      </div>
    </UiCard>

    <UiCard variant="glass" class="overflow-hidden">
      <div v-if="loading" class="p-8 text-center">
        <LoadingSpinner />
        <p class="mt-2 text-theme-secondary">Cargando procedimientos...</p>
      </div>

      <EmptyState
        v-else-if="!hasProcedures"
        title="No hay procedimientos"
        description="Crea el primer procedimiento para empezar a usar el catálogo clínico."
      >
        <template #action>
          <UiButton @click="openCreate">Crear procedimiento</UiButton>
        </template>
      </EmptyState>

      <div v-else class="overflow-x-auto">
        <table class="min-w-full divide-y divide-theme">
          <thead class="bg-theme-surface">
            <tr>
              <th
                class="px-6 py-3 text-left text-xs font-medium text-theme-secondary uppercase tracking-wider"
              >
                Código
              </th>
              <th
                class="px-6 py-3 text-left text-xs font-medium text-theme-secondary uppercase tracking-wider"
              >
                Procedimiento
              </th>
              <th
                class="px-6 py-3 text-left text-xs font-medium text-theme-secondary uppercase tracking-wider"
              >
                Especialidad
              </th>
              <th
                class="px-6 py-3 text-left text-xs font-medium text-theme-secondary uppercase tracking-wider"
              >
                Duración
              </th>
              <th
                class="px-6 py-3 text-left text-xs font-medium text-theme-secondary uppercase tracking-wider"
              >
                Costo
              </th>
              <th
                class="px-6 py-3 text-left text-xs font-medium text-theme-secondary uppercase tracking-wider"
              >
                Estado
              </th>
              <th
                class="px-6 py-3 text-right text-xs font-medium text-theme-secondary uppercase tracking-wider"
              >
                Acciones
              </th>
            </tr>
          </thead>
          <tbody class="bg-theme-surface-elevated divide-y divide-theme">
            <tr v-for="p in procedures" :key="p.id" class="hover:bg-theme-surface">
              <td class="px-6 py-4 whitespace-nowrap">
                <span class="font-mono text-xs text-theme-secondary">{{ p.code }}</span>
              </td>
              <td class="px-6 py-4">
                <div class="text-sm font-medium text-theme-primary">
                  {{ p.name }}
                </div>
                <div class="text-sm text-theme-secondary line-clamp-1">
                  {{ p.description || 'Sin descripción' }}
                </div>
              </td>
              <td class="px-6 py-4 whitespace-nowrap">
                <span
                  v-if="p.specialty_name"
                  class="px-2 py-1 text-xs rounded-full bg-primary-50 text-primary-700"
                >
                  {{ p.specialty_name }}
                </span>
                <span v-else class="text-sm text-theme-secondary">—</span>
              </td>
              <td class="px-6 py-4 whitespace-nowrap text-sm text-theme-primary">
                {{ p.default_duration_minutes }} min
              </td>
              <td class="px-6 py-4 whitespace-nowrap text-sm text-theme-primary">
                S/ {{ Number(p.default_cost).toFixed(2) }}
              </td>
              <td class="px-6 py-4 whitespace-nowrap">
                <UiBadge :variant="p.is_active ? 'success' : 'error'">
                  {{ p.is_active ? 'Activo' : 'Inactivo' }}
                </UiBadge>
              </td>
              <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                <div class="flex justify-end gap-2">
                  <UiButton
                    variant="ghost"
                    size="sm"
                    class="text-accent hover:text-accent-hover"
                    @click="goDetail(p.id)"
                  >
                    Ver
                  </UiButton>
                  <UiButton
                    variant="ghost"
                    size="sm"
                    class="text-accent hover:text-primary-800"
                    @click="openEdit(p)"
                  >
                    Editar
                  </UiButton>
                  <UiButton
                    v-if="p.is_active"
                    variant="ghost"
                    size="sm"
                    class="text-red-600 hover:text-red-900"
                    @click="confirmDeactivate(p)"
                  >
                    Desactivar
                  </UiButton>
                </div>
              </td>
            </tr>
          </tbody>
        </table>

        <div v-if="totalPages > 1" class="p-4 border-t border-theme">
          <Pagination
            :current-page="currentPage"
            :total-pages="totalPages"
            :total="pagination.total"
            @page-change="onPageChange"
          />
        </div>
      </div>
    </UiCard>

    <ProcedureCatalogFormModal
      v-if="showForm"
      :procedure="editingProcedure"
      :specialties="specialties"
      @close="closeForm"
      @saved="onSaved"
    />

    <ImportCsvModal
      v-if="showImportModal"
      :open="showImportModal"
      @close="showImportModal = false"
      @imported="onCsvImported"
    />

    <UiModal v-model="showDeactivateConfirm" title="Desactivar procedimiento" size="sm">
      <p class="text-theme-primary">
        ¿Estás seguro de desactivar el procedimiento
        <strong>{{ procedureToDeactivate?.name }}</strong>
        ? Los procedimientos desactivados no aparecen en la selección al agendar citas.
      </p>
      <template #footer>
        <div class="flex justify-end gap-3">
          <UiButton variant="secondary" @click="showDeactivateConfirm = false">Cancelar</UiButton>
          <UiButton variant="danger" :disabled="loading" @click="doDeactivate">Desactivar</UiButton>
        </div>
      </template>
    </UiModal>
  </AppLayout>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import { useRouter } from 'vue-router'
import { useProcedureCatalog } from '../../composables/useProcedureCatalog'
import { useSpecialties } from '../../composables/useSpecialties'
import { useToast } from '../../composables/useToast'
import AppLayout from '../../components/layout/AppLayout.vue'
import UiButton from '../../components/ui/Button.vue'
import UiCard from '../../components/ui/Card.vue'
import UiInput from '../../components/ui/Input.vue'
import UiBadge from '../../components/ui/Badge.vue'
import UiModal from '../../components/ui/Modal.vue'
import EmptyState from '../../components/ui/EmptyState.vue'
import LoadingSpinner from '../../components/ui/LoadingSpinner.vue'
import Pagination from '../../components/ui/Pagination.vue'
import ProcedureCatalogFormModal from './ProcedureCatalogFormModal.vue'
import ImportCsvModal from '../../components/procedures/ImportCsvModal.vue'

const router = useRouter()
const toast = useToast()
const {
  procedures,
  loading,
  error,
  pagination,
  hasProcedures,
  totalPages,
  currentPage,
  getProcedures,
  deactivateProcedure,
  clearError
} = useProcedureCatalog()
const { specialties, getSpecialties } = useSpecialties()

const searchQuery = ref('')
const specialtyFilter = ref('')
const statusFilter = ref('')
const showForm = ref(false)
const editingProcedure = ref(null)
const showDeactivateConfirm = ref(false)
const procedureToDeactivate = ref(null)
const showImportModal = ref(false)

let searchTimeout = null

const load = () =>
  getProcedures({
    q: searchQuery.value || undefined,
    specialty: specialtyFilter.value || undefined,
    is_active: statusFilter.value === '' ? undefined : statusFilter.value,
    page: currentPage.value
  })

const onSearch = () => {
  clearTimeout(searchTimeout)
  searchTimeout = setTimeout(() => {
    pagination.value.current_page = 1
    load()
  }, 350)
}

const onFilter = () => {
  pagination.value.current_page = 1
  load()
}

const onPageChange = page => {
  pagination.value.current_page = page
  load()
}

const openCreate = () => {
  editingProcedure.value = null
  showForm.value = true
}

const openEdit = procedure => {
  editingProcedure.value = { ...procedure }
  showForm.value = true
}

const closeForm = () => {
  showForm.value = false
  editingProcedure.value = null
}

const onSaved = procedure => {
  closeForm()
  toast.success(
    editingProcedure.value
      ? `Procedimiento "${procedure.name}" actualizado`
      : `Procedimiento "${procedure.name}" creado`
  )
  load()
}

const onCsvImported = result => {
  toast.success(
    `Importación CSV: ${result.inserted} insertados, ${result.updated} actualizados, ${result.errors} errores.`
  )
  showImportModal.value = false
  load()
}

const confirmDeactivate = procedure => {
  procedureToDeactivate.value = procedure
  showDeactivateConfirm.value = true
}

const doDeactivate = async () => {
  if (!procedureToDeactivate.value) return
  try {
    await deactivateProcedure(procedureToDeactivate.value.id)
    toast.success(`Procedimiento "${procedureToDeactivate.value.name}" desactivado`)
    showDeactivateConfirm.value = false
    procedureToDeactivate.value = null
  } catch (err) {
    toast.error('No se pudo desactivar el procedimiento')
  }
}

const goDetail = id => router.push(`/procedure-catalog/${id}`)
const goBack = () => router.push('/dashboard')

watch(error, newError => {
  if (newError) {
    toast.error(newError)
    clearError()
  }
})

onMounted(async () => {
  await Promise.all([load(), getSpecialties(true)])
})
</script>
