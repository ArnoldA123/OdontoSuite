<template>
  <AppLayout>
    <div class="space-y-6">
      <!-- Hero band: dashboard surface vocabulary; the topbar owns the page's <h1>. -->
      <header
        ref="heroSection"
        data-ai-hero
        data-reveal="hero"
        class="relative mb-6 overflow-hidden p-6 md:p-8"
        style="
          background: var(--color-canvas);
          border: 1px solid var(--color-hairline);
          border-radius: var(--radius-panel);
          box-shadow: var(--elevation-2);
        "
        :style="revealStyle('--spring-ai-hero-o')"
      >
        <DentalMotifLayer variant="hero" />
        <div class="relative flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
          <div class="min-w-0">
            <p
              class="text-xs font-medium uppercase tracking-wide text-theme-secondary"
              data-hero-eyebrow
            >
              Inteligencia artificial
            </p>
            <h2 class="mt-2 text-4xl font-semibold text-label" data-hero-title>Análisis con IA</h2>
            <p class="mt-2 text-sm text-theme-secondary" data-hero-caption>
              Análisis asistido de imágenes clínicas con inteligencia artificial
            </p>
            <p class="mt-1 text-xs text-theme-secondary" data-ai-assistive-notice>
              Apoyo a la decisión asistido por IA. No sustituye el diagnóstico profesional.
            </p>
          </div>
          <div
            class="flex flex-wrap items-center gap-3 md:flex-shrink-0 md:flex-nowrap"
            data-hero-actions
          >
            <UiButton variant="primary" data-hero-primary @click="openSheet">
              <template #icon-left>
                <PlusIcon class="h-4 w-4" aria-hidden="true" />
              </template>
              Nuevo análisis
            </UiButton>
            <UiButton
              variant="secondary"
              :disabled="loading"
              data-hero-refresh
              @click="refreshData"
            >
              <template #icon-left>
                <ArrowPathIcon
                  class="h-4 w-4"
                  :class="{ 'animate-spin': loading }"
                  aria-hidden="true"
                />
              </template>
              Actualizar
            </UiButton>
          </div>
        </div>
      </header>

      <div
        v-if="loadError"
        data-state="error"
        role="alert"
        class="mb-6 flex flex-wrap items-center justify-between gap-4 rounded-[var(--radius-panel)] bg-systemRed-50 p-5"
        style="border: 1px solid var(--color-hairline)"
      >
        <div class="flex min-w-0 items-center gap-3">
          <ExclamationTriangleIcon
            class="h-6 w-6 flex-shrink-0 text-systemRed-600"
            aria-hidden="true"
          />
          <div class="min-w-0">
            <p class="text-sm font-medium text-theme-primary">
              No se pudieron cargar los análisis.
            </p>
            <p class="text-sm text-theme-secondary">{{ loadError }}</p>
          </div>
        </div>
        <UiButton variant="primary" size="sm" data-retry @click="refreshData">Reintentar</UiButton>
      </div>

      <!-- Summary block: dashboard KPI anatomy (non-interactive). -->
      <section
        ref="summarySection"
        data-ai-summary
        data-reveal="summary"
        aria-label="Resumen de análisis"
        :style="revealStyle('--spring-ai-summary-o')"
      >
        <div
          v-if="!hasLoadedOnce && !loadError"
          aria-busy="true"
          class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-4"
        >
          <UiSkeleton v-for="slot in 4" :key="slot" variant="card" animation="wave" />
        </div>
        <div v-else-if="!loadError" class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-4">
          <UiCard
            v-for="kpi in kpiCards"
            :key="kpi.key"
            variant="glass"
            padding="sm"
            :data-kpi-card="kpi.key"
            :style="{ boxShadow: 'var(--elevation-2)', borderColor: 'var(--color-hairline)' }"
          >
            <p class="text-xs font-medium uppercase text-theme-secondary">{{ kpi.label }}</p>
            <p
              class="mt-2 text-2xl font-bold leading-none text-label tabular-nums"
              :data-kpi-value="kpi.key"
            >
              {{ kpi.value }}
            </p>
            <p class="mt-1 text-xs text-theme-secondary" :data-kpi-caption="kpi.key">
              {{ kpi.caption }}
            </p>
          </UiCard>
        </div>
      </section>

      <!-- Directory block: filters travel to the server; the table owns the rows. -->
      <section
        ref="directorySection"
        data-ai-directory
        data-reveal="directory"
        class="space-y-4"
        :style="revealStyle('--spring-ai-directory-o')"
      >
        <div class="flex items-baseline gap-3">
          <h2 class="text-2xl font-semibold text-label">Análisis recientes</h2>
          <p class="text-sm text-theme-secondary">Revisa, filtra y da seguimiento a cada estudio</p>
        </div>

        <UiCard variant="glass" data-ai-filters>
          <div class="grid grid-cols-1 gap-4 lg:grid-cols-12 lg:items-end">
            <div class="lg:col-span-4">
              <span class="mb-2 block text-sm font-medium text-theme-primary">Estado</span>
              <div
                class="inline-flex flex-wrap gap-1 rounded-[var(--radius-control)] p-1"
                style="border: 1px solid var(--color-hairline)"
                role="group"
                aria-label="Presets de estado"
              >
                <UiButton
                  v-for="preset in presets"
                  :key="preset.id"
                  :variant="isPresetActive(preset.id) ? 'primary' : 'ghost'"
                  size="sm"
                  :aria-pressed="isPresetActive(preset.id)"
                  :data-preset="preset.id"
                  @click="applyPreset(preset.id)"
                >
                  {{ preset.label }}
                </UiButton>
              </div>
            </div>
            <div class="lg:col-span-3">
              <UiInput
                :model-value="searchQuery"
                placeholder="Buscar por paciente"
                data-filters-search
                @update:model-value="onSearchInput"
              >
                <template #prefix>
                  <MagnifyingGlassIcon class="h-5 w-5 text-theme-secondary" aria-hidden="true" />
                </template>
              </UiInput>
            </div>
            <div class="lg:col-span-2">
              <span class="mb-2 block text-sm font-medium text-theme-primary">Revisión</span>
              <UiSelect
                :model-value="filters.reviewed"
                :options="reviewedOptions"
                size="sm"
                label="Revisión"
                @update:model-value="onReviewedChange"
              />
            </div>
            <div class="lg:col-span-1">
              <UiInput
                :model-value="filters.date_from"
                type="date"
                label="Desde"
                data-filters-from
                @update:model-value="onDateFromChange"
              />
            </div>
            <div class="lg:col-span-1">
              <UiInput
                :model-value="filters.date_to"
                type="date"
                label="Hasta"
                data-filters-to
                @update:model-value="onDateToChange"
              />
            </div>
            <div v-if="hasActiveFilters" class="lg:col-span-1">
              <UiButton
                variant="secondary"
                size="sm"
                class="w-full"
                data-clear-filters
                @click="clearFilters"
              >
                Limpiar filtros
              </UiButton>
            </div>
          </div>
        </UiCard>

        <div v-if="loading && !hasLoadedOnce" aria-busy="true">
          <UiSkeleton variant="table" animation="wave" />
        </div>

        <UiCard v-else-if="!loadError && analyses.length === 0" variant="glass" data-ai-empty>
          <UiSectionEmpty
            :title="
              hasActiveFilters
                ? 'Ningún análisis coincide con los filtros aplicados.'
                : 'Sin análisis registrados'
            "
            :description="
              hasActiveFilters
                ? 'Ajusta o limpia los filtros para volver a ver los análisis.'
                : 'Sube una radiografía o foto clínica para obtener un análisis asistido.'
            "
          >
            <div class="flex flex-wrap items-center justify-center gap-3">
              <UiButton variant="primary" size="sm" data-empty-primary @click="openSheet">
                Nuevo análisis
              </UiButton>
              <UiButton
                v-if="hasActiveFilters"
                variant="secondary"
                size="sm"
                data-clear-filters
                @click="clearFilters"
              >
                Limpiar filtros
              </UiButton>
            </div>
          </UiSectionEmpty>
        </UiCard>

        <template v-else-if="!loadError">
          <UiDataTable
            :data="analyses"
            :columns="columns"
            :searchable="false"
            :pagination="false"
            :show-header="false"
            row-key="id"
            data-ai-table
          >
            <template #cell-image="{ row }">
              <img
                v-if="row.clinical_attachment?.file_path"
                :src="`/storage/${row.clinical_attachment.file_path}`"
                :alt="row.clinical_attachment.original_name || ''"
                class="h-10 w-10 rounded-[var(--radius-control)] object-cover"
              />
              <span
                v-else
                class="inline-flex h-10 w-10 items-center justify-center rounded-[var(--radius-control)] bg-theme-surface"
              >
                <PhotoIcon class="h-5 w-5 text-theme-secondary" aria-hidden="true" />
              </span>
            </template>
            <template #cell-patient="{ row }">
              <span class="font-medium text-theme-primary">
                {{ patientName(row) }}
              </span>
            </template>
            <template #cell-category="{ row }">
              <span class="text-theme-secondary">{{ categoryLabel(row) }}</span>
            </template>
            <template #cell-status="{ row }">
              <UiStatusPill :status="row.status" :variant="statusVariant(row.status)" size="sm">
                {{ getStatusLabel(row.status) }}
              </UiStatusPill>
            </template>
            <template #cell-reviewed="{ row }">
              <span :class="row.reviewed ? 'text-systemGreen-700' : 'text-theme-secondary'">
                {{ row.reviewed ? 'Revisado' : 'Sin revisar' }}
              </span>
            </template>
            <template #cell-confidence="{ row }">
              <span class="tabular-nums">{{ formatConfidence(row.confidence_score) }}</span>
            </template>
            <template #cell-created_at="{ row }">
              <span class="text-theme-secondary">{{ formatDate(row.created_at) }}</span>
            </template>
            <template #cell-actions="{ row }">
              <div class="flex items-center gap-1">
                <UiButton
                  variant="ghost"
                  size="sm"
                  data-row-action="view"
                  aria-label="Ver detalle"
                  @click="viewAnalysis(row)"
                >
                  <EyeIcon class="h-4 w-4" aria-hidden="true" />
                </UiButton>
                <UiButton
                  variant="ghost"
                  size="sm"
                  data-row-action="review"
                  aria-label="Revisar análisis"
                  @click="viewAnalysis(row)"
                >
                  <ClipboardDocumentCheckIcon class="h-4 w-4" aria-hidden="true" />
                </UiButton>
                <UiButton
                  variant="ghost"
                  size="sm"
                  data-row-action="void"
                  aria-label="Anular análisis"
                  @click="viewAnalysis(row)"
                >
                  <NoSymbolIcon class="h-4 w-4" aria-hidden="true" />
                </UiButton>
              </div>
            </template>
          </UiDataTable>

          <div v-if="paginationMeta.last_page > 1">
            <Pagination
              :current-page="paginationMeta.current_page"
              :total-pages="paginationMeta.last_page"
              :total="paginationMeta.total"
              :per-page="paginationMeta.per_page"
              @page-change="handlePageChange"
            />
          </div>
        </template>
      </section>
    </div>

    <AiAnalysisModal
      v-if="selectedAnalysis"
      :analysis="selectedAnalysis"
      @close="selectedAnalysis = null"
      @review="handleReview"
      @void="handleVoid"
    />

    <AiAnalysisSheet
      v-model="showSheet"
      :submitting="sheetSubmitting"
      :error-message="sheetError"
      @close="handleSheetClose"
      @submit="handleSheetSubmit"
    />

    <AnalyzingModal
      :show="showAnalyzingModal"
      :status="analysisStatus"
      :message="analysisErrorMessage"
      @close="showAnalyzingModal = false"
    />
  </AppLayout>
</template>

<script setup>
import { computed, nextTick, onMounted, onUnmounted, ref } from 'vue'
import {
  ArrowPathIcon,
  ClipboardDocumentCheckIcon,
  ExclamationTriangleIcon,
  EyeIcon,
  MagnifyingGlassIcon,
  NoSymbolIcon,
  PhotoIcon,
  PlusIcon
} from '@heroicons/vue/24/outline'
import { useAiAnalysis } from '../../composables/useAiAnalysis'
import { useSpring } from '../../composables/useSpring'
import { prefersReducedMotion } from '../../composables/useSpringMath'
import AppLayout from '../../components/layout/AppLayout.vue'
import DentalMotifLayer from '../../components/ui/DentalMotifLayer.vue'
import UiButton from '../../components/ui/Button.vue'
import UiCard from '../../components/ui/Card.vue'
import UiDataTable from '../../components/ui/DataTable.vue'
import UiInput from '../../components/ui/Input.vue'
import UiSectionEmpty from '../../components/ui/UiSectionEmpty.vue'
import UiSelect from '../../components/ui/Select.vue'
import UiSkeleton from '../../components/ui/Skeleton.vue'
import UiStatusPill from '../../components/ui/StatusPill.vue'
import Pagination from '../../components/ui/Pagination.vue'
import AiAnalysisModal from './components/AiAnalysisModal.vue'
import AiAnalysisSheet from './components/AiAnalysisSheet.vue'
import AnalyzingModal from './components/AnalyzingModal.vue'

const {
  analyses,
  stats,
  getAnalyses,
  getStats,
  reviewAnalysis: reviewAnalysisApi,
  voidAnalysis: voidAnalysisApi,
  uploadAndAnalyze,
  getStatusLabel,
  formatDate
} = useAiAnalysis()

const PAGE_SIZE = 15

const loading = ref(true)
const loadError = ref('')
const hasLoadedOnce = ref(false)
const paginationMeta = ref({
  current_page: 1,
  last_page: 1,
  per_page: PAGE_SIZE,
  total: 0
})

const filters = ref({
  status: '',
  reviewed: '',
  date_from: '',
  date_to: ''
})
const searchQuery = ref('')

const presets = [
  { id: 'all', label: 'Todas', status: '', reviewed: '' },
  { id: 'pending', label: 'Pendientes', status: 'completed', reviewed: false },
  { id: 'completed', label: 'Completados', status: 'completed', reviewed: '' },
  { id: 'failed', label: 'Fallidos', status: 'failed', reviewed: '' }
]

const reviewedOptions = [
  { value: '', label: 'Todas' },
  { value: true, label: 'Revisados' },
  { value: false, label: 'Sin revisar' }
]

const CATEGORY_LABELS = {
  radiografia: 'Radiografía',
  foto_clinica: 'Foto clínica'
}

const STATUS_VARIANTS = {
  pending: 'warning',
  processing: 'info',
  completed: 'success',
  failed: 'error'
}

const columns = [
  { key: 'image', label: 'Imagen' },
  { key: 'patient', label: 'Paciente' },
  { key: 'category', label: 'Tipo' },
  { key: 'status', label: 'Estado' },
  { key: 'reviewed', label: 'Revisión' },
  { key: 'confidence', label: 'Confianza' },
  { key: 'created_at', label: 'Fecha' },
  { key: 'actions', label: 'Acciones' }
]

const selectedAnalysis = ref(null)

const showSheet = ref(false)
const sheetSubmitting = ref(false)
const sheetError = ref('')
const showAnalyzingModal = ref(false)
const analysisStatus = ref('uploading')
const analysisErrorMessage = ref('')

const kpiCards = computed(() => {
  const data = stats.value ?? {}
  return [
    {
      key: 'total_analyses',
      label: 'Total de análisis',
      value: data.total_analyses ?? 0,
      caption: 'Acumulados'
    },
    {
      key: 'completed_analyses',
      label: 'Completados',
      value: data.completed_analyses ?? 0,
      caption: `${data.reviewed_analyses ?? 0} revisados`
    },
    {
      key: 'pending_review',
      label: 'Pendientes de revisión',
      value: data.pending_review ?? 0,
      caption: 'Esperando tu revisión'
    },
    {
      key: 'completion_rate',
      label: 'Tasa de completado',
      value: `${data.completion_rate ?? 0}%`,
      caption: `${data.review_rate ?? 0}% de tasa de revisión`
    }
  ]
})

const nonEmptyFilters = () => {
  const result = {}
  const term = searchQuery.value.trim()

  if (term) result.search = term
  if (filters.value.status) result.status = filters.value.status
  if (filters.value.reviewed !== '') result.reviewed = filters.value.reviewed
  if (filters.value.date_from) result.date_from = filters.value.date_from
  if (filters.value.date_to) result.date_to = filters.value.date_to

  return result
}

const hasActiveFilters = computed(() => Object.keys(nonEmptyFilters()).length > 0)

const isPresetActive = presetId => {
  const preset = presets.find(item => item.id === presetId)
  if (!preset) return false
  return filters.value.status === preset.status && filters.value.reviewed === preset.reviewed
}

const patientName = row => {
  const patient = row?.patient

  if (!patient) return 'Paciente no disponible'
  if (patient.full_name) return patient.full_name

  const composed = [patient.first_name, patient.last_name].filter(Boolean).join(' ')

  return composed || 'Paciente no disponible'
}

const categoryLabel = row => {
  const category = row?.clinical_attachment?.category
  return CATEGORY_LABELS[category] || category || '—'
}

const statusVariant = status => STATUS_VARIANTS[status] || 'neutral'

const formatConfidence = value =>
  value === null || value === undefined || value === '' ? '—' : `${value}%`

const openSheet = () => {
  sheetError.value = ''
  showSheet.value = true
}

const handleSheetClose = () => {
  showSheet.value = false
  sheetError.value = ''
}

const handleSheetSubmit = async ({
  patientId,
  file,
  category,
  description,
  noticeAcknowledged = false,
  clinicalEncounterId = null
}) => {
  if (sheetSubmitting.value) return

  // #296 — the declaration travels with the request and gates it: without it
  // the professional has not informed the patient the result is assistive.
  if (!noticeAcknowledged) return

  sheetSubmitting.value = true
  sheetError.value = ''
  analysisErrorMessage.value = ''
  analysisStatus.value = 'uploading'
  showAnalyzingModal.value = true

  try {
    analysisStatus.value = 'analyzing'
    const result = await uploadAndAnalyze(
      patientId,
      file,
      description,
      category,
      clinicalEncounterId
    )

    // #427 — the door answers 201 for a row the provider left failed. That row
    // is not a result: the sheet closes, the list refreshes and the failure
    // keeps its own surface with the reason the API publishes.
    if (result?.status !== 'completed') {
      analysisStatus.value = 'failed'
      analysisErrorMessage.value = result?.failure_reason || 'No se pudo completar el análisis.'
      showSheet.value = false
      await loadData(1)
      return
    }

    analysisStatus.value = 'completed'
    showAnalyzingModal.value = false
    showSheet.value = false
    selectedAnalysis.value = result
    await loadData(1)
  } catch (error) {
    analysisStatus.value = 'failed'
    analysisErrorMessage.value =
      error?.response?.data?.message || 'No se pudo analizar la imagen. Intenta de nuevo.'
    sheetError.value = analysisErrorMessage.value
    showAnalyzingModal.value = false
  } finally {
    sheetSubmitting.value = false
  }
}

const loadData = async (page = 1) => {
  loading.value = true
  loadError.value = ''

  try {
    const [response] = await Promise.all([
      getAnalyses({ page, per_page: PAGE_SIZE, ...nonEmptyFilters() }),
      getStats()
    ])
    const meta = response?.meta ?? {}

    paginationMeta.value = {
      current_page: meta.current_page ?? page,
      last_page: meta.last_page ?? 1,
      per_page: meta.per_page ?? PAGE_SIZE,
      total: meta.total ?? (Array.isArray(response?.data) ? response.data.length : 0)
    }
  } catch (err) {
    loadError.value = err?.response?.data?.message || 'Revisa tu conexión y vuelve a intentarlo.'
    paginationMeta.value = {
      current_page: 1,
      last_page: 1,
      per_page: PAGE_SIZE,
      total: 0
    }
  } finally {
    loading.value = false
    hasLoadedOnce.value = true
  }

  if (!entrancePlayed && !loadError.value) {
    await nextTick()
    playEntrance()
  }
}

const refreshData = () => loadData(paginationMeta.value.current_page || 1)

const handlePageChange = page => loadData(page)

const applyPreset = presetId => {
  const preset = presets.find(item => item.id === presetId)
  if (!preset) return

  filters.value = { ...filters.value, status: preset.status, reviewed: preset.reviewed }
  loadData(1)
}

const onReviewedChange = value => {
  filters.value.reviewed = value
  loadData(1)
}

const onDateFromChange = value => {
  filters.value.date_from = value
  loadData(1)
}

const onDateToChange = value => {
  filters.value.date_to = value
  loadData(1)
}

let searchTimer = null

const onSearchInput = value => {
  searchQuery.value = value

  if (searchTimer) clearTimeout(searchTimer)
  searchTimer = setTimeout(() => {
    searchTimer = null
    loadData(1)
  }, 300)
}

const clearFilters = () => {
  if (searchTimer) {
    clearTimeout(searchTimer)
    searchTimer = null
  }

  searchQuery.value = ''
  filters.value = { status: '', reviewed: '', date_from: '', date_to: '' }
  loadData(1)
}

const viewAnalysis = row => {
  selectedAnalysis.value = row
}

const handleVoid = async (analysisId, reason) => {
  try {
    await voidAnalysisApi(analysisId, reason)
    selectedAnalysis.value = null
    await loadData(paginationMeta.value.current_page || 1)
  } catch (error) {
    // The composable surfaces the failure; the analysis keeps its row.
  }
}

const handleReview = async (analysisId, decision, notes) => {
  try {
    await reviewAnalysisApi(analysisId, decision, notes)
    selectedAnalysis.value = null
    await loadData(paginationMeta.value.current_page || 1)
  } catch (error) {
    // The modal keeps the review open; the composable shows the toast.
  }
}

// One-shot staggered entrance (60ms) per section, mirroring the dashboard and
// PatientsPage pattern. revealStyle() renders the FINAL state whenever no
// spring is attached (reduced motion, or a section mounting later).
const heroSpring = useSpring({
  damping: 1.0,
  response: 0.35,
  cssVar: '--spring-ai-hero-o'
})
const summarySpring = useSpring({
  damping: 1.0,
  response: 0.35,
  cssVar: '--spring-ai-summary-o'
})
const directorySpring = useSpring({
  damping: 1.0,
  response: 0.35,
  cssVar: '--spring-ai-directory-o'
})

const heroSection = ref(null)
const summarySection = ref(null)
const directorySection = ref(null)

const revealStyle = cssVar => ({
  opacity: `var(${cssVar}, 1)`,
  transform: `translateY(calc((1 - var(${cssVar}, 1)) * 8px))`
})

let entrancePlayed = false
const playEntrance = () => {
  if (entrancePlayed) return
  entrancePlayed = true
  if (prefersReducedMotion()) return

  if (heroSection.value) heroSpring.attach(heroSection.value)
  if (summarySection.value) summarySpring.attach(summarySection.value)
  if (directorySection.value) directorySpring.attach(directorySection.value)

  setTimeout(() => heroSpring.set(1), 0)
  setTimeout(() => summarySpring.set(1), 60)
  setTimeout(() => directorySpring.set(1), 120)
}

onMounted(() => {
  loadData(1)
})

onUnmounted(() => {
  if (searchTimer) {
    clearTimeout(searchTimer)
    searchTimer = null
  }
})
</script>
