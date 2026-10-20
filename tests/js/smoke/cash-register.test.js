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
import Page from '../../../resources/js/modules/cash-register/CashRegisterPage.vue'
const router = createRouter({
  history: createMemoryHistory(),
  routes: [{ path: '/:pathMatch(.*)*', component: { template: '<div />' } }]
})
describe('cash-register smoke', () => {
  it('mounts the Caja shell with its hero and KPI strip', async () => {
    const wrapper = mount(Page, { global: { plugins: [router, uiComponents] } })
    await flushPromises()

    expect(wrapper.find('[data-caja-hero]').exists()).toBe(true)
    expect(wrapper.find('[data-hero-title]').text()).toBe('Caja')
    expect(wrapper.findAll('[data-kpi-card]')).toHaveLength(4)
    expect(wrapper.text()).toContain('Saldo esperado')
    expect(wrapper.find('[data-kpi-caption="expected"]').text()).toBe('Apertura + movimientos')
    expect(wrapper.text()).not.toContain('Gestión de Caja')
    wrapper.unmount()
  })
})
