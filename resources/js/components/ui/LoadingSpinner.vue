<template>
  <div :class="containerClasses" :aria-label="ariaLabel">
    <div :class="spinnerClasses" :style="spinnerStyle">
      <div class="spinner-ring"></div>
      <div class="spinner-ring"></div>
      <div class="spinner-ring"></div>
    </div>
    <p v-if="text" :class="textClasses">{{ text }}</p>
  </div>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  size: {
    type: String,
    default: 'md',
    validator: (value) => ['xs', 'sm', 'md', 'lg', 'xl'].includes(value)
  },
  variant: {
    type: String,
    default: 'primary',
    validator: (value) => ['primary', 'secondary', 'white', 'gray'].includes(value)
  },
  text: {
    type: String,
    default: ''
  },
  centered: {
    type: Boolean,
    default: true
  },
  ariaLabel: {
    type: String,
    default: 'Cargando...'
  }
})

const containerClasses = computed(() => [
  'loading-spinner-container',
  props.centered ? 'flex flex-col items-center justify-center' : 'flex items-center gap-3',
  'text-center'
])

const spinnerClasses = computed(() => {
  const sizes = {
    xs: 'w-4 h-4',
    sm: 'w-6 h-6',
    md: 'w-8 h-8',
    lg: 'w-12 h-12',
    xl: 'w-16 h-16'
  }

  return [
    'loading-spinner',
    sizes[props.size]
  ].join(' ')
})

const textClasses = computed(() => {
  const sizes = {
    xs: 'text-xs',
    sm: 'text-sm',
    md: 'text-base',
    lg: 'text-lg',
    xl: 'text-xl'
  }

  const variants = {
    primary: 'text-accent',
    secondary: 'text-theme-secondary',
    white: 'text-white',
    gray: 'text-theme-secondary'
  }

  return [
    'loading-spinner-text',
    'mt-2',
    sizes[props.size],
    variants[props.variant]
  ].join(' ')
})

const spinnerStyle = computed(() => {
  const colors = {
    primary: 'var(--color-system-blue-500)',
    secondary: 'var(--color-text-secondary)',
    white: 'var(--color-background-system-background)',
    gray: 'var(--color-text-secondary)'
  }

  return {
    '--spinner-color': colors[props.variant]
  }
})
</script>

<style scoped>
.loading-spinner {
  position: relative;
  display: inline-block;
}

.spinner-ring {
  position: absolute;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  border: 2px solid transparent;
  border-top: 2px solid var(--spinner-color);
  border-radius: 50%;
  animation: spin 1.2s cubic-bezier(0.5, 0, 0.5, 1) infinite;
}

.spinner-ring:nth-child(1) {
  animation-delay: -0.45s;
}

.spinner-ring:nth-child(2) {
  animation-delay: -0.3s;
}

.spinner-ring:nth-child(3) {
  animation-delay: -0.15s;
}

@keyframes spin {
  0% {
    transform: rotate(0deg);
  }
  100% {
    transform: rotate(360deg);
  }
}

/* Accessibility */
.loading-spinner-container[aria-label] {
  role: status;
  aria-live: polite;
}

/* Reduced motion support */
@media (prefers-reduced-motion: reduce) {
  .spinner-ring {
    animation: none;
    border-top: 2px solid var(--spinner-color);
  }

  .spinner-ring:nth-child(2),
  .spinner-ring:nth-child(3) {
    display: none;
  }
}
</style>




















