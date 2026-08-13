<template>
  <UiModal :model-value="show" :closable="false">
    <div class="p-8 text-center">
      <!-- Animación de IA procesando -->
      <div class="relative w-24 h-24 mx-auto mb-6">
        <div class="absolute inset-0 bg-gradient-accent rounded-full animate-pulse" />
        <CpuChipIcon class="absolute inset-0 m-auto w-12 h-12 text-white animate-spin-slow" />
      </div>

      <h3 class="text-xl font-semibold mb-2">Analizando Imagen...</h3>
      <p class="text-theme-secondary mb-6">
        La IA está procesando la imagen. Esto puede tomar unos segundos.
      </p>

      <!-- Pasos del proceso -->
      <div class="space-y-3 text-left max-w-md mx-auto">
        <div class="step" :class="[{ completed: step >= 1 }]">
          <CheckCircleIcon v-if="step >= 1" class="w-5 h-5 text-green-500" />
          <div v-else class="w-5 h-5 border-2 border-theme rounded-full" />
          <span>Subiendo imagen...</span>
        </div>
        <div class="step" :class="[{ completed: step >= 2 }]">
          <CheckCircleIcon v-if="step >= 2" class="w-5 h-5 text-green-500" />
          <div v-else class="w-5 h-5 border-2 border-theme rounded-full" />
          <span>Procesando con IA...</span>
        </div>
        <div class="step" :class="[{ completed: step >= 3 }]">
          <CheckCircleIcon v-if="step >= 3" class="w-5 h-5 text-green-500" />
          <div v-else class="w-5 h-5 border-2 border-theme rounded-full" />
          <span>Generando hallazgos...</span>
        </div>
      </div>
    </div>
  </UiModal>
</template>

<script setup>
import { defineProps } from 'vue'
import UiModal from '@/components/ui/Modal.vue'
import { CpuChipIcon, CheckCircleIcon } from '@heroicons/vue/24/outline'

const props = defineProps({
  show: {
    type: Boolean,
    default: false
  },
  step: {
    type: Number,
    default: 0
  }
})
</script>

<style scoped>
.step {
  @apply flex items-center gap-3 text-sm;
}

.step.completed {
  @apply text-green-600;
}

.animate-spin-slow {
  animation: spin 3s linear infinite;
}

@keyframes spin {
  from {
    transform: rotate(0deg);
  }
  to {
    transform: rotate(360deg);
  }
}
</style>
