<template>
  <div class="currency-input">
    <label v-if="label" :for="inputId" class="block text-sm font-medium text-theme-primary mb-1">
      {{ label }}
      <span v-if="required" class="text-red-500">*</span>
    </label>

    <div class="relative">
      <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
        <span class="text-theme-secondary sm:text-sm">S/</span>
      </div>

      <input
        :id="inputId"
        ref="input"
        v-model="displayValue"
        :type="type"
        :placeholder="placeholder"
        :disabled="disabled"
        :readonly="readonly"
        :class="inputClasses"
        @input="handleInput"
        @blur="handleBlur"
        @focus="handleFocus"
        @keydown="handleKeydown"
      />

      <div
        v-if="error"
        class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none"
      >
        <ExclamationCircleIcon class="h-5 w-5 text-red-500" />
      </div>
    </div>

    <p v-if="error" class="mt-1 text-sm text-red-600">
      {{ error }}
    </p>
    <p v-else-if="help" class="mt-1 text-sm text-theme-secondary">
      {{ help }}
    </p>
  </div>
</template>

<script setup>
import { ref, computed, watch, nextTick } from 'vue'
import { ExclamationCircleIcon } from '@heroicons/vue/24/outline'

const props = defineProps({
  modelValue: {
    type: [Number, String],
    default: 0
  },
  label: {
    type: String,
    default: ''
  },
  placeholder: {
    type: String,
    default: '0.00'
  },
  disabled: {
    type: Boolean,
    default: false
  },
  readonly: {
    type: Boolean,
    default: false
  },
  required: {
    type: Boolean,
    default: false
  },
  error: {
    type: String,
    default: ''
  },
  help: {
    type: String,
    default: ''
  },
  min: {
    type: Number,
    default: 0
  },
  max: {
    type: Number,
    default: null
  },
  precision: {
    type: Number,
    default: 2
  },
  allowNegative: {
    type: Boolean,
    default: false
  },
  type: {
    type: String,
    default: 'text'
  }
})

const emit = defineEmits(['update:modelValue', 'blur', 'focus'])

const input = ref(null)
const isFocused = ref(false)
const inputId = `currency-input-${Math.random().toString(36).substr(2, 9)}`

// Valor numérico interno
const numericValue = ref(parseFloat(props.modelValue) || 0)

// Valor de visualización
const displayValue = computed({
  get() {
    if (isFocused.value) {
      // Mostrar valor sin formatear cuando está enfocado
      return numericValue.value.toString()
    } else {
      // Formatear cuando no está enfocado
      return formatCurrency(numericValue.value)
    }
  },
  set(value) {
    // No hacer nada aquí, se maneja en handleInput
  }
})

// Clases del input
const inputClasses = computed(() => {
  const baseClasses =
    'block w-full pl-8 pr-10 py-2 border rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-accent sm:text-sm'

  if (props.error) {
    return `${baseClasses} border-red-300 text-red-900 placeholder-red-300 bg-theme-surface-elevated`
  } else if (props.disabled) {
    return `${baseClasses} bg-theme-surface border-theme text-theme-secondary cursor-not-allowed opacity-50`
  } else if (props.readonly) {
    return `${baseClasses} bg-theme-surface border-theme text-theme-primary`
  } else {
    return `${baseClasses} border-theme text-theme-primary placeholder-theme-secondary bg-theme-surface-elevated`
  }
})

// Formatear moneda
const formatCurrency = value => {
  if (isNaN(value) || value === null || value === undefined) return '0.00'

  return new Intl.NumberFormat('es-PE', {
    minimumFractionDigits: props.precision,
    maximumFractionDigits: props.precision
  }).format(value)
}

// Manejar input
const handleInput = event => {
  let { value } = event.target

  // Remover caracteres no numéricos excepto punto y signo negativo
  value = value.replace(/[^\d.-]/g, '')

  // Permitir solo un punto decimal
  const parts = value.split('.')
  if (parts.length > 2) {
    value = `${parts[0]}.${parts.slice(1).join('')}`
  }

  // Permitir solo un signo negativo al inicio
  if (value.includes('-') && value.indexOf('-') !== 0) {
    value = value.replace(/-/g, '')
  }

  // Convertir a número
  const numValue = parseFloat(value) || 0

  // Validar límites
  if (numValue < props.min) {
    numericValue.value = props.min
  } else if (props.max !== null && numValue > props.max) {
    numericValue.value = props.max
  } else {
    numericValue.value = numValue
  }

  // Emitir valor
  emit('update:modelValue', numericValue.value)
}

// Manejar blur
const handleBlur = () => {
  isFocused.value = false
  emit('blur', numericValue.value)
}

// Manejar focus
const handleFocus = () => {
  isFocused.value = true
  emit('focus', numericValue.value)

  // Seleccionar todo el texto cuando se enfoca
  nextTick(() => {
    if (input.value) {
      input.value.select()
    }
  })
}

// Manejar teclas especiales
const handleKeydown = event => {
  // Permitir teclas de control
  if (
    [
      'Backspace',
      'Delete',
      'Tab',
      'Escape',
      'Enter',
      'ArrowLeft',
      'ArrowRight',
      'ArrowUp',
      'ArrowDown'
    ].includes(event.key)
  ) {
    return
  }

  // Permitir Ctrl+A, Ctrl+C, Ctrl+V, Ctrl+X
  if (event.ctrlKey && ['a', 'c', 'v', 'x'].includes(event.key.toLowerCase())) {
    return
  }

  // Permitir números, punto decimal y signo negativo
  if (!/[\d.-]/.test(event.key)) {
    event.preventDefault()
  }

  // Validar punto decimal
  if (event.key === '.' && numericValue.value.toString().includes('.')) {
    event.preventDefault()
  }

  // Validar signo negativo
  if (event.key === '-' && (!props.allowNegative || numericValue.value.toString().includes('-'))) {
    event.preventDefault()
  }
}

// Watch para cambios externos
watch(
  () => props.modelValue,
  newValue => {
    const numValue = parseFloat(newValue) || 0
    if (numValue !== numericValue.value) {
      numericValue.value = numValue
    }
  }
)

// Exponer métodos
const focus = () => {
  if (input.value) {
    input.value.focus()
  }
}

const blur = () => {
  if (input.value) {
    input.value.blur()
  }
}

const select = () => {
  if (input.value) {
    input.value.select()
  }
}

defineExpose({
  focus,
  blur,
  select
})
</script>

<style scoped>
.currency-input input::-webkit-outer-spin-button,
.currency-input input::-webkit-inner-spin-button {
  -webkit-appearance: none;
  margin: 0;
}

.currency-input input[type='number'] {
  -moz-appearance: textfield;
}
</style>
