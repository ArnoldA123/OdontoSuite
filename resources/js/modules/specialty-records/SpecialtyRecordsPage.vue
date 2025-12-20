<template>
  <AppLayout>
    <div class="specialty-records-page">
    <!-- Header -->
    <div class="page-header">
      <div class="flex justify-between items-center">
        <div>
          <h1 class="page-title">Registros de Especialidades</h1>
          <p class="page-subtitle">Gestiona los registros específicos por especialidad</p>
        </div>
        <button
          @click="openCreateModal"
          class="btn btn-primary"
          :disabled="loading"
        >
          <PlusIcon class="w-5 h-5 mr-2" />
          Nuevo Registro
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
            Cargar Registros
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
              Nuevo Registro
            </button>
          </div>
        </div>
      </div>

      <!-- Tabs de especialidades -->
      <div class="tabs-section">
        <div class="tabs-nav">
          <button
            v-for="specialty in availableSpecialties"
            :key="specialty.key"
            @click="activeSpecialty = specialty.key"
            :class="[
              'tab-button',
              activeSpecialty === specialty.key ? 'tab-active' : 'tab-inactive'
            ]"
          >
            <component :is="specialty.icon" class="w-4 h-4 mr-2" />
            {{ specialty.label }}
          </button>
        </div>
      </div>

      <!-- Contenido de especialidad -->
      <div class="specialty-content">
        <div v-if="loading" class="flex justify-center py-8">
          <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-primary-600"></div>
        </div>

        <div v-else-if="!hasRecords" class="empty-state">
          <component :is="getSpecialtyIcon(activeSpecialty)" class="w-12 h-12 text-theme-secondary mx-auto mb-4" />
          <h3 class="text-lg font-medium text-theme-primary mb-2">No hay registros de {{ getSpecialtyLabel(activeSpecialty) }}</h3>
          <p class="text-theme-secondary mb-4">Comienza creando el primer registro</p>
          <button @click="openCreateModal" class="btn btn-primary">
            Crear Registro
          </button>
        </div>

        <div v-else class="records-list">
          <SpecialtyRecordCard
            v-for="record in currentRecords"
            :key="record.id"
            :record="record"
            :specialty="activeSpecialty"
            @view="viewRecord"
            @edit="editRecord"
            @delete="deleteRecord"
          />
        </div>
      </div>
    </div>

    <!-- Estado sin paciente seleccionado -->
    <div v-else class="no-patient-state">
      <UserIcon class="w-16 h-16 text-theme-secondary mx-auto mb-4" />
      <h3 class="text-lg font-medium text-theme-primary mb-2">Selecciona un paciente</h3>
      <p class="text-theme-secondary">Para ver los registros de especialidades, primero selecciona un paciente</p>
    </div>

    <!-- Modales -->
    <SpecialtyRecordModal
      v-if="showModal"
      :record="selectedRecord"
      :specialty="activeSpecialty"
      :patient="selectedPatient"
      :is-edit="isEdit"
      @close="closeModal"
      @saved="handleRecordSaved"
    />

    <SpecialtyRecordDetail
      v-if="showDetailModal"
      :record="selectedRecord"
      :specialty="activeSpecialty"
      @close="closeDetailModal"
      @edit="editRecord"
    />
    </div>
  </AppLayout>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { useSpecialtyRecords } from '@/composables/useSpecialtyRecords'
import { useAuth } from '@/composables/useAuth'
import { useEcho } from '@/composables/useEcho'
import { useToast } from '@/composables/useToast'
import AppLayout from '@/components/layout/AppLayout.vue'
import PatientSelector from '@/components/ui/PatientSelector.vue'
import SpecialtyRecordCard from './components/SpecialtyRecordCard.vue'
import SpecialtyRecordModal from './components/SpecialtyRecordModal.vue'
import SpecialtyRecordDetail from './components/SpecialtyRecordDetail.vue'
import {
  PlusIcon,
  MagnifyingGlassIcon,
  UserIcon,
  // Iconos de especialidades
  WrenchScrewdriverIcon, // Implantología
  BeakerIcon, // Ortodoncia
  CpuChipIcon, // Endodoncia
  CogIcon, // Rehabilitación
  ScissorsIcon // Cirugía Oral
} from '@heroicons/vue/24/outline'

// Composables
const { user } = useAuth()
const { channel, echo } = useEcho()
const toast = useToast()
const {
  records,
  allRecords,
  loading,
  error,
  hasRecords,
  getRecords,
  getAllRecords,
  createRecord,
  updateRecord,
  deleteRecord
} = useSpecialtyRecords()

// Estado reactivo
const selectedPatient = ref(null)
const selectedRecord = ref(null)
const activeSpecialty = ref('implantologia')
const showModal = ref(false)
const showDetailModal = ref(false)
const isEdit = ref(false)

// Computed
const availableSpecialties = computed(() => {
  const specialties = [
    { key: 'implantologia', label: 'Implantología', icon: WrenchScrewdriverIcon },
    { key: 'ortodoncia', label: 'Ortodoncia', icon: BeakerIcon },
    { key: 'endodoncia', label: 'Endodoncia', icon: CpuChipIcon },
    { key: 'rehabilitacion', label: 'Rehabilitación', icon: CogIcon },
    { key: 'cirugia_oral', label: 'Cirugía Oral', icon: ScissorsIcon }
  ]

  // Filtrar por permisos del usuario
  return specialties.filter(specialty => {
    if (user.value?.role === 'administrador') return true
    if (user.value?.role === 'implantologo' && specialty.key === 'implantologia') return true
    if (user.value?.role === 'odontologo' && ['ortodoncia', 'endodoncia', 'rehabilitacion', 'cirugia_oral'].includes(specialty.key)) return true
    return false
  })
})

const currentRecords = computed(() => {
  return records.value || []
})

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
    await getAllRecords(selectedPatient.value.id)
  } catch (err) {
    console.error('Error loading patient records:', err)
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

const closeModal = () => {
  showModal.value = false
  selectedRecord.value = null
  isEdit.value = false
}

const closeDetailModal = () => {
  showDetailModal.value = false
  selectedRecord.value = null
}

const handleRecordSaved = (record) => {
  closeModal()
  loadPatientRecords()
}

const getSpecialtyIcon = (specialty) => {
  const icons = {
    implantologia: WrenchScrewdriverIcon,
    ortodoncia: BeakerIcon,
    endodoncia: CpuChipIcon,
    rehabilitacion: CogIcon,
    cirugia_oral: ScissorsIcon
  }
  return icons[specialty] || UserIcon
}

const getSpecialtyLabel = (specialty) => {
  const labels = {
    implantologia: 'Implantología',
    ortodoncia: 'Ortodoncia',
    endodoncia: 'Endodoncia',
    rehabilitacion: 'Rehabilitación',
    cirugia_oral: 'Cirugía Oral'
  }
  return labels[specialty] || specialty
}

// WebSocket subscriptions
let specialtyRecordsChannel = null

// Lifecycle
onMounted(() => {
  // No cargar datos hasta que se seleccione un paciente

  // Suscribirse a canales WebSocket para actualizaciones en tiempo real
  try {
    specialtyRecordsChannel = channel('specialty-records')
    if (specialtyRecordsChannel) {
      specialtyRecordsChannel
        .listen('.specialty-record.created', async (e) => {
          console.log('Specialty record created via WebSocket:', e.record, e.specialty)
          // Solo actualizar si es del paciente seleccionado y de la especialidad activa
          if (selectedPatient.value && e.record.patient_id === selectedPatient.value.id) {
            if (e.specialty === activeSpecialty.value) {
              await loadPatientRecords()
              toast.success(`Nuevo registro de ${getSpecialtyLabel(e.specialty)} creado`)
            }
          }
        })
        .listen('.specialty-record.updated', async (e) => {
          console.log('Specialty record updated via WebSocket:', e.record, e.specialty)
          // Solo actualizar si es del paciente seleccionado y de la especialidad activa
          if (selectedPatient.value && e.record.patient_id === selectedPatient.value.id) {
            if (e.specialty === activeSpecialty.value) {
              // Actualizar el registro en la lista si existe
              const index = records.value.findIndex(r => r.id === e.record.id)
              if (index !== -1) {
                records.value[index] = e.record
              } else {
                await loadPatientRecords()
              }
              toast.success(`Registro de ${getSpecialtyLabel(e.specialty)} actualizado`)
            }
          }
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
      echo.leave('specialty-records')
    } catch (e) {
      console.error('Error leaving specialty-records channel:', e)
    }
  }
})
</script>

<style scoped>
.specialty-records-page {
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
  @apply border-b border-theme;
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
  @apply border-transparent text-theme-secondary hover:text-theme-primary hover:border-theme;
}

.specialty-content {
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
  @apply bg-theme-surface text-theme-primary hover:bg-theme-surface-elevated;
}
</style>
