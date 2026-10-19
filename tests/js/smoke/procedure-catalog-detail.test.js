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
  requirements: 'Radiografía previa',
  materials_needed: 'Lima K, irrigante',
  materials_needed_list: ['Lima K', 'irrigante'],
  requires_anesthesia: true,
  requires_radiographs: true,
  contraindications: 'Infección aguda',
  post_procedure_care: 'Evitar masticar 24 h',
  is_active: true
}

const version = {
  id: 11,
  change_type: 'updated',
  changed_fields: { name: 'Endodoncia actualizada' },
  default_cost: 150,
  name: 'Endodoncia actualizada',
  code: 'ENDO-001',
  is_active: true,
  changed_at: '2026-10-18T10:30:00-05:00',
  changed_by: { id: 1, name: 'Catalog Admin' }
}

const auditLog = {
  id: 5,
  action: 'procedure_catalog_updated',
  old_values: { name: 'Endodoncia unirradicular' },
  new_values: { name: 'Endodoncia actualizada' },
  created_at: '2026-10-18T10:30:00-05:00',
  user: { id: 1, name: 'Catalog Admin' }
}

vi.mock('../../../resources/js/composables/useApi', () => ({
  useApi: () => ({
    get: async url => {
      if (url.includes('/versions')) return { data: [version] }
      if (url.includes('/api/audit-logs')) return { data: [auditLog], meta: { total: 1 } }
      if (url.includes('/api/procedure-catalog/7')) return { data: procedure }
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
import Page from '../../../resources/js/modules/procedure-catalog/ProcedureCatalogDetailPage.vue'

const router = createRouter({
  history: createMemoryHistory(),
  routes: [{ path: '/procedure-catalog/:id', component: { template: '<div />' } }]
})

const mountPage = async () => {
  await router.push('/procedure-catalog/7')
  await router.isReady()
  const wrapper = mount(Page, { global: { plugins: [router, uiComponents] } })
  await flushPromises()
  await new Promise(resolve => setTimeout(resolve, 0))
  await flushPromises()
  return wrapper
}

describe('procedure-catalog detail', () => {
  it('renders the hero, clinical data and the version history tab', async () => {
    const wrapper = await mountPage()
    await flushPromises()

    expect(wrapper.find('[data-detail-hero]').exists()).toBe(true)
    expect(wrapper.find('[data-detail-title]').text()).toContain('Endodoncia unirradicular')
    expect(wrapper.text()).toContain('ENDO-001')
    expect(wrapper.text()).toContain('Radiografía previa')
    expect(wrapper.text()).toContain('Lima K')
    expect(wrapper.text()).toContain('Evitar masticar 24 h')

    await wrapper.find('#tab-versions').trigger('click')
    await flushPromises()

    expect(wrapper.find('[data-versions-list]').exists()).toBe(true)
    expect(wrapper.text()).toContain('Endodoncia actualizada')
    expect(wrapper.text()).toContain('Catalog Admin')

    wrapper.unmount()
  })

  it('loads the audit tab with the procedure filter', async () => {
    const wrapper = await mountPage()
    await flushPromises()

    await wrapper.find('#tab-audit').trigger('click')
    await flushPromises()

    expect(wrapper.find('[data-audit-list]').exists()).toBe(true)
    expect(wrapper.text()).toContain('Actualizado')
    expect(wrapper.text()).toContain('Catalog Admin')

    wrapper.unmount()
  })
})
