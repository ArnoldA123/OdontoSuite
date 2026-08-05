<template>
  <Transition name="slide">
    <div v-if="isOpen" class="lg:hidden fixed inset-0 z-50">
      <div class="fixed inset-0 bg-black/50" @click="$emit('close')" />
      <div class="fixed inset-y-0 right-0 w-64 bg-theme-surface-elevated shadow-2xl">
        <div class="flex flex-col h-full">
          <!-- Mobile Header -->
          <div class="flex items-center justify-between px-6 py-4 border-b border-theme">
            <img src="/images/easy_dent.png" alt="OdontoSuite" class="h-8 w-auto" />
            <button @click="$emit('close')" class="p-2 rounded-xl hover:bg-theme-surface">
              <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
              </svg>
            </button>
          </div>

          <!-- Mobile Navigation -->
          <nav class="flex-1 px-3 py-4 space-y-1">
            <slot />
          </nav>
        </div>
      </div>
    </div>
  </Transition>
</template>

<script setup>
defineProps({
  isOpen: { type: Boolean, default: false }
})

defineEmits(['close'])
</script>

<style scoped>
.slide-enter-active, .slide-leave-active {
  transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

.slide-enter-from {
  transform: translateX(100%);
}

.slide-leave-to {
  transform: translateX(100%);
}
</style>





























