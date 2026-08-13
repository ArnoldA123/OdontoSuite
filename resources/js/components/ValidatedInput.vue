<template>
  <div class="form-group-responsive">
    <label v-if="label" :for="inputId" class="form-label-responsive">
      {{ label }}
      <span v-if="required" class="text-red-500">*</span>
    </label>

    <div class="relative">
      <input
        :id="inputId"
        :type="type"
        :value="modelValue"
        :placeholder="placeholder"
        :disabled="disabled"
        :readonly="readonly"
        :required="required"
        :autocomplete="autocomplete"
        :class="inputClasses"
        :aria-invalid="hasError"
        :aria-describedby="hasError ? `${inputId}-error` : undefined"
        @input="handleInput"
        @blur="handleBlur"
        @focus="handleFocus"
      />

      <!-- Icon -->
      <div v-if="icon" class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
        <component :is="icon" class="h-5 w-5 text-theme-secondary" />
      </div>

      <!-- Loading spinner -->
      <div v-if="loading" class="absolute inset-y-0 right-0 pr-3 flex items-center">
        <svg
          class="animate-spin h-5 w-5 text-theme-secondary"
          xmlns="http://www.w3.org/2000/svg"
          fill="none"
          viewBox="0 0 24 24"
        >
          <circle
            class="opacity-25"
            cx="12"
            cy="12"
            r="10"
            stroke="currentColor"
            stroke-width="4"
          />
          <path
            class="opacity-75"
            fill="currentColor"
            d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
          />
        </svg>
      </div>
    </div>

    <!-- Error message -->
    <p
      v-if="shouldShowError"
      :id="`${inputId}-error`"
      class="mt-1 text-sm text-red-600"
      role="alert"
    >
      {{ errorMessage }}
    </p>

    <!-- Help text -->
    <p v-else-if="helpText" class="mt-1 text-sm text-theme-secondary">
      {{ helpText }}
    </p>
  </div>
</template>

<script>
import { computed, ref } from 'vue'

export default {
  name: 'ValidatedInput',
  props: {
    modelValue: {
      type: [String, Number],
      default: ''
    },
    type: {
      type: String,
      default: 'text'
    },
    label: {
      type: String,
      default: null
    },
    placeholder: {
      type: String,
      default: null
    },
    helpText: {
      type: String,
      default: null
    },
    required: {
      type: Boolean,
      default: false
    },
    disabled: {
      type: Boolean,
      default: false
    },
    readonly: {
      type: Boolean,
      default: false
    },
    autocomplete: {
      type: String,
      default: null
    },
    icon: {
      type: [String, Object],
      default: null
    },
    loading: {
      type: Boolean,
      default: false
    },
    // Validation props
    hasError: {
      type: Boolean,
      default: false
    },
    errorMessage: {
      type: String,
      default: null
    },
    shouldShowError: {
      type: Boolean,
      default: false
    }
  },
  emits: ['update:modelValue', 'blur', 'focus', 'input'],
  setup(props, { emit }) {
    const inputId = ref(`input-${Math.random().toString(36).substr(2, 9)}`)

    const inputClasses = computed(() => {
      const baseClasses = [
        'form-input-responsive',
        'block w-full rounded-md border-theme shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm bg-theme-surface-elevated text-theme-primary'
      ]

      if (props.icon) {
        baseClasses.push('pl-10')
      }

      if (props.loading) {
        baseClasses.push('pr-10')
      }

      if (props.hasError) {
        baseClasses.push('border-red-300 focus:border-red-500 focus:ring-red-500')
      }

      if (props.disabled) {
        baseClasses.push('bg-theme-surface text-theme-secondary cursor-not-allowed opacity-50')
      }

      if (props.readonly) {
        baseClasses.push('bg-theme-surface')
      }

      return baseClasses.join(' ')
    })

    const handleInput = event => {
      emit('update:modelValue', event.target.value)
      emit('input', event)
    }

    const handleBlur = event => {
      emit('blur', event)
    }

    const handleFocus = event => {
      emit('focus', event)
    }

    return {
      inputId,
      inputClasses,
      handleInput,
      handleBlur,
      handleFocus
    }
  }
}
</script>
