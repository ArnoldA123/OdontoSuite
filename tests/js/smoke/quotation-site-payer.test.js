import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'

const harness = vi.hoisted(() => ({
  apiGet: vi.fn(),
  createQuotation: vi.fn()
}))

const PAYERS = [
  { id: 3, code: 'ESSALUD', name: 'EsSalud' },
  { id: 4, code: 'SIS', name: 'SIS' }
]

const PIECES = [
  {
    id: 9,
    fdi_number: '16',
    name: 'Primer Molar Superior Derecho',
    type: 'molar',
    quadrant: 'superior_derecho',
    surfaces: [{ id: 2, surface_code: 'O', surface_name: 'Oclusal', abbreviation: 'O' }]
  }
]

const PROCEDURES = [
  {
    id: 5,
    code: 'OBT-COMP',
    name: 'Obturación composite',
    site_type: 'tooth',
    default_cost: 180,
    default_duration_minutes: 45,
    specialty_name: 'Odontología general',
    is_active: true
  }
]

const PATIENTS = [
  {
    id: 7,
    first_name: 'Ana',
    last_name: 'Torres',
    document_number: '12345678',
    phone: '999888777',
    email: 'ana@test.com',
    age: 30
  }
]

vi.mock('../../../resources/js/composables/useApi', () => ({
  useApi: () => ({
    get: harness.apiGet,
    post: async () => ({ data: {} }),
    put: async () => ({ data: {} }),
    patch: async () => ({ data: {} }),
    delete: async () => ({ data: {} }),
    setToken: () => {},
    normalizeError: () => ''
  })
}))

vi.mock('../../../resources/js/composables/useQuotations', () => ({
  useQuotations: () => ({
    createQuotation: harness.createQuotation,
    updateQuotation: vi.fn(),
    approveQuotation: vi.fn(),
    rejectQuotation: vi.fn(),
    downloadPDF: vi.fn()
  })
}))

import uiComponents from '../../../resources/js/plugins/ui-components'
import QuotationModal from '../../../resources/js/modules/quotations/components/QuotationModal.vue'

const chooseOption = async (scopeSelector, label) => {
  const scope = document.querySelector(scopeSelector)
  expect(scope, `"${scopeSelector}" must be rendered`).toBeTruthy()

  const trigger = scope.querySelector('button[aria-haspopup="true"]')
  expect(trigger, `"${scopeSelector}" must render a select trigger`).toBeTruthy()
  trigger.click()
  await flushPromises()

  const option = [...scope.querySelectorAll('[role="listbox"] button')].find(
    button => button.textContent.trim() === label
  )
  expect(option, `option "${label}" must exist in "${scopeSelector}"`).toBeTruthy()
  option.click()
  await flushPromises()
}

const chooseProcedure = async procedureName => {
  document.querySelector('[data-item-catalog-toggle="0"]').click()
  await flushPromises()

  const panel = document.querySelector('[data-item-catalog-panel="0"]')
  expect(panel, 'the catalog panel must open').toBeTruthy()

  const option = [...panel.querySelectorAll('button')].find(button =>
    button.textContent.includes(procedureName)
  )
  expect(option, `procedure "${procedureName}" must be offered`).toBeTruthy()
  option.click()
  await flushPromises()
}

const selectPatient = async () => {
  const row = [...document.querySelectorAll('.patient-selector div')].find(
    element => element.className.includes('cursor-pointer') && element.textContent.includes('Ana')
  )
  expect(row, 'the patient list must render the mocked patient').toBeTruthy()
  row.click()
  await flushPromises()
}

const submit = async () => {
  document.querySelector('[data-form-action="submit"]').click()
  await flushPromises()
  await flushPromises()
}

const mountModal = async () => {
  const wrapper = mount(QuotationModal, {
    props: { quotation: null, isEdit: false },
    global: { plugins: [uiComponents] },
    attachTo: document.body
  })
  await flushPromises()
  return wrapper
}

let wrapper = null

describe('quotation site and convenio', () => {
  beforeEach(() => {
    document.queryCommandState = () => false
    document.execCommand = () => false
    localStorage.setItem('auth_token', 'test-token')
    localStorage.setItem('user', JSON.stringify({ id: 1, role: 'administrador' }))

    document.body.innerHTML = ''
    harness.createQuotation.mockReset()
    harness.createQuotation.mockResolvedValue({ data: { id: 88 } })
    harness.apiGet.mockReset()
    harness.apiGet.mockImplementation(async url => {
      if (url.includes('/api/payers/active')) return { data: PAYERS }
      if (url.includes('/api/dental-pieces')) return { data: PIECES }
      if (url.includes('/api/procedure-catalog')) {
        return {
          data: PROCEDURES,
          meta: { current_page: 1, last_page: 1, per_page: 12, total: PROCEDURES.length }
        }
      }
      if (url.includes('/api/patients')) return { data: PATIENTS }
      return { data: [] }
    })
  })

  afterEach(() => {
    wrapper?.unmount()
    wrapper = null
    document.body.innerHTML = ''
  })

  it('offers the convenios of /api/payers/active and sends the chosen payer_id', async () => {
    wrapper = await mountModal()

    expect(harness.apiGet.mock.calls.some(([url]) => url.includes('/api/payers/active'))).toBe(true)

    const payerField = document.querySelector('[data-field="payer_id"]')
    expect(payerField.textContent).toContain('Sin convenio')

    await chooseOption('[data-field="payer_id"]', 'EsSalud')

    await selectPatient()
    await chooseProcedure('Obturación composite')
    await chooseOption('[data-site-field="dental_piece_id"]', '#16 · Primer Molar Superior Derecho')
    await submit()

    expect(harness.createQuotation).toHaveBeenCalledTimes(1)

    const payload = harness.createQuotation.mock.calls[0][0]
    expect(payload.payer_id).toBe(3)
    expect(payload.items[0]).toMatchObject({
      description: 'Obturación composite',
      procedure_catalog_id: 5,
      dental_piece_id: 9
    })
  })

  it('blocks a site-requiring procedure without its site locally', async () => {
    wrapper = await mountModal()

    await selectPatient()
    await chooseProcedure('Obturación composite')
    await submit()

    expect(harness.createQuotation).not.toHaveBeenCalled()

    const alert = document.querySelector('[data-submit-error]')
    expect(alert).toBeTruthy()
    expect(alert.textContent).toContain(
      'El procedimiento «Obturación composite» se realiza sobre una pieza dental: selecciona la pieza.'
    )
  })
})
