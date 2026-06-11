<template>
  <AppLayout>
    <div class="treatment-plans-page">
      <!-- Header siempre a la vista -->
      <div class="page-header">
        <div class="flex justify-between items-center gap-4 flex-wrap">
          <div>
            <h1 class="page-title">Planes de Tratamiento</h1>
            <p class="page-subtitle">Gestiona los planes de tratamiento de tus pacientes</p>
          </div>
          <div class="flex items-center gap-2">
            <div class="counters">
              <span class="counter-pill" :class="{ active: !statusFilter }">
                Todos <strong>{{ pagination?.total ?? 0 }}</strong>
              </span>
              <span class="counter-pill pill-active">Activos {{ counters.active }}</span>
              <span v-if="counters.overdue > 0" class="counter-pill pill-overdue">
                Vencidos {{ counters.overdue }}
              </span>
            </div>
            <button
              @click="openCreateModal"
              class="btn btn-primary"
              :disabled="loading"
            >
              <PlusIcon class="w-5 h-5 mr-2" />
              Nuevo Plan
              <kbd class="kbd">N</kbd>
            </button>
          </div>
        </div>
      </div>

      <!-- Filtros siempre visibles (no escondidos) -->
      <div class="filters-section">
        <div class="filters-grid">
          <div class="filter-field">
            <label class="filter-label">Paciente</label>
            <input
              v-model="filters.patient_name"
              type="text"
              placeholder="Buscar por nombre o documento..."
              class="filter-input"
            />
          </div>

          <div class="filter-field">
            <label class="filter-label">Estado</label>
            <select v-model="filters.status" class="filter-input">
              <option value="">Todos</option>
              <option value="draft">Borrador</option>
              <option value="proposed">Propuesto</option>
              <option value="approved">Aprobado</option>
              <option value="in_progress">En Progreso</option>
              <option value="completed">Completado</option>
              <option value="cancelled">Cancelado</option>
            </select>
          </div>

          <div class="filter-field">
            <label class="filter-label">Desde</label>
            <input v-model="filters.date_from" type="date" class="filter-input" />
          </div>

          <div class="filter-field">
            <label class="filter-label">Hasta</label>
            <input v-model="filters.date_to" type="date" class="filter-input" />
          </div>

          <div class="filter-actions">
            <button @click="clearFilters" class="btn btn-outline" :disabled="loading">
              Limpiar
            </button>
          </div>
        </div>

        <!-- Filtros rápidos tipo pill (1 click) -->
        <div class="quick-filters">
          <div class="quick-filters-left">
            <button
              v-for="qf in quickFilters"
              :key="qf.value"
              @click="applyQuickFilter(qf)"
              :class="['quick-pill', { active: activeQuick === qf.value }]"
            >
              <component :is="qf.icon" v-if="qf.icon" class="w-3.5 h-3.5" />
              {{ qf.label }}
            </button>
          </div>

          <div class="view-toggle">
            <button
              @click="view = 'list'"
              :class="['view-btn', { active: view === 'list' }]"
              title="Vista lista"
              aria-label="Vista lista"
            >
              <Squares2X2Icon class="w-4 h-4" />
            </button>
            <button
              @click="view = 'kanban'"
              :class="['view-btn', { active: view === 'kanban' }]"
              title="Vista kanban"
              aria-label="Vista kanban"
            >
              <ViewColumnsIcon class="w-4 h-4" />
            </button>
          </div>
        </div>
      </div>

      <!-- Vista: lista -->
      <div v-if="view === 'list'" class="plans-section">
        <div v-if="loading" class="grid grid-cols-1 lg:grid-cols-2 xl:grid-cols-3 gap-4">
          <div v-for="n in 6" :key="n" class="skeleton-card"></div>
        </div>

        <div v-else-if="!hasPlans" class="empty-state">
          <DocumentTextIcon class="w-12 h-12 text-theme-secondary mx-auto mb-4" />
          <h3 class="text-lg font-medium text-theme-primary mb-2">
            {{ hasActiveFilters ? 'Sin resultados' : 'No hay planes de tratamiento' }}
          </h3>
          <p class="text-theme-secondary mb-4">
            {{ hasActiveFilters
              ? 'Prueba ajustando los filtros'
              : 'Comienza creando tu primer plan de tratamiento' }}
          </p>
          <button v-if="!hasActiveFilters" @click="openCreateModal" class="btn btn-primary">
            Crear Plan
          </button>
          <button v-else @click="clearFilters" class="btn btn-outline">
            Limpiar filtros
          </button>
        </div>

        <div v-else class="grid grid-cols-1 lg:grid-cols-2 xl:grid-cols-3 gap-4">
          <TreatmentPlanCard
            v-for="plan in plans"
            :key="plan.id"
            :plan="plan"
            @view="viewPlan"
            @edit="editPlan"
            @duplicate="duplicatePlan"
            @change-status="changeStatus"
            @delete="deletePlan"
          />
        </div>
      </div>

      <!-- Vista: kanban (drag entre estados) -->
      <div v-else class="kanban-section">
        <div class="kanban-grid">
          <KanbanColumn
            v-for="col in kanbanColumns"
            :key="col.value"
            :column="col"
            :plans="plansByStatus[col.value] || []"
            @view="viewPlan"
            @change-status="changeStatus"
            @drop-plan="onDropPlan"
          />
        </div>
      </div>

      <!-- Paginación -->
      <div v-if="hasPlans && totalPages > 1" class="pagination-section">
        <Pagination
          :current-page="currentPage"
          :total-pages="totalPages"
          @page-change="handlePageChange"
        />
      </div>

      <!-- Modales -->
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
        @duplicate="duplicatePlan"
      />
    </div>
  </AppLayout>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted, watch } from 'vue'
import { useTreatmentPlans } from '@/composables/useTreatmentPlans'
import { useAuth } from '@/composables/useAuth'
import { useEcho } from '@/composables/useEcho'
import { useToast } from '@/composables/useToast'
import AppLayout from '@/components/layout/AppLayout.vue'
import TreatmentPlanCard from './components/TreatmentPlanCard.vue'
import TreatmentPlanModal from './components/TreatmentPlanModal.vue'
import TreatmentPlanDetail from './components/TreatmentPlanDetail.vue'
import KanbanColumn from './components/KanbanColumn.vue'
import Pagination from '@/components/ui/Pagination.vue'
import {
  PlusIcon,
  DocumentTextIcon,
  ClockIcon,
  CheckCircleIcon,
  XCircleIcon,
  PencilIcon,
  Squares2X2Icon,
  ViewColumnsIcon,
} from '@heroicons/vue/24/outline'

const { user } = useAuth()
const { channel, echo } = useEcho()
const toast = useToast()
const {
  plans,
  loading,
  error,
  hasPlans,
  totalPages,
  currentPage,
  pagination,
  getPlans,
  createPlan,
  updatePlan,
  deletePlan,
  changeStatus,
  duplicatePlan,
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
  date_to: '',
})

const statusFilter = computed(() => filters.value.status)

const quickFilters = [
  { value: 'all', label: 'Todos', icon: null },
  { value: 'active', label: 'Activos', icon: ClockIcon },
  { value: 'mine', label: 'Míos', icon: PencilIcon },
  { value: 'overdue', label: 'Vencidos', icon: XCircleIcon },
  { value: 'completed', label: 'Completados', icon: CheckCircleIcon },
]
const activeQuick = ref('all')

const hasActiveFilters = computed(() =>
  Object.values(filters.value).some((v) => v !== '')
)

const counters = computed(() => {
  const all = plans.value
  return {
    active: all.filter((p) => ['draft', 'proposed', 'approved', 'in_progress'].includes(p.status)).length,
    overdue: all.filter((p) => p.is_overdue).length,
  }
})

const kanbanColumns = [
  { value: 'draft', label: 'Borrador', tone: 'gray' },
  { value: 'proposed', label: 'Propuesto', tone: 'blue' },
  { value: 'approved', label: 'Aprobado', tone: 'green' },
  { value: 'in_progress', label: 'En Progreso', tone: 'amber' },
  { value: 'completed', label: 'Completado', tone: 'emerald' },
  { value: 'cancelled', label: 'Cancelado', tone: 'red' },
]

const plansByStatus = computed(() => {
  const map = {}
  for (const col of kanbanColumns) map[col.value] = []
  for (const plan of plans.value) {
    if (map[plan.status]) map[plan.status].push(plan)
  }
  return map
})

const openCreateModal = () => {
  selectedPlan.value = null
  isEdit.value = false
  showModal.value = true
}

const editPlan = (plan) => {
  selectedPlan.value = plan
  isEdit.value = true
  showModal.value = true
}

const viewPlan = (plan) => {
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
  loadPlans()
}

const applyFilters = () => loadPlans()
const clearFilters = () => {
  filters.value = { patient_name: '', status: '', date_from: '', date_to: '' }
  activeQuick.value = 'all'
  loadPlans()
}

const applyQuickFilter = (qf) => {
  activeQuick.value = qf.value
  filters.value.status = qf.value === 'all' ? '' : qf.value
  loadPlans()
}

const handlePageChange = (page) => loadPlans({ page })

const onDropPlan = async ({ plan, newStatus }) => {
  try {
    await changeStatus(plan.id, newStatus)
    toast.success(`Plan movido a ${newStatus}`)
  } catch (e) {
    toast.error('No se pudo cambiar el estado')
    loadPlans()
  }
}

const loadPlans = async (additional = {}) => {
  try {
    const all = { ...filters.value, ...additional }
    Object.keys(all).forEach((k) => {
      if (all[k] === '' || all[k] === null) delete all[k]
    })
    await getPlans(all)
  } catch (err) {
  }
}

// Debounce reactivo en patient_name (sin click en Buscar)
let debounceTimer = null
watch(
  () => filters.value.patient_name,
  () => {
    clearTimeout(debounceTimer)
    debounceTimer = setTimeout(() => loadPlans(), 400)
  }
)
watch(
  () => [filters.value.status, filters.value.date_from, filters.value.date_to],
  () => loadPlans()
)

// Atajos de teclado
const onKeydown = (e) => {
  if (e.target.matches('input, select, textarea')) return
  if (e.key === 'n' || e.key === 'N') {
    e.preventDefault()
    openCreateModal()
  }
  if (e.key === 'Escape') {
    if (showModal.value) closeModal()
    else if (showDetailModal.value) closeDetailModal()
  }
}

let treatmentPlansChannel = null

onMounted(() => {
  loadPlans()
  document.addEventListener('keydown', onKeydown)

  try {
    treatmentPlansChannel = channel('treatment-plans')
    if (treatmentPlansChannel) {
      treatmentPlansChannel
        .listen('.treatment-plan.created', async () => {
          await loadPlans()
          toast.info('Nuevo plan creado')
        })
        .listen('.treatment-plan.updated', async (e) => {
          const idx = plans.value.findIndex((p) => p.id === e.treatment_plan.id)
          if (idx !== -1) plans.value[idx] = e.treatment_plan
          else await loadPlans()
        })
        .listen('.treatment-plan.deleted', async (e) => {
          plans.value = plans.value.filter((p) => p.id !== e.treatment_plan_id)
          if (selectedPlan.value?.id === e.treatment_plan_id) {
            selectedPlan.value = null
            showDetailModal.value = false
          }
        })
    }
  } catch (err) {
  }
})

onUnmounted(() => {
  document.removeEventListener('keydown', onKeydown)
  if (echo) {
    try {
      echo.leave('treatment-plans')
    } catch (e) {
    }
  }
  clearTimeout(debounceTimer)
})
</script>

<style scoped>
.treatment-plans-page {
  @apply p-6 space-y-5;
}

.page-title {
  @apply text-2xl font-bold;
  color: var(--color-text-primary);
}

.page-subtitle {
  @apply text-sm;
  color: var(--color-text-secondary);
}

.counters {
  @apply hidden md:flex items-center gap-2 text-xs;
}
.counter-pill {
  @apply inline-flex items-center gap-1 px-2.5 py-1 rounded-full bg-theme-surface text-theme-secondary;
}
.counter-pill strong {
  @apply text-theme-primary font-semibold;
}
.counter-pill.pill-active {
  background-color: rgb(219 234 254);
  color: rgb(29 78 216);
}
.counter-pill.pill-overdue {
  background-color: rgb(254 226 226);
  color: rgb(185 28 28);
}

.filters-section {
  @apply p-4 rounded-lg space-y-3;
  background-color: var(--color-surface);
}

.filters-grid {
  @apply grid grid-cols-1 md:grid-cols-5 gap-3;
}

.filter-field {
  @apply space-y-1;
}

.filter-label {
  @apply block text-xs font-medium;
  color: var(--color-text-secondary);
}

.filter-input {
  @apply w-full px-3 py-2 text-sm border border-theme rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent;
  background-color: var(--color-surface-elevated);
  color: var(--color-text-primary);
}

.filter-actions {
  @apply flex items-end;
}

.quick-filters {
  @apply flex flex-wrap items-center justify-between gap-2 pt-2 border-t border-theme;
}

.quick-filters-left {
  @apply flex flex-wrap gap-2;
}

.view-toggle {
  @apply flex items-center gap-1 p-0.5 rounded-md;
  background-color: var(--color-surface-elevated);
}

.view-btn {
  @apply p-1.5 rounded text-theme-secondary hover:text-theme-primary transition-colors;
}

.view-btn.active {
  @apply bg-primary-600 text-white;
}

.quick-pill {
  @apply inline-flex items-center gap-1.5 px-3 py-1 text-xs font-medium rounded-full border border-theme bg-theme-surface-elevated text-theme-secondary hover:bg-theme-surface transition-colors;
}
.quick-pill.active {
  @apply bg-primary-600 text-white border-primary-600;
}

.skeleton-card {
  @apply h-48 rounded-lg;
  background-color: var(--color-surface);
  animation: pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
}

@keyframes pulse {
  0%, 100% { opacity: 1; }
  50% { opacity: 0.5; }
}

.empty-state {
  @apply text-center py-12;
}

.pagination-section {
  @apply flex justify-center;
}

.kanban-section {
  @apply overflow-x-auto pb-2;
}

.kanban-grid {
  @apply grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-3 min-w-[1100px];
}

.btn {
  @apply inline-flex items-center justify-center px-3 py-2 border border-transparent text-sm font-medium rounded-md transition-colors disabled:opacity-50 disabled:cursor-not-allowed;
}
.btn-primary {
  @apply bg-primary-600 text-white hover:bg-primary-700;
}
.btn-outline {
  @apply border-theme text-theme-primary bg-theme-surface-elevated hover:bg-theme-surface;
}

.kbd {
  @apply ml-2 px-1.5 py-0.5 text-[10px] font-mono rounded;
  background-color: rgba(255, 255, 255, 0.2);
}
</style>
