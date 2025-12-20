<template>
  <button
    :class="fabClasses"
    @click="$emit('click')"
  >
    <slot />
  </button>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  position: { type: String, default: 'bottom-right' }, // bottom-right, bottom-left, top-right, top-left
  size: { type: String, default: 'lg' }
})

defineEmits(['click'])

const fabClasses = computed(() => {
  const base = 'fixed z-50 flex items-center justify-center rounded-full shadow-2xl smooth-transition hover:scale-110 active:scale-95 bg-gradient-to-b from-purple-500 to-purple-600 text-white'

  const positions = {
    'bottom-right': 'bottom-6 right-6',
    'bottom-left': 'bottom-6 left-6',
    'top-right': 'top-20 right-6',
    'top-left': 'top-20 left-6'
  }

  const sizes = {
    md: 'w-12 h-12',
    lg: 'w-14 h-14',
    xl: 'w-16 h-16'
  }

  return [base, positions[props.position], sizes[props.size]].join(' ')
})
</script>
