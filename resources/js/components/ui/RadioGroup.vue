<template>
  <div class="space-y-3">
    <label v-if="label" class="block text-sm font-medium text-theme-primary">{{ label }}</label>
    <div :class="horizontal ? 'flex gap-3' : 'space-y-2'">
      <label
        v-for="option in options"
        :key="option.value"
        :class="optionClasses(option.value)"
      >
        <input
          type="radio"
          :name="name"
          :value="option.value"
          :checked="modelValue === option.value"
          @change="$emit('update:modelValue', option.value)"
          class="sr-only"
        />
        <span class="flex items-center justify-center gap-2">
          <span :class="radioIndicator(option.value)" />
          {{ option.label }}
        </span>
      </label>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  label: String,
  name: { type: String, required: true },
  modelValue: [String, Number, Boolean],
  options: { type: Array, required: true },
  horizontal: { type: Boolean, default: false }
})

defineEmits(['update:modelValue'])

const optionClasses = (value) => {
  const base = 'flex-1 px-4 py-3 rounded-xl border-2 cursor-pointer smooth-transition text-center font-medium'
  const selected = props.modelValue === value
    ? 'border-accent bg-primary-50 text-primary-700'
    : 'border-theme bg-theme-surface-elevated hover:border-theme-strong text-theme-primary'
  return [base, selected].join(' ')
}

const radioIndicator = (value) => {
  const base = 'w-5 h-5 rounded-full border-2 smooth-transition'
  const selected = props.modelValue === value
    ? 'border-accent bg-accent shadow-inner'
    : 'border-theme bg-theme-surface-elevated'
  return [base, selected].join(' ')
}
</script>
