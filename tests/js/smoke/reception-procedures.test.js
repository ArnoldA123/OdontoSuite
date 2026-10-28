import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { createRouter, createMemoryHistory } from 'vue-router'

const procedure = {
  id: 7,
  code: 'ENDO-001',
  name: 'Endodoncia unirradicular',
  description: 'Tratamiento de conducto',
  specialty_name: 'Endodoncia',
  default_cost: 150,
  default_duration_minutes: 45,
  is_active: true
}

const requestedUrls = []

// #452 (U11, states) — the catalog request is held from a test to observe the
// frame the browser paints while the first read is in flight, and answered with
// an empty list to read the declared empty state.
const harness = vi.hoisted(() => ({ catalogGate: null, emptyCatalog: false }))

vi.mock('../../../resources/js/composables/useApi', () => ({
  useApi: () => ({
    get: async url => {
      requestedUrls.push(url)
      if (url.includes('/api/procedure-catalog?')) {
        if (harness.catalogGate) await harness.catalogGate
        if (harness.emptyCatalog) {
          return { data: [], meta: { total: 0, per_page: 12, current_page: 1, last_page: 1 } }
        }
        return {
          data: [procedure],
          meta: { total: 12, per_page: 12, current_page: 1, last_page: 1 }
        }
      }
      if (url.includes('/api/specialties/active')) {
        return { data: [{ id: 2, code: 'endodoncia', name: 'Endodoncia' }] }
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
import Page from '../../../resources/js/modules/reception-procedures/ReceptionProceduresPage.vue'

const router = createRouter({
  history: createMemoryHistory(),
  routes: [{ path: '/:pathMatch(.*)*', component: { template: '<div />' } }]
})

const deferred = () => {
  let resolve
  const promise = new Promise(done => {
    resolve = done
  })

  return { promise, resolve }
}

beforeEach(() => {
  harness.catalogGate = null
  harness.emptyCatalog = false
})

describe('reception-procedures smoke', () => {
  it('paints the loading primitive from the first frame, before any payload', async () => {
    const gate = deferred()
    harness.catalogGate = gate.promise

    const wrapper = mount(Page, { global: { plugins: [router, uiComponents] } })

    // The specialties lookup runs before the catalog read, so this frame is the
    // one the browser paints: it used to be the «Sin resultados» empty state.
    expect(wrapper.find('.loading-spinner-container').exists()).toBe(true)
    expect(wrapper.find('.empty-state').exists()).toBe(false)

    await flushPromises()
    expect(wrapper.find('[data-reception-loading]').exists()).toBe(true)
    expect(wrapper.find('[data-procedure-card]').exists()).toBe(false)

    harness.catalogGate = null
    gate.resolve()
    await flushPromises()

    expect(wrapper.find('.loading-spinner-container').exists()).toBe(false)
    expect(wrapper.findAll('[data-procedure-card]')).toHaveLength(1)

    wrapper.unmount()
  })

  it('declares the empty state when the catalog answers with no procedures', async () => {
    harness.emptyCatalog = true

    const wrapper = mount(Page, { global: { plugins: [router, uiComponents] } })
    await flushPromises()

    const empty = wrapper.find('.empty-state')
    expect(empty.exists()).toBe(true)
    expect(empty.text()).toContain('Sin resultados')
    expect(wrapper.find('.loading-spinner-container').exists()).toBe(false)

    wrapper.unmount()
  })

  it('renders the hero, page KPIs, filters and procedure cards', async () => {
    const wrapper = mount(Page, { global: { plugins: [router, uiComponents] } })
    await flushPromises()

    expect(wrapper.find('[data-reception-hero]').exists()).toBe(true)
    expect(wrapper.find('[data-hero-title]').text()).toContain('Catálogo de Procedimientos')
    expect(wrapper.find('[data-kpi-card="total"]').text()).toContain('12')
    expect(wrapper.find('[data-kpi-card="avg-price"]').text()).toContain('150')
    expect(wrapper.find('[data-kpi-card="avg-duration"]').text()).toContain('45')
    expect(wrapper.find('[data-reception-search]').exists()).toBe(true)
    expect(wrapper.find('[data-procedure-card]').exists()).toBe(true)
    expect(wrapper.text()).toContain('Endodoncia unirradicular')
    expect(wrapper.text()).toContain('Endodoncia')

    wrapper.unmount()
  })

  it('sends the term as q, the parameter the index reads', async () => {
    requestedUrls.length = 0
    const wrapper = mount(Page, { global: { plugins: [router, uiComponents] } })
    await flushPromises()

    await wrapper.find('[data-reception-search] input').setValue('REST-COMP-1S')
    await new Promise(resolve => setTimeout(resolve, 400))
    await flushPromises()

    const catalogCalls = requestedUrls.filter(url => url.includes('/api/procedure-catalog?'))
    const lastCall = catalogCalls[catalogCalls.length - 1]

    expect(lastCall).toBeDefined()
    expect(lastCall).toContain('q=REST-COMP-1S')
    expect(lastCall).not.toContain('search=')

    wrapper.unmount()
  })

  it('mounts the filter bar primitive around the filter row', async () => {
    const wrapper = mount(Page, { global: { plugins: [router, uiComponents] } })
    await flushPromises()

    const bar = wrapper.find('[data-reception-filters]')

    expect(bar.exists()).toBe(true)
    expect(bar.attributes('role')).toBe('search')
    expect(bar.find('[data-reception-search]').exists()).toBe(true)

    wrapper.unmount()
  })

  it('«Limpiar filtros» restores the defaults with a single request', async () => {
    requestedUrls.length = 0
    const wrapper = mount(Page, { global: { plugins: [router, uiComponents] } })
    await flushPromises()

    await wrapper.find('[data-reception-search] input').setValue('ENDO')
    await new Promise(resolve => setTimeout(resolve, 400))
    await flushPromises()

    requestedUrls.length = 0

    const clear = wrapper.find('[data-reception-clear-filters]')

    expect(clear.text()).toBe('Limpiar filtros')

    await clear.trigger('click')
    await new Promise(resolve => setTimeout(resolve, 400))
    await flushPromises()

    const catalogCalls = requestedUrls.filter(url => url.includes('/api/procedure-catalog?'))

    expect(catalogCalls).toHaveLength(1)
    expect(catalogCalls[0]).not.toContain('q=ENDO')
    expect(wrapper.find('[data-reception-search] input').element.value).toBe('')

    wrapper.unmount()
  })
})
