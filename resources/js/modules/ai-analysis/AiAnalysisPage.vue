<template>
  <AppLayout>
    <!-- Header Section -->
    <div class="mb-8 animate-fade-in">
      <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
          <h1 class="text-3xl font-bold text-theme-primary mb-2 flex items-center">
            <div class="w-10 h-10 bg-gradient-to-br from-accent to-accent-hover rounded-xl mr-4 flex items-center justify-center">
              <CpuChipIcon class="w-6 h-6 text-white" />
            </div>
            Análisis con IA
          </h1>
          <p class="text-theme-secondary">Análisis asistido de imágenes clínicas con inteligencia artificial</p>
        </div>
        <div class="flex gap-3">
          <UiButton
            variant="secondary"
            @click="refreshData"
            :disabled="loading"
            class="flex items-center gap-2"
          >
            <ArrowPathIcon class="w-4 h-4" :class="{ 'animate-spin': loading }" />
            Actualizar
          </UiButton>
        </div>
      </div>
    </div>

    <!-- Sección: Nuevo Análisis -->
    <UiCard variant="glass" class="mb-8">
      <div class="p-6">
        <h2 class="text-xl font-semibold mb-4 flex items-center">
          <div class="w-8 h-8 bg-gradient-to-br from-accent to-accent-hover rounded-lg mr-3 flex items-center justify-center">
            <CpuChipIcon class="w-5 h-5 text-white" />
          </div>
          Nuevo Análisis de IA
        </h2>

        <!-- Paso 1: Seleccionar Paciente -->
        <div class="mb-4">
          <label class="block text-sm font-medium text-theme-primary mb-2">
            1. Seleccionar Paciente
          </label>
          <PatientSelector
            v-model="newAnalysis.patient"
            placeholder="Buscar paciente..."
            class="w-full"
          />
        </div>

        <!-- Paso 2: Subir Imagen -->
        <div class="mb-4" v-if="newAnalysis.patient">
          <label class="block text-sm font-medium text-theme-primary mb-2">
            2. Subir Radiografía o Imagen Clínica
          </label>
          <FileUpload
            @file-selected="handleFileSelected"
            @file-cleared="handleFileCleared"
            accept="image/*"
            :preview="true"
          />
        </div>

        <!-- Paso 3: Información Adicional (Opcional) -->
        <div class="mb-4" v-if="newAnalysis.file">
          <label class="block text-sm font-medium text-theme-primary mb-2">
            3. Tipo de Imagen
          </label>
          <select
            v-model="newAnalysis.category"
            class="w-full px-3 py-2 border border-theme rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-accent bg-theme-surface-elevated text-theme-primary"
          >
            <option value="radiografia">Radiografía</option>
            <option value="foto_clinica">Foto Clínica</option>
          </select>
        </div>

        <div class="mb-4" v-if="newAnalysis.file">
          <label class="block text-sm font-medium text-theme-primary mb-2">
            4. Descripción (Opcional)
          </label>
          <UiTextarea
            v-model="newAnalysis.description"
            placeholder="Ej: Radiografía panorámica, dolor en molar inferior derecho..."
            rows="2"
          />
        </div>

        <!-- Botón Analizar -->
        <UiButton
          @click="startAnalysis"
          :disabled="!canStartAnalysis"
          :loading="analyzing"
          size="lg"
          class="w-full"
        >
          <CpuChipIcon class="w-5 h-5 mr-2" />
          Analizar con IA
        </UiButton>
      </div>
    </UiCard>

    <!-- Stats Grid -->
    <div v-if="stats" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
      <!-- Total Analyses -->
      <UiCard variant="glass" hover>
        <div class="flex items-center justify-between">
          <div>
            <p class="text-sm font-medium text-theme-secondary mb-1">Total Análisis</p>
            <p class="text-3xl font-bold text-theme-primary">{{ stats.total_analyses }}</p>
            <p class="text-xs text-theme-secondary mt-1">Realizados</p>
          </div>
          <div class="w-12 h-12 bg-primary-100 rounded-xl flex items-center justify-center">
            <CpuChipIcon class="w-6 h-6 text-accent" />
          </div>
        </div>
      </UiCard>

      <!-- Completed Analyses -->
      <UiCard variant="glass" hover>
        <div class="flex items-center justify-between">
          <div>
            <p class="text-sm font-medium text-theme-secondary mb-1">Completados</p>
            <p class="text-3xl font-bold text-theme-primary">{{ stats.completed_analyses }}</p>
            <p class="text-xs text-theme-secondary mt-1">Listos para revisar</p>
          </div>
          <div class="w-12 h-12 bg-green-100 rounded-xl flex items-center justify-center">
            <CheckCircleIcon class="w-6 h-6 text-green-600" />
          </div>
        </div>
      </UiCard>

      <!-- Pending Review -->
      <UiCard variant="glass" hover>
        <div class="flex items-center justify-between">
          <div>
            <p class="text-sm font-medium text-theme-secondary mb-1">Pendientes</p>
            <p class="text-3xl font-bold text-theme-primary">{{ stats.pending_review }}</p>
            <p class="text-xs text-theme-secondary mt-1">Por revisar</p>
          </div>
          <div class="w-12 h-12 bg-yellow-100 rounded-xl flex items-center justify-center">
            <ClockIcon class="w-6 h-6 text-yellow-600" />
          </div>
        </div>
      </UiCard>

      <!-- Completion Rate -->
      <UiCard variant="glass" hover>
        <div class="flex items-center justify-between">
          <div>
            <p class="text-sm font-medium text-theme-secondary mb-1">Tasa Completado</p>
            <p class="text-3xl font-bold text-theme-primary">{{ stats.completion_rate }}%</p>
            <p class="text-xs text-theme-secondary mt-1">Eficiencia</p>
          </div>
          <div class="w-12 h-12 bg-primary-50 rounded-xl flex items-center justify-center">
            <ChartBarIcon class="w-6 h-6 text-accent" />
          </div>
        </div>
      </UiCard>
    </div>

    <!-- Search and Filters -->
    <UiCard variant="glass" class="mb-6">
      <div class="flex flex-col lg:flex-row gap-4">
        <div class="flex-1">
          <UiInput
            v-model="searchQuery"
            placeholder="Buscar por paciente o hallazgos..."
            class="w-full"
          >
            <template #prefix>
              <svg class="w-5 h-5 text-theme-secondary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
              </svg>
            </template>
          </UiInput>
        </div>
        <div class="flex gap-3">
          <select
            v-model="filters.status"
            class="min-w-[140px] px-3 py-2 border border-theme rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent bg-theme-surface-elevated text-theme-primary"
          >
            <option value="">Todos los estados</option>
            <option value="pending">Pendiente</option>
            <option value="processing">Procesando</option>
            <option value="completed">Completado</option>
            <option value="failed">Fallido</option>
          </select>
          <select
            v-model="filters.reviewed"
            class="min-w-[140px] px-3 py-2 border border-theme rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent bg-theme-surface-elevated text-theme-primary"
          >
            <option value="">Todos</option>
            <option value="true">Revisados</option>
            <option value="false">Sin Revisar</option>
          </select>
          <UiButton
            variant="secondary"
            @click="clearFilters"
            class="px-3"
            v-if="hasActiveFilters"
          >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
            </svg>
          </UiButton>
        </div>
      </div>
    </UiCard>

    <!-- Tabs Navigation -->
    <div class="mb-6">
      <div class="flex space-x-1 bg-theme-surface p-1 rounded-lg">
        <button
          v-for="tab in tabs"
          :key="tab.key"
          @click="activeTab = tab.key"
          :class="[
            'flex items-center px-4 py-2 text-sm font-medium rounded-md transition-all duration-200',
            activeTab === tab.key
              ? 'bg-theme-surface-elevated text-theme-primary shadow-sm'
              : 'text-theme-secondary hover:text-theme-primary'
          ]"
        >
          <component :is="tab.icon" class="w-4 h-4 mr-2" />
          {{ tab.label }}
          <span v-if="tab.count !== null" class="ml-2 px-2 py-0.5 text-xs font-medium bg-theme-surface-elevated text-theme-secondary rounded-full">
            {{ tab.count }}
          </span>
        </button>
      </div>
    </div>

    <!-- Content -->
    <div class="content-section">
      <!-- Loading State -->
      <div v-if="loading && analyses.length === 0" class="loading-state">
        <div class="loading-spinner"></div>
        <p>Cargando análisis...</p>
      </div>

      <!-- Empty State -->
      <div v-else-if="filteredAnalyses.length === 0" class="empty-state">
        <CpuChipIcon class="w-16 h-16 text-theme-secondary mx-auto mb-4" />
        <h3 class="empty-title">No hay análisis disponibles</h3>
        <p class="empty-description">
          {{ hasActiveFilters ? 'No se encontraron análisis con los filtros aplicados' : 'Aún no se han realizado análisis de IA' }}
        </p>
      </div>

      <!-- Analyses List -->
      <div v-else class="analyses-grid">
        <AiAnalysisCard
          v-for="analysis in paginatedAnalyses"
          :key="analysis.id"
          :analysis="analysis"
          @view="viewAnalysis"
          @review="reviewAnalysis"
          @delete="deleteAnalysis"
        />
      </div>

      <!-- Pagination -->
      <div v-if="totalPages > 1" class="pagination-section">
        <div class="pagination-info">
          Mostrando {{ (currentPage - 1) * itemsPerPage + 1 }} - {{ Math.min(currentPage * itemsPerPage, totalItems) }} de {{ totalItems }} análisis
        </div>
        <div class="pagination-controls">
          <button
            @click="currentPage = Math.max(1, currentPage - 1)"
            :disabled="currentPage === 1"
            class="pagination-btn"
          >
            <ChevronLeftIcon class="w-4 h-4" />
          </button>

          <div class="pagination-pages">
            <button
              v-for="page in visiblePages"
              :key="page"
              @click="currentPage = page"
              :class="[
                'pagination-page',
                { 'pagination-page-active': currentPage === page }
              ]"
            >
              {{ page }}
            </button>
          </div>

          <button
            @click="currentPage = Math.min(totalPages, currentPage + 1)"
            :disabled="currentPage === totalPages"
            class="pagination-btn"
          >
            <ChevronRightIcon class="w-4 h-4" />
          </button>
        </div>
      </div>
    </div>

    <!-- Analysis Modal -->
    <AiAnalysisModal
      v-if="selectedAnalysis"
      :analysis="selectedAnalysis"
      @close="selectedAnalysis = null"
      @review="handleReview"
    />

    <!-- Analyzing Modal -->
    <AnalyzingModal
      :show="showAnalyzingModal"
      :step="analysisStep"
    />
  </AppLayout>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import { useAiAnalysis } from '@/composables/useAiAnalysis'
import { usePermissions } from '@/composables/usePermissions'
import AiAnalysisCard from './components/AiAnalysisCard.vue'
import AiAnalysisModal from './components/AiAnalysisModal.vue'
import AnalyzingModal from './components/AnalyzingModal.vue'
import FileUpload from '@/components/ui/FileUpload.vue'
import PatientSelector from '@/components/ui/PatientSelector.vue'
import UiTextarea from '@/components/ui/UiTextarea.vue'
import {
  CpuChipIcon,
  ArrowPathIcon,
  CheckCircleIcon,
  ClockIcon,
  ChartBarIcon,
  ChevronLeftIcon,
  ChevronRightIcon
} from '@heroicons/vue/24/outline'

const {
  loading,
  analyses,
  stats,
  getAnalyses,
  getPendingAnalyses,
  getStats,
  reviewAnalysis: reviewAnalysisApi,
  deleteAnalysis: deleteAnalysisApi,
  uploadAndAnalyze
} = useAiAnalysis()

const { can } = usePermissions()

// State
const activeTab = ref('all')
const selectedAnalysis = ref(null)
const currentPage = ref(1)
const itemsPerPage = 12

// Nuevo análisis
const newAnalysis = ref({
  patient: null,
  file: null,
  description: '',
  category: 'radiografia'
})

const analyzing = ref(false)
const analysisStep = ref(0)
const showAnalyzingModal = ref(false)

const filters = ref({
  status: '',
  reviewed: '',
  date_from: '',
  date_to: ''
})

// Tabs configuration
const tabs = computed(() => [
  {
    key: 'all',
    label: 'Todos',
    icon: 'CpuChipIcon',
    count: analyses.value?.length || 0
  },
  {
    key: 'pending',
    label: 'Pendientes',
    icon: 'ClockIcon',
    count: stats.value?.pending_review || 0
  },
  {
    key: 'completed',
    label: 'Completados',
    icon: 'CheckCircleIcon',
    count: stats.value?.completed_analyses || 0
  }
])

// Computed
const hasActiveFilters = computed(() => {
  return Object.values(filters.value).some(value => value !== '')
})

// Computed para nuevo análisis
const canStartAnalysis = computed(() => {
  return newAnalysis.value.patient && newAnalysis.value.file
})

const filteredAnalyses = computed(() => {
  let filtered = analyses.value || []

  if (activeTab.value === 'pending') {
    filtered = filtered.filter(a => a.status === 'completed' && !a.reviewed)
  } else if (activeTab.value === 'completed') {
    filtered = filtered.filter(a => a.status === 'completed')
  }

  if (filters.value.status) {
    filtered = filtered.filter(a => a.status === filters.value.status)
  }

  if (filters.value.reviewed !== '') {
    const isReviewed = filters.value.reviewed === 'true'
    filtered = filtered.filter(a => a.reviewed === isReviewed)
  }

  if (filters.value.date_from) {
    filtered = filtered.filter(a => new Date(a.created_at) >= new Date(filters.value.date_from))
  }

  if (filters.value.date_to) {
    filtered = filtered.filter(a => new Date(a.created_at) <= new Date(filters.value.date_to))
  }

  return filtered
})

const totalItems = computed(() => filteredAnalyses.value.length)
const totalPages = computed(() => Math.ceil(totalItems.value / itemsPerPage))
const paginatedAnalyses = computed(() => {
  const start = (currentPage.value - 1) * itemsPerPage
  const end = start + itemsPerPage
  return filteredAnalyses.value.slice(start, end)
})

const visiblePages = computed(() => {
  const pages = []
  const start = Math.max(1, currentPage.value - 2)
  const end = Math.min(totalPages.value, currentPage.value + 2)

  for (let i = start; i <= end; i++) {
    pages.push(i)
  }

  return pages
})

// Methods
const loadData = async () => {
  try {
    await Promise.all([
      getAnalyses(),
      getStats()
    ])
  } catch (error) {
  }
}

const refreshData = async () => {
  await loadData()
}

const clearFilters = () => {
  filters.value = {
    status: '',
    reviewed: '',
    date_from: '',
    date_to: ''
  }
}

const viewAnalysis = (analysis) => {
  selectedAnalysis.value = analysis
}

const reviewAnalysis = async (analysisId, decision, notes) => {
  try {
    await reviewAnalysisApi(analysisId, decision, notes)
    selectedAnalysis.value = null
    await loadData() // Refresh data
  } catch (error) {
  }
}

const deleteAnalysis = async (analysisId) => {
  if (confirm('¿Estás seguro de que quieres eliminar este análisis?')) {
    try {
      await deleteAnalysisApi(analysisId)
      await loadData() // Refresh data
    } catch (error) {
    }
  }
}

const handleReview = (analysisId, decision, notes) => {
  reviewAnalysis(analysisId, decision, notes)
}

// Watchers
watch(activeTab, () => {
  currentPage.value = 1
})

watch(filters, () => {
  currentPage.value = 1
}, { deep: true })

// Métodos para nuevo análisis
const handleFileSelected = (file) => {
  newAnalysis.value.file = file
}

const handleFileCleared = () => {
  newAnalysis.value.file = null
}

const startAnalysis = async () => {
  if (!canStartAnalysis.value) return

  // Validar datos antes de enviar

  if (!newAnalysis.value.patient?.id) {
    alert('Por favor selecciona un paciente')
    return
  }

  if (!newAnalysis.value.file) {
    alert('Por favor selecciona una imagen')
    return
  }

  analyzing.value = true
  showAnalyzingModal.value = true
  analysisStep.value = 0

  try {
    // Paso 1: Subiendo
    analysisStep.value = 1
    await new Promise(resolve => setTimeout(resolve, 500))

    // Paso 2: Procesando
    analysisStep.value = 2
    const result = await uploadAndAnalyze(
      newAnalysis.value.patient.id,
      newAnalysis.value.file,
      newAnalysis.value.description,
      newAnalysis.value.category
    )

    // Paso 3: Completado
    analysisStep.value = 3
    await new Promise(resolve => setTimeout(resolve, 500))

    // Cerrar modal y mostrar resultados
    showAnalyzingModal.value = false
    selectedAnalysis.value = result

    // Limpiar formulario
    newAnalysis.value = {
      patient: null,
      file: null,
      description: '',
      category: 'radiografia'
    }

    // Recargar lista
    await loadData()

  } catch (error) {
    showAnalyzingModal.value = false
  } finally {
    analyzing.value = false
  }
}

// Lifecycle
onMounted(() => {
  loadData()
})
</script>

<style scoped>
.ai-analysis-page {
  @apply space-y-6;
}

.page-header {
  @apply bg-theme-surface-elevated border border-theme rounded-lg p-6;
}

.header-content {
  @apply flex items-center justify-between;
}

.page-title {
  @apply text-2xl font-bold text-theme-primary flex items-center;
}

.page-subtitle {
  @apply text-theme-secondary mt-1;
}

.stats-grid {
  @apply grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4;
}

.stat-card {
  @apply bg-theme-surface-elevated border border-theme rounded-lg p-4 flex items-center space-x-3;
}

.stat-icon {
  @apply w-12 h-12 rounded-lg flex items-center justify-center;
}

.stat-content {
  @apply flex-1;
}

.stat-value {
  @apply text-2xl font-bold text-theme-primary;
}

.stat-label {
  @apply text-sm text-theme-secondary;
}

.filters-section {
  @apply bg-theme-surface-elevated border border-theme rounded-lg p-4;
}

.filters-header {
  @apply flex items-center justify-between mb-4;
}

.filters-title {
  @apply text-lg font-medium text-theme-primary;
}

.filters-grid {
  @apply grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4;
}

.filter-group {
  @apply space-y-2;
}

.filter-label {
  @apply text-sm font-medium text-theme-primary;
}

.filter-select,
.filter-input {
  @apply w-full px-3 py-2 border border-theme rounded-lg text-sm focus:ring-2 focus:ring-primary-500 focus:border-accent bg-theme-surface-elevated text-theme-primary;
}

.tabs-section {
  @apply bg-theme-surface-elevated border border-theme rounded-lg;
}

.tabs-header {
  @apply flex border-b border-theme;
}

.tab-button {
  @apply flex items-center px-4 py-3 text-sm font-medium text-theme-secondary hover:text-theme-primary hover:bg-theme-surface border-b-2 border-transparent transition-colors;
}

.tab-active {
  @apply text-accent border-accent bg-primary-50;
}

.tab-count {
  @apply ml-2 px-2 py-1 text-xs font-medium bg-theme-surface-elevated text-theme-secondary rounded-full;
}

.content-section {
  @apply bg-theme-surface-elevated border border-theme rounded-lg;
}

.loading-state {
  @apply flex flex-col items-center justify-center py-12;
}

.loading-spinner {
  @apply animate-spin rounded-full h-8 w-8 border-b-2 border-primary-600 mb-4;
}

.empty-state {
  @apply text-center py-12;
}

.empty-title {
  @apply text-lg font-medium text-theme-primary mb-2;
}

.empty-description {
  @apply text-theme-secondary;
}

.analyses-grid {
  @apply grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 p-6;
}

.pagination-section {
  @apply flex items-center justify-between px-6 py-4 border-t border-theme;
}

.pagination-info {
  @apply text-sm text-theme-primary;
}

.pagination-controls {
  @apply flex items-center space-x-2;
}

.pagination-btn {
  @apply p-2 text-theme-secondary hover:text-theme-primary disabled:opacity-50 disabled:cursor-not-allowed;
}

.pagination-pages {
  @apply flex items-center space-x-1;
}

.pagination-page {
  @apply px-3 py-2 text-sm font-medium text-theme-primary hover:text-theme-primary hover:bg-theme-surface rounded-lg transition-colors;
}

.pagination-page-active {
  @apply text-accent bg-primary-50;
}

.btn {
  @apply inline-flex items-center px-4 py-2 text-sm font-medium rounded-lg transition-colors;
}

.btn-secondary {
  @apply bg-theme-surface text-theme-primary hover:bg-theme-surface-elevated;
}

.btn-sm {
  @apply px-3 py-1.5 text-xs;
}

.btn-outline {
  @apply border border-theme text-theme-primary hover:bg-theme-surface;
}
</style>
