<template>
  <div
    data-section-empty
    class="relative overflow-hidden rounded-ios p-6 text-center bg-accent-50"
    style="border: 1px solid var(--color-hairline)"
  >
    <!--
      WU5b - the login's dental motif, scaled down for a small box. Static
      layer reusing the same /images/login assets as DentalParallaxBackground
      (raster marks at ~24-32px instead of full-screen sizes). Empty states
      only: sections with real rows stay clean.
    -->
    <div data-empty-motif aria-hidden="true" class="pointer-events-none absolute inset-0">
      <img
        src="/images/login/tooth-1.png"
        alt=""
        class="absolute h-8 w-auto opacity-[0.16]"
        style="top: 14%; left: 7%; transform: rotate(-12deg)"
      />
      <img
        src="/images/login/toothbrush.png"
        alt=""
        class="absolute h-5 w-auto opacity-[0.14]"
        style="top: 20%; right: 8%; transform: rotate(16deg)"
      />
      <img
        src="/images/login/tooth-1.png"
        alt=""
        class="absolute h-7 w-auto opacity-[0.12]"
        style="bottom: 16%; left: 13%; transform: rotate(9deg)"
      />
      <img
        src="/images/login/toothbrush.png"
        alt=""
        class="absolute h-5 w-auto opacity-[0.12]"
        style="bottom: 18%; right: 11%; transform: rotate(-18deg)"
      />
    </div>
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
// WU3 / D9 - compact empty state: one short title + one precise sentence
// and, only where no other CTA for that destination exists on screen, a
// single contextual action in the default slot.
defineProps({
  title: { type: String, default: '' },
  description: { type: String, default: '' }
})
</script>
