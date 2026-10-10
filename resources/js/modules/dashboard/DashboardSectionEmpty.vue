<template>
  <div
    data-section-empty
    class="relative overflow-hidden rounded-ios p-6 text-center bg-accent-50"
    style="border: 1px solid var(--color-hairline)"
  >
    <!--
      WU5b / user request - the login's dental parallax backdrop on empty
      states: same /images/login assets, scaled down for a small box,
      scattered across the whole surface with the login's visibility, plus a
      gentle drift (static under prefers-reduced-motion). Empty states only:
      sections with real rows stay clean.
    -->
    <div data-empty-motif aria-hidden="true" class="pointer-events-none absolute inset-0">
      <img
        src="/images/login/tooth-1.png"
        alt=""
        class="motif-drift-a absolute h-12 w-auto opacity-[0.28]"
        style="top: 6%; left: 4%; transform: rotate(-12deg)"
      />
      <img
        src="/images/login/toothbrush.png"
        alt=""
        class="motif-drift-b absolute h-8 w-auto opacity-[0.24]"
        style="top: 10%; left: 30%; transform: rotate(20deg)"
      />
      <img
        src="/images/login/tooth-1.png"
        alt=""
        class="motif-drift-b absolute h-10 w-auto opacity-[0.26]"
        style="top: 4%; right: 8%; transform: rotate(14deg)"
      />
      <img
        src="/images/login/toothbrush.png"
        alt=""
        class="motif-drift-a absolute h-7 w-auto opacity-[0.22]"
        style="top: 8%; right: 28%; transform: rotate(-18deg)"
      />
      <img
        src="/images/login/tooth-1.png"
        alt=""
        class="motif-drift-b absolute h-11 w-auto opacity-[0.26]"
        style="bottom: 8%; left: 10%; transform: rotate(8deg)"
      />
      <img
        src="/images/login/toothbrush.png"
        alt=""
        class="motif-drift-a absolute h-8 w-auto opacity-[0.22]"
        style="bottom: 12%; left: 34%; transform: rotate(-24deg)"
      />
      <img
        src="/images/login/tooth-1.png"
        alt=""
        class="motif-drift-a absolute h-12 w-auto opacity-[0.28]"
        style="bottom: 6%; right: 6%; transform: rotate(-10deg)"
      />
      <img
        src="/images/login/toothbrush.png"
        alt=""
        class="motif-drift-b absolute h-8 w-auto opacity-[0.24]"
        style="bottom: 10%; right: 30%; transform: rotate(16deg)"
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

<style scoped>
/* WU5b / user request - gentle parallax-style drift for the empty-state
   dental motif: two slow phase-shifted paths (the `translate` property, so
   the inline rotate transforms stay intact), static under reduced motion. */
.motif-drift-a {
  animation: motif-float-a 9s ease-in-out infinite alternate;
}

.motif-drift-b {
  animation: motif-float-b 12s ease-in-out infinite alternate;
}

@keyframes motif-float-a {
  from {
    translate: 0 0;
  }
  to {
    translate: 6px -8px;
  }
}

@keyframes motif-float-b {
  from {
    translate: 0 0;
  }
  to {
    translate: -7px 6px;
  }
}

@media (prefers-reduced-motion: reduce) {
  .motif-drift-a,
  .motif-drift-b {
    animation: none;
  }
}
</style>
