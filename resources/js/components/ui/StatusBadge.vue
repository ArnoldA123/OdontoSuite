<template>
  <component
    :is="as"
    :class="badgeClasses"
    role="status"
    :aria-label="ariaLabel"
    :data-variant="variant"
  >
    <span
      v-if="showDot"
      class="inline-block w-1.5 h-1.5 rounded-full status-badge__dot"
      :class="dotClasses"
      aria-hidden="true"
    />
    <slot name="icon-left" />
    <slot>{{ label }}</slot>
  </component>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  variant: {
    type: String,
    default: 'neutral',
    validator: value => ['success', 'warning', 'error', 'info', 'neutral'].includes(value)
  },
  label: { type: [String, Number], default: null },
  size: {
    type: String,
    default: 'md',
    validator: value => ['sm', 'md'].includes(value)
  },
  showDot: { type: Boolean, default: false },
  as: {
    type: String,
    default: 'span',
    validator: value => ['span', 'div'].includes(value)
  }
})

const SIZE_CLASSES = {
  sm: 'px-2 py-0.5 text-xs min-h-[20px]',
  md: 'px-2.5 py-1 text-xs min-h-[24px]'
}

const VARIANT_CLASSES = {
  success: 'bg-systemGreen-50 text-systemGreen-700',
  warning: 'bg-systemYellow-50 text-systemYellow-700',
  error: 'bg-systemRed-50 text-systemRed-700',
  info: 'bg-systemBlue-50 text-systemBlue-700',
  neutral: 'bg-systemGray-100 text-systemGray-700'
}

const DOT_CLASSES = {
  success: 'bg-systemGreen-500',
  warning: 'bg-systemYellow-500',
  error: 'bg-systemRed-500',
  info: 'bg-systemBlue-500',
  neutral: 'bg-systemGray-500'
}

const badgeClasses = computed(() =>
  [
    'inline-flex items-center gap-1.5 rounded-full font-medium select-none status-badge',
    SIZE_CLASSES[props.size],
    VARIANT_CLASSES[props.variant]
  ].join(' ')
)

const dotClasses = computed(() => DOT_CLASSES[props.variant])

const ariaLabel = computed(() => (props.label ? `Estado: ${props.label}` : undefined))
</script>

<style scoped>
/* PR0 (ui-rollout-all-modules-2026-08) — StatusBadge primitive.
   Decorative only: colour washes (no transform; D10 of the vertical slice).
   Reduced-motion caps transition durations to 200ms (D11).
   Focus ring is composed via the project token (D6 / STATUS-PRIM-003). */
.status-badge {
  transition:
    background-color var(--motion-duration-normal) ease-out,
    color var(--motion-duration-normal) ease-out,
    box-shadow var(--motion-duration-normal) ease-out;
}

.status-badge:focus-visible {
  outline: none;
  box-shadow: var(--focus-ring-default);
}

@media (prefers-reduced-motion: reduce) {
  .status-badge {
    transition-duration: 200ms;
  }
}
</style>
