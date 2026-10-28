import { beforeEach, describe, it, expect, vi } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { createRouter, createMemoryHistory } from 'vue-router'

const { getMock } = vi.hoisted(() => ({ getMock: vi.fn() }))

const procedure = {
  id: 7,
  code: 'ENDO-001',
  name: 'Endodoncia unirradicular',
  description: 'Tratamiento de conducto',
  specialty: 'endodoncia',
  specialty_id: 2,
  specialty_name: 'Endodoncia',
  site_type: 'tooth',
  default_cost: 150,
  default_duration_minutes: 45,
  requirements: 'Radiografía previa',
  materials_needed: 'Lima K, irrigante',
  materials_needed_list: ['Lima K', 'irrigante'],
  external_codes: [{ system: 'minsa_rm550_2023', code: '997301', description: 'Obturación' }],
  material_recipe: [
    {
      product_id: 3,
      product_name: 'Resina A2',
      unit: 'jeringa',
      cost_price: 20,
      stock_quantity: 12,
      quantity: 1.5
    }
  ],
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
    get: getMock,
    post: vi.fn(async () => ({ data: {} })),
    put: vi.fn(async () => ({ data: {} })),
    patch: vi.fn(async () => ({ data: {} })),
    delete: vi.fn(async () => ({ data: {} })),
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

const makeRouter = () => {
  const router = createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: '/procedure-catalog', component: { template: '<div />' } },
      { path: '/procedure-catalog/:id', component: { template: '<div />' } }
    ]
  })

  return router
}

const mountPage = async () => {
  const router = makeRouter()
  await router.push('/procedure-catalog/7')
  await router.isReady()
  const wrapper = mount(Page, { global: { plugins: [router, uiComponents] } })
  await flushPromises()
  await new Promise(resolve => setTimeout(resolve, 0))
  await flushPromises()
  return { wrapper, router }
}

const flat = value => String(value).replace(/\s+/g, ' ')

describe('procedure-catalog detail', () => {
  beforeEach(() => {
    getMock.mockReset()
    getMock.mockImplementation(async url => {
      if (url.includes('/versions')) return { data: [version] }
      if (url.includes('/api/audit-logs')) return { data: [auditLog], meta: { total: 1 } }
      if (url.includes('/api/procedure-catalog/7')) return { data: procedure }
      return { data: [] }
    })
  })

  it('renders the hero, clinical data and the version history tab', async () => {
    const { wrapper } = await mountPage()

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

  it('publishes the input recipe the encounter closure consumes (#434)', async () => {
    const { wrapper } = await mountPage()

    expect(wrapper.find('[data-detail-group="material-recipe"]').exists()).toBe(true)
    expect(wrapper.find('[data-material-recipe-list]').exists()).toBe(true)
    expect(wrapper.findAll('[data-material-recipe-row]')).toHaveLength(1)
    expect(wrapper.find('[data-material-recipe-name]').text()).toBe('Resina A2')
    expect(flat(wrapper.find('[data-material-recipe-quantity]').text())).toContain('1.5 jeringa')

    wrapper.unmount()
  })

  it('loads the audit tab with the model type the API stores (#435)', async () => {
    const { wrapper } = await mountPage()

    await wrapper.find('#tab-audit').trigger('click')
    await flushPromises()

    expect(wrapper.find('[data-audit-list]').exists()).toBe(true)
    expect(wrapper.text()).toContain('Actualizado')
    expect(wrapper.text()).toContain('Catalog Admin')

    const auditCall = getMock.mock.calls.find(([url]) => url.includes('/api/audit-logs'))
    expect(auditCall).toBeTruthy()
    expect(auditCall[0]).toContain('model_type=App%5CModels%5CProcedureCatalog')
    expect(auditCall[0]).toContain('auditable_id=7')

    wrapper.unmount()
  })

  it('opens the catalog form through the edit door with the loaded procedure (#435)', async () => {
    const { wrapper, router } = await mountPage()

    await wrapper.find('[data-detail-action="edit"]').trigger('click')
    await flushPromises()

    expect(router.currentRoute.value.path).toBe('/procedure-catalog')
    expect(router.currentRoute.value.query.edit).toBe('7')

    wrapper.unmount()
  })
})
