<template>
  <button
    :class="buttonClasses"
    :disabled="disabled || loading"
    :aria-label="ariaLabel"
    @click="handleClick"
  >
    <!-- Loading spinner -->
    <div v-if="loading" class="spinner" aria-hidden="true"></div>

    <!-- Icon slot (left) -->
    <slot v-if="!loading" name="icon-left" />

    <!-- Button content -->
    <span v-if="!loading" class="button-content">
      <slot />
    </span>

    <!-- Icon slot (right) -->
    <slot v-if="!loading" name="icon-right" />

    <!-- Ripple effect -->
    <span v-if="showRipple" class="ripple" :style="rippleStyle"></span>
  </button>
</template>

<script setup>
import { computed, ref, nextTick } from 'vue'

const props = defineProps({
  variant: {
    type: String,
    default: 'primary',
    validator: (value) => ['primary', 'secondary', 'ghost', 'danger', 'success', 'warning', 'icon'].includes(value)
  },
  size: {
    type: String,
    default: 'md',
    validator: (value) => ['xs', 'sm', 'md', 'lg', 'xl'].includes(value)
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
    'focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2',
    'disabled:opacity-50 disabled:cursor-not-allowed disabled:pointer-events-none',
    'overflow-hidden select-none'
  ]

  const variants = {
    primary: [
      'bg-accent hover:bg-accent-hover active:bg-accent-active',
      'text-white shadow-soft hover:shadow-medium',
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
    sm: 'px-3 py-1.5 text-sm rounded-lg min-h-[36px] gap-1.5',
    md: 'px-4 py-2 text-base rounded-lg min-h-[44px] gap-2',
    lg: 'px-6 py-3 text-lg rounded-xl min-h-[52px] gap-2.5',
    xl: 'px-8 py-4 text-xl rounded-xl min-h-[60px] gap-3'
  }

  // Special handling for icon variant
  const iconSizes = {
    xs: 'p-1 rounded-md min-h-[28px] min-w-[28px]',
    sm: 'p-1.5 rounded-lg min-h-[36px] min-w-[36px]',
    md: 'p-2 rounded-lg min-h-[44px] min-w-[44px]',
    lg: 'p-3 rounded-xl min-h-[52px] min-w-[52px]',
    xl: 'p-4 rounded-xl min-h-[60px] min-w-[60px]'
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
  ].filter(Boolean).join(' ')
})

const handleClick = async (event) => {
  if (props.disabled || props.loading) return

  // Ripple effect
  if (props.ripple) {
    await createRipple(event)
  }

  emit('click', event)
}

const createRipple = async (event) => {
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
   primitive makes on :hover / :focus-visible / :active. */
button {
  transition:
    background-color 200ms ease-out,
    border-color 200ms ease-out,
    color 200ms ease-out,
    box-shadow 200ms ease-out,
    transform 150ms ease-out;
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
  0% { transform: rotate(0deg); }
  100% { transform: rotate(360deg); }
}

@keyframes ripple {
  to {
    transform: scale(4);
    opacity: 0;
  }
}

/* Hover effects */
button:not(:disabled):hover {
  transform: translateY(-1px);
}

button:not(:disabled):active {
  transform: translateY(0);
}

/* Focus styles for accessibility — terracotta focus ring stays visible
   against cream backgrounds (accent vs. cream-100 = 4.6:1, AAA). */
button:focus-visible {
  outline: 2px solid var(--color-accent);
  outline-offset: 2px;
}

/* Loading state */
button:disabled {
  cursor: not-allowed;
  opacity: 0.6;
}

/* Icon variant specific styles */
button[data-variant="icon"] {
  aspect-ratio: 1;
}
</style>
