<template>
  <section
    data-kanban-column
    :data-tone="column.tone"
    class="flex min-h-[300px] flex-col gap-2 rounded-[var(--radius-panel)] bg-canvas p-2"
    :style="{ border: '1px solid var(--color-hairline)' }"
    :aria-label="`Columna ${column.label}`"
  >
    <header class="flex items-center gap-2 px-2 pt-1">
      <span class="h-2 w-2 shrink-0 rounded-full" :class="toneDotClass" aria-hidden="true" />
      <span class="truncate text-xs font-semibold text-theme-primary">{{ column.label }}</span>
      <span
        class="ml-auto shrink-0 rounded-[var(--radius-control)] bg-theme-surface-elevated px-1.5 py-0.5 text-xs tabular-nums text-theme-secondary"
      >
        {{ plans.length }}
      </span>
    </header>

    <div
      data-kanban-drop-zone
      class="flex-1 space-y-2 rounded-[var(--radius-control)] p-1 transition-colors"
      :class="{ 'ring-2 ring-accent-500': isOver }"
      @dragover.prevent="isOver = true"
      @dragleave="isOver = false"
      @drop.prevent="onDrop"
    >
      <article
        v-for="plan in plans"
        :key="plan.id"
        data-kanban-card
        class="cursor-pointer rounded-[var(--radius-control)] bg-theme-surface-elevated p-2.5 hover-lift"
        :style="{ border: '1px solid var(--color-hairline)' }"
        draggable="true"
        @dragstart="onDragStart($event, plan)"
        @dragend="isOver = false"
        @click="emit('view', plan)"
      >
        <p class="truncate text-sm font-medium text-theme-primary">
          {{ plan.title }}
        </p>
        <div class="mt-1 flex items-center justify-between gap-2 text-xs">
          <span class="truncate text-theme-secondary">{{ patientName(plan) }}</span>
          <span class="shrink-0 font-semibold tabular-nums text-theme-primary">
            {{ formatCurrency(plan.final_cost) }}
          </span>
        </div>
        <p
          v-if="plan.is_overdue"
          class="mt-1.5 inline-flex rounded-[var(--radius-control)] bg-systemRed-50 px-1.5 py-0.5 text-[10px] font-medium text-systemRed-600"
        >
          Vencido
        </p>
      </article>

      <p v-if="plans.length === 0" class="py-6 text-center text-xs text-theme-secondary">
        Arrastra planes aquí
      </p>
    </div>
  </section>
</template>

<script setup>
import { ref, computed } from 'vue'
import { formatCurrency } from '@/composables/useFormatters'

const props = defineProps({
  column: { type: Object, required: true },
  plans: { type: Array, default: () => [] }
})

const emit = defineEmits(['view', 'dropPlan'])

const TONE_DOT_CLASSES = {
  neutral: 'bg-systemGray-500',
  steel: 'bg-systemSteel-500',
  green: 'bg-systemGreen-500',
  yellow: 'bg-systemYellow-500',
  accent: 'bg-accent-500',
  red: 'bg-systemRed-500'
}

const isOver = ref(false)

const toneDotClass = computed(() => TONE_DOT_CLASSES[props.column.tone] ?? TONE_DOT_CLASSES.neutral)

const patientName = plan => {
  const name = [plan.patient?.first_name, plan.patient?.last_name].filter(Boolean).join(' ')

  return name || 'Sin paciente'
}

const onDragStart = (e, plan) => {
  e.dataTransfer.setData('application/json', JSON.stringify(plan))
  e.dataTransfer.effectAllowed = 'move'
}

const onDrop = e => {
  isOver.value = false
  try {
    const plan = JSON.parse(e.dataTransfer.getData('application/json'))
    if (plan.status === props.column.value) return
    emit('dropPlan', { plan, newStatus: props.column.value })
  } catch (err) {
    // Drop fuera del kanban: se ignora sin mover el plan
  }
}
</script>
