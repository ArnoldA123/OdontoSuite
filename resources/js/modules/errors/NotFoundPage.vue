<template>
  <AppLayout>
    <div class="min-h-[60vh] flex items-center justify-center px-4">
      <div class="max-w-lg w-full text-center">
        <div class="mx-auto w-24 h-24 rounded-full bg-theme-surface flex items-center justify-center mb-6">
          <svg
            class="w-12 h-12 text-theme-secondary"
            fill="none"
            stroke="currentColor"
            viewBox="0 0 24 24"
            aria-hidden="true"
          >
            <path
              stroke-linecap="round"
              stroke-linejoin="round"
              stroke-width="1.5"
              d="M9.879 16.121A3 3 0 1012.015 11L11 14H9c0 .768.293 1.536.879 2.121z"
            />
            <path
              stroke-linecap="round"
              stroke-linejoin="round"
              stroke-width="1.5"
              d="M3.124 7.5A8.969 8.969 0 015.292 3m13.416 0a8.969 8.969 0 012.168 4.5"
            />
          </svg>
        </div>
        <h1 class="text-3xl font-bold text-theme-primary mb-2">404</h1>
        <h2 class="text-xl font-semibold text-theme-primary mb-2">
          Página no encontrada
        </h2>
        <p class="text-theme-secondary mb-6">
          La ruta solicitada no existe o fue movida. Verifica la URL o vuelve al panel principal.
        </p>
        <div class="flex flex-col sm:flex-row gap-3 justify-center">
          <UiButton variant="primary" @click="goBack">
            <template #icon-left>
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
              </svg>
            </template>
            Volver
          </UiButton>
          <UiButton variant="secondary" @click="goHome">
            Ir al inicio
          </UiButton>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { useRouter } from 'vue-router'
import AppLayout from '@/components/layout/AppLayout.vue'
import UiButton from '@/components/ui/Button.vue'

const router = useRouter()

const goBack = () => {
  if (window.history.length > 1) {
    router.back()
  } else {
    router.push('/dashboard')
  }
}

const goHome = () => {
  router.push('/dashboard')
}
</script>
