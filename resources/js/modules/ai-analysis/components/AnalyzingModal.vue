<template>
  <UiModal :model-value="show" :closable="false">
    <div class="p-6 text-center md:p-8">
      <div
        class="mx-auto mb-6 flex h-20 w-20 items-center justify-center rounded-full"
        style="background: var(--color-canvas); border: 1px solid var(--color-hairline)"
      >
        <ExclamationTriangleIcon
          v-if="status === 'failed'"
          class="h-10 w-10 text-systemRed-600"
          aria-hidden="true"
        />
        <CpuChipIcon
          v-else
          class="h-10 w-10 text-theme-primary"
          :class="{ 'animate-spin': isRunning }"
          aria-hidden="true"
        />
      </div>

      <h3 class="mb-2 text-xl font-semibold text-theme-primary">Analizando imagen...</h3>
      <p class="mb-6 text-sm text-theme-secondary">
        La IA está procesando la imagen. Esto puede tomar unos segundos.
      </p>

      <ol class="mx-auto max-w-md space-y-3 text-left">
        <li
          v-for="step in steps"
          :key="step.key"
          :data-progress-step="step.key"
          class="flex items-center gap-3 text-sm"
          :class="stepClass(step.state)"
        >
          <CheckCircleIcon
            v-if="step.state === 'done'"
            class="h-5 w-5 flex-shrink-0 text-systemGreen-600"
            aria-hidden="true"
          />
          <span
            v-else-if="step.state === 'active'"
            class="flex h-5 w-5 flex-shrink-0 items-center justify-center"
            aria-hidden="true"
          >
            <span class="h-2.5 w-2.5 animate-pulse rounded-full bg-theme-primary" />
          </span>
          <span
            v-else
            class="h-5 w-5 flex-shrink-0 rounded-full"
            style="border: 2px solid var(--color-hairline)"
            aria-hidden="true"
          />
          <span>{{ step.label }}</span>
        </li>
      </ol>

      <div v-if="status === 'failed'" class="mt-6 space-y-4">
        <p role="alert" data-progress-error class="text-sm text-systemRed-600">
          {{ message }}
        </p>
        <UiButton variant="secondary" data-progress-close @click="emit('close')">Cerrar</UiButton>
      </div>
    </div>
  </UiModal>
</template>

<script setup>
import { computed } from 'vue'
import { CheckCircleIcon, CpuChipIcon, ExclamationTriangleIcon } from '@heroicons/vue/24/outline'
import UiButton from '../../../components/ui/Button.vue'
import UiModal from '../../../components/ui/Modal.vue'

const props = defineProps({
  show: { type: Boolean, default: false },
  status: {
    type: String,
    default: 'uploading',
    validator: value => ['uploading', 'analyzing', 'completed', 'failed'].includes(value)
  },
  message: { type: String, default: '' }
})

const emit = defineEmits(['close'])

const STEP_ORDER = ['uploading', 'analyzing', 'completed']

const STEPS = [
  { key: 'uploading', label: 'Subiendo imagen...' },
  { key: 'analyzing', label: 'Procesando con IA...' },
  { key: 'completed', label: 'Generando hallazgos...' }
]

const isRunning = computed(() => props.status === 'uploading' || props.status === 'analyzing')

const steps = computed(() => {
  const current = STEP_ORDER.indexOf(props.status)

  return STEPS.map((step, index) => {
    let state = 'pending'
    if (current >= 0) {
      if (index < current) state = 'done'
      else if (index === current) state = props.status === 'completed' ? 'done' : 'active'
    }
    return { ...step, state }
  })
})

const stepClass = state => {
  if (state === 'done') return 'text-theme-secondary'
  if (state === 'active') return 'font-medium text-theme-primary'
  return 'text-theme-secondary'
}
</script>
