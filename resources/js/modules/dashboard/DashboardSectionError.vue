<template>
  <div
    data-section-error
    class="flex items-center justify-between flex-wrap gap-4 rounded-ios p-5 bg-systemRed-50"
    style="border: 1px solid var(--color-hairline)"
  >
    <div class="flex items-center gap-3 min-w-0">
      <ExclamationTriangleIcon
        class="flex-shrink-0 w-6 h-6 text-systemRed-600"
        aria-hidden="true"
      />
      <div class="min-w-0">
        <p class="text-sm font-medium text-theme-primary">{{ title }}</p>
        <p class="text-sm text-theme-secondary">{{ description }}</p>
      </div>
    </div>
    <UiButton variant="primary" size="sm" v-bind="retryAttrs" @click="$emit('retry')">
      Reintentar
    </UiButton>
  </div>
</template>

<script setup>
import { ExclamationTriangleIcon } from '@heroicons/vue/24/outline'

defineProps({
  title: { type: String, required: true },
  description: { type: String, required: true },
  retryAttrs: { type: Object, default: () => ({}) }
})

defineEmits(['retry'])
</script>
