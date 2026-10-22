import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'

const updateQuotation = vi.fn()
const createQuotation = vi.fn()

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
    get: async () => ({ data: [] }),
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

let wrapper = null

const mountForm = async () => {
  wrapper = mount(QuotationModal, {
    props: { quotation, isEdit: true },
    global: { plugins: [uiComponents] },
    attachTo: document.body
  })
  await flushPromises()
}

const root = () => document.querySelector('[data-quotation-form]')
const dialogText = () => document.querySelector('[role="dialog"]').textContent

describe('quotations edit form', () => {
  beforeEach(() => {
    document.queryCommandState = () => false
    document.execCommand = () => false
    localStorage.setItem('auth_token', 'test-token')
    localStorage.setItem('user', JSON.stringify({ id: 1, role: 'administrador' }))

    updateQuotation.mockReset()
    updateQuotation.mockResolvedValue({ ...quotation, subtotal: 100 })
    createQuotation.mockReset()
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
    const amount = selector => root().querySelector(selector).textContent.replace(/\u00a0/g, ' ').trim()

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
})
