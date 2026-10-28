<template>
  <AppLayout>
    <header
      data-pm-hero
      class="mb-6 border border-hairline p-6 md:p-8"
      style="
        background: var(--color-canvas);
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
          <h2 class="mt-2 text-4xl font-semibold text-label" data-hero-title>Métodos de Pago</h2>
          <p class="mt-2 text-sm text-theme-secondary" data-hero-caption>
            Gestiona los métodos con los que la clínica registra cobros
          </p>
        </div>
        <div class="flex flex-shrink-0 items-center gap-3">
          <UiButton variant="secondary" @click="goBack">
            <template #icon-left>
              <ArrowLeftIcon class="h-4 w-4" aria-hidden="true" />
            </template>
            Volver
          </UiButton>
          <UiButton v-if="can.managePaymentMethods?.value" variant="primary" @click="openCreate">
            <template #icon-left>
              <PlusIcon class="h-4 w-4" aria-hidden="true" />
            </template>
            Nuevo método
          </UiButton>
        </div>
      </div>
    </header>

    <section
      aria-label="Resumen de métodos de pago"
      class="mb-6 grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-4"
    >
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
          {{ kpiTotal }}
        </p>
        <p class="mt-1 text-xs text-theme-secondary">Métodos registrados</p>
      </UiCard>

      <UiCard
        variant="glass"
        padding="sm"
        data-kpi-card="active"
        :style="{ boxShadow: 'var(--elevation-2)', borderColor: 'var(--color-hairline)' }"
      >
        <p class="text-xs font-medium uppercase text-theme-secondary">Activos</p>
        <p
          class="mt-2 text-2xl font-bold text-label tabular-nums leading-none"
          data-kpi-value="active"
        >
          {{ kpiActive }}
        </p>
        <p class="mt-1 text-xs text-theme-secondary">Disponibles para cobro</p>
      </UiCard>

      <UiCard
        variant="glass"
        padding="sm"
        data-kpi-card="inactive"
        :style="{ boxShadow: 'var(--elevation-2)', borderColor: 'var(--color-hairline)' }"
      >
        <p class="text-xs font-medium uppercase text-theme-secondary">Inactivos</p>
        <p
          class="mt-2 text-2xl font-bold text-label tabular-nums leading-none"
          data-kpi-value="inactive"
        >
          {{ kpiInactive }}
        </p>
        <p class="mt-1 text-xs text-theme-secondary">Fuera de circulación</p>
      </UiCard>

      <UiCard
        variant="glass"
        padding="sm"
        data-kpi-card="system"
        :style="{ boxShadow: 'var(--elevation-2)', borderColor: 'var(--color-hairline)' }"
      >
        <p class="text-xs font-medium uppercase text-theme-secondary">Del sistema</p>
        <p
          class="mt-2 text-2xl font-bold text-label tabular-nums leading-none"
          data-kpi-value="system"
        >
          {{ kpiSystem }}
        </p>
        <p class="mt-1 text-xs text-theme-secondary">Gestionados por el sistema</p>
      </UiCard>
    </section>

    <UiCard variant="glass" class="mb-6">
      <div class="flex flex-col gap-4 lg:flex-row lg:items-center">
        <div class="w-full flex-1">
          <UiInput
            v-model="searchQuery"
            data-pm-search
            placeholder="Buscar por nombre, código o descripción..."
            class="w-full"
            @input="onSearchInput"
          >
            <template #prefix>
              <MagnifyingGlassIcon class="h-5 w-5 text-theme-secondary" aria-hidden="true" />
            </template>
          </UiInput>
        </div>
        <UiSelect
          v-model="statusFilter"
          :options="STATUS_FILTER_OPTIONS"
          placeholder="Todos los estados"
          class="w-48"
          data-status-filter
          @change="loadMethods"
        />
        <UiSelect
          v-model="typeFilter"
          :options="TYPE_FILTER_OPTIONS"
          placeholder="Todas las familias"
          class="w-48"
          data-type-filter
        />
      </div>
    </UiCard>

    <section aria-label="Métodos de pago" class="space-y-4">
      <div class="flex flex-col gap-1 sm:flex-row sm:items-baseline sm:gap-3">
        <h2 class="text-2xl font-semibold text-label">Todos los métodos</h2>
        <p class="text-sm text-theme-secondary">Busca, filtra y gestiona cada método</p>
      </div>

      <div data-pm-list>
        <div v-if="loading" data-pm-loading>
          <UiSkeleton variant="table" animation="wave" aria-label="Cargando métodos de pago..." />
        </div>

        <div
          v-else-if="loadError"
          data-pm-error
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
                No pudimos cargar los métodos de pago
              </p>
              <p class="text-sm text-theme-secondary">Revisa tu conexión y vuelve a intentarlo.</p>
            </div>
          </div>
          <UiButton variant="primary" size="sm" data-pm-retry @click="loadMethods">
            Reintentar
          </UiButton>
        </div>

        <div v-else-if="filteredMethods.length === 0" data-pm-empty>
          <UiEmptyState
            :title="emptyStateTitle"
            :description="emptyStateDescription"
            :icon="CreditCardIcon"
          >
            <template #action>
              <UiButton v-if="hasActiveFilters" variant="secondary" @click="clearFilters">
                Limpiar filtros
              </UiButton>
              <UiButton v-else-if="can.managePaymentMethods?.value" @click="openCreate">
                Nuevo método
              </UiButton>
            </template>
          </UiEmptyState>
        </div>

        <UiDataTable
          v-else
          :data="filteredMethods"
          :columns="columns"
          :searchable="false"
          :show-header="false"
          :pagination="false"
          data-pm-table
        >
          <template #cell-name="{ row }">
            <div class="min-w-0">
              <p class="text-sm font-medium text-theme-primary" data-row-method-name>
                {{ row.name }}
              </p>
              <p class="font-mono text-xs text-theme-secondary" data-row-method-code>
                {{ row.code }}
              </p>
              <p
                v-if="row.description"
                class="mt-1 max-w-md truncate text-xs text-theme-secondary"
                :title="row.description"
              >
                {{ row.description }}
              </p>
            </div>
          </template>

          <template #cell-gateway="{ row }">
            <div class="flex items-center gap-2">
              <span class="text-sm text-theme-primary">
                {{ getGatewayLabel(row.gateway_type) }}
              </span>
              <span
                v-if="row.has_gateway_config"
                data-gateway-configured
                class="inline-flex items-center gap-1 text-xs font-medium text-systemGreen-700"
              >
                <span class="h-1.5 w-1.5 rounded-full bg-systemGreen-500" aria-hidden="true" />
                Configurado
              </span>
            </div>
          </template>

          <template #cell-commission="{ row }">
            <span class="tabular-nums">{{ formatCommission(row.commission_percentage) }}</span>
          </template>

          <template #cell-family="{ row }">
            <UiStatusBadge
              :variant="getTypeVariant(row.type)"
              :label="getTypeLabel(row.type)"
              size="sm"
            />
          </template>

          <template #cell-reference="{ row }">
            <span
              data-row-reference
              :data-requires-reference="row.requires_reference ? 'true' : 'false'"
              class="text-sm text-theme-primary"
            >
              {{ row.requires_reference ? 'Sí' : 'No' }}
            </span>
          </template>

          <template #cell-scope="{ row }">
            <UiStatusBadge
              :variant="getScopeVariant(row.is_system)"
              :label="getScopeLabel(row.is_system)"
              size="sm"
            />
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
              v-if="can.managePaymentMethods?.value"
              variant="ghost"
              size="sm"
              class="text-systemBlue-600 hover:text-systemBlue-700"
              data-row-action="edit"
              @click="openEdit(row)"
            >
              Editar
            </UiButton>
            <UiButton
              v-if="can.managePaymentMethods?.value"
              variant="ghost"
              size="sm"
              :class="row.is_active ? 'text-systemRed-700' : 'text-systemGreen-700'"
              data-row-action="toggle"
              @click="toggleMethod(row)"
            >
              {{ row.is_active ? 'Desactivar' : 'Activar' }}
            </UiButton>
            <UiButton
              v-if="can.managePaymentMethods?.value && canDelete(row)"
              variant="ghost"
              size="sm"
              class="text-systemRed-700"
              data-row-action="delete"
              @click="deleteMethod(row)"
            >
              Eliminar
            </UiButton>
          </template>
        </UiDataTable>
      </div>
    </section>
  </AppLayout>

  <PaymentMethodFormModal
    v-if="showForm"
    :method="editingMethod"
    @close="closeForm"
    @saved="onSaved"
  />
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import {
  ArrowLeftIcon,
  CreditCardIcon,
  ExclamationTriangleIcon,
  MagnifyingGlassIcon,
  PlusIcon
} from '@heroicons/vue/24/outline'
import { useApi } from '../../../composables/useApi'
import { useConfirm } from '../../../composables/useConfirm'
import { useErrorHandler } from '../../../composables/useErrorHandler'
import { usePermissions } from '../../../composables/usePermissions'
import { useToast } from '../../../composables/useToast'
import AppLayout from '../../../components/layout/AppLayout.vue'
import UiButton from '../../../components/ui/Button.vue'
import UiCard from '../../../components/ui/Card.vue'
import UiDataTable from '../../../components/ui/DataTable.vue'
import UiEmptyState from '../../../components/ui/EmptyState.vue'
import UiInput from '../../../components/ui/Input.vue'
import UiSelect from '../../../components/ui/Select.vue'
import UiSkeleton from '../../../components/ui/Skeleton.vue'
import UiStatusBadge from '../../../components/ui/StatusBadge.vue'
import PaymentMethodFormModal from './PaymentMethodFormModal.vue'
import {
  STATUS_FILTER_OPTIONS,
  TYPE_FILTER_OPTIONS,
  canDelete,
  formatCommission,
  getGatewayLabel,
  getScopeLabel,
  getScopeVariant,
  getStatusLabel,
  getStatusVariant,
  getTypeLabel,
  getTypeVariant
} from './paymentMethodMeta'

const router = useRouter()
const { get, put, delete: remove } = useApi()
const { confirm } = useConfirm()
const toast = useToast()
const { handleError } = useErrorHandler()
// #439: writing the directory needs the permission the API already demands; a
// read-only session keeps the list, the filters and the KPIs.
const { can } = usePermissions()

const loading = ref(true)
const loadError = ref(false)
// #445: the KPI strip describes the whole directory, so a filtered read is
// paired with an unfiltered population read.
const populationLoading = ref(false)
const populationError = ref(false)
const methods = ref([])
const population = ref([])
const searchQuery = ref('')
const statusFilter = ref('')
const typeFilter = ref('')
const showForm = ref(false)
const editingMethod = ref(null)

let searchTimer = null

const columns = [
  { key: 'name', label: 'Método' },
  { key: 'gateway', label: 'Pasarela' },
  { key: 'commission', label: 'Comisión' },
  { key: 'family', label: 'Familia' },
  { key: 'reference', label: 'Referencia' },
  { key: 'scope', label: 'Tipo' },
  { key: 'status', label: 'Estado' }
]

// The two server filters narrow the read; the family filter is client-side and
// never changes the population.
const serverFiltersActive = computed(
  () => searchQuery.value.trim() !== '' || statusFilter.value !== ''
)

const hasActiveFilters = computed(() => serverFiltersActive.value || typeFilter.value !== '')

const filteredMethods = computed(() =>
  typeFilter.value === ''
    ? methods.value
    : methods.value.filter(method => method.type === typeFilter.value)
)

const populationMethods = computed(() =>
  serverFiltersActive.value ? population.value : methods.value
)

const kpisUnavailable = computed(
  () => loading.value || populationLoading.value || loadError.value || populationError.value
)

const kpiTotal = computed(() =>
  kpisUnavailable.value ? 'N/D' : String(populationMethods.value.length)
)

const kpiActive = computed(() =>
  kpisUnavailable.value
    ? 'N/D'
    : String(populationMethods.value.filter(method => method.is_active).length)
)

const kpiInactive = computed(() =>
  kpisUnavailable.value
    ? 'N/D'
    : String(populationMethods.value.filter(method => !method.is_active).length)
)

const kpiSystem = computed(() =>
  kpisUnavailable.value
    ? 'N/D'
    : String(populationMethods.value.filter(method => method.is_system).length)
)

const emptyStateTitle = computed(() =>
  hasActiveFilters.value ? 'No se encontraron métodos de pago' : 'Aún no hay métodos de pago'
)

const emptyStateDescription = computed(() =>
  hasActiveFilters.value
    ? 'Prueba con otros términos de búsqueda o quita los filtros aplicados.'
    : 'Crea el primer método de pago para empezar a registrar cobros.'
)

const listUrl = () => {
  const params = new URLSearchParams()
  const query = searchQuery.value.trim()
  if (query) params.set('q', query)
  if (statusFilter.value !== '') params.set('is_active', statusFilter.value)
  const search = params.toString()
  return search ? `/api/payment-methods?${search}` : '/api/payment-methods'
}

const fetchMethods = async url => {
  const response = await get(url)
  return Array.isArray(response?.data) ? response.data : []
}

const loadMethods = async () => {
  const needsPopulation = serverFiltersActive.value

  loading.value = true
  loadError.value = false
  populationLoading.value = needsPopulation
  populationError.value = false

  const [listResult, populationResult] = await Promise.allSettled([
    fetchMethods(listUrl()),
    needsPopulation ? fetchMethods('/api/payment-methods') : Promise.resolve([])
  ])

  loading.value = false
  populationLoading.value = false

  if (listResult.status === 'fulfilled') {
    methods.value = listResult.value
  } else {
    loadError.value = true
    methods.value = []
  }

  if (!needsPopulation) {
    population.value = []
    return
  }

  if (populationResult.status === 'fulfilled') {
    population.value = populationResult.value
  } else {
    populationError.value = true
    population.value = []
  }
}

const onSearchInput = () => {
  clearTimeout(searchTimer)
  searchTimer = setTimeout(loadMethods, 350)
}

const clearFilters = () => {
  searchQuery.value = ''
  statusFilter.value = ''
  typeFilter.value = ''
  loadMethods()
}

const openCreate = () => {
  editingMethod.value = null
  showForm.value = true
}

const openEdit = method => {
  editingMethod.value = { ...method }
  showForm.value = true
}

const closeForm = () => {
  showForm.value = false
  editingMethod.value = null
}

const onSaved = method => {
  const wasEditing = Boolean(editingMethod.value)
  closeForm()
  loadMethods()
  const name = method?.name ? ` "${method.name}"` : ''
  toast.success(`Método${name} ${wasEditing ? 'actualizado' : 'creado'}`)
}

const toggleMethod = async method => {
  const nextActive = !method.is_active
  const ok = await confirm({
    title: nextActive ? 'Activar método' : 'Desactivar método',
    message: nextActive
      ? `¿Activar el método "${method.name}"? Volverá a estar disponible para registrar cobros.`
      : `¿Desactivar el método "${method.name}"? Dejará de aparecer al registrar cobros.`,
    confirmText: nextActive ? 'Activar' : 'Desactivar',
    variant: nextActive ? 'default' : 'danger'
  })
  if (!ok) return

  try {
    await put(`/api/payment-methods/${method.id}`, {
      name: method.name,
      is_active: nextActive
    })
    toast.success(`Método "${method.name}" ${nextActive ? 'activado' : 'desactivado'}`)
    await loadMethods()
  } catch (error) {
    handleError(error, `No pudimos ${nextActive ? 'activar' : 'desactivar'} el método de pago`)
  }
}

const deleteMethod = async method => {
  const ok = await confirm({
    title: 'Eliminar método de pago',
    message: `¿Eliminar el método "${method.name}"? Esta acción no se puede deshacer.`,
    confirmText: 'Eliminar',
    variant: 'danger'
  })
  if (!ok) return

  try {
    await remove(`/api/payment-methods/${method.id}`)
    toast.success(`Método "${method.name}" eliminado`)
    await loadMethods()
  } catch (error) {
    handleError(error, 'No pudimos eliminar el método de pago')
  }
}

const goBack = () => {
  router.back()
}

onMounted(() => {
  loadMethods()
})

onBeforeUnmount(() => {
  clearTimeout(searchTimer)
})
</script>
