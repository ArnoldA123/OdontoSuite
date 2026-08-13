<template>
  <div :class="cardClasses" :data-variant="variant" :data-clickable="clickable">
    <!-- Card header -->
    <div v-if="$slots.header" class="card-header">
      <slot name="header" />
    </div>

    <!-- Card content -->
    <div class="card-content">
      <slot />
    </div>

    <!-- Card footer -->
    <div v-if="$slots.footer" class="card-footer">
      <slot name="footer" />
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  variant: {
    type: String,
    default: 'default',
    validator: value => ['default', 'glass', 'flat', 'elevated', 'outlined'].includes(value)
  },
  padding: {
    type: String,
    default: 'md',
    validator: value => ['none', 'sm', 'md', 'lg', 'xl'].includes(value)
  },
  hover: { type: Boolean, default: false },
  clickable: { type: Boolean, default: false },
  loading: { type: Boolean, default: false }
})

const cardClasses = computed(() => {
  // PR2 (D8/D10): the blanket `transition-all duration-200` utility is replaced
  // by an explicit property list in <style scoped> so the transform rides the
  // fast rung on the iOS curve while colour washes keep standard easing.
  const base = ['relative overflow-hidden', 'border-theme']

  // Variant styles
  // Historical name: kept for compat. The `glass` variant is OPAQUE
  // (systemBackground surface, separator hairline, shadow-medium) — 76 call
  // sites across 21 module files depend on this rendering as an ordinary
  // data card. Translucent chrome surfaces use the `.surface-glass` class
  // from resources/css/tokens.generated.css (AppLayout sidebar / top bar /
  // mobile Sheet wrapper only). Do NOT add a blur effect here — see
  // design Decision 5.
  const variants = {
    default: ['bg-theme-surface-elevated shadow-soft', 'hover:shadow-medium'],
    glass: [
      'bg-systemBackground',
      'border border-separator',
      'shadow-medium',
      'rounded-ios',
      'hover:shadow-large'
    ],
    flat: ['bg-theme-surface border-theme', 'shadow-none', 'hover:shadow-subtle'],
    elevated: ['bg-theme-surface-elevated shadow-large', 'hover:shadow-elevated'],
    outlined: [
      'bg-theme-surface-elevated border-2 border-theme',
      'shadow-none',
      'hover:border-theme-strong hover:shadow-subtle'
    ]
  }

  // Padding variants
  const paddings = {
    none: 'p-0',
    sm: 'p-4',
    md: 'p-6',
    lg: 'p-8',
    xl: 'p-12'
  }

  // Interactive states
  const interactive = []
  if (props.clickable) {
    interactive.push('cursor-pointer')
  }
  if (props.hover) {
    interactive.push('hover:scale-[1.02] hover:shadow-medium')
  }
  if (props.loading) {
    interactive.push('opacity-75 pointer-events-none')
  }

  // Border radius — iOS clinical standard (10 px).
  const radius = 'rounded-ios'

  return [
    ...base,
    ...variants[props.variant],
    paddings[props.padding],
    radius,
    ...interactive
  ].join(' ')
})
</script>

<style scoped>
/* PR2 (D8) — explicit transition list replacing the removed `transition-all`
   utility. Colour and shadow washes keep the standard curve (Apple does this
   deliberately); only the transform adopts the iOS curve on the fast rung.
   `[data-variant]` is the card root: it is always bound, so it is the only
   stable scoped handle this component has. */
[data-variant] {
  transition:
    background-color var(--motion-duration-normal) ease-out,
    border-color var(--motion-duration-normal) ease-out,
    box-shadow var(--motion-duration-normal) ease-out,
    transform var(--motion-duration-fast) var(--motion-easing-ios);
}

.card-header {
  border-bottom: 1px solid var(--color-border);
  margin: calc(-1 * var(--spacing-6)) calc(-1 * var(--spacing-6)) var(--spacing-6);
  padding: var(--spacing-6);
}

.card-footer {
  border-top: 1px solid var(--color-border);
  margin: var(--spacing-6) calc(-1 * var(--spacing-6)) calc(-1 * var(--spacing-6));
  padding: var(--spacing-6);
}

.card-content {
  flex: 1;
}

/* Glass variant specific styles — opaque data card. See the comment in
   <script setup> for why the `glass` name is kept and why no blur
   declaration appears here. */
[data-variant='glass'] {
  background: var(--color-background-system-background);
  border: 1px solid var(--color-separator-separator);
  box-shadow: var(--shadow-medium);
}

/* Loading state */
[data-loading='true'] {
  position: relative;
  overflow: hidden;
}

[data-loading='true']::after {
  content: '';
  position: absolute;
  top: 0;
  left: -100%;
  width: 100%;
  height: 100%;
  background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
  animation: shimmer 1.5s infinite;
}

@keyframes shimmer {
  0% {
    left: -100%;
  }
  100% {
    left: 100%;
  }
}

/* Hover effects */
[data-hover='true']:hover {
  transform: translateY(-2px);
  box-shadow: var(--shadow-medium);
}

/* Clickable state */
[data-clickable='true'] {
  cursor: pointer;
  user-select: none;
}

/* Hover lift — one elevation rung up (PR1 --elevation-* ramp). The layered
   opacity technique (a ::before carrying the target shadow) is NOT usable
   here: the card root is `overflow: hidden`, which clips a pseudo-element's
   outer shadow to nothing. A single hover-only box-shadow transition is the
   correct trade — it repaints one element on pointer entry, not per frame of
   a running animation. */
[data-clickable='true']:hover {
  box-shadow: var(--elevation-2);
}

[data-clickable='true']:active {
  transform: scale(0.98);
}

/* Focus styles for accessibility — the ring is the PR1 token (D6). */
[data-clickable='true']:focus-visible {
  outline: none;
  box-shadow: var(--focus-ring-default);
}

/* Responsive adjustments */
@media (max-width: 640px) {
  .card-header,
  .card-footer {
    margin-left: calc(-1 * var(--spacing-4));
    margin-right: calc(-1 * var(--spacing-4));
    padding-left: var(--spacing-4);
    padding-right: var(--spacing-4);
  }
}

/* Glass variant usa variables CSS que ya manejan dark mode */

/* Reduced motion (D11) — the movement goes, the feedback stays. The press
   transform is swapped for an opacity dip on the same fast rung, and the
   hover lift keeps its (non-vestibular) shadow change. Durations are NOT
   flipped to 0: that would remove the feedback rather than gentle it. */
@media (prefers-reduced-motion: reduce) {
  [data-variant] {
    transition:
      background-color var(--motion-duration-normal) ease-out,
      border-color var(--motion-duration-normal) ease-out,
      box-shadow var(--motion-duration-normal) ease-out,
      opacity var(--motion-duration-normal) ease-out;
  }

  [data-hover='true']:hover {
    transform: none;
  }

  [data-clickable='true']:active {
    transform: none;
    opacity: 0.72;
  }
}
</style>
