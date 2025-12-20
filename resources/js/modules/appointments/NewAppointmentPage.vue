<template>
  <div class="min-h-screen bg-theme-background flex items-center justify-center">
    <div class="text-center">
      <div class="animate-spin rounded-full h-12 w-12 border-4 border-purple-200 border-t-purple-600 mx-auto mb-4"></div>
      <p class="text-theme-secondary">Redirigiendo al dashboard...</p>
    </div>
  </div>
</template>

<script setup>
import { onMounted } from 'vue'
import { useRouter } from 'vue-router'

const router = useRouter()

onMounted(() => {
  // Redirigir al dashboard - el modal se abrirá automáticamente si hay un query param
  router.push('/dashboard?openAppointmentModal=true')
})
</script>

<!-- 
  Este componente está deprecado. 
  La creación de citas ahora se maneja mediante un modal reutilizable.
  Redirigimos al dashboard donde el usuario puede abrir el modal.
-->
