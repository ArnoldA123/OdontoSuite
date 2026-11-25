<template>
  <div
    v-if="notifications.length > 0"
    class="notification-toast pointer-events-none fixed inset-x-4 top-20 z-fixed ml-auto flex max-w-sm flex-col sm:inset-x-auto sm:right-6"
    :style="railStyle"
    data-notification-toast-stack
  >
    <TransitionGroup
      name="notification"
      tag="div"
      class="notification-list pointer-events-auto flex w-full max-w-sm flex-col gap-3 overflow-y-auto overscroll-contain"
      :style="listStyle"
      enter-active-class="transition-all duration-300 ease-out"
      enter-from-class="opacity-0 translate-y-2"
      enter-to-class="opacity-100 translate-y-0"
      leave-active-class="transition-all duration-200 ease-in absolute"
      leave-from-class="opacity-100 translate-y-0"
      leave-to-class="opacity-0"
    >
      <div
        v-for="notification in notifications"
        :key="notification.id"
        class="notification-item"
        :class="[`notification-${notification.type}`]"
        :data-notification-id="notification.id"
        :data-notification-key="notification.key"
        @click="removeNotification(notification.id)"
      >
        <div class="notification-content">
          <div class="notification-icon">
            <CheckCircleIcon v-if="notification.type === 'success'" class="w-5 h-5" />
            <ExclamationTriangleIcon v-else-if="notification.type === 'warning'" class="w-5 h-5" />
            <XCircleIcon v-else-if="notification.type === 'error'" class="w-5 h-5" />
            <InformationCircleIcon v-else class="w-5 h-5" />
          </div>
          <div class="notification-message">
            {{ notification.message }}
          </div>
          <button class="notification-close" @click.stop="removeNotification(notification.id)">
            <XMarkIcon class="w-4 h-4" />
          </button>
        </div>

        <!-- Barra de progreso: el temporizador se rearma al montar y cuando la
             misma clave actualiza la fila (la revisión cambia). -->
        <div
          v-if="notification.persistent !== true && Number(notification.duration) > 0"
          :key="`${notification.id}:${notification.revision ?? 0}`"
          class="notification-progress"
          :class="progressClass(notification.type)"
          :style="{ animationDuration: `${notification.duration}ms` }"
          data-notification-progress
          aria-hidden="true"
        />
      </div>
    </TransitionGroup>
  </div>
</template>

<script setup>
import { computed, onUnmounted, watch } from 'vue'
import { useNotifications } from '@/composables/useNotifications'
import { useToast } from '@/composables/useToast'
import {
  CheckCircleIcon,
  ExclamationTriangleIcon,
  XCircleIcon,
  InformationCircleIcon,
  XMarkIcon
} from '@heroicons/vue/24/outline'

const { notifications, removeNotification } = useNotifications()

// Riel compartido con el toast efímero: el top sale del alto que publica su
// pila, así el aviso persistente se apila debajo y nunca la tapa. El máximo
// deja 24 px de margen inferior del viewport. Un mock de `useToast` puede
// devolver un objeto parcial: el riel cae al offset seguro de la topbar.
const RAIL_FALLBACK_TOP = 80
const { railTop: railTopSource } = useToast()

const railTop = computed(() => {
  const top = Number(railTopSource?.value ?? railTopSource)
  return Number.isFinite(top) && top > 0 ? top : RAIL_FALLBACK_TOP
})
const railStyle = computed(() => ({ top: `${railTop.value}px` }))
const listStyle = computed(() => ({
  maxHeight: `calc(100dvh - ${railTop.value + 24}px)`
}))

// Un temporizador por fila visible. El almacén ya agenda el suyo cuando la
// notificación nace, pero una fila rehidratada desde localStorage llega sin
// temporizador: este mapa es el que la retira y evita el toast atascado.
const timers = new Map()

const clearTimer = id => {
  const timer = timers.get(id)
  if (timer) {
    clearTimeout(timer)
    timers.delete(id)
  }
}

const schedule = notification => {
  clearTimer(notification.id)

  if (notification.persistent === true || !(Number(notification.duration) > 0)) {
    return
  }

  const revision = notification.revision ?? 0
  const timer = setTimeout(() => {
    timers.delete(notification.id)
    removeNotification(notification.id)
  }, Number(notification.duration))

  timers.set(notification.id, { timer, revision })
}

const syncTimers = list => {
  const ids = new Set(list.map(notification => notification.id))

  for (const id of [...timers.keys()]) {
    if (!ids.has(id)) clearTimer(id)
  }

  for (const notification of list) {
    const entry = timers.get(notification.id)
    const revision = notification.revision ?? 0
    if (!entry || entry.revision !== revision) {
      schedule(notification)
    }
  }
}

watch(notifications, syncTimers, { immediate: true, deep: true })

onUnmounted(() => {
  for (const id of [...timers.keys()]) clearTimer(id)
})

const progressClass = type => {
  const classes = {
    success: 'bg-green-500',
    warning: 'bg-yellow-500',
    error: 'bg-red-500',
    info: 'bg-accent'
  }
  return classes[type] || classes.info
}
</script>

<style scoped>
.notification-item {
  @apply bg-theme-surface-elevated border border-theme rounded-ios relative overflow-hidden shadow-lg p-4 w-full max-w-sm cursor-pointer;
  @apply transform;
  @apply hover:shadow-xl;
}

.notification-success {
  @apply border-systemGreen-200 bg-systemGreen-50;
}

.notification-warning {
  @apply border-systemYellow-200 bg-systemYellow-50;
}

.notification-error {
  @apply border-systemRed-200 bg-systemRed-50;
}

.notification-info {
  @apply border-systemBlue-200 bg-systemBlue-50;
}

.notification-content {
  @apply flex items-start space-x-3;
}

.notification-icon {
  @apply flex-shrink-0 mt-0.5;
}

.notification-success .notification-icon {
  @apply text-green-600;
}

.notification-warning .notification-icon {
  @apply text-yellow-600;
}

.notification-error .notification-icon {
  @apply text-red-600;
}

.notification-info .notification-icon {
  @apply text-accent;
}

.notification-message {
  @apply flex-1 text-sm font-medium text-theme-primary;
}

.notification-close {
  @apply flex-shrink-0 text-theme-secondary hover:text-theme-primary;
}

.notification-progress {
  position: absolute;
  inset-inline: 0;
  bottom: 0;
  height: 2px;
  transform-origin: left center;
  animation-name: notification-progress-shrink;
  animation-timing-function: linear;
  animation-fill-mode: forwards;
}

@keyframes notification-progress-shrink {
  from {
    transform: scaleX(1);
  }
  to {
    transform: scaleX(0);
  }
}

/* Scoped transitions — replaces the removed global `* { transition }` rule. */
.notification-item,
.notification-close {
  transition:
    background-color 300ms ease-in-out,
    color 300ms ease-in-out,
    border-color 300ms ease-in-out,
    box-shadow 300ms ease-in-out,
    transform 300ms ease-in-out;
}

@media (prefers-reduced-motion: reduce) {
  .notification-progress {
    animation: none;
  }
}
</style>
