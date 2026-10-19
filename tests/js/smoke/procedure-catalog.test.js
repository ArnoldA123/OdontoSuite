import { describe, it, expect, vi } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { createRouter, createMemoryHistory } from 'vue-router'

const procedure = {
  id: 7,
  code: 'ENDO-001',
  name: 'Endodoncia unirradicular',
  description: 'Tratamiento de conducto',
  specialty: 'endodoncia',
  specialty_id: 2,
  specialty_name: 'Endodoncia',
  default_cost: 150,
  default_duration_minutes: 45,
  is_active: true,
  materials_needed: 'Lima K',
  requires_anesthesia: true,
  requires_radiographs: true
}

vi.mock('../../../resources/js/composables/useApi', () => ({
  useApi: () => ({
    get: async url => {
      if (url.includes('/procedure-catalog/summary')) {
        return {
          data: {
            total: 3,
            active: 2,
            inactive: 1,
            avg_duration_minutes: 60,
            avg_cost: 200,
            by_specialty: []
          }
        }
      }
      if (url.includes('/api/procedure-catalog?')) {
        return {
          data: [procedure],
          meta: { total: 1, per_page: 20, current_page: 1, last_page: 1 }
        }
      }
      return { data: [] }
    },
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
import Page from '../../../resources/js/modules/procedure-catalog/ProcedureCatalogPage.vue'

const router = createRouter({
  history: createMemoryHistory(),
  routes: [{ path: '/:pathMatch(.*)*', component: { template: '<div />' } }]
})

describe('procedure-catalog smoke', () => {
  it('mounts the catalog and renders hero, KPIs and rows', async () => {
    const wrapper = mount(Page, { global: { plugins: [router, uiComponents] } })
    await flushPromises()

    expect(wrapper.text()).toContain('Catálogo de Procedimientos')
    expect(wrapper.find('[data-procedure-catalog-hero]').exists()).toBe(true)
    expect(wrapper.find('[data-hero-title]').text()).toContain('Catálogo de Procedimientos')
    expect(wrapper.find('[data-kpi-card="total"] [data-kpi-value="total"]').text()).toBe('3')
    expect(wrapper.find('[data-kpi-card="active"] [data-kpi-value="active"]').text()).toBe('2')
    expect(wrapper.find('[data-kpi-card="avg-cost"] [data-kpi-value="avg-cost"]').text()).toContain(
      '200.00'
    )
    expect(wrapper.find('[data-procedure-row]').exists()).toBe(true)
    expect(wrapper.text()).toContain('Endodoncia unirradicular')
    expect(wrapper.text()).toContain('Endodoncia')
    expect(wrapper.find('[data-row-action="duplicate"]').exists()).toBe(true)

    wrapper.unmount()
  })

  it('opens the duplicate modal with a prefilled code', async () => {
    const wrapper = mount(Page, { global: { plugins: [router, uiComponents] } })
    await flushPromises()

    await wrapper.find('[data-row-action="duplicate"]').trigger('click')
    await flushPromises()

    expect(document.body.textContent).toContain('Duplicar procedimiento')
    const inputValues = Array.from(document.querySelectorAll('input')).map(input => input.value)
    expect(inputValues).toContain('ENDO-001-COPIA')
    expect(inputValues).toContain('Endodoncia unirradicular (copia)')

    wrapper.unmount()
  })
})
