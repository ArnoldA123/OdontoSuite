<template>
  <AppLayout>
    <div class="treatment-plans-page">
    <!-- Header -->
    <div class="page-header">
      <div class="flex justify-between items-center">
        <div>
          <h1 class="page-title">Planes de Tratamiento</h1>
          <p class="page-subtitle">Gestiona los planes de tratamiento de tus pacientes</p>
        </div>
        <button
          @click="openCreateModal"
          class="btn btn-primary"
          :disabled="loading"
        >
          <PlusIcon class="w-5 h-5 mr-2" />
          Nuevo Plan
        </button>
      </div>
    </div>

    <!-- Filtros -->
    <div class="filters-section">
      <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <div>
          <label class="block text-sm font-medium text-theme-primary mb-1">Paciente</label>
          <input
            v-model="filters.patient_name"
            type="text"
            placeholder="Buscar por nombre..."
            class="w-full px-3 py-2 border border-theme rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent bg-theme-surface-elevated text-theme-primary"
          />
        </div>

        <div>
          <label class="block text-sm font-medium text-theme-primary mb-1">Estado</label>
          <select
            v-model="filters.status"
            class="w-full px-3 py-2 border border-theme rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent bg-theme-surface-elevated text-theme-primary"
          >
            <option value="">Todos los estados</option>
            <option value="draft">Borrador</option>
            <option value="proposed">Propuesto</option>
            <option value="approved">Aprobado</option>
            <option value="in_progress">En Progreso</option>
            <option value="completed">Completado</option>
            <option value="cancelled">Cancelado</option>
          </select>
        </div>

        <div>
          <label class="block text-sm font-medium text-theme-primary mb-1">Fecha desde</label>
          <input
            v-model="filters.date_from"
            type="date"
            class="w-full px-3 py-2 border border-theme rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent bg-theme-surface-elevated text-theme-primary"
          />
        </div>

        <div class="flex items-end">
          <button
            @click="applyFilters"
            class="btn btn-secondary mr-2"
            :disabled="loading"
          >
            <MagnifyingGlassIcon class="w-4 h-4 mr-1" />
            Buscar
          </button>
          <button
            @click="clearFilters"
            class="btn btn-outline"
            :disabled="loading"
          >
            Limpiar
          </button>
        </div>
      </div>
    </div>

    <!-- Lista de planes -->
    <div class="plans-section">
      <div v-if="loading" class="flex justify-center py-8">
        <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-primary-600"></div>
      </div>

      <div v-else-if="!hasPlans" class="empty-state">
        <DocumentTextIcon class="w-12 h-12 text-theme-secondary mx-auto mb-4" />
        <h3 class="text-lg font-medium text-theme-primary mb-2">No hay planes de tratamiento</h3>
        <p class="text-theme-secondary mb-4">Comienza creando tu primer plan de tratamiento</p>
        <button @click="openCreateModal" class="btn btn-primary">
          Crear Plan
        </button>
      </div>

      <div v-else class="grid grid-cols-1 lg:grid-cols-2 xl:grid-cols-3 gap-6">
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
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { useTreatmentPlans } from '@/composables/useTreatmentPlans'
import { useAuth } from '@/composables/useAuth'
import { useEcho } from '@/composables/useEcho'
import { useToast } from '@/composables/useToast'
import AppLayout from '@/components/layout/AppLayout.vue'
import TreatmentPlanCard from './components/TreatmentPlanCard.vue'
import TreatmentPlanModal from './components/TreatmentPlanModal.vue'
import TreatmentPlanDetail from './components/TreatmentPlanDetail.vue'
import Pagination from '@/components/ui/Pagination.vue'
import {
  PlusIcon,
  MagnifyingGlassIcon,
  DocumentTextIcon
} from '@heroicons/vue/24/outline'

// Composables
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
  getPlans,
  createPlan,
  updatePlan,
  deletePlan,
  changeStatus,
  duplicatePlan
} = useTreatmentPlans()

// Estado reactivo
const showModal = ref(false)
const showDetailModal = ref(false)
const selectedPlan = ref(null)
const isEdit = ref(false)
const filters = ref({
  patient_name: '',
  status: '',
  date_from: '',
  date_to: ''
})

// Métodos
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

const handlePlanSaved = (plan) => {
  console.log('Plan guardado, recargando lista...')
  closeModal()
  loadPlans()
}

const applyFilters = () => {
  loadPlans()
}

const clearFilters = () => {
  filters.value = {
    patient_name: '',
    status: '',
    date_from: '',
    date_to: ''
  }
  loadPlans()
}

const handlePageChange = (page) => {
  loadPlans({ page })
}

const loadPlans = async (additionalFilters = {}) => {
  try {
    const allFilters = { ...filters.value, ...additionalFilters }
    await getPlans(allFilters)
  } catch (err) {
    console.error('Error loading plans:', err)
  }
}

// WebSocket subscriptions
let treatmentPlansChannel = null

// Lifecycle
onMounted(() => {
  loadPlans()

  // Suscribirse a canales WebSocket para actualizaciones en tiempo real
  try {
    treatmentPlansChannel = channel('treatment-plans')
    if (treatmentPlansChannel) {
      treatmentPlansChannel
        .listen('.treatment-plan.created', async (e) => {
          console.log('Treatment plan created via WebSocket:', e.treatment_plan)
          // Recargar lista para incluir el nuevo plan
          await loadPlans()
          toast.success('Nuevo plan de tratamiento creado')
        })
        .listen('.treatment-plan.updated', async (e) => {
          console.log('Treatment plan updated via WebSocket:', e.treatment_plan)
          // Actualizar el plan en la lista si existe
          const index = plans.value.findIndex(plan => plan.id === e.treatment_plan.id)
          if (index !== -1) {
            plans.value[index] = e.treatment_plan
          } else {
            // Si no existe, recargar todo
            await loadPlans()
          }
          toast.success('Plan de tratamiento actualizado')
        })
        .listen('.treatment-plan.deleted', async (e) => {
          console.log('Treatment plan deleted via WebSocket:', e.treatment_plan_id)
          // Remover el plan de la lista
          plans.value = plans.value.filter(plan => plan.id !== e.treatment_plan_id)
          if (selectedPlan.value?.id === e.treatment_plan_id) {
            selectedPlan.value = null
            showDetailModal.value = false
          }
          toast.success('Plan de tratamiento eliminado')
        })
    }
  } catch (error) {
    console.error('Error setting up WebSocket subscriptions:', error)
  }
})

onUnmounted(() => {
  // Limpiar suscripciones WebSocket
  if (echo) {
    try {
      echo.leave('treatment-plans')
    } catch (e) {
      console.error('Error leaving treatment-plans channel:', e)
    }
  }
})
</script>

<style scoped>
.treatment-plans-page {
  @apply p-6;
}

.page-header {
  @apply mb-6;
}

.page-title {
  @apply text-2xl font-bold;
  color: var(--color-text-primary);
}

.page-subtitle {
  color: var(--color-text-secondary);
}

.filters-section {
  @apply mb-6 p-4 rounded-lg;
  background-color: var(--color-surface);
}

.plans-section {
  @apply mb-6;
}

.empty-state {
  @apply text-center py-12;
}

.pagination-section {
  @apply flex justify-center;
}
</style>
