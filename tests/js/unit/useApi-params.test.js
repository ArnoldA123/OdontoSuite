import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'

// #408 — the HTTP client serialized `undefined` filters as the literal string
// "undefined", so the server applied them and the Cobros tab was always empty.
// The client now omits `undefined`/`null` before building the query; the tab
// lists what the endpoint publishes.
import { buildQueryString } from '../../../resources/js/utils/query.js'
import { useApi } from '../../../resources/js/composables/useApi.js'
import uiComponents from '../../../resources/js/plugins/ui-components'
import PendingPaymentsList from '../../../resources/js/modules/cash-register/components/PendingPaymentsList.vue'

const PAYMENTS_PATH = '/api/cash-register/pending-payments'

const jsonResponse = body => ({
  ok: true,
  status: 200,
  headers: { get: () => 'application/json' },
  json: async () => body
})

const pendingPayment = {
  id: 7,
  patient: { id: 5, name: 'Ana Torres', document_number: '45123456' },
  appointment: {
    id: 7,
    date: '2026-10-01T10:00:00',
    appointment_type: { id: 2, name: 'Consulta' },
    professional: { id: 3, name: 'Dr. Rojas' }
  },
  concept: 'Consulta',
  amount: 120,
  status: 'pending'
}

describe('query string without empty filters (#408)', () => {
  it('omits undefined and null while keeping explicit empty strings', () => {
    const query = buildQueryString({
      search: undefined,
      date_from: null,
      date_to: '',
      page: 1
    })

    expect(query).toBe('date_to=&page=1')
    expect(query).not.toContain('undefined')
    expect(query).not.toContain('null')
  })

  it('keeps falsy but meaningful values', () => {
    expect(buildQueryString({ page: 0, active: false, q: '' })).toBe('page=0&active=false&q=')
  })

  // #501 — an array of one element arrived as a scalar and Laravel's `array`
  // rule answered 422, so `/api/quotations/pricing` never resolved the tariff of
  // a single catalog line and the form saved a total the server changed.
  it('serializes arrays with brackets so a one-element list stays a list', () => {
    expect(buildQueryString({ procedure_ids: [7] })).toBe('procedure_ids%5B%5D=7')
    expect(buildQueryString({ procedure_ids: [7, 9] })).toBe(
      'procedure_ids%5B%5D=7&procedure_ids%5B%5D=9'
    )
    expect(buildQueryString({ procedure_ids: [] })).toBe('')
  })
})

describe('useApi request URL (#408)', () => {
  let fetchMock

  beforeEach(() => {
    fetchMock = vi.fn(async () => jsonResponse({ data: [], meta: { total: 0 } }))
    vi.stubGlobal('fetch', fetchMock)
  })

  afterEach(() => {
    vi.unstubAllGlobals()
  })

  it('never serializes the literal "undefined"', async () => {
    await useApi().get(PAYMENTS_PATH, {
      params: {
        search: undefined,
        date_from: undefined,
        date_to: undefined,
        page: 1,
        per_page: 10
      }
    })

    const [url] = fetchMock.mock.calls[0]
    expect(url).toBe('http://localhost:8000/api/cash-register/pending-payments?page=1&per_page=10')
    expect(url).not.toContain('undefined')
  })

  it('lists the rows the endpoint publishes', async () => {
    fetchMock.mockResolvedValue(
      jsonResponse({
        data: [pendingPayment],
        meta: { current_page: 1, last_page: 1, per_page: 10, total: 1 }
      })
    )

    const wrapper = mount(PendingPaymentsList, { global: { plugins: [uiComponents] } })
    await flushPromises()

    const [url] = fetchMock.mock.calls[0]
    expect(url).not.toContain('search=undefined')
    expect(wrapper.text()).toContain('Ana Torres')
    expect(wrapper.text()).not.toContain('No hay cobros pendientes')

    wrapper.unmount()
  })
})
