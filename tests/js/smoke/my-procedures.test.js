import { describe, it, expect, vi } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { createRouter, createMemoryHistory } from 'vue-router'

const favorite = {
  id: 1,
  code: 'ENDO-001',
  name: 'Endodoncia unirradicular',
  specialty_name: 'Endodoncia',
  default_cost: 150,
  default_duration_minutes: 45,
  position: 1
}
const suggested = {
  id: 2,
  code: 'REST-001',
  name: 'Restauración con resina',
  specialty_name: 'Rehabilitación',
  default_cost: 90,
  default_duration_minutes: 30,
  is_favorite: false
}
const searchResult = {
  id: 3,
  code: 'ORTO-001',
  name: 'Corona zirconio',
  specialty_name: 'Ortodoncia',
  default_cost: 800,
  default_duration_minutes: 60,
  label: 'ORTO-001 — Corona zirconio'
}

vi.mock('../../../resources/js/composables/useApi', () => ({
  useApi: () => ({
    get: async url => {
      if (url.includes('procedure-catalog-favorites')) return { data: [favorite] }
      if (url.includes('for-me')) return { data: [favorite, suggested] }
      if (url.includes('procedure-catalog/search')) return { data: [searchResult] }
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
import Page from '../../../resources/js/modules/my-procedures/MyProceduresPage.vue'

const router = createRouter({
  history: createMemoryHistory(),
  routes: [{ path: '/:pathMatch(.*)*', component: { template: '<div />' } }]
})

describe('my-procedures smoke', () => {
  it('renders favorites, suggestions and the global explorer results', async () => {
    const wrapper = mount(Page, { global: { plugins: [router, uiComponents] } })
    await flushPromises()

    expect(wrapper.find('[data-my-procedures-hero]').exists()).toBe(true)
    expect(wrapper.find('[data-hero-title]').text()).toContain('Mis Procedimientos')
    expect(wrapper.find('[data-favorites-section]').exists()).toBe(true)
    expect(wrapper.find('[data-favorite-row]').exists()).toBe(true)
    expect(wrapper.text()).toContain('Endodoncia unirradicular')
    expect(wrapper.find('[data-suggestions-section]').exists()).toBe(true)
    expect(wrapper.text()).toContain('Restauración con resina')

    const input = wrapper.find('[data-explore-search] input')
    await input.setValue('corona')
    await new Promise(resolve => setTimeout(resolve, 400))
    await flushPromises()

    expect(wrapper.find('[data-explorer-section]').exists()).toBe(true)
    expect(wrapper.find('[data-explorer-row]').exists()).toBe(true)
    expect(wrapper.text()).toContain('Corona zirconio')

    wrapper.unmount()
  })
})
