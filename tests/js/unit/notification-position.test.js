import { describe, it, expect, beforeEach } from 'vitest'
import { mount } from '@vue/test-utils'
import { nextTick } from 'vue'
import ToastContainer from '../../../resources/js/components/ToastContainer.vue'
import NotificationToast from '../../../resources/js/components/ui/NotificationToast.vue'
import NotificationCenter from '../../../resources/js/components/NotificationCenter.vue'
import { useToast } from '../../../resources/js/composables/useToast.js'
import { useNotifications } from '../../../resources/js/composables/useNotifications.js'

const { clearAll: clearToasts } = useToast()
const { clearAll: clearNotifications, addNotification } = useNotifications()

// Un solo riel para toda notificación visible: arriba-derecha, debajo de la
// topbar, con el margen derecho y el ancho máximo compartidos. La variante
// inferior (la que se encimaba con el FAB en recepción) no vuelve.
const RAIL_CLASSES = ['fixed', 'top-20', 'inset-x-4', 'sm:right-6', 'z-fixed', 'max-w-sm']

const expectRail = element => {
  for (const className of RAIL_CLASSES) {
    expect(element.classes(), `falta ${className} en el riel`).toContain(className)
  }
  expect(element.classes()).not.toContain('bottom-0')
  expect(element.classes()).not.toContain('bottom-6')
  expect(element.classes()).not.toContain('sm:bottom-6')
}

beforeEach(() => {
  clearToasts()
  clearNotifications()
})

describe('riel único de notificaciones', () => {
  it('el toast efímero vive arriba-derecha con el ancho y la separación compartidos', () => {
    const wrapper = mount(ToastContainer)

    expectRail(wrapper.find('[data-toast-stack]'))

    const list = wrapper.find('[data-toast-list]')
    expect(list.classes()).toContain('max-w-sm')
    expect(list.classes()).toContain('gap-3')

    wrapper.unmount()
  })

  it('el aviso persistente usa la misma esquina, margen y ancho que el toast', async () => {
    addNotification('Cita creada en la agenda.', 'info', { persistent: true })

    const wrapper = mount(NotificationToast)
    await nextTick()

    expectRail(wrapper.find('[data-notification-toast-stack]'))

    const list = wrapper.find('.notification-list')
    expect(list.classes()).toContain('max-w-sm')
    expect(list.classes()).toContain('gap-3')

    wrapper.unmount()
  })

  it('espera bajo la topbar y deja margen inferior del viewport', async () => {
    addNotification('Cita creada en la agenda.', 'info', { persistent: true })

    const wrapper = mount(NotificationToast)
    await nextTick()

    const rail = wrapper.find('[data-notification-toast-stack]')
    expect(rail.attributes('style')).toContain('top: 80px')

    const list = wrapper.find('.notification-list')
    expect(list.attributes('style')).toContain('calc(100dvh - 104px)')

    wrapper.unmount()
  })

  it('el centro de notificaciones se abre por encima del riel, en la misma esquina', () => {
    const wrapper = mount(NotificationCenter, { props: { isOpen: true } })

    const overlay = wrapper.find('.notification-center-overlay')
    // z-backdrop (1040) > z-fixed (1030): el panel nunca queda debajo de un
    // toast, y el riel tampoco queda debajo del panel.
    expect(overlay.classes()).toContain('z-backdrop')
    expect(overlay.classes()).toContain('pt-20')
    expect(overlay.classes()).toContain('pr-4')
    expect(overlay.classes()).toContain('sm:pr-6')

    wrapper.unmount()
  })
})
