<template>
  <div :class="containerClasses" role="search" :aria-label="ariaLabel">
    <label v-if="title" class="filter-title">{{ title }}</label>
    <div :class="gridClasses">
      <slot />
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  columns: {
    type: Number,
    default: 4,
    validator: value => [2, 3, 4, 5, 6].includes(value)
  },
  title: {
    type: String,
    default: ''
  }
})

const ariaLabel = computed(() => (props.title ? `Filtros: ${props.title}` : 'Filtros'))

const containerClasses = computed(() => ['filter-bar', 'animate-fade-in'])

const COLS_CLASSES = {
  2: 'grid-cols-1 sm:grid-cols-2',
  3: 'grid-cols-1 sm:grid-cols-2 md:grid-cols-3',
  4: 'grid-cols-1 sm:grid-cols-2 lg:grid-cols-4',
  5: 'grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-5',
  6: 'grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-6'
}

const gridClasses = computed(() => ['grid gap-4', COLS_CLASSES[props.columns]])
</script>

<style scoped>
.filter-bar {
  @apply p-4 rounded-xl border;
  background-color: var(--color-background-secondary);
  border-color: var(--color-border-light);
}

.filter-title {
  @apply block text-sm font-medium mb-3;
  color: var(--color-text-primary);
}
</style>
