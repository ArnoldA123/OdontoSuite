<template>
  <AppLayout>
    <div class="space-y-6">
      <!-- Hero band: the login surface vocabulary + the page's single primary CTA. -->
      <header
        ref="heroSection"
        data-medical-records-hero
        data-reveal="hero"
        class="relative overflow-hidden p-6 md:p-8"
        style="
          background: var(--color-canvas);
          border: 1px solid var(--color-hairline);
          border-radius: var(--radius-panel);
          box-shadow: var(--elevation-2);
        "
        :style="revealStyle('--spring-medical-records-hero-o')"
      >
        <DentalMotifLayer variant="hero" data-hero-motif />
        <div class="relative flex flex-wrap items-center justify-between gap-3">
          <!-- Brand lockup: tooth glyph + wordmark on accent-500. -->
          <p class="inline-flex items-center gap-2">
            <svg
              width="18"
              height="18"
              viewBox="0 0 24 24"
              fill="none"
              stroke="currentColor"
              stroke-width="1.75"
              stroke-linecap="round"
              stroke-linejoin="round"
              style="color: var(--color-accent-500)"
              aria-hidden="true"
            >
              <path
                d="M8 3.5C5.5 3.5 4 5.5 4 8c0 2.5 1.5 4 2 5.5s.5 4.5 1.5 6 1.5 1 2 0 1-3.5 1.5-3.5 1 2.5 1.5 3.5 1 1 2 0 .5-4.5 1.5-6 2-3 2-5.5c0-2.5-1.5-4.5-4-4.5-1.5 0-2 1-3 1s-1.5-1-3-1z"
              />
            </svg>
            <span class="text-sm font-semibold tracking-tight text-label">OdontoSuite</span>
          </p>
          <div
            class="flex flex-wrap w-full sm:w-auto items-center justify-end gap-2 sm:gap-3"
            data-hero-actions
          >
            <UiButton
              variant="ghost"
              size="sm"
              class="flex-none whitespace-nowrap"
              data-back-button
              @click="goBack"
            >
              <template #icon-left>
                <ArrowLeftIcon class="w-4 h-4" aria-hidden="true" />
              </template>
              Volver
            </UiButton>
            <UiButton
              variant="primary"
              size="sm"
              class="flex-none whitespace-nowrap"
              data-new-record-button
              :disabled="!selectedPatient"
              @click="openCreateRecord"
            >
              <template #icon-left>
                <PlusIcon class="w-4 h-4" aria-hidden="true" />
              </template>
              Nueva historia
            </UiButton>
          </div>
        </div>
        <p class="page-title relative mt-3 text-4xl font-semibold text-label">Historias clínicas</p>
        <p class="relative mt-2 text-base leading-relaxed text-theme-secondary">
          Evoluciones, adjuntos y estadísticas del expediente clínico
        </p>
      </header>

      <!-- Summary block: dashboard KPI anatomy over the real stats payload. -->
      <section
        ref="summarySection"
        data-medical-records-block="summary"
        data-reveal="summary"
        class="space-y-4"
        :style="revealStyle('--spring-medical-records-summary-o')"
      >
        <div class="flex items-baseline gap-3">
          <h2 class="text-xl font-semibold text-label">Resumen clínico</h2>
          <p class="text-sm text-theme-secondary">Los totales de la historia seleccionada</p>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
          <UiCard
            variant="glass"
            padding="sm"
            data-stat-card="records"
            class="relative h-full flex flex-col"
            :style="{ boxShadow: 'var(--elevation-2)', borderColor: 'var(--color-hairline)' }"
          >
            <div class="flex items-start justify-between gap-3">
              <div class="min-w-0 flex-1">
                <p class="text-xs font-medium text-theme-secondary uppercase whitespace-nowrap">
                  Historias
                </p>
                <p
                  class="mt-2 text-2xl font-bold text-label tabular-nums leading-none truncate"
                  style="
                    font-feature-settings:
                      'tnum' 1,
                      'lnum' 1;
                  "
                  aria-live="polite"
                  data-stat="records"
                >
                  {{ stats?.total_records ?? '—' }}
                </p>
                <p class="mt-1 text-xs text-theme-secondary truncate" data-kpi-caption="records">
                  Historias registradas
                </p>
              </div>
            </div>
          </UiCard>

          <UiCard
            variant="glass"
            padding="sm"
            data-stat-card="evolutions"
            class="relative h-full flex flex-col"
            :style="{ boxShadow: 'var(--elevation-2)', borderColor: 'var(--color-hairline)' }"
          >
            <div class="flex items-start justify-between gap-3">
              <div class="min-w-0 flex-1">
                <p class="text-xs font-medium text-theme-secondary uppercase whitespace-nowrap">
                  Evoluciones
                </p>
                <p
                  class="mt-2 text-2xl font-bold text-label tabular-nums leading-none truncate"
                  style="
                    font-feature-settings:
                      'tnum' 1,
                      'lnum' 1;
                  "
                  aria-live="polite"
                  data-stat="evolutions"
                >
                  {{ stats?.total_evolutions ?? '—' }}
                </p>
                <p class="mt-1 text-xs text-theme-secondary truncate" data-kpi-caption="evolutions">
                  Registros clínicos
                </p>
              </div>
            </div>
          </UiCard>

          <UiCard
            variant="glass"
            padding="sm"
            data-stat-card="attachments"
            class="relative h-full flex flex-col"
            :style="{ boxShadow: 'var(--elevation-2)', borderColor: 'var(--color-hairline)' }"
          >
            <div class="flex items-start justify-between gap-3">
              <div class="min-w-0 flex-1">
                <p class="text-xs font-medium text-theme-secondary uppercase whitespace-nowrap">
                  Adjuntos
                </p>
                <p
                  class="mt-2 text-2xl font-bold text-label tabular-nums leading-none truncate"
                  style="
                    font-feature-settings:
                      'tnum' 1,
                      'lnum' 1;
                  "
                  aria-live="polite"
                  data-stat="attachments"
                >
                  {{ stats?.total_attachments ?? '—' }}
                </p>
                <p
                  class="mt-1 text-xs text-theme-secondary truncate"
                  data-kpi-caption="attachments"
                >
                  Archivos del expediente
                </p>
              </div>
            </div>
          </UiCard>

          <UiCard
            variant="glass"
            padding="sm"
            data-stat-card="follow-ups"
            class="relative h-full flex flex-col"
            :style="{ boxShadow: 'var(--elevation-2)', borderColor: 'var(--color-hairline)' }"
          >
            <div class="flex items-start justify-between gap-3">
              <div class="min-w-0 flex-1">
                <p class="text-xs font-medium text-theme-secondary uppercase whitespace-nowrap">
                  Seguimientos
                </p>
                <p
                  class="mt-2 text-2xl font-bold text-label tabular-nums leading-none truncate"
                  style="
                    font-feature-settings:
                      'tnum' 1,
                      'lnum' 1;
                  "
                  aria-live="polite"
                  data-stat="follow-ups"
                >
                  {{ stats?.pending_follow_ups ?? '—' }}
                </p>
                <p class="mt-1 text-xs text-theme-secondary truncate" data-kpi-caption="follow-ups">
                  Pendientes de seguimiento
                </p>
              </div>
            </div>
          </UiCard>
        </div>
      </section>

      <!-- Landing block: the recent-histories index is the way in without a patient. -->
      <section
        v-if="!selectedPatient"
        ref="contentSection"
        data-medical-records-block="recent"
        data-reveal="recent"
        class="space-y-4"
        :style="revealStyle('--spring-medical-records-content-o')"
      >
        <div class="flex items-baseline gap-3">
          <h2 class="text-2xl font-semibold text-label">Historias recientes</h2>
          <p class="text-sm text-theme-secondary">Abre una historia o busca al paciente</p>
        </div>

        <UiCard variant="glass">
          <div class="flex flex-col gap-4">
            <div class="flex flex-col lg:flex-row gap-3">
              <div class="flex-1">
                <UiInput
                  v-model="searchTerm"
                  placeholder="Buscar por paciente o número de historia..."
                  class="w-full"
                  data-recent-search
                  @input="onSearchInput"
                >
                  <template #prefix>
                    <MagnifyingGlassIcon class="w-5 h-5 text-theme-secondary" aria-hidden="true" />
                  </template>
                </UiInput>
              </div>
              <div class="flex flex-wrap items-center gap-3">
                <UiSelect
                  v-model="statusFilter"
                  :options="statusOptions"
                  size="sm"
                  class="min-w-[140px]"
                  data-recent-status
                  @update:model-value="loadRecentRecords(1)"
                />
                <UiButton
                  variant="secondary"
                  size="sm"
                  class="px-3 whitespace-nowrap"
                  data-recent-refresh
                  @click="loadRecentRecords(meta?.current_page || 1)"
                >
                  <template #icon-left>
                    <ArrowPathIcon class="w-4 h-4" aria-hidden="true" />
                  </template>
                  Actualizar
                </UiButton>
              </div>
            </div>
            <div data-landing-picker>
              <UiButton
                v-if="!showPatientPicker"
                variant="secondary"
                size="sm"
                data-open-patient-picker
                @click="showPatientPicker = true"
              >
                <template #icon-left>
                  <UserIcon class="w-4 h-4" aria-hidden="true" />
                </template>
                Ir a un paciente
              </UiButton>
              <div v-else class="space-y-2">
                <div class="flex items-center justify-between">
                  <p class="text-xs font-medium text-theme-secondary uppercase">Ir a un paciente</p>
                  <UiButton
                    variant="ghost"
                    size="xs"
                    data-close-patient-picker
                    @click="showPatientPicker = false"
                  >
                    Cerrar
                  </UiButton>
                </div>
                <PatientSelector
                  v-model="landingPatient"
                  placeholder="Buscar paciente..."
                  @patient-selected="openPatient"
                />
              </div>
            </div>
          </div>
        </UiCard>

        <UiCard variant="glass" class="overflow-hidden">
          <LoadingSpinner
            v-if="loadingBySurface.records"
            class="p-12"
            size="lg"
            text="Cargando historias clínicas..."
          />

          <UiSectionEmpty
            v-else-if="errors.records"
            data-state="error-records"
            title="No pudimos cargar las historias clínicas"
            :description="errors.records"
          >
            <UiButton variant="secondary" size="sm" data-retry-records @click="retryRecords">
              Reintentar
            </UiButton>
          </UiSectionEmpty>

          <UiSectionEmpty
            v-else-if="records.length === 0"
            data-state="empty-records"
            title="Sin historias clínicas"
            description="Ajusta la búsqueda o el estado, o abre la ficha de un paciente para crear la primera."
          />

          <div v-else data-recent-list>
            <ul class="divide-y divide-[color:var(--color-hairline)]">
              <li v-for="record in records" :key="record.id">
                <button
                  type="button"
                  :data-recent-row="record.id"
                  class="w-full text-left px-6 py-4 transition-colors hover:bg-theme-surface"
                  @click="openPatient(record.patient)"
                >
                  <div class="flex flex-col gap-2 lg:flex-row lg:items-center lg:justify-between">
                    <div class="min-w-0">
                      <p class="text-sm font-medium text-label truncate">
                        {{ patientName(record.patient) }}
                      </p>
                      <p class="mt-1 text-xs text-theme-secondary">
                        <span class="tabular-nums" data-row-record-number>
                          {{ record.record_number }}
                        </span>
                        <span aria-hidden="true">·</span>
                        <span class="tabular-nums">
                          Primera visita {{ formatDate(record.first_visit_date) }}
                        </span>
                      </p>
                    </div>
                    <div class="flex items-center gap-4 text-xs text-theme-secondary tabular-nums">
                      <span data-row-evolutions>
                        {{ pluralize(record.evolutions_count, 'evolución', 'evoluciones') }}
                      </span>
                      <span data-row-attachments>
                        {{ pluralize(record.attachments_count, 'adjunto', 'adjuntos') }}
                      </span>
                      <span class="text-systemBlue-700 font-medium">Abrir</span>
                    </div>
                  </div>
                </button>
              </li>
            </ul>
          </div>

          <div
            v-if="!loadingBySurface.records && !errors.records && meta && meta.last_page > 1"
            class="p-4 border-t border-hairline"
          >
            <Pagination
              :current-page="meta.current_page"
              :total-pages="meta.last_page"
              :total="meta.total"
              :per-page="meta.per_page"
              @page-change="loadRecentRecords"
            />
          </div>
        </UiCard>
      </section>

      <!-- Workspace block: the selected patient's clinical history. -->
      <section
        v-else
        ref="contentSection"
        data-medical-records-block="workspace"
        data-reveal="workspace"
        class="space-y-4"
        :style="revealStyle('--spring-medical-records-content-o')"
      >
        <UiCard variant="glass" data-patient-context>
          <div class="flex flex-wrap items-start justify-between gap-4">
            <div class="min-w-0">
              <p class="text-xs font-medium text-theme-secondary uppercase">Paciente</p>
              <p class="mt-1 text-xl font-semibold text-label" data-patient-name>
                {{ selectedPatientName }}
              </p>
              <div
                class="mt-2 flex flex-wrap items-center gap-x-4 gap-y-1 text-sm text-theme-secondary"
              >
                <span
                  v-if="selectedPatient.document_number"
                  data-patient-document
                  class="tabular-nums"
                >
                  DNI {{ selectedPatient.document_number }}
                </span>
                <span v-if="selectedPatient.phone" data-patient-phone class="tabular-nums">
                  {{ selectedPatient.phone }}
                </span>
                <span v-if="selectedPatient.email" data-patient-email>
                  {{ selectedPatient.email }}
                </span>
              </div>
              <p class="mt-2 text-xs text-theme-secondary">
                Historias:
                <span class="tabular-nums" data-patient-record-numbers>
                  {{ recordNumbersLabel }}
                </span>
              </p>
              <div v-if="records.length > 1" class="mt-3 w-full sm:w-80" data-record-selector>
                <UiSelect
                  :model-value="currentRecord?.id"
                  :options="recordOptions"
                  label="Historia activa"
                  size="sm"
                  @update:model-value="selectRecord"
                />
              </div>
            </div>
            <UiButton variant="secondary" size="sm" data-clear-selection @click="clearSelection">
              <template #icon-left>
                <XMarkIcon class="w-4 h-4" aria-hidden="true" />
              </template>
              Quitar selección
            </UiButton>
          </div>
        </UiCard>

        <UiCard variant="glass">
          <div
            class="flex flex-wrap items-center justify-between gap-2 px-1 pb-3"
            data-active-record-header
          >
            <p class="text-xs font-medium uppercase tracking-wide text-theme-secondary">
              Historia activa
            </p>
            <p class="text-sm font-semibold text-label tabular-nums" data-active-record-label>
              {{ currentRecord?.record_number || 'Sin historia registrada' }}
            </p>
          </div>

          <UiTabs v-model="activeTab" :tabs="tabs" aria-label="Superficies de la historia clínica">
            <template #general>
              <div v-if="activeTab === 'general'" class="space-y-4" data-surface="general">
                <LoadingSpinner
                  v-if="loadingBySurface.records"
                  class="p-8"
                  size="md"
                  text="Cargando historia clínica..."
                />

                <UiSectionEmpty
                  v-else-if="errors.records"
                  data-state="error-records-panel"
                  title="No pudimos cargar la historia clínica"
                  :description="errors.records"
                >
                  <UiButton variant="secondary" size="sm" @click="retryRecords">
                    Reintentar
                  </UiButton>
                </UiSectionEmpty>

                <UiSectionEmpty
                  v-else-if="records.length === 0"
                  data-state="empty-records-panel"
                  title="Sin historia clínica"
                  description="Este paciente todavía no tiene una historia clínica abierta."
                >
                  <UiButton variant="primary" size="sm" @click="openCreateRecord">
                    Crear historia
                  </UiButton>
                </UiSectionEmpty>

                <div v-else class="space-y-4" data-record-list>
                  <MedicalRecordCard
                    v-for="record in records"
                    :key="record.id"
                    :record="record"
                    @view="viewRecord"
                    @edit="editRecord"
                  />
                </div>
              </div>
            </template>

            <template #evolutions>
              <div v-if="activeTab === 'evolutions'" data-surface="evolutions">
                <UiSectionEmpty
                  v-if="!currentRecord"
                  data-state="no-record-evolutions"
                  title="Sin historia clínica"
                  description="Las evoluciones viven dentro de una historia clínica. Crea la primera para empezar."
                />

                <UiSectionEmpty
                  v-else-if="errors.evolutions"
                  data-state="error-evolutions"
                  title="No pudimos cargar las evoluciones"
                  :description="errors.evolutions"
                >
                  <UiButton variant="secondary" size="sm" @click="retryEvolutions">
                    Reintentar
                  </UiButton>
                </UiSectionEmpty>

                <EvolutionTimeline
                  v-else
                  :evolutions="evolutions"
                  :specialties="specialties"
                  :record="currentRecord"
                  :loading="loadingBySurface.evolutions"
                  @add="openEvolutionModal"
                  @edit="editEvolution"
                  @delete="handleDeleteEvolution"
                />
              </div>
            </template>

            <template #attachments>
              <div v-if="activeTab === 'attachments'" data-surface="attachments">
                <UiSectionEmpty
                  v-if="errors.attachments"
                  data-state="error-attachments"
                  title="No pudimos cargar los adjuntos"
                  :description="errors.attachments"
                >
                  <UiButton variant="secondary" size="sm" @click="retryAttachments">
                    Reintentar
                  </UiButton>
                </UiSectionEmpty>

                <AttachmentGallery
                  v-else
                  :attachments="attachments"
                  :record="currentRecord"
                  :loading="loadingBySurface.attachments"
                  @upload="openUploadModal"
                  @delete="handleDeleteAttachment"
                />
              </div>
            </template>

            <template #stats>
              <div v-if="activeTab === 'stats'" data-surface="stats">
                <p class="mb-3 text-xs text-theme-secondary" data-stats-scope>
                  Las estadísticas resumen todo el expediente del paciente, no una historia
                  concreta.
                </p>

                <UiSectionEmpty
                  v-if="errors.stats"
                  data-state="error-stats"
                  title="No pudimos cargar las estadísticas"
                  :description="errors.stats"
                >
                  <UiButton variant="secondary" size="sm" @click="retryStats">Reintentar</UiButton>
                </UiSectionEmpty>

                <MedicalRecordStats v-else :stats="stats" :loading="loadingBySurface.stats" />
              </div>
            </template>
          </UiTabs>
        </UiCard>
      </section>
    </div>

    <!-- Surface modals. Their internals belong to T4–T7; the shell only hosts them. -->
    <MedicalRecordModal
      v-if="showRecordModal"
      :record="selectedRecord"
      :patient="selectedPatient"
      :is-edit="recordModalMode === 'edit'"
      @close="closeRecordModal"
      @saved="handleRecordSaved"
    />

    <MedicalRecordDetail
      v-if="showDetailModal && selectedRecord"
      :record="selectedRecord"
      @close="closeDetailModal"
      @edit="editRecord"
      @add-evolution="handleDetailAddEvolution"
      @upload-attachment="handleDetailUpload"
    />

    <EvolutionModal
      v-if="showEvolutionModal && currentRecord"
      :record="currentRecord"
      :evolution="selectedEvolution"
      :specialties="specialties"
      @close="closeEvolutionModal"
      @saved="handleEvolutionSaved"
    />

    <UploadAttachmentModal
      v-if="showUploadModal && selectedPatient"
      :patient="selectedPatient"
      @close="closeUploadModal"
      @uploaded="handleAttachmentUploaded"
    />
  </AppLayout>
</template>

<script setup>
import { computed, nextTick, onMounted, onUnmounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import {
  ArrowLeftIcon,
  ArrowPathIcon,
  MagnifyingGlassIcon,
  PlusIcon,
  UserIcon,
  XMarkIcon
} from '@heroicons/vue/24/outline'
import { useApi } from '@/composables/useApi'
import { useMedicalRecords } from '@/composables/useMedicalRecords'
import { useEcho } from '@/composables/useEcho'
import { useToast } from '@/composables/useToast'
import { useSpring } from '@/composables/useSpring'
import { prefersReducedMotion } from '@/composables/useSpringMath'
import { formatDateOnly } from '@/utils/formatters'
import AppLayout from '@/components/layout/AppLayout.vue'
import DentalMotifLayer from '@/components/ui/DentalMotifLayer.vue'
import UiCard from '@/components/ui/Card.vue'
import UiButton from '@/components/ui/Button.vue'
import UiInput from '@/components/ui/Input.vue'
import UiSelect from '@/components/ui/Select.vue'
import UiTabs from '@/components/ui/Tabs.vue'
import UiSectionEmpty from '@/components/ui/UiSectionEmpty.vue'
import Pagination from '@/components/ui/Pagination.vue'
import PatientSelector from '@/components/ui/PatientSelector.vue'
import MedicalRecordCard from './components/MedicalRecordCard.vue'
import MedicalRecordDetail from './components/MedicalRecordDetail.vue'
import MedicalRecordModal from './components/MedicalRecordModal.vue'
import EvolutionTimeline from './components/EvolutionTimeline.vue'
import EvolutionModal from './components/EvolutionModal.vue'
import AttachmentGallery from './components/AttachmentGallery.vue'
import UploadAttachmentModal from './components/UploadAttachmentModal.vue'
import MedicalRecordStats from './components/MedicalRecordStats.vue'

// Data layer (T2): one store, one loading/error slot per surface.
const {
  records,
  meta,
  currentRecord,
  evolutions,
  attachments,
  stats,
  specialties,
  loadingBySurface,
  errors,
  getRecords,
  getAllRecords,
  getRecord,
  getEvolutions,
  getAttachments,
  getSpecialties,
  getStats,
  deleteAttachment,
  reset
} = useMedicalRecords()

const route = useRoute()
const router = useRouter()
const { get } = useApi()
const toast = useToast()
const { privateChannel, echo } = useEcho()

// Landing state.
const searchTerm = ref('')
const statusFilter = ref('active')
const showPatientPicker = ref(false)
const statusOptions = [
  { value: 'active', label: 'Activas' },
  { value: 'inactive', label: 'Inactivas' },
  { value: 'all', label: 'Todas' }
]
const landingPatient = ref(null)

// Workspace state.
const selectedPatient = ref(null)
const activeTab = ref('general')
const tabs = [
  { id: 'general', label: 'Historia general' },
  { id: 'evolutions', label: 'Evoluciones' },
  { id: 'attachments', label: 'Adjuntos' },
  { id: 'stats', label: 'Estadísticas' }
]

// Surface modals (their internals are T4–T7; the shell only hosts them).
const showRecordModal = ref(false)
const recordModalMode = ref('create')
const showDetailModal = ref(false)
const showEvolutionModal = ref(false)
const showUploadModal = ref(false)
const selectedRecord = ref(null)
const selectedEvolution = ref(null)

const patientName = patient => {
  if (!patient) return 'Paciente sin ficha'
  const full = `${patient.first_name ?? ''} ${patient.last_name ?? ''}`.trim()

  return full || patient.full_name || `Paciente #${patient.id}`
}

const selectedPatientName = computed(() => patientName(selectedPatient.value))

const recordOptions = computed(() =>
  records.value.map(record => ({
    value: record.id,
    label: `${record.record_number} · ${formatDate(record.first_visit_date)}${record.is_active ? '' : ' (inactiva)'}`
  }))
)
const recordNumbersLabel = computed(() => {
  const numbers = records.value.map(record => record.record_number).filter(Boolean)
  return numbers.length > 0 ? numbers.join(' · ') : 'Sin historia registrada'
})

const formatDate = value => formatDateOnly(value) || 'Sin fecha'

const pluralize = (count, singular, plural) => {
  const total = Number(count) || 0

  return `${total} ${total === 1 ? singular : plural}`
}

const goBack = () => {
  router.back()
}

// ---------------------------------------------------------------------------
// Landing: the recent-histories index (GET /api/medical-records filters).
// ---------------------------------------------------------------------------
let searchDebounceTimer = null

const loadRecentRecords = async (page = 1) => {
  try {
    await getRecords({
      search: searchTerm.value,
      status: statusFilter.value,
      page,
      per_page: 10
    })
  } catch {
    // errors.records carries the message the inline retry block renders.
  }

  if (!errors.records) {
    await nextTick()
    playEntrance()
  }
}

const onSearchInput = () => {
  if (searchDebounceTimer) {
    clearTimeout(searchDebounceTimer)
  }

  searchDebounceTimer = setTimeout(() => {
    searchDebounceTimer = null
    loadRecentRecords(1)
  }, 300)
}

// ---------------------------------------------------------------------------
// Workspace: one patient, four surfaces, partial-failure tolerance.
// ---------------------------------------------------------------------------
// Changing the active history reloads the timeline it owns. Attachments and
// stats are patient-scoped on purpose and say so on their surfaces.
const selectRecord = async recordId => {
  const record = records.value.find(entry => entry.id === Number(recordId))

  if (!record || record.id === currentRecord.value?.id) return

  currentRecord.value = record
  await loadEvolutions()
}

const loadEvolutions = async () => {
  if (!currentRecord.value?.id) return

  try {
    await getEvolutions(currentRecord.value.id)
  } catch {
    // errors.evolutions drives the surface retry block.
  }
}

const loadAttachments = async () => {
  if (!selectedPatient.value) return

  try {
    await getAttachments(selectedPatient.value.id)
  } catch {
    // errors.attachments drives the surface retry block.
  }
}

const loadStats = async () => {
  if (!selectedPatient.value) return

  try {
    await getStats(selectedPatient.value.id)
  } catch {
    // errors.stats drives the surface retry block.
  }
}

const syncCurrentRecord = ({ preferLatest = false } = {}) => {
  const stillSelected = preferLatest
    ? null
    : records.value.find(record => record.id === currentRecord.value?.id)

  currentRecord.value =
    stillSelected || records.value.find(record => record.is_active) || records.value[0] || null
}

const loadWorkspace = async (patient, record = null) => {
  const surfaces = [
    getAllRecords({ patient_id: patient.id, status: 'all' }),
    getAttachments(patient.id),
    getStats(patient.id),
    getSpecialties()
  ]

  // The deep link already knows which record it opened; the landing path
  // discovers it from the patient's records a moment later.
  if (record) surfaces.push(getEvolutions(record.id))

  await Promise.allSettled(surfaces)

  if (!record) {
    syncCurrentRecord()
    await loadEvolutions()
  }

  await nextTick()
  playEntrance()
}

const refreshWorkspace = async ({ preferLatest = false } = {}) => {
  if (!selectedPatient.value) return

  try {
    await getAllRecords({ patient_id: selectedPatient.value.id, status: 'all' })
  } catch {
    // errors.records drives the panel retry block.
  }

  syncCurrentRecord({ preferLatest })
  await loadEvolutions()
  await loadStats()
}

const syncSelectionQuery = patientId => {
  // `/medical-records/:id` owns its own address; only the index mirrors the
  // selection in `?patient_id=`.
  if (route.params.id) return

  const query = { ...route.query }

  if (patientId) {
    query.patient_id = String(patientId)
  } else {
    delete query.patient_id
  }

  router.replace({ path: route.path, query }).catch(() => {})
}

const openPatient = async (patient, record = null) => {
  if (!patient?.id) return

  selectedPatient.value = patient
  landingPatient.value = patient
  currentRecord.value = record
  activeTab.value = 'general'
  syncSelectionQuery(patient.id)
  await loadWorkspace(patient, record)
}

const openPatientById = async patientId => {
  try {
    const response = await get(`/api/patients/${patientId}`)

    if (response?.data?.id) {
      await openPatient(response.data)

      return
    }

    toast.error('El paciente no está disponible')
  } catch {
    toast.error('No pudimos abrir la historia del paciente')
  }

  await loadRecentRecords()
}

const openRecordById = async recordId => {
  try {
    const record = await getRecord(recordId)

    if (record?.patient?.id) {
      await openPatient(record.patient, record)

      return
    }

    toast.error('La historia clínica no tiene un paciente asociado')
  } catch {
    toast.error('No pudimos abrir la historia clínica')
  }

  await loadRecentRecords()
}

const clearSelection = async () => {
  selectedPatient.value = null
  landingPatient.value = null
  currentRecord.value = null
  selectedRecord.value = null
  selectedEvolution.value = null
  showRecordModal.value = false
  showDetailModal.value = false
  showEvolutionModal.value = false
  showUploadModal.value = false

  // No stale panel survives: the data layer drops every surface and slot.
  reset()

  syncSelectionQuery(null)
  await loadRecentRecords(1)
}

// Retries: one per surface, each confined to its own error slot.
const retryRecords = async () => {
  if (!selectedPatient.value) {
    await loadRecentRecords(meta.value?.current_page || 1)

    return
  }

  try {
    await getAllRecords({ patient_id: selectedPatient.value.id, status: 'all' })
    syncCurrentRecord()
  } catch {
    // errors.records stays set for the panel.
  }
}

const retryEvolutions = () => loadEvolutions()
const retryAttachments = () => loadAttachments()
const retryStats = () => loadStats()

const handleDeleteAttachment = async attachment => {
  if (!attachment?.id) return

  try {
    await deleteAttachment(attachment.id)
    toast.success('Adjunto eliminado')
  } catch {
    toast.error(errors.write || 'No se pudo eliminar el adjunto')

    return
  }

  await loadStats()
}

// Modal wiring.
const openCreateRecord = () => {
  selectedRecord.value = null
  recordModalMode.value = 'create'
  showRecordModal.value = true
}

const editRecord = record => {
  // The detail modal and the form are two surfaces of the same record; leaving
  // both mounted stacks one dialog on top of the other.
  showDetailModal.value = false
  selectedRecord.value = record
  recordModalMode.value = 'edit'
  showRecordModal.value = true
}

const viewRecord = record => {
  selectedRecord.value = record
  showDetailModal.value = true
}

const closeRecordModal = () => {
  showRecordModal.value = false
  selectedRecord.value = null
  recordModalMode.value = 'create'
}

const closeDetailModal = () => {
  showDetailModal.value = false
  selectedRecord.value = null
}

const openEvolutionModal = () => {
  if (!currentRecord.value) return

  selectedEvolution.value = null
  showEvolutionModal.value = true
}

const editEvolution = evolution => {
  selectedEvolution.value = evolution
  showEvolutionModal.value = true
}

const closeEvolutionModal = () => {
  showEvolutionModal.value = false
  selectedEvolution.value = null
}

const openUploadModal = () => {
  showUploadModal.value = true
}

// The detail modal forwards its footer actions; the record it shows is the one
// the evolution modal must attach to.
const handleDetailAddEvolution = record => {
  closeDetailModal()

  // The evolution belongs to the record the detail was showing, which is not
  // necessarily the one the tab had selected.
  if (record?.id) currentRecord.value = record

  openEvolutionModal()
}

const handleDetailUpload = () => {
  closeDetailModal()
  openUploadModal()
}

const closeUploadModal = () => {
  showUploadModal.value = false
}

const handleRecordSaved = async () => {
  const created = recordModalMode.value === 'create'
  closeRecordModal()
  await refreshWorkspace({ preferLatest: created })
}

const handleEvolutionSaved = async () => {
  closeEvolutionModal()
  await refreshWorkspace()
}

const handleAttachmentUploaded = async () => {
  closeUploadModal()
  await loadAttachments()
  await loadStats()
}

// ---------------------------------------------------------------------------
// Realtime: same channel and toast copy as before, but every payload is
// matched against the patient the workspace is showing.
// ---------------------------------------------------------------------------
let medicalRecordsChannel = null

const belongsToSelection = patientId =>
  selectedPatient.value?.id !== undefined &&
  patientId !== undefined &&
  patientId !== null &&
  Number(patientId) === Number(selectedPatient.value.id)

const subscribeToMedicalRecords = () => {
  try {
    medicalRecordsChannel = privateChannel('medical-records')

    if (!medicalRecordsChannel) return

    medicalRecordsChannel
      .listen('.medical-record.created', async event => {
        if (!belongsToSelection(event?.medical_record?.patient_id)) return

        await refreshWorkspace({ preferLatest: true })
        toast.success('Nueva historia clínica creada')
      })
      .listen('.medical-record.updated', async event => {
        if (!belongsToSelection(event?.medical_record?.patient_id)) return

        const index = records.value.findIndex(record => record.id === event.medical_record.id)

        if (index !== -1) {
          records.value[index] = event.medical_record
        } else {
          await refreshWorkspace()
        }

        toast.success('Historia clínica actualizada')
      })
      .listen('.clinical-evolution.created', async event => {
        const patientId =
          event?.evolution?.medical_record?.patient_id ?? event?.evolution?.patient_id

        if (!belongsToSelection(patientId)) return

        await loadEvolutions()
        await loadStats()
        toast.success('Nueva evolución clínica agregada')
      })
      .listen('.clinical-evolution.updated', async event => {
        const patientId =
          event?.evolution?.medical_record?.patient_id ?? event?.evolution?.patient_id

        if (!belongsToSelection(patientId)) return

        const updated = event.evolution
        const index = evolutions.value.findIndex(evolution => evolution.id === updated.id)

        if (index !== -1) {
          evolutions.value[index] = updated
        } else if (Number(updated.medical_record_id) === Number(currentRecord.value?.id)) {
          await loadEvolutions()
        }

        await loadStats()
        toast.success('Evolución clínica actualizada')
      })
      .listen('.clinical-attachment.created', async event => {
        const patientId =
          event?.attachment?.patient_id ?? event?.attachment?.medical_record?.patient_id

        if (!belongsToSelection(patientId)) return

        await loadAttachments()
        await loadStats()
        toast.success('Nuevo adjunto agregado')
      })
  } catch {
    // Without a WebSocket connection the page keeps working on HTTP reads.
  }
}

// ---------------------------------------------------------------------------
// One-shot staggered entrance (60 ms), mirroring PatientsPage. revealStyle()
// renders the final state whenever no spring is attached (reduced motion, or
// a block mounting later).
// ---------------------------------------------------------------------------
const heroSpring = useSpring({
  damping: 1.0,
  response: 0.35,
  cssVar: '--spring-medical-records-hero-o'
})
const summarySpring = useSpring({
  damping: 1.0,
  response: 0.35,
  cssVar: '--spring-medical-records-summary-o'
})
const contentSpring = useSpring({
  damping: 1.0,
  response: 0.35,
  cssVar: '--spring-medical-records-content-o'
})

const heroSection = ref(null)
const summarySection = ref(null)
const contentSection = ref(null)

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
  if (contentSection.value) contentSpring.attach(contentSection.value)

  setTimeout(() => heroSpring.set(1), 0)
  setTimeout(() => summarySpring.set(1), 60)
  setTimeout(() => contentSpring.set(1), 120)
}

// ---------------------------------------------------------------------------
// Lifecycle: both deep links resolve before the landing index runs.
// ---------------------------------------------------------------------------
const applyDeepLink = async () => {
  const recordId = Number(route.params.id)

  if (recordId) {
    await openRecordById(recordId)

    return
  }

  const patientId = Number(route.query.patient_id)

  if (patientId) {
    await openPatientById(patientId)

    return
  }

  await loadRecentRecords()
}

onMounted(async () => {
  subscribeToMedicalRecords()
  await applyDeepLink()
})

onUnmounted(() => {
  if (searchDebounceTimer) {
    clearTimeout(searchDebounceTimer)
  }

  if (echo) {
    try {
      echo.leave('private-medical-records')
    } catch {
      // The channel was already closed; nothing to clean up.
    }
  }
})
</script>
