import { onMounted, onUnmounted } from 'vue'
import { useRoute } from 'vue-router'
import { useEcho } from './useEcho'
import { useNotifications } from './useNotifications'
import { NotificationService } from '@/services/NotificationService'

/**
 * Composable para escuchar eventos WebSocket globales y generar notificaciones
 */
export function useWebSocketNotifications() {
  const route = useRoute()
  const { channel, echo } = useEcho()
  const { addNotification } = useNotifications()

  let channels = {}

  /**
   * Determinar si se debe mostrar la notificación según la página actual
   */
  const shouldShowNotification = (eventName, category) => {
    const currentPath = route.path

    // Siempre mostrar notificaciones importantes
    const importantEvents = [
      'quotation.approved',
      'interconsultation.created',
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
      addNotification(
        notificationData.message,
        notificationData.type,
        {
          title: notificationData.title,
          category: notificationData.category,
          action: notificationData.action,
          sound: notificationData.sound !== false,
          persistent: notificationData.persistent || false
        }
      )
    } catch (error) {
    }
  }

  /**
   * Configurar suscripciones a canales WebSocket
   */
  const setupSubscriptions = () => {
    try {
      // Canal de citas
      const appointmentsChannel = channel('appointments')
      if (appointmentsChannel) {
        appointmentsChannel
          .listen('.appointment.created', (e) => handleEvent('appointment.created', e))
          .listen('.appointment.updated', (e) => handleEvent('appointment.updated', e))
          .listen('.appointment.deleted', (e) => handleEvent('appointment.deleted', e))
        channels.appointments = appointmentsChannel
      }

      // Canal de pacientes
      const patientsChannel = channel('patients')
      if (patientsChannel) {
        patientsChannel
          .listen('.patient.created', (e) => handleEvent('patient.created', e))
          .listen('.patient.updated', (e) => handleEvent('patient.updated', e))
          .listen('.patient.deleted', (e) => handleEvent('patient.deleted', e))
        channels.patients = patientsChannel
      }

      // Canal de planes de tratamiento
      const treatmentPlansChannel = channel('treatment-plans')
      if (treatmentPlansChannel) {
        treatmentPlansChannel
          .listen('.treatment-plan.created', (e) => handleEvent('treatment-plan.created', e))
          .listen('.treatment-plan.updated', (e) => handleEvent('treatment-plan.updated', e))
          .listen('.treatment-plan.deleted', (e) => handleEvent('treatment-plan.deleted', e))
        channels.treatmentPlans = treatmentPlansChannel
      }

      // Canal de presupuestos
      const quotationsChannel = channel('quotations')
      if (quotationsChannel) {
        quotationsChannel
          .listen('.quotation.created', (e) => handleEvent('quotation.created', e))
          .listen('.quotation.updated', (e) => handleEvent('quotation.updated', e))
          .listen('.quotation.approved', (e) => handleEvent('quotation.approved', e))
        channels.quotations = quotationsChannel
      }

      // Canal de historias clínicas
      const medicalRecordsChannel = channel('medical-records')
      if (medicalRecordsChannel) {
        medicalRecordsChannel
          .listen('.medical-record.created', (e) => handleEvent('medical-record.created', e))
          .listen('.medical-record.updated', (e) => handleEvent('medical-record.updated', e))
          .listen('.clinical-evolution.created', (e) => handleEvent('clinical-evolution.created', e))
          .listen('.clinical-attachment.created', (e) => handleEvent('clinical-attachment.created', e))
        channels.medicalRecords = medicalRecordsChannel
      }

      // Canal de registros de especialidades
      const specialtyRecordsChannel = channel('specialty-records')
      if (specialtyRecordsChannel) {
        specialtyRecordsChannel
          .listen('.specialty-record.created', (e) => handleEvent('specialty-record.created', e))
          .listen('.specialty-record.updated', (e) => handleEvent('specialty-record.updated', e))
        channels.specialtyRecords = specialtyRecordsChannel
      }

      // Canal de interconsultas
      const interconsultationsChannel = channel('interconsultations')
      if (interconsultationsChannel) {
        interconsultationsChannel
          .listen('.interconsultation.created', (e) => handleEvent('interconsultation.created', e))
          .listen('.interconsultation.responded', (e) => handleEvent('interconsultation.responded', e))
        channels.interconsultations = interconsultationsChannel
      }

      // Canal de caja
      const cashRegisterChannel = channel('cash-register')
      if (cashRegisterChannel) {
        cashRegisterChannel
          .listen('.payment.registered', (e) => handleEvent('payment.registered', e))
          .listen('.cash-session.opened', (e) => handleEvent('cash-session.opened', e))
          .listen('.cash-session.closed', (e) => handleEvent('cash-session.closed', e))
        channels.cashRegister = cashRegisterChannel
      }

      // Canal de lista de espera
      const waitingListsChannel = channel('waiting-lists')
      if (waitingListsChannel) {
        waitingListsChannel
          .listen('.waiting-list.created', (e) => handleEvent('waiting-list.created', e))
          .listen('.waiting-list.filled', (e) => handleEvent('waiting-list.filled', e))
        channels.waitingLists = waitingListsChannel
      }

      // Canal de recordatorios
      const remindersChannel = channel('reminders')
      if (remindersChannel) {
        remindersChannel
          .listen('.reminder.sent', (e) => handleEvent('reminder.sent', e))
        channels.reminders = remindersChannel
      }
    } catch (error) {
    }
  }

  /**
   * Limpiar suscripciones
   */
  const cleanup = () => {
    if (echo) {
      try {
        Object.keys(channels).forEach(channelName => {
          echo.leave(channelName.replace(/([A-Z])/g, '-$1').toLowerCase())
        })
      } catch (e) {
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

