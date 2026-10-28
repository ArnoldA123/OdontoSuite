import { describe, it, expect, beforeEach, afterEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'

import rolePermissions from '../support/role-permissions.json'
import uiComponents from '../../../resources/js/plugins/ui-components'
import QuotationDetail from '../../../resources/js/modules/quotations/components/QuotationDetail.vue'

// #420: the detail gates its footer on `usePermissions().can.*`, and those
// flags are a membership check on the permission list the API publishes, so a
// session is seeded with that list and never with a role lookup.
const seedSession = role => {
  localStorage.setItem('auth_token', 'test-token')
  localStorage.setItem('user', JSON.stringify({ id: 1, role, permissions: rolePermissions[role] }))
}

// #289, S1 (finding D2): `subtotal` is the base imponible, so the panel starts
// from the list price and shows the base plus IGV as the breakdown of the total.
const quotation = {
  id: 42,
  quotation_number: 'PR-2026-ABC123',
  quotation_date: '2026-10-01',
  valid_until: '2026-11-01',
  status: 'sent',
  subtotal: 152.54,
  discount_percentage: 10,
  discount_amount: 20,
  tax_percentage: 18,
  tax_amount: 27.46,
  total_amount: 180,
  terms_conditions: '',
  notes: '',
  patient: { id: 7, first_name: 'Ana', last_name: 'Torres', document_number: '12345678' },
  items: [
    {
      id: 9,
      item_name: 'Limpieza dental',
      item_description: 'Con ultrasonido',
      quantity: 1,
      unit_price: 200,
      total_price: 200
    }
  ]
}

let wrapper = null

const mountDetail = async (overrides = {}) => {
  wrapper = mount(QuotationDetail, {
    props: { quotation: { ...quotation, ...overrides } },
    global: { plugins: [uiComponents] },
    attachTo: document.body
  })
  await flushPromises()
}

const costs = () => document.querySelector('[data-detail-section="costs"]')
const costsText = () => costs().textContent.replace(/\u00a0/g, ' ')
const amount = selector => {
  const node = costs().querySelector(selector)

  return node ? node.textContent.replace(/\u00a0/g, ' ').trim() : ''
}

beforeEach(() => {
  seedSession('administrador')
})

afterEach(() => {
  wrapper?.unmount()
  wrapper = null
  document.body.innerHTML = ''
})

describe('quotations detail breakdown', () => {
  it('starts from the list price and breaks the total into base plus IGV', async () => {
    await mountDetail()

    // 200 list, -20 discount, 152.54 base + 27.46 IGV = 180 total.
    expect(amount('[data-detail-subtotal]')).toBe('S/ 200.00')
    expect(amount('[data-detail-taxable-base]')).toBe('S/ 152.54')
    expect(costsText()).toContain('S/ 27.46')
    expect(costsText()).toContain('S/ 180.00')
  })

  it('hides the base row when the quotation declares no tax', async () => {
    await mountDetail({ subtotal: 180, tax_amount: 0, tax_percentage: 0 })

    expect(amount('[data-detail-subtotal]')).toBe('S/ 200.00')
    expect(amount('[data-detail-taxable-base]')).toBe('')
    expect(costsText()).not.toContain('Base imponible')
    expect(costsText()).toContain('S/ 180.00')
  })

  it('shows the same amount as subtotal and total when there is no discount', async () => {
    await mountDetail({
      subtotal: 100,
      discount_percentage: 0,
      discount_amount: 0,
      tax_amount: 18,
      total_amount: 118
    })

    expect(amount('[data-detail-subtotal]')).toBe('S/ 118.00')
    expect(amount('[data-detail-taxable-base]')).toBe('S/ 100.00')
    expect(costsText()).not.toContain('Descuento')
    expect(costsText()).toContain('S/ 118.00')
  })

  it('offers the footer actions the session can execute', async () => {
    const action = name => document.querySelector(`[data-detail-action="${name}"]`)

    // Reading is a separate grant: recepcionista has view.quotation and no
    // manage.quotation, so the PDF stays and the three write actions go.
    seedSession('recepcionista')
    await mountDetail()

    expect(action('pdf')).not.toBeNull()
    expect(action('send')).toBeNull()
    expect(action('edit')).toBeNull()
    expect(action('approve')).toBeNull()
    expect(action('reject')).toBeNull()

    wrapper.unmount()
    wrapper = null
    document.body.innerHTML = ''

    // manage.quotation only releases the actions the status allows: a `sent`
    // quotation can be approved or rejected, a `draft` can be edited. The
    // permission is no longer the limiting factor for either.
    seedSession('administrador')
    await mountDetail()

    expect(action('pdf')).not.toBeNull()
    expect(action('approve')).not.toBeNull()
    expect(action('reject')).not.toBeNull()
    expect(action('edit')).toBeNull()
    // #422 S4: a `sent` quotation is past the sending door.
    expect(action('send')).toBeNull()

    wrapper.unmount()
    wrapper = null
    document.body.innerHTML = ''

    await mountDetail({ status: 'draft' })

    expect(action('pdf')).not.toBeNull()
    expect(action('edit')).not.toBeNull()
    expect(action('send')).not.toBeNull()
    expect(action('approve')).toBeNull()
    expect(action('reject')).toBeNull()
  })
})
