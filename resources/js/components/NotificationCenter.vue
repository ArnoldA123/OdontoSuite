<template>
  <div v-if="isOpen" class="notification-center-overlay" @click.self="close">
    <div class="notification-center" @click.stop>
      <!-- Header -->
      <div class="notification-center-header">
        <div class="flex items-center justify-between">
          <h2 class="text-lg font-semibold text-theme-primary">Notificaciones</h2>
          <div class="flex items-center gap-2">
            <button
              v-if="unreadCount > 0"
              class="text-sm text-theme-secondary hover:text-theme-primary transition-colors"
              @click="markAllAsRead"
            >
              Marcar todas como leídas
            </button>
            <button class="p-1 hover:bg-theme-surface rounded transition-colors" @click="close">
              <XMarkIcon class="w-5 h-5 text-theme-secondary" />
            </button>
          </div>
        </div>

        <div class="flex items-center justify-between mt-4">
          <p class="text-xs text-theme-secondary">Feed de actividad reciente del sistema</p>
          <button
            v-if="notifications.length > 0"
            class="text-xs text-theme-secondary hover:text-theme-primary transition-colors"
            @click="markAllAsRead"
          >
            Marcar todas como leídas
          </button>
        </div>
      </div>

      <!-- Lista de notificaciones -->
      <div class="notification-center-body">
        <div v-if="notifications.length === 0" class="empty-state">
          <BellIcon class="w-12 h-12 text-theme-secondary mx-auto mb-4" />
          <p class="text-theme-secondary">No hay notificaciones</p>
        </div>

        <div v-else class="notifications-list">
          <div
            v-for="notification in notifications"
            :key="notification.id"
            class="notification-item"
            :class="[
              `notification-${notification.type}`,
              { 'notification-unread': !notification.read }
            ]"
            @click="handleNotificationClick(notification)"
          >
            <div class="notification-icon">
              <CheckCircleIcon
                v-if="notification.type === 'success'"
                class="w-5 h-5 text-green-500"
              />
              <ExclamationTriangleIcon
                v-else-if="notification.type === 'warning'"
                class="w-5 h-5 text-yellow-500"
              />
              <XCircleIcon v-else-if="notification.type === 'error'" class="w-5 h-5 text-red-500" />
              <InformationCircleIcon v-else class="w-5 h-5 text-primary-500" />
            </div>
            <div class="notification-content">
              <div class="flex items-start justify-between">
                <div class="flex-1">
                  <h4 class="font-semibold text-theme-primary">
                    {{ notification.title }}
                  </h4>
                  <p class="text-sm text-theme-secondary mt-1">
                    {{ notification.message }}
                  </p>
                  <div class="flex items-center gap-2 mt-2">
                    <span class="text-xs text-theme-secondary">
                      {{ formatTime(notification.timestamp) }}
                    </span>
                    <span
                      v-if="notification.category !== 'system'"
                      class="px-2 py-0.5 text-xs rounded bg-theme-surface text-theme-secondary"
                    >
                      {{ getCategoryLabel(notification.category) }}
                    </span>
                  </div>
                </div>
                <div class="flex items-center gap-2 ml-4">
                  <button
                    v-if="!notification.read"
                    class="p-1 hover:bg-theme-surface rounded transition-colors"
                    title="Marcar como leída"
                    @click.stop="markAsRead(notification.id)"
                  >
                    <CheckIcon class="w-4 h-4 text-theme-secondary" />
                  </button>
                  <button
                    class="p-1 hover:bg-theme-surface rounded transition-colors"
                    title="Eliminar"
                    @click.stop="removeNotification(notification.id)"
                  >
                    <XMarkIcon class="w-4 h-4 text-theme-secondary" />
                  </button>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Footer -->
      <div class="notification-center-footer">
        <button
          class="text-sm text-theme-secondary hover:text-theme-primary transition-colors"
          :disabled="readCount === 0"
          @click="clearRead"
        >
          Limpiar leídas
        </button>
        <button
          class="text-sm text-red-500 hover:text-red-600 transition-colors"
          :disabled="notifications.length === 0"
          @click="clearAll"
        >
          Limpiar todas
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, watch } from 'vue'
import { useNotifications } from '@/composables/useNotifications'
import {
  BellIcon,
  XMarkIcon,
  CheckCircleIcon,
  ExclamationTriangleIcon,
  XCircleIcon,
  InformationCircleIcon,
  CheckIcon
} from '@heroicons/vue/24/outline'

const props = defineProps({
  isOpen: {
    type: Boolean,
    default: false
  }
})

const emit = defineEmits(['close'])

const {
  notifications,
  markAsRead,
  markAllAsRead,
  removeNotification,
  getUnreadCount,
  clearAll,
  clearRead
} = useNotifications()

const unreadCount = computed(() => getUnreadCount.value)
const readCount = computed(() => notifications.value.filter(n => n.read).length)

const close = () => {
  emit('close')
}

const handleNotificationClick = notification => {
  if (!notification.read) {
    markAsRead(notification.id)
  }

  // Ejecutar acción si existe
  if (notification.action && typeof notification.action === 'function') {
    notification.action()
  }

  // Cerrar el centro si la notificación tiene acción
  if (notification.action) {
    close()
  }
}

const formatTime = timestamp => {
  const date = new Date(timestamp)
  const now = new Date()
  const diff = now - date
  const minutes = Math.floor(diff / 60000)
  const hours = Math.floor(diff / 3600000)
  const days = Math.floor(diff / 86400000)

  if (minutes < 1) return 'Ahora'
  if (minutes < 60) return `Hace ${minutes} min`
  if (hours < 24) return `Hace ${hours} h`
  if (days < 7) return `Hace ${days} días`
  return date.toLocaleDateString('es-ES', { day: 'numeric', month: 'short' })
}

const getCategoryLabel = category => {
  const labels = {
    appointments: 'Citas',
    patients: 'Pacientes',
    'treatment-plans': 'Planes',
    quotations: 'Presupuestos',
    'medical-records': 'Historias',
    payments: 'Pagos',
    system: 'Sistema'
  }
  return labels[category] || category
}

// Cerrar con Escape
watch(
  () => props.isOpen,
  isOpen => {
    if (isOpen) {
      const handleEscape = e => {
        if (e.key === 'Escape') {
          close()
        }
      }
      document.addEventListener('keydown', handleEscape)
      return () => {
        document.removeEventListener('keydown', handleEscape)
      }
    }
  }
)
</script>

<style scoped>
.notification-center-overlay {
  @apply fixed inset-0 z-50 flex items-start justify-end pt-16 pr-4;
  background-color: rgba(0, 0, 0, 0.3);
  backdrop-filter: blur(4px);
}

.notification-center {
  @apply bg-theme-surface-elevated border border-theme rounded-lg shadow-xl;
  @apply w-full max-w-md max-h-[calc(100vh-5rem)];
  @apply flex flex-col;
  animation: slideIn 0.2s ease-out;
}

@keyframes slideIn {
  from {
    transform: translateX(100%);
    opacity: 0;
  }
  to {
    transform: translateX(0);
    opacity: 1;
  }
}

.notification-center-header {
  @apply p-4 border-b border-theme;
}

.notification-center-body {
  @apply flex-1 overflow-y-auto;
  max-height: calc(100vh - 12rem);
}

.notification-center-footer {
  @apply p-4 border-t border-theme flex justify-between;
}

.notifications-list {
  @apply divide-y divide-theme;
}

.notification-item {
  @apply p-4 hover:bg-theme-surface transition-colors cursor-pointer;
  @apply flex gap-3;
}

.notification-item.notification-unread {
  @apply bg-primary-50/30 border-l-4 border-primary-500;
}

.notification-icon {
  @apply flex-shrink-0 mt-0.5;
}

.notification-content {
  @apply flex-1 min-w-0;
}

.empty-state {
  @apply flex flex-col items-center justify-center py-12 px-4;
}

.notification-success {
  @apply border-l-4 border-green-500;
}

.notification-error {
  @apply border-l-4 border-red-500;
}

.notification-warning {
  @apply border-l-4 border-yellow-500;
}

.notification-info {
  @apply border-l-4 border-primary-500;
}
</style>
