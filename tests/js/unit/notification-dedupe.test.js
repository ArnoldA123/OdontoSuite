import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { mount } from '@vue/test-utils'
import { nextTick } from 'vue'
import NotificationToast from '../../../resources/js/components/ui/NotificationToast.vue'
import { useNotifications } from '../../../resources/js/composables/useNotifications.js'
import { NotificationService } from '../../../resources/js/services/NotificationService.js'

const { notifications, addNotification, clearAll } = useNotifications()

const appointmentEvent = (id, status = 'scheduled') => ({
  appointment: {
    id,
    user_id: 8,
    status,
    scheduled_at: '2026-12-03T09:00:00',
    updated_at: '2026-12-03T08:00:00'
  }
})

// El mismo camino que usa useWebSocketNotifications: mapear el evento y
// entregar el aviso al almacén con su clave estable.
const announce = (eventName, payload) => {
  const mapped = NotificationService.convertEventToNotification(eventName, payload)
  return addNotification(mapped.message, mapped.type, {
    key: mapped.key,
    title: mapped.title,
    category: mapped.category,
    sound: false
  })
}

beforeEach(() => {
  clearAll()
})

afterEach(() => {
  clearAll()
  vi.useRealTimers()
})

describe('dos eventos de cita idénticos producen un solo aviso', () => {
  it('comparte la clave appointment.created:{id} y actualiza la fila viva', () => {
    announce('appointment.created', appointmentEvent(42))
    announce('appointment.created', appointmentEvent(42))

    expect(notifications.value).toHaveLength(1)
    expect(notifications.value[0].key).toBe('appointment.created:42')
    expect(notifications.value[0].revision).toBe(1)
  })

  it('no mezcla dos citas distintas', () => {
    announce('appointment.created', appointmentEvent(42))
    announce('appointment.created', appointmentEvent(43))

    expect(notifications.value).toHaveLength(2)
    expect(notifications.value.map(n => n.key)).toEqual([
      'appointment.created:43',
      'appointment.created:42'
    ])
  })

  it('usa el id suelto del sobre de borrado', () => {
    announce('appointment.deleted', { appointment_id: 51, user_id: 8 })
    announce('appointment.deleted', { appointment_id: 51, user_id: 8 })

    expect(notifications.value).toHaveLength(1)
    expect(notifications.value[0].key).toBe('appointment.deleted:51')
  })
})

describe('useNotifications — caducidad y reinicio', () => {
  it('retira el aviso al vencer su duración', () => {
    vi.useFakeTimers()

    addNotification('Cita creada en la agenda.', 'info', {
      key: 'appointment.created:42',
      duration: 5000
    })
    expect(notifications.value).toHaveLength(1)

    vi.advanceTimersByTime(5000)
    expect(notifications.value).toHaveLength(0)
  })

  it('reinicia el temporizador cuando llega la misma clave', () => {
    vi.useFakeTimers()

    addNotification('Cita creada en la agenda.', 'info', {
      key: 'appointment.created:42',
      duration: 5000
    })
    vi.advanceTimersByTime(4000)

    addNotification('Cita creada en la agenda.', 'info', {
      key: 'appointment.created:42',
      duration: 5000
    })
    vi.advanceTimersByTime(4000)

    expect(notifications.value).toHaveLength(1)

    vi.advanceTimersByTime(1000)
    expect(notifications.value).toHaveLength(0)
  })

  it('respeta persistent y duration: 0 como peticiones explícitas', () => {
    vi.useFakeTimers()

    addNotification('Persistente', 'info', { persistent: true, duration: 1000 })
    addNotification('Sin caducidad', 'info', { duration: 0 })

    vi.advanceTimersByTime(60000)
    expect(notifications.value).toHaveLength(2)
  })
})

describe('NotificationToast — una fila rehidratada también caduca', () => {
  it('arranca el temporizador al montar y retira la fila al vencer', async () => {
    vi.useFakeTimers()

    // Una notificación vieja llega desde localStorage sin temporizador: es la
    // que antes se quedaba en pantalla para siempre.
    notifications.value.push({
      id: 900,
      key: 'appointment.updated:7',
      revision: 0,
      title: 'Cita actualizada',
      message: 'La cita ha sido modificada.',
      type: 'info',
      category: 'appointments',
      read: false,
      timestamp: new Date().toISOString(),
      action: null,
      sound: false,
      persistent: false,
      duration: 3000,
      data: null
    })

    const wrapper = mount(NotificationToast)
    await nextTick()
    expect(wrapper.find('[data-notification-id="900"]').exists()).toBe(true)

    vi.advanceTimersByTime(3000)
    await nextTick()

    expect(notifications.value).toHaveLength(0)
    expect(wrapper.find('[data-notification-id="900"]').exists()).toBe(false)

    wrapper.unmount()
  })

  it('pinta la barra de progreso y respeta persistent', async () => {
    vi.useFakeTimers()

    notifications.value.push({
      id: 901,
      key: null,
      revision: 0,
      title: 'Persistente',
      message: 'No caduca',
      type: 'info',
      category: 'system',
      read: false,
      timestamp: new Date().toISOString(),
      action: null,
      sound: false,
      persistent: true,
      duration: 1000,
      data: null
    })

    const wrapper = mount(NotificationToast)
    await nextTick()

    expect(wrapper.find('[data-notification-progress]').exists()).toBe(false)

    vi.advanceTimersByTime(60000)
    await nextTick()
    expect(notifications.value).toHaveLength(1)

    wrapper.unmount()
  })
})
