<template>
  <!--
    Canvas surface inherited from AppLayout.vue (see canvasRoutes + matchesCanvasRoute
    helper, AMB-01-001). Page header pinned on bg-canvas per DLR-R-001 so the page
    can be grep-verified for the canvas-token reference. AppointmentTypesPage.vue
    uses the same anchor for the same reason.
  -->
  <AppLayout>
    <!-- Header Section -->
    <PageHeader title="Ambientes" subtitle="Gestiona los ambientes y consultorios" class="bg-canvas mb-6">
      <template #actions>
        <UiButton variant="secondary" @click="goBack">
          <template #icon-left>
            <ArrowLeftIcon class="w-4 h-4" aria-hidden="true" />
          </template>
          Volver
        </UiButton>
        <UiButton @click="showNewEnvironmentModal = true">
          <template #icon-left>
            <PlusIcon class="w-4 h-4" aria-hidden="true" />
          </template>
          Nuevo Ambiente
        </UiButton>
      </template>
    </PageHeader>

    <!-- Search and Filters -->
    <UiCard variant="glass" padding="sm" class="mb-6" :style="panelStyle">
      <div class="flex flex-col lg:flex-row gap-4">
        <div class="flex-1">
          <UiInput
            v-model="searchQuery"
            placeholder="Buscar ambientes por nombre o descripción..."
            class="w-full"
            @input="searchEnvironments"
          >
            <template #prefix>
              <MagnifyingGlassIcon class="w-5 h-5 text-theme-secondary" aria-hidden="true" />
            </template>
          </UiInput>
        </div>
        <div class="flex gap-3">
          <UiSelect
            v-model="statusFilter"
            :options="statusOptions"
            class="w-48"
            data-status-filter
            @change="filterEnvironments"
          />
        </div>
      </div>
    </UiCard>

    <!-- Environments List -->
    <UiCard variant="glass" padding="sm" class="overflow-hidden" :style="panelStyle">
      <div v-if="loading" data-environments-loading>
        <UiSkeleton variant="table" animation="wave" aria-label="Cargando ambientes..." />
      </div>

      <div
        v-else-if="loadError"
        data-environments-error
        class="flex items-center justify-between flex-wrap gap-4 rounded-[var(--radius-panel)] p-5 bg-systemRed-50"
        style="border: 1px solid var(--color-hairline)"
      >
        <div class="flex items-center gap-3 min-w-0">
          <ExclamationTriangleIcon
            class="flex-shrink-0 w-6 h-6 text-systemRed-600"
            aria-hidden="true"
          />
          <div class="min-w-0">
            <p class="text-sm font-medium text-theme-primary">No pudimos cargar los ambientes</p>
            <p class="text-sm text-theme-secondary">Revisa tu conexión y vuelve a intentarlo.</p>
          </div>
        </div>
        <UiButton variant="primary" size="sm" @click="retryLoad">Reintentar</UiButton>
      </div>

      <div v-else-if="environments.length === 0" data-environments-empty class="py-4">
        <UiEmptyState
          :title="emptyTitle"
          :description="emptyDescription"
          :icon="BuildingOffice2Icon"
        >
          <template #action>
            <UiButton v-if="hasActiveFilters" variant="secondary" @click="clearFilters">
              Limpiar filtros
            </UiButton>
            <UiButton v-else @click="showNewEnvironmentModal = true">Nuevo Ambiente</UiButton>
          </template>
        </UiEmptyState>
      </div>

      <UiDataTable
        v-else
        :data="environments"
        :columns="columns"
        :searchable="false"
        :show-header="false"
        data-environments-table
      >
        <template #cell-name="{ row }">
          <div class="flex items-center">
            <div class="flex-shrink-0 h-10 w-10">
              <div class="h-10 w-10 rounded-full bg-systemBlue-50 flex items-center justify-center">
                <span class="text-sm font-medium text-systemBlue-700">
                  {{ row.name.charAt(0) }}
                </span>
              </div>
            </div>
            <div class="ml-4">
              <div class="text-sm font-medium text-theme-primary">{{ row.name }}</div>
            </div>
          </div>
        </template>
        <template #cell-equipment="{ row }">
          <span class="text-theme-secondary">{{ row.equipment || 'Sin equipamiento' }}</span>
        </template>
        <template #cell-status="{ row }">
          <UiStatusBadge
            :variant="getStatusVariant(row.status)"
            :label="getStatusText(row.status)"
          />
        </template>
        <template #actions="{ row }">
          <UiButton variant="link" size="sm" @click="viewDetail(row)">
            <template #icon-left>
              <EyeIcon class="w-4 h-4" aria-hidden="true" />
            </template>
            Ver Detalle
          </UiButton>
          <UiButton variant="link" size="sm" @click="editEnvironment(row)">
            <template #icon-left>
              <PencilSquareIcon class="w-4 h-4" aria-hidden="true" />
            </template>
            Editar
          </UiButton>
          <UiButton
            variant="ghost"
            size="sm"
            class="text-systemRed-700"
            @click="deleteEnvironment(row)"
          >
            <template #icon-left>
              <TrashIcon class="w-4 h-4" aria-hidden="true" />
            </template>
            Eliminar
          </UiButton>
        </template>
      </UiDataTable>
    </UiCard>
  </AppLayout>

  <!-- New Environment Modal (PR-ambientes-02 / AMB-02-007) — 4 raw form fields
       migrated to <UiInput> / <UiTextarea> / <UiSelect> primitives. `v-model=`
       bindings + `required` attributes preserved byte-for-byte. -->
  <UiModal v-model="showNewEnvironmentModal" title="Nuevo Ambiente" size="md">
    <form id="form-new-env" class="space-y-4" @submit.prevent="createEnvironment">
      <UiInput
        v-model="newEnvironment.name"
        label="Nombre del Ambiente"
        required
      />
      <UiTextarea
        v-model="newEnvironment.description"
        label="Descripción"
        :rows="3"
      />
      <UiTextarea
        v-model="newEnvironment.equipment"
        label="Equipamiento"
        :rows="2"
      />
      <UiSelect
        v-model="newEnvironment.status"
        :options="statusOptions"
        label="Estado"
        required
      />
    </form>
    <template #footer>
      <UiButton variant="secondary" :disabled="creating" @click="showNewEnvironmentModal = false">
        Cancelar
      </UiButton>
      <UiButton type="submit" :loading="creating" @click="createEnvironment">Crear</UiButton>
    </template>
  </UiModal>

  <!-- Edit Environment Modal (PR-ambientes-02 / AMB-02-007) — 4 raw form fields
       migrated to <UiInput> / <UiTextarea> / <UiSelect> primitives. `v-model=`
       bindings + `required` attributes preserved byte-for-byte. -->
  <UiModal v-model="showEditEnvironmentModal" title="Editar Ambiente" size="md">
    <form
      v-if="editingEnvironment"
      id="form-edit-env"
      class="space-y-4"
      @submit.prevent="updateEnvironment"
    >
      <UiInput
        v-model="editingEnvironment.name"
        label="Nombre"
        required
      />
      <UiInput
        v-model="editingEnvironment.code"
        label="Código"
        required
      />
      <UiTextarea
        v-model="editingEnvironment.description"
        label="Descripción"
        :rows="3"
      />
      <UiSelect
        v-model="editingEnvironment.status"
        :options="statusOptions"
        label="Estado"
        required
      />
    </form>
    <template #footer>
      <UiButton variant="secondary" :disabled="updating" @click="showEditEnvironmentModal = false">
        Cancelar
      </UiButton>
      <UiButton type="submit" :loading="updating" @click="updateEnvironment">Actualizar</UiButton>
    </template>
  </UiModal>

  <!-- View Environment Modal -->
  <UiModal v-model="showViewEnvironmentModal" title="Ver Ambiente" size="md">
    <div v-if="viewingEnvironment" class="space-y-4">
      <div>
        <label class="block text-sm font-medium text-theme-primary">Nombre</label>
        <p class="mt-1 text-sm text-theme-primary">
          {{ viewingEnvironment.name }}
        </p>
      </div>
      <div>
        <label class="block text-sm font-medium text-theme-primary">Código</label>
        <p class="mt-1 text-sm text-theme-primary">
          {{ viewingEnvironment.code }}
        </p>
      </div>
      <div>
        <label class="block text-sm font-medium text-theme-primary">Descripción</label>
        <p class="mt-1 text-sm text-theme-primary">
          {{ viewingEnvironment.description || 'Sin descripción' }}
        </p>
      </div>
      <div>
        <label class="block text-sm font-medium text-theme-primary">Estado</label>
        <p class="mt-1 text-sm text-theme-primary">
          <UiStatusBadge
            :variant="getStatusVariant(viewingEnvironment.status)"
            :label="getStatusText(viewingEnvironment.status)"
          />
        </p>
      </div>
    </div>
    <template #footer>
      <UiButton variant="secondary" @click="showViewEnvironmentModal = false">Cerrar</UiButton>
    </template>
  </UiModal>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import {
  ArrowLeftIcon,
  BuildingOffice2Icon,
  ExclamationTriangleIcon,
  EyeIcon,
  MagnifyingGlassIcon,
  PencilSquareIcon,
  PlusIcon,
  TrashIcon
} from '@heroicons/vue/24/outline'
import { useApi } from '../../composables/useApi'
import { useConfirm } from '../../composables/useConfirm'
import { useErrorHandler } from '../../composables/useErrorHandler'
import { useToast } from '../../composables/useToast'
import { getStatusText, getStatusVariant } from './environmentStatus'
import AppLayout from '../../components/layout/AppLayout.vue'
import UiButton from '../../components/ui/Button.vue'
import UiCard from '../../components/ui/Card.vue'
import UiDataTable from '../../components/ui/DataTable.vue'
import UiEmptyState from '../../components/ui/EmptyState.vue'
import UiInput from '../../components/ui/Input.vue'
import UiModal from '../../components/ui/Modal.vue'
import UiSelect from '../../components/ui/Select.vue'
import UiSkeleton from '../../components/ui/Skeleton.vue'
import UiStatusBadge from '../../components/ui/StatusBadge.vue'
import UiTextarea from '../../components/ui/UiTextarea.vue'

const router = useRouter()
const { get, post, put, delete: remove } = useApi()
const { confirm } = useConfirm()
const toast = useToast()
const { handleError } = useErrorHandler()

const loading = ref(false)
const creating = ref(false)
const updating = ref(false)
const loadError = ref(false)
const sourceRows = ref([])
const searchQuery = ref('')
const statusFilter = ref('')
const showNewEnvironmentModal = ref(false)
const showEditEnvironmentModal = ref(false)
const showViewEnvironmentModal = ref(false)
const editingEnvironment = ref(null)
const viewingEnvironment = ref(null)

const newEnvironment = ref({
  name: '',
  description: '',
  equipment: '',
  status: 'active'
})

// PR-ambientes-01 (AMB-01-002) — status filter options consumed by
// `<UiSelect :options="statusOptions" v-model="statusFilter" />`.
// Values match the legacy `<option value="...">` attributes byte-for-byte.
const statusOptions = [
  { value: '', label: 'Todos los estados' },
  { value: 'active', label: 'Activos' },
  { value: 'inactive', label: 'Inactivos' },
  { value: 'maintenance', label: 'Mantenimiento' }
]

const columns = [
  { key: 'code', label: 'Código' },
  { key: 'name', label: 'Nombre' },
  { key: 'equipment', label: 'Equipamiento' },
  { key: 'status', label: 'Estado' }
]

// Panel chrome rung shared by the filter bar + list panel (hairline edge,
// --radius-panel shape, --elevation-2 shadow).
const panelStyle = {
  borderColor: 'var(--color-hairline)',
  borderRadius: 'var(--radius-panel)',
  boxShadow: 'var(--elevation-2)'
}

// Search results come from /api/dental-chairs/search without a server-side
// status filter, so the status select is applied client-side on every fetch.
const environments = computed(() =>
  statusFilter.value
    ? sourceRows.value.filter(environment => environment.status === statusFilter.value)
    : sourceRows.value
)

const hasActiveFilters = computed(
  () => searchQuery.value.trim() !== '' || statusFilter.value !== ''
)

const emptyTitle = computed(() =>
  hasActiveFilters.value ? 'No se encontraron ambientes' : 'Aún no hay ambientes registrados'
)

const emptyDescription = computed(() =>
  hasActiveFilters.value
    ? 'Prueba con otros términos de búsqueda o quita los filtros aplicados.'
    : 'Crea el primer ambiente para empezar a gestionar tus espacios clínicos.'
)

const loadEnvironments = async () => {
  loading.value = true
  loadError.value = false
  try {
    const query = statusFilter.value ? `?status=${statusFilter.value}` : ''
    const response = await get(`/api/dental-chairs${query}`)
    sourceRows.value = response?.data || []
  } catch {
    sourceRows.value = []
    loadError.value = true
  } finally {
    loading.value = false
  }
}

const searchEnvironments = async () => {
  if (searchQuery.value.trim().length < 2) {
    loadEnvironments()
    return
  }

  loading.value = true
  loadError.value = false
  try {
    const response = await get(
      `/api/dental-chairs/search?q=${encodeURIComponent(searchQuery.value)}`
    )
    sourceRows.value = response?.data || []
  } catch {
    sourceRows.value = []
    loadError.value = true
  } finally {
    loading.value = false
  }
}

const filterEnvironments = () => {
  if (searchQuery.value.trim().length >= 2) return
  loadEnvironments()
}

const retryLoad = () => {
  if (searchQuery.value.trim().length >= 2) {
    searchEnvironments()
  } else {
    loadEnvironments()
  }
}

const clearFilters = () => {
  searchQuery.value = ''
  statusFilter.value = ''
  loadEnvironments()
}

const createEnvironment = async () => {
  if (creating.value) return
  creating.value = true
  try {
    await post('/api/dental-chairs', newEnvironment.value)
    showNewEnvironmentModal.value = false
    newEnvironment.value = {
      name: '',
      description: '',
      equipment: '',
      status: 'active'
    }
    toast.success('Ambiente creado exitosamente')
    loadEnvironments()
  } catch (error) {
    handleError(error, 'Error al crear el ambiente')
  } finally {
    creating.value = false
  }
}

const editEnvironment = environment => {
  editingEnvironment.value = { ...environment }
  showEditEnvironmentModal.value = true
}

// Kept unreachable on purpose: T3 removes the View modal template and this handler together.
// eslint-disable-next-line no-unused-vars
const viewEnvironment = environment => {
  viewingEnvironment.value = environment
  showViewEnvironmentModal.value = true
}

const viewDetail = environment => {
  router.push(`/environments/${environment.id}`)
}

const updateEnvironment = async () => {
  if (updating.value) return
  updating.value = true
  try {
    await put(`/api/dental-chairs/${editingEnvironment.value.id}`, editingEnvironment.value)
    showEditEnvironmentModal.value = false
    editingEnvironment.value = null
    loadEnvironments()
    toast.success('Ambiente actualizado exitosamente')
  } catch (error) {
    handleError(error, 'Error al actualizar el ambiente')
  } finally {
    updating.value = false
  }
}

const deleteEnvironment = async environment => {
  const ok = await confirm({
    title: 'Eliminar ambiente',
    message: `¿Estás seguro de que quieres eliminar el ambiente ${environment.name}?`,
    confirmText: 'Eliminar',
    variant: 'danger'
  })
  if (ok) {
    try {
      await remove(`/api/dental-chairs/${environment.id}`)
      loadEnvironments()
      toast.success('Ambiente eliminado exitosamente')
    } catch (error) {
      handleError(error, 'Error al eliminar el ambiente')
    }
  }
}

const goBack = () => {
  router.back()
}

onMounted(() => {
  loadEnvironments()
})
</script>
