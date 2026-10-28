<template>
  <!--
    Canvas surface inherited from AppLayout.vue (see canvasRoutes + matchesCanvasRoute
    helper, AMB-01-001). Page header pinned on bg-canvas per DLR-R-001 so the page
    can be grep-verified for the canvas-token reference. AppointmentTypesPage.vue
    uses the same anchor for the same reason.
  -->
  <AppLayout>
    <!-- Header Section -->
    <PageHeader
      title="Ambientes"
      subtitle="Gestiona los ambientes y consultorios"
      class="bg-canvas mb-6"
    >
      <template #actions>
        <UiButton variant="secondary" @click="goBack">
          <template #icon-left>
            <ArrowLeftIcon class="w-4 h-4" aria-hidden="true" />
          </template>
          Volver
        </UiButton>
        <UiButton v-if="canManageEnvironments" @click="openCreateModal">
          <template #icon-left>
            <PlusIcon class="w-4 h-4" aria-hidden="true" />
          </template>
          Nuevo ambiente
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
            @input="handleSearchInput"
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
          <UiSelect
            v-model="archivedFilter"
            :options="archivedOptions"
            class="w-56"
            data-archived-filter
            @change="filterArchived"
          />
        </div>
      </div>
    </UiCard>

    <!-- Environments List (T5/F5): no page-level card wraps this section —
         the DataTable primitive renders its own single framed surface
         (data-table-container), and the transient states (skeleton / error
         callout / empty state) carry their own chrome. Wrapping it all in a
         glass card stacked two bordered boxes with the same background. -->
    <div data-environments-list>
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
            <p class="text-sm text-theme-secondary">{{ loadErrorMessage }}</p>
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
            <UiButton v-else-if="canManageEnvironments" @click="openCreateModal">
              Nuevo ambiente
            </UiButton>
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
            <div class="ml-4 flex items-center gap-2">
              <div class="text-sm font-medium text-theme-primary">{{ row.name }}</div>
              <UiBadge v-if="!row.is_active" variant="neutral" size="sm">Archivado</UiBadge>
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
          <UiButton variant="ghost" size="sm" @click="viewDetail(row)">
            <template #icon-left>
              <EyeIcon class="w-4 h-4" aria-hidden="true" />
            </template>
            Ver detalle
          </UiButton>
          <UiButton
            v-if="canManageEnvironments"
            variant="ghost"
            size="sm"
            @click="editEnvironment(row)"
          >
            <template #icon-left>
              <PencilSquareIcon class="w-4 h-4" aria-hidden="true" />
            </template>
            Editar
          </UiButton>
          <UiButton
            v-if="canManageEnvironments && row.is_active"
            variant="ghost"
            size="sm"
            @click="archiveEnvironment(row)"
          >
            <template #icon-left>
              <ArchiveBoxIcon class="w-4 h-4" aria-hidden="true" />
            </template>
            Archivar
          </UiButton>
          <UiButton
            v-else-if="canManageEnvironments"
            variant="ghost"
            size="sm"
            @click="reactivateEnvironment(row)"
          >
            <template #icon-left>
              <ArrowPathIcon class="w-4 h-4" aria-hidden="true" />
            </template>
            Reactivar
          </UiButton>
          <UiButton
            v-if="canManageEnvironments"
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
    </div>
  </AppLayout>

  <!-- New environment modal (T3): grouped form (identification / description /
       equipment / status) with the saving state exposed on the primary action
       and the form locked while the request runs. The container is a <div>,
       not a <form>: UiSelect's internal buttons lack `type="button"` and would
       trigger implicit submission on every option click. The v-model bindings
       and required semantics pinned by EnvironmentsModalChromeTest (AMB-02-007)
       are preserved verbatim. -->
  <UiModal v-model="showNewEnvironmentModal" title="Nuevo ambiente" size="md">
    <div class="space-y-6" data-environment-form="create">
      <fieldset class="space-y-3">
        <legend class="text-xs font-semibold uppercase tracking-wide text-theme-secondary">
          Identificación
        </legend>
        <div data-field="name">
          <UiInput
            v-model="newEnvironment.name"
            label="Nombre del ambiente"
            class="w-full"
            required
            :error="createErrors.name"
            :disabled="creating"
          />
        </div>
      </fieldset>
      <fieldset class="space-y-3">
        <legend class="text-xs font-semibold uppercase tracking-wide text-theme-secondary">
          Descripción
        </legend>
        <div data-field="description">
          <UiTextarea
            v-model="newEnvironment.description"
            :rows="3"
            aria-label="Descripción"
            :disabled="creating"
          />
        </div>
      </fieldset>
      <fieldset class="space-y-3">
        <legend class="text-xs font-semibold uppercase tracking-wide text-theme-secondary">
          Equipamiento
        </legend>
        <div data-field="equipment">
          <UiTextarea
            v-model="newEnvironment.equipment"
            :rows="2"
            aria-label="Equipamiento"
            :disabled="creating"
          />
        </div>
      </fieldset>
      <fieldset class="space-y-3">
        <legend class="text-xs font-semibold uppercase tracking-wide text-theme-secondary">
          Estado
        </legend>
        <div class="flex items-center gap-3" data-field="status">
          <UiSelect
            v-model="newEnvironment.status"
            :options="statusFieldOptions"
            label="Estado"
            class="flex-1"
            required
            :disabled="creating"
          />
          <UiStatusBadge
            :variant="getStatusVariant(newEnvironment.status)"
            :label="getStatusText(newEnvironment.status)"
          />
        </div>
      </fieldset>
    </div>
    <template #footer>
      <UiButton
        type="button"
        variant="secondary"
        :disabled="creating"
        data-modal-cancel="create"
        @click="showNewEnvironmentModal = false"
      >
        Cancelar
      </UiButton>
      <UiButton
        type="button"
        :loading="creating"
        data-modal-submit="create"
        @click="createEnvironment"
      >
        Crear ambiente
      </UiButton>
    </template>
  </UiModal>

  <!-- Edit environment modal (T3 + T5): identification (name + read-only
       code), description, equipment, status. The prefill bindings are the
       ones pinned by EnvironmentsModalChromeTest (AMB-02-007). The code
       field is read-only (F3): DentalChairController@update validates
       updateRules(storeRules()) and storeRules() has no `code` rule, so
       code is generated server-side on create and never editable. -->
  <UiModal v-model="showEditEnvironmentModal" title="Editar ambiente" size="md">
    <div v-if="editingEnvironment" class="space-y-6" data-environment-form="edit">
      <fieldset class="space-y-3">
        <legend class="text-xs font-semibold uppercase tracking-wide text-theme-secondary">
          Identificación
        </legend>
        <div class="space-y-4">
          <div data-field="name">
            <UiInput
              v-model="editingEnvironment.name"
              label="Nombre del ambiente"
              class="w-full"
              required
              :error="editErrors.name"
              :disabled="updating"
            />
          </div>
          <div data-field="code">
            <UiInput
              v-model="editingEnvironment.code"
              label="Código"
              class="w-full"
              required
              readonly
              hint="Generado automáticamente"
              :disabled="updating"
            />
          </div>
        </div>
      </fieldset>
      <fieldset class="space-y-3">
        <legend class="text-xs font-semibold uppercase tracking-wide text-theme-secondary">
          Descripción
        </legend>
        <div data-field="description">
          <UiTextarea
            v-model="editingEnvironment.description"
            :rows="3"
            aria-label="Descripción"
            :disabled="updating"
          />
        </div>
      </fieldset>
      <fieldset class="space-y-3">
        <legend class="text-xs font-semibold uppercase tracking-wide text-theme-secondary">
          Equipamiento
        </legend>
        <div data-field="equipment">
          <UiTextarea
            v-model="editingEnvironment.equipment"
            :rows="2"
            aria-label="Equipamiento"
            :disabled="updating"
          />
        </div>
      </fieldset>
      <fieldset class="space-y-3">
        <legend class="text-xs font-semibold uppercase tracking-wide text-theme-secondary">
          Estado
        </legend>
        <div class="flex items-center gap-3" data-field="status">
          <UiSelect
            v-model="editingEnvironment.status"
            :options="statusFieldOptions"
            label="Estado"
            class="flex-1"
            required
            :disabled="updating"
          />
          <UiStatusBadge
            :variant="getStatusVariant(editingEnvironment.status)"
            :label="getStatusText(editingEnvironment.status)"
          />
        </div>
      </fieldset>
    </div>
    <template #footer>
      <UiButton
        type="button"
        variant="secondary"
        :disabled="updating"
        data-modal-cancel="edit"
        @click="showEditEnvironmentModal = false"
      >
        Cancelar
      </UiButton>
      <UiButton
        type="button"
        :loading="updating"
        data-modal-submit="edit"
        @click="updateEnvironment"
      >
        Actualizar ambiente
      </UiButton>
    </template>
  </UiModal>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import {
  ArchiveBoxIcon,
  ArrowLeftIcon,
  ArrowPathIcon,
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
import { usePermissions } from '../../composables/usePermissions'
import { useToast } from '../../composables/useToast'
import { getStatusText, getStatusVariant } from './environmentStatus'
import AppLayout from '../../components/layout/AppLayout.vue'
import UiButton from '../../components/ui/Button.vue'
import UiCard from '../../components/ui/Card.vue'
import UiBadge from '../../components/ui/Badge.vue'
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
const { can } = usePermissions()

// #439: the page offers a write door only to a session that holds the same
// permission its route demands (`manage.dental_chair`). Readers keep the list,
// the filters and "Ver detalle".
const canManageEnvironments = computed(() => Boolean(can.manageEnvironments?.value))

const loading = ref(false)
const creating = ref(false)
const updating = ref(false)
const loadError = ref(false)
const loadErrorMessage = ref('')
const sourceRows = ref([])
const searchQuery = ref('')
const statusFilter = ref('')
const archivedFilter = ref('exclude')
const showNewEnvironmentModal = ref(false)
const showEditEnvironmentModal = ref(false)
const editingEnvironment = ref(null)
const createErrors = ref({})
const editErrors = ref({})

const newEnvironment = ref({
  name: '',
  description: '',
  equipment: '',
  status: 'active'
})

// PR-ambientes-01 (AMB-01-002) status filter options consumed by
// `<UiSelect :options="statusOptions" v-model="statusFilter" />`.
// Values match the legacy `<option value="...">` attributes byte-for-byte.
const statusOptions = [
  { value: '', label: 'Todos los estados' },
  { value: 'active', label: 'Activos' },
  { value: 'inactive', label: 'Inactivos' },
  { value: 'maintenance', label: 'Mantenimiento' }
]

// Archive visibility is a separate axis from the operational status: a chair
// can be in maintenance while still visible, or archived (is_active=false) and
// only reachable through this filter.
const archivedOptions = [
  { value: 'exclude', label: 'Sin archivados' },
  { value: 'include', label: 'Incluir archivados' },
  { value: 'only', label: 'Solo archivados' }
]

// Form-state options for the create/edit modals: only the operational
// statuses (the filter's "Todos los estados" entry is not a valid form value).
// Labels come from the shared vocabulary (T3, read-only consumption).
const statusFieldOptions = ['active', 'inactive', 'maintenance'].map(value => ({
  value,
  label: getStatusText(value)
}))

const columns = [
  { key: 'code', label: 'Código' },
  { key: 'name', label: 'Nombre' },
  { key: 'equipment', label: 'Equipamiento' },
  { key: 'status', label: 'Estado' }
]

// Panel chrome rung for the filter bar (hairline edge, --radius-panel shape,
// --elevation-2 shadow). The list section deliberately has no page-level
// chrome: the DataTable primitive owns the single framed surface (T5/F5).
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
  () =>
    searchQuery.value.trim() !== '' ||
    statusFilter.value !== '' ||
    archivedFilter.value !== 'exclude'
)

const emptyTitle = computed(() =>
  hasActiveFilters.value ? 'No se encontraron ambientes' : 'Aún no hay ambientes registrados'
)

const emptyDescription = computed(() =>
  hasActiveFilters.value
    ? 'Prueba con otros términos de búsqueda o quita los filtros aplicados.'
    : 'Crea el primer ambiente para empezar a gestionar tus espacios clínicos.'
)

const SEARCH_DEBOUNCE_MS = 300

const CONNECTION_ERROR_MESSAGE = 'Revisa tu conexión y vuelve a intentarlo.'
const SERVER_ERROR_MESSAGE = 'Ocurrió un error en el servidor. Intenta nuevamente.'

// Errors arrive either as a useApi throw (`{ status, response }`) or as a plain
// Error when the request never completed, so both carriers are read.
const requestStatus = error => error?.status ?? error?.response?.status

const listErrorMessage = error => {
  const status = requestStatus(error)
  if (status === 403) return 'No tienes permisos para ver los ambientes.'
  if (typeof status === 'number' && status >= 500) return SERVER_ERROR_MESSAGE
  return CONNECTION_ERROR_MESSAGE
}

// Monotonic token: only the newest request may commit rows, error state or the
// loading flag, so a slow response can never overwrite a newer one.
let latestRequestId = 0
let searchDebounceTimer = null

const clearPendingSearch = () => {
  if (searchDebounceTimer === null) return
  clearTimeout(searchDebounceTimer)
  searchDebounceTimer = null
}

const fetchEnvironments = async url => {
  const requestId = ++latestRequestId
  loading.value = true
  loadError.value = false
  loadErrorMessage.value = ''
  try {
    const response = await get(url)
    if (requestId !== latestRequestId) return
    sourceRows.value = response?.data || []
  } catch (error) {
    if (requestId !== latestRequestId) return
    sourceRows.value = []
    loadError.value = true
    loadErrorMessage.value = listErrorMessage(error)
  } finally {
    if (requestId === latestRequestId) loading.value = false
  }
}

const loadEnvironments = () => {
  const params = new URLSearchParams()
  if (statusFilter.value) params.set('status', statusFilter.value)
  if (archivedFilter.value !== 'exclude') params.set('archived', archivedFilter.value)
  const query = params.toString()

  return fetchEnvironments(`/api/dental-chairs${query ? `?${query}` : ''}`)
}

const searchEnvironments = () => {
  clearPendingSearch()
  const params = new URLSearchParams({ q: searchQuery.value.trim() })
  if (archivedFilter.value !== 'exclude') params.set('archived', archivedFilter.value)

  return fetchEnvironments(`/api/dental-chairs/search?${params.toString()}`)
}

// One request 300 ms after the last keystroke. Queries shorter than two
// characters never hit /search: they reload the unfiltered list immediately.
const handleSearchInput = () => {
  clearPendingSearch()
  if (searchQuery.value.trim().length < 2) {
    loadEnvironments()
    return
  }
  searchDebounceTimer = setTimeout(() => {
    searchDebounceTimer = null
    searchEnvironments()
  }, SEARCH_DEBOUNCE_MS)
}

const filterEnvironments = () => {
  clearPendingSearch()
  if (searchQuery.value.trim().length >= 2) return
  loadEnvironments()
}

const filterArchived = () => {
  clearPendingSearch()
  if (searchQuery.value.trim().length >= 2) {
    searchEnvironments()
    return
  }
  loadEnvironments()
}

const reloadCurrentView = () => {
  if (searchQuery.value.trim().length >= 2) return searchEnvironments()
  return loadEnvironments()
}

const retryLoad = () => {
  if (searchQuery.value.trim().length >= 2) {
    searchEnvironments()
  } else {
    loadEnvironments()
  }
}

const clearFilters = () => {
  clearPendingSearch()
  searchQuery.value = ''
  statusFilter.value = ''
  archivedFilter.value = 'exclude'
  loadEnvironments()
}

// #440: a rejected save names the field that failed. `/api/dental-chairs`
// answers 422 with `errors.<field>`, so the first message per key feeds the
// modal field and the toast is reserved for errors that carry no field.
const fieldErrors = error => {
  const errors = error?.response?.data?.errors
  if (!errors || typeof errors !== 'object') return null
  const mapped = Object.fromEntries(
    Object.entries(errors).map(([field, messages]) => [
      field,
      Array.isArray(messages) ? messages[0] : messages
    ])
  )
  return Object.keys(mapped).length > 0 ? mapped : null
}

const openCreateModal = () => {
  createErrors.value = {}
  showNewEnvironmentModal.value = true
}

const createEnvironment = async () => {
  if (creating.value) return
  creating.value = true
  createErrors.value = {}
  try {
    await post('/api/dental-chairs', newEnvironment.value)
    showNewEnvironmentModal.value = false
    createErrors.value = {}
    newEnvironment.value = {
      name: '',
      description: '',
      equipment: '',
      status: 'active'
    }
    toast.success('Ambiente creado exitosamente')
    reloadCurrentView()
  } catch (error) {
    const errors = fieldErrors(error)
    if (errors) {
      createErrors.value = errors
      return
    }
    handleError(error, 'Error al crear el ambiente')
  } finally {
    creating.value = false
  }
}

const editEnvironment = environment => {
  editErrors.value = {}
  editingEnvironment.value = { ...environment }
  showEditEnvironmentModal.value = true
}

const viewDetail = environment => {
  router.push(`/environments/${environment.id}`)
}

const updateEnvironment = async () => {
  if (updating.value) return
  updating.value = true
  editErrors.value = {}
  try {
    await put(`/api/dental-chairs/${editingEnvironment.value.id}`, editingEnvironment.value)
    showEditEnvironmentModal.value = false
    editingEnvironment.value = null
    editErrors.value = {}
    reloadCurrentView()
    toast.success('Ambiente actualizado exitosamente')
  } catch (error) {
    const errors = fieldErrors(error)
    if (errors) {
      editErrors.value = errors
      return
    }
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
      reloadCurrentView()
      toast.success('Ambiente eliminado exitosamente')
    } catch (error) {
      const status = error?.status ?? error?.response?.status
      handleError(
        error,
        status === 409 ? error?.response?.data?.message : 'Error al eliminar el ambiente'
      )
    }
  }
}

const goBack = () => {
  router.back()
}
const archiveEnvironment = async environment => {
  const ok = await confirm({
    title: 'Archivar ambiente',
    message: `¿Archivar el ambiente ${environment.name}? Dejará de estar disponible para agendar citas, pero conservará su historial.`,
    confirmText: 'Archivar',
    variant: 'danger'
  })
  if (!ok) return

  try {
    await put(`/api/dental-chairs/${environment.id}`, { is_active: false })
    toast.success('Ambiente archivado exitosamente')
    reloadCurrentView()
  } catch (error) {
    handleError(error, 'Error al archivar el ambiente')
  }
}

const reactivateEnvironment = async environment => {
  try {
    await put(`/api/dental-chairs/${environment.id}`, { is_active: true })
    toast.success('Ambiente reactivado exitosamente')
    reloadCurrentView()
  } catch (error) {
    handleError(error, 'Error al reactivar el ambiente')
  }
}

onMounted(() => {
  loadEnvironments()
})

onBeforeUnmount(() => {
  clearPendingSearch()
})
</script>
