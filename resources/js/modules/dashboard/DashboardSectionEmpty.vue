<template>
  <div
    data-section-empty
    class="relative overflow-hidden rounded-ios p-6 text-center"
    style="background: var(--color-canvas); border: 1px solid var(--color-hairline)"
  >
    <!--
      WU5b / user request - the login's dental parallax scene, scaled down
      for the box (see DentalMotifLayer). Empty states only: sections with
      real rows stay clean.
    -->
    <DentalMotifLayer variant="box" data-empty-motif />
    <div class="relative">
      <slot name="icon">
        <svg
          class="mx-auto h-8 w-8 mb-3"
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
      <p v-if="title" class="text-sm font-medium text-theme-primary">{{ title }}</p>
      <p v-if="description" class="text-xs text-theme-secondary mt-1 max-w-md mx-auto">
        {{ description }}
      </p>
      <div v-if="$slots.default" class="mt-4">
        <slot />
      </div>
    </div>
  </div>
</template>

<script setup>
import DentalMotifLayer from './DentalMotifLayer.vue'

// WU3 / D9 - compact empty state: one short title + one precise sentence
// and, only where no other CTA for that destination exists on screen, a
// single contextual action in the default slot.
defineProps({
  title: { type: String, default: '' },
  description: { type: String, default: '' }
})
</script>
