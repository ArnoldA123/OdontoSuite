import { describe, it, expect, vi } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { createRouter, createMemoryHistory } from 'vue-router'

const stats = {
  period: { from: '2026-07-21', to: '2026-10-19' },
  catalog: { total: 3, active: 2, inactive: 1 },
  top_procedures: [
    {
      procedure_id: 7,
      code: 'ENDO-001',
      name: 'Endodoncia unirradicular',
      usage_count: 12,
      total_quantity: 14,
      total_revenue: 2100
    }
  ],
  by_specialty: [
    {
      specialty_id: 2,
      specialty_code: 'endodoncia',
      specialty_name: 'Endodoncia',
      usage_count: 12,
      total_revenue: 2100
    }
  ]
}

vi.mock('../../../resources/js/composables/useApi', () => ({
  useApi: () => ({
    get: async () => ({ data: stats }),
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
import Page from '../../../resources/js/modules/procedure-catalog/ProcedureStatsPage.vue'

const router = createRouter({
  history: createMemoryHistory(),
  routes: [{ path: '/:pathMatch(.*)*', component: { template: '<div />' } }]
})

describe('procedure-stats smoke', () => {
  it('renders the hero, catalog KPIs and top procedures', async () => {
    const wrapper = mount(Page, { global: { plugins: [router, uiComponents] } })
    await flushPromises()

    expect(wrapper.find('[data-stats-hero]').exists()).toBe(true)
    expect(wrapper.find('[data-hero-title]').text()).toContain('Estadísticas de Procedimientos')
    expect(wrapper.find('[data-stat-card="total-procedures"]').text()).toContain('3')
    expect(wrapper.find('[data-stat-card="active"]').text()).toContain('2')
    expect(wrapper.find('[data-stat-card="inactive"]').text()).toContain('1')
    expect(wrapper.find('[data-top-row]').exists()).toBe(true)
    expect(wrapper.text()).toContain('Endodoncia unirradicular')
    expect(wrapper.text()).toContain('Endodoncia')
    expect(wrapper.find('[data-stats-error]').exists()).toBe(false)

    wrapper.unmount()
  })
})
