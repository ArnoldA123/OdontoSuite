<template>
  <div class="notification-toast" v-if="notifications.length > 0">
    <div
      v-for="notification in notifications"
      :key="notification.id"
      :class="[
        'notification-item',
        `notification-${notification.type}`
      ]"
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
        <button
          @click.stop="removeNotification(notification.id)"
          class="notification-close"
        >
          <XMarkIcon class="w-4 h-4" />
        </button>
      </div>
    </div>
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
  @apply bg-theme-surface-elevated border border-theme rounded-lg shadow-lg p-4 min-w-80 max-w-96 cursor-pointer;
  @apply transform transition-all duration-300 ease-in-out;
  @apply hover:shadow-xl hover:scale-105;
}

.notification-success {
  @apply border-green-200 bg-green-50;
}

.notification-warning {
  @apply border-yellow-200 bg-yellow-50;
}

.notification-error {
  @apply border-red-200 bg-red-50;
}

.notification-info {
  @apply border-primary-200 bg-primary-50;
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
  @apply flex-shrink-0 text-theme-secondary hover:text-theme-primary transition-colors;
}
</style>
