<template>
  <Teleport to="body">
    <Transition
      name="modal"
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
      >
        <Transition
          name="modal-content"
          enter-active-class="transition-all duration-300 ease-out"
          enter-from-class="opacity-0 scale-95 translate-y-4"
          enter-to-class="opacity-100 scale-100 translate-y-0"
          leave-active-class="transition-all duration-200 ease-in"
          leave-from-class="opacity-100 scale-100 translate-y-0"
          leave-to-class="opacity-0 scale-95 translate-y-4"
        >
          <div
            v-if="modelValue"
            ref="modalRef"
            :class="modalClasses"
            :role="role"
            tabindex="-1"
            :aria-labelledby="titleId"
            :aria-describedby="descriptionId"
            :aria-modal="role === 'dialog' ? 'true' : undefined"
            @click.stop
          >
            <!-- Modal header -->
            <div v-if="$slots.header || title" class="modal-header">
              <slot name="header">
                <h2 :id="titleId" class="modal-title">{{ title }}</h2>
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

            <!-- Modal body -->
            <div class="modal-body">
              <slot />
            </div>

            <!-- Modal footer -->
            <div v-if="$slots.footer" class="modal-footer">
              <slot name="footer" />
            </div>
          </div>
        </Transition>
      </div>
    </Transition>
  </Teleport>
</template>

<script setup>
import { computed, ref, watch, onBeforeUnmount, useId } from 'vue'
import { useFocusTrap } from '../../composables/useFocusTrap'

const props = defineProps({
  modelValue: { type: Boolean, default: false },
  title: String,
  size: {
    type: String,
    default: 'md',
    validator: (value) => ['sm', 'md', 'lg', 'xl', 'full'].includes(value)
  },
  variant: {
    type: String,
    default: 'default',
    validator: (value) => ['default', 'centered', 'top', 'bottom'].includes(value)
  },
  closable: { type: Boolean, default: true },
  closeOnBackdrop: { type: Boolean, default: true },
  closeOnEscape: { type: Boolean, default: true },
  persistent: { type: Boolean, default: false },
  role: { type: String, default: 'dialog' }
})

const emit = defineEmits(['update:modelValue', 'close', 'open'])

// Stable IDs across re-renders (Vue 3.5+ useId); fallback to a per-instance ref
// so SSR / older toolchains still produce deterministic values.
const _id = useId ? useId() : `modal-${Math.random().toString(36).slice(2, 10)}`
const titleId = computed(() => `modal-title-${_id}`)
const descriptionId = computed(() => `modal-description-${_id}`)

const closeLabel = computed(() => 'Cerrar modal')

const backdropClasses = computed(() => [
  'fixed inset-0 z-50 flex items-center justify-center',
  'bg-black/50 backdrop-blur-sm',
  props.variant === 'centered' && 'items-center',
  props.variant === 'top' && 'items-start pt-16',
  props.variant === 'bottom' && 'items-end pb-16'
])

const modalClasses = computed(() => {
  const base = [
    'relative bg-theme-surface-elevated rounded-modal shadow-2xl',
    'max-h-screen overflow-hidden',
    'focus:outline-none'
  ]

  // Size variants
  const sizes = {
    sm: 'w-full max-w-sm',
    md: 'w-full max-w-md',
    lg: 'w-full max-w-lg',
    xl: 'w-full max-w-xl',
    full: 'w-full max-w-full h-full max-h-full rounded-none'
  }

  return [
    ...base,
    sizes[props.size]
  ].join(' ')
})

const closeButtonClasses = computed(() => [
  'absolute top-4 right-4 p-2 rounded-ios',
  'text-theme-secondary hover:text-theme-primary',
  'hover:bg-theme-surface',
  'focus:outline-none focus:ring-2 focus:ring-systemBlue-500 focus:ring-offset-2'
])

// Focus management (WCAG 2.1.1 + 2.4.3)
const modalRef = ref(null)
const focusTrap = useFocusTrap()

// Event handlers
const handleBackdropClick = () => {
  if (props.closeOnBackdrop && !props.persistent) {
    handleClose()
  }
}

const handleEscape = (event) => {
  if (props.closeOnEscape && !props.persistent) {
    event.preventDefault()
    event.stopPropagation()
    handleClose()
  }
}

const handleClose = () => {
  emit('update:modelValue', false)
  emit('close')
}

// Watch for modelValue changes
watch(
  () => props.modelValue,
  (newValue) => {
    if (newValue) {
      emit('open')
      document.addEventListener('keydown', handleDocumentEscape)
      document.body.style.overflow = 'hidden'
      focusTrap.activate(modalRef.value)
    } else {
      document.removeEventListener('keydown', handleDocumentEscape)
      document.body.style.overflow = ''
      focusTrap.release()
    }
  }
)

// Use document-level listener so Escape is captured even when focus is on a
// nested control (e.g. select dropdown). The Modal listens via document so the
// keydown is decoupled from the focused element.
function handleDocumentEscape(event) {
  if (event.key === 'Escape' || event.key === 'Esc') {
    handleEscape(event)
  }
}

onBeforeUnmount(() => {
  document.removeEventListener('keydown', handleDocumentEscape)
  document.body.style.overflow = ''
  focusTrap.release()
})
</script>

<style scoped>
.modal-header {
  @apply flex items-center justify-between p-6 border-b border-theme;
}

.modal-title {
  @apply text-lg font-semibold text-theme-primary;
}

.modal-body {
  @apply p-6 overflow-y-auto;
}

.modal-footer {
  @apply flex items-center justify-end gap-3 p-6 border-t border-theme bg-theme-surface;
}

/* Modal animations */
.modal-enter-active,
.modal-leave-active {
  transition: opacity 0.3s ease;
}

.modal-enter-from,
.modal-leave-to {
  opacity: 0;
}

.modal-content-enter-active {
  transition: all 0.3s ease-out;
}

.modal-content-leave-active {
  transition: all 0.2s ease-in;
}

.modal-content-enter-from {
  opacity: 0;
  transform: scale(0.95) translateY(16px);
}

.modal-content-leave-to {
  opacity: 0;
  transform: scale(0.95) translateY(16px);
}

/* Focus trap */
.modal:focus {
  outline: none;
}

/* Responsive adjustments */
@media (max-width: 640px) {
  .modal {
    margin: 16px;
    max-width: calc(100vw - 32px);
  }

  .modal-header,
  .modal-body,
  .modal-footer {
    padding: 16px;
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
  .modal-enter-active,
  .modal-leave-active,
  .modal-content-enter-active,
  .modal-content-leave-active {
    transition: none;
  }
}

/* High contrast mode support — boundary label-label lifts the modal edge
   against the systemBackground surface for high-contrast users. */
@media (prefers-contrast: high) {
  .modal {
    border: 2px solid var(--color-label-label);
  }

  .modal-header,
  .modal-footer {
    border-color: var(--color-label-label);
  }
}

/* Scoped transitions — close button hover/focus colors. */
button.modal-close {
  transition:
    background-color 200ms ease-out,
    color 200ms ease-out,
    border-color 200ms ease-out,
    box-shadow 200ms ease-out;
}
</style>
