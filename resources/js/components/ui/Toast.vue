<template>
  <Transition
    name="toast"
    enter-active-class="transition-all duration-300 ease-out"
    enter-from-class="opacity-0 translate-y-2 scale-95"
    enter-to-class="opacity-100 translate-y-0 scale-100"
    leave-active-class="transition-all duration-200 ease-in"
    leave-from-class="opacity-100 translate-y-0 scale-100"
    leave-to-class="opacity-0 translate-y-2 scale-95"
  >
    <div
      v-if="visible"
      :class="toastClasses"
      :role="role"
      :aria-live="ariaLive"
      :aria-atomic="ariaAtomic"
    >
      <!-- Toast icon -->
      <div v-if="icon || $slots.icon" class="toast-icon">
        <slot name="icon">
          <component :is="iconComponent" v-if="iconComponent" class="w-5 h-5" />
        </slot>
      </div>

      <!-- Toast content -->
      <div class="toast-content">
        <div v-if="title" class="toast-title">{{ title }}</div>
        <div v-if="$slots.default" class="toast-message">
          <slot />
        </div>
      </div>

      <!-- Toast actions -->
      <div v-if="$slots.actions || dismissible" class="toast-actions">
        <slot name="actions" />
        <button
          v-if="dismissible"
          type="button"
          :class="dismissButtonClasses"
          :aria-label="dismissLabel"
          @click="handleDismiss"
        >
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
          </svg>
        </button>
      </div>
    </div>
  </Transition>
</template>

<script setup>
import { computed, ref, onMounted, onUnmounted } from 'vue'

const props = defineProps({
  type: {
    type: String,
    default: 'info',
    validator: (value) => ['success', 'error', 'warning', 'info'].includes(value)
  },
  title: String,
  duration: { type: Number, default: 5000 },
  dismissible: { type: Boolean, default: true },
  persistent: { type: Boolean, default: false },
  position: {
    type: String,
    default: 'top-right',
    validator: (value) => ['top-left', 'top-center', 'top-right', 'bottom-left', 'bottom-center', 'bottom-right'].includes(value)
  }
})

const emit = defineEmits(['dismiss', 'close'])

// State
const visible = ref(false)
const timeoutId = ref(null)

const dismissLabel = computed(() => 'Cerrar notificación')

const role = computed(() => {
  const roles = {
    success: 'status',
    error: 'alert',
    warning: 'alert',
    info: 'status'
  }
  return roles[props.type]
})

const ariaLive = computed(() => {
  const live = {
    success: 'polite',
    error: 'assertive',
    warning: 'assertive',
    info: 'polite'
  }
  return live[props.type]
})

const ariaAtomic = computed(() => 'true')

const toastClasses = computed(() => {
  const base = [
    'relative flex items-start gap-3 p-4 rounded-lg shadow-lg',
    'max-w-sm w-full',
    'transition-all duration-200',
    'focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2'
  ]

  // Type variants
  const types = {
    success: [
      'bg-green-50 border border-green-200',
      'text-green-800'
    ],
    error: [
      'bg-red-50 border border-red-200',
      'text-red-800'
    ],
    warning: [
      'bg-yellow-50 border border-yellow-200',
      'text-yellow-800'
    ],
    info: [
      'bg-primary-50 border border-primary-200',
      'text-primary-800'
    ]
  }

  return [
    ...base,
    ...types[props.type]
  ].join(' ')
})

const iconComponent = computed(() => {
  const icons = {
    success: 'CheckCircleIcon',
    error: 'XCircleIcon',
    warning: 'ExclamationTriangleIcon',
    info: 'InformationCircleIcon'
  }
  return icons[props.type]
})

const dismissButtonClasses = computed(() => [
  'p-1 rounded-md',
  'text-current/60 hover:text-current',
  'hover:bg-current/10 transition-colors duration-200',
  'focus:outline-none focus:ring-2 focus:ring-current focus:ring-offset-1'
])

// Auto-dismiss timer
const startTimer = () => {
  if (props.persistent || props.duration <= 0) return

  timeoutId.value = setTimeout(() => {
    handleDismiss()
  }, props.duration)
}

const clearTimer = () => {
  if (timeoutId.value) {
    clearTimeout(timeoutId.value)
    timeoutId.value = null
  }
}

// Event handlers
const handleDismiss = () => {
  visible.value = false
  clearTimer()
  emit('dismiss')

  // Emit close after animation
  setTimeout(() => {
    emit('close')
  }, 200)
}

// Lifecycle
onMounted(() => {
  visible.value = true
  startTimer()
})

onUnmounted(() => {
  clearTimer()
})

// Pause timer on hover
const handleMouseEnter = () => {
  clearTimer()
}

const handleMouseLeave = () => {
  startTimer()
}
</script>

<style scoped>
.toast-icon {
  @apply flex-shrink-0 mt-1;
}

.toast-content {
  @apply flex-1 min-w-0;
}

.toast-title {
  @apply text-sm font-semibold;
}

.toast-message {
  @apply text-sm mt-1;
}

.toast-actions {
  @apply flex items-center gap-2 flex-shrink-0;
}

/* Toast animations */
.toast-enter-active {
  transition: all 0.3s ease-out;
}

.toast-leave-active {
  transition: all 0.2s ease-in;
}

.toast-enter-from {
  opacity: 0;
  transform: translateY(8px) scale(0.95);
}

.toast-leave-to {
  opacity: 0;
  transform: translateY(8px) scale(0.95);
}

/* Hover effects */
.toast:hover {
  transform: translateY(-1px);
  box-shadow: var(--shadow-large);
}

/* Focus styles */
.toast:focus-visible {
  outline: 2px solid var(--color-accent);
  outline-offset: 2px;
}

/* Type-specific icon colors */
.toast-icon .w-5.h-5 {
  @apply text-current;
}

/* Responsive adjustments */
@media (max-width: 640px) {
  .toast {
    max-width: calc(100vw - 32px);
    margin: 0 16px;
  }
}

/* High contrast mode support */
@media (prefers-contrast: high) {
  .toast {
    border-width: 2px;
  }
}

/* Reduced motion support */
@media (prefers-reduced-motion: reduce) {
  .toast-enter-active,
  .toast-leave-active {
    transition: none;
  }

  .toast:hover {
    transform: none;
  }
}
</style>
