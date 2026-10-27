<template>
  <section data-evolution-timeline class="space-y-4">
    <div class="flex flex-wrap items-end justify-between gap-3">
      <div class="min-w-0">
        <h2 class="text-xl font-semibold text-label">Evoluciones</h2>
        <p class="mt-1 text-sm text-theme-secondary">
          El registro SOAP de cada consulta, de la más reciente a la más antigua
        </p>
        <p
          v-if="record"
          class="mt-1 text-xs text-theme-secondary tabular-nums"
          data-evolution-record
        >
          Historia {{ record.record_number }}
        </p>
      </div>
      <UiButton variant="secondary" size="sm" data-add-evolution @click="emit('add')">
        <template #icon-left>
          <PlusIcon class="w-4 h-4" aria-hidden="true" />
        </template>
        Nueva evolución
      </UiButton>
    </div>

    <LoadingSpinner v-if="loading" class="p-12" size="lg" text="Cargando evoluciones..." />

    <UiSectionEmpty
      v-else-if="evolutions.length === 0"
      data-state="empty-evolutions"
      title="Sin evoluciones registradas"
      description="Registra la primera evolución para dejar constancia del subjetivo, objetivo, evaluación y plan de la consulta."
      motif
    >
      <UiButton variant="primary" size="sm" data-add-evolution-empty @click="emit('add')">
        <template #icon-left>
          <PlusIcon class="w-4 h-4" aria-hidden="true" />
        </template>
        Nueva evolución
      </UiButton>
    </UiSectionEmpty>

    <ol
      v-else
      data-evolution-rail
      class="space-y-4 border-l border-[color:var(--color-hairline)] pl-5 md:pl-6"
    >
      <li
        v-for="evolution in evolutions"
        :key="evolution.id"
        :data-evolution-id="evolution.id"
        data-evolution-card
      >
        <UiCard variant="glass">
          <div class="flex flex-wrap items-start justify-between gap-3">
            <div class="min-w-0">
              <div class="flex flex-wrap items-center gap-x-2 gap-y-1">
                <p class="text-sm font-semibold text-label tabular-nums" data-evolution-date>
                  {{ formatEvolutionDate(evolution.evolution_date) }}
                </p>
                <span class="text-theme-secondary" aria-hidden="true">·</span>
                <p class="text-sm text-theme-secondary" data-evolution-specialty>
                  {{ specialtyLabel(evolution) }}
                </p>
                <StatusBadge
                  v-if="evolution.requires_follow_up"
                  variant="warning"
                  size="sm"
                  show-dot
                  label="Requiere seguimiento"
                  data-evolution-follow-up
                />
              </div>
              <p class="mt-1 text-xs text-theme-secondary" data-evolution-author>
                <span>{{ authorLabel(evolution) }}</span>
                <template v-if="evolution.requires_follow_up && evolution.follow_up_date">
                  <span aria-hidden="true">·</span>
                  <span data-evolution-follow-up-date class="tabular-nums">
                    Seguimiento {{ formatEvolutionDate(evolution.follow_up_date) }}
                  </span>
                </template>
              </p>
            </div>

            <div class="flex flex-wrap items-center gap-2">
              <UiButton
                v-if="can.addEvolution?.value"
                variant="ghost"
                size="sm"
                data-evolution-addendum
                @click="emit('addendum', evolution)"
              >
                <template #icon-left>
                  <PlusIcon class="w-4 h-4" aria-hidden="true" />
                </template>
                Addendum
              </UiButton>
            </div>
          </div>

          <!-- SOAP: four labelled blocks, each rendered only with content. -->
          <div class="mt-4 grid grid-cols-1 gap-3 md:grid-cols-2">
            <div
              v-if="evolution.subjective"
              data-soap-block="subjective"
              class="rounded-ios border border-[color:var(--color-hairline)] p-3"
            >
              <p class="text-xs font-semibold uppercase tracking-wide text-theme-secondary">
                Subjetivo
              </p>
              <p class="mt-1 text-sm text-theme-primary whitespace-pre-line">
                {{ evolution.subjective }}
              </p>
            </div>

            <div
              v-if="evolution.objective"
              data-soap-block="objective"
              class="rounded-ios border border-[color:var(--color-hairline)] p-3"
            >
              <p class="text-xs font-semibold uppercase tracking-wide text-theme-secondary">
                Objetivo
              </p>
              <p class="mt-1 text-sm text-theme-primary whitespace-pre-line">
                {{ evolution.objective }}
              </p>
            </div>

            <div
              v-if="evolution.assessment"
              data-soap-block="assessment"
              class="rounded-ios border border-[color:var(--color-hairline)] p-3"
            >
              <p class="text-xs font-semibold uppercase tracking-wide text-theme-secondary">
                Evaluación
              </p>
              <p class="mt-1 text-sm text-theme-primary whitespace-pre-line">
                {{ evolution.assessment }}
              </p>
            </div>

            <div
              v-if="evolution.plan"
              data-soap-block="plan"
              class="rounded-ios border border-[color:var(--color-hairline)] p-3"
            >
              <p class="text-xs font-semibold uppercase tracking-wide text-theme-secondary">Plan</p>
              <p class="mt-1 text-sm text-theme-primary whitespace-pre-line">
                {{ evolution.plan }}
              </p>
            </div>
          </div>

          <!-- Clinical rows: the course of the session and its instructions. -->
          <div v-if="hasClinicalDetail(evolution)" class="mt-4 space-y-3">
            <div v-if="evolution.procedures_performed" data-evolution-row="procedures">
              <p class="text-xs font-semibold uppercase tracking-wide text-theme-secondary">
                Procedimientos realizados
              </p>
              <p class="mt-1 text-sm text-theme-primary whitespace-pre-line">
                {{ evolution.procedures_performed }}
              </p>
            </div>

            <div v-if="evolution.materials_used" data-evolution-row="materials">
              <p class="text-xs font-semibold uppercase tracking-wide text-theme-secondary">
                Materiales utilizados
              </p>
              <p class="mt-1 text-sm text-theme-primary whitespace-pre-line">
                {{ evolution.materials_used }}
              </p>
            </div>

            <div v-if="evolution.prescriptions" data-evolution-row="prescriptions">
              <p class="text-xs font-semibold uppercase tracking-wide text-theme-secondary">
                Prescripciones
              </p>
              <p class="mt-1 text-sm text-theme-primary whitespace-pre-line">
                {{ evolution.prescriptions }}
              </p>
            </div>

            <div v-if="evolution.recommendations" data-evolution-row="recommendations">
              <p class="text-xs font-semibold uppercase tracking-wide text-theme-secondary">
                Recomendaciones
              </p>
              <p class="mt-1 text-sm text-theme-primary whitespace-pre-line">
                {{ evolution.recommendations }}
              </p>
            </div>
          </div>

          <!-- Vital signs: a chip grid, one chip per recorded measure. -->
          <div v-if="vitalSigns(evolution).length > 0" data-evolution-vitals class="mt-4">
            <p class="text-xs font-semibold uppercase tracking-wide text-theme-secondary">
              Signos vitales
            </p>
            <div class="mt-2 flex flex-wrap gap-2">
              <span
                v-for="sign in vitalSigns(evolution)"
                :key="sign.key"
                class="inline-flex items-center gap-1.5 rounded-full border border-[color:var(--color-hairline)] px-2.5 py-1 text-xs tabular-nums"
              >
                <span class="text-theme-secondary">{{ sign.label }}</span>
                <span class="font-semibold text-theme-primary">{{ sign.value }}</span>
              </span>
            </div>
          </div>

          <!-- Clinical measurements: the periodontal contract (issue #222). -->
          <div
            v-if="clinicalMeasurements(evolution).length > 0"
            data-evolution-measurements
            class="mt-4"
          >
            <p class="text-xs font-semibold uppercase tracking-wide text-theme-secondary">
              Mediciones clínicas
            </p>
            <div class="mt-2 flex flex-wrap gap-2">
              <span
                v-for="measure in clinicalMeasurements(evolution)"
                :key="measure.key"
                class="inline-flex items-center gap-1.5 rounded-full border border-[color:var(--color-hairline)] px-2.5 py-1 text-xs tabular-nums"
                :data-evolution-measurement="measure.key"
              >
                <span class="text-theme-secondary">{{ measure.label }}</span>
                <span class="font-semibold text-theme-primary">{{ measure.value }}</span>
              </span>
            </div>
          </div>

          <!-- Next appointment: instructions that outlive this session. -->
          <div
            v-if="evolution.next_appointment_notes"
            data-evolution-notes
            class="mt-4 rounded-ios bg-systemGray-50 p-3"
          >
            <p class="text-xs font-semibold uppercase tracking-wide text-theme-secondary">
              Notas para la próxima cita
            </p>
            <p class="mt-1 text-sm text-theme-secondary whitespace-pre-line">
              {{ evolution.next_appointment_notes }}
            </p>
          </div>
          <!-- Corrections: the original stays intact, the addendum is what
               changes the record (#292). -->
          <div v-if="evolution.addenda?.length" class="mt-4 space-y-2" data-evolution-addenda>
            <p class="text-xs font-semibold uppercase tracking-wide text-theme-secondary">
              Addenda
            </p>
            <div
              v-for="addendum in evolution.addenda"
              :key="addendum.id"
              class="rounded-ios border border-[color:var(--color-hairline)] p-3"
              data-addendum
              :data-addendum-id="addendum.id"
            >
              <p class="text-xs text-theme-secondary tabular-nums" data-addendum-meta>
                {{ addendumDateLabel(addendum) }} · {{ addendumAuthorLabel(addendum) }}
              </p>
              <p class="mt-1 text-sm font-medium text-theme-primary" data-addendum-reason>
                Motivo: {{ addendum.reason }}
              </p>
              <p class="mt-1 text-sm whitespace-pre-line text-theme-primary" data-addendum-content>
                {{ addendum.content }}
              </p>
            </div>
          </div>
        </UiCard>
      </li>
    </ol>
  </section>
</template>

<script setup>
import { PlusIcon } from '@heroicons/vue/24/outline'
import { usePermissions } from '@/composables/usePermissions'
import { formatDate } from '@/composables/useFormatters'
import UiButton from '@/components/ui/Button.vue'
import UiCard from '@/components/ui/Card.vue'
import UiSectionEmpty from '@/components/ui/UiSectionEmpty.vue'
import LoadingSpinner from '@/components/ui/LoadingSpinner.vue'
import StatusBadge from '@/components/ui/StatusBadge.vue'

const props = defineProps({
  evolutions: {
    type: Array,
    default: () => []
  },
  specialties: {
    type: Array,
    default: () => []
  },
  record: {
    type: Object,
    default: null
  },
  loading: {
    type: Boolean,
    default: false
  }
})

const emit = defineEmits(['add', 'addendum'])

const { can } = usePermissions()

// The surface names the four SOAP blocks after the payload columns, so a
// reader can map every block back to `clinical_evolutions`.
const formatEvolutionDate = value => formatDate(value) || 'Sin fecha'

// The row stores the catalog code; the timeline shows the human name and
// falls back to the code for historical values the catalog no longer has.
const specialtyLabel = evolution => {
  const code = evolution.specialty

  if (!code) return 'Sin especialidad'

  return props.specialties.find(specialty => specialty.code === code)?.name || code
}

const authorLabel = evolution => {
  const name = evolution.created_by?.name || evolution.createdBy?.name

  return name ? `Registrada por ${name}` : 'Registro clínico'
}

const addendumDateLabel = addendum => formatDate(addendum.created_at) || 'Sin fecha'

const addendumAuthorLabel = addendum =>
  addendum.author?.name ? `Firmado por ${addendum.author.name}` : 'Firmado por el profesional'

const hasText = value => typeof value === 'string' && value.trim() !== ''

const hasClinicalDetail = evolution =>
  [
    evolution.procedures_performed,
    evolution.materials_used,
    evolution.prescriptions,
    evolution.recommendations
  ].some(hasText)

const VITAL_SIGN_SLOTS = [
  { key: 'blood_pressure', label: 'PA', suffix: '' },
  { key: 'heart_rate', label: 'FC', suffix: ' bpm' },
  { key: 'temperature', label: 'T°', suffix: ' °C' },
  { key: 'respiratory_rate', label: 'FR', suffix: ' rpm' }
]

const vitalSigns = evolution => {
  const signs = evolution.vital_signs || {}

  return VITAL_SIGN_SLOTS.map(slot => ({ ...slot, value: signs[slot.key] }))
    .filter(
      sign => sign.value !== null && sign.value !== undefined && String(sign.value).trim() !== ''
    )
    .map(sign => ({
      key: sign.key,
      label: sign.label,
      value: `${sign.value}${sign.suffix}`
    }))
}

const CLINICAL_MEASUREMENT_SLOTS = [
  { key: 'pocket_depth_mm', label: 'Sondaje', suffix: ' mm' },
  { key: 'gingival_recession_mm', label: 'Recesión', suffix: ' mm' },
  { key: 'plaque_index_percent', label: 'Índice de placa', suffix: ' %' },
  { key: 'tooth_mobility', label: 'Movilidad', format: value => `Grado ${value}` },
  {
    key: 'bleeding_on_probing',
    label: 'Sangrado al sondaje',
    format: value => (value ? 'Sí' : 'No')
  }
]

const clinicalMeasurements = evolution => {
  const measurements = evolution.clinical_measurements || {}

  return CLINICAL_MEASUREMENT_SLOTS.map(slot => ({ ...slot, value: measurements[slot.key] }))
    .filter(
      measure =>
        measure.value !== null && measure.value !== undefined && String(measure.value).trim() !== ''
    )
    .map(measure => ({
      key: measure.key,
      label: measure.label,
      value: measure.format ? measure.format(measure.value) : `${measure.value}${measure.suffix}`
    }))
}
</script>
