import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'

const updateQuotation = vi.fn()
const createQuotation = vi.fn()

// #414 S1: the form reads the pricing context on mount, so the mocked client
// answers that URL with whatever the case under test needs.
const PRICING_URL = '/api/quotations/pricing'
let apiGetResponse = { data: [] }
const apiGet = vi.fn(async url => (url === PRICING_URL ? apiGetResponse : { data: [] }))

vi.mock('../../../resources/js/composables/useQuotations', () => ({
  useQuotations: () => ({
    createQuotation,
    updateQuotation,
    approveQuotation: vi.fn(),
    rejectQuotation: vi.fn(),
    downloadPDF: vi.fn()
  })
}))

vi.mock('../../../resources/js/composables/useApi', () => ({
  useApi: () => ({
    get: (...args) => apiGet(...args),
    post: async () => ({ data: {} }),
    put: async () => ({ data: {} }),
    patch: async () => ({ data: {} }),
    delete: async () => ({ data: {} }),
    setToken: () => {},
    normalizeError: () => ''
  })
}))

import uiComponents from '../../../resources/js/plugins/ui-components'
import QuotationModal from '../../../resources/js/modules/quotations/components/QuotationModal.vue'

const quotation = {
  id: 42,
  quotation_number: 'PR-2026-ABC123',
  quotation_date: '2026-10-01',
  valid_until: '2026-11-01',
  status: 'draft',
  subtotal: 100,
  discount_percentage: 0,
  discount_amount: 0,
  tax_percentage: 0,
  tax_amount: 0,
  total_amount: 100,
  terms_conditions: '',
  notes: '',
  patient: { id: 7, first_name: 'Ana', last_name: 'Torres', document_number: '12345678' },
  treatment_plan: { id: 3, title: 'Plan de rehabilitación' },
  items: [
    {
      id: 9,
      item_name: 'Limpieza dental',
      item_description: 'Con ultrasonido',
      quantity: 1,
      unit_price: 100
    }
  ]
}

// A line that names a catalog procedure: the server prices it from the tariff,
// so the client's stored number is not the one the document will carry.
const catalogQuotation = {
  ...quotation,
  // The form never declared a rate: the server applied the billing policy.
  tax_percentage: null,
  payer_id: 4,
  items: [
    {
      id: 10,
      item_name: 'Corona de zirconio',
      item_description: '',
      quantity: 1,
      unit_price: 120,
      procedure_catalog_id: 55
    }
  ]
}

const pricingContext = () => ({
  data: {
    tax_percentage: 18,
    quotation_date: '2026-10-01',
    prices: {
      55: {
        unit_price: 140,
        source: 'payer_branch',
        price_id: 7,
        valid_from: '2026-01-01',
        valid_to: null
      }
    }
  }
})

let wrapper = null

const mountForm = async (props = { quotation, isEdit: true }) => {
  wrapper = mount(QuotationModal, {
    props,
    global: { plugins: [uiComponents] },
    attachTo: document.body
  })
  await flushPromises()
}

const root = () => document.querySelector('[data-quotation-form]')
const dialogText = () => document.querySelector('[role="dialog"]').textContent
const amount = selector =>
  root()
    .querySelector(selector)
    .textContent.replace(/\u00a0/g, ' ')
    .trim()

describe('quotations edit form', () => {
  beforeEach(() => {
    document.queryCommandState = () => false
    document.execCommand = () => false
    localStorage.setItem('auth_token', 'test-token')
    localStorage.setItem('user', JSON.stringify({ id: 1, role: 'administrador' }))

    updateQuotation.mockReset()
    updateQuotation.mockResolvedValue({ ...quotation, subtotal: 100 })
    createQuotation.mockReset()

    apiGetResponse = { data: [] }
    apiGet.mockReset()
    apiGet.mockImplementation(async url => (url === PRICING_URL ? apiGetResponse : { data: [] }))
  })

  afterEach(() => {
    wrapper?.unmount()
    wrapper = null
    document.body.innerHTML = ''
  })

  it('hydrates the item rows from the persisted item_name', async () => {
    await mountForm()

    expect(dialogText()).toContain('Editar presupuesto')
    expect(root().getAttribute('data-quotation-form')).toBe('edit')
    expect(dialogText()).toContain('El paciente y el plan de origen no se modifican')

    const row = root().querySelector('[data-item-row]')
    expect(row).not.toBeNull()
    expect(row.querySelector('input').value).toBe('Limpieza dental')
  })

  it('breaks the IGV out of the price instead of adding it on top', async () => {
    await mountForm()

    const rate = root().querySelector('[data-field="tax_percentage"] input')
    rate.value = '18'
    rate.dispatchEvent(new Event('input'))
    await flushPromises()

    // Gross 100 at 18%: base 84.75 plus IGV 15.25, and the patient still pays 100.
    expect(amount('[data-summary-tax]')).toBe('S/ 15.25')
    expect(amount('[data-summary-total]')).toBe('S/ 100.00')

    document.querySelector('[data-form-action="submit"]').click()
    await flushPromises()

    expect(updateQuotation.mock.calls[0][1].tax_percentage).toBe(18)
  })

  it('keeps a declared rate and omits the field when it is cleared', async () => {
    await mountForm()

    const rate = root().querySelector('[data-field="tax_percentage"] input')
    expect(rate.value).toBe('0')

    rate.value = ''
    rate.dispatchEvent(new Event('input'))
    await flushPromises()

    document.querySelector('[data-form-action="submit"]').click()
    await flushPromises()

    const payload = updateQuotation.mock.calls[0][1]
    expect(payload).not.toHaveProperty('tax_percentage')
    expect(payload.subtotal).toBe(100)
  })

  it('submits an update payload without patient_id or server-owned amounts', async () => {
    await mountForm()

    document.querySelector('[data-form-action="submit"]').click()
    await flushPromises()

    expect(updateQuotation).toHaveBeenCalledTimes(1)

    const [id, payload] = updateQuotation.mock.calls[0]
    expect(id).toBe(42)
    expect(payload).not.toHaveProperty('patient_id')
    expect(payload).not.toHaveProperty('treatment_plan_id')
    expect(payload).not.toHaveProperty('tax_amount')
    expect(payload).not.toHaveProperty('total_amount')
    expect(payload.items[0]).toEqual({
      description: 'Limpieza dental',
      quantity: 1,
      unit_price: 100
    })
    expect(payload.subtotal).toBe(100)

    const emitted = wrapper.emitted('saved')
    expect(emitted).toBeTruthy()
    expect(emitted[0][0]).toMatchObject({ id: 42 })
  })

  it('paints the configured IGV rate when the form declares none', async () => {
    apiGetResponse = pricingContext()

    // #414: an untouched rate is null, not a zero. The server applies the
    // billing policy, so the summary must show S/ 15.25 and never S/ 0.00.
    await mountForm({ quotation: { ...quotation, tax_percentage: null }, isEdit: true })

    expect(amount('[data-summary-tax]')).toBe('S/ 15.25')
    expect(amount('[data-summary-taxable-base]')).toBe('S/ 84.75')
    expect(amount('[data-summary-total]')).toBe('S/ 100.00')
  })

  it('reads the policy rate even when every line is free text', async () => {
    // #414: a free-text estimate has no tariff to resolve, but the writer still
    // applies the configured rate. The preview reads what the server publishes
    // instead of falling back to the client constant.
    apiGetResponse = { data: { tax_percentage: 10, quotation_date: '2026-10-01', prices: {} } }

    await mountForm({ quotation: { ...quotation, tax_percentage: null }, isEdit: true })

    const pricingCall = apiGet.mock.calls.find(([url]) => url === PRICING_URL)

    expect(pricingCall).toBeTruthy()
    expect(pricingCall[1].params).toMatchObject({ procedure_ids: [] })
    expect(amount('[data-summary-tax]')).toBe('S/ 9.09')
    expect(amount('[data-summary-taxable-base]')).toBe('S/ 90.91')
    expect(amount('[data-summary-total]')).toBe('S/ 100.00')
  })

  it('prices a catalog line from the tariff context and names its origin', async () => {
    apiGetResponse = pricingContext()

    await mountForm({ quotation: catalogQuotation, isEdit: true })

    // The catalog line stops offering a price of its own: the tariff resolved
    // 140 (convenio + sede) where the stored line said 120.
    const row = root().querySelector('[data-item-row]')
    const priceInput = row.querySelector('[data-item-price] input')

    expect(priceInput.value).toBe('140')
    expect(priceInput.disabled).toBe(true)
    expect(row.querySelector('[data-item-price-source]').textContent).toContain(
      'Precio del tarifario: Convenio y sede'
    )

    expect(amount('[data-summary-subtotal]')).toBe('S/ 140.00')
    expect(amount('[data-summary-taxable-base]')).toBe('S/ 118.64')
    expect(amount('[data-summary-tax]')).toBe('S/ 21.36')
    expect(amount('[data-summary-total]')).toBe('S/ 140.00')

    const pricingCall = apiGet.mock.calls.find(([url]) => url === PRICING_URL)

    expect(pricingCall).toBeTruthy()
    expect(pricingCall[1].params).toMatchObject({
      procedure_ids: [55],
      payer_id: 4,
      quotation_date: '2026-10-01'
    })
  })

  it('shows the API reason when the edit is rejected with a generic 422', async () => {
    updateQuotation.mockRejectedValue({
      response: {
        status: 422,
        data: {
          message: 'Error de validación',
          errors: {
            status: [
              'Solo se puede modificar un presupuesto en borrador, enviado o visto. Estado actual: approved.'
            ]
          }
        }
      }
    })

    await mountForm()

    document.querySelector('[data-form-action="submit"]').click()
    await flushPromises()

    expect(document.querySelector('[data-submit-error]').textContent).toContain(
      'Solo se puede modificar un presupuesto en borrador, enviado o visto.'
    )
  })
})
