<template>
  <AppLayout>
    <div class="quotations-page">
    <!-- Header -->
    <div class="page-header">
      <div class="flex justify-between items-center">
        <div>
          <h1 class="page-title">Presupuestos</h1>
          <p class="page-subtitle">Gestiona los presupuestos de tus pacientes</p>
        </div>
        <button
          @click="openCreateModal"
          class="btn btn-primary"
          :disabled="loading"
        >
          <PlusIcon class="w-5 h-5 mr-2" />
          Nuevo Presupuesto
        </button>
      </div>
    </div>

    <!-- Filtros -->
    <div class="filters-section">
      <div class="grid grid-cols-1 md:grid-cols-5 gap-4">
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
            <option value="sent">Enviado</option>
            <option value="approved">Aprobado</option>
            <option value="rejected">Rechazado</option>
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

        <div>
          <label class="block text-sm font-medium text-theme-primary mb-1">Fecha hasta</label>
          <input
            v-model="filters.date_to"
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

    <!-- Lista de presupuestos -->
    <div class="quotations-section">
      <div v-if="loading" class="flex justify-center py-8">
        <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-primary-600"></div>
      </div>

      <div v-else-if="!hasQuotations" class="empty-state">
        <DocumentTextIcon class="w-12 h-12 text-theme-secondary mx-auto mb-4" />
        <h3 class="text-lg font-medium text-theme-primary mb-2">No hay presupuestos</h3>
        <p class="text-theme-secondary mb-4">Comienza creando tu primer presupuesto</p>
        <button @click="openCreateModal" class="btn btn-primary">
          Crear Presupuesto
        </button>
      </div>

      <div v-else class="grid grid-cols-1 lg:grid-cols-2 xl:grid-cols-3 gap-6">
        <QuotationCard
          v-for="quotation in quotations"
          :key="quotation.id"
          :quotation="quotation"
          @view="viewQuotation"
          @edit="editQuotation"
          @approve="approveQuotation"
          @reject="rejectQuotation"
          @download="downloadPDF"
          @delete="deleteQuotation"
        />
      </div>
    </div>

    <!-- Paginación -->
    <div v-if="hasQuotations && totalPages > 1" class="pagination-section">
      <Pagination
        :current-page="currentPage"
        :total-pages="totalPages"
        @page-change="handlePageChange"
      />
    </div>

    <!-- Modales -->
    <QuotationModal
      v-if="showModal"
      :quotation="selectedQuotation"
      :is-edit="isEdit"
      @close="closeModal"
      @saved="handleQuotationSaved"
    />

    <QuotationDetail
      v-if="showDetailModal"
      :quotation="selectedQuotation"
      @close="closeDetailModal"
      @edit="editQuotation"
      @approve="approveQuotation"
      @reject="rejectQuotation"
      @download="downloadPDF"
    />

    <QuotationApprovalModal
      v-if="showApprovalModal"
      :quotation="selectedQuotation"
      @close="closeApprovalModal"
      @approved="handleQuotationApproved"
    />
    </div>
  </AppLayout>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { useQuotations } from '@/composables/useQuotations'
import { useAuth } from '@/composables/useAuth'
import { useEcho } from '@/composables/useEcho'
import { useToast } from '@/composables/useToast'
import AppLayout from '@/components/layout/AppLayout.vue'
import QuotationCard from './components/QuotationCard.vue'
import QuotationModal from './components/QuotationModal.vue'
import QuotationDetail from './components/QuotationDetail.vue'
import QuotationApprovalModal from './components/QuotationApprovalModal.vue'
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
  quotations,
  loading,
  error,
  hasQuotations,
  totalPages,
  currentPage,
  getQuotations,
  createQuotation,
  updateQuotation,
  deleteQuotation,
  approveQuotation,
  rejectQuotation,
  downloadPDF
} = useQuotations()

// Estado reactivo
const showModal = ref(false)
const showDetailModal = ref(false)
const showApprovalModal = ref(false)
const selectedQuotation = ref(null)
const isEdit = ref(false)
const filters = ref({
  patient_name: '',
  status: '',
  date_from: '',
  date_to: ''
})

// Métodos
const openCreateModal = () => {
  selectedQuotation.value = null
  isEdit.value = false
  showModal.value = true
}

const editQuotation = (quotation) => {
  selectedQuotation.value = quotation
  isEdit.value = true
  showModal.value = true
}

const viewQuotation = (quotation) => {
  selectedQuotation.value = quotation
  showDetailModal.value = true
}

// approveQuotation, rejectQuotation y downloadPDF ya están importados de useQuotations()

const closeModal = () => {
  showModal.value = false
  selectedQuotation.value = null
  isEdit.value = false
}

const closeDetailModal = () => {
  showDetailModal.value = false
  selectedQuotation.value = null
}

const closeApprovalModal = () => {
  showApprovalModal.value = false
  selectedQuotation.value = null
}

const handleQuotationSaved = (quotation) => {
  closeModal()
  loadQuotations()
}

const handleQuotationApproved = (quotation) => {
  closeApprovalModal()
  loadQuotations()
}

const applyFilters = () => {
  loadQuotations()
}

const clearFilters = () => {
  filters.value = {
    patient_name: '',
    status: '',
    date_from: '',
    date_to: ''
  }
  loadQuotations()
}

const handlePageChange = (page) => {
  loadQuotations({ page })
}

const loadQuotations = async (additionalFilters = {}) => {
  try {
    const allFilters = { ...filters.value, ...additionalFilters }
    await getQuotations(allFilters)
  } catch (err) {
    console.error('Error loading quotations:', err)
  }
}

// WebSocket subscriptions
let quotationsChannel = null

// Lifecycle
onMounted(() => {
  loadQuotations()

  // Suscribirse a canales WebSocket para actualizaciones en tiempo real
  try {
    quotationsChannel = channel('quotations')
    if (quotationsChannel) {
      quotationsChannel
        .listen('.quotation.created', async (e) => {
          console.log('Quotation created via WebSocket:', e.quotation)
          // Recargar lista para incluir el nuevo presupuesto
          await loadQuotations()
          toast.success('Nuevo presupuesto creado')
        })
        .listen('.quotation.updated', async (e) => {
          console.log('Quotation updated via WebSocket:', e.quotation)
          // Actualizar el presupuesto en la lista si existe
          const index = quotations.value.findIndex(q => q.id === e.quotation.id)
          if (index !== -1) {
            quotations.value[index] = e.quotation
          } else {
            // Si no existe, recargar todo
            await loadQuotations()
          }
          toast.success('Presupuesto actualizado')
        })
        .listen('.quotation.approved', async (e) => {
          console.log('Quotation approved via WebSocket:', e.quotation)
          // Actualizar el presupuesto en la lista
          const index = quotations.value.findIndex(q => q.id === e.quotation.id)
          if (index !== -1) {
            quotations.value[index] = e.quotation
          } else {
            await loadQuotations()
          }
          toast.success('Presupuesto aprobado', { duration: 6000 })
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
      echo.leave('quotations')
    } catch (e) {
      console.error('Error leaving quotations channel:', e)
    }
  }
})
</script>

<style scoped>
.quotations-page {
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

.quotations-section {
  @apply mb-6;
}

.empty-state {
  @apply text-center py-12;
}

.pagination-section {
  @apply flex justify-center;
}
</style>
