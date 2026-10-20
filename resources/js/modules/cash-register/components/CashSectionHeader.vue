<template>
  <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
    <div class="min-w-0">
      <h3 class="text-lg font-semibold text-label">{{ title }}</h3>
      <p v-if="caption" class="mt-1 text-sm text-theme-secondary">{{ caption }}</p>
    </div>
    <div v-if="$slots.actions" class="flex flex-shrink-0 items-center gap-2">
      <slot name="actions" />
    </div>
  </div>
</template>

<script setup>
defineProps({
  title: { type: String, required: true },
  caption: { type: String, default: '' }
})
</script>
