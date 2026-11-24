<template>
  <div
    v-if="isOutcome"
    class="flex flex-wrap items-center gap-3 rounded-ios border border-hairline bg-theme-surface px-4 py-3"
    data-appointment-rail="outcome"
    :data-appointment-rail-status="status"
  >
    <UiStatusBadge
      :variant="badgeProps.variant"
      :label="statusLabel(status)"
      :show-dot="badgeProps.showDot"
      :data-status="status"
    />
    <p v-if="outcomeNote" class="min-w-0 flex-1 text-sm text-theme-secondary">
      {{ outcomeNote }}
    </p>
  </div>

  <ol
    v-else
    class="grid grid-cols-5"
    aria-label="Etapas de la cita"
    data-appointment-rail="steps"
    :data-appointment-rail-status="status"
  >
    <li
      v-for="(step, index) in steps"
      :key="step.key"
      class="relative pt-2.5 text-center"
      :data-appointment-rail-step="step.key"
      :data-appointment-rail-step-state="step.state"
      :aria-current="step.state === 'current' ? 'step' : undefined"
    >
      <span
        v-if="index > 0"
        class="absolute top-[19px] h-0.5"
        style="left: calc(-50% + 14px); right: calc(50% - 14px)"
        :class="step.connectorClasses"
        data-appointment-rail-connector
        aria-hidden="true"
      />
      <span
        class="relative mx-auto flex h-5 w-5 items-center justify-center rounded-full border-2"
        :class="step.nodeClasses"
        aria-hidden="true"
      >
        <CheckIcon v-if="step.state === 'done'" class="h-2.5 w-2.5" />
        <span v-else-if="step.state === 'current'" class="h-1.5 w-1.5 rounded-full bg-white" />
      </span>
      <span class="mt-2 block min-h-8 text-xs" :class="step.labelClasses">{{ step.label }}</span>
      <span
        class="block h-4 text-xs text-theme-tertiary"
        style="font-feature-settings: 'tnum' 1"
        data-appointment-rail-time-slot
        :data-appointment-rail-time="step.time ? '' : null"
      >
        {{ step.time }}
      </span>
    </li>
  </ol>
</template>

<script setup>
import { computed } from 'vue'
import UiStatusBadge from '../../components/ui/StatusBadge.vue'
import { formatTime } from '../../composables/useFormatters'
import {
  APPOINTMENT_RAIL_STAGES,
  isTerminalOutcome,
  railStep,
  statusBadgeProps,
  statusLabel,
  statusRailClasses
} from './appointmentStatus'
import { CheckIcon } from '@heroicons/vue/20/solid'

const props = defineProps({
  status: { type: String, default: '' },
  // Stage key -> published ISO timestamp. A stage without one renders
  // without a time; nothing is derived.
  times: { type: Object, default: () => ({}) }
})

const OUTCOME_NOTES = {
  cancelled: 'La cita se canceló y liberó su horario.',
  no_show: 'El paciente no se presentó a la cita.',
  rescheduled: 'La cita se movió a otro horario de la agenda.'
}

const isOutcome = computed(() => isTerminalOutcome(props.status))
const badgeProps = computed(() => statusBadgeProps(props.status))
const outcomeNote = computed(() => OUTCOME_NOTES[props.status] ?? '')

const nodeClasses = (state, rail) => {
  if (state === 'done') return 'border-systemGray-600 bg-systemGray-600 text-white'
  if (state === 'current') return `${rail.node} ${rail.dot}`
  return 'border-systemGray-200 bg-theme-surface'
}

const labelClasses = (state, rail) => {
  if (state === 'current') return `font-semibold ${rail.label}`
  if (state === 'done') return 'text-theme-primary'
  return 'text-theme-secondary'
}

const steps = computed(() => {
  const current = railStep(props.status)
  const rail = statusRailClasses(props.status)

  return APPOINTMENT_RAIL_STAGES.map((stage, index) => {
    const state = index < current ? 'done' : index === current ? 'current' : 'todo'
    const time = props.times?.[stage.key]

    return {
      key: stage.key,
      label: stage.label,
      state,
      connectorClasses: state === 'todo' ? 'bg-systemGray-200' : 'bg-systemGray-600',
      nodeClasses: nodeClasses(state, rail),
      labelClasses: labelClasses(state, rail),
      time: time ? formatTime(time) : ''
    }
  })
})
</script>
