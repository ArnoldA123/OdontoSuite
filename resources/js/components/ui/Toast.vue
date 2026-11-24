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
        <div v-if="title" class="toast-title">
          {{ title }}
        </div>
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
            <path
              stroke-linecap="round"
              stroke-linejoin="round"
              stroke-width="2"
              d="M6 18L18 6M6 6l12 12"
            />
          </svg>
        </button>
      </div>

      <!-- Barra de progreso: arranca al montar, se reinicia cuando cambia la
           clave o la duración y desaparece con el toast. -->
      <div
        v-if="progress && !persistent && duration > 0"
        :key="`${toastKey ?? 'toast'}:${duration}`"
        class="toast-progress"
        :style="{ animationDuration: `${duration}ms` }"
        data-toast-progress
        aria-hidden="true"
      />
    </div>
  </Transition>
</template>

<script setup>
import { computed, ref, watch, onMounted, onUnmounted } from 'vue'
import {
  CheckCircleIcon,
  ExclamationTriangleIcon,
  InformationCircleIcon,
  XCircleIcon
} from '@heroicons/vue/24/outline'

const props = defineProps({
  type: {
    type: String,
    default: 'info',
    validator: value => ['success', 'error', 'warning', 'info'].includes(value)
  },
  title: String,
  icon: { type: Boolean, default: true },
  duration: { type: Number, default: 5000 },
  dismissible: { type: Boolean, default: true },
  persistent: { type: Boolean, default: false },
  progress: { type: Boolean, default: true },
  toastKey: { type: [String, Number], default: null },
  position: {
    type: String,
    default: 'top-right',
    validator: value =>
      [
        'top-left',
        'top-center',
        'top-right',
        'bottom-left',
        'bottom-center',
        'bottom-right'
      ].includes(value)
  }
})

const emit = defineEmits(['dismiss', 'close'])

// State
const visible = ref(false)
const mounted = ref(false)
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
    'relative flex items-start gap-3 p-4 rounded-ios shadow-lg',
    'max-w-sm w-full',
    'focus:outline-none'
  ]

  // Type variants (iOS filled pattern).
  const types = {
    success: ['bg-systemGreen-50 border border-systemGreen-200', 'text-systemGreen-700'],
    error: ['bg-systemRed-50 border border-systemRed-200', 'text-systemRed-700'],
    warning: ['bg-systemYellow-50 border border-systemYellow-200', 'text-systemYellow-700'],
    info: ['bg-systemBlue-50 border border-systemBlue-200', 'text-systemBlue-700']
  }

  return [...base, ...types[props.type]].join(' ')
})

const iconComponent = computed(() => {
  const icons = {
    success: CheckCircleIcon,
    error: XCircleIcon,
    warning: ExclamationTriangleIcon,
    info: InformationCircleIcon
  }
  return icons[props.type]
})

const dismissButtonClasses = computed(() => [
  'p-1 rounded-ios',
  'text-current/60 hover:text-current',
  'hover:bg-current/10',
  'focus:outline-none'
])

// Auto-dismiss timer
const startTimer = () => {
  clearTimer()
  if (props.persistent || !(props.duration > 0)) return

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

const restartTimer = () => {
  if (!mounted.value) return
  startTimer()
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
  mounted.value = true
  visible.value = true
  startTimer()
})

// La misma clave actualizada (o una duración nueva) rearma la cuenta en vez
// de heredar el temporizador anterior.
watch(() => [props.toastKey, props.duration, props.persistent], restartTimer)

onUnmounted(() => {
  mounted.value = false
  clearTimer()
})
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

/* Focus styles — PR2 (D6/G1) tokenised ring on the toast itself and on its
   dismiss button. Replaces the inline outline and the Tailwind `focus:ring-*`
   utilities dropped above. */
.toast:focus-visible,
button:focus-visible {
  outline: none;
  box-shadow: var(--focus-ring-default);
}

/* Type-specific icon colors */
.toast-icon .w-5.h-5 {
  @apply text-current;
}

.toast-progress {
  position: absolute;
  inset-inline: 0;
  bottom: 0;
  height: 2px;
  background-color: currentColor;
  opacity: 0.45;
  transform-origin: left center;
  animation-name: toast-progress-shrink;
  animation-timing-function: linear;
  animation-fill-mode: forwards;
}

@keyframes toast-progress-shrink {
  from {
    transform: scaleX(1);
  }
  to {
    transform: scaleX(0);
  }
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

/* Scoped transitions — toast hover and dismiss. Replaces the removed
   global `* { transition }` rule. */
.toast {
  transition:
    background-color 200ms ease-out,
    color 200ms ease-out,
    border-color 200ms ease-out,
    box-shadow 200ms ease-out,
    transform 150ms ease-out;
}

button {
  transition:
    background-color 200ms ease-out,
    color 200ms ease-out;
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

  .toast-progress {
    animation: none;
  }
}
</style>
