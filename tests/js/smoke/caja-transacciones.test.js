import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'

// Caja > Transacciones contract (T6). The tab consumes the design-system
// primitives and the public payment-method catalog:
//  - the list renders through UiDataTable/UiFilterBar/UiPagination, so no
//    hand-written <table>/<select> survives in either component;
//  - "Filtrar" hands the applied filters (date range, type, payment method)
//    to the page, which forwards them to GET /api/transactions, and paging
//    reuses the same filters;
//  - Anular emits `void` with the row and never opens a native confirm
//    (the page owns useConfirm);
//  - Comprobante emits `receipt` with the row;
//  - the modal loads /api/payment-methods/active and exposes the 422 body
//    (general + field errors) instead of swallowing it.
const harness = vi.hoisted(() => ({
  apiGet: vi.fn(),
  apiPost: vi.fn()
}))

vi.mock('../../../resources/js/composables/useApi', () => ({
  useApi: () => ({
    get: harness.apiGet,
    post: harness.apiPost,
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
import TransactionList from '../../../resources/js/modules/cash-register/components/TransactionList.vue'
import TransactionModal from '../../../resources/js/modules/cash-register/components/TransactionModal.vue'
import DataTable from '../../../resources/js/components/ui/DataTable.vue'
import Pagination from '../../../resources/js/components/ui/Pagination.vue'
import listSource from '../../../resources/js/modules/cash-register/components/TransactionList.vue?raw'
import modalSource from '../../../resources/js/modules/cash-register/components/TransactionModal.vue?raw'

const PAYMENT_METHODS = [
  { id: 1, name: 'Efectivo', is_active: true },
  { id: 2, name: 'Tarjeta de crédito', is_active: true }
]

// #412: the search endpoint publishes first_name/last_name (plus the additive
// `name`), so the fixture carries exactly the contract the modal must read.
const PATIENTS = [
  {
    id: 3,
    first_name: 'Gonzalo',
    last_name: 'Velasco',
    document_number: '45678912',
    email: 'gonzalo.velasco@test.com'
  }
]

const TRANSACTIONS = [
  {
    id: 11,
    transaction_number: 'TRX-0011',
    created_at: '2026-10-20T14:35:00.000000Z',
    type: 'payment',
    amount: '150.50',
    discount_amount: '0.00',
    status: 'completed',
    description: 'Limpieza dental',
    reference_number: 'REF-99',
    patient: { id: 3, name: 'Ana', last_name: 'Torres', document_number: '45678912' },
    payment_method: { id: 1, name: 'Efectivo' }
  },
  {
    id: 12,
    transaction_number: 'TRX-0012',
    created_at: '2026-10-20T15:10:00.000000Z',
    type: 'refund',
    amount: '40.00',
    discount_amount: '0.00',
    status: 'voided',
    description: 'Devolución',
    reference_number: null,
    patient: { id: 3, name: 'Ana', last_name: 'Torres', document_number: '45678912' },
    payment_method: { id: 2, name: 'Tarjeta de crédito' }
  }
]

const PAGINATION = { current_page: 1, last_page: 3, per_page: 15, total: 31 }

const mockCatalog = () => {
  harness.apiGet.mockImplementation(async url => {
    const target = String(url)
    if (target === '/api/payment-methods/active') return { data: PAYMENT_METHODS }
    if (target === '/api/patients/search') return { data: PATIENTS }
    if (target === '/api/appointments') return { data: [] }
    return { data: [] }
  })
}

const mountList = async (props = {}) => {
  mockCatalog()
  const wrapper = mount(TransactionList, {
    props: {
      transactions: TRANSACTIONS,
      loading: false,
      pagination: PAGINATION,
      canVoid: true,
      ...props
    },
    global: { plugins: [uiComponents] }
  })
  await flushPromises()
  return wrapper
}

const chooseOption = async (root, optionText) => {
  expect(root).toBeTruthy()
  root.querySelector('button').click()
  await flushPromises()
  const option = [...root.querySelectorAll('button')].find(
    candidate => candidate.textContent.trim() === optionText
  )
  expect(option, `option "${optionText}" must be rendered`).toBeTruthy()
  option.click()
  await flushPromises()
}

const mountOpenModal = async () => {
  mockCatalog()
  const wrapper = mount(TransactionModal, {
    props: { modelValue: true },
    attachTo: document.body,
    global: { plugins: [uiComponents] }
  })
  await flushPromises()
  return wrapper
}

const fillModal = async () => {
  const patientInput = document.body.querySelector('[data-modal-patient-search] input')
  patientInput.value = 'Ana'
  patientInput.dispatchEvent(new Event('input'))
  await flushPromises()

  const patientResult = document.body.querySelector('[data-patient-result]')
  expect(patientResult).toBeTruthy()
  patientResult.click()
  await flushPromises()

  await chooseOption(
    document.body.querySelector('[data-modal-field="payment_method_id"]'),
    'Efectivo'
  )

  const amountInput = document.body.querySelector('[data-modal-amount] input')
  amountInput.value = '50'
  amountInput.dispatchEvent(new Event('input'))
  await flushPromises()
}

let wrapper = null

beforeEach(() => {
  harness.apiGet.mockReset()
  harness.apiPost.mockReset()
  harness.apiPost.mockResolvedValue({ data: { id: 99 } })
})

afterEach(() => {
  if (wrapper) {
    wrapper.unmount()
    wrapper = null
  }
  document.body.innerHTML = ''
  vi.unstubAllGlobals()
  vi.restoreAllMocks()
})

describe('Caja > Transacciones list', () => {
  it('renders through the design-system table and keeps no hand-written table or select', async () => {
    wrapper = await mountList()

    expect(wrapper.findComponent(DataTable).exists()).toBe(true)
    expect(wrapper.find('[data-transaction-table]').exists()).toBe(true)
    expect(wrapper.text().replace(/\u00a0/g, ' ')).toContain('S/ 150.50')
    expect(wrapper.text()).toContain('Completada')

    expect(listSource).not.toContain('<table')
    expect(listSource).not.toContain('<select')
    expect(listSource).not.toContain('exportToExcel')
    expect(modalSource).not.toContain('<select')
  })

  it('emits refresh with only the applied filters when the filter row is submitted', async () => {
    wrapper = await mountList()

    const dates = wrapper.findAll('input[type="date"]')
    expect(dates).toHaveLength(2)
    await dates[0].setValue('2026-10-01')
    await dates[1].setValue('2026-10-20')

    await chooseOption(wrapper.find('[data-filter="type"]').element, 'Ingreso')
    await chooseOption(wrapper.find('[data-filter="payment_method_id"]').element, 'Efectivo')

    await wrapper.find('[data-filter-apply]').trigger('click')

    expect(wrapper.emitted('refresh')).toHaveLength(1)
    expect(wrapper.emitted('refresh')[0][0]).toEqual({
      date_from: '2026-10-01',
      date_to: '2026-10-20',
      type: 'payment',
      payment_method_id: 1
    })
  })

  it('keeps the applied filters when the server-side page changes', async () => {
    wrapper = await mountList()

    await chooseOption(wrapper.find('[data-filter="type"]').element, 'Devolución')
    await wrapper.find('[data-filter-apply]').trigger('click')

    const pagination = wrapper.findComponent(Pagination)
    expect(pagination.props()).toMatchObject({
      currentPage: 1,
      totalPages: 3,
      total: 31,
      perPage: 15
    })
    pagination.vm.$emit('pageChange', 2)
    await flushPromises()

    expect(wrapper.emitted('refresh')).toHaveLength(2)
    expect(wrapper.emitted('refresh')[1][0]).toEqual({ type: 'refund', page: 2 })
  })

  it('emits void with the row and never opens a native confirm', async () => {
    const nativeConfirm = vi.fn()
    vi.stubGlobal('confirm', nativeConfirm)

    wrapper = await mountList()
    await wrapper.find('[data-row-action="void"]').trigger('click')

    expect(nativeConfirm).not.toHaveBeenCalled()
    expect(wrapper.emitted('void')).toHaveLength(1)
    expect(wrapper.emitted('void')[0][0].id).toBe(11)
  })

  it('hides Anular unless canVoid is granted and always exposes Comprobante', async () => {
    wrapper = await mountList({ canVoid: false })

    expect(wrapper.find('[data-row-action="void"]').exists()).toBe(false)

    await wrapper.find('[data-row-action="receipt"]').trigger('click')
    expect(wrapper.emitted('receipt')).toHaveLength(1)
    expect(wrapper.emitted('receipt')[0][0].id).toBe(11)
  })
})

describe('Caja transaction modal', () => {
  it('loads the public active payment-method catalog and closes through v-model', async () => {
    wrapper = await mountOpenModal()

    const urls = harness.apiGet.mock.calls.map(([url]) => url)
    expect(urls).toContain('/api/payment-methods/active')
    expect(urls).not.toContain('/api/payment-methods')

    const methodRoot = document.body.querySelector('[data-modal-field="payment_method_id"]')
    expect(methodRoot).toBeTruthy()

    await chooseOption(methodRoot, 'Efectivo')
    expect(methodRoot.textContent).toContain('Efectivo')

    document.body.querySelector('[data-modal-cancel]').click()
    await flushPromises()

    expect(wrapper.emitted('update:modelValue')).toEqual([[false]])
  })

  it('renders the patient row and card from first_name/last_name without an undefined', async () => {
    wrapper = await mountOpenModal()

    const patientInput = document.body.querySelector('[data-modal-patient-search] input')
    patientInput.value = 'Gonzalo Velasco'
    patientInput.dispatchEvent(new Event('input'))
    await flushPromises()

    expect(harness.apiGet).toHaveBeenCalledWith('/api/patients/search', {
      params: { q: 'Gonzalo Velasco' }
    })

    const patientResult = document.body.querySelector('[data-patient-result]')
    expect(patientResult).toBeTruthy()
    expect(patientResult.textContent).toContain('Gonzalo Velasco')
    expect(patientResult.textContent).not.toContain('undefined')

    patientResult.click()
    await flushPromises()

    const patientField = document.body.querySelector('[data-modal-field="patient_id"]')
    expect(patientField.textContent).toContain('Gonzalo Velasco')
    expect(patientField.textContent).not.toContain('undefined')

    const selectedInput = document.body.querySelector('[data-modal-patient-search] input')
    expect(selectedInput.value).toBe('Gonzalo Velasco')
  })

  it('renders errors.general and field errors when the API rejects the transaction', async () => {
    harness.apiPost.mockRejectedValue({
      response: {
        data: {
          message: 'Datos inválidos',
          errors: { amount: ['El monto debe ser mayor a 0.'] }
        }
      }
    })

    wrapper = await mountOpenModal()
    await fillModal()

    document.body.querySelector('[data-modal-submit]').click()
    await flushPromises()

    const general = document.body.querySelector('[data-modal-general-error]')
    expect(general).toBeTruthy()
    expect(general.textContent).toContain('Datos inválidos')

    const fieldError = document.body.querySelector('[data-field-error="amount"]')
    expect(fieldError).toBeTruthy()
    expect(fieldError.textContent).toContain('El monto debe ser mayor a 0.')

    expect(wrapper.emitted('success')).toBeUndefined()
  })
})
