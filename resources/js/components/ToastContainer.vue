<template>
  <div
    aria-live="assertive"
    class="pointer-events-none fixed inset-x-4 top-20 z-fixed ml-auto flex max-w-sm flex-col sm:inset-x-auto sm:right-6"
    data-toast-stack
  >
    <div
      ref="stackEl"
      class="pointer-events-auto flex max-h-[calc(100dvh-6.5rem)] w-full max-w-sm flex-col overflow-y-auto overscroll-contain"
    >
      <TransitionGroup
        tag="div"
        class="flex w-full max-w-sm flex-col gap-3"
        data-toast-list
        enter-active-class="transform ease-out duration-300 transition"
        enter-from-class="translate-y-2 opacity-0 sm:translate-y-0"
        enter-to-class="translate-y-0 opacity-100"
        leave-active-class="transition ease-in duration-100"
        leave-from-class="opacity-100"
        leave-to-class="opacity-0"
        move-class="transition duration-300"
      >
        <div
          v-for="toast in globalToasts"
          :key="toast.id"
          class="pointer-events-auto relative w-full max-w-sm overflow-hidden rounded-lg bg-theme-surface-elevated shadow-lg ring-1 ring-black/5"
          :data-toast-id="toast.id"
          :data-toast-key="toast.key"
        >
          <div class="p-4">
            <div class="flex items-start">
              <div class="flex-shrink-0">
                <!-- Success Icon -->
                <svg
                  v-if="toast.type === 'success'"
                  class="h-6 w-6 text-green-400"
                  fill="none"
                  viewBox="0 0 24 24"
                  stroke-width="1.5"
                  stroke="currentColor"
                >
                  <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"
                  />
                </svg>

                <!-- Error Icon -->
                <svg
                  v-else-if="toast.type === 'error'"
                  class="h-6 w-6 text-red-400"
                  fill="none"
                  viewBox="0 0 24 24"
                  stroke-width="1.5"
                  stroke="currentColor"
                >
                  <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z"
                  />
                </svg>

                <!-- Warning Icon -->
                <svg
                  v-else-if="toast.type === 'warning'"
                  class="h-6 w-6 text-yellow-400"
                  fill="none"
                  viewBox="0 0 24 24"
                  stroke-width="1.5"
                  stroke="currentColor"
                >
                  <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z"
                  />
                </svg>

                <!-- Info Icon -->
                <svg
                  v-else
                  class="h-6 w-6 text-accent"
                  fill="none"
                  viewBox="0 0 24 24"
                  stroke-width="1.5"
                  stroke="currentColor"
                >
                  <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    d="M11.25 11.25l.041-.02a.75.75 0 011.063.852l-.708 2.836a.75.75 0 001.063.853l.041-.021M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9-3.75h.008v.008H12V8.25z"
                  />
                </svg>
              </div>

              <div class="ml-3 w-0 flex-1 pt-0.5">
                <p v-if="toast.title" class="text-sm font-medium text-theme-primary mb-1">
                  {{ toast.title }}
                </p>
                <p class="text-sm text-theme-secondary whitespace-pre-line">
                  {{ toast.message }}
                </p>
              </div>

              <div v-if="toast.dismissible" class="ml-4 flex flex-shrink-0">
                <button
                  type="button"
                  class="inline-flex rounded-md text-theme-secondary hover:text-theme-primary focus:outline-none focus:ring-2 focus:ring-primary-500"
                  @click="removeToast(toast.id)"
                >
                  <span class="sr-only">Cerrar</span>
                  <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                    <path
                      fill-rule="evenodd"
                      d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z"
                      clip-rule="evenodd"
                    />
                  </svg>
                </button>
              </div>
            </div>
          </div>

          <!-- Barra de progreso: se reinicia con la revisión del toast, que sube
               cuando la misma clave actualiza el aviso. -->
          <div
            v-if="toast.persistent !== true && Number(toast.duration) > 0"
            :key="`${toast.id}:${toast.revision ?? 0}`"
            class="toast-progress"
            :class="progressClasses(toast.type)"
            :style="{ animationDuration: `${toast.duration}ms` }"
            data-toast-progress
            aria-hidden="true"
          />
        </div>
      </TransitionGroup>
    </div>
  </div>
</template>

<script setup>
import { nextTick, onMounted, onUnmounted, ref, watch } from 'vue'
import { globalToasts, useToast } from '../composables/useToast.js'

const { removeToast, setRailHeight } = useToast()

// El riel es compartido: este alto es el que coloca el aviso persistente
// debajo de la pila efímera, sin que las dos capas se tapen.
const stackEl = ref(null)
let observer = null

const publishRailHeight = () => {
  // Un mock de `useToast` puede devolver un objeto parcial: el riel es una
  // mejora de posición, no un requisito de montaje.
  if (typeof setRailHeight !== 'function') return

  const height = stackEl.value?.getBoundingClientRect?.().height ?? 0
  setRailHeight(height)
}

// El mock de un test puede entregar la pila como objeto plano: el getter
// evita el aviso de "watch source" y sigue observando la misma referencia.
watch(
  () => globalToasts?.value,
  () => nextTick(publishRailHeight),
  { deep: true }
)

onMounted(() => {
  publishRailHeight()
  if (typeof ResizeObserver !== 'undefined') {
    observer = new ResizeObserver(publishRailHeight)
    if (stackEl.value) observer.observe(stackEl.value)
  }
})

onUnmounted(() => {
  observer?.disconnect()
  observer = null
  if (typeof setRailHeight === 'function') setRailHeight(0)
})

const progressClasses = type => {
  const classes = {
    success: 'bg-green-500',
    error: 'bg-red-500',
    warning: 'bg-yellow-500',
    info: 'bg-accent'
  }
  return classes[type] || classes.info
}
</script>

<style scoped>
.toast-progress {
  position: absolute;
  inset-inline: 0;
  bottom: 0;
  height: 2px;
  transform-origin: left center;
  animation-name: toast-progress-shrink;
  animation-timing-function: linear;
  animation-fill-mode: forwards;
}

@keyframes toast-progress-shrink {
  from {
    transform: scaleX(1);
  }
  to {
    transform: scaleX(0);
  }
}

@media (prefers-reduced-motion: reduce) {
  .toast-progress {
    animation: none;
  }
}
</style>
