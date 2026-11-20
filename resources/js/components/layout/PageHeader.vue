<template>
  <header data-page-header class="page-header" :style="surfaceStyle">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
      <div class="min-w-0 flex-1">
        <Breadcrumbs
          v-if="breadcrumbs && breadcrumbs.length"
          :items="breadcrumbs"
          :show-home="false"
          size="sm"
          variant="minimal"
          class="mb-2"
        />
        <p v-if="kicker" data-page-header-kicker class="page-kicker">{{ kicker }}</p>
        <h1 data-page-header-title class="page-title text-4xl font-semibold text-label">
          {{ title }}
        </h1>
        <p v-if="subtitle" data-page-header-subtitle class="page-subtitle">
          {{ subtitle }}
        </p>
      </div>

      <div v-if="$slots.actions" data-page-header-actions class="page-actions">
        <slot name="actions" />
      </div>
    </div>
  </header>
</template>

<script setup>
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
  kicker: {
    type: String,
    default: ''
  },
  breadcrumbs: {
    type: Array,
    default: () => []
  }
})

// Canonical page header surface: canvas fill, hairline, panel radius and the
// elevation-2 rung. Declared inline (same as the nine page headers it
// replaces) so the anatomy is measurable from the rendered element.
const surfaceStyle = {
  background: 'var(--color-canvas)',
  border: '1px solid var(--color-hairline)',
  borderRadius: 'var(--radius-panel)',
  boxShadow: 'var(--elevation-2)'
}
</script>

<style scoped>
.page-header {
  @apply p-6 md:p-8;
}

.page-kicker {
  @apply text-xs font-medium uppercase tracking-wide;
  color: var(--color-text-secondary);
}

.page-title {
  @apply mt-2;
}

.page-subtitle {
  @apply mt-2 text-sm;
  color: var(--color-text-secondary);
  font-feature-settings:
    'tnum' 1,
    'lnum' 1;
}

.page-actions {
  @apply flex flex-shrink-0 flex-wrap items-center gap-2;
}
</style>
