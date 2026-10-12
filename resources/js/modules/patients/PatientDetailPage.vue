<template>
  <AppLayout>
    <div class="space-y-6">
      <!-- Hero band: the login surface vocabulary + the page's primary edit CTA. -->
      <header
        ref="heroSection"
        data-patient-detail-hero
        data-reveal="hero"
        class="relative overflow-hidden p-6 md:p-8"
        style="
          background: var(--color-canvas);
          border: 1px solid var(--color-hairline);
          border-radius: var(--radius-panel);
          box-shadow: var(--elevation-2);
        "
        :style="revealStyle('--spring-patient-detail-hero-o')"
      >
        <DentalMotifLayer variant="hero" data-hero-motif />
        <div class="relative flex flex-wrap items-center justify-between gap-3">
          <Breadcrumbs :items="breadcrumbs" :show-home="false" size="sm" variant="minimal" />
          <div
            class="flex flex-wrap w-full sm:w-auto items-center justify-end gap-2 sm:gap-3"
            data-hero-actions
          >
            <UiButton
              variant="secondary"
              size="sm"
              class="flex-none whitespace-nowrap"
              data-export-button
              :disabled="exporting"
              @click="exportPatientFile"
            >
              <template #icon-left>
                <ArrowDownTrayIcon class="w-4 h-4" aria-hidden="true" />
              </template>
              Exportar
            </UiButton>
            <UiButton
              v-if="can.editPatient?.value"
              variant="primary"
              size="sm"
              class="flex-none whitespace-nowrap"
              data-edit-patient-button
              @click="editPatient"
            >
              <template #icon-left>
                <PencilSquareIcon class="w-4 h-4" aria-hidden="true" />
              </template>
              Editar
            </UiButton>
          </div>
        </div>
        <p class="page-title relative mt-3 text-4xl font-semibold text-label">
          {{ patient?.first_name }} {{ patient?.last_name }}
        </p>
        <p
          class="relative mt-2 flex flex-wrap items-center gap-x-3 gap-y-1 text-base text-theme-secondary"
        >
          <span
            v-if="patient?.document_number"
            data-patient-identity="document"
            class="tabular-nums"
          >
            DNI {{ patient.document_number }}
          </span>
          <span v-if="patientAge !== null" data-patient-identity="age" class="tabular-nums">
            {{ patientAge }} años
          </span>
          <UiBadge
            v-if="patient"
            data-patient-identity="status"
            :variant="patient.is_active ? 'success' : 'error'"
          >
            {{ patient.is_active ? 'Activo' : 'Inactivo' }}
          </UiBadge>
        </p>
      </header>

      <!-- Summary block: the contact band that follows the hero. -->
      <section
        ref="summarySection"
        data-patient-detail-block="summary"
        data-reveal="summary"
        class="space-y-4"
        :style="revealStyle('--spring-patient-detail-summary-o')"
      >
        <div class="flex items-baseline gap-3">
          <h2 class="text-xl font-semibold text-label">Resumen del paciente</h2>
          <p class="text-sm text-theme-secondary">Contacto directo de la ficha</p>
        </div>
        <UiCard variant="glass">
          <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <div>
              <p class="text-xs font-medium text-theme-secondary uppercase">Email</p>
              <p class="mt-1 text-sm text-label">
                {{ patient?.email || 'No registrado' }}
              </p>
            </div>
            <div>
              <p class="text-xs font-medium text-theme-secondary uppercase">Teléfono</p>
              <p class="mt-1 text-sm text-label tabular-nums">
                {{ patient?.phone || 'No registrado' }}
              </p>
            </div>
            <div>
              <p class="text-xs font-medium text-theme-secondary uppercase">Fecha de Nacimiento</p>
              <p class="mt-1 text-sm text-label tabular-nums">
                {{ formatDate(patient?.birth_date) }}
              </p>
            </div>
          </div>
        </UiCard>
      </section>

      <!-- Work block: the tabbed clinical record (page protagonist). -->
      <section
        ref="workSection"
        data-patient-detail-block="work"
        data-reveal="work"
        class="space-y-4"
        :style="revealStyle('--spring-patient-detail-work-o')"
      >
        <div class="flex items-baseline gap-3">
          <h2 class="text-2xl font-semibold text-label">Expediente clínico</h2>
          <p class="text-sm text-theme-secondary">
            Planes, presupuestos, historias y auditoría del paciente
          </p>
        </div>

        <UiTabs
          v-model="activeTab"
          :tabs="tabs.map(t => ({ id: t.id, label: t.name, icon: t.icon }))"
          class="min-h-[400px]"
        >
          <template #data>
            <!-- Datos del Paciente -->
            <PatientTabPanel
              state="data"
              :loading="patientLoading"
              loading-text="Cargando paciente..."
              :error="patientError"
              error-title="No pudimos cargar el paciente"
              error-description="Revisa tu conexión e inténtalo de nuevo."
              @retry="loadPatient"
            >
              <div class="space-y-6">
                <div>
                  <h3 class="text-lg font-semibold text-theme-primary mb-4">
                    Información Personal
                  </h3>
                  <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                      <p class="block text-sm font-medium text-theme-primary mb-1">
                        Nombre Completo
                      </p>
                      <p class="text-theme-primary">
                        {{ patient?.first_name }} {{ patient?.last_name }}
                      </p>
                    </div>
                    <div>
                      <p class="block text-sm font-medium text-theme-primary mb-1">Email</p>
                      <p class="text-theme-primary">
                        {{ patient?.email }}
                      </p>
                    </div>
                    <div>
                      <p class="block text-sm font-medium text-theme-primary mb-1">Teléfono</p>
                      <p class="text-theme-primary">
                        {{ patient?.phone }}
                      </p>
                    </div>
                    <div>
                      <p class="block text-sm font-medium text-theme-primary mb-1">
                        Fecha de Nacimiento
                      </p>
                      <p class="text-theme-primary">
                        {{ formatDate(patient?.birth_date) }}
                      </p>
                    </div>
                    <div>
                      <p class="block text-sm font-medium text-theme-primary mb-1">Género</p>
                      <p class="text-theme-primary">
                        {{ patient?.gender }}
                      </p>
                    </div>
                    <div>
                      <p class="block text-sm font-medium text-theme-primary mb-1">Dirección</p>
                      <p class="text-theme-primary">
                        {{ patient?.address || 'No especificada' }}
                      </p>
                    </div>
                  </div>
                </div>
                <div v-if="patient?.medical_history">
                  <h3 class="text-lg font-semibold text-theme-primary mb-4">Historial Médico</h3>
                  <p class="text-theme-primary">
                    {{ patient.medical_history }}
                  </p>
                </div>
                <div v-if="patient?.notes">
                  <h3 class="text-lg font-semibold text-theme-primary mb-4">Notas</h3>
                  <p class="text-theme-primary">
                    {{ patient.notes }}
                  </p>
                </div>
              </div>
            </PatientTabPanel>
          </template>

          <template #treatment-plans>
            <!-- Planes de Tratamiento -->
            <PatientTabPanel
              state="treatment-plans"
              :loading="treatmentPlansLoading"
              loading-text="Cargando planes..."
              :error="treatmentPlansError"
              error-title="No pudimos cargar los planes"
              error-description="Revisa tu conexión e inténtalo de nuevo."
              :empty="treatmentPlans.length === 0"
              empty-title="No hay planes de tratamiento"
              empty-description="Este paciente no tiene planes de tratamiento registrados"
              @retry="loadTreatmentPlans"
            >
              <template #header>
                <h3 class="text-lg font-semibold text-theme-primary">Planes de Tratamiento</h3>
                <UiButton
                  v-if="can.createTreatmentPlan?.value"
                  variant="secondary"
                  class="flex items-center gap-2"
                  @click="createTreatmentPlan"
                >
                  <template #icon-left>
                    <PlusIcon class="w-4 h-4" aria-hidden="true" />
                  </template>
                  Nuevo Plan
                </UiButton>
              </template>
              <template #empty-action>
                <UiButton
                  v-if="can.createTreatmentPlan?.value"
                  variant="secondary"
                  class="flex items-center gap-2"
                  @click="createTreatmentPlan"
                >
                  <template #icon-left>
                    <PlusIcon class="w-4 h-4" aria-hidden="true" />
                  </template>
                  Crear Primer Plan
                </UiButton>
              </template>
              <div class="space-y-4">
                <div
                  v-for="plan in treatmentPlans"
                  :key="plan.id"
                  class="border border-theme rounded-lg p-4 hover:bg-theme-surface transition-colors"
                >
                  <div class="flex justify-between items-start">
                    <div>
                      <h4 class="font-semibold text-theme-primary">
                        {{ plan.title }}
                      </h4>
                      <p class="text-sm text-theme-secondary mt-1">
                        {{ plan.description }}
                      </p>
                      <div class="flex items-center gap-4 mt-2 text-sm text-theme-secondary">
                        <span>
                          Estado:
                          <UiBadge :variant="getStatusVariant(plan.status)">
                            {{ plan.status }}
                          </UiBadge>
                        </span>
                        <span>Costo: S/ {{ plan.total_cost }}</span>
                        <span>Fecha: {{ formatDate(plan.created_at) }}</span>
                      </div>
                    </div>
                    <div class="flex gap-2">
                      <UiButton variant="ghost" size="sm" @click="viewTreatmentPlan(plan.id)">
                        Ver
                      </UiButton>
                      <UiButton
                        v-if="can.editTreatmentPlan?.value"
                        variant="ghost"
                        size="sm"
                        @click="editTreatmentPlan(plan.id)"
                      >
                        Editar
                      </UiButton>
                    </div>
                  </div>
                </div>
              </div>
            </PatientTabPanel>
          </template>

          <template #quotations>
            <!-- Presupuestos -->
            <PatientTabPanel
              state="quotations"
              :loading="quotationsLoading"
              loading-text="Cargando presupuestos..."
              :error="quotationsError"
              error-title="No pudimos cargar los presupuestos"
              error-description="Revisa tu conexión e inténtalo de nuevo."
              :empty="quotations.length === 0"
              empty-title="No hay presupuestos"
              empty-description="Este paciente no tiene presupuestos registrados"
              @retry="loadQuotations"
            >
              <template #header>
                <h3 class="text-lg font-semibold text-theme-primary">Presupuestos</h3>
                <UiButton
                  v-if="can.createQuotation?.value"
                  variant="secondary"
                  class="flex items-center gap-2"
                  @click="createQuotation"
                >
                  <template #icon-left>
                    <PlusIcon class="w-4 h-4" aria-hidden="true" />
                  </template>
                  Nuevo Presupuesto
                </UiButton>
              </template>
              <template #empty-action>
                <UiButton
                  v-if="can.createQuotation?.value"
                  variant="secondary"
                  class="flex items-center gap-2"
                  @click="createQuotation"
                >
                  <template #icon-left>
                    <PlusIcon class="w-4 h-4" aria-hidden="true" />
                  </template>
                  Crear Primer Presupuesto
                </UiButton>
              </template>
              <div class="space-y-4">
                <div
                  v-for="quotation in quotations"
                  :key="quotation.id"
                  class="border border-theme rounded-lg p-4 hover:bg-theme-surface transition-colors"
                >
                  <div class="flex justify-between items-start">
                    <div>
                      <h4 class="font-semibold text-theme-primary">
                        {{ quotation.quotation_number }}
                      </h4>
                      <p class="text-sm text-theme-secondary mt-1">
                        {{ quotation.notes }}
                      </p>
                      <div class="flex items-center gap-4 mt-2 text-sm text-theme-secondary">
                        <span>
                          Estado:
                          <UiBadge :variant="getQuotationStatusVariant(quotation.status)">
                            {{ quotation.status }}
                          </UiBadge>
                        </span>
                        <span>Total: S/ {{ quotation.total_amount }}</span>
                        <span>Fecha: {{ formatDate(quotation.quotation_date) }}</span>
                      </div>
                    </div>
                    <div class="flex gap-2">
                      <UiButton variant="ghost" size="sm" @click="viewQuotation(quotation.id)">
                        Ver
                      </UiButton>
                      <UiButton
                        v-if="can.downloadQuotationPDF?.value"
                        variant="ghost"
                        size="sm"
                        @click="downloadQuotationPDF(quotation.id)"
                      >
                        PDF
                      </UiButton>
                    </div>
                  </div>
                </div>
              </div>
            </PatientTabPanel>
          </template>

          <template #medical-records>
            <!-- Historia Clínica -->
            <PatientTabPanel
              state="medical-records"
              :loading="medicalRecordsLoading"
              loading-text="Cargando historias clínicas..."
              :error="medicalRecordsError"
              error-title="No pudimos cargar las historias clínicas"
              error-description="Revisa tu conexión e inténtalo de nuevo."
              :empty="medicalRecords.length === 0"
              empty-title="No hay historias clínicas"
              empty-description="Este paciente no tiene historias clínicas registradas"
              @retry="loadMedicalRecords"
            >
              <template #header>
                <h3 class="text-lg font-semibold text-theme-primary">Historia Clínica</h3>
                <UiButton
                  v-if="can.createMedicalRecord?.value"
                  variant="secondary"
                  class="flex items-center gap-2"
                  @click="createMedicalRecord"
                >
                  <template #icon-left>
                    <PlusIcon class="w-4 h-4" aria-hidden="true" />
                  </template>
                  Nueva Historia
                </UiButton>
              </template>
              <template #empty-action>
                <UiButton
                  v-if="can.createMedicalRecord?.value"
                  variant="secondary"
                  class="flex items-center gap-2"
                  @click="createMedicalRecord"
                >
                  <template #icon-left>
                    <PlusIcon class="w-4 h-4" aria-hidden="true" />
                  </template>
                  Crear Primera Historia
                </UiButton>
              </template>
              <div class="space-y-4">
                <div
                  v-for="record in medicalRecords"
                  :key="record.id"
                  class="border border-theme rounded-lg p-4 hover:bg-theme-surface transition-colors"
                >
                  <div class="flex justify-between items-start">
                    <div>
                      <h4 class="font-semibold text-theme-primary">
                        {{ record.record_number || `HC-${record.id}` }}
                      </h4>
                      <p class="text-sm text-theme-secondary mt-1">
                        {{ record.chief_complaint }}
                      </p>
                      <div class="flex items-center gap-4 mt-2 text-sm text-theme-secondary">
                        <span>Primera visita: {{ formatDate(record.first_visit_date) }}</span>
                        <span>
                          Estado:
                          <UiBadge :variant="record.is_active ? 'success' : 'error'">
                            {{ record.is_active ? 'Activa' : 'Inactiva' }}
                          </UiBadge>
                        </span>
                      </div>
                    </div>
                    <div class="flex gap-2">
                      <UiButton variant="ghost" size="sm" @click="viewMedicalRecord(record.id)">
                        Ver
                      </UiButton>
                      <UiButton
                        v-if="can.editMedicalRecord?.value"
                        variant="ghost"
                        size="sm"
                        @click="editMedicalRecord(record.id)"
                      >
                        Editar
                      </UiButton>
                    </div>
                  </div>
                </div>
              </div>
            </PatientTabPanel>
          </template>

          <template #specialties>
            <!-- Especialidades -->
            <PatientTabPanel
              state="specialties"
              :loading="specialtyRecordsLoading"
              loading-text="Cargando registros de especialidades..."
              :error="specialtyRecordsError"
              error-title="No pudimos cargar los registros de especialidades"
              error-description="Revisa tu conexión e inténtalo de nuevo."
              :empty="specialtyRecords.length === 0"
              empty-title="No hay registros de especialidades"
              empty-description="Este paciente no tiene registros de especialidades"
              @retry="loadSpecialtyRecords"
            >
              <template #header>
                <h3 class="text-lg font-semibold text-theme-primary">
                  Registros de Especialidades
                </h3>
                <UiButton
                  v-if="can.createSpecialtyRecord?.value"
                  variant="secondary"
                  class="flex items-center gap-2"
                  @click="createSpecialtyRecord"
                >
                  <template #icon-left>
                    <PlusIcon class="w-4 h-4" aria-hidden="true" />
                  </template>
                  Nuevo Registro
                </UiButton>
              </template>
              <template #empty-action>
                <UiButton
                  v-if="can.createSpecialtyRecord?.value"
                  variant="secondary"
                  class="flex items-center gap-2"
                  @click="createSpecialtyRecord"
                >
                  <template #icon-left>
                    <PlusIcon class="w-4 h-4" aria-hidden="true" />
                  </template>
                  Crear Primer Registro
                </UiButton>
              </template>
              <div class="space-y-4">
                <div
                  v-for="record in specialtyRecords"
                  :key="record.id"
                  class="border border-theme rounded-lg p-4 hover:bg-theme-surface transition-colors"
                >
                  <div class="flex justify-between items-start">
                    <div>
                      <h4 class="font-semibold text-theme-primary">
                        {{ getSpecialtyName(record.specialty_type) }}
                      </h4>
                      <p class="text-sm text-theme-secondary mt-1">
                        {{ record.description || 'Sin descripción' }}
                      </p>
                      <div class="flex items-center gap-4 mt-2 text-sm text-theme-secondary">
                        <span>Fecha: {{ formatDate(record.created_at) }}</span>
                        <span>Especialidad: {{ getSpecialtyName(record.specialty_type) }}</span>
                      </div>
                    </div>
                    <div class="flex gap-2">
                      <UiButton
                        variant="ghost"
                        size="sm"
                        @click="viewSpecialtyRecord(record.id, record.specialty_type)"
                      >
                        Ver
                      </UiButton>
                      <UiButton
                        v-if="can.editSpecialtyRecord?.value"
                        variant="ghost"
                        size="sm"
                        @click="editSpecialtyRecord(record.id, record.specialty_type)"
                      >
                        Editar
                      </UiButton>
                    </div>
                  </div>
                </div>
              </div>
            </PatientTabPanel>
          </template>

          <template #audit>
            <!-- Historial de Auditoría -->
            <PatientTabPanel
              state="audit"
              :loading="auditLogsLoading"
              loading-text="Cargando historial de auditoría..."
              :empty="auditLogs.length === 0"
              empty-title="No hay historial de auditoría"
              empty-description="Este paciente no tiene registros de auditoría."
              @retry="loadAuditLogs"
            >
              <div class="space-y-4">
                <div>
                  <h3 class="text-lg font-semibold text-theme-primary mb-4">
                    Historial de Auditoría
                  </h3>
                  <UiCard
                    v-for="log in auditLogs"
                    :key="log.id"
                    variant="glass"
                    class="hover:bg-theme-surface transition-colors"
                  >
                    <div class="flex justify-between items-start">
                      <div class="flex-1">
                        <div class="flex items-center gap-2 mb-2">
                          <UiBadge variant="info">
                            {{ formatAction(log.action) }}
                          </UiBadge>
                          <span class="text-sm text-theme-secondary">
                            por {{ log.user?.name || 'Sistema' }}
                          </span>
                        </div>
                        <p class="text-sm text-theme-secondary mb-2">
                          {{ formatDate(log.created_at) }}
                        </p>
                        <div v-if="log.old_values && log.new_values" class="mt-2 text-sm">
                          <p class="font-medium text-theme-primary mb-1">Cambios realizados:</p>
                          <div
                            v-if="
                              getChangesSummary(log) &&
                              Object.keys(getChangesSummary(log)).length > 0
                            "
                            class="text-theme-secondary space-y-1"
                          >
                            <div
                              v-for="(change, field) in getChangesSummary(log)"
                              :key="field"
                              class="pl-2 border-l-2 border-hairline"
                            >
                              <p class="font-medium text-theme-primary">{{ change.field }}:</p>
                              <p class="text-xs">
                                De:
                                <span class="text-red-500">{{ change.old }}</span>
                              </p>
                              <p class="text-xs">
                                A:
                                <span class="text-green-500">{{ change.new }}</span>
                              </p>
                            </div>
                          </div>
                          <div v-else class="text-theme-secondary italic">
                            Sin cambios registrados
                          </div>
                        </div>
                        <div
                          v-else-if="log.action === 'patient_created'"
                          class="mt-2 text-sm text-theme-secondary"
                        >
                          Paciente creado en el sistema.
                        </div>
                        <div
                          v-else-if="log.action === 'patient_deleted'"
                          class="mt-2 text-sm text-theme-secondary"
                        >
                          Paciente eliminado del sistema.
                        </div>
                      </div>
                    </div>
                  </UiCard>
                </div>
              </div>
            </PatientTabPanel>
          </template>
        </UiTabs>
      </section>
    </div>

    <!-- Edit Patient Modal -->
    <PatientFormModal
      v-if="showEditModal"
      mode="edit"
      :patient="patient"
      @close="cancelEdit"
      @saved="handlePatientSaved"
    />
  </AppLayout>
</template>

<script>
import { ref, computed, onMounted, onUnmounted, watch, nextTick } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import {
  AcademicCapIcon,
  ArrowDownTrayIcon,
  ClipboardDocumentListIcon,
  ClockIcon,
  DocumentTextIcon,
  HeartIcon,
  PencilSquareIcon,
  PlusIcon,
  UserIcon
} from '@heroicons/vue/24/outline'
import { useApi } from '../../composables/useApi'
import { usePermissions } from '../../composables/usePermissions'
import { useToast } from '../../composables/useToast'
import { useEcho } from '../../composables/useEcho'
import { useAuditLogs } from '../../composables/useAuditLogs'
import { useSpring } from '../../composables/useSpring'
import { prefersReducedMotion } from '../../composables/useSpringMath'
import AppLayout from '../../components/layout/AppLayout.vue'
import Breadcrumbs from '../../components/ui/Breadcrumbs.vue'
import DentalMotifLayer from '../../components/ui/DentalMotifLayer.vue'
import UiCard from '../../components/ui/Card.vue'
import UiButton from '../../components/ui/Button.vue'
import UiBadge from '../../components/ui/Badge.vue'
import UiTabs from '../../components/ui/Tabs.vue'
import PatientFormModal from './PatientFormModal.vue'
import PatientTabPanel from './PatientTabPanel.vue'

export default {
  name: 'PatientDetailPage',
  components: {
    AppLayout,
    ArrowDownTrayIcon,
    PencilSquareIcon,
    PlusIcon,
    Breadcrumbs,
    DentalMotifLayer,
    UiCard,
    UiButton,
    UiBadge,
    UiTabs,
    PatientFormModal,
    PatientTabPanel
  },
  setup() {
    const route = useRoute()
    const router = useRouter()
    const { get } = useApi()
    const { can } = usePermissions()
    const toast = useToast()
    const { channel, echo } = useEcho()
    const {
      loading: auditLogsLoading,
      auditLogs,
      getPatientAuditLogs,
      formatAction,
      getChangesSummary
    } = useAuditLogs()

    // State
    const patient = ref(null)
    const patientLoading = ref(true)
    const patientError = ref(false)
    const activeTab = ref('data')
    const treatmentPlans = ref([])
    const quotations = ref([])
    const medicalRecords = ref([])
    const specialtyRecords = ref([])
    const treatmentPlansLoading = ref(false)
    const quotationsLoading = ref(false)
    const medicalRecordsLoading = ref(false)
    const specialtyRecordsLoading = ref(false)
    const treatmentPlansError = ref(false)
    const quotationsError = ref(false)
    const medicalRecordsError = ref(false)
    const specialtyRecordsError = ref(false)
    const exporting = ref(false)
    const showEditModal = ref(false)

    const breadcrumbs = [{ to: '/patients', label: 'Pacientes' }]

    const patientAge = computed(() => {
      const current = patient.value
      if (!current) return null
      if (typeof current.age === 'number') return current.age
      if (!current.birth_date) return null
      const birth = new Date(current.birth_date)
      if (Number.isNaN(birth.getTime())) return null
      const diff = Date.now() - birth.getTime()
      return Math.max(0, Math.floor(diff / (365.25 * 24 * 60 * 60 * 1000)))
    })

    const tabs = [
      {
        id: 'data',
        name: 'Datos',
        icon: UserIcon
      },
      {
        id: 'treatment-plans',
        name: 'Planes',
        icon: ClipboardDocumentListIcon
      },
      {
        id: 'quotations',
        name: 'Presupuestos',
        icon: DocumentTextIcon
      },
      {
        id: 'medical-records',
        name: 'Historia Clínica',
        icon: HeartIcon
      },
      {
        id: 'specialties',
        name: 'Especialidades',
        icon: AcademicCapIcon
      },
      {
        id: 'audit',
        name: 'Historial',
        icon: ClockIcon
      }
    ]

    // Methods
    const loadPatient = async () => {
      patientLoading.value = true
      patientError.value = false
      try {
        const response = await get(`/api/patients/${route.params.id}`)
        patient.value = response.data
      } catch (error) {
        patientError.value = true
        toast.error('Error al cargar el paciente')
      } finally {
        patientLoading.value = false
      }
    }

    const loadTreatmentPlans = async () => {
      if (!patient.value) return
      treatmentPlansLoading.value = true
      treatmentPlansError.value = false
      try {
        const response = await get(`/api/treatment-plans?patient_id=${patient.value.id}`)
        treatmentPlans.value = response.data
      } catch (error) {
        treatmentPlansError.value = true
      } finally {
        treatmentPlansLoading.value = false
      }
    }

    const loadQuotations = async () => {
      if (!patient.value) return
      quotationsLoading.value = true
      quotationsError.value = false
      try {
        const response = await get(`/api/quotations?patient_id=${patient.value.id}`)
        quotations.value = response.data
      } catch (error) {
        quotationsError.value = true
      } finally {
        quotationsLoading.value = false
      }
    }

    const loadMedicalRecords = async () => {
      if (!patient.value) return
      medicalRecordsLoading.value = true
      medicalRecordsError.value = false
      try {
        const response = await get(`/api/medical-records?patient_id=${patient.value.id}`)
        medicalRecords.value = response.data
      } catch (error) {
        medicalRecordsError.value = true
      } finally {
        medicalRecordsLoading.value = false
      }
    }

    const loadSpecialtyRecords = async () => {
      if (!patient.value) return
      specialtyRecordsLoading.value = true
      specialtyRecordsError.value = false
      try {
        const response = await get(`/api/specialty-records/patient/${patient.value.id}/all`)
        specialtyRecords.value = response.data
      } catch (error) {
        specialtyRecordsError.value = true
      } finally {
        specialtyRecordsLoading.value = false
      }
    }

    const loadAuditLogs = async () => {
      if (!patient.value) return
      await getPatientAuditLogs(patient.value.id)
    }

    const formatDate = date => {
      if (!date) return 'No especificada'
      return new Date(date).toLocaleDateString('es-ES')
    }

    const getStatusVariant = status => {
      const variants = {
        draft: 'secondary',
        pending: 'warning',
        approved: 'success',
        in_progress: 'primary',
        completed: 'success',
        cancelled: 'error'
      }
      return variants[status] || 'secondary'
    }

    const getQuotationStatusVariant = status => {
      const variants = {
        pending: 'warning',
        approved: 'success',
        rejected: 'error',
        expired: 'secondary'
      }
      return variants[status] || 'secondary'
    }

    const getSpecialtyName = specialty => {
      const names = {
        implantology: 'Implantología',
        orthodontics: 'Ortodoncia',
        endodontics: 'Endodoncia',
        rehabilitation: 'Rehabilitación',
        oral_surgery: 'Cirugía Oral'
      }
      return names[specialty] || specialty
    }

    const getAuditActionVariant = action => {
      const variants = {
        patient_created: 'success',
        patient_updated: 'warning',
        patient_deleted: 'error'
      }
      return variants[action] || 'secondary'
    }

    const editPatient = () => {
      if (!patient.value) return
      showEditModal.value = true
    }

    const cancelEdit = () => {
      showEditModal.value = false
    }

    const handlePatientSaved = async () => {
      await loadPatient()
      toast.success('Paciente actualizado exitosamente')
    }

    const exportPatientFile = async (format = 'pdf') => {
      if (!patient.value) return

      // Ensure format is a valid string
      const exportFormat = format && typeof format === 'string' ? format.toLowerCase() : 'pdf'

      if (exportFormat !== 'pdf' && exportFormat !== 'zip') {
        toast.error('Formato de exportación no válido. Use PDF o ZIP.')
        return
      }

      try {
        exporting.value = true
        const token = localStorage.getItem('auth_token')
        // Use the same base URL as useApi
        const baseUrl = import.meta.env.VITE_APP_URL || window.location.origin
        const url = `${baseUrl}/api/patients/${patient.value.id}/export?format=${exportFormat}`

        const response = await fetch(url, {
          method: 'GET',
          headers: {
            Authorization: `Bearer ${token}`,
            Accept: exportFormat === 'pdf' ? 'application/pdf' : 'application/zip'
          }
        })

        if (!response.ok) {
          const errorText = await response.text().catch(() => 'Error desconocido')
          throw new Error(errorText || 'Error al exportar ficha del paciente')
        }

        const blob = await response.blob()
        const downloadUrl = window.URL.createObjectURL(blob)
        const link = document.createElement('a')
        link.href = downloadUrl
        link.download = `ficha_paciente_${patient.value.id}_${new Date().toISOString().split('T')[0]}.${exportFormat}`
        document.body.appendChild(link)
        link.click()
        document.body.removeChild(link)
        window.URL.revokeObjectURL(downloadUrl)

        toast.success(`Ficha exportada exitosamente como ${exportFormat.toUpperCase()}`)
      } catch (error) {
        toast.error('Error al exportar ficha del paciente')
      } finally {
        exporting.value = false
      }
    }

    const createTreatmentPlan = () => {
      router.push(`/treatment-plans?patient_id=${patient.value.id}`)
    }

    const viewTreatmentPlan = id => {
      router.push(`/treatment-plans/${id}`)
    }

    const editTreatmentPlan = id => {
      router.push(`/treatment-plans/${id}?edit=true`)
    }

    const createQuotation = () => {
      router.push(`/quotations?patient_id=${patient.value.id}`)
    }

    const viewQuotation = id => {
      router.push(`/quotations/${id}`)
    }

    const downloadQuotationPDF = async id => {
      try {
        // Slice 01 / T-01.4 (API-012): window.open strips the Authorization
        // header in Sanctum cookie/session hybrid, causing a 401 on PDF
        // downloads. Use fetch + blob + temporary anchor click instead.
        const token = localStorage.getItem('auth_token')
        const baseUrl = import.meta.env.VITE_APP_URL || window.location.origin
        const url = `${baseUrl}/api/quotations/${id}/pdf`

        const response = await fetch(url, {
          method: 'GET',
          headers: {
            Authorization: `Bearer ${token}`,
            Accept: 'application/pdf'
          }
        })

        if (!response.ok) {
          throw new Error(`Error ${response.status} al descargar el PDF`)
        }

        const blob = await response.blob()
        const downloadUrl = window.URL.createObjectURL(blob)
        const link = document.createElement('a')
        link.href = downloadUrl
        link.download = `presupuesto_${id}_${new Date().toISOString().split('T')[0]}.pdf`
        document.body.appendChild(link)
        link.click()
        document.body.removeChild(link)
        window.URL.revokeObjectURL(downloadUrl)
      } catch (error) {
        toast.error('Error al descargar el PDF del presupuesto')
      }
    }

    const createMedicalRecord = () => {
      router.push(`/medical-records?patient_id=${patient.value.id}`)
    }

    const viewMedicalRecord = id => {
      router.push(`/medical-records/${id}`)
    }

    const editMedicalRecord = id => {
      router.push(`/medical-records/${id}?edit=true`)
    }

    const createSpecialtyRecord = () => {
      router.push(`/specialty-records?patient_id=${patient.value.id}`)
    }

    const viewSpecialtyRecord = (id, specialty) => {
      router.push(`/specialty-records/${id}?specialty=${specialty}`)
    }

    const editSpecialtyRecord = (id, specialty) => {
      router.push(`/specialty-records/${id}?edit=true&specialty=${specialty}`)
    }

    // One-shot staggered entrance (60ms) per section, mirroring the
    // dashboard pattern. revealStyle() renders the FINAL state whenever no
    // spring is attached (reduced motion, or a section mounting later).
    const heroSpring = useSpring({
      damping: 1.0,
      response: 0.35,
      cssVar: '--spring-patient-detail-hero-o'
    })
    const summarySpring = useSpring({
      damping: 1.0,
      response: 0.35,
      cssVar: '--spring-patient-detail-summary-o'
    })
    const workSpring = useSpring({
      damping: 1.0,
      response: 0.35,
      cssVar: '--spring-patient-detail-work-o'
    })

    const heroSection = ref(null)
    const summarySection = ref(null)
    const workSection = ref(null)

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
      if (workSection.value) workSpring.attach(workSection.value)

      setTimeout(() => heroSpring.set(1), 0)
      setTimeout(() => summarySpring.set(1), 60)
      setTimeout(() => workSpring.set(1), 120)
    }

    // WebSocket subscriptions
    let patientsChannel = null
    let treatmentPlansChannel = null
    let quotationsChannel = null
    let medicalRecordsChannel = null
    let specialtyRecordsChannel = null

    // Watch for tab changes to load data
    watch(activeTab, newTab => {
      if (newTab === 'audit' && patient.value) {
        loadAuditLogs()
      }
    })

    // Lifecycle
    onMounted(async () => {
      await loadPatient()
      if (patient.value) {
        await nextTick()
        playEntrance()

        await Promise.all([
          loadTreatmentPlans(),
          loadQuotations(),
          loadMedicalRecords(),
          loadSpecialtyRecords()
        ])

        // Load audit logs if audit tab is active
        if (activeTab.value === 'audit') {
          loadAuditLogs()
        }

        // Suscribirse a canales WebSocket para actualizaciones en tiempo real
        try {
          const patientId = patient.value.id

          // Canal de pacientes
          patientsChannel = channel('patients')
          if (patientsChannel) {
            patientsChannel.listen('.patient.updated', async e => {
              if (e.patient.id === patientId) {
                patient.value = e.patient
                toast.success('Datos del paciente actualizados')
              }
            })
          }

          // Canal de planes de tratamiento
          treatmentPlansChannel = channel('treatment-plans')
          if (treatmentPlansChannel) {
            treatmentPlansChannel
              .listen('.treatment-plan.created', async e => {
                if (e.treatment_plan.patient_id === patientId) {
                  await loadTreatmentPlans()
                  if (activeTab.value === 'treatment-plans') {
                    toast.success('Nuevo plan de tratamiento creado')
                  }
                }
              })
              .listen('.treatment-plan.updated', async e => {
                if (e.treatment_plan.patient_id === patientId) {
                  const index = treatmentPlans.value.findIndex(p => p.id === e.treatment_plan.id)
                  if (index !== -1) {
                    treatmentPlans.value[index] = e.treatment_plan
                  } else {
                    await loadTreatmentPlans()
                  }
                }
              })
              .listen('.treatment-plan.deleted', async e => {
                if (e.patient_id === patientId) {
                  treatmentPlans.value = treatmentPlans.value.filter(
                    p => p.id !== e.treatment_plan_id
                  )
                }
              })
          }

          // Canal de presupuestos
          quotationsChannel = channel('quotations')
          if (quotationsChannel) {
            quotationsChannel
              .listen('.quotation.created', async e => {
                if (e.quotation.patient_id === patientId) {
                  await loadQuotations()
                  if (activeTab.value === 'quotations') {
                    toast.success('Nuevo presupuesto creado')
                  }
                }
              })
              .listen('.quotation.updated', async e => {
                if (e.quotation.patient_id === patientId) {
                  const index = quotations.value.findIndex(q => q.id === e.quotation.id)
                  if (index !== -1) {
                    quotations.value[index] = e.quotation
                  } else {
                    await loadQuotations()
                  }
                }
              })
              .listen('.quotation.approved', async e => {
                if (e.quotation.patient_id === patientId) {
                  const index = quotations.value.findIndex(q => q.id === e.quotation.id)
                  if (index !== -1) {
                    quotations.value[index] = e.quotation
                  } else {
                    await loadQuotations()
                  }
                  if (activeTab.value === 'quotations') {
                    toast.success('Presupuesto aprobado')
                  }
                }
              })
          }

          // Canal de historias clínicas
          medicalRecordsChannel = channel('medical-records')
          if (medicalRecordsChannel) {
            medicalRecordsChannel
              .listen('.medical-record.created', async e => {
                if (e.medical_record.patient_id === patientId) {
                  await loadMedicalRecords()
                  if (activeTab.value === 'medical-records') {
                    toast.success('Nueva historia clínica creada')
                  }
                }
              })
              .listen('.medical-record.updated', async e => {
                if (e.medical_record.patient_id === patientId) {
                  const index = medicalRecords.value.findIndex(r => r.id === e.medical_record.id)
                  if (index !== -1) {
                    medicalRecords.value[index] = e.medical_record
                  } else {
                    await loadMedicalRecords()
                  }
                }
              })
              .listen('.clinical-evolution.created', async e => {
                if (e.evolution.medical_record?.patient_id === patientId) {
                  await loadMedicalRecords()
                  if (activeTab.value === 'medical-records') {
                    toast.success('Nueva evolución clínica agregada')
                  }
                }
              })
              .listen('.clinical-attachment.created', async e => {
                if (e.attachment.medical_record?.patient_id === patientId) {
                  await loadMedicalRecords()
                  if (activeTab.value === 'medical-records') {
                    toast.success('Nuevo adjunto agregado')
                  }
                }
              })
          }

          // Canal de registros de especialidades
          specialtyRecordsChannel = channel('specialty-records')
          if (specialtyRecordsChannel) {
            specialtyRecordsChannel
              .listen('.specialty-record.created', async e => {
                if (e.record.patient_id === patientId) {
                  await loadSpecialtyRecords()
                  if (activeTab.value === 'specialties') {
                    toast.success('Nuevo registro de especialidad creado')
                  }
                }
              })
              .listen('.specialty-record.updated', async e => {
                if (e.record.patient_id === patientId) {
                  const index = specialtyRecords.value.findIndex(r => r.id === e.record.id)
                  if (index !== -1) {
                    specialtyRecords.value[index] = e.record
                  } else {
                    await loadSpecialtyRecords()
                  }
                }
              })
          }
        } catch (error) {
          // Sin WebSocket la ficha sigue operativa vía fetch inicial
        }
      }
    })

    onUnmounted(() => {
      // Limpiar suscripciones WebSocket
      if (echo) {
        try {
          echo.leave('patients')
          echo.leave('treatment-plans')
          echo.leave('quotations')
          echo.leave('medical-records')
          echo.leave('specialty-records')
        } catch (e) {
          // Los canales ya estaban cerrados, nada que limpiar
        }
      }
    })

    return {
      patient,
      patientLoading,
      patientError,
      patientAge,
      breadcrumbs,
      activeTab,
      tabs,
      treatmentPlans,
      quotations,
      medicalRecords,
      specialtyRecords,
      treatmentPlansLoading,
      quotationsLoading,
      medicalRecordsLoading,
      specialtyRecordsLoading,
      treatmentPlansError,
      quotationsError,
      medicalRecordsError,
      specialtyRecordsError,
      exporting,
      showEditModal,
      can,
      loadPatient,
      loadTreatmentPlans,
      loadQuotations,
      loadMedicalRecords,
      loadSpecialtyRecords,
      loadAuditLogs,
      formatDate,
      getStatusVariant,
      getQuotationStatusVariant,
      getSpecialtyName,
      getAuditActionVariant,
      editPatient,
      cancelEdit,
      handlePatientSaved,
      exportPatientFile,
      auditLogs,
      auditLogsLoading,
      formatAction,
      getChangesSummary,
      createTreatmentPlan,
      viewTreatmentPlan,
      editTreatmentPlan,
      createQuotation,
      viewQuotation,
      downloadQuotationPDF,
      createMedicalRecord,
      viewMedicalRecord,
      editMedicalRecord,
      createSpecialtyRecord,
      viewSpecialtyRecord,
      editSpecialtyRecord,
      heroSection,
      summarySection,
      workSection,
      revealStyle
    }
  }
}
</script>
