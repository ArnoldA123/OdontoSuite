<template>
  <AppLayout>
    <div class="medical-records-page">
    <!-- Header -->
    <div class="page-header">
      <div class="flex justify-between items-center">
        <div>
          <h1 class="page-title">Historias Clínicas</h1>
          <p class="page-subtitle">Gestiona las historias clínicas de tus pacientes</p>
        </div>
        <button
          @click="openCreateModal"
          class="btn btn-primary"
          :disabled="loading"
        >
          <PlusIcon class="w-5 h-5 mr-2" />
          Nueva Historia
        </button>
      </div>
    </div>

    <!-- Selector de paciente -->
    <div class="patient-selector-section">
      <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div>
          <label class="block text-sm font-medium text-theme-primary mb-1">Seleccionar Paciente</label>
          <PatientSelector
            v-model="selectedPatient"
            @change="handlePatientChange"
            placeholder="Buscar paciente..."
          />
        </div>

        <div v-if="selectedPatient" class="flex items-end">
          <button
            @click="loadPatientRecords"
            class="btn btn-secondary"
            :disabled="loading"
          >
            <MagnifyingGlassIcon class="w-4 h-4 mr-1" />
            Cargar Historia
          </button>
        </div>
      </div>
    </div>

    <!-- Contenido principal -->
    <div v-if="selectedPatient" class="main-content">
      <!-- Información del paciente -->
      <div class="patient-info-card">
        <div class="flex items-center justify-between">
          <div>
            <h2 class="patient-name">{{ selectedPatient.first_name }} {{ selectedPatient.last_name }}</h2>
            <p class="patient-details">
              {{ selectedPatient.email }} • {{ selectedPatient.phone }}
            </p>
          </div>
          <div class="patient-actions">
            <button @click="openCreateModal" class="btn btn-primary btn-sm">
              <PlusIcon class="w-4 h-4 mr-1" />
              Nueva Historia
            </button>
          </div>
        </div>
      </div>

      <!-- Tabs de navegación -->
      <div class="tabs-section">
        <div class="tabs-nav">
          <button
            v-for="tab in tabs"
            :key="tab.key"
            @click="activeTab = tab.key"
            :class="[
              'tab-button',
              activeTab === tab.key ? 'tab-active' : 'tab-inactive'
            ]"
          >
            <component :is="tab.icon" class="w-4 h-4 mr-2" />
            {{ tab.label }}
          </button>
        </div>
      </div>

      <!-- Contenido de tabs -->
      <div class="tab-content">
        <!-- Historia General -->
        <div v-if="activeTab === 'general'" class="tab-panel">
          <div v-if="loading" class="flex justify-center py-8">
            <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-primary-600"></div>
          </div>

          <div v-else-if="!hasRecords" class="empty-state">
            <DocumentTextIcon class="w-12 h-12 text-theme-secondary mx-auto mb-4" />
            <h3 class="text-lg font-medium text-theme-primary mb-2">No hay historia clínica</h3>
            <p class="text-theme-secondary mb-4">Comienza creando la historia clínica del paciente</p>
            <button @click="openCreateModal" class="btn btn-primary">
              Crear Historia
            </button>
          </div>

          <div v-else class="records-list">
            <MedicalRecordCard
              v-for="record in records"
              :key="record.id"
              :record="record"
              @view="viewRecord"
              @edit="editRecord"
              @delete="deleteRecord"
            />
          </div>
        </div>

        <!-- Evoluciones -->
        <div v-if="activeTab === 'evolutions'" class="tab-panel">
          <EvolutionTimeline
            :evolutions="evolutions"
            :loading="loading"
            @add="openEvolutionModal"
            @edit="editEvolution"
            @delete="deleteEvolution"
          />
        </div>

        <!-- Adjuntos -->
        <div v-if="activeTab === 'attachments'" class="tab-panel">
          <AttachmentGallery
            :attachments="attachments"
            :loading="loading"
            @upload="openUploadModal"
            @delete="deleteAttachment"
          />
        </div>

        <!-- Estadísticas -->
        <div v-if="activeTab === 'stats'" class="tab-panel">
          <MedicalRecordStats
            :stats="stats"
            :loading="loading"
          />
        </div>
      </div>
    </div>

    <!-- Estado sin paciente seleccionado -->
    <div v-else class="no-patient-state">
      <UserIcon class="w-16 h-16 text-theme-secondary mx-auto mb-4" />
      <h3 class="text-lg font-medium text-theme-primary mb-2">Selecciona un paciente</h3>
      <p class="text-theme-secondary">Para ver las historias clínicas, primero selecciona un paciente</p>
    </div>

    <!-- Modales -->
    <MedicalRecordModal
      v-if="showModal"
      :record="selectedRecord"
      :patient="selectedPatient"
      :is-edit="isEdit"
      @close="closeModal"
      @saved="handleRecordSaved"
    />

    <MedicalRecordDetail
      v-if="showDetailModal"
      :record="selectedRecord"
      @close="closeDetailModal"
      @edit="editRecord"
    />

    <EvolutionModal
      v-if="showEvolutionModal"
      :evolution="selectedEvolution"
      :patient="selectedPatient"
      :record="currentRecord"
      :is-edit="isEditEvolution"
      @close="closeEvolutionModal"
      @saved="handleEvolutionSaved"
    />

    <UploadAttachmentModal
      v-if="showUploadModal"
      :patient="selectedPatient"
      @close="closeUploadModal"
      @uploaded="handleAttachmentUploaded"
    />
    </div>
  </AppLayout>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { useMedicalRecords } from '@/composables/useMedicalRecords'
import { useAuth } from '@/composables/useAuth'
import { useEcho } from '@/composables/useEcho'
import { useToast } from '@/composables/useToast'
import AppLayout from '@/components/layout/AppLayout.vue'
import PatientSelector from '@/components/ui/PatientSelector.vue'
import MedicalRecordCard from './components/MedicalRecordCard.vue'
import MedicalRecordModal from './components/MedicalRecordModal.vue'
import MedicalRecordDetail from './components/MedicalRecordDetail.vue'
import EvolutionTimeline from './components/EvolutionTimeline.vue'
import EvolutionModal from './components/EvolutionModal.vue'
import AttachmentGallery from './components/AttachmentGallery.vue'
import UploadAttachmentModal from './components/UploadAttachmentModal.vue'
import MedicalRecordStats from './components/MedicalRecordStats.vue'
import {
  PlusIcon,
  MagnifyingGlassIcon,
  DocumentTextIcon,
  UserIcon,
  ClockIcon,
  PaperClipIcon,
  ChartBarIcon
} from '@heroicons/vue/24/outline'

// Composables
const { user } = useAuth()
const { channel, echo } = useEcho()
const toast = useToast()
const {
  records,
  evolutions,
  attachments,
  stats,
  loading,
  error,
  hasRecords,
  getRecords,
  getEvolutions,
  getAttachmentsByCategory,
  getStats,
  createRecord,
  updateRecord,
  deleteRecord,
  addEvolution,
  updateEvolution,
  deleteEvolution,
  uploadAttachment,
  deleteAttachment
} = useMedicalRecords()

// Estado reactivo
const selectedPatient = ref(null)
const selectedRecord = ref(null)
const selectedEvolution = ref(null)
const currentRecord = ref(null)
const activeTab = ref('general')
const showModal = ref(false)
const showDetailModal = ref(false)
const showEvolutionModal = ref(false)
const showUploadModal = ref(false)
const isEdit = ref(false)
const isEditEvolution = ref(false)

// Computed
const tabs = computed(() => [
  { key: 'general', label: 'Historia General', icon: DocumentTextIcon },
  { key: 'evolutions', label: 'Evoluciones', icon: ClockIcon },
  { key: 'attachments', label: 'Adjuntos', icon: PaperClipIcon },
  { key: 'stats', label: 'Estadísticas', icon: ChartBarIcon }
])

// Métodos
const handlePatientChange = (patient) => {
  selectedPatient.value = patient
  if (patient) {
    loadPatientRecords()
  }
}

const loadPatientRecords = async () => {
  if (!selectedPatient.value) return

  try {
    await Promise.all([
      getRecords(selectedPatient.value.id),
      getEvolutions(selectedPatient.value.id),
      getAttachmentsByCategory(selectedPatient.value.id, 'general'),
      getStats(selectedPatient.value.id)
    ])
  } catch (err) {
  }
}

const openCreateModal = () => {
  selectedRecord.value = null
  isEdit.value = false
  showModal.value = true
}

const editRecord = (record) => {
  selectedRecord.value = record
  isEdit.value = true
  showModal.value = true
}

const viewRecord = (record) => {
  selectedRecord.value = record
  showDetailModal.value = true
}

const openEvolutionModal = (record = null) => {
  currentRecord.value = record
  selectedEvolution.value = null
  isEditEvolution.value = false
  showEvolutionModal.value = true
}

const editEvolution = (evolution) => {
  selectedEvolution.value = evolution
  isEditEvolution.value = true
  showEvolutionModal.value = true
}

const openUploadModal = () => {
  showUploadModal.value = true
}

const closeModal = () => {
  showModal.value = false
  selectedRecord.value = null
  isEdit.value = false
}

const closeDetailModal = () => {
  showDetailModal.value = false
  selectedRecord.value = null
}

const closeEvolutionModal = () => {
  showEvolutionModal.value = false
  selectedEvolution.value = null
  currentRecord.value = null
  isEditEvolution.value = false
}

const closeUploadModal = () => {
  showUploadModal.value = false
}

const handleRecordSaved = (record) => {
  closeModal()
  loadPatientRecords()
}

const handleEvolutionSaved = (evolution) => {
  closeEvolutionModal()
  loadPatientRecords()
}

const handleAttachmentUploaded = (attachment) => {
  closeUploadModal()
  loadPatientRecords()
}

// WebSocket subscriptions
let medicalRecordsChannel = null

// Lifecycle
onMounted(() => {
  // No cargar datos hasta que se seleccione un paciente

  // Suscribirse a canales WebSocket para actualizaciones en tiempo real
  try {
    medicalRecordsChannel = channel('medical-records')
    if (medicalRecordsChannel) {
      medicalRecordsChannel
        .listen('.medical-record.created', async (e) => {
          // Solo actualizar si es del paciente seleccionado
          if (selectedPatient.value && e.medical_record.patient_id === selectedPatient.value.id) {
            await loadPatientRecords()
            toast.success('Nueva historia clínica creada')
          }
        })
        .listen('.medical-record.updated', async (e) => {
          // Solo actualizar si es del paciente seleccionado
          if (selectedPatient.value && e.medical_record.patient_id === selectedPatient.value.id) {
            // Actualizar el registro en la lista si existe
            const index = records.value.findIndex(r => r.id === e.medical_record.id)
            if (index !== -1) {
              records.value[index] = e.medical_record
            } else {
              await loadPatientRecords()
            }
            toast.success('Historia clínica actualizada')
          }
        })
        .listen('.clinical-evolution.created', async (e) => {
          // Solo actualizar si es del paciente seleccionado
          if (selectedPatient.value && e.evolution.medical_record?.patient_id === selectedPatient.value.id) {
            await loadPatientRecords()
            toast.success('Nueva evolución clínica agregada')
          }
        })
        .listen('.clinical-attachment.created', async (e) => {
          // Solo actualizar si es del paciente seleccionado
          if (selectedPatient.value && e.attachment.medical_record?.patient_id === selectedPatient.value.id) {
            await loadPatientRecords()
            toast.success('Nuevo adjunto agregado')
          }
        })
    }
  } catch (error) {
  }
})

onUnmounted(() => {
  // Limpiar suscripciones WebSocket
  if (echo) {
    try {
      echo.leave('medical-records')
    } catch (e) {
    }
  }
})
</script>

<style scoped>
.medical-records-page {
  @apply p-6;
}

.page-header {
  @apply mb-6;
}

.page-title {
  @apply text-2xl font-bold text-theme-primary;
}

.page-subtitle {
  @apply text-theme-secondary;
}

.patient-selector-section {
  @apply mb-6 p-4 bg-theme-surface rounded-lg;
}

.main-content {
  @apply space-y-6;
}

.patient-info-card {
  @apply p-4 bg-theme-surface-elevated border border-theme rounded-lg shadow-sm;
}

.patient-name {
  @apply text-xl font-semibold text-theme-primary;
}

.patient-details {
  @apply text-sm text-theme-secondary;
}

.patient-actions {
  @apply flex space-x-2;
}

.tabs-section {
  @apply border-b;
  border-color: var(--color-border);
}

.tabs-nav {
  @apply flex space-x-8;
}

.tab-button {
  @apply flex items-center py-4 px-1 border-b-2 font-medium text-sm transition-colors;
}

.tab-active {
  @apply border-primary-500 text-primary-600;
}

.tab-inactive {
  @apply border-transparent;
  color: var(--color-text-secondary);
}

.tab-inactive:hover {
  color: var(--color-text-primary);
  border-color: var(--color-border);
}

.tab-content {
  @apply mt-6;
}

.tab-panel {
  @apply space-y-4;
}

.empty-state {
  @apply text-center py-12;
}

.no-patient-state {
  @apply text-center py-12;
}

.records-list {
  @apply space-y-4;
}

.btn {
  @apply inline-flex items-center justify-center px-4 py-2 border border-transparent text-sm font-medium rounded-md transition-colors;
}

.btn-sm {
  @apply px-3 py-1 text-xs;
}

.btn-primary {
  @apply bg-primary-600 text-white hover:bg-primary-700;
}

.btn-secondary {
  background-color: var(--color-surface);
  color: var(--color-text-primary);
}

.btn-secondary:hover {
  background-color: var(--color-surface-elevated);
}
</style>
