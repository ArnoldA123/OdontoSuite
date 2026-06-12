<template>
  <button
    :type="type"
    :disabled="disabled || loading"
    :aria-label="ariaLabel"
    :aria-describedby="ariaDescribedby"
    :aria-expanded="ariaExpanded"
    :aria-pressed="ariaPressed"
    :class="buttonClasses"
    @click="handleClick"
    @keydown="handleKeyDown"
  >
    <!-- Loading spinner -->
    <svg
      v-if="loading"
      class="animate-spin -ml-1 mr-2 h-4 w-4"
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
      ></circle>
      <path
        class="opacity-75"
        fill="currentColor"
        d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
      ></path>
    </svg>

    <!-- Icon (left) -->
    <component
      v-if="icon && !loading"
      :is="icon"
      :class="iconClasses"
    />

    <!-- Button content -->
    <span v-if="$slots.default" :class="textClasses">
      <slot />
    </span>

    <!-- Icon (right) -->
    <component
      v-if="iconRight && !loading"
      :is="iconRight"
      :class="[iconClasses, 'ml-2']"
    />
  </button>
</template>

<script>
import { computed } from 'vue'

export default {
  name: 'AccessibleButton',
  props: {
    type: {
      type: String,
      default: 'button'
    },
    variant: {
      type: String,
      default: 'primary',
      validator: (value) => ['primary', 'secondary', 'danger', 'success', 'warning', 'ghost'].includes(value)
    },
    size: {
      type: String,
      default: 'md',
      validator: (value) => ['sm', 'md', 'lg', 'xl'].includes(value)
    },
    disabled: {
      type: Boolean,
      default: false
    },
    loading: {
      type: Boolean,
      default: false
    },
    fullWidth: {
      type: Boolean,
      default: false
    },
    icon: {
      type: [String, Object],
      default: null
    },
    iconRight: {
      type: [String, Object],
      default: null
    },
    ariaLabel: {
      type: String,
      default: null
    },
    ariaDescribedby: {
      type: String,
      default: null
    },
    ariaExpanded: {
      type: [Boolean, String],
      default: null
    },
    ariaPressed: {
      type: [Boolean, String],
      default: null
    }
  },
  emits: ['click'],
  setup(props, { emit }) {
    const buttonClasses = computed(() => {
      const baseClasses = [
         'inline-flex items-center justify-center font-medium rounded-md transition-colors focus:outline-none focus:ring-2 disabled:opacity-50 disabled:cursor-not-allowed'
      ]

      // Size classes
      const sizeClasses = {
        sm: 'px-3 py-1.5 text-sm',
        md: 'px-4 py-2 text-sm',
        lg: 'px-6 py-3 text-base',
        xl: 'px-8 py-4 text-lg'
      }

      // Variant classes
      const variantClasses = {
        primary: 'bg-accent text-white hover:bg-accent-hover focus:ring-accent',
        secondary: 'bg-theme-surface text-theme-primary hover:bg-theme-surface-elevated focus:ring-primary-500',
        danger: 'bg-red-600 text-white hover:bg-red-700 focus:ring-red-500',
        success: 'bg-green-600 text-white hover:bg-green-700 focus:ring-green-500',
        warning: 'bg-yellow-600 text-white hover:bg-yellow-700 focus:ring-yellow-500',
        ghost: 'bg-transparent text-theme-primary hover:bg-theme-surface focus:ring-primary-500'
      }

      baseClasses.push(sizeClasses[props.size])
      baseClasses.push(variantClasses[props.variant])

      if (props.fullWidth) {
        baseClasses.push('w-full')
      }

      return baseClasses.join(' ')
    })

    const iconClasses = computed(() => {
      const sizeClasses = {
        sm: 'h-4 w-4',
        md: 'h-4 w-4',
        lg: 'h-5 w-5',
        xl: 'h-6 w-6'
      }
      return sizeClasses[props.size]
    })

    const textClasses = computed(() => {
      return props.loading ? 'opacity-75' : ''
    })

    const handleClick = (event) => {
      if (!props.disabled && !props.loading) {
        emit('click', event)
      }
    }

    const handleKeyDown = (event) => {
      // Handle Enter and Space keys
      if (event.key === 'Enter' || event.key === ' ') {
        event.preventDefault()
        handleClick(event)
      }
    }

    return {
      buttonClasses,
      iconClasses,
      textClasses,
      handleClick,
      handleKeyDown
    }
  }
}
</script>
