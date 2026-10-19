<template>
  <AppLayout>
    <header
      data-branches-hero
      class="mb-6 p-6 md:p-8"
      style="
        background: var(--color-canvas);
        border: 1px solid var(--color-hairline);
        border-radius: var(--radius-panel);
        box-shadow: var(--elevation-2);
      "
    >
      <div class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
        <div class="min-w-0">
          <p
            class="text-xs font-medium uppercase tracking-wide text-theme-secondary"
            data-hero-eyebrow
          >
            Configuración
          </p>
          <h1 class="mt-2 text-4xl font-semibold text-label" data-hero-title>Sucursales</h1>
          <p class="mt-2 text-sm text-theme-secondary" data-hero-caption>
            Sedes de la clínica: identificación, ubicación y estado operativo
          </p>
        </div>
        <div class="flex flex-shrink-0 items-center gap-3">
          <UiButton variant="secondary" @click="goBack">
            <template #icon-left>
              <ArrowLeftIcon class="w-4 h-4" aria-hidden="true" />
            </template>
            Volver
          </UiButton>
          <UiButton data-hero-action @click="openCreate">
            <template #icon-left>
              <PlusIcon class="w-4 h-4" aria-hidden="true" />
            </template>
            Nueva sucursal
          </UiButton>
        </div>
      </div>
    </header>

    <section
      aria-label="Resumen de sucursales"
      class="mb-6 grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-4"
    >
      <UiCard
        variant="glass"
        padding="sm"
        data-kpi-card="total"
        :style="{ boxShadow: 'var(--elevation-2)', borderColor: 'var(--color-hairline)' }"
      >
        <p class="text-xs font-medium uppercase text-theme-secondary">Sucursales</p>
        <p
          class="mt-2 text-2xl font-bold text-label tabular-nums leading-none"
          data-kpi-value="total"
        >
          {{ kpiTotalDisplay }}
        </p>
        <p class="mt-1 text-xs text-theme-secondary">En el directorio</p>
      </UiCard>

      <UiCard
        variant="glass"
        padding="sm"
        data-kpi-card="active"
        :style="{ boxShadow: 'var(--elevation-2)', borderColor: 'var(--color-hairline)' }"
      >
        <p class="text-xs font-medium uppercase text-theme-secondary">Activas</p>
        <p
          class="mt-2 text-2xl font-bold text-label tabular-nums leading-none"
          data-kpi-value="active"
        >
          {{ kpiActiveDisplay }}
        </p>
        <p class="mt-1 text-xs text-theme-secondary">Disponibles para operar</p>
      </UiCard>

      <UiCard
        variant="glass"
        padding="sm"
        data-kpi-card="inactive"
        :style="{ boxShadow: 'var(--elevation-2)', borderColor: 'var(--color-hairline)' }"
      >
        <p class="text-xs font-medium uppercase text-theme-secondary">Inactivas</p>
        <p
          class="mt-2 text-2xl font-bold text-label tabular-nums leading-none"
          data-kpi-value="inactive"
        >
          {{ kpiInactiveDisplay }}
        </p>
        <p class="mt-1 text-xs text-theme-secondary">Fuera de los selectores</p>
      </UiCard>

      <UiCard
        variant="glass"
        padding="sm"
        data-kpi-card="cities"
        :style="{ boxShadow: 'var(--elevation-2)', borderColor: 'var(--color-hairline)' }"
      >
        <p class="text-xs font-medium uppercase text-theme-secondary">Ciudades</p>
        <p
          class="mt-2 text-2xl font-bold text-label tabular-nums leading-none"
          data-kpi-value="cities"
        >
          {{ kpiCitiesDisplay }}
        </p>
        <p class="mt-1 text-xs text-theme-secondary">Cobertura geográfica</p>
      </UiCard>
    </section>

    <UiCard variant="glass" class="mb-6">
      <div class="flex flex-col gap-4 lg:flex-row lg:items-center">
        <div class="w-full flex-1">
          <UiInput
            v-model="searchQuery"
            data-branch-search
            placeholder="Buscar por código, nombre, ciudad o contacto..."
            class="w-full"
          >
            <template #prefix>
              <MagnifyingGlassIcon class="w-5 h-5 text-theme-secondary" aria-hidden="true" />
            </template>
          </UiInput>
        </div>
        <div class="flex items-center gap-3">
          <UiSelect
            v-model="statusFilter"
            :options="statusFilterOptions"
            class="w-48"
            data-status-filter="status"
          />
          <p class="whitespace-nowrap text-xs text-theme-secondary" data-filter-count>
            {{ filterCountText }}
          </p>
        </div>
      </div>
    </UiCard>

    <section aria-label="Sucursales" class="space-y-4">
      <div class="flex items-baseline gap-3">
        <h2 class="text-2xl font-semibold text-label">Directorio de sucursales</h2>
        <p class="text-sm text-theme-secondary">Busca, filtra y gestiona cada sede</p>
      </div>

      <div>
        <div v-if="loading && branches.length === 0" data-branches-loading>
          <UiSkeleton variant="table" animation="wave" aria-label="Cargando sucursales..." />
        </div>

        <div
          v-else-if="loadError"
          data-branches-error
          class="flex items-center justify-between flex-wrap gap-4 rounded-[var(--radius-panel)] p-5 bg-systemRed-50"
          style="border: 1px solid var(--color-hairline)"
        >
          <div class="flex items-center gap-3 min-w-0">
            <ExclamationTriangleIcon
              class="flex-shrink-0 w-6 h-6 text-systemRed-600"
              aria-hidden="true"
            />
            <div class="min-w-0">
              <p class="text-sm font-medium text-theme-primary">No pudimos cargar las sucursales</p>
              <p class="text-sm text-theme-secondary">Revisa tu conexión y vuelve a intentarlo.</p>
            </div>
          </div>
          <UiButton variant="primary" size="sm" data-retry-branches @click="loadBranches">
            Reintentar
          </UiButton>
        </div>

        <div v-else-if="filteredBranches.length === 0" data-branches-empty class="py-4">
          <UiEmptyState
            :title="emptyStateTitle"
            :description="emptyStateDescription"
            :icon="BuildingOffice2Icon"
          >
            <template #action>
              <UiButton v-if="hasActiveFilters" variant="secondary" @click="clearFilters">
                Limpiar filtros
              </UiButton>
              <UiButton v-else @click="openCreate">Nueva sucursal</UiButton>
            </template>
          </UiEmptyState>
        </div>

        <UiDataTable
          v-else
          :data="filteredBranches"
          :columns="columns"
          :searchable="false"
          :show-header="false"
          :pagination="false"
          data-branches-table
        >
          <template #cell-code="{ row }">
            <span class="font-mono text-xs text-theme-secondary">{{ row.code }}</span>
          </template>
          <template #cell-name="{ row }">
            <div class="text-sm font-medium text-theme-primary">{{ row.name }}</div>
            <div v-if="row.address" class="mt-0.5 text-xs text-theme-secondary">
              {{ row.address }}
            </div>
          </template>
          <template #cell-city="{ row }">
            <span class="text-sm text-theme-primary">{{ formatBranchLocation(row) }}</span>
          </template>
          <template #cell-contact="{ row }">
            <div class="text-sm text-theme-primary">{{ row.phone || 'Sin teléfono' }}</div>
            <div class="text-xs text-theme-secondary">{{ row.email || 'Sin email' }}</div>
          </template>
          <template #cell-status="{ row }">
            <UiStatusBadge
              data-branch-status
              :variant="getBranchStatusVariant(row.is_active)"
              :label="getBranchStatusLabel(row.is_active)"
              size="sm"
            />
          </template>
          <template #actions="{ row }">
            <UiButton
              variant="ghost"
              size="sm"
              class="text-systemBlue-600 hover:text-systemBlue-700"
              data-row-action="detail"
              aria-label="Ver detalle de la sucursal"
              @click="viewDetail(row)"
            >
              <template #icon-left>
                <EyeIcon class="w-4 h-4" aria-hidden="true" />
              </template>
              Ver
            </UiButton>
            <UiButton
              variant="ghost"
              size="sm"
              class="text-systemBlue-600 hover:text-systemBlue-700"
              data-row-action="edit"
              title="Editar sucursal"
              aria-label="Editar sucursal"
              @click="openEdit(row)"
            >
              <PencilSquareIcon class="w-4 h-4" aria-hidden="true" />
            </UiButton>
            <UiButton
              variant="ghost"
              size="sm"
              :class="
                row.is_active
                  ? 'text-systemRed-600 hover:text-systemRed-700'
                  : 'text-systemGreen-600 hover:text-systemGreen-700'
              "
              data-row-action="toggle"
              :title="row.is_active ? 'Desactivar sucursal' : 'Activar sucursal'"
              :aria-label="row.is_active ? 'Desactivar sucursal' : 'Activar sucursal'"
              @click="toggleBranch(row)"
            >
              <PowerIcon class="w-4 h-4" aria-hidden="true" />
            </UiButton>
          </template>
        </UiDataTable>
      </div>
    </section>

    <BranchFormModal v-if="showForm" :branch="editingBranch" @close="closeForm" @saved="onSaved" />
  </AppLayout>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import {
  ArrowLeftIcon,
  BuildingOffice2Icon,
  ExclamationTriangleIcon,
  EyeIcon,
  MagnifyingGlassIcon,
  PencilSquareIcon,
  PlusIcon,
  PowerIcon
} from '@heroicons/vue/24/outline'
import { useBranches } from '../../../composables/useBranches'
import { useConfirm } from '../../../composables/useConfirm'
import { useToast } from '../../../composables/useToast'
import {
  formatBranchLocation,
  getBranchStatusLabel,
  getBranchStatusVariant
} from './branchPresentation'
import AppLayout from '../../../components/layout/AppLayout.vue'
import UiButton from '../../../components/ui/Button.vue'
import UiCard from '../../../components/ui/Card.vue'
import UiDataTable from '../../../components/ui/DataTable.vue'
import UiEmptyState from '../../../components/ui/EmptyState.vue'
import UiInput from '../../../components/ui/Input.vue'
import UiSelect from '../../../components/ui/Select.vue'
import UiSkeleton from '../../../components/ui/Skeleton.vue'
import UiStatusBadge from '../../../components/ui/StatusBadge.vue'
import BranchFormModal from './BranchFormModal.vue'

const route = useRoute()
const router = useRouter()
const toast = useToast()
const { confirm } = useConfirm()
const { branches, loading, getBranches, toggleActive } = useBranches()

const searchQuery = ref('')
const statusFilter = ref('')
const loadError = ref(false)
const showForm = ref(false)
const editingBranch = ref(null)

const columns = [
  { key: 'code', label: 'Código' },
  { key: 'name', label: 'Sucursal' },
  { key: 'city', label: 'Ciudad' },
  { key: 'contact', label: 'Contacto' },
  { key: 'status', label: 'Estado' }
]

const statusFilterOptions = [
  { value: '', label: 'Todas' },
  { value: 'active', label: 'Activas' },
  { value: 'inactive', label: 'Inactivas' }
]

const hasActiveFilters = computed(
  () => searchQuery.value.trim() !== '' || statusFilter.value !== ''
)

const filteredBranches = computed(() => {
  const query = searchQuery.value.trim().toLowerCase()
  return branches.value.filter(branch => {
    if (statusFilter.value === 'active' && !branch.is_active) return false
    if (statusFilter.value === 'inactive' && branch.is_active) return false
    if (query) {
      const haystack = [
        branch.code,
        branch.name,
        branch.city,
        branch.state,
        branch.phone,
        branch.email
      ]
        .filter(Boolean)
        .join(' ')
        .toLowerCase()
      if (!haystack.includes(query)) return false
    }
    return true
  })
})

const filterCountText = computed(
  () => `Mostrando ${filteredBranches.value.length} de ${branches.value.length}`
)

const kpiTotalDisplay = computed(() => (loadError.value ? 'N/D' : String(branches.value.length)))

const kpiActiveDisplay = computed(() =>
  loadError.value ? 'N/D' : String(branches.value.filter(branch => branch.is_active).length)
)

const kpiInactiveDisplay = computed(() =>
  loadError.value ? 'N/D' : String(branches.value.filter(branch => !branch.is_active).length)
)

const kpiCitiesDisplay = computed(() => {
  if (loadError.value) return 'N/D'
  const cities = new Set(
    branches.value.map(branch => branch.city?.trim().toLowerCase()).filter(city => !!city)
  )
  return String(cities.size)
})

const emptyStateTitle = computed(() => {
  if (hasActiveFilters.value) return 'No se encontraron sucursales'
  return 'Aún no hay sucursales registradas'
})

const emptyStateDescription = computed(() => {
  const query = searchQuery.value.trim()
  if (query) {
    return `Ninguna sucursal coincide con ${query}. Prueba con otro término de búsqueda.`
  }
  if (hasActiveFilters.value) return 'Prueba con otro estado o quita los filtros aplicados.'
  return 'Crea la primera sede para empezar a gestionar múltiples ubicaciones.'
})

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

const openEditFromQuery = () => {
  const editId = route.query.edit
  if (!editId) return
  const match = branches.value.find(branch => String(branch.id) === String(editId))
  if (match) openEdit(match)
  router.replace({ query: { ...route.query, edit: undefined } })
}

const loadBranches = async () => {
  loadError.value = false
  try {
    await getBranches()
    openEditFromQuery()
  } catch {
    loadError.value = true
  }
}

const clearFilters = () => {
  searchQuery.value = ''
  statusFilter.value = ''
}

const viewDetail = branch => {
  router.push(`/settings/branches/${branch.id}`)
}

const toggleBranch = async branch => {
  const isActive = !!branch.is_active
  const ok = await confirm({
    title: isActive ? 'Desactivar sucursal' : 'Activar sucursal',
    message: isActive
      ? `¿Desactivar la sucursal ${branch.name}? Dejará de aparecer en los selectores de sede y de apertura de caja.`
      : `¿Activar la sucursal ${branch.name}? Volverá a estar disponible en los selectores de sede.`,
    confirmText: isActive ? 'Desactivar' : 'Activar',
    variant: isActive ? 'danger' : 'default'
  })
  if (!ok) return
  try {
    await toggleActive(branch)
    toast.success(
      isActive ? `Sucursal "${branch.name}" desactivada` : `Sucursal "${branch.name}" activada`
    )
  } catch {
    toast.error('No se pudo actualizar la sucursal')
  }
}

const onSaved = saved => {
  const wasEditing = !!editingBranch.value
  closeForm()
  toast.success(
    wasEditing ? `Sucursal "${saved.name}" actualizada` : `Sucursal "${saved.name}" creada`
  )
  loadBranches()
}

const goBack = () => {
  router.push('/dashboard')
}

onMounted(() => {
  loadBranches()
})
</script>
