<template>
  <div :class="cardClasses" :data-variant="variant">
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
    validator: (value) => ['default', 'glass', 'flat', 'elevated', 'outlined'].includes(value)
  },
  padding: {
    type: String,
    default: 'md',
    validator: (value) => ['none', 'sm', 'md', 'lg', 'xl'].includes(value)
  },
  hover: { type: Boolean, default: false },
  clickable: { type: Boolean, default: false },
  loading: { type: Boolean, default: false }
})

const cardClasses = computed(() => {
  const base = [
    'relative overflow-hidden transition-all duration-200',
    'border-theme'
  ]

  // Variant styles
  // Historical name: kept for compat. The `glass` variant is OPAQUE
  // (cream-100 surface, ink-200 hairline, shadow-medium) — 76 call sites
  // across 21 module files depend on this rendering as an ordinary data
  // card. Translucent chrome surfaces use the `.surface-glass` class from
  // resources/css/tokens.generated.css (AppLayout sidebar / top bar /
  // mobile Sheet wrapper only). Do NOT add a blur effect here — see
  // design Decision 5.
  const variants = {
    default: [
      'bg-theme-surface-elevated shadow-soft',
      'hover:shadow-medium'
    ],
    glass: [
      'bg-cream-100',
      'border border-ink-200',
      'shadow-medium',
      'rounded-xl',
      'hover:shadow-large'
    ],
    flat: [
      'bg-theme-surface border-theme',
      'shadow-none',
      'hover:shadow-subtle'
    ],
    elevated: [
      'bg-theme-surface-elevated shadow-large',
      'hover:shadow-elevated'
    ],
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

  // Border radius
  const radius = 'rounded-xl'

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
[data-variant="glass"] {
  background: var(--color-cream-100);
  border: 1px solid var(--color-border);
  box-shadow: var(--shadow-medium);
}

/* Loading state */
[data-loading="true"] {
  position: relative;
  overflow: hidden;
}

[data-loading="true"]::after {
  content: '';
  position: absolute;
  top: 0;
  left: -100%;
  width: 100%;
  height: 100%;
  background: linear-gradient(
    90deg,
    transparent,
    rgba(255, 255, 255, 0.2),
    transparent
  );
  animation: shimmer 1.5s infinite;
}

@keyframes shimmer {
  0% { left: -100%; }
  100% { left: 100%; }
}

/* Hover effects */
[data-hover="true"]:hover {
  transform: translateY(-2px);
  box-shadow: var(--shadow-medium);
}

/* Clickable state */
[data-clickable="true"] {
  cursor: pointer;
  user-select: none;
}

[data-clickable="true"]:active {
  transform: scale(0.98);
}

/* Focus styles for accessibility */
[data-clickable="true"]:focus-visible {
  outline: 2px solid var(--color-accent);
  outline-offset: 2px;
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

/* Reduced motion support */
@media (prefers-reduced-motion: reduce) {
  .card {
    transition: none;
  }

  [data-hover="true"]:hover {
    transform: none;
  }

  [data-clickable="true"]:active {
    transform: none;
  }
}
</style>
