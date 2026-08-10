<template>
  <span :class="badgeClasses" :data-variant="variant">
    <slot name="icon" />
    <span v-if="$slots.default" class="badge-content">
      <slot />
    </span>
    <button
      v-if="dismissible"
      type="button"
      :class="dismissButtonClasses"
      :aria-label="dismissLabel"
      @click="handleDismiss"
    >
      <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
      </svg>
    </button>
  </span>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  variant: {
    type: String,
    default: 'default',
    validator: (value) => ['default', 'primary', 'success', 'warning', 'error', 'info', 'neutral'].includes(value)
  },
  size: {
    type: String,
    default: 'md',
    validator: (value) => ['sm', 'md', 'lg'].includes(value)
  },
  shape: {
    type: String,
    default: 'rounded',
    validator: (value) => ['rounded', 'pill', 'square'].includes(value)
  },
  dismissible: { type: Boolean, default: false },
  dot: { type: Boolean, default: false }
})

const emit = defineEmits(['dismiss'])

const dismissLabel = computed(() => 'Eliminar badge')

const badgeClasses = computed(() => {
  const base = [
    'inline-flex items-center gap-1.5 font-medium',
    'select-none'
  ]

  // Size variants
  const sizes = {
    sm: 'px-2 py-0.5 text-xs min-h-[20px]',
    md: 'px-2.5 py-1 text-sm min-h-[24px]',
    lg: 'px-3 py-1.5 text-base min-h-[28px]'
  }

  // Shape variants
  const shapes = {
    rounded: 'rounded-md',
    pill: 'rounded-full',
    square: 'rounded-sm'
  }

  // Color variants
  // `info` is an alias for the medical-state clinicalTeal ramp (PR2 Decision
  // 3) so 76+ existing call sites render as before without a code change.
  const variants = {
    default: [
      'bg-theme-surface text-theme-secondary',
      'border border-theme'
    ],
    primary: [
      'bg-primary-50 text-primary-700',
      'border border-primary-200'
    ],
    success: [
      'bg-success-badge',
      'border border-success'
    ],
    warning: [
      'bg-warning-badge',
      'border border-warning'
    ],
    error: [
      'bg-danger-badge',
      'border border-danger'
    ],
    info: [
      'bg-clinicalTeal-50 text-clinicalTeal-700',
      'border border-clinicalTeal-200'
    ],
    neutral: [
      'bg-theme-surface text-theme-secondary',
      'border border-theme'
    ]
  }

  // Dot variant
  const dotClasses = props.dot ? 'w-2 h-2 p-0' : ''

  return [
    ...base,
    sizes[props.size],
    shapes[props.shape],
    ...(variants[props.variant] || variants.default),
    dotClasses
  ].join(' ')
})

const dismissButtonClasses = computed(() => [
  'ml-1 -mr-1 p-0.5 rounded-full',
  'hover:bg-black/10 focus:bg-black/10',
  'transition-colors duration-200',
  'focus:outline-none focus:ring-2 focus:ring-current focus:ring-offset-1'
])

const handleDismiss = () => {
  emit('dismiss')
}
</script>

<style scoped>
.badge-content {
  display: flex;
  align-items: center;
  gap: inherit;
}

/* Dot variant specific styles */
[data-variant]:has(.dot) {
  aspect-ratio: 1;
  padding: 0;
  min-width: 8px;
  min-height: 8px;
}

/* Hover effects for interactive badges */
.badge:hover {
  transform: translateY(-1px);
  box-shadow: var(--shadow-subtle);
}

/* Focus styles for dismissible badges */
.dismiss-button:focus-visible {
  outline: 2px solid currentColor;
  outline-offset: 1px;
}

/* Scoped transitions — badge hover + dismiss.
   Replaces the removed global `* { transition }` rule. */
.badge {
  transition:
    background-color 200ms ease-out,
    color 200ms ease-out,
    border-color 200ms ease-out,
    box-shadow 200ms ease-out,
    transform 150ms ease-out;
}

.badge.dismissing {
  transform: scale(0);
  opacity: 0;
}

/* Responsive adjustments */
@media (max-width: 640px) {
  .badge {
    font-size: 12px;
    padding: 2px 6px;
  }
}
</style>
