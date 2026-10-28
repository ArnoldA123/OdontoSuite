import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'

// #416: the picker must ask the server for the written term. Before the fix it
// filtered the 12 rows already loaded and reloaded page 1 without the term, so
// a procedure outside that page could never be reached.
const api = vi.hoisted(() => ({ calls: [], searchRows: [] }))

vi.mock('../../../resources/js/composables/useApi', () => ({
  useApi: () => ({
    get: async url => {
      api.calls.push(url)
      const query = new URL(url, 'http://localhost').searchParams
      const term = query.get('q') || ''

      if (term !== '') {
        return {
          data: api.searchRows,
          meta: {
            current_page: Number(query.get('page') || 1),
            last_page: 3,
            per_page: 12,
            total: 30
          }
        }
      }

      return {
        data: [
          {
            id: 1,
            code: 'LIMP-001',
            name: 'Limpieza dental',
            specialty_name: 'Odontología general',
            default_duration_minutes: 30,
            default_cost: 80
          }
        ],
        meta: { current_page: 1, last_page: 4, per_page: 12, total: 46 }
      }
    },
    post: async () => ({ data: {} }),
    put: async () => ({ data: {} }),
    patch: async () => ({ data: {} }),
    delete: async () => ({ data: {} }),
    setToken: () => {},
    normalizeError: () => ''
  })
}))

import uiComponents from '../../../resources/js/plugins/ui-components'
import ProcedureCatalogPicker from '../../../resources/js/components/procedures/ProcedureCatalogPicker.vue'
import UiPagination from '../../../resources/js/components/ui/Pagination.vue'

const mountPicker = async (props = {}) => {
  const wrapper = mount(ProcedureCatalogPicker, {
    props: { modelValue: null, ...props },
    global: { plugins: [uiComponents] },
    attachTo: document.body
  })
  await flushPromises()
  return wrapper
}

const typeSearch = async (wrapper, term) => {
  const input = wrapper.get('input[type="text"]')
  input.element.value = term
  input.element.dispatchEvent(new Event('input', { bubbles: true }))
  await new Promise(resolve => setTimeout(resolve, 400))
  await flushPromises()
}

const lastCall = () => api.calls[api.calls.length - 1]

describe('procedure catalog picker search', () => {
  beforeEach(() => {
    api.calls.length = 0
    api.searchRows = [
      {
        id: 99,
        code: 'REST-COMP-1S',
        name: 'Restauración compuesta 1 superficie',
        specialty_name: 'Operatoria',
        default_duration_minutes: 45,
        default_cost: 150
      }
    ]
  })

  afterEach(() => {
    document.body.innerHTML = ''
  })

  it('asks the server for the term instead of filtering the loaded page', async () => {
    const wrapper = await mountPicker()

    expect(api.calls[0]).toContain('/api/procedure-catalog?')
    expect(api.calls[0]).not.toContain('q=')
    expect(wrapper.text()).toContain('Limpieza dental')

    await typeSearch(wrapper, 'REST-COMP-1S')

    expect(lastCall()).toContain('q=REST-COMP-1S')
    // The row is outside the first page the picker had loaded: it can only be
    // rendered because the server answered the search.
    expect(wrapper.text()).toContain('Restauración compuesta 1 superficie')
    expect(wrapper.text()).not.toContain('Limpieza dental')

    wrapper.unmount()
  })

  it('keeps the term while paging the server results', async () => {
    const wrapper = await mountPicker()
    await typeSearch(wrapper, 'REST-COMP-1S')

    wrapper.findComponent(UiPagination).vm.$emit('pageChange', 2)
    await flushPromises()

    expect(lastCall()).toContain('q=REST-COMP-1S')
    expect(lastCall()).toContain('page=2')

    wrapper.unmount()
  })

  it('drops the term when the search box is cleared', async () => {
    const wrapper = await mountPicker()
    await typeSearch(wrapper, 'REST-COMP-1S')

    await typeSearch(wrapper, '')

    expect(lastCall()).not.toContain('q=')
    expect(wrapper.text()).toContain('Limpieza dental')

    wrapper.unmount()
  })
})
