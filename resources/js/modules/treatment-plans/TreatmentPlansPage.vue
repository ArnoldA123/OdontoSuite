<template>
  <AppLayout>
    <!--
      Loading: the skeleton mirrors the block order (KPI strip -> rows) so the
      surface does not jump when the payload lands. While a reload runs over an
      already-populated list the content stays mounted instead of flashing.
    -->
    <section
      v-if="loading && !hasPlans"
      aria-busy="true"
      aria-live="polite"
      aria-label="Cargando planes de tratamiento"
      class="space-y-6"
    >
      <div class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-4">
        <UiSkeleton
          v-for="i in 4"
          :key="`kpi-skel-${i}`"
          variant="card"
          animation="wave"
          :aria-label="`Cargando indicador ${i}`"
        />
      </div>
      <UiSkeleton
        v-for="i in 3"
        :key="`row-skel-${i}`"
        variant="list"
        animation="wave"
        aria-label="Cargando plan de tratamiento"
      />
    </section>

    <div v-else class="space-y-6">
      <!--
        Hero band: the dashboard surface recipe (canvas + hairline + panel
        radius + elevation rung). The topbar keeps owning the single h1, so the
        band stays typographic with an h2.
      -->
      <header
        data-treatment-plans-hero
        class="relative overflow-hidden p-6 md:p-8"
        style="
          background: var(--color-canvas);
          border: 1px solid var(--color-hairline);
          border-radius: var(--radius-panel);
          box-shadow: var(--elevation-2);
        "
      >
        <div class="relative flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
          <div class="min-w-0">
            <p
              class="text-xs font-medium uppercase tracking-wide text-theme-secondary"
              data-hero-eyebrow
            >
              Operación clínica
            </p>
            <h2 class="mt-2 text-4xl font-semibold text-label" data-hero-title>
              Planes de Tratamiento
            </h2>
            <p class="mt-2 text-sm text-theme-secondary" data-hero-caption>
              Gestiona los planes de tratamiento de tus pacientes
            </p>
          </div>

          <div class="flex flex-wrap items-center gap-3 md:flex-shrink-0 md:flex-nowrap">
            <UiButton
              variant="secondary"
              size="md"
              data-refresh
              :disabled="loading"
              @click="loadAll"
            >
              <template #icon-left>
                <ArrowPathIcon
                  class="w-4 h-4"
                  :class="{ 'animate-spin motion-reduce:animate-none': loading }"
                  aria-hidden="true"
                />
              </template>
              Actualizar
            </UiButton>
            <UiButton variant="primary" size="md" data-create-plan @click="openCreateModal">
              <template #icon-left>
                <PlusIcon class="w-4 h-4" aria-hidden="true" />
              </template>
              Nuevo Plan
              <kbd
                class="ml-2 rounded px-1.5 py-0.5 font-mono text-[10px]"
                style="background: rgba(255, 255, 255, 0.2)"
              >
                N
              </kbd>
            </UiButton>
          </div>
        </div>
      </header>

      <!-- Error: above every data surface, never rendered as empty results. -->
      <div
        v-if="error"
        data-state="error"
        role="alert"
        class="flex flex-wrap items-center justify-between gap-4 rounded-[var(--radius-panel)] bg-systemRed-50 p-5"
        style="border: 1px solid var(--color-hairline)"
      >
        <div class="flex min-w-0 items-center gap-3">
          <ExclamationTriangleIcon
            class="h-6 w-6 flex-shrink-0 text-systemRed-600"
            aria-hidden="true"
          />
          <div class="min-w-0">
            <p class="text-sm font-medium text-theme-primary">No pudimos cargar los planes</p>
            <p class="text-sm text-theme-secondary">{{ error }}</p>
          </div>
        </div>
        <UiButton variant="primary" size="sm" data-retry @click="loadPlans()">Reintentar</UiButton>
      </div>

      <!--
        KPI strip: the four cards summarize the WHOLE table through the summary
        endpoint, never the visible page. Static reference surface: no card
        carries a click affordance.
      -->
      <div class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-4">
        <UiCard
          variant="glass"
          padding="sm"
          data-kpi-card="active"
          class="h-full"
          :style="{ boxShadow: 'var(--elevation-2)', borderColor: 'var(--color-hairline)' }"
        >
          <p class="text-xs font-medium uppercase text-theme-secondary">Planes activos</p>
          <p
            class="mt-2 text-2xl font-bold leading-none tabular-nums text-systemGreen-600"
            data-kpi-value="active"
          >
            {{ summary?.active ?? '—' }}
          </p>
          <p class="mt-1 text-xs text-theme-secondary" data-kpi-caption="active">
            Borrador, propuesta, aprobado o en curso
          </p>
        </UiCard>

        <UiCard
          variant="glass"
          padding="sm"
          data-kpi-card="pending-approval"
          class="h-full"
          :style="{ boxShadow: 'var(--elevation-2)', borderColor: 'var(--color-hairline)' }"
        >
          <p class="text-xs font-medium uppercase text-theme-secondary">Por aprobar</p>
          <p
            class="mt-2 text-2xl font-bold leading-none tabular-nums text-label"
            data-kpi-value="pending-approval"
          >
            {{ summary?.pending_approval ?? '—' }}
          </p>
          <p class="mt-1 text-xs text-theme-secondary" data-kpi-caption="pending-approval">
            Esperando la decisión del paciente
          </p>
        </UiCard>

        <UiCard
          variant="glass"
          padding="sm"
          data-kpi-card="overdue"
          class="h-full"
          :style="{ boxShadow: 'var(--elevation-2)', borderColor: 'var(--color-hairline)' }"
        >
          <p class="text-xs font-medium uppercase text-theme-secondary">Vencidos</p>
          <p
            class="mt-2 text-2xl font-bold leading-none tabular-nums"
            :class="overdueTone"
            data-kpi-value="overdue"
          >
            {{ summary?.overdue ?? '—' }}
          </p>
          <p class="mt-1 text-xs text-theme-secondary" data-kpi-caption="overdue">
            Pasaron su fecha de cierre y siguen abiertos
          </p>
        </UiCard>

        <UiCard
          variant="glass"
          padding="sm"
          data-kpi-card="pending-cost"
          class="h-full"
          :style="{ boxShadow: 'var(--elevation-2)', borderColor: 'var(--color-hairline)' }"
        >
          <p class="text-xs font-medium uppercase text-theme-secondary">Valor pendiente</p>
          <p
            class="mt-2 text-2xl font-bold leading-none tabular-nums text-label"
            data-kpi-value="pending-cost"
          >
            {{ summary ? formatCurrency(summary.pending_cost) : '—' }}
          </p>
          <p class="mt-1 text-xs text-theme-secondary" data-kpi-caption="pending-cost">
            Costo de los planes abiertos
          </p>
        </UiCard>
      </div>

      <!-- Filters: one glass card; presets and the view toggle live inside it. -->
      <UiCard variant="glass" data-filters>
        <div class="grid grid-cols-1 gap-4 lg:grid-cols-12 lg:items-end">
          <div class="lg:col-span-4">
            <label
              for="treatment-plan-patient"
              class="mb-2 block text-sm font-medium text-theme-primary"
            >
              Paciente
            </label>
            <UiInput
              id="treatment-plan-patient"
              v-model="filters.patient_name"
              placeholder="Nombre o documento"
              data-filter-patient
            />
          </div>

          <div class="lg:col-span-3">
            <span class="mb-2 block text-sm font-medium text-theme-primary">Estado</span>
            <UiSelect
              v-model="filters.status"
              :options="statusOptions"
              placeholder="Todos los estados"
              data-filter-status
              @change="onManualStatusChange"
            />
          </div>

          <div class="lg:col-span-2">
            <span class="mb-2 block text-sm font-medium text-theme-primary">Desde</span>
            <UiInput v-model="filters.date_from" type="date" data-filter-from />
          </div>

          <div class="lg:col-span-2">
            <span class="mb-2 block text-sm font-medium text-theme-primary">Hasta</span>
            <UiInput v-model="filters.date_to" type="date" data-filter-to />
          </div>

          <div class="lg:col-span-1">
            <UiButton variant="ghost" size="md" full-width data-clear-filters @click="clearFilters">
              Limpiar
            </UiButton>
          </div>
        </div>

        <div
          class="mt-4 flex flex-wrap items-center justify-between gap-3 pt-4"
          style="border-top: 1px solid var(--color-hairline)"
        >
          <div class="flex flex-wrap items-center gap-2" role="group" aria-label="Filtros rápidos">
            <UiButton
              v-for="qf in quickFilters"
              :key="qf.value"
              :variant="activeQuick === qf.value ? 'primary' : 'ghost'"
              size="sm"
              :aria-pressed="activeQuick === qf.value"
              :data-quick-filter="qf.value"
              @click="applyQuickFilter(qf)"
            >
              {{ qf.label }}
            </UiButton>
          </div>

          <div
            class="inline-flex gap-1 rounded-[var(--radius-control)] p-1"
            role="group"
            aria-label="Vista de los planes"
            data-view-toggle
            style="border: 1px solid var(--color-hairline)"
          >
            <UiButton
              :variant="view === 'list' ? 'primary' : 'ghost'"
              size="sm"
              data-view="list"
              :aria-pressed="view === 'list'"
              @click="view = 'list'"
            >
              <template #icon-left>
                <Squares2X2Icon class="w-4 h-4" aria-hidden="true" />
              </template>
              Lista
            </UiButton>
            <UiButton
              :variant="view === 'kanban' ? 'primary' : 'ghost'"
              size="sm"
              data-view="kanban"
              :aria-pressed="view === 'kanban'"
              @click="view = 'kanban'"
            >
              <template #icon-left>
                <ViewColumnsIcon class="w-4 h-4" aria-hidden="true" />
              </template>
              Kanban
            </UiButton>
          </div>
        </div>
      </UiCard>

      <!-- List surface -->
      <section v-if="view === 'list'" aria-label="Lista de planes de tratamiento" class="space-y-4">
        <UiCard
          v-if="!loading && !hasPlans && !error"
          data-state="empty"
          variant="glass"
          padding="lg"
        >
          <UiEmptyState
            :title="hasActiveFilters ? 'Sin resultados' : 'No hay planes de tratamiento'"
            :description="
              hasActiveFilters
                ? 'Prueba ajustando los filtros para encontrar el plan que buscas.'
                : 'Comienza creando tu primer plan de tratamiento.'
            "
          >
            <template #action>
              <UiButton
                v-if="hasActiveFilters"
                variant="secondary"
                size="md"
                data-empty-clear
                @click="clearFilters"
              >
                Limpiar filtros
              </UiButton>
              <UiButton
                v-else
                variant="primary"
                size="md"
                data-empty-create
                @click="openCreateModal"
              >
                Crear Plan
              </UiButton>
            </template>
          </UiEmptyState>
        </UiCard>

        <div
          v-else-if="hasPlans"
          class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3"
          data-plan-list
        >
          <TreatmentPlanCard
            v-for="plan in plans"
            :key="plan.id"
            :plan="plan"
            @view="viewPlan"
            @edit="editPlan"
            @duplicate="onDuplicate"
            @change-status="onCardStatusChange"
            @delete="onDelete"
          />
        </div>
      </section>

      <!-- Kanban surface -->
      <section v-else aria-label="Tablero kanban de planes" class="overflow-x-auto pb-2">
        <div
          class="grid min-w-[1100px] grid-cols-1 gap-3 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-6"
        >
          <KanbanColumn
            v-for="col in kanbanColumns"
            :key="col.value"
            :column="col"
            :plans="plansByStatus[col.value] || []"
            @view="viewPlan"
            @drop-plan="onDropPlan"
          />
        </div>
      </section>

      <div v-if="hasPlans && totalPages > 1" class="flex justify-center">
        <Pagination
          :current-page="currentPage"
          :total-pages="totalPages"
          :total="pagination?.total ?? 0"
          :per-page="pagination?.per_page ?? 15"
          @page-change="handlePageChange"
        />
      </div>
    </div>

    <TreatmentPlanModal
      v-if="showModal"
      :plan="selectedPlan"
      :is-edit="isEdit"
      @close="closeModal"
      @saved="handlePlanSaved"
    />

    <TreatmentPlanDetail
      v-if="showDetailModal"
      :plan="selectedPlan"
      @close="closeDetailModal"
      @edit="editPlan"
      @duplicate="onDuplicate"
    />
  </AppLayout>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted, watch } from 'vue'
import { useTreatmentPlans } from '@/composables/useTreatmentPlans'
import { useEcho } from '@/composables/useEcho'
import { useToast } from '@/composables/useToast'
import { useAuth } from '@/composables/useAuth'
import { formatCurrency } from '@/composables/useFormatters'
import { pickValidationMessage } from '@/composables/validationErrors'
import AppLayout from '@/components/layout/AppLayout.vue'
import TreatmentPlanCard from './components/TreatmentPlanCard.vue'
import TreatmentPlanModal from './components/TreatmentPlanModal.vue'
import TreatmentPlanDetail from './components/TreatmentPlanDetail.vue'
import KanbanColumn from './components/KanbanColumn.vue'
import Pagination from '@/components/ui/Pagination.vue'
import UiEmptyState from '@/components/ui/EmptyState.vue'
import {
  PlusIcon,
  ArrowPathIcon,
  ExclamationTriangleIcon,
  Squares2X2Icon,
  ViewColumnsIcon
} from '@heroicons/vue/24/outline'

const OPEN_STATUSES = ['draft', 'proposed', 'approved', 'in_progress']

const { privateChannel, echo } = useEcho()
const toast = useToast()
const { user } = useAuth()
const {
  plans,
  loading,
  error,
  hasPlans,
  totalPages,
  currentPage,
  pagination,
  summary,
  getPlans,
  getSummary,
  changeStatus,
  duplicatePlan,
  deletePlan
} = useTreatmentPlans()

const showModal = ref(false)
const showDetailModal = ref(false)
const selectedPlan = ref(null)
const isEdit = ref(false)
const view = ref('list')

const filters = ref({
  patient_name: '',
  status: '',
  date_from: '',
  date_to: ''
})

/**
 * Presets are shortcuts over the same API filters the manual fields use.
 * `params` is what makes each pill truthful: the list endpoint understands
 * `statuses` (list), `overdue` and `created_by`, not made-up status names.
 */
const quickFilters = [
  { value: 'all', label: 'Todos', params: {} },
  { value: 'active', label: 'Activos', params: { statuses: OPEN_STATUSES.join(',') } },
  {
    value: 'mine',
    label: 'Míos',
    params: { created_by: user.value?.id ?? '' }
  },
  { value: 'overdue', label: 'Vencidos', params: { overdue: '1' } },
  { value: 'completed', label: 'Completados', params: { status: 'completed' } }
]

const activeQuick = ref('all')
const presetParams = ref({})

const statusOptions = [
  { value: '', label: 'Todos los estados' },
  { value: 'draft', label: 'Borrador' },
  { value: 'proposed', label: 'Propuesto' },
  { value: 'approved', label: 'Aprobado' },
  { value: 'in_progress', label: 'En Progreso' },
  { value: 'completed', label: 'Completado' },
  { value: 'cancelled', label: 'Cancelado' }
]

const kanbanColumns = [
  { value: 'draft', label: 'Borrador', tone: 'neutral' },
  { value: 'proposed', label: 'Propuesto', tone: 'steel' },
  { value: 'approved', label: 'Aprobado', tone: 'green' },
  { value: 'in_progress', label: 'En Progreso', tone: 'yellow' },
  { value: 'completed', label: 'Completado', tone: 'accent' },
  { value: 'cancelled', label: 'Cancelado', tone: 'red' }
]

const overdueCount = computed(() => summary.value?.overdue ?? 0)
const overdueTone = computed(() => (overdueCount.value > 0 ? 'text-systemRed-600' : 'text-label'))

const hasActiveFilters = computed(
  () =>
    activeQuick.value !== 'all' ||
    Object.values(filters.value).some(
      value => value !== '' && value !== null && value !== undefined
    )
)

const plansByStatus = computed(() => {
  const map = {}
  for (const col of kanbanColumns) map[col.value] = []
  for (const plan of plans.value) {
    if (map[plan.status]) map[plan.status].push(plan)
  }
  return map
})

/**
 * One request shape for every path: manual fields first, then the preset that
 * is currently pressed. Empty values never reach the query string.
 */
const buildParams = (additional = {}) => {
  const params = { ...filters.value }

  if (activeQuick.value !== 'all') {
    delete params.status
    Object.assign(params, presetParams.value)
  }

  Object.assign(params, additional)

  Object.keys(params).forEach(key => {
    if (params[key] === '' || params[key] === null || params[key] === undefined) delete params[key]
  })

  return params
}

const loadPlans = async (additional = {}) => {
  try {
    await getPlans(buildParams(additional))
  } catch (err) {
    // La lista conserva los planes ya cargados si falla la recarga
  }
}

const loadSummary = async () => {
  try {
    await getSummary()
  } catch (err) {
    // La tira de KPIs cae a "—" sin tumbar la lista
  }
}

const loadAll = () => Promise.allSettled([loadPlans(), loadSummary()])

const openCreateModal = () => {
  selectedPlan.value = null
  isEdit.value = false
  showModal.value = true
}

const editPlan = plan => {
  selectedPlan.value = plan
  isEdit.value = true
  showModal.value = true
}

const viewPlan = plan => {
  selectedPlan.value = plan
  showDetailModal.value = true
}

const closeModal = () => {
  showModal.value = false
  selectedPlan.value = null
  isEdit.value = false
}

const closeDetailModal = () => {
  showDetailModal.value = false
  selectedPlan.value = null
}

const handlePlanSaved = () => {
  closeModal()
  loadAll()
}

const clearFilters = () => {
  const hadPreset = activeQuick.value !== 'all'
  const hadValues = Object.values(filters.value).some(value => value !== '')

  filters.value = { patient_name: '', status: '', date_from: '', date_to: '' }
  activeQuick.value = 'all'
  presetParams.value = {}

  // The watchers reload when a field actually changed; with no field to reset
  // but a pressed preset, the list still needs one explicit reload.
  if (hadPreset && !hadValues) loadPlans()
}

const applyQuickFilter = qf => {
  activeQuick.value = qf.value
  presetParams.value = { ...(qf.params || {}) }
  filters.value.status = ''
  loadPlans()
}

/**
 * Picking a manual status releases the active preset: presets and the select
 * are two ways to express the same filter, so the last action wins.
 */
const onManualStatusChange = () => {
  if (activeQuick.value === 'all') {
    loadPlans()

    return
  }

  activeQuick.value = 'all'
  presetParams.value = {}
  loadPlans()
}

const handlePageChange = page => loadPlans({ page })

const onCardStatusChange = async (planId, status) => {
  try {
    await changeStatus(planId, status)
    toast.success('Estado actualizado')
    loadSummary()
  } catch (err) {
    toast.error(pickValidationMessage(err.response?.data, 'No se pudo cambiar el estado'))
    loadPlans()
  }
}

const onDropPlan = async ({ plan, newStatus }) => {
  await onCardStatusChange(plan.id, newStatus)
}

/**
 * The card and the detail emit the whole plan; the composable needs its id.
 * Passing the object straight through produced `/treatment-plans/[object
 * Object]/duplicate` (defect found while rewiring this surface).
 */
const onDuplicate = async plan => {
  try {
    await duplicatePlan(plan.id)
    toast.success('Plan duplicado')
    loadSummary()
  } catch (err) {
    toast.error('No se pudo duplicar el plan')
  }
}

const onDelete = async planId => {
  try {
    await deletePlan(planId)
    if (selectedPlan.value?.id === planId) closeDetailModal()
    toast.success('Plan eliminado')
    loadSummary()
  } catch (err) {
    toast.error('No se pudo eliminar el plan')
  }
}

// Debounce on the patient search so typing does not fire a request per key.
let debounceTimer = null
watch(
  () => filters.value.patient_name,
  () => {
    clearTimeout(debounceTimer)
    debounceTimer = setTimeout(() => loadPlans(), 400)
  }
)
watch(
  () => [filters.value.date_from, filters.value.date_to],
  () => loadPlans()
)

// Keyboard: N opens the create modal. Escape belongs to each overlay: the plan
// modal guards its dirty form through UiModal's before-close and the detail
// dialog owns its own dismissal, so the page must not close them behind their
// back (that bypassed the unsaved-changes prompt). While an overlay is open the
// shortcut stays inert: re-opening would re-initialize the form and drop the
// edits the guard is protecting.
const onKeydown = e => {
  if (e.target.matches('input, select, textarea')) return
  if (showModal.value || showDetailModal.value) return
  if (e.key === 'n' || e.key === 'N') {
    e.preventDefault()
    openCreateModal()
  }
}

let treatmentPlansChannel = null

onMounted(() => {
  loadAll()
  document.addEventListener('keydown', onKeydown)

  try {
    treatmentPlansChannel = privateChannel('treatment-plans')
    if (treatmentPlansChannel) {
      treatmentPlansChannel
        .listen('.treatment-plan.created', async () => {
          await loadAll()
          toast.info('Nuevo plan creado')
        })
        .listen('.treatment-plan.updated', async e => {
          const idx = plans.value.findIndex(p => p.id === e.treatment_plan.id)
          if (idx !== -1) plans.value[idx] = e.treatment_plan
          else await loadPlans()
          loadSummary()
        })
        .listen('.treatment-plan.deleted', async e => {
          plans.value = plans.value.filter(p => p.id !== e.treatment_plan_id)
          if (selectedPlan.value?.id === e.treatment_plan_id) {
            selectedPlan.value = null
            showDetailModal.value = false
          }
          loadSummary()
        })
    }
  } catch (err) {
    // Sin WebSocket el kanban sigue operativo vía fetch inicial
  }
})

onUnmounted(() => {
  document.removeEventListener('keydown', onKeydown)
  if (echo) {
    try {
      echo.leave('private-treatment-plans')
    } catch (e) {
      // El canal ya estaba cerrado, nada que limpiar
    }
  }
  clearTimeout(debounceTimer)
})
</script>
