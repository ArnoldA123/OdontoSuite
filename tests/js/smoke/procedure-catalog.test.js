import { beforeEach, describe, it, expect, vi } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { createRouter, createMemoryHistory } from 'vue-router'
import { nextTick } from 'vue'
import { readFileSync } from 'node:fs'
import { resolve } from 'node:path'

const { getMock, postMock, state } = vi.hoisted(() => ({
  getMock: vi.fn(),
  postMock: vi.fn(),
  state: { rows: [], detail: null }
}))

const procedure = {
  id: 7,
  code: 'ENDO-001',
  name: 'Endodoncia unirradicular',
  description: 'Tratamiento de conducto',
  specialty: 'endodoncia',
  specialty_id: 2,
  specialty_name: 'Endodoncia',
  site_type: 'surface',
  external_codes: [{ system: 'minsa_rm550_2023', code: '997301', description: 'Obturación' }],
  default_cost: 150,
  default_duration_minutes: 45,
  is_active: true,
  materials_needed: 'Lima K',
  requires_anesthesia: true,
  requires_radiographs: true
}

const rows = [
  procedure,
  ...Array.from({ length: 19 }, (_, index) => ({
    ...procedure,
    id: index + 8,
    code: `PROC-${index + 8}`,
    name: `Procedimiento ${index + 8}`,
    description: ''
  }))
]

const detail = {
  ...procedure,
  material_recipe: [
    {
      product_id: 3,
      product_name: 'Resina A2',
      unit: 'jeringa',
      cost_price: 20,
      stock_quantity: 12,
      quantity: 2
    }
  ]
}

vi.mock('../../../resources/js/composables/useApi', () => ({
  useApi: () => ({
    get: getMock,
    post: postMock,
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
import Page from '../../../resources/js/modules/procedure-catalog/ProcedureCatalogPage.vue'
import DataTable from '../../../resources/js/components/ui/DataTable.vue'

const flat = value => String(value).replace(/\s+/g, ' ')

const makeRouter = () =>
  createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: '/procedure-catalog', component: { template: '<div />' } },
      { path: '/:pathMatch(.*)*', component: { template: '<div />' } }
    ]
  })

const mountPage = async (initialLocation = '/procedure-catalog') => {
  const router = makeRouter()
  await router.push(initialLocation)
  await router.isReady()
  const wrapper = mount(Page, { global: { plugins: [router, uiComponents] } })
  await flushPromises()
  return { wrapper, router }
}

const duplicateButton = () =>
  [...document.body.querySelectorAll('button')].find(
    button => button.textContent.trim() === 'Duplicar'
  )

// jsdom performs no layout: the overflow the primitive advertises is declared by
// the test through the viewport geometry the primitive reads.
const declareTableOverflow = async (wrapper, { scrollWidth = 960, clientWidth = 640 } = {}) => {
  const viewport = wrapper.get('[data-procedure-table] [data-table-scroll-viewport]').element
  Object.defineProperty(viewport, 'scrollWidth', { configurable: true, value: scrollWidth })
  Object.defineProperty(viewport, 'clientWidth', { configurable: true, value: clientWidth })
  viewport.scrollLeft = 0
  window.dispatchEvent(new Event('resize'))
  await nextTick()
  await nextTick()
}

describe('procedure-catalog smoke', () => {
  beforeEach(() => {
    state.rows = rows
    state.detail = detail
    document.body.innerHTML = ''
    getMock.mockReset()
    postMock.mockReset()

    getMock.mockImplementation(async url => {
      if (url.includes('/procedure-catalog/summary')) {
        return {
          data: {
            total: 46,
            active: 40,
            inactive: 6,
            avg_duration_minutes: 60,
            avg_cost: 200,
            by_specialty: []
          }
        }
      }
      if (url.includes('/api/procedure-catalog?')) {
        return {
          data: state.rows,
          meta: { total: 46, per_page: 20, current_page: 1, last_page: 3 }
        }
      }
      if (url.startsWith('/api/procedure-catalog/')) return { data: state.detail }
      if (url.includes('/api/inventory/products')) return { data: [] }
      return { data: [] }
    })
    postMock.mockResolvedValue({ data: { name: 'Copia' } })
  })

  it('mounts the catalog and renders hero, KPIs, rows and the table contract', async () => {
    const { wrapper } = await mountPage()

    expect(wrapper.text()).toContain('Catálogo de Procedimientos')
    expect(wrapper.find('[data-procedure-catalog-hero]').exists()).toBe(true)
    expect(wrapper.find('[data-hero-title]').text()).toContain('Catálogo de Procedimientos')
    expect(wrapper.find('[data-kpi-card="total"] [data-kpi-value="total"]').text()).toBe('46')
    expect(wrapper.find('[data-kpi-card="active"] [data-kpi-value="active"]').text()).toBe('40')
    expect(wrapper.find('[data-kpi-card="avg-cost"] [data-kpi-value="avg-cost"]').text()).toContain(
      '200.00'
    )
    expect(wrapper.findAll('[data-procedure-row]')).toHaveLength(20)
    expect(wrapper.text()).toContain('Endodoncia unirradicular')
    expect(wrapper.text()).toContain('Endodoncia')
    expect(wrapper.find('[data-row-action="duplicate"]').exists()).toBe(true)

    // #436: the list rides the module's table primitive, so the overflow
    // affordance and the scroll hook arrive with it.
    expect(wrapper.find('[data-procedure-table] [data-table-scroll]').exists()).toBe(true)

    // #453: the primitive renders the same columns the page declared.
    const header = wrapper.get('[data-procedure-table] thead').text()
    for (const label of ['Código', 'Procedimiento', 'Especialidad', 'Duración', 'Costo']) {
      expect(header).toContain(label)
    }
    expect(header).toContain('Estado')
    expect(header).toContain('Acciones')

    wrapper.unmount()
  })

  it('publishes the primitive scroll affordance when the columns overflow', async () => {
    const { wrapper } = await mountPage()

    expect(wrapper.findComponent(DataTable).exists()).toBe(true)
    expect(wrapper.find('[data-table-scroll-affordance]').exists()).toBe(false)

    await declareTableOverflow(wrapper)

    expect(wrapper.get('[data-table-scroll]').attributes('data-table-scroll')).toBe('right')
    const hint = wrapper.get('[data-table-scroll-affordance]')
    expect(hint.text()).toMatch(/desliza/i)
    expect(hint.text()).toMatch(/columnas/i)

    wrapper.unmount()
  })

  it('reports the applied page size in the pagination', async () => {
    const { wrapper } = await mountPage()

    expect(flat(wrapper.text())).toContain('Mostrando 1 a 20 de 46 resultados')

    wrapper.unmount()
  })

  it('opens the duplicate modal with a prefilled code', async () => {
    const { wrapper } = await mountPage()

    await wrapper.find('[data-row-action="duplicate"]').trigger('click')
    await flushPromises()

    expect(document.body.textContent).toContain('Duplicar procedimiento')
    const inputValues = Array.from(document.querySelectorAll('input')).map(input => input.value)
    expect(inputValues).toContain('ENDO-001-COPIA')
    expect(inputValues).toContain('Endodoncia unirradicular (copia)')

    wrapper.unmount()
  })

  it('proposes a copy code that fits the 20-character column', async () => {
    state.rows = [{ ...procedure, code: 'AUDIT-U8-REST-COMP-S' }, ...rows.slice(1)]
    const { wrapper } = await mountPage()

    await wrapper.find('[data-row-action="duplicate"]').trigger('click')
    await flushPromises()

    const proposal = Array.from(document.querySelectorAll('input')).find(input =>
      input.value.endsWith('-COPIA')
    )
    expect(proposal).toBeTruthy()
    expect(proposal.value.length).toBeLessThanOrEqual(20)

    wrapper.unmount()
  })

  it('copies applicability, equivalences and the input recipe into the duplicate', async () => {
    const { wrapper } = await mountPage()

    await wrapper.find('[data-row-action="duplicate"]').trigger('click')
    await flushPromises()
    duplicateButton().click()
    await flushPromises()
    await flushPromises()

    expect(postMock).toHaveBeenCalledTimes(1)
    const [url, payload] = postMock.mock.calls[0]
    expect(url).toBe('/api/procedure-catalog')
    expect(payload).toMatchObject({
      code: 'ENDO-001-COPIA',
      name: 'Endodoncia unirradicular (copia)',
      site_type: 'surface',
      external_codes: [{ system: 'minsa_rm550_2023', code: '997301', description: 'Obturación' }],
      material_recipe: [{ product_id: 3, quantity: 2 }]
    })

    wrapper.unmount()
  })

  it('opens the form for ?edit= and consumes the parameter without remounting', async () => {
    const { wrapper, router } = await mountPage('/procedure-catalog?edit=7')

    expect(document.body.textContent).toContain('Editar procedimiento')
    const codeInput = Array.from(document.querySelectorAll('input')).find(
      input => input.value === 'ENDO-001'
    )
    expect(codeInput).toBeTruthy()
    // The form is fed by the detail, so the recipe arrives with it.
    expect(document.querySelectorAll('[data-material-recipe-row]').length).toBeGreaterThan(0)
    expect(router.currentRoute.value.query.edit).toBeUndefined()

    wrapper.unmount()
  })

  it('edits from the procedure detail, not the branch-resolved index row', async () => {
    // #431 publishes the branch price in the index; the form must edit the list
    // price the catalog declares and keep the recipe (the row omits it).
    state.rows = [{ ...procedure, default_cost: 180, list_cost: 150 }, ...rows.slice(1)]
    state.detail = { ...detail, default_cost: 150 }
    const { wrapper } = await mountPage()

    await wrapper.find('[data-row-action="edit"]').trigger('click')
    await flushPromises()

    expect(document.body.textContent).toContain('Editar procedimiento')
    const cost = document.querySelector('[data-form-group="pricing"] input[type="number"]')
    expect(cost.value).toBe('150')
    expect(document.querySelectorAll('[data-material-recipe-row]').length).toBeGreaterThan(0)

    wrapper.unmount()
  })

  it('keeps the catalog view mounted while the edit parameter is consumed', () => {
    const source = readFileSync(resolve('resources/js/app.js'), 'utf8')
    const routeBlock = source.slice(
      source.indexOf("path: '/procedure-catalog',"),
      source.indexOf("path: '/procedure-catalog/:id'")
    )
    expect(routeBlock).toContain('preserveOnQuery: true')
  })
})
