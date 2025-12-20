<template>
  <Teleport to="body">
    <Transition
      name="sheet"
      enter-active-class="transition-opacity duration-300"
      enter-from-class="opacity-0"
      enter-to-class="opacity-100"
      leave-active-class="transition-opacity duration-200"
      leave-from-class="opacity-100"
      leave-to-class="opacity-0"
    >
      <div
        v-if="modelValue"
        :class="backdropClasses"
        @click="handleBackdropClick"
        @keydown.esc="handleEscape"
      >
        <Transition
          :name="sheetTransition"
          :enter-active-class="enterActiveClass"
          :enter-from-class="enterFromClass"
          :enter-to-class="enterToClass"
          :leave-active-class="leaveActiveClass"
          :leave-from-class="leaveFromClass"
          :leave-to-class="leaveToClass"
        >
          <div
            v-if="modelValue"
            :class="sheetClasses"
            :role="role"
            :aria-labelledby="titleId"
            :aria-describedby="descriptionId"
            @click.stop
          >
            <!-- Handle for mobile -->
            <div v-if="showHandle" class="sheet-handle"></div>

            <!-- Sheet header -->
            <div v-if="$slots.header || title" class="sheet-header">
              <slot name="header">
                <h2 :id="titleId" class="sheet-title">{{ title }}</h2>
                <button
                  v-if="closable"
                  type="button"
                  :class="closeButtonClasses"
                  :aria-label="closeLabel"
                  @click="handleClose"
                >
                  <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                  </svg>
                </button>
              </slot>
            </div>

            <!-- Sheet body -->
            <div class="sheet-body">
              <slot />
            </div>

            <!-- Sheet footer -->
            <div v-if="$slots.footer" class="sheet-footer">
              <slot name="footer" />
            </div>
          </div>
        </Transition>
      </div>
    </Transition>
  </Teleport>
</template>

<script setup>
import { computed, watch, nextTick } from 'vue'

const props = defineProps({
  modelValue: { type: Boolean, default: false },
  title: String,
  position: {
    type: String,
    default: 'bottom',
    validator: (value) => ['top', 'bottom', 'left', 'right'].includes(value)
  },
  size: {
    type: String,
    default: 'md',
    validator: (value) => ['sm', 'md', 'lg', 'xl', 'full'].includes(value)
  },
  closable: { type: Boolean, default: true },
  closeOnBackdrop: { type: Boolean, default: true },
  closeOnEscape: { type: Boolean, default: true },
  persistent: { type: Boolean, default: false },
  showHandle: { type: Boolean, default: true },
  role: { type: String, default: 'dialog' }
})

const emit = defineEmits(['update:modelValue', 'close', 'open'])

// Generate unique IDs
const titleId = computed(() => `sheet-title-${Math.random().toString(36).substr(2, 9)}`)
const descriptionId = computed(() => `sheet-description-${Math.random().toString(36).substr(2, 9)}`)

const closeLabel = computed(() => 'Cerrar panel')

const backdropClasses = computed(() => [
  'fixed inset-0 z-50 flex',
  'bg-black/50 backdrop-blur-sm',
  props.position === 'top' && 'items-start',
  props.position === 'bottom' && 'items-end',
  props.position === 'left' && 'items-center justify-start',
  props.position === 'right' && 'items-center justify-end'
])

const sheetClasses = computed(() => {
  const base = [
    'relative bg-theme-surface-elevated shadow-lg',
    'flex flex-col max-h-screen',
    'focus:outline-none'
  ]

  // Position-specific classes
  const positions = {
    top: 'w-full rounded-b-2xl',
    bottom: 'w-full rounded-t-2xl',
    left: 'h-full rounded-r-2xl',
    right: 'h-full rounded-l-2xl'
  }

  // Size variants
  const sizes = {
    sm: props.position === 'top' || props.position === 'bottom' ? 'max-h-96' : 'max-w-sm',
    md: props.position === 'top' || props.position === 'bottom' ? 'max-h-3xl' : 'max-w-md',
    lg: props.position === 'top' || props.position === 'bottom' ? 'max-h-5xl' : 'max-w-lg',
    xl: props.position === 'top' || props.position === 'bottom' ? 'max-h-6xl' : 'max-w-xl',
    full: 'w-full h-full rounded-none'
  }

  return [
    ...base,
    positions[props.position],
    sizes[props.size]
  ].join(' ')
})

const sheetTransition = computed(() => {
  const transitions = {
    top: 'slide-down',
    bottom: 'slide-up',
    left: 'slide-right',
    right: 'slide-left'
  }
  return transitions[props.position]
})

const enterActiveClass = computed(() => {
  const classes = {
    top: 'transition-all duration-300 ease-out',
    bottom: 'transition-all duration-300 ease-out',
    left: 'transition-all duration-300 ease-out',
    right: 'transition-all duration-300 ease-out'
  }
  return classes[props.position]
})

const enterFromClass = computed(() => {
  const classes = {
    top: 'opacity-0 -translate-y-full',
    bottom: 'opacity-0 translate-y-full',
    left: 'opacity-0 -translate-x-full',
    right: 'opacity-0 translate-x-full'
  }
  return classes[props.position]
})

const enterToClass = computed(() => {
  return 'opacity-100 translate-x-0 translate-y-0'
})

const leaveActiveClass = computed(() => {
  const classes = {
    top: 'transition-all duration-200 ease-in',
    bottom: 'transition-all duration-200 ease-in',
    left: 'transition-all duration-200 ease-in',
    right: 'transition-all duration-200 ease-in'
  }
  return classes[props.position]
})

const leaveFromClass = computed(() => {
  return 'opacity-100 translate-x-0 translate-y-0'
})

const leaveToClass = computed(() => {
  const classes = {
    top: 'opacity-0 -translate-y-full',
    bottom: 'opacity-0 translate-y-full',
    left: 'opacity-0 -translate-x-full',
    right: 'opacity-0 translate-x-full'
  }
  return classes[props.position]
})

const closeButtonClasses = computed(() => [
  'p-2 rounded-lg',
  'text-theme-secondary hover:text-theme-primary',
  'hover:bg-theme-surface transition-colors duration-200',
  'focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2'
])

// Event handlers
const handleBackdropClick = () => {
  if (props.closeOnBackdrop && !props.persistent) {
    handleClose()
  }
}

const handleEscape = () => {
  if (props.closeOnEscape && !props.persistent) {
    handleClose()
  }
}

const handleClose = () => {
  emit('update:modelValue', false)
  emit('close')
}

// Watch for modelValue changes
watch(() => props.modelValue, (newValue) => {
  if (newValue) {
    emit('open')
    // Focus the sheet when it opens
    nextTick(() => {
      const sheet = document.querySelector('[role="dialog"]')
      if (sheet) sheet.focus()
    })
  }
})

// Prevent body scroll when sheet is open
watch(() => props.modelValue, (newValue) => {
  if (newValue) {
    document.body.style.overflow = 'hidden'
  } else {
    document.body.style.overflow = ''
  }
})
</script>

<style scoped>
.sheet-handle {
  @apply w-12 h-1 bg-theme-secondary rounded-full mx-auto mt-2 mb-4;
}

.sheet-header {
  @apply flex items-center justify-between p-6 border-b border-theme;
}

.sheet-title {
  @apply text-lg font-semibold text-theme-primary;
}

.sheet-body {
  @apply flex-1 p-6 overflow-y-auto;
}

.sheet-footer {
  @apply flex items-center justify-end gap-3 p-6 border-t border-theme bg-theme-surface;
}

/* Sheet animations */
.sheet-enter-active,
.sheet-leave-active {
  transition: opacity 0.3s ease;
}

.sheet-enter-from,
.sheet-leave-to {
  opacity: 0;
}

/* Slide animations */
.slide-up-enter-active,
.slide-up-leave-active {
  transition: all 0.3s ease-out;
}

.slide-up-enter-from {
  opacity: 0;
  transform: translateY(100%);
}

.slide-up-leave-to {
  opacity: 0;
  transform: translateY(100%);
}

.slide-down-enter-active,
.slide-down-leave-active {
  transition: all 0.3s ease-out;
}

.slide-down-enter-from {
  opacity: 0;
  transform: translateY(-100%);
}

.slide-down-leave-to {
  opacity: 0;
  transform: translateY(-100%);
}

.slide-left-enter-active,
.slide-left-leave-active {
  transition: all 0.3s ease-out;
}

.slide-left-enter-from {
  opacity: 0;
  transform: translateX(100%);
}

.slide-left-leave-to {
  opacity: 0;
  transform: translateX(100%);
}

.slide-right-enter-active,
.slide-right-leave-active {
  transition: all 0.3s ease-out;
}

.slide-right-enter-from {
  opacity: 0;
  transform: translateX(-100%);
}

.slide-right-leave-to {
  opacity: 0;
  transform: translateX(-100%);
}

/* Focus trap */
.sheet:focus {
  outline: none;
}

/* Responsive adjustments */
@media (max-width: 640px) {
  .sheet {
    margin: 0;
    max-width: 100vw;
    max-height: 100vh;
  }

  .sheet-header,
  .sheet-body,
  .sheet-footer {
    padding: 16px;
  }

  .sheet-handle {
    @apply mt-1 mb-2;
  }
}

/* Full screen variant */
[data-size="full"] {
  margin: 0;
  max-width: 100vw;
  max-height: 100vh;
  border-radius: 0;
}

/* Accessibility improvements */
@media (prefers-reduced-motion: reduce) {
  .sheet-enter-active,
  .sheet-leave-active,
  .slide-up-enter-active,
  .slide-up-leave-active,
  .slide-down-enter-active,
  .slide-down-leave-active,
  .slide-left-enter-active,
  .slide-left-leave-active,
  .slide-right-enter-active,
  .slide-right-leave-active {
    transition: none;
  }
}

/* High contrast mode support */
@media (prefers-contrast: high) {
  .sheet {
    border: 2px solid var(--color-neutral-900);
  }

  .sheet-header,
  .sheet-footer {
    border-color: var(--color-neutral-900);
  }
}
</style>
