<template>
  <AppLayout>
    <div class="bg-canvas">
      <header
        data-specialty-records-hero
        class="mb-6 p-6 md:p-8"
        style="
          background: var(--color-canvas);
          border: 1px solid var(--color-hairline);
          border-radius: var(--radius-panel);
          box-shadow: var(--elevation-2);
        "
      >
        <div class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
          <div class="min-w-0">
            <p
              class="text-xs font-medium uppercase tracking-wide text-theme-secondary"
              data-hero-eyebrow
            >
              Registros clínicos
            </p>
            <h2 class="mt-2 text-4xl font-semibold text-label" data-hero-title>
              Registros de Especialidades
            </h2>
            <p class="mt-2 text-sm text-theme-secondary" data-hero-caption>
              Consulta y registra los datos clínicos de cada especialidad del paciente
            </p>
          </div>
          <div class="flex flex-shrink-0 items-center gap-3">
            <UiButton variant="secondary" data-back-button @click="goBack">
              <template #icon-left>
                <ArrowLeftIcon class="h-4 w-4" aria-hidden="true" />
              </template>
              Volver
            </UiButton>
            <UiButton
              variant="primary"
              data-new-record
              :disabled="!selectedPatient || availableDefinitions.length === 0"
              @click="openCreate"
            >
              <template #icon-left>
                <PlusIcon class="h-4 w-4" aria-hidden="true" />
              </template>
              Nuevo registro
            </UiButton>
          </div>
        </div>
      </header>

      <section
        aria-label="Resumen de la especialidad activa"
        class="mb-6 grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-4"
      >
        <UiCard
          variant="glass"
          padding="sm"
          data-kpi-card="total"
          :style="{ boxShadow: 'var(--elevation-2)', borderColor: 'var(--color-hairline)' }"
        >
          <p class="text-xs font-medium uppercase text-theme-secondary">Total</p>
          <p
            class="mt-2 text-2xl font-bold text-label tabular-nums leading-none"
            data-kpi-value="total"
          >
            {{ kpiTotal }}
          </p>
          <p class="mt-1 text-xs text-theme-secondary">Registros de la especialidad</p>
        </UiCard>

        <UiCard
          variant="glass"
          padding="sm"
          data-kpi-card="in_progress"
          :style="{ boxShadow: 'var(--elevation-2)', borderColor: 'var(--color-hairline)' }"
        >
          <p class="text-xs font-medium uppercase text-theme-secondary">En curso</p>
          <p
            class="mt-2 text-2xl font-bold text-label tabular-nums leading-none"
            data-kpi-value="in_progress"
          >
            {{ kpiInProgress }}
          </p>
          <p class="mt-1 text-xs text-theme-secondary">Tratamientos activos</p>
        </UiCard>

        <UiCard
          variant="glass"
          padding="sm"
          data-kpi-card="completed"
          :style="{ boxShadow: 'var(--elevation-2)', borderColor: 'var(--color-hairline)' }"
        >
          <p class="text-xs font-medium uppercase text-theme-secondary">Completados</p>
          <p
            class="mt-2 text-2xl font-bold text-label tabular-nums leading-none"
            data-kpi-value="completed"
          >
            {{ kpiCompleted }}
          </p>
          <p class="mt-1 text-xs text-theme-secondary">Casos cerrados</p>
        </UiCard>

        <UiCard
          variant="glass"
          padding="sm"
          data-kpi-card="last"
          :style="{ boxShadow: 'var(--elevation-2)', borderColor: 'var(--color-hairline)' }"
        >
          <p class="text-xs font-medium uppercase text-theme-secondary">Último registro</p>
          <p
            class="mt-2 text-2xl font-bold text-label tabular-nums leading-none"
            data-kpi-value="last"
          >
            {{ kpiLast }}
          </p>
          <p class="mt-1 text-xs text-theme-secondary">Fecha de la última captura</p>
        </UiCard>
      </section>

      <UiCard variant="glass" class="mb-6" data-patient-card>
        <div
          v-if="selectedPatient"
          class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"
        >
          <div class="min-w-0">
            <p class="text-xs font-medium uppercase tracking-wide text-theme-secondary">Paciente</p>
            <p class="mt-1 truncate text-lg font-semibold text-label" data-patient-name>
              {{ patientName }}
            </p>
            <p class="mt-1 text-sm text-theme-secondary tabular-nums" data-patient-meta>
              {{ patientMeta }}
            </p>
          </div>
          <UiButton variant="secondary" data-change-patient @click="showPatientModal = true">
            <template #icon-left>
              <ArrowsRightLeftIcon class="h-4 w-4" aria-hidden="true" />
            </template>
            Cambiar paciente
          </UiButton>
        </div>

        <div v-else>
          <p class="text-xs font-medium uppercase tracking-wide text-theme-secondary">Paciente</p>
          <p class="mt-1 mb-3 text-sm text-theme-secondary">
            Busca al paciente para consultar y registrar sus especialidades.
          </p>
          <PatientSelector v-model="selectedPatient" @patient-selected="onPatientSelected" />
        </div>
      </UiCard>

      <section aria-label="Registros de especialidades">
        <div v-if="availableDefinitions.length === 0" data-specialty-records-no-specialties>
          <UiEmptyState
            title="Sin especialidades asignadas"
            description="Tu rol no tiene especialidades clínicas habilitadas. Solicita acceso al administrador."
            :icon="ShieldCheckIcon"
          />
        </div>

        <div v-else-if="!selectedPatient" data-specialty-records-no-patient>
          <UiEmptyState
            title="Selecciona un paciente"
            description="Los registros de especialidades se consultan por paciente."
            :icon="UserIcon"
          />
        </div>

        <UiTabs v-else v-model="activeSpecialty" :tabs="tabs" aria-label="Especialidades clínicas">
          <template
            v-for="definition in availableDefinitions"
            :key="definition.key"
            #[definition.key]
          >
            <div v-if="activeSpecialty === definition.key" data-specialty-records-panel>
              <div v-if="loading" data-specialty-records-loading>
                <UiSkeleton variant="card" animation="wave" aria-label="Cargando registros..." />
              </div>

              <div
                v-else-if="loadError"
                data-specialty-records-error
                role="alert"
                class="flex flex-wrap items-center justify-between gap-4 rounded-[var(--radius-panel)] border border-hairline bg-systemRed-50 p-5"
              >
                <div class="flex min-w-0 items-center gap-3">
                  <ExclamationTriangleIcon
                    class="h-6 w-6 flex-shrink-0 text-systemRed-600"
                    aria-hidden="true"
                  />
                  <div class="min-w-0">
                    <p class="text-sm font-medium text-theme-primary">
                      No pudimos cargar los registros
                    </p>
                    <p class="text-sm text-theme-secondary">
                      Revisa tu conexión y vuelve a intentarlo.
                    </p>
                  </div>
                </div>
                <UiButton
                  variant="primary"
                  size="sm"
                  data-specialty-records-retry
                  @click="loadRecords"
                >
                  Reintentar
                </UiButton>
              </div>

              <div v-else-if="recordsFor(definition.key).length === 0" data-specialty-records-empty>
                <UiEmptyState
                  :title="`Sin registros de ${definition.label}`"
                  description="Registra el primer caso para dar seguimiento clínico."
                  :icon="definition.icon"
                >
                  <template #action>
                    <UiButton @click="openCreate">Nuevo registro</UiButton>
                  </template>
                </UiEmptyState>
              </div>

              <div v-else data-specialty-records-grid class="grid grid-cols-1 gap-4 lg:grid-cols-2">
                <SpecialtyRecordCard
                  v-for="record in recordsFor(definition.key)"
                  :key="record.id"
                  :record="record"
                  :definition="definition"
                  @view="viewRecord(record, definition.key)"
                  @edit="editRecord(record, definition.key)"
                  @delete="requestDelete(record, definition.key)"
                />
              </div>
            </div>
          </template>
        </UiTabs>
      </section>
    </div>

    <UiModal
      v-model="showPatientModal"
      title="Cambiar paciente"
      size="lg"
      @close="showPatientModal = false"
    >
      <div class="bg-canvas">
        <PatientSelector v-model="selectedPatient" @patient-selected="onPatientSelected" />
      </div>
      <template #footer>
        <UiButton variant="secondary" data-patient-modal-close @click="showPatientModal = false">
          Cerrar
        </UiButton>
      </template>
    </UiModal>

    <SpecialtyRecordModal
      v-if="showForm"
      :record="selectedRecord"
      :specialty="selectedRecordSpecialty"
      :patient="selectedPatient"
      :is-edit="isEdit"
      @close="closeForm"
      @saved="onSaved"
    />

    <SpecialtyRecordDetail
      v-if="showDetail && selectedRecord"
      :record="selectedRecord"
      :specialty="selectedRecordSpecialty"
      @close="closeDetail"
      @edit="editRecord(selectedRecord, selectedRecordSpecialty)"
    />
  </AppLayout>
</template>

<script setup>
import { computed, onMounted, onUnmounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import {
  ArrowLeftIcon,
  ArrowsRightLeftIcon,
  ExclamationTriangleIcon,
  PlusIcon,
  ShieldCheckIcon,
  UserIcon
} from '@heroicons/vue/24/outline'
import { useAuth } from '@/composables/useAuth'
import { useConfirm } from '@/composables/useConfirm'
import { useEcho } from '@/composables/useEcho'
import { useErrorHandler } from '@/composables/useErrorHandler'
import { useSpecialtyRecords } from '@/composables/useSpecialtyRecords'
import { useToast } from '@/composables/useToast'
import AppLayout from '@/components/layout/AppLayout.vue'
import PatientSelector from '@/components/ui/PatientSelector.vue'
import UiButton from '@/components/ui/Button.vue'
import UiCard from '@/components/ui/Card.vue'
import UiEmptyState from '@/components/ui/EmptyState.vue'
import UiModal from '@/components/ui/Modal.vue'
import UiSkeleton from '@/components/ui/Skeleton.vue'
import UiTabs from '@/components/ui/Tabs.vue'
import SpecialtyRecordCard from './components/SpecialtyRecordCard.vue'
import SpecialtyRecordDetail from './components/SpecialtyRecordDetail.vue'
import SpecialtyRecordModal from './components/SpecialtyRecordModal.vue'
import {
  countInGroup,
  definitionsForRole,
  formatRecordDate,
  getSpecialtyDefinition,
  latestRecordDate
} from './specialtyRecordMeta'

const router = useRouter()
const { user } = useAuth()
const { channel, echo } = useEcho()
const { confirm } = useConfirm()
const { handleError } = useErrorHandler()
const toast = useToast()
const { allRecords, loading, getAllRecords, deleteRecord } = useSpecialtyRecords()

const selectedPatient = ref(null)
const selectedRecord = ref(null)
const selectedRecordSpecialty = ref('')
const activeSpecialty = ref('')
const showForm = ref(false)
const showDetail = ref(false)
const showPatientModal = ref(false)
const isEdit = ref(false)
const loadError = ref(false)

const availableDefinitions = computed(() => definitionsForRole(user.value?.role))
const activeDefinition = computed(() => getSpecialtyDefinition(activeSpecialty.value))

const recordsFor = specialty => {
  const group = allRecords.value?.[specialty]

  return Array.isArray(group) ? group : []
}

const tabs = computed(() =>
  availableDefinitions.value.map(definition => ({
    id: definition.key,
    label: definition.label,
    icon: definition.icon,
    badge: String(countInGroup(recordsFor(definition.key))),
    badgeVariant: countInGroup(recordsFor(definition.key)) > 0 ? 'primary' : 'neutral',
    badgeSize: 'sm'
  }))
)

const patientName = computed(() => {
  const patient = selectedPatient.value
  if (!patient) return ''

  return `${patient.first_name ?? ''} ${patient.last_name ?? ''}`.trim()
})

const patientMeta = computed(() => {
  const patient = selectedPatient.value
  if (!patient) return ''

  const document = patient.document_number ?? patient.dni ?? ''
  const phone = patient.phone ?? ''

  return [document ? `Documento ${document}` : '', phone].filter(Boolean).join(' · ')
})

const activeRecords = computed(() => recordsFor(activeSpecialty.value))

const countByStatuses = statuses => {
  const definition = activeDefinition.value
  if (!definition || !Array.isArray(statuses)) return 0

  return activeRecords.value.filter(record => statuses.includes(record?.[definition.statusField]))
    .length
}

const kpiTotal = computed(() => String(countInGroup(activeRecords.value)))
const kpiInProgress = computed(() =>
  String(countByStatuses(activeDefinition.value?.progressStatuses))
)
const kpiCompleted = computed(() =>
  String(countByStatuses(activeDefinition.value?.completedStatuses))
)
const kpiLast = computed(() => formatRecordDate(latestRecordDate(activeRecords.value)))

const loadRecords = async () => {
  if (!selectedPatient.value) return

  loadError.value = false

  try {
    await getAllRecords(selectedPatient.value.id)
  } catch (error) {
    loadError.value = true
    handleError(error, 'No pudimos cargar los registros del paciente')
  }
}

const onPatientSelected = patient => {
  selectedPatient.value = patient ?? null
  showPatientModal.value = false

  if (!selectedPatient.value) return

  activeSpecialty.value = availableDefinitions.value[0]?.key ?? ''
  loadRecords()
}

const openCreate = () => {
  if (!selectedPatient.value || !activeDefinition.value) return

  selectedRecord.value = null
  selectedRecordSpecialty.value = activeSpecialty.value
  isEdit.value = false
  showForm.value = true
}

const viewRecord = (record, specialty) => {
  selectedRecord.value = record
  selectedRecordSpecialty.value = specialty
  showDetail.value = true
}

const editRecord = (record, specialty) => {
  showDetail.value = false
  selectedRecord.value = record
  selectedRecordSpecialty.value = specialty
  isEdit.value = true
  showForm.value = true
}

const closeForm = () => {
  showForm.value = false
  selectedRecord.value = null
  isEdit.value = false
}

const closeDetail = () => {
  showDetail.value = false
  selectedRecord.value = null
}

const onSaved = saved => {
  const specialty = saved?.specialty ?? selectedRecordSpecialty.value

  closeForm()

  if (availableDefinitions.value.some(definition => definition.key === specialty)) {
    activeSpecialty.value = specialty
  }

  loadRecords()
}

const requestDelete = async (record, specialty) => {
  const definition = getSpecialtyDefinition(specialty)
  const ok = await confirm({
    title: 'Eliminar registro',
    message: `¿Eliminar el registro de ${definition?.label ?? 'especialidad'}? Esta acción no se puede deshacer.`,
    confirmText: 'Eliminar',
    variant: 'danger'
  })

  if (!ok) return

  try {
    await deleteRecord(record.id, specialty)
    toast.success('Registro eliminado')
    await loadRecords()
  } catch (error) {
    handleError(error, 'No pudimos eliminar el registro')
  }
}

const goBack = () => {
  router.back()
}

let recordsChannel = null

const relayRecordEvent = async (event, prefix) => {
  if (!selectedPatient.value || event?.record?.patient_id !== selectedPatient.value.id) return

  await loadRecords()

  const definition = getSpecialtyDefinition(event.specialty)
  toast.success(`${prefix} ${definition?.label ?? 'especialidad'}`)
}

const subscribeToRecords = () => {
  try {
    recordsChannel = channel('specialty-records')
  } catch {
    recordsChannel = null
  }

  if (typeof recordsChannel?.listen !== 'function') return

  recordsChannel
    .listen('.specialty-record.created', event => relayRecordEvent(event, 'Nuevo registro de'))
    .listen('.specialty-record.updated', event =>
      relayRecordEvent(event, 'Registro actualizado de')
    )
}

onMounted(() => {
  activeSpecialty.value = availableDefinitions.value[0]?.key ?? ''
  subscribeToRecords()
})

onUnmounted(() => {
  try {
    echo?.leave?.('specialty-records')
  } catch {
    recordsChannel = null
  }
})
</script>
