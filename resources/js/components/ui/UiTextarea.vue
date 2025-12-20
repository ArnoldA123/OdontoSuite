<template>
  <textarea
    :value="modelValue"
    @input="$emit('update:modelValue', $event.target.value)"
    :placeholder="placeholder"
    :rows="rows"
    :disabled="disabled"
    :class="[
      'w-full px-3 py-2 border border-theme rounded-md shadow-sm',
      'focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-accent',
      'bg-theme-surface-elevated text-theme-primary',
      'disabled:bg-theme-surface disabled:opacity-50 disabled:cursor-not-allowed',
      error ? 'border-red-500 focus:ring-red-500 focus:border-red-500' : '',
      className
    ]"
  />
</template>

<script setup>
defineProps({
  modelValue: {
    type: String,
    default: ''
  },
  placeholder: {
    type: String,
    default: ''
  },
  rows: {
    type: Number,
    default: 3
  },
  disabled: {
    type: Boolean,
    default: false
  },
  error: {
    type: Boolean,
    default: false
  },
  className: {
    type: String,
    default: ''
  }
})

defineEmits(['update:modelValue'])
</script>
