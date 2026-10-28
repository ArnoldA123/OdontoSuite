<template>
  <UiModal
    :model-value="modelValue"
    :title="labCase?.work || 'Caso de laboratorio'"
    size="2xl"
    data-lab-case-detail
    @update:model-value="value => emit('update:modelValue', value)"
    @close="emit('update:modelValue', false)"
  >
    <template #header>
      <div class="min-w-0">
        <h2 class="text-lg font-semibold text-theme-primary" data-lab-case-detail-title>
          {{ labCase?.work || 'Caso de laboratorio' }}
        </h2>
        <p class="mt-1 text-sm text-theme-secondary" data-lab-case-detail-patient>
          {{ patientFullName(labCase?.patient) }}
        </p>
      </div>
    </template>

    <div v-if="labCase" class="space-y-6">
      <div class="flex flex-wrap items-center gap-3">
        <UiStatusBadge
          :variant="phaseBadgeVariant(labCase.status)"
          :label="phaseLabel(labCase.status)"
          data-lab-case-detail-phase
        />
        <span
          v-if="isOverdue(labCase)"
          class="text-xs font-medium text-systemRed-600"
          data-lab-case-detail-overdue
        >
          Vencido
        </span>
      </div>

      <dl class="grid grid-cols-1 gap-4 sm:grid-cols-2" data-lab-case-facts>
        <div v-for="fact in facts" :key="fact.key">
          <dt class="text-xs font-medium uppercase tracking-wide text-theme-secondary">
            {{ fact.label }}
          </dt>
          <dd class="mt-1 text-sm text-theme-primary" :data-lab-case-fact="fact.key">
            {{ fact.value }}
          </dd>
        </div>
      </dl>

      <div v-if="labCase.notes">
        <p class="text-xs font-medium uppercase tracking-wide text-theme-secondary">Notas</p>
        <p class="mt-1 whitespace-pre-line text-sm text-theme-primary" data-lab-case-notes>
          {{ labCase.notes }}
        </p>
      </div>

      <!--
        The phase door. Only later phases are offered because the machine never
        goes back; a rejected move (the same phase asked twice from another
        desk) answers 422 and the service sentence lands here.
      -->
      <section
        class="space-y-3 border-t pt-4"
        style="border-color: var(--color-hairline)"
        data-lab-case-phase-door
      >
        <h3 class="text-base font-semibold text-theme-primary">Fase del caso</h3>

        <div v-if="canManage && laterPhases.length" class="flex flex-wrap gap-2">
          <UiButton
            v-for="phase in laterPhases"
            :key="phase"
            size="sm"
            variant="secondary"
            :data-lab-case-phase="phase"
            :disabled="acting === labCase.id"
            @click="goToPhase(phase)"
          >
            Pasar a {{ phaseLabel(phase) }}
          </UiButton>
        </div>

        <UiSectionEmpty
          v-else-if="laterPhases.length === 0"
          title="El caso ya está colocado."
          description="No hay fases posteriores: el trabajo concluyó."
        />

        <p v-else class="text-sm text-theme-secondary" data-lab-case-read-only>
          Tu perfil puede leer el caso, pero no cambiar su fase.
        </p>

        <p
          v-if="phaseError"
          data-lab-case-phase-error
          role="alert"
          class="text-sm font-medium text-systemRed-600"
        >
          {{ phaseError }}
        </p>
      </section>
    </div>

    <template #footer>
      <div class="flex justify-end gap-3">
        <UiButton variant="secondary" data-lab-case-close @click="emit('update:modelValue', false)">
          Cerrar
        </UiButton>
        <UiButton
          v-if="canEdit"
          variant="primary"
          data-lab-case-detail-edit
          @click="emit('edit', labCase)"
        >
          Editar
        </UiButton>
      </div>
    </template>
  </UiModal>
</template>

<script setup>
import { computed, ref, watch } from 'vue'
import UiModal from '../../components/ui/Modal.vue'
import UiButton from '../../components/ui/Button.vue'
import UiSectionEmpty from '../../components/ui/UiSectionEmpty.vue'
import UiStatusBadge from '../../components/ui/StatusBadge.vue'
import {
  formatDate,
  formatDateTime,
  isOverdue,
  nextPhases,
  patientFullName,
  phaseBadgeVariant,
  phaseLabel,
  pieceLabel
} from './labCaseMeta'
import { labCaseMessageFor, useLabCases } from '../../composables/useLabCases'

const props = defineProps({
  modelValue: { type: Boolean, default: false },
  labCase: { type: Object, default: null },
  // Projection of `can.manageLabCase`: a forgotten prop never grants a write.
  canManage: { type: Boolean, default: false },
  // Projection of the case's `can_edit`; both are required to offer the edit.
  canEdit: { type: Boolean, default: false }
})

const emit = defineEmits(['update:modelValue', 'updated', 'edit'])

const { acting, transition } = useLabCases()

const phaseError = ref('')

const laterPhases = computed(() => nextPhases(props.labCase?.status))

const facts = computed(() => {
  const current = props.labCase
  if (!current) return []

  return [
    { key: 'patient', label: 'Paciente', value: patientFullName(current.patient) },
    { key: 'dental_piece', label: 'Pieza', value: pieceLabel(current.dental_piece) },
    { key: 'professional', label: 'Profesional', value: current.professional?.name || '—' },
    { key: 'technician', label: 'Técnico', value: current.technician?.name || '—' },
    { key: 'material', label: 'Material', value: current.material || '—' },
    { key: 'sent_at', label: 'Enviado', value: formatDate(current.sent_at) },
    { key: 'promised_at', label: 'Prometido', value: formatDate(current.promised_at) },
    {
      key: 'delivered_at',
      label: 'Recibido del taller',
      value: formatDateTime(current.delivered_at)
    },
    { key: 'placed_at', label: 'Colocado', value: formatDateTime(current.placed_at) }
  ]
})

// A different case is a different attempt: the previous rejection must not
// follow the user into another work order.
watch(
  () => props.labCase?.id,
  () => {
    phaseError.value = ''
  }
)

const goToPhase = async phase => {
  const current = props.labCase
  if (!current?.id) return

  phaseError.value = ''
  try {
    const fresh = await transition(current, phase)
    if (fresh) emit('updated', fresh)
  } catch (thrown) {
    phaseError.value = labCaseMessageFor(thrown, 'No se pudo cambiar la fase del caso')
  }
}
</script>
