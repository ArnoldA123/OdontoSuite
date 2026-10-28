import { onMounted, onUnmounted } from 'vue'
import { useRoute } from 'vue-router'
import { useEcho } from './useEcho'
import { useNotifications } from './useNotifications'
import { usePermissions } from './usePermissions'
import { useToast } from './useToast'
import { CHANNEL_PERMISSIONS } from '@/utils/broadcastChannels'
import { NotificationService } from '@/services/NotificationService'

/**
 * Composable para escuchar eventos WebSocket globales y generar notificaciones
 */
export function useWebSocketNotifications() {
  const route = useRoute()
  const { privateChannel, echo } = useEcho()
  const { has } = usePermissions()
  const { addNotification } = useNotifications()
  const { error: showErrorToast } = useToast()

  let channels = {}

  /**
   * Determinar si se debe mostrar la notificación según la página actual
   */
  const shouldShowNotification = (eventName, category) => {
    const currentPath = route.path

    // Siempre mostrar notificaciones importantes
    const importantEvents = [
      'quotation.approved',
      'quotation.rejected',
      'cash-session.closed',
      'payment.registered'
    ]

    if (importantEvents.includes(eventName)) {
      return true
    }

    // Mostrar notificaciones según la página actual
    const pageCategoryMap = {
      '/patients': ['patients', 'appointments'],
      '/calendar': ['appointments'],
      '/treatment-plans': ['treatment-plans'],
      '/quotations': ['quotations'],
      '/medical-records': ['medical-records'],
      '/cash-register': ['payments']
    }

    for (const [path, categories] of Object.entries(pageCategoryMap)) {
      if (currentPath.startsWith(path) && categories.includes(category)) {
        return true
      }
    }

    // Por defecto, mostrar notificaciones del sistema y pagos
    return category === 'system' || category === 'payments'
  }

  /**
   * Manejar evento WebSocket y crear notificación
   */
  const handleEvent = (eventName, data) => {
    try {
      const notificationData = NotificationService.convertEventToNotification(eventName, data)

      if (!notificationData) {
        return
      }

      // Verificar si se debe mostrar la notificación
      if (!shouldShowNotification(eventName, notificationData.category)) {
        return
      }

      // Crear la notificación
      addNotification(notificationData.message, notificationData.type, {
        title: notificationData.title,
        category: notificationData.category,
        action: notificationData.action,
        sound: notificationData.sound !== false,
        persistent: notificationData.persistent || false
      })
    } catch (error) {
      // Log the failure so an empty catch never masks a real bug; surface
      // a one-shot toast so the user knows the WebSocket listener chain is
      // dropping an event instead of failing silently.
      console.error('[WebSocket] handler error for', eventName, error)
      showErrorToast('No se pudo procesar una notificación en vivo.')
    }
  }

  /**
   * #378/#384: subscribe one domain channel only when the session holds the
   * permission the server uses to authorize it. The wire name keeps the
   * `private-` prefix because Echo's `leave()` does not add it.
   */
  const subscribe = (channelName, bind) => {
    if (!has(CHANNEL_PERMISSIONS[channelName])) {
      return
    }

    const channel = privateChannel(channelName)
    if (!channel) {
      return
    }

    bind(channel)
    channels[`private-${channelName}`] = channel
  }

  /**
   * Configurar suscripciones a canales WebSocket
   */
  const setupSubscriptions = () => {
    try {
      subscribe('appointments', channel =>
        channel
          .listen('.appointment.created', e => handleEvent('appointment.created', e))
          .listen('.appointment.updated', e => handleEvent('appointment.updated', e))
          .listen('.appointment.deleted', e => handleEvent('appointment.deleted', e))
      )

      subscribe('patients', channel =>
        channel
          .listen('.patient.created', e => handleEvent('patient.created', e))
          .listen('.patient.updated', e => handleEvent('patient.updated', e))
          .listen('.patient.deleted', e => handleEvent('patient.deleted', e))
      )

      subscribe('treatment-plans', channel =>
        channel
          .listen('.treatment-plan.created', e => handleEvent('treatment-plan.created', e))
          .listen('.treatment-plan.updated', e => handleEvent('treatment-plan.updated', e))
          .listen('.treatment-plan.deleted', e => handleEvent('treatment-plan.deleted', e))
      )

      subscribe('quotations', channel =>
        channel
          .listen('.quotation.created', e => handleEvent('quotation.created', e))
          .listen('.quotation.updated', e => handleEvent('quotation.updated', e))
          .listen('.quotation.sent', e => handleEvent('quotation.sent', e))
          .listen('.quotation.approved', e => handleEvent('quotation.approved', e))
          .listen('.quotation.rejected', e => handleEvent('quotation.rejected', e))
      )

      subscribe('medical-records', channel =>
        channel
          .listen('.medical-record.created', e => handleEvent('medical-record.created', e))
          .listen('.clinical-evolution.created', e => handleEvent('clinical-evolution.created', e))
          .listen('.clinical-addendum.created', e => handleEvent('clinical-addendum.created', e))
          .listen('.clinical-attachment.created', e =>
            handleEvent('clinical-attachment.created', e)
          )
      )

      subscribe('specialty-records', channel =>
        channel
          .listen('.specialty-record.created', e => handleEvent('specialty-record.created', e))
          .listen('.specialty-record.updated', e => handleEvent('specialty-record.updated', e))
      )

      subscribe('cash-register', channel =>
        channel
          .listen('.payment.registered', e => handleEvent('payment.registered', e))
          .listen('.cash-session.opened', e => handleEvent('cash-session.opened', e))
          .listen('.cash-session.closed', e => handleEvent('cash-session.closed', e))
      )

      subscribe('reminders', channel =>
        channel.listen('.reminder.sent', e => handleEvent('reminder.sent', e))
      )

      subscribe('procedure-catalog', channel =>
        channel.listen('.procedure.catalog.deactivated', e =>
          handleEvent('procedure.catalog.deactivated', e)
        )
      )
    } catch (error) {
      console.error('[WebSocket] setupSubscriptions failed', error)
      showErrorToast('No se pudieron activar las notificaciones en vivo.')
    }
  }

  /**
   * Limpiar suscripciones
   */
  const cleanup = () => {
    if (echo) {
      try {
        // The keys are the wire names (`private-*`): Echo's leave() does not
        // add the private prefix, so leaving the base name is a no-op.
        Object.keys(channels).forEach(wireName => {
          echo.leave(wireName)
        })
      } catch (e) {
        console.error('[WebSocket] cleanup failed', e)
      }
    }
    channels = {}
  }

  // Configurar al montar
  onMounted(() => {
    setupSubscriptions()
  })

  // Limpiar al desmontar
  onUnmounted(() => {
    cleanup()
  })

  return {
    setupSubscriptions,
    cleanup
  }
}
