<template>
  <AppLayout>
    <div class="quotations-page bg-canvas">
      <!-- Header -->
      <PageHeader
        title="Presupuestos"
        subtitle="Gestiona los presupuestos de tus pacientes"
        class="mb-6"
      >
        <template #actions>
          <UiButton :disabled="loading" @click="openCreateModal">
            <template #icon-left>
              <PlusIcon class="w-5 h-5" />
            </template>
            Nuevo Presupuesto
          </UiButton>
        </template>
      </PageHeader>

      <!-- Filtros -->
      <UiCard variant="flat" padding="md" class="mb-6">
        <div class="grid grid-cols-1 md:grid-cols-5 gap-4">
          <div>
            <UiInput
              v-model="filters.patient_name"
              type="text"
              placeholder="Buscar por nombre..."
              label="Paciente"
            />
          </div>

          <div>
            <UiSelect
              v-model="filters.status"
              label="Estado"
              :options="statusOptions"
              placeholder="Todos los estados"
            />
          </div>

          <div>
            <UiInput v-model="filters.date_from" type="date" label="Fecha desde" />
          </div>

          <div>
            <UiInput v-model="filters.date_to" type="date" label="Fecha hasta" />
          </div>

          <div class="flex items-end gap-2">
            <UiButton variant="secondary" :disabled="loading" @click="applyFilters">
              <template #icon-left>
                <MagnifyingGlassIcon class="w-4 h-4" />
              </template>
              Buscar
            </UiButton>
            <UiButton variant="ghost" :disabled="loading" @click="clearFilters">Limpiar</UiButton>
          </div>
        </div>
      </UiCard>

      <!-- Lista de presupuestos -->
      <div class="quotations-section">
        <div v-if="loading" class="flex justify-center py-8">
          <UiLoadingSpinner size="md" variant="primary" text="Cargando presupuestos..." />
        </div>

        <div v-else-if="!hasQuotations" class="empty-state border border-hairline rounded-xl">
          <DocumentTextIcon class="w-12 h-12 text-theme-secondary mx-auto mb-4" />
          <h3 class="text-lg font-medium text-theme-primary mb-2">No hay presupuestos</h3>
          <p class="text-theme-secondary mb-4">Comienza creando tu primer presupuesto</p>
          <UiButton @click="openCreateModal">Crear Presupuesto</UiButton>
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
import { ref, onMounted, onUnmounted } from 'vue'
import { useQuotations } from '@/composables/useQuotations'
import { useAuth } from '@/composables/useAuth'
import { useEcho } from '@/composables/useEcho'
import { useToast } from '@/composables/useToast'
import AppLayout from '@/components/layout/AppLayout.vue'
import UiCard from '@/components/ui/Card.vue'
import UiInput from '@/components/ui/Input.vue'
import UiSelect from '@/components/ui/Select.vue'
import UiButton from '@/components/ui/Button.vue'
import UiLoadingSpinner from '@/components/ui/LoadingSpinner.vue'
import QuotationCard from './components/QuotationCard.vue'
import QuotationModal from './components/QuotationModal.vue'
import QuotationDetail from './components/QuotationDetail.vue'
import QuotationApprovalModal from './components/QuotationApprovalModal.vue'
import Pagination from '@/components/ui/Pagination.vue'
import { PlusIcon, MagnifyingGlassIcon, DocumentTextIcon } from '@heroicons/vue/24/outline'

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

const statusOptions = [
  { value: '', label: 'Todos los estados' },
  { value: 'draft', label: 'Borrador' },
  { value: 'sent', label: 'Enviado' },
  { value: 'approved', label: 'Aprobado' },
  { value: 'rejected', label: 'Rechazado' }
]

// Métodos
const openCreateModal = () => {
  selectedQuotation.value = null
  isEdit.value = false
  showModal.value = true
}

const editQuotation = quotation => {
  selectedQuotation.value = quotation
  isEdit.value = true
  showModal.value = true
}

const viewQuotation = quotation => {
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

const handleQuotationSaved = quotation => {
  closeModal()
  loadQuotations()
}

const handleQuotationApproved = quotation => {
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

const handlePageChange = page => {
  loadQuotations({ page })
}

const loadQuotations = async (additionalFilters = {}) => {
  try {
    const allFilters = { ...filters.value, ...additionalFilters }
    await getQuotations(allFilters)
  } catch (err) {}
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
        .listen('.quotation.created', async e => {
          // Recargar lista para incluir el nuevo presupuesto
          await loadQuotations()
          toast.success('Nuevo presupuesto creado')
        })
        .listen('.quotation.updated', async e => {
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
        .listen('.quotation.approved', async e => {
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
  } catch (error) {}
})

onUnmounted(() => {
  // Limpiar suscripciones WebSocket
  if (echo) {
    try {
      echo.leave('quotations')
    } catch (e) {}
  }
})
</script>
