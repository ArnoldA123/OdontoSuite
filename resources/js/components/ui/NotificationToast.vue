<template>
  <div v-if="notifications.length > 0" class="notification-toast">
    <TransitionGroup
      name="notification"
      tag="div"
      enter-active-class="transition-all duration-300 ease-out"
      enter-from-class="opacity-0 translate-x-full"
      enter-to-class="opacity-100 translate-x-0"
      leave-active-class="transition-all duration-200 ease-in absolute"
      leave-from-class="opacity-100 translate-x-0"
      leave-to-class="opacity-0 translate-x-full"
    >
      <div
        v-for="notification in notifications"
        :key="notification.id"
        class="notification-item"
        :class="[`notification-${notification.type}`]"
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
      </div>
    </TransitionGroup>
  </div>
</template>

<script setup>
import { useNotifications } from '@/composables/useNotifications'
import {
  CheckCircleIcon,
  ExclamationTriangleIcon,
  XCircleIcon,
  InformationCircleIcon,
  XMarkIcon
} from '@heroicons/vue/24/outline'

const { notifications, removeNotification } = useNotifications()
</script>

<style scoped>
.notification-toast {
  @apply fixed top-4 right-4 z-50 space-y-2;
}

.notification-item {
  @apply bg-theme-surface-elevated border border-theme rounded-ios shadow-lg p-4 min-w-80 max-w-96 cursor-pointer;
  @apply transform;
  @apply hover:shadow-xl hover:scale-105;
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
</style>
