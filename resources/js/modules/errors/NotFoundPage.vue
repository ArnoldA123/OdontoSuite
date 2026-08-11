<template>
  <AppLayout>
    <div class="not-found-page">
      <div class="not-found-grid" ref="pageRef">
        <div class="not-found-content">
          <p class="not-found-eyebrow">Error 404</p>
          <h1 class="not-found-headline">
            Página no encontrada
          </h1>
          <p class="not-found-subhead">
            La ruta que buscabas no existe o fue movida. Verifica la URL o vuelve al panel principal.
          </p>
          <div class="not-found-actions">
            <UiButton variant="secondary" @click="goBack">
              <template #icon-left>
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                </svg>
              </template>
              Volver
            </UiButton>
            <UiButton variant="primary" @click="goHome">
              Ir al inicio
            </UiButton>
          </div>
        </div>

        <figure class="not-found-figure" aria-hidden="true">
          <img
            src="/images/ui/not-found.jpg"
            alt="Ilustración de una página no encontrada"
            class="not-found-image"
            loading="lazy"
            decoding="async"
          />
        </figure>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref, onMounted, nextTick } from 'vue'
import { useRouter } from 'vue-router'
import { useSpring } from '@/composables/useSpring'
import AppLayout from '@/components/layout/AppLayout.vue'
import UiButton from '@/components/ui/Button.vue'

const router = useRouter()

const pageRef = ref(null)

// One entrance spring on the content card. Critically damped, no bounce.
// Under prefers-reduced-motion the spring writes the target instantly.
const entrance = useSpring({
  response: 0.35,
  damping: 1.0,
  from: 0,
  to: 1,
  cssVar: '--spring-404-o'
})
const entranceOpacity = useSpring({
  response: 0.2,
  damping: 1.0,
  from: 0,
  to: 1,
  cssVar: '--spring-404-opacity'
})

onMounted(async () => {
  await nextTick()
  if (pageRef.value) {
    entrance.attach(pageRef.value)
    entranceOpacity.attach(pageRef.value)
  }
  entrance.set(1)
  entranceOpacity.set(1)
})

const goBack = () => {
  if (typeof window !== 'undefined' && window.history.length > 1) {
    router.back()
  } else {
    router.push('/login')
  }
}

const goHome = () => {
  router.push('/login')
}
</script>

<style scoped>
.not-found-page {
  @apply w-full flex items-center justify-center px-5 py-10 sm:py-16;
  background: var(--color-cream-50);
  min-height: calc(100dvh - 64px);
}

.not-found-grid {
  --spring-404-o: 1;
  --spring-404-opacity: 1;
  @apply w-full max-w-4xl grid items-center gap-10;
  grid-template-columns: 1fr;
  transform: translate3d(0, calc((1 - var(--spring-404-o)) * 12px), 0);
  opacity: var(--spring-404-opacity);
}

.not-found-content {
  @apply flex flex-col gap-4 text-center md:text-left;
}

.not-found-eyebrow {
  @apply text-xs uppercase tracking-[0.18em];
  color: var(--color-ink-300);
}

.not-found-headline {
  @apply text-3xl sm:text-4xl font-medium leading-[1.1];
  color: var(--color-label-label);
  letter-spacing: -0.022em;
}

.not-found-subhead {
  @apply text-sm leading-relaxed;
  color: var(--color-ink-500);
}

.not-found-actions {
  @apply flex flex-wrap items-center justify-center md:justify-start gap-3 pt-2;
}

.not-found-figure {
  @apply flex justify-center;
}

.not-found-image {
  @apply w-full max-w-md rounded-2xl;
  border: 1px solid var(--color-ink-100);
  box-shadow: var(--shadow-medium);
}

@media (min-width: 768px) {
  .not-found-grid {
    grid-template-columns: 1fr 1fr;
    gap: 3rem;
  }
}

@media (prefers-reduced-motion: reduce) {
  .not-found-grid {
    transform: none !important;
    transition: none !important;
  }
}

@media (prefers-contrast: more) {
  .not-found-headline {
    color: var(--color-ink-900);
  }
  .not-found-subhead {
    color: var(--color-ink-700);
  }
  .not-found-image {
    border-color: var(--color-ink-700);
  }
}
</style>