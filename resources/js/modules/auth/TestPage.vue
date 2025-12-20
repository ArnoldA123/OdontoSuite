<template>
  <div class="min-h-screen bg-primary-500 flex items-center justify-center">
    <div class="bg-theme-surface-elevated p-8 rounded-lg shadow-lg">
      <h1 class="text-2xl font-bold text-theme-primary mb-4">EasyDent - Prueba</h1>
      <p class="text-theme-secondary">Si ves esto, Vue.js está funcionando correctamente.</p>
      <button @click="testClick" class="mt-4 bg-primary-500 text-white px-4 py-2 rounded">
        Probar Click ({{ count }})
      </button>
    </div>
  </div>
</template>

<script>
import { ref } from 'vue'

export default {
  name: 'TestPage',
  setup() {
    const count = ref(0)

    const testClick = () => {
      count.value++
    }

    return {
      count,
      testClick
    }
  }
}
</script>


