<template>
  <button
    :class="fabClasses"
    :aria-label="ariaLabel"
    @click="$emit('click')"
  >
    <slot />
  </button>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  position: { type: String, default: 'bottom-right' }, // bottom-right, bottom-left, top-right, top-left
  size: { type: String, default: 'lg' },
  // Slot content; if absent, the button renders an empty body (caller is
  // expected to provide an icon). ariaLabel is required for screen readers
  // since the slot might be decorative.
  ariaLabel: { type: String, default: 'Accion flotante' }
})

defineEmits(['click'])

// Solid terracotta fill, no gradient. Per design contract, gradients are
// decoration, this is a clinical tool used all day - so the FAB is a
// flat, single-token fill with shadow + hover scale.
const fabClasses = computed(() => {
  const base =
    'fixed z-50 flex items-center justify-center rounded-full shadow-large smooth-transition ' +
    'hover:scale-105 active:scale-95 ' +
    'bg-terracotta-500 text-cream-50 border border-terracotta-600 ' +
    'focus:outline-none focus-visible:ring-2 focus-visible:ring-terracotta-500 focus-visible:ring-offset-2'

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

  return [base, positions[props.position], sizes[props.size]].filter(Boolean).join(' ')
})
</script>
