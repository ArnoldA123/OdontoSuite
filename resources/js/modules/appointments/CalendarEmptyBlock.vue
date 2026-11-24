<template>
  <!-- Contenido de vacío de la superficie del calendario: vive DENTRO de una
       tarjeta (el tablero del día o la tarjeta de la superficie), así que no
       pinta marco propio — ni borde, ni fondo, ni radio—. El único borde
       redondeado es el de la tarjeta que lo aloja; aquí sólo hay icono, título,
       frase y la acción. -->
  <div
    data-calendar-empty-block
    class="mx-auto flex max-w-[420px] flex-col items-center gap-1.5 px-4 py-4 text-center"
  >
    <CalendarDaysIcon class="h-5 w-5 text-theme-secondary" aria-hidden="true" />
    <p class="text-sm font-medium text-theme-primary">{{ title }}</p>
    <p v-if="description" class="text-xs leading-4 text-theme-secondary">{{ description }}</p>
    <div v-if="$slots.default" class="mt-1.5 flex flex-wrap items-center justify-center gap-2">
      <slot />
    </div>
  </div>
</template>

<script setup>
import { CalendarDaysIcon } from '@heroicons/vue/24/outline'

// T8 — un solo marco. La primitiva `UiSectionEmpty` pinta tarjeta propia
// (fondo, hairline y radio de control); dentro de una tarjeta eso se lee como
// dos marcos anidados con doble radio. Este bloque conserva la anatomía —icono,
// título, frase y acción— y deja el marco a la superficie que lo monta.
defineProps({
  title: { type: String, required: true },
  description: { type: String, default: '' }
})
</script>
