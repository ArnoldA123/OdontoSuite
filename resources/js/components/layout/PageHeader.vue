<template>
  <header :class="containerClasses" role="banner">
    <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4">
      <div class="min-w-0 flex-1">
        <Breadcrumbs
          v-if="breadcrumbs && breadcrumbs.length"
          :items="breadcrumbs"
          :show-home="false"
          size="sm"
          variant="minimal"
          class="mb-2"
        />
        <h1 class="page-title">
          {{ title }}
        </h1>
        <p v-if="subtitle" class="page-subtitle">
          {{ subtitle }}
        </p>
      </div>

      <div v-if="$slots.actions" class="page-actions flex items-center gap-2 flex-wrap">
        <slot name="actions" />
      </div>
    </div>
  </header>
</template>

<script setup>
import { computed } from 'vue'
import Breadcrumbs from '../ui/Breadcrumbs.vue'

defineProps({
  title: {
    type: String,
    required: true
  },
  subtitle: {
    type: String,
    default: ''
  },
  breadcrumbs: {
    type: Array,
    default: () => []
  }
})

const containerClasses = computed(() => ['page-header', 'animate-fade-in'])
</script>

<style scoped>
.page-header {
  @apply p-6 pb-4 border-b border-theme;
  background-color: var(--color-background);
}

.page-title {
  @apply text-3xl font-bold tracking-tight;
  color: var(--color-text-primary);
  line-height: 1.2;
}

.page-subtitle {
  @apply mt-1 text-base;
  color: var(--color-text-secondary);
}

.page-actions {
  @apply flex-shrink-0;
}

@media (max-width: 640px) {
  .page-header {
    @apply p-4 pb-3;
  }

  .page-title {
    @apply text-2xl;
  }
}
</style>
