<template>
  <button
    :class="buttonClasses"
    :data-variant="variant"
    :disabled="disabled || loading"
    :aria-label="ariaLabel"
    @click="handleClick"
  >
    <!-- Loading spinner -->
    <div v-if="loading" class="spinner" aria-hidden="true" />

    <!-- Icon slot (left) -->
    <slot v-if="!loading" name="icon-left" />

    <!-- Button content -->
    <span v-if="!loading" class="button-content">
      <slot />
    </span>

    <!-- Icon slot (right) -->
    <slot v-if="!loading" name="icon-right" />

    <!-- Ripple effect -->
    <span v-if="showRipple" class="ripple" :style="rippleStyle" />
  </button>
</template>

<script setup>
import { computed, ref, nextTick } from 'vue'

const props = defineProps({
  variant: {
    type: String,
    default: 'primary',
    validator: value =>
      ['primary', 'secondary', 'ghost', 'danger', 'success', 'warning', 'icon'].includes(value)
  },
  size: {
    type: String,
    default: 'md',
    validator: value => ['xs', 'sm', 'md', 'lg', 'xl'].includes(value)
  },
  disabled: { type: Boolean, default: false },
  loading: { type: Boolean, default: false },
  fullWidth: { type: Boolean, default: false },
  ripple: { type: Boolean, default: true },
  ariaLabel: { type: String, default: '' }
})

const emit = defineEmits(['click'])

// Ripple effect state
const showRipple = ref(false)
const rippleStyle = ref({})

const buttonClasses = computed(() => {
  const base = [
    'relative inline-flex items-center justify-center',
    'font-medium',
    // PR2 (D6): the Tailwind `focus:ring-*` trio is dropped in favour of the
    // tokenised `:focus-visible` ring declared in <style scoped>. Tailwind's
    // `focus:` variant also fires on pointer press; `:focus-visible` does not.
    'focus:outline-none',
    'disabled:opacity-50 disabled:cursor-not-allowed disabled:pointer-events-none',
    'overflow-hidden select-none'
  ]

  const variants = {
    // HOTFIX-LOGIN-004 — primary variant now ships the Apple premium
    // construction: elevation-3 (heavier rung, apple-design §12) +
    // inset 0 1px 0 rgba(255,255,255,0.32) highlight at the top edge so the
    // button reads as a real material that catches light, not a flat
    // rectangle. The :active translateY(1px) is response on pointer-down
    // (apple-design §1) — feedback fires the instant the user presses, not
    // on release. The full construction is declared in <style scoped> under
    // .button-primary so it survives Tailwind class compilation order.
    primary: [
      'bg-accent hover:bg-accent-hover active:bg-accent-active',
      'text-white',
      'border border-transparent'
    ],
    secondary: [
      'bg-transparent hover:bg-theme-surface active:bg-theme-surface-elevated',
      'text-theme-primary border border-theme hover:border-theme-strong',
      'shadow-subtle hover:shadow-soft'
    ],
    ghost: [
      'bg-transparent hover:bg-theme-surface active:bg-theme-surface-elevated',
      'text-theme-secondary hover:text-theme-primary',
      'border border-transparent'
    ],
    danger: [
      'bg-error-500 hover:bg-error-600 active:bg-error-700',
      'text-white shadow-soft hover:shadow-medium',
      'border border-transparent'
    ],
    success: [
      'bg-success-500 hover:bg-success-600 active:bg-success-700',
      'text-white shadow-soft hover:shadow-medium',
      'border border-transparent'
    ],
    warning: [
      'bg-warning-500 hover:bg-warning-600 active:bg-warning-700',
      'text-white shadow-soft hover:shadow-medium',
      'border border-transparent'
    ],
    icon: [
      'bg-transparent hover:bg-theme-surface active:bg-theme-surface-elevated',
      'text-theme-secondary hover:text-theme-primary',
      'border border-transparent rounded-full'
    ]
  }

  const sizes = {
    xs: 'px-2 py-1 text-xs rounded-md min-h-[28px] gap-1',
    sm: 'px-3 py-1.5 text-sm rounded-ios min-h-[36px] gap-1.5',
    md: 'px-4 py-2 text-base rounded-ios min-h-[44px] gap-2',
    lg: 'px-6 py-3 text-lg rounded-ios min-h-[52px] gap-2.5',
    xl: 'px-8 py-4 text-xl rounded-ios min-h-[60px] gap-3'
  }

  // Special handling for icon variant
  const iconSizes = {
    xs: 'p-1 rounded-md min-h-[28px] min-w-[28px]',
    sm: 'p-1.5 rounded-ios min-h-[36px] min-w-[36px]',
    md: 'p-2 rounded-ios min-h-[44px] min-w-[44px]',
    lg: 'p-3 rounded-ios min-h-[52px] min-w-[52px]',
    xl: 'p-4 rounded-ios min-h-[60px] min-w-[60px]'
  }

  const sizeClasses = props.variant === 'icon' ? iconSizes[props.size] : sizes[props.size]

  // Get variant classes, fallback to primary if variant is invalid
  const variantClasses = variants[props.variant] || variants.primary

  return [
    ...base,
    ...variantClasses,
    sizeClasses,
    props.fullWidth && props.variant !== 'icon' ? 'w-full' : '',
    props.loading ? 'cursor-wait' : ''
  ]
    .filter(Boolean)
    .join(' ')
})

const handleClick = async event => {
  if (props.disabled || props.loading) return

  // Ripple effect
  if (props.ripple) {
    await createRipple(event)
  }

  emit('click', event)
}

const createRipple = async event => {
  const button = event.currentTarget
  const rect = button.getBoundingClientRect()
  const size = Math.max(rect.width, rect.height)
  const x = event.clientX - rect.left - size / 2
  const y = event.clientY - rect.top - size / 2

  rippleStyle.value = {
    width: `${size}px`,
    height: `${size}px`,
    left: `${x}px`,
    top: `${y}px`
  }

  showRipple.value = true

  await nextTick()

  setTimeout(() => {
    showRipple.value = false
  }, 600)
}
</script>

<style scoped>
/* Scoped transitions — replace the removed global `* { transition }` rule
   from PR1. The four properties below cover every state change this
   primitive makes on :hover / :focus-visible / :active.
   PR2 (D8): the transform adopts the iOS curve; colour washes keep standard
   easing deliberately. Durations read from PR1's motion ramp. */
button {
  transition:
    background-color var(--motion-duration-normal) ease-out,
    border-color var(--motion-duration-normal) ease-out,
    color var(--motion-duration-normal) ease-out,
    box-shadow var(--motion-duration-normal) ease-out,
    transform var(--motion-duration-fast) var(--motion-easing-ios);
}

/* HOTFIX-LOGIN-004 — primary variant: Apple premium construction.
   elevation-3 (heavier shadow for the primary CTA, apple-design §12) +
   inset highlight at the top edge (the "light catching the material"
   trick from apple-design §12). Tokens are kept — no freeform values. */
button[data-variant='primary'] {
  box-shadow:
    var(--elevation-3),
    inset 0 1px 0 rgba(255, 255, 255, 0.32),
    0 0 0 1px rgba(0, 0, 0, 0.04);
}

/* HOTFIX-LOGIN-004 — :active translateY(1px) is response on pointer-down
   per apple-design §1. Feedback fires the instant the user presses, not
   on release. Only the primary variant presses down (others stay at 0,
   matching their existing behaviour). */
button[data-variant='primary']:not(:disabled):active {
  transform: translateY(1px);
}

.button-content {
  display: flex;
  align-items: center;
  gap: inherit;
}

.spinner {
  width: 16px;
  height: 16px;
  border: 2px solid transparent;
  border-top: 2px solid currentColor;
  border-radius: 50%;
  animation: spin 1s linear infinite;
}

.ripple {
  position: absolute;
  border-radius: 50%;
  background: rgba(255, 255, 255, 0.3);
  transform: scale(0);
  animation: ripple 0.6s ease-out;
  pointer-events: none;
}

@keyframes spin {
  0% {
    transform: rotate(0deg);
  }
  100% {
    transform: rotate(360deg);
  }
}

@keyframes ripple {
  to {
    transform: scale(4);
    opacity: 0;
  }
}

/* Hover effects — EXCLUDE [data-magnetic='true'] so the additive magnetic
   transform (composed via the --spring-magnet-x/y CSS vars) wins at runtime.
   Without the exclusion, the hover rule (specificity 0,2,1) silently
   overrides the data-magnetic rule (0,1,1) — see apply-progress.md F-03. */
button:not(:disabled):not([data-magnetic='true']):hover {
  transform: translateY(-1px);
}

/* HOTFIX-LOGIN-004 — the primary variant has its own :active rule above
   (translateY(1px) for response-on-pointer-down, apple-design §1). All
   other variants settle to translateY(0) on press, matching their
   pre-existing behaviour. */
button:not([data-variant='primary']):not(:disabled):active {
  transform: translateY(0);
}

/* Focus styles for accessibility — PR2 (D6) replaces the inline outline with
   the tokenised ring. `:focus-visible` (not `:focus`) so a pointer press does
   not paint a ring. */
button:focus-visible {
  outline: none;
  box-shadow: var(--focus-ring-default);
}

/* Loading state */
button:disabled {
  cursor: not-allowed;
  opacity: 0.6;
}

/* Icon variant specific styles */
button[data-variant='icon'] {
  aspect-ratio: 1;
}

/* Reduced motion (D11) — the hover lift and press settle are movement, so they
   collapse to an opacity change on the same rung. Feedback survives; the
   translate goes. Durations are never zeroed. */
@media (prefers-reduced-motion: reduce) {
  button {
    transition:
      background-color var(--motion-duration-normal) ease-out,
      border-color var(--motion-duration-normal) ease-out,
      color var(--motion-duration-normal) ease-out,
      box-shadow var(--motion-duration-normal) ease-out,
      opacity var(--motion-duration-normal) ease-out;
  }

  button:not(:disabled):hover {
    transform: none;
    opacity: 0.88;
  }

  /* HOTFIX-LOGIN-004 — primary variant keeps its feedback on press even
     under reduced motion: the response on pointer-down is feedback, not
     movement (apple-design §14). Other variants collapse to opacity. */
  button:not(:disabled):active {
    transform: none;
    opacity: 0.72;
  }

  button[data-variant='primary']:not(:disabled):active {
    transform: translateY(1px);
  }

  .spinner,
  .ripple {
    animation: none;
  }
}

/* Additive (PR1 ui-login-premium-motion-2026-08, design D17 + spec M7):
     applies the magnetic effect ONLY when --spring-magnet-x/y are defined
     on the button root via the data-magnetic attribute. Other buttons are
     untouched. The hover lift composes with the magnet via transform
     composition. NO change to the template, the variants, the ripple
     logic, or any existing scoped CSS — appended at the END of the
     existing <style scoped> block, after the prefers-reduced-motion block. */
button:not(:disabled)[data-magnetic='true'] {
  transform: translate3d(var(--spring-magnet-x, 0), calc(var(--spring-magnet-y, 0) - 1px), 0);
}
button[data-magnetic='true']:not(:disabled):active {
  /* On press the magnet is inert (cursor is on the button, not hovering
       around it). The existing translateY(1px) takes over. */
  transform: translateY(1px);
}
@media (prefers-reduced-motion: reduce) {
  button:not(:disabled)[data-magnetic='true'] {
    transform: none;
  }
}
</style>
