<template>
  <CatalogPageShell
    kicker="Catálogo"
    title="Especialidades"
    subtitle="Taxonomía de procedimientos y profesionales"
    list-label="Especialidades"
    kpi-label="Resumen de especialidades"
    loading-label="Cargando especialidades..."
    content-class="bg-canvas"
    :state="listState"
  >
    <template #actions>
      <UiButton
        variant="secondary"
        data-refresh-specialties
        :disabled="loading"
        @click="loadSpecialties"
      >
        <template #icon-left>
          <ArrowPathIcon class="h-4 w-4" aria-hidden="true" />
        </template>
        Actualizar
      </UiButton>
      <UiButton v-if="can.manageSpecialties?.value" data-new-specialty @click="openCreate">
        <template #icon-left>
          <PlusIcon class="h-4 w-4" aria-hidden="true" />
        </template>
        Nueva especialidad
      </UiButton>
    </template>

    <template #kpis>
      <UiCard
        variant="glass"
        padding="sm"
        data-kpi-card="total"
        :style="{ boxShadow: 'var(--elevation-2)', borderColor: 'var(--color-hairline)' }"
      >
        <p class="text-xs font-medium uppercase text-theme-secondary">Total</p>
        <p
          class="mt-2 text-2xl font-bold text-label tabular-nums leading-none"
          data-kpi-value="total"
        >
          {{ kpiValue('total') }}
        </p>
        <p class="mt-1 text-xs text-theme-secondary">Especialidades registradas</p>
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
          {{ kpiValue('active') }}
        </p>
        <p class="mt-1 text-xs text-theme-secondary">Disponibles para asignar</p>
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
          {{ kpiValue('inactive') }}
        </p>
        <p class="mt-1 text-xs text-theme-secondary">Fuera de circulación</p>
      </UiCard>

      <UiCard
        variant="glass"
        padding="sm"
        data-kpi-card="procedures"
        :style="{ boxShadow: 'var(--elevation-2)', borderColor: 'var(--color-hairline)' }"
      >
        <p class="text-xs font-medium uppercase text-theme-secondary">Procedimientos</p>
        <p
          class="mt-2 text-2xl font-bold text-label tabular-nums leading-none"
          data-kpi-value="procedures"
        >
          {{ kpiValue('procedures') }}
        </p>
        <p class="mt-1 text-xs text-theme-secondary">Vinculados en el catálogo</p>
      </UiCard>
    </template>

    <template #filters>
      <UiFilterBar :columns="4" data-specialties-filters>
        <div class="sm:col-span-2">
          <UiInput
            v-model="searchQuery"
            data-specialties-search
            placeholder="Buscar por nombre, código o descripción..."
            class="w-full"
            @input="onSearchInput"
          >
            <template #prefix>
              <MagnifyingGlassIcon class="h-5 w-5 text-theme-secondary" aria-hidden="true" />
            </template>
          </UiInput>
        </div>
        <div class="flex items-end">
          <div class="w-full">
            <label
              for="specialties-filter-status"
              class="mb-1 block text-xs font-medium text-theme-secondary"
            >
              Estado
            </label>
            <UiSelect
              id="specialties-filter-status"
              v-model="statusFilter"
              :options="STATUS_FILTER_OPTIONS"
              placeholder="Todos los estados"
              class="w-full"
              data-status-filter
              @change="loadSpecialties"
            />
          </div>
        </div>
        <div v-if="hasActiveFilters" class="flex items-end">
          <UiButton variant="secondary" class="w-full" data-filters-clear @click="clearFilters">
            Limpiar filtros
          </UiButton>
        </div>
      </UiFilterBar>
    </template>

    <template #heading>
      <div class="flex flex-col gap-1 sm:flex-row sm:items-baseline sm:gap-3">
        <h2 class="text-2xl font-semibold text-label">Todas las especialidades</h2>
        <p class="text-sm text-theme-secondary">Código, uso y estado de cada especialidad</p>
      </div>
    </template>

    <template #loading>
      <div data-specialties-loading>
        <UiSkeleton variant="table" animation="wave" aria-label="Cargando especialidades..." />
      </div>
    </template>

    <template #error>
      <div
        data-specialties-error
        role="alert"
        class="flex flex-wrap items-center justify-between gap-4 rounded-[var(--radius-panel)] border border-hairline bg-systemRed-50 p-5"
      >
        <div class="flex min-w-0 items-center gap-3">
          <ExclamationTriangleIcon
            class="h-6 w-6 flex-shrink-0 text-systemRed-600"
            aria-hidden="true"
          />
          <div class="min-w-0">
            <p class="text-sm font-medium text-theme-primary">
              No pudimos cargar las especialidades
            </p>
            <p class="text-sm text-theme-secondary">Revisa tu conexión y vuelve a intentarlo.</p>
          </div>
        </div>
        <UiButton variant="primary" size="sm" data-specialties-retry @click="loadSpecialties">
          Reintentar
        </UiButton>
      </div>
    </template>

    <template #empty>
      <div data-specialties-empty>
        <UiEmptyState
          :title="emptyStateTitle"
          :description="emptyStateDescription"
          :icon="AcademicCapIcon"
        >
          <template #action>
            <UiButton v-if="hasActiveFilters" variant="secondary" @click="clearFilters">
              Limpiar filtros
            </UiButton>
            <UiButton v-else-if="can.manageSpecialties?.value" @click="openCreate">
              Nueva especialidad
            </UiButton>
          </template>
        </UiEmptyState>
      </div>
    </template>

    <template #table>
      <UiDataTable
        :data="specialties"
        :columns="columns"
        :searchable="false"
        :show-header="false"
        :pagination="false"
        data-specialties-table
      >
        <template #cell-name="{ row }">
          <div class="min-w-0">
            <p class="text-sm font-medium text-theme-primary" data-row-specialty-name>
              {{ row.name }}
            </p>
            <p class="font-mono text-xs text-theme-secondary" data-row-specialty-code>
              {{ row.code }}
            </p>
          </div>
        </template>

        <template #cell-procedures="{ row }">
          <div class="min-w-0" data-row-procedures>
            <p class="text-sm text-theme-primary tabular-nums">
              {{ countProcedures(row) }}
            </p>
            <p v-if="countProcedures(row) === 0" class="text-xs text-theme-secondary">
              Sin procedimientos
            </p>
          </div>
        </template>

        <template #cell-professionals="{ row }">
          <div class="min-w-0" data-row-professionals>
            <p class="text-sm text-theme-primary tabular-nums">
              {{ countProfessionals(row) }}
            </p>
            <p v-if="countProfessionals(row) === 0" class="text-xs text-theme-secondary">
              Sin profesionales
            </p>
          </div>
        </template>

        <template #cell-status="{ row }">
          <UiStatusBadge
            :variant="getStatusVariant(row.is_active)"
            :label="getStatusLabel(row.is_active)"
            size="sm"
          />
        </template>

        <template #actions="{ row }">
          <UiButton
            variant="ghost"
            size="sm"
            :class="ROW_ACTION_PALETTE.view.classes"
            data-row-action="view"
            @click="openDetail(row)"
          >
            Ver ficha
          </UiButton>
          <UiButton
            v-if="can.manageSpecialties?.value"
            variant="ghost"
            size="sm"
            :class="ROW_ACTION_PALETTE.edit.classes"
            data-row-action="edit"
            @click="openEdit(row)"
          >
            Editar
          </UiButton>
          <UiButton
            v-if="can.manageSpecialties?.value"
            variant="ghost"
            size="sm"
            :class="
              row.is_active
                ? ROW_ACTION_PALETTE.archive.classes
                : ROW_ACTION_PALETTE.reactivate.classes
            "
            data-row-action="toggle"
            @click="toggleSpecialty(row)"
          >
            {{ row.is_active ? 'Desactivar' : 'Activar' }}
          </UiButton>
        </template>
      </UiDataTable>
    </template>
  </CatalogPageShell>

  <UiModal v-model="showDetail" title="Ficha de la especialidad" size="md" data-specialty-detail>
    <div v-if="detailSpecialty" class="space-y-4">
      <div>
        <p class="text-xs font-medium uppercase tracking-wide text-theme-secondary">Especialidad</p>
        <p class="mt-1 text-lg font-semibold text-label" data-specialty-detail-name>
          {{ detailSpecialty.name }}
        </p>
        <p class="font-mono text-xs text-theme-secondary" data-specialty-detail-code>
          {{ detailSpecialty.code }}
        </p>
      </div>
      <p v-if="detailSpecialty.description" class="text-sm text-theme-secondary">
        {{ detailSpecialty.description }}
      </p>
      <dl class="grid grid-cols-1 gap-3 sm:grid-cols-3">
        <div>
          <dt class="text-xs font-medium uppercase text-theme-secondary">Procedimientos</dt>
          <dd class="mt-1 text-lg font-semibold text-label tabular-nums">
            {{ countProcedures(detailSpecialty) }}
          </dd>
        </div>
        <div>
          <dt class="text-xs font-medium uppercase text-theme-secondary">Profesionales</dt>
          <dd class="mt-1 text-lg font-semibold text-label tabular-nums">
            {{ countProfessionals(detailSpecialty) }}
          </dd>
        </div>
        <div>
          <dt class="text-xs font-medium uppercase text-theme-secondary">Estado</dt>
          <dd class="mt-1">
            <UiStatusBadge
              :variant="getStatusVariant(detailSpecialty.is_active)"
              :label="getStatusLabel(detailSpecialty.is_active)"
              size="sm"
            />
          </dd>
        </div>
      </dl>
    </div>
    <template #footer>
      <UiButton variant="secondary" data-specialty-detail-close @click="showDetail = false">
        Cerrar
      </UiButton>
    </template>
  </UiModal>

  <SpecialtyFormModal
    v-if="showForm"
    :specialty="editingSpecialty"
    @close="closeForm"
    @saved="onSaved"
  />
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import {
  AcademicCapIcon,
  ArrowPathIcon,
  ExclamationTriangleIcon,
  MagnifyingGlassIcon,
  PlusIcon
} from '@heroicons/vue/24/outline'
import { useApi } from '../../composables/useApi'
import { useConfirm } from '../../composables/useConfirm'
import { useErrorHandler } from '../../composables/useErrorHandler'
import { usePermissions } from '../../composables/usePermissions'
import { useToast } from '../../composables/useToast'
import { ROW_ACTION_PALETTE } from '../../design-system/rowActionPalette'
import CatalogPageShell from '../../components/catalogs/CatalogPageShell.vue'
import UiButton from '../../components/ui/Button.vue'
import UiCard from '../../components/ui/Card.vue'
import UiDataTable from '../../components/ui/DataTable.vue'
import UiEmptyState from '../../components/ui/EmptyState.vue'
import UiFilterBar from '../../components/ui/FilterBar.vue'
import UiInput from '../../components/ui/Input.vue'
import UiModal from '../../components/ui/Modal.vue'
import UiSelect from '../../components/ui/Select.vue'
import UiSkeleton from '../../components/ui/Skeleton.vue'
import UiStatusBadge from '../../components/ui/StatusBadge.vue'
import SpecialtyFormModal from './SpecialtyFormModal.vue'
import {
  STATUS_FILTER_OPTIONS,
  countProcedures,
  countProfessionals,
  getStatusLabel,
  getStatusVariant
} from './specialtyMeta'

const { get, put } = useApi()
const { confirm } = useConfirm()
const toast = useToast()
const { handleError } = useErrorHandler()
// #439: writing the catalog needs the permission the API already demands; a
// read-only session keeps the list, the filters and the KPIs.
const { can } = usePermissions()

const loading = ref(true)
const loadError = ref(false)
const specialties = ref([])
const kpis = ref(null)
const searchQuery = ref('')
const statusFilter = ref('')
const showForm = ref(false)
const editingSpecialty = ref(null)
const showDetail = ref(false)
const detailSpecialty = ref(null)

let searchTimer = null

const columns = [
  { key: 'name', label: 'Especialidad' },
  { key: 'procedures', label: 'Procedimientos' },
  { key: 'professionals', label: 'Profesionales' },
  { key: 'status', label: 'Estado' }
]

const hasActiveFilters = computed(
  () => searchQuery.value.trim() !== '' || statusFilter.value !== ''
)

const listState = computed(() => {
  if (loading.value) return 'loading'
  if (loadError.value) return 'error'
  if (specialties.value.length === 0) return 'empty'

  return 'ready'
})

const kpisUnavailable = computed(() => loading.value || loadError.value || kpis.value === null)

const kpiValue = key => {
  if (kpisUnavailable.value) return 'N/D'

  const value = Number(kpis.value?.[key] ?? 0)

  return Number.isFinite(value) ? String(value) : 'N/D'
}

const emptyStateTitle = computed(() =>
  hasActiveFilters.value ? 'No se encontraron especialidades' : 'Aún no hay especialidades'
)

const emptyStateDescription = computed(() =>
  hasActiveFilters.value
    ? 'Prueba con otros términos de búsqueda o quita los filtros aplicados.'
    : 'Crea la primera especialidad para ordenar el catálogo de procedimientos.'
)

const listUrl = () => {
  const params = new URLSearchParams()
  const query = searchQuery.value.trim()
  if (query) params.set('q', query)
  if (statusFilter.value !== '') params.set('is_active', statusFilter.value)
  const search = params.toString()
  return search ? `/api/specialties?${search}` : '/api/specialties'
}

const loadSpecialties = async () => {
  loading.value = true
  loadError.value = false
  try {
    const response = await get(listUrl())
    specialties.value = Array.isArray(response?.data) ? response.data : []
    kpis.value = response?.meta?.kpis ?? null
  } catch {
    loadError.value = true
    specialties.value = []
    kpis.value = null
  } finally {
    loading.value = false
  }
}

const onSearchInput = () => {
  clearTimeout(searchTimer)
  searchTimer = setTimeout(loadSpecialties, 350)
}

const clearFilters = () => {
  searchQuery.value = ''
  statusFilter.value = ''
  loadSpecialties()
}

const openCreate = () => {
  editingSpecialty.value = null
  showForm.value = true
}

const openDetail = specialty => {
  detailSpecialty.value = { ...specialty }
  showDetail.value = true
}

const openEdit = specialty => {
  editingSpecialty.value = { ...specialty }
  showForm.value = true
}

const closeForm = () => {
  showForm.value = false
  editingSpecialty.value = null
}

const onSaved = specialty => {
  const wasEditing = Boolean(editingSpecialty.value)
  closeForm()
  loadSpecialties()
  const name = specialty?.name ? ` "${specialty.name}"` : ''
  toast.success(`Especialidad${name} ${wasEditing ? 'actualizada' : 'creada'}`)
}

const toggleSpecialty = async specialty => {
  const nextActive = !specialty.is_active
  const ok = await confirm({
    title: nextActive ? 'Activar especialidad' : 'Desactivar especialidad',
    message: nextActive
      ? `¿Activar la especialidad "${specialty.name}"? Volverá a estar disponible para asignar.`
      : `¿Desactivar la especialidad "${specialty.name}"? Dejará de estar disponible para nuevas asignaciones.`,
    confirmText: nextActive ? 'Activar' : 'Desactivar',
    variant: nextActive ? 'default' : 'danger'
  })
  if (!ok) return

  try {
    await put(`/api/specialties/${specialty.id}`, { is_active: nextActive })
    toast.success(`Especialidad "${specialty.name}" ${nextActive ? 'activada' : 'desactivada'}`)
    await loadSpecialties()
  } catch (error) {
    handleError(error, `No pudimos ${nextActive ? 'activar' : 'desactivar'} la especialidad`)
  }
}

onMounted(() => {
  loadSpecialties()
})

onBeforeUnmount(() => {
  clearTimeout(searchTimer)
})
</script>
