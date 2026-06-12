<template>
  <div class="kanban-col" :class="`tone-${column.tone}`">
    <div class="kanban-col-header">
      <span class="dot" :class="`dot-${column.tone}`"></span>
      <span class="label">{{ column.label }}</span>
      <span class="count">{{ plans.length }}</span>
    </div>

    <div
      class="kanban-col-body"
      @dragover.prevent="isOver = true"
      @dragleave="isOver = false"
      @drop.prevent="onDrop"
      :class="{ 'is-over': isOver }"
    >
      <div
        v-for="plan in plans"
        :key="plan.id"
        class="kanban-card"
        draggable="true"
        @dragstart="onDragStart($event, plan)"
        @click="$emit('view', plan)"
      >
        <div class="kc-title">{{ plan.title }}</div>
        <div class="kc-meta">
          <span class="kc-patient">{{ plan.patient?.first_name }} {{ plan.patient?.last_name }}</span>
          <span class="kc-cost">S/ {{ formatPrice(plan.final_cost) }}</span>
        </div>
        <div v-if="plan.is_overdue" class="kc-overdue">Vencido</div>
      </div>

      <div v-if="plans.length === 0" class="kanban-empty">
        Arrastra planes aquí
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue'

const props = defineProps({
  column: { type: Object, required: true },
  plans: { type: Array, default: () => [] },
})

const emit = defineEmits(['view', 'change-status', 'drop-plan'])

const isOver = ref(false)

const onDragStart = (e, plan) => {
  e.dataTransfer.setData('application/json', JSON.stringify(plan))
  e.dataTransfer.effectAllowed = 'move'
}

const onDrop = (e) => {
  isOver.value = false
  try {
    const plan = JSON.parse(e.dataTransfer.getData('application/json'))
    if (plan.status === props.column.value) return
    emit('drop-plan', { plan, newStatus: props.column.value })
  } catch (err) {
  }
}

const formatPrice = (price) =>
  new Intl.NumberFormat('es-PE', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  }).format(price || 0)
</script>

<style scoped>
.kanban-col {
  @apply flex flex-col rounded-lg p-2 min-h-[300px];
  background-color: var(--color-surface);
}

.kanban-col-header {
  @apply flex items-center gap-2 px-2 py-1.5 text-xs font-semibold;
  color: var(--color-text-secondary);
}

.dot {
  @apply w-2 h-2 rounded-full;
}
.dot-gray { background-color: rgb(156 163 175); }
.dot-blue { background-color: rgb(59 130 246); }
.dot-green { background-color: rgb(34 197 94); }
.dot-amber { background-color: rgb(245 158 11); }
.dot-emerald { background-color: rgb(16 185 129); }
.dot-red { background-color: rgb(239 68 68); }

.count {
  @apply ml-auto text-xs px-1.5 py-0.5 rounded;
  background-color: var(--color-surface-elevated);
}

.kanban-col-body {
  @apply flex-1 space-y-2 p-1 rounded transition-colors;
}

.kanban-col-body.is-over {
  @apply ring-2 ring-primary-400;
}

.kanban-card {
  @apply p-2.5 rounded-md cursor-pointer hover-lift transition-shadow;
  background-color: var(--color-surface-elevated);
  border: 1px solid var(--color-border, transparent);
}

.kc-title {
  @apply text-sm font-medium truncate;
  color: var(--color-text-primary);
}

.kc-meta {
  @apply flex justify-between items-center mt-1 text-xs;
  color: var(--color-text-secondary);
}

.kc-cost {
  @apply font-semibold;
  color: rgb(37 99 235);
}

.kc-overdue {
  @apply mt-1.5 inline-block text-[10px] px-1.5 py-0.5 rounded;
  background-color: rgb(254 226 226);
  color: rgb(185 28 28);
}

.kanban-empty {
  @apply text-center text-xs py-6;
  color: var(--color-text-secondary);
  opacity: 0.6;
}
</style>
