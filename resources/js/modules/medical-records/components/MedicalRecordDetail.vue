<template>
  <UiModal model-value size="lg" title="Historia clínica" @close="emit('close')">
    <template #header>
      <div class="min-w-0" data-detail-header>
        <p class="text-xs font-medium uppercase tracking-wide text-theme-secondary">
          Historia clínica
        </p>
        <div class="mt-1 flex flex-wrap items-center gap-2">
          <p class="text-xl font-semibold text-label tabular-nums" data-detail-record-number>
            {{ recordNumberLabel }}
          </p>
          <StatusBadge :variant="statusVariant" size="sm" show-dot data-detail-status>
            {{ statusLabel }}
          </StatusBadge>
        </div>
        <p class="mt-1 text-sm text-theme-secondary">
          Primera visita
          <span class="tabular-nums" data-detail-first-visit>{{ firstVisitLabel }}</span>
        </p>
      </div>
      <UiButton
        variant="icon"
        size="sm"
        aria-label="Cerrar detalle"
        data-detail-close
        @click="emit('close')"
      >
        <template #icon-left>
          <XMarkIcon class="h-5 w-5" aria-hidden="true" />
        </template>
      </UiButton>
    </template>

    <div class="space-y-6 text-theme-primary" data-medical-record-detail>
      <section v-if="patientLabel" class="space-y-1" data-detail-section="patient">
        <h3 class="text-xs font-semibold uppercase tracking-wide text-theme-secondary">Paciente</h3>
        <p class="text-sm font-medium text-label">{{ patientLabel }}</p>
        <p v-if="patientMeta" class="text-sm text-theme-secondary tabular-nums">
          {{ patientMeta }}
        </p>
      </section>

      <UiSectionEmpty
        v-if="!hasClinicalContent"
        :motif="false"
        data-detail-empty
        title="Sin contenido clínico"
        description="Esta historia todavía no tiene antecedentes, examen ni diagnóstico. Complétala con el botón Editar."
      />

      <section v-if="vitalSignChips.length" class="space-y-2" data-detail-section="vital-signs">
        <h3 class="text-xs font-semibold uppercase tracking-wide text-theme-secondary">
          Signos vitales
        </h3>
        <div class="grid grid-cols-2 gap-2 sm:grid-cols-4">
          <div
            v-for="chip in vitalSignChips"
            :key="chip.key"
            class="rounded-ios px-3 py-2"
            style="border: 1px solid var(--color-hairline); background: var(--color-canvas)"
          >
            <p class="text-xs text-theme-secondary">{{ chip.label }}</p>
            <p class="text-sm font-semibold text-label tabular-nums">
              {{ chip.value }}{{ chip.suffix }}
            </p>
          </div>
        </div>
      </section>

      <section v-if="chiefComplaintLabel" class="space-y-1" data-detail-section="chief-complaint">
        <h3 class="text-xs font-semibold uppercase tracking-wide text-theme-secondary">
          Motivo de consulta
        </h3>
        <p class="text-sm whitespace-pre-line">{{ chiefComplaintLabel }}</p>
      </section>

      <section v-if="antecedentItems.length" class="space-y-3" data-detail-section="antecedents">
        <h3 class="text-xs font-semibold uppercase tracking-wide text-theme-secondary">
          Antecedentes
        </h3>
        <div class="space-y-2">
          <div
            v-for="item in antecedentItems"
            :key="item.key"
            :data-detail-field="item.key"
            class="space-y-1"
          >
            <p class="text-xs font-medium text-theme-secondary">{{ item.label }}</p>
            <p class="text-sm whitespace-pre-line">{{ item.value }}</p>
          </div>
        </div>
      </section>

      <section
        v-for="section in clinicalSections"
        :key="section.key"
        class="space-y-1"
        :data-detail-section="section.key"
      >
        <h3 class="text-xs font-semibold uppercase tracking-wide text-theme-secondary">
          {{ section.title }}
        </h3>
        <p class="text-sm whitespace-pre-line">{{ section.value }}</p>
      </section>
    </div>

    <template #footer>
      <UiButton
        v-if="canEdit"
        variant="secondary"
        size="sm"
        data-detail-action="edit"
        @click="emit('edit', record)"
      >
        <template #icon-left>
          <PencilIcon class="h-4 w-4" aria-hidden="true" />
        </template>
        Editar
      </UiButton>
      <UiButton
        v-if="canAddEvolution"
        variant="secondary"
        size="sm"
        data-detail-action="add-evolution"
        @click="emit('addEvolution', record)"
      >
        <template #icon-left>
          <PlusIcon class="h-4 w-4" aria-hidden="true" />
        </template>
        Agregar evolución
      </UiButton>
      <UiButton
        v-if="canUploadAttachment"
        variant="primary"
        size="sm"
        data-detail-action="upload-attachment"
        @click="emit('uploadAttachment', record)"
      >
        <template #icon-left>
          <DocumentPlusIcon class="h-4 w-4" aria-hidden="true" />
        </template>
        Subir archivo
      </UiButton>
    </template>
  </UiModal>
</template>

<script setup>
import { computed } from 'vue'
import { DocumentPlusIcon, PencilIcon, PlusIcon, XMarkIcon } from '@heroicons/vue/24/outline'
import UiModal from '@/components/ui/Modal.vue'
import UiButton from '@/components/ui/Button.vue'
import StatusBadge from '@/components/ui/StatusBadge.vue'
import UiSectionEmpty from '@/components/ui/UiSectionEmpty.vue'
import { usePermissions } from '@/composables/usePermissions'
import { formatDateOnly } from '@/utils/formatters'

const props = defineProps({
  record: {
    type: Object,
    required: true
  }
})

const emit = defineEmits(['close', 'edit', 'addEvolution', 'uploadAttachment'])

const { can } = usePermissions()

// `can` is an object of computed refs (decision D7), never a function.
const canEdit = computed(() => can.editMedicalRecord?.value ?? false)
const canAddEvolution = computed(() => can.addEvolution?.value ?? false)
const canUploadAttachment = computed(() => can.uploadAttachment?.value ?? false)

const VITAL_SIGNS = [
  { key: 'blood_pressure', label: 'Presión arterial', suffix: '' },
  { key: 'heart_rate', label: 'Frec. cardíaca', suffix: ' lpm' },
  { key: 'temperature', label: 'Temperatura', suffix: ' °C' },
  { key: 'respiratory_rate', label: 'Frec. respiratoria', suffix: ' rpm' }
]

const ANTECEDENTS = [
  { key: 'medical_history', label: 'Antecedentes médicos' },
  { key: 'dental_history', label: 'Antecedentes dentales' },
  { key: 'allergies', label: 'Alergias' },
  { key: 'medications', label: 'Medicamentos' },
  { key: 'systemic_conditions', label: 'Condiciones sistémicas' },
  { key: 'family_history', label: 'Antecedentes familiares' },
  { key: 'social_history', label: 'Antecedentes sociales' }
]

const CLINICAL_SECTIONS = [
  { key: 'clinical_examination', title: 'Examen clínico' },
  { key: 'diagnosis', title: 'Diagnóstico' },
  { key: 'treatment_plan', title: 'Plan de tratamiento' },
  { key: 'notes', title: 'Notas' }
]

const hasText = value => value !== null && value !== undefined && String(value).trim() !== ''

const recordNumberLabel = computed(() => props.record.record_number || 'Sin número asignado')
const firstVisitLabel = computed(() => formatDateOnly(props.record.first_visit_date) || 'Sin fecha')
const statusLabel = computed(() => (props.record.is_active ? 'Activa' : 'Inactiva'))
const statusVariant = computed(() => (props.record.is_active ? 'success' : 'neutral'))

const patientLabel = computed(() => {
  const { patient } = props.record

  if (!patient) return ''

  return `${patient.first_name ?? ''} ${patient.last_name ?? ''}`.trim()
})

const patientMeta = computed(() => {
  const { patient } = props.record

  if (!patient) return ''

  return [patient.document_number, patient.phone, patient.email].filter(hasText).join(' · ')
})

const vitalSignChips = computed(() => {
  const signs = props.record.vital_signs || {}

  return VITAL_SIGNS.map(item => ({ ...item, value: signs[item.key] })).filter(item =>
    hasText(item.value)
  )
})

const chiefComplaintLabel = computed(() => props.record.chief_complaint || '')

const antecedentItems = computed(() =>
  ANTECEDENTS.map(item => ({ ...item, value: props.record[item.key] })).filter(item =>
    hasText(item.value)
  )
)

const clinicalSections = computed(() =>
  CLINICAL_SECTIONS.map(item => ({ ...item, value: props.record[item.key] })).filter(item =>
    hasText(item.value)
  )
)

const hasClinicalContent = computed(
  () =>
    vitalSignChips.value.length > 0 ||
    antecedentItems.value.length > 0 ||
    clinicalSections.value.length > 0 ||
    hasText(chiefComplaintLabel.value)
)
</script>
