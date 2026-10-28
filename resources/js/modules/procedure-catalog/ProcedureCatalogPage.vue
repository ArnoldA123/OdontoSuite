<template>
  <AppLayout>
    <header
      data-procedure-catalog-hero
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
          <p class="text-xs font-medium uppercase tracking-wide text-theme-secondary">Catálogo</p>
          <h2 class="mt-2 text-4xl font-semibold text-label" data-hero-title>
            Catálogo de Procedimientos
          </h2>
          <p class="mt-2 text-sm text-theme-secondary" data-hero-caption>
            Gestiona los procedimientos clínicos disponibles en la clínica
          </p>
        </div>
        <div class="flex flex-wrap flex-shrink-0 items-center gap-2 md:gap-3">
          <UiButton variant="secondary" @click="goBack">
            <template #icon-left>
              <ArrowLeftIcon class="w-4 h-4" aria-hidden="true" />
            </template>
            Volver
          </UiButton>
          <UiButton variant="secondary" data-hero-import @click="showImportModal = true">
            <template #icon-left>
              <ArrowUpTrayIcon class="w-4 h-4" aria-hidden="true" />
            </template>
            Importar CSV
          </UiButton>
          <UiButton v-if="canViewStats" variant="secondary" @click="goStats">
            <template #icon-left>
              <ChartBarIcon class="w-4 h-4" aria-hidden="true" />
            </template>
            Estadísticas
          </UiButton>
          <UiButton data-hero-action @click="openCreate">
            <template #icon-left>
              <PlusIcon class="w-4 h-4" aria-hidden="true" />
            </template>
            Nuevo procedimiento
          </UiButton>
        </div>
      </div>
    </header>

    <section aria-label="Resumen del catálogo" class="mb-6 grid grid-cols-2 gap-4 lg:grid-cols-5">
      <UiCard
        v-for="kpi in kpis"
        :key="kpi.key"
        variant="glass"
        padding="sm"
        :data-kpi-card="kpi.key"
        :style="{ boxShadow: 'var(--elevation-2)', borderColor: 'var(--color-hairline)' }"
      >
        <p class="text-xs font-medium uppercase text-theme-secondary">{{ kpi.label }}</p>
        <p
          class="mt-2 text-2xl font-bold tabular-nums leading-none"
          :class="kpi.tone"
          :data-kpi-value="kpi.key"
        >
          {{ kpi.value }}
        </p>
        <p class="mt-1 text-xs text-theme-secondary">{{ kpi.caption }}</p>
      </UiCard>
    </section>

    <UiCard variant="glass" class="mb-6">
      <div class="flex flex-col gap-4 lg:flex-row lg:items-center">
        <div class="w-full flex-1">
          <UiInput
            v-model="searchQuery"
            data-procedure-search
            placeholder="Buscar por nombre, código o descripción..."
            class="w-full"
            @input="onSearch"
          >
            <template #prefix>
              <MagnifyingGlassIcon class="w-5 h-5 text-theme-secondary" aria-hidden="true" />
            </template>
          </UiInput>
        </div>
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
          <UiSelect
            v-model="specialtyFilter"
            :options="specialtyOptions"
            placeholder="Todas las especialidades"
            class="w-full sm:w-56"
            data-specialty-filter
          />
          <UiSelect
            v-model="statusFilter"
            :options="statusOptions"
            placeholder="Todos los estados"
            class="w-full sm:w-44"
            data-status-filter
          />
          <p class="whitespace-nowrap text-xs text-theme-secondary" data-filter-count>
            {{ filterCountText }}
          </p>
        </div>
      </div>
    </UiCard>

    <section aria-label="Procedimientos" class="space-y-4">
      <div class="flex items-baseline gap-3">
        <h2 class="text-2xl font-semibold text-label">Procedimientos</h2>
        <p class="text-sm text-theme-secondary">Busca, filtra y gestiona cada procedimiento</p>
      </div>

      <UiCard variant="glass" class="overflow-hidden">
        <div v-if="loading" class="p-8 text-center">
          <LoadingSpinner class="inline-block" text="Cargando procedimientos..." />
        </div>

        <div
          v-else-if="loadError"
          data-procedures-error
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
            Revisa tu conexión e inténtalo de nuevo. Si el problema continúa, vuelve a intentarlo en
            unos minutos.
          </p>
          <div class="mt-6">
            <UiButton variant="primary" size="md" data-retry-procedures @click="load">
              Reintentar
            </UiButton>
          </div>
        </div>

        <UiEmptyState
          v-else-if="procedures.length === 0"
          :title="emptyStateTitle"
          :description="emptyStateDescription"
        >
          <template #action>
            <UiButton v-if="!hasActiveFilters" @click="openCreate">Crear procedimiento</UiButton>
          </template>
        </UiEmptyState>

        <UiDataTable
          v-else
          :data="procedures"
          :columns="tableColumns"
          :searchable="false"
          :show-header="false"
          :pagination="false"
          row-key="id"
          table-class="min-w-[880px]"
          :row-attributes="procedureRowAttributes"
          data-procedure-table
        >
          <template #cell-code="{ row }">
            <span class="font-mono text-xs text-theme-secondary">{{ row.code }}</span>
          </template>
          <template #cell-name="{ row }">
            <button
              type="button"
              class="block max-w-full truncate text-left text-sm font-medium text-label hover:text-systemBlue-700"
              :title="row.name"
              data-row-action="detail"
              @click="goDetail(row.id)"
            >
              {{ row.name }}
            </button>
            <p
              class="mt-0.5 w-full truncate text-sm text-theme-secondary"
              :title="row.description || 'Sin descripción'"
            >
              {{ row.description || 'Sin descripción' }}
            </p>
          </template>
          <template #cell-specialty_name="{ row }">
            <UiStatusBadge
              v-if="row.specialty_name"
              variant="info"
              :label="row.specialty_name"
              size="sm"
            />
            <span v-else class="text-sm text-theme-secondary">—</span>
          </template>
          <template #cell-duration="{ row }">
            <span class="text-sm tabular-nums text-theme-primary">
              {{ row.default_duration_minutes }} min
            </span>
          </template>
          <template #cell-cost="{ row }">
            <span class="text-sm tabular-nums text-theme-primary">
              {{ formatCurrency(row.default_cost) }}
            </span>
          </template>
          <template #cell-status="{ row }">
            <UiStatusBadge
              :variant="row.is_active ? 'success' : 'neutral'"
              :label="row.is_active ? 'Activo' : 'Inactivo'"
              size="sm"
            />
          </template>
          <template #actions="{ row }">
            <div class="flex items-center gap-1">
              <UiButton
                variant="ghost"
                size="sm"
                class="text-systemBlue-600 hover:text-systemBlue-700"
                data-row-action="edit"
                title="Editar procedimiento"
                aria-label="Editar procedimiento"
                @click="openEdit(row)"
              >
                <PencilIcon class="h-4 w-4" aria-hidden="true" />
              </UiButton>
              <UiButton
                variant="ghost"
                size="sm"
                class="text-systemBlue-600 hover:text-systemBlue-700"
                data-row-action="duplicate"
                title="Duplicar procedimiento"
                aria-label="Duplicar procedimiento"
                @click="openDuplicate(row)"
              >
                <DocumentDuplicateIcon class="h-4 w-4" aria-hidden="true" />
              </UiButton>
              <UiButton
                v-if="row.is_active"
                variant="ghost"
                size="sm"
                class="text-systemRed-600 hover:text-systemRed-700"
                data-row-action="deactivate"
                title="Desactivar procedimiento"
                aria-label="Desactivar procedimiento"
                @click="confirmDeactivate(row)"
              >
                <TrashIcon class="h-4 w-4" aria-hidden="true" />
              </UiButton>
              <UiButton
                v-else
                variant="ghost"
                size="sm"
                class="text-systemGreen-600 hover:text-systemGreen-700"
                data-row-action="reactivate"
                title="Reactivar procedimiento"
                aria-label="Reactivar procedimiento"
                @click="confirmReactivate(row)"
              >
                <ArrowPathIcon class="h-4 w-4" aria-hidden="true" />
              </UiButton>
            </div>
          </template>
        </UiDataTable>

        <div v-if="!loading && !loadError && totalPages > 1" class="p-4 border-t border-hairline">
          <Pagination
            :current-page="currentPage"
            :total-pages="totalPages"
            :total="pagination.total"
            :per-page="pagination.per_page || 20"
            @page-change="onPageChange"
          />
        </div>
      </UiCard>
    </section>

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

    <UiModal v-model="showDuplicateModal" title="Duplicar procedimiento" size="md">
      <form id="form-duplicate-procedure" class="space-y-4" @submit.prevent="submitDuplicate">
        <p class="text-sm text-theme-secondary">
          Se copiarán todos los datos clínicos de
          <strong class="text-theme-primary">{{ duplicateSource?.name }}</strong>
          . Asigna un código y un nombre nuevos.
        </p>
        <UiInput
          v-model="duplicateForm.code"
          label="Código del nuevo procedimiento"
          maxlength="20"
          :error="duplicateErrors.code"
          required
        />
        <UiInput
          v-model="duplicateForm.name"
          label="Nombre del nuevo procedimiento"
          :error="duplicateErrors.name"
          required
        />
      </form>
      <template #footer>
        <div class="flex justify-end gap-3">
          <UiButton variant="secondary" :disabled="duplicating" @click="showDuplicateModal = false">
            Cancelar
          </UiButton>
          <UiButton type="submit" :loading="duplicating" @click="submitDuplicate">
            Duplicar
          </UiButton>
        </div>
      </template>
    </UiModal>
  </AppLayout>
</template>

<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import {
  ArrowLeftIcon,
  ArrowPathIcon,
  ArrowUpTrayIcon,
  ChartBarIcon,
  DocumentDuplicateIcon,
  ExclamationTriangleIcon,
  MagnifyingGlassIcon,
  PencilIcon,
  PlusIcon,
  TrashIcon
} from '@heroicons/vue/24/outline'
import { useApi } from '../../composables/useApi'
import { useAuth } from '../../composables/useAuth'
import { useConfirm } from '../../composables/useConfirm'
import { useProcedureCatalog } from '../../composables/useProcedureCatalog'
import { useSpecialties } from '../../composables/useSpecialties'
import { useToast } from '../../composables/useToast'
import { formatCurrency } from '../../composables/useFormatters'
import AppLayout from '../../components/layout/AppLayout.vue'
import UiButton from '../../components/ui/Button.vue'
import UiCard from '../../components/ui/Card.vue'
import UiDataTable from '../../components/ui/DataTable.vue'
import UiEmptyState from '../../components/ui/EmptyState.vue'
import UiInput from '../../components/ui/Input.vue'
import UiModal from '../../components/ui/Modal.vue'
import UiSelect from '../../components/ui/Select.vue'
import UiStatusBadge from '../../components/ui/StatusBadge.vue'
import LoadingSpinner from '../../components/ui/LoadingSpinner.vue'
import Pagination from '../../components/ui/Pagination.vue'
import ImportCsvModal from '../../components/procedures/ImportCsvModal.vue'
import ProcedureCatalogFormModal from './ProcedureCatalogFormModal.vue'
import { PROCEDURE_CODE_MAX_LENGTH, duplicateProcedureCode } from './procedureCode'

const route = useRoute()
const router = useRouter()
const { get } = useApi()
const toast = useToast()
const { confirm } = useConfirm()
const { hasAnyRole } = useAuth()
const {
  procedures,
  loading,
  pagination,
  totalPages,
  currentPage,
  getProcedures,
  getProcedure,
  createProcedure,
  updateProcedure,
  deactivateProcedure
} = useProcedureCatalog()
const { specialties, getSpecialties } = useSpecialties()

const searchQuery = ref('')
const specialtyFilter = ref('')
const statusFilter = ref('all')
const loadError = ref(false)
const showForm = ref(false)
const editingProcedure = ref(null)
const showImportModal = ref(false)
const showDuplicateModal = ref(false)
const duplicateSource = ref(null)
const duplicateForm = ref({ code: '', name: '' })
const duplicateErrors = ref({})
const duplicating = ref(false)

const summary = ref(null)
const summaryLoading = ref(false)
const summaryError = ref(false)

// #436: the list rides the module's table contract (`UiDataTable`) instead of
// its own `<table>`; the primitive owns the scroll affordance and the row
// hooks stay available through `rowAttributes`.
const tableColumns = [
  { key: 'code', label: 'Código' },
  { key: 'name', label: 'Procedimiento' },
  { key: 'specialty_name', label: 'Especialidad' },
  { key: 'duration', label: 'Duración' },
  { key: 'cost', label: 'Costo' },
  { key: 'status', label: 'Estado' }
]

const procedureRowAttributes = row => ({ 'data-procedure-row': String(row.id) })

const canViewStats = computed(() => hasAnyRole(['administrador', 'finanzas']))

const specialtyOptions = computed(() => [
  { value: '', label: 'Todas las especialidades' },
  ...specialties.value.map(specialty => ({ value: specialty.code, label: specialty.name }))
])

const statusOptions = [
  { value: 'all', label: 'Todos los estados' },
  { value: 'active', label: 'Activos' },
  { value: 'inactive', label: 'Inactivos' }
]

const hasActiveFilters = computed(
  () =>
    Boolean(searchQuery.value.trim()) ||
    specialtyFilter.value !== '' ||
    statusFilter.value !== 'all'
)

const filterCountText = computed(
  () => `Mostrando ${procedures.value.length} de ${pagination.value.total ?? 0}`
)

const kpiValue = (key, formatter = value => String(value)) => {
  if (summaryError.value) return 'N/D'
  if (summaryLoading.value || !summary.value) return '—'
  return formatter(summary.value[key] ?? 0)
}

const kpis = computed(() => [
  {
    key: 'total',
    label: 'Procedimientos',
    value: kpiValue('total'),
    caption: 'En el catálogo',
    tone: 'text-label'
  },
  {
    key: 'active',
    label: 'Activos',
    value: kpiValue('active'),
    caption: 'Disponibles para uso clínico',
    tone: 'text-systemGreen-600'
  },
  {
    key: 'inactive',
    label: 'Inactivos',
    value: kpiValue('inactive'),
    caption: 'Fuera del flujo clínico',
    tone: 'text-theme-secondary'
  },
  {
    key: 'avg-duration',
    label: 'Duración media',
    value: kpiValue('avg_duration_minutes', value => `${Math.round(Number(value))} min`),
    caption: 'Promedio del catálogo',
    tone: 'text-label'
  },
  {
    key: 'avg-cost',
    label: 'Costo medio',
    value: kpiValue('avg_cost', value => formatCurrency(Number(value))),
    caption: 'Promedio del catálogo',
    tone: 'text-label'
  }
])

const emptyStateTitle = computed(() => {
  if (searchQuery.value.trim()) return 'Sin resultados'
  if (statusFilter.value === 'active') return 'No hay procedimientos activos'
  if (statusFilter.value === 'inactive') return 'No hay procedimientos inactivos'
  if (specialtyFilter.value) return 'No hay procedimientos en esa especialidad'
  return 'No hay procedimientos'
})

const emptyStateDescription = computed(() => {
  if (searchQuery.value.trim()) {
    return `Ningún procedimiento coincide con ${searchQuery.value.trim()}. Prueba con otro término de búsqueda.`
  }
  if (statusFilter.value === 'active')
    return 'Todos los procedimientos del catálogo están inactivos.'
  if (statusFilter.value === 'inactive')
    return 'Todos los procedimientos del catálogo están activos.'
  if (specialtyFilter.value)
    return 'Ajusta la especialidad o crea el primer procedimiento de esta área.'
  return 'Crea el primer procedimiento para empezar a usar el catálogo clínico.'
})

let searchTimeout = null

const load = async () => {
  loading.value = true
  loadError.value = false
  try {
    await getProcedures({
      q: searchQuery.value.trim() || undefined,
      specialty: specialtyFilter.value || undefined,
      is_active: statusFilter.value === 'all' ? undefined : statusFilter.value === 'active',
      page: pagination.value.current_page,
      per_page: 20
    })
    await openEditFromQuery()
  } catch {
    loadError.value = true
  } finally {
    loading.value = false
  }
}

const loadSummary = async () => {
  summaryLoading.value = true
  summaryError.value = false
  try {
    const response = await get('/api/procedure-catalog/summary')
    summary.value = response.data || null
  } catch {
    summaryError.value = true
    summary.value = null
  } finally {
    summaryLoading.value = false
  }
}

const onSearch = () => {
  clearTimeout(searchTimeout)
  searchTimeout = setTimeout(() => {
    pagination.value.current_page = 1
    load()
  }, 350)
}

const onFilterChange = () => {
  pagination.value.current_page = 1
  load()
}

watch([specialtyFilter, statusFilter], onFilterChange)

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
  const wasEditing = Boolean(editingProcedure.value)
  closeForm()
  toast.success(
    wasEditing
      ? `Procedimiento "${procedure.name}" actualizado`
      : `Procedimiento "${procedure.name}" creado`
  )
  load()
  loadSummary()
}

const openEditFromQuery = async () => {
  const editId = route.query.edit
  if (!editId) return
  let match = procedures.value.find(procedure => String(procedure.id) === String(editId))
  if (!match) {
    try {
      match = await getProcedure(editId)
    } catch {
      toast.error('No se pudo cargar el procedimiento solicitado')
    }
  }
  if (match) openEdit(match)
  router.replace({ query: { ...route.query, edit: undefined } })
}

const confirmDeactivate = async procedure => {
  const ok = await confirm({
    title: 'Desactivar procedimiento',
    message: `¿Estás seguro de desactivar "${procedure.name}"? Los procedimientos desactivados no aparecen en la selección al agendar citas.`,
    confirmText: 'Desactivar',
    variant: 'danger'
  })
  if (!ok) return
  try {
    await deactivateProcedure(procedure.id)
    toast.success(`Procedimiento "${procedure.name}" desactivado`)
    load()
    loadSummary()
  } catch {
    toast.error('No se pudo desactivar el procedimiento')
  }
}

const confirmReactivate = async procedure => {
  const ok = await confirm({
    title: 'Reactivar procedimiento',
    message: `¿Quieres reactivar "${procedure.name}" para que vuelva a estar disponible en el catálogo?`,
    confirmText: 'Reactivar',
    variant: 'default'
  })
  if (!ok) return
  try {
    await updateProcedure(procedure.id, { is_active: true })
    toast.success(`Procedimiento "${procedure.name}" reactivado`)
    load()
    loadSummary()
  } catch {
    toast.error('No se pudo reactivar el procedimiento')
  }
}

const openDuplicate = procedure => {
  duplicateSource.value = procedure
  duplicateForm.value = {
    code: duplicateProcedureCode(procedure.code),
    name: `${procedure.name} (copia)`
  }
  duplicateErrors.value = {}
  showDuplicateModal.value = true
}

const submitDuplicate = async () => {
  if (duplicating.value || !duplicateSource.value) return

  const code = duplicateForm.value.code.trim()
  const name = duplicateForm.value.name.trim()
  const localErrors = {}

  if (!code) localErrors.code = 'Requerido'
  else if (code.length > PROCEDURE_CODE_MAX_LENGTH) {
    localErrors.code = `Máximo ${PROCEDURE_CODE_MAX_LENGTH} caracteres.`
  }
  if (!name) localErrors.name = 'Requerido'

  duplicateErrors.value = localErrors
  if (Object.keys(localErrors).length > 0) return

  duplicating.value = true
  try {
    // #434: the list row does not carry the inventory recipe; the copy reads
    // the original detail so the duplicate keeps applicability, equivalences
    // and recipe.
    let source
    try {
      source = (await get(`/api/procedure-catalog/${duplicateSource.value.id}`)).data
    } catch {
      toast.error('No se pudo leer el procedimiento original para duplicarlo')
      return
    }

    const created = await createProcedure({
      code,
      name,
      description: source.description,
      specialty_id: source.specialty_id ?? null,
      legacy_specialty: source.legacy_specialty,
      site_type: source.site_type ?? 'none',
      default_cost: source.default_cost,
      default_duration_minutes: source.default_duration_minutes,
      requirements: source.requirements,
      materials_needed: source.materials_needed,
      requires_anesthesia: source.requires_anesthesia,
      requires_radiographs: source.requires_radiographs,
      steps: source.steps,
      contraindications: source.contraindications,
      post_procedure_care: source.post_procedure_care,
      external_codes: (source.external_codes ?? []).map(externalCode => ({
        system: externalCode.system,
        code: externalCode.code,
        description: externalCode.description ?? null
      })),
      material_recipe: (source.material_recipe ?? []).map(line => ({
        product_id: line.product_id,
        quantity: Number(line.quantity)
      })),
      is_active: true
    })
    showDuplicateModal.value = false
    duplicateSource.value = null
    toast.success(`Procedimiento "${created.name}" duplicado`)
    load()
    loadSummary()
  } catch (error) {
    if (error.response?.data?.errors) {
      duplicateErrors.value = Object.fromEntries(
        Object.entries(error.response.data.errors).map(([field, messages]) => [field, messages[0]])
      )
    } else {
      toast.error(error.response?.data?.message || 'No se pudo duplicar el procedimiento')
    }
  } finally {
    duplicating.value = false
  }
}

const onCsvImported = result => {
  toast.success(
    `Importación CSV: ${result.inserted} insertados, ${result.updated} actualizados, ${result.errors} errores.`
  )
  showImportModal.value = false
  load()
  loadSummary()
}

const goDetail = id => router.push(`/procedure-catalog/${id}`)
const goStats = () => router.push('/procedure-stats')
const goBack = () => router.push('/dashboard')

onMounted(async () => {
  await Promise.all([load(), loadSummary(), getSpecialties(true)])
})
</script>
