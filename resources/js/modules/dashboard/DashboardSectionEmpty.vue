<template>
  <div
    data-section-empty
    class="rounded-ios p-10 text-center bg-accent-50"
    style="border: 1px solid var(--color-hairline)"
  >
    <slot name="icon">
      <svg
        class="mx-auto h-12 w-12 mb-4"
        fill="none"
        stroke="currentColor"
        viewBox="0 0 24 24"
        stroke-width="1.5"
        style="color: var(--color-label-tertiary-label)"
        aria-hidden="true"
      >
        <path
          stroke-linecap="round"
          stroke-linejoin="round"
          d="M3 13.5h4.5l1.5 3h6l1.5-3H21M5.25 4.5h13.5a1.5 1.5 0 0 1 1.5 1.5v12a1.5 1.5 0 0 1-1.5 1.5H5.25a1.5 1.5 0 0 1-1.5-1.5V6a1.5 1.5 0 0 1 1.5-1.5z"
        />
      </svg>
    </slot>
    <p v-if="title" class="text-base font-medium text-theme-primary">{{ title }}</p>
    <p v-if="description" class="text-sm text-theme-secondary mt-1 max-w-md mx-auto">
      {{ description }}
    </p>
    <div v-if="$slots.default" class="mt-6">
      <slot />
    </div>
  </div>
</template>

<script setup>
defineProps({
  title: { type: String, default: '' },
  description: { type: String, default: '' }
})
</script>
