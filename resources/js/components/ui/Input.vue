<template>
  <div class="input-wrapper" :class="wrapperClasses">
    <!-- Floating label -->
    <label
      v-if="label"
      :for="inputId"
      :class="labelClasses"
      :data-floating="floatingLabel"
    >
      {{ label }}
    </label>

    <!-- Input container -->
    <div class="relative">
      <!-- Prefix icon -->
      <div v-if="$slots.prefix" class="prefix-icon">
        <slot name="prefix" />
      </div>

      <!-- Input field -->
      <input
        :id="inputId"
        :type="type"
        :value="modelValue"
        :placeholder="floatingLabel ? '' : placeholder"
        :disabled="disabled"
        :readonly="readonly"
        :class="inputClasses"
        :aria-describedby="hintId"
        :aria-invalid="!!error"
        @input="handleInput"
        @focus="handleFocus"
        @blur="handleBlur"
        @keydown="handleKeydown"
      />

      <!-- Clear button -->
      <button
        v-if="showClearButton"
        type="button"
        :class="clearButtonClasses"
        :aria-label="clearButtonLabel"
        @click="clearInput"
      >
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
        </svg>
      </button>

      <!-- Suffix icon -->
      <div v-if="$slots.suffix && !showClearButton" class="suffix-icon">
        <slot name="suffix" />
      </div>
    </div>

    <!-- Helper text -->
    <div v-if="error || hint" class="helper-text">
      <p v-if="error" :id="hintId" class="text-error-500">
        {{ error }}
      </p>
      <p v-else-if="hint" :id="hintId" class="text-theme-secondary">
        {{ hint }}
      </p>
    </div>
  </div>
</template>

<script setup>
import { computed, ref, nextTick, useSlots } from 'vue'

const slots = useSlots()

const props = defineProps({
  id: String,
  label: String,
  type: {
    type: String,
    default: 'text',
    validator: (value) => ['text', 'email', 'password', 'number', 'tel', 'url', 'search', 'date', 'time', 'datetime-local'].includes(value)
  },
  modelValue: [String, Number],
  placeholder: String,
  disabled: { type: Boolean, default: false },
  readonly: { type: Boolean, default: false },
  error: String,
  hint: String,
  size: {
    type: String,
    default: 'md',
    validator: (value) => ['sm', 'md', 'lg'].includes(value)
  },
  variant: {
    type: String,
    default: 'default',
    validator: (value) => ['default', 'filled', 'outlined'].includes(value)
  },
  clearable: { type: Boolean, default: false },
  floatingLabel: { type: Boolean, default: false }
})

const emit = defineEmits(['update:modelValue', 'blur', 'focus', 'clear'])

// Generate unique IDs
const inputId = computed(() => props.id || `input-${Math.random().toString(36).substr(2, 9)}`)
const hintId = computed(() => `${inputId.value}-hint`)

// Focus state
const isFocused = ref(false)

// Computed properties
const hasValue = computed(() => props.modelValue !== null && props.modelValue !== undefined && props.modelValue !== '')
const showClearButton = computed(() => props.clearable && hasValue.value && !props.disabled && !props.readonly)
const clearButtonLabel = computed(() => 'Limpiar campo')

const wrapperClasses = computed(() => {
  const base = ['input-wrapper']

  if (props.error) base.push('error')
  if (props.disabled) base.push('disabled')
  if (isFocused.value) base.push('focused')
  if (hasValue.value) base.push('has-value')

  return base.join(' ')
})

const labelClasses = computed(() => {
  const base = [
    'block text-sm font-medium',
    'pointer-events-none select-none'
  ]

  if (props.floatingLabel) {
    base.push('absolute left-3 z-10 origin-top-left')

    if (isFocused.value || hasValue.value) {
      base.push('top-1 scale-75 text-accent')
    } else {
      base.push('top-1/2 -translate-y-1/2 text-theme-secondary')
    }
  } else {
    base.push('mb-2 text-theme-primary')
  }

  if (props.error) base.push('text-error-500')

  return base.join(' ')
})

const inputClasses = computed(() => {
  // PR2 (D6): the Tailwind `focus:ring-*` utilities are dropped — the ring is
  // now the tokenised `box-shadow` in <style scoped>. Tailwind's ring utility
  // would win on specificity and shadow the token, so it cannot simply coexist.
  const base = [
    'block w-full border',
    'focus:outline-none',
    'disabled:opacity-50 disabled:cursor-not-allowed',
    'read-only:cursor-default'
  ]

  // Size variants
  const sizes = {
    sm: 'px-3 py-2 text-sm rounded-md min-h-[36px]',
    md: 'px-4 py-3 text-base rounded-ios min-h-[44px]',
    lg: 'px-5 py-4 text-lg rounded-ios min-h-[52px]'
  }

  // Variant styles
  const variants = {
    default: 'bg-theme-surface-elevated border-theme focus:border-accent',
    filled: 'bg-theme-surface border-theme focus:bg-theme-surface-elevated focus:border-accent',
    outlined: 'bg-transparent border-2 border-theme focus:border-accent'
  }

  // State styles
  const state = props.error
    ? 'border-error-500 focus:border-error-500'
    : variants[props.variant]

  // Padding adjustments for icons
  const hasPrefix = !!slots.prefix
  const hasSuffix = !!slots.suffix || showClearButton.value

  const padding = [
    hasPrefix ? 'pl-10' : '',
    hasSuffix ? 'pr-10' : '',
    props.floatingLabel ? 'pt-6' : ''
  ].filter(Boolean).join(' ')

  return [
    ...base,
    sizes[props.size],
    state,
    padding
  ].join(' ')
})

const clearButtonClasses = computed(() => [
  'absolute inset-y-0 right-0 flex items-center pr-3',
  'text-theme-secondary hover:text-theme-primary',
  'focus:outline-none',
  'rounded-md'
])

// Event handlers
const handleInput = (event) => {
  emit('update:modelValue', event.target.value)
}

const handleFocus = (event) => {
  isFocused.value = true
  emit('focus', event)
}

const handleBlur = (event) => {
  isFocused.value = false
  emit('blur', event)
}

const handleKeydown = (event) => {
  if (event.key === 'Escape' && showClearButton.value) {
    clearInput()
  }
}

const clearInput = () => {
  emit('update:modelValue', '')
  emit('clear')

  // Focus the input after clearing
  nextTick(() => {
    const input = document.getElementById(inputId.value)
    if (input) input.focus()
  })
}
</script>

<style scoped>
.input-wrapper {
  position: relative;
  display: flex;
  flex-direction: column;
}

.input-wrapper.focused .label {
  color: var(--color-accent);
}

.input-wrapper.error .label {
  color: var(--color-error-500);
}

.input-wrapper.disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.prefix-icon,
.suffix-icon {
  position: absolute;
  top: 50%;
  transform: translateY(-50%);
  display: flex;
  align-items: center;
  justify-content: center;
  pointer-events: none;
  color: var(--color-text-secondary);
  z-index: 10;
}

.prefix-icon {
  left: 12px;
}

.suffix-icon {
  right: 12px;
}

/* Permitir pointer-events en botones y elementos interactivos dentro del suffix */
.suffix-icon > * {
  pointer-events: auto;
}

.suffix-icon button {
  cursor: pointer;
  background: transparent;
  border: none;
  padding: 0;
  display: flex;
  align-items: center;
  justify-content: center;
}

.helper-text {
  margin-top: 4px;
  min-height: 20px;
}

.helper-text p {
  font-size: 12px;
  line-height: 1.4;
  font-weight: 400;
}

/* Helper text usa clases temáticas de Tailwind */

/* Floating label animation — scoped to the floating-label mode.
   Replaces the removed global `* { transition }` rule.
   PR2 (D8): the label's scale/translate is a transform, so it takes the iOS
   curve. Note there is deliberately NO press transform on Input — a scale on
   :active would fight text selection. */
[data-floating="true"] {
  transform-origin: top left;
  transition:
    color var(--motion-duration-normal) ease-out,
    transform var(--motion-duration-fast) var(--motion-easing-ios);
}

/* Scoped input transitions for hover, focus, and disabled states. Pure colour
   and ring washes, so standard easing is kept deliberately (D8). */
input {
  transition:
    background-color var(--motion-duration-normal) ease-out,
    border-color var(--motion-duration-normal) ease-out,
    color var(--motion-duration-normal) ease-out,
    box-shadow var(--motion-duration-normal) ease-out;
}

button {
  transition:
    background-color var(--motion-duration-normal) ease-out,
    color var(--motion-duration-normal) ease-out,
    border-color var(--motion-duration-normal) ease-out;
}

/* Focus ring for accessibility — PR2 (D6) tokenised. */
input:focus-visible,
input:focus {
  outline: none;
  box-shadow: var(--focus-ring-default);
}

button:focus-visible {
  outline: none;
  box-shadow: var(--focus-ring-default);
}

/* Clear button hover effect */
.clear-button:hover {
  background-color: var(--color-surface);
}

/* Disabled state */
input:disabled {
  background-color: var(--color-surface);
  cursor: not-allowed;
}

/* Readonly state */
input:read-only {
  background-color: var(--color-background-secondary);
  cursor: default;
}

/* Error state — the ring stays distinct so the invalid field reads at a glance,
   but it is composed from the PR1 focus-ring PARTS (width + alpha) and tinted
   with the systemRed-500 channels (255, 59, 48). The Tailwind red this rule
   previously used belongs to a different palette and must not reappear (D6). */
.input-wrapper.error input {
  border-color: var(--color-error-500);
}

.input-wrapper.error input:focus {
  border-color: var(--color-error-500);
  box-shadow: 0 0 0 var(--focus-ring-width) rgba(255, 59, 48, var(--focus-ring-alpha));
}

/* Success state (if needed) — same composition, systemGreen-500 channels. */
.input-wrapper.success input {
  border-color: var(--color-success-500);
}

.input-wrapper.success input:focus {
  border-color: var(--color-success-500);
  box-shadow: 0 0 0 var(--focus-ring-width) rgba(52, 199, 89, var(--focus-ring-alpha));
}
</style>
