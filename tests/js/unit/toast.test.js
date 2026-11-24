import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { mount } from '@vue/test-utils'
import { nextTick } from 'vue'
import ToastContainer from '../../../resources/js/components/ToastContainer.vue'
import NotificationToast from '../../../resources/js/components/ui/NotificationToast.vue'
import Toast from '../../../resources/js/components/ui/Toast.vue'
import { useToast, globalToasts } from '../../../resources/js/composables/useToast.js'
import { useNotifications } from '../../../resources/js/composables/useNotifications.js'

const { addToast, clearAll } = useToast()
const { addNotification, clearAll: clearNotifications } = useNotifications()

beforeEach(() => {
  clearAll()
  clearNotifications()
})

afterEach(() => {
  clearAll()
  clearNotifications()
  vi.useRealTimers()
})

describe('useToast — una clave es un solo toast', () => {
  it('actualiza el toast vivo en vez de apilar una copia con la misma clave', () => {
    addToast({ type: 'info', message: 'Cita creada en la agenda.', key: 'appointment.created:42' })
    addToast({ type: 'info', message: 'Cita creada en la agenda.', key: 'appointment.created:42' })

    expect(globalToasts.value).toHaveLength(1)
    expect(globalToasts.value[0].key).toBe('appointment.created:42')
    expect(globalToasts.value[0].revision).toBe(1)
  })

  it('acepta dedupeKey como alias y no apila el mismo hecho', () => {
    addToast({ message: 'A', dedupeKey: 'appointment.updated:7' })
    addToast({ message: 'B', dedupeKey: 'appointment.updated:7' })

    expect(globalToasts.value).toHaveLength(1)
    expect(globalToasts.value[0].key).toBe('appointment.updated:7')
    expect(globalToasts.value[0].message).toBe('B')
  })

  it('mantiene separados dos hechos distintos', () => {
    addToast({ message: 'A', key: 'appointment.created:1' })
    addToast({ message: 'B', key: 'appointment.created:2' })

    expect(globalToasts.value).toHaveLength(2)
  })
})

describe('useToast — caducidad', () => {
  it('retira el toast al vencer su duración', () => {
    vi.useFakeTimers()

    addToast({ message: 'Cita creada', duration: 5000, key: 'appointment.created:42' })
    expect(globalToasts.value).toHaveLength(1)

    vi.advanceTimersByTime(4999)
    expect(globalToasts.value).toHaveLength(1)

    vi.advanceTimersByTime(1)
    expect(globalToasts.value).toHaveLength(0)
  })

  it('reinicia el temporizador cuando llega la misma clave', () => {
    vi.useFakeTimers()

    addToast({ message: 'Cita creada', duration: 5000, key: 'appointment.created:42' })
    vi.advanceTimersByTime(4000)

    addToast({ message: 'Cita creada de nuevo', duration: 5000, key: 'appointment.created:42' })
    vi.advanceTimersByTime(4000)

    // La cuenta vieja ya habría vencido: sigue vivo por el reinicio.
    expect(globalToasts.value).toHaveLength(1)

    vi.advanceTimersByTime(1000)
    expect(globalToasts.value).toHaveLength(0)
  })

  it('respeta persistent y duration: 0 como peticiones explícitas', () => {
    vi.useFakeTimers()

    addToast({ message: 'Persistente', duration: 1000, persistent: true })
    addToast({ message: 'Sin caducidad', duration: 0 })

    vi.advanceTimersByTime(60000)
    expect(globalToasts.value).toHaveLength(2)
  })

  it('cancela los temporizadores al limpiar la pila', () => {
    vi.useFakeTimers()

    addToast({ message: 'Uno', duration: 1000 })
    clearAll()

    vi.advanceTimersByTime(5000)
    expect(globalToasts.value).toHaveLength(0)
  })
})

describe('ToastContainer — pila y barra de progreso', () => {
  it('vive en la única esquina del riel: arriba-derecha, bajo la topbar', () => {
    const wrapper = mount(ToastContainer)

    const stack = wrapper.find('[data-toast-stack]')
    expect(stack.classes()).toContain('fixed')
    expect(stack.classes()).toContain('top-20')
    expect(stack.classes()).toContain('inset-x-4')
    expect(stack.classes()).toContain('sm:right-6')
    expect(stack.classes()).toContain('z-fixed')
    expect(stack.classes()).toContain('max-w-sm')
    expect(stack.classes()).not.toContain('bottom-0')
    expect(stack.classes()).not.toContain('bottom-6')

    const list = wrapper.find('[data-toast-list]')
    expect(list.classes()).toContain('max-w-sm')
    expect(list.classes()).toContain('gap-3')

    wrapper.unmount()
  })

  it('publica el alto de la pila para que el aviso persistente se apile debajo', async () => {
    const rect = vi.spyOn(Element.prototype, 'getBoundingClientRect').mockReturnValue({
      height: 96,
      width: 384
    })

    const toasts = mount(ToastContainer)
    addToast({ message: 'Cita creada', duration: 5000, key: 'appointment.created:42' })
    addNotification('Cita creada en la agenda.', 'info', { persistent: true })
    await nextTick()
    await nextTick()

    const notifications = mount(NotificationToast)
    await nextTick()

    // 80 px del riel + 96 px medidos + 12 px de gap-3.
    const rail = notifications.find('[data-notification-toast-stack]')
    expect(rail.attributes('style')).toContain('top: 188px')

    notifications.unmount()
    toasts.unmount()
    rect.mockRestore()
  })

  it('pinta una barra de progreso con la duración del toast', async () => {
    addToast({ message: 'Cita creada', duration: 5000, key: 'appointment.created:42' })
    const wrapper = mount(ToastContainer)
    await nextTick()

    const progress = wrapper.find('[data-toast-progress]')
    expect(progress.exists()).toBe(true)
    expect(progress.attributes('style')).toContain('5000ms')

    wrapper.unmount()
  })

  it('reinicia la barra cuando la misma clave actualiza el toast', async () => {
    addToast({ message: 'Cita creada', duration: 5000, key: 'appointment.created:42' })
    const wrapper = mount(ToastContainer)
    await nextTick()

    const before = wrapper.find('[data-toast-progress]').element

    addToast({ message: 'Cita creada otra vez', duration: 8000, key: 'appointment.created:42' })
    await nextTick()

    const after = wrapper.find('[data-toast-progress]').element
    expect(after).not.toBe(before)
    expect(after.getAttribute('style')).toContain('8000ms')

    wrapper.unmount()
  })
})

describe('Toast — el temporizador vive con el componente', () => {
  it('arranca al montar, se reinicia al cambiar la clave y se limpia al desmontar', async () => {
    vi.useFakeTimers()

    const wrapper = mount(Toast, {
      props: { type: 'info', duration: 1000, toastKey: 'a' },
      slots: { default: 'Cita creada' }
    })
    await nextTick()

    vi.advanceTimersByTime(800)
    await wrapper.setProps({ toastKey: 'b' })

    vi.advanceTimersByTime(800)
    expect(wrapper.emitted('dismiss')).toBeUndefined()

    vi.advanceTimersByTime(200)
    expect(wrapper.emitted('dismiss')).toHaveLength(1)

    wrapper.unmount()
  })

  it('no caduca con persistent ni con duration 0', async () => {
    vi.useFakeTimers()

    const wrapper = mount(Toast, {
      props: { duration: 1000, persistent: true },
      slots: { default: 'Persistente' }
    })
    await nextTick()

    vi.advanceTimersByTime(60000)
    expect(wrapper.emitted('dismiss')).toBeUndefined()

    wrapper.unmount()
  })

  it('pinta su barra de progreso cuando caduca', async () => {
    const wrapper = mount(Toast, {
      props: { duration: 4000 },
      slots: { default: 'Cita creada' }
    })
    await nextTick()

    const progress = wrapper.find('[data-toast-progress]')
    expect(progress.exists()).toBe(true)
    expect(progress.attributes('style')).toContain('4000ms')

    wrapper.unmount()
  })
})
