import { describe, it, expect, vi } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { createRouter, createMemoryHistory } from 'vue-router'
vi.mock('../../../resources/js/composables/useApi', () => ({
  useApi: () => ({
    get: async () => ({ data: [] }),
    post: async () => ({ data: {} }),
    put: async () => ({ data: {} }),
    patch: async () => ({ data: {} }),
    delete: async () => ({ data: {} }),
    setToken: () => {},
    normalizeError: () => ''
  })
}))
vi.mock('../../../resources/js/composables/useEcho', () => ({
  useEcho: () => ({
    echo: {},
    channel: () => ({}),
    privateChannel: () => ({}),
    connectionStatus: { value: 'connected' }
  })
}))
vi.mock('../../../resources/js/composables/useWebSocketNotifications', () => ({
  useWebSocketNotifications: () => ({})
}))
import uiComponents from '../../../resources/js/plugins/ui-components'
import Page from '../../../resources/js/modules/appointments/CalendarPage.vue'
import NewAppointmentModal from '../../../resources/js/components/appointments/NewAppointmentModal.vue'
const router = createRouter({
  history: createMemoryHistory(),
  routes: [{ path: '/:pathMatch(.*)*', component: { template: '<div />' } }]
})
describe('appointments smoke', () => {
  it('mounts CalendarPage.vue and renders content', async () => {
    const wrapper = mount(Page, { global: { plugins: [router, uiComponents] } })
    await flushPromises()
    expect(wrapper.text()).toContain('Agenda')
    wrapper.unmount()
  })

  // T4 click-to-create: behavioural anchor for the slot → modal prefill
  // contract (the data-calendar-* hooks + permission gate are pinned
  // structurally by CitasCalendarAppShellTest; this case exercises the
  // real wiring end to end in the mounted page).
  it('opens NewAppointmentModal prefilled when an empty slot is clicked (T4)', async () => {
    const wrapper = mount(Page, { global: { plugins: [router, uiComponents] } })
    await flushPromises()

    const slot = wrapper.find('button[data-calendar-slot]')
    expect(slot.exists()).toBe(true)

    const key = slot.attributes('data-calendar-slot')
    const dateStr = key.slice(0, 10)
    const hour = Number(key.slice(11))
    expect(key).toBe(`${dateStr}-${hour}`)

    await slot.trigger('click')
    await flushPromises()

    const modal = wrapper.findComponent(NewAppointmentModal)
    expect(modal.props('modelValue')).toBe(true)
    expect(modal.props('initialDate')).toBe(`${dateStr}T${String(hour).padStart(2, '0')}:00`)
    wrapper.unmount()
  })
})
