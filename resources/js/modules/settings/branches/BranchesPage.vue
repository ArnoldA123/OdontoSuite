<template>
  <AppLayout>
    <PageHeader title="Sucursales" subtitle="Gestiona las sedes de la clinica" class="mb-6">
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
        <UiButton @click="openCreate">
          <template #icon-left>
            <svg class="w-4 h-4" fill="none" stroke="currentColor"
viewBox="0 0 24 24">
              <path
                stroke-linecap="round"
                stroke-linejoin="round"
                stroke-width="2"
                d="M12 6v6m0 0v6m0-6h6m-6 0H6"
              />
            </svg>
          </template>
          Nueva Sucursal
        </UiButton>
      </template>
    </PageHeader>

    <!-- Contadores -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
      <UiCard variant="glass" class="hover-lift">
        <div class="text-center">
          <p class="text-sm font-medium text-theme-secondary">Total</p>
          <p class="text-3xl font-bold text-theme-primary">
            {{ pagination.total || branches.length }}
          </p>
        </div>
      </UiCard>
      <UiCard variant="glass" class="hover-lift">
        <div class="text-center">
          <p class="text-sm font-medium text-theme-secondary">Activas</p>
          <p class="text-3xl font-bold text-success-600">
            {{ activeBranches.length }}
          </p>
        </div>
      </UiCard>
      <UiCard variant="glass" class="hover-lift">
        <div class="text-center">
          <p class="text-sm font-medium text-theme-secondary">Inactivas</p>
          <p class="text-3xl font-bold text-theme-secondary">
            {{ branches.length - activeBranches.length }}
          </p>
        </div>
      </UiCard>
    </div>

    <!-- Filtros -->
    <UiCard variant="glass" class="mb-6">
      <div class="flex flex-col lg:flex-row gap-4">
        <div class="flex-1">
          <UiInput
            v-model="searchQuery"
            placeholder="Buscar por nombre, codigo o ciudad..."
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
            v-model="statusFilter"
            class="w-40 px-3 py-2 border border-theme rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-accent bg-theme-surface-elevated text-theme-primary"
            @change="onFilter"
          >
            <option value="">Todas</option>
            <option value="true">Activas</option>
            <option value="false">Inactivas</option>
          </select>
        </div>
      </div>
    </UiCard>

    <!-- Loading inicial -->
    <div v-if="loading && branches.length === 0" class="flex justify-center py-12">
      <LoadingSpinner size="lg" />
    </div>

    <!-- Empty state -->
    <UiCard v-else-if="!hasBranches" variant="glass" class="text-center py-12">
      <EmptyState
        :icon="BuildingOfficeIcon"
        title="No hay sucursales registradas"
        description="Crea la primera sede para empezar a gestionar multiples ubicaciones."
        action-text="Nueva Sucursal"
        @action="openCreate"
      />
    </UiCard>

    <!-- Tabla de sucursales -->
    <UiCard v-else variant="glass">
      <div class="overflow-x-auto">
        <table class="w-full">
          <thead>
            <tr class="border-b border-theme">
              <th
                class="text-left py-3 px-4 text-xs font-semibold text-theme-secondary uppercase tracking-wider"
              >
                Codigo
              </th>
              <th
                class="text-left py-3 px-4 text-xs font-semibold text-theme-secondary uppercase tracking-wider"
              >
                Nombre
              </th>
              <th
                class="text-left py-3 px-4 text-xs font-semibold text-theme-secondary uppercase tracking-wider"
              >
                Ciudad
              </th>
              <th
                class="text-left py-3 px-4 text-xs font-semibold text-theme-secondary uppercase tracking-wider"
              >
                Telefono
              </th>
              <th
                class="text-center py-3 px-4 text-xs font-semibold text-theme-secondary uppercase tracking-wider"
              >
                Estado
              </th>
              <th
                class="text-right py-3 px-4 text-xs font-semibold text-theme-secondary uppercase tracking-wider"
              >
                Acciones
              </th>
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="b in branches"
              :key="b.id"
              class="border-b border-theme/50 hover:bg-theme-surface/30 transition-colors"
            >
              <td class="py-3 px-4">
                <span class="font-mono text-sm text-theme-primary">{{ b.code }}</span>
              </td>
              <td class="py-3 px-4">
                <div class="text-sm font-medium text-theme-primary">
                  {{ b.name }}
                </div>
                <div v-if="b.address" class="text-xs text-theme-secondary mt-0.5">
                  {{ b.address }}
                </div>
              </td>
              <td class="py-3 px-4 text-sm text-theme-primary">
                {{ b.city }}
                <span v-if="b.state">, {{ b.state }}</span>
              </td>
              <td class="py-3 px-4 text-sm text-theme-primary">
                {{ b.phone || '-' }}
              </td>
              <td class="py-3 px-4 text-center">
                <UiBadge :variant="b.is_active ? 'success' : 'secondary'">
                  {{ b.is_active ? 'Activa' : 'Inactiva' }}
                </UiBadge>
              </td>
              <td class="py-3 px-4">
                <div class="flex justify-end gap-2">
                  <UiButton
                    variant="ghost"
                    size="sm"
                    class="text-accent hover:text-primary-800"
                    @click="openEdit(b)"
                  >
                    Editar
                  </UiButton>
                  <UiButton
                    v-if="b.is_active"
                    variant="ghost"
                    size="sm"
                    class="text-red-600 hover:text-red-900"
                    @click="confirmDeactivate(b)"
                  >
                    Desactivar
                  </UiButton>
                  <UiButton
                    v-else
                    variant="ghost"
                    size="sm"
                    class="text-success-600 hover:text-success-800"
                    @click="confirmActivate(b)"
                  >
                    Activar
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

    <!-- Modal de crear/editar -->
    <BranchFormModal v-if="showForm" :branch="editingBranch" @close="closeForm" @saved="onSaved" />

    <!-- Confirm deactivate -->
    <UiModal v-model="showDeactivateConfirm" title="Desactivar sucursal" size="sm">
      <p class="text-theme-primary">
        ¿Estas seguro de desactivar la sucursal
        <strong>{{ branchToToggle?.name }}</strong>
        ? No aparecera en los dropdowns de abrir caja ni en la seleccion de sede.
      </p>
      <template #footer>
        <div class="flex justify-end gap-3">
          <UiButton variant="secondary" @click="showDeactivateConfirm = false">Cancelar</UiButton>
          <UiButton variant="danger" :disabled="toggling" @click="doDeactivate">
            Desactivar
          </UiButton>
        </div>
      </template>
    </UiModal>

    <!-- Confirm activate -->
    <UiModal v-model="showActivateConfirm" title="Activar sucursal" size="sm">
      <p class="text-theme-primary">
        ¿Activar la sucursal
        <strong>{{ branchToToggle?.name }}</strong>
        ? Volvera a estar disponible para seleccion en abrir caja.
      </p>
      <template #footer>
        <div class="flex justify-end gap-3">
          <UiButton variant="secondary" @click="showActivateConfirm = false">Cancelar</UiButton>
          <UiButton :disabled="toggling" @click="doActivate">Activar</UiButton>
        </div>
      </template>
    </UiModal>
  </AppLayout>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import { useRouter } from 'vue-router'
import { useBranches } from '../../../composables/useBranches'
import { useToast } from '../../../composables/useToast'
import AppLayout from '../../../components/layout/AppLayout.vue'
import PageHeader from '../../../components/layout/PageHeader.vue'
import UiButton from '../../../components/ui/Button.vue'
import UiCard from '../../../components/ui/Card.vue'
import UiInput from '../../../components/ui/Input.vue'
import UiBadge from '../../../components/ui/Badge.vue'
import UiModal from '../../../components/ui/Modal.vue'
import EmptyState from '../../../components/ui/EmptyState.vue'
import LoadingSpinner from '../../../components/ui/LoadingSpinner.vue'
import Pagination from '../../../components/ui/Pagination.vue'
import BranchFormModal from './BranchFormModal.vue'
import { BuildingOfficeIcon } from '@heroicons/vue/24/outline'

const router = useRouter()
const toast = useToast()
const {
  branches,
  loading,
  error,
  pagination,
  hasBranches,
  totalPages,
  currentPage,
  activeBranches,
  getBranches,
  toggleActive,
  clearError
} = useBranches()

const searchQuery = ref('')
const statusFilter = ref('')
const showForm = ref(false)
const editingBranch = ref(null)
const showDeactivateConfirm = ref(false)
const showActivateConfirm = ref(false)
const branchToToggle = ref(null)
const toggling = ref(false)

let searchTimeout = null

const load = () =>
  getBranches({
    q: searchQuery.value || undefined,
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
  editingBranch.value = null
  showForm.value = true
}

const openEdit = branch => {
  editingBranch.value = { ...branch }
  showForm.value = true
}

const closeForm = () => {
  showForm.value = false
  editingBranch.value = null
}

const onSaved = branch => {
  closeForm()
  toast.success(
    editingBranch.value
      ? `Sucursal "${branch.name}" actualizada`
      : `Sucursal "${branch.name}" creada`
  )
  load()
}

const confirmDeactivate = branch => {
  branchToToggle.value = branch
  showDeactivateConfirm.value = true
}

const confirmActivate = branch => {
  branchToToggle.value = branch
  showActivateConfirm.value = true
}

const doDeactivate = async () => {
  if (!branchToToggle.value) return
  toggling.value = true
  try {
    await toggleActive(branchToToggle.value)
    toast.success(`Sucursal "${branchToToggle.value.name}" desactivada`)
    showDeactivateConfirm.value = false
    branchToToggle.value = null
  } catch (err) {
    toast.error('No se pudo desactivar la sucursal')
  } finally {
    toggling.value = false
  }
}

const doActivate = async () => {
  if (!branchToToggle.value) return
  toggling.value = true
  try {
    await toggleActive(branchToToggle.value)
    toast.success(`Sucursal "${branchToToggle.value.name}" activada`)
    showActivateConfirm.value = false
    branchToToggle.value = null
  } catch (err) {
    toast.error('No se pudo activar la sucursal')
  } finally {
    toggling.value = false
  }
}

const goBack = () => router.push('/dashboard')

watch(error, newError => {
  if (newError) {
    toast.error(newError)
    clearError()
  }
})

onMounted(() => {
  load()
})
</script>
