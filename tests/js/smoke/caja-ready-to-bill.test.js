import { readFileSync } from 'node:fs'
import { dirname, resolve } from 'node:path'
import { fileURLToPath } from 'node:url'
import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { createRouter, createMemoryHistory } from 'vue-router'

// Ready-to-bill (cash-register-interface-redesign T10). Pins the observable
// contract at the page boundary: the surface lives inside AppLayout, lists
// through UiDataTable with server pagination, a failed request replaces the
// rows with an alert that retries, the preview modal owns an error branch, and
// the quotation action cannot double-submit while the request is in flight.
const harness = vi.hoisted(() => ({ apiGet: vi.fn(), apiPost: vi.fn() }))

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
import Page from '../../../resources/js/modules/cash-register/ReadyToBillPage.vue'
import PaymentModal from '../../../resources/js/modules/cash-register/components/PaymentModal.vue'
import { formatCurrency } from '../../../resources/js/composables/useFormatters'
import { globalToasts } from '../../../resources/js/composables/useToast'

const PAGE_PATH = resolve(
  dirname(fileURLToPath(import.meta.url)),
  '../../../resources/js/modules/cash-register/ReadyToBillPage.vue'
)

const rows = [
  {
    id: 41,
    patient: {
      id: 5,
      first_name: 'Ana',
      last_name: 'Torres',
      document_number: '45123456'
    },
    appointment_type: 'Consulta',
    completed_at: '2026-10-01T10:00:00-05:00',
    final_amount: 120,
    paid_amount: 20,
    balance: 100,
    has_quotation: false
  },
  {
    id: 42,
    patient: {
      id: 6,
      first_name: 'Luis',
      last_name: 'Ramos',
      document_number: '40998877'
    },
    appointment_type: 'Limpieza',
    completed_at: '2026-10-02T11:30:00-05:00',
    final_amount: 200,
    paid_amount: 200,
    balance: 0,
    has_quotation: true
  }
]

const meta = { current_page: 1, last_page: 2, per_page: 20, total: 21 }

// #451 — the page renders through the canonical `formatDateTime`, so the
// expectation pins the `es-PE`/24 h shape (`dd/mm/yyyy, HH:mm`) itself instead
// of deriving it from the module under test.
const formattedDate = value =>
  new Intl.DateTimeFormat('es-PE', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
    hourCycle: 'h23'
  }).format(new Date(value))

const preview = {
  appointment: { id: 41, patient: rows[0].patient, completed_at: rows[0].completed_at },
  items: [{ id: 1, procedure_name: 'Limpieza', quantity: 1, total_cost: 120 }],
  subtotal: 120,
  final_amount: 120,
  paid_amount: 20,
  balance: 100,
  payments: [],
  quotations: []
}

let wrapper = null

const mountPage = async (options = {}) => {
  const router = createRouter({
    history: createMemoryHistory(),
    routes: [{ path: '/:pathMatch(.*)*', component: { template: '<div />' } }]
  })
  await router.push('/cash-register/ready-to-bill')
  await router.isReady()

  wrapper = mount(Page, {
    ...options,
    global: { plugins: [router, uiComponents], ...(options.global || {}) }
  })
  await flushPromises()
  return wrapper
}

const listCalls = () =>
  harness.apiGet.mock.calls.filter(call => call[0] === '/api/appointments/ready-to-bill')

const lastListParams = () => listCalls().at(-1)[1].params

const cashMethod = {
  id: 1,
  name: 'Efectivo',
  type: 'cash',
  requires_reference: false,
  gateway_type: null,
  commission_percentage: 0
}

const choosePaymentMethod = async label => {
  await wrapper.find('[data-payment-method] button[aria-haspopup="true"]').trigger('click')
  await flushPromises()

  const option = wrapper
    .findAll('[role="listbox"] button')
    .find(button => button.text().includes(label))
  expect(option, `option "${label}" must be listed`).toBeTruthy()

  await option.trigger('click')
  await flushPromises()
}

beforeEach(() => {
  harness.apiGet.mockReset()
  harness.apiPost.mockReset()
  harness.apiGet.mockResolvedValue({ data: rows, meta })
  harness.apiPost.mockResolvedValue({
    data: { id: 77, quotation_number: 'COT-2026-0077' },
    meta: {}
  })
  globalToasts.value = []
})

afterEach(() => {
  wrapper?.unmount()
  wrapper = null
})

describe('ready-to-bill: primitives', () => {
  it('builds the surface inside AppLayout on the design-system primitives', () => {
    const source = readFileSync(PAGE_PATH, 'utf8')

    expect(source).toContain('<AppLayout>')
    expect(source).toContain('UiDataTable')
    expect(source).toContain('UiFilterBar')
    expect(source).toContain('UiPagination')
    expect(source).toContain('formatCurrency')
    expect(source, 'no hand-written grid may remain').not.toMatch(/<table\b/)
    expect(source, 'no native input may remain').not.toMatch(/<input\b/)
    expect(source, 'no native h1 may remain').not.toMatch(/<h1\b/)
    expect(source).not.toContain('<style')
  })
})

describe('ready-to-bill: shell', () => {
  it('renders the hero band inside the app layout and keeps the topbar h1 alone', async () => {
    await mountPage()

    const hero = wrapper.find('[data-rtb-hero]')
    expect(hero.exists()).toBe(true)
    expect(hero.find('[data-hero-title]').text()).toBe('Listos para facturar')
    expect(hero.find('[data-hero-eyebrow]').text()).toBe('Cobros')
    expect(hero.find('[data-hero-action="refresh"]').exists()).toBe(true)
    expect(wrapper.find('main [data-rtb-hero]').exists()).toBe(true)

    const headings = wrapper.findAll('h1')
    expect(headings).toHaveLength(1)
    expect(headings[0].element.closest('[data-app-chrome="topbar"]')).not.toBeNull()
  })
})

describe('ready-to-bill: list', () => {
  it('renders the endpoint rows with formatted money and quotation state', async () => {
    await mountPage()

    const table = wrapper.find('[data-rtb-table]')
    expect(table.exists()).toBe(true)
    expect(wrapper.findAll('[data-rtb-table] tbody tr')).toHaveLength(2)

    const text = table.text()
    expect(text).toContain('Ana Torres')
    expect(text).toContain('Luis Ramos')
    expect(text).toContain('Consulta')
    expect(text).toContain(formattedDate(rows[0].completed_at))
    expect(text).toContain(formatCurrency(120))
    expect(text).toContain(formatCurrency(20))
    expect(text).toContain(formatCurrency(100))
    expect(text).toContain('Pendiente')
    expect(text).toContain('Generada')

    expect(wrapper.find('[data-preview-action="41"]').exists()).toBe(true)
    expect(wrapper.find('[data-quotation-action="41"]').exists()).toBe(true)
    expect(wrapper.find('[data-quotation-action="42"]').exists()).toBe(false)

    expect(lastListParams()).not.toHaveProperty('from')
    expect(lastListParams()).not.toHaveProperty('to')
  })

  it('pages through the server meta instead of slicing the rows', async () => {
    harness.apiGet.mockResolvedValue({
      data: [rows[0]],
      meta: { current_page: 1, last_page: 3, per_page: 20, total: 45 }
    })
    await mountPage()

    const pager = wrapper.find('[data-rtb-pagination]')
    expect(pager.exists()).toBe(true)
    expect(pager.text()).toContain('45')

    await pager.find('[aria-label="Ir a la página 2"]').trigger('click')
    await flushPromises()

    expect(lastListParams().page).toBe(2)
  })

  it('debounces the date filters and always returns to page 1', async () => {
    harness.apiGet.mockResolvedValue({
      data: [rows[0]],
      meta: { current_page: 1, last_page: 3, per_page: 20, total: 45 }
    })
    await mountPage()

    await wrapper.find('[data-rtb-pagination] [aria-label="Ir a la página 2"]').trigger('click')
    await flushPromises()
    expect(lastListParams().page).toBe(2)

    const callsBeforeFilter = listCalls().length
    await wrapper.find('[data-filter="from"] input').setValue('2026-10-01')
    expect(listCalls()).toHaveLength(callsBeforeFilter)

    await new Promise(resolve => setTimeout(resolve, 350))
    await flushPromises()

    expect(lastListParams()).toMatchObject({ from: '2026-10-01', page: 1 })
  })

  it('filters by patient through the patient lookup and clears it', async () => {
    const FakeSelector = {
      name: 'PatientSelector',
      props: ['modelValue'],
      emits: ['update:modelValue'],
      template:
        "<button data-fake-patient @click=\"$emit('update:modelValue', { id: 9, first_name: 'Ana', last_name: 'Torres' })\">Elegir paciente</button>"
    }

    await mountPage({
      global: { stubs: { teleport: true, PatientSelector: FakeSelector } }
    })

    await wrapper.find('[data-filter-patient]').trigger('click')
    await flushPromises()
    await wrapper.find('[data-fake-patient]').trigger('click')
    await wrapper.find('[data-patient-apply]').trigger('click')
    await flushPromises()

    expect(lastListParams()).toMatchObject({ patient_id: 9, page: 1 })
    expect(wrapper.find('[data-filter-patient]').text()).toContain('Ana Torres')

    await wrapper.find('[data-filter-patient-clear]').trigger('click')
    await flushPromises()

    expect(lastListParams()).not.toHaveProperty('patient_id')
  })

  it('replaces stale rows with an alert that retries the request', async () => {
    await mountPage()
    expect(wrapper.text()).toContain('Ana Torres')

    harness.apiGet.mockRejectedValueOnce({
      response: { status: 500, data: { message: 'Error al listar citas por cobrar' } }
    })
    await wrapper.find('[data-hero-action="refresh"]').trigger('click')
    await flushPromises()

    const alert = wrapper.find('[data-rtb-error]')
    expect(alert.exists()).toBe(true)
    expect(alert.attributes('role')).toBe('alert')
    expect(alert.text()).toContain('Error al listar citas por cobrar')
    expect(wrapper.find('[data-rtb-table]').exists()).toBe(false)
    expect(wrapper.text()).not.toContain('Ana Torres')

    harness.apiGet.mockResolvedValueOnce({ data: rows, meta })
    await wrapper.find('[data-rtb-retry]').trigger('click')
    await flushPromises()

    expect(wrapper.find('[data-rtb-error]').exists()).toBe(false)
    expect(wrapper.find('[data-rtb-table]').text()).toContain('Ana Torres')
  })
})

describe('ready-to-bill: preview', () => {
  it('renders the payment-preview error inside the modal with a retry', async () => {
    harness.apiGet.mockImplementation(async url => {
      if (url === '/api/appointments/41/payment-preview') {
        throw { response: { status: 500, data: { message: 'Error al obtener el desglose' } } }
      }
      return { data: rows, meta }
    })
    await mountPage({ global: { stubs: { teleport: true } } })

    await wrapper.find('[data-preview-action="41"]').trigger('click')
    await flushPromises()

    const error = wrapper.find('[data-preview-error]')
    expect(error.exists()).toBe(true)
    expect(error.attributes('role')).toBe('alert')
    expect(error.text()).toContain('Error al obtener el desglose')

    harness.apiGet.mockImplementation(async url => {
      if (url === '/api/appointments/41/payment-preview') {
        return { data: preview }
      }
      return { data: rows, meta }
    })
    await wrapper.find('[data-preview-retry]').trigger('click')
    await flushPromises()

    expect(wrapper.find('[data-preview-error]').exists()).toBe(false)
    expect(wrapper.text()).toContain('Subtotal')
    expect(wrapper.text()).toContain(formatCurrency(100))
  })
})

describe('ready-to-bill: collection', () => {
  it('collects the balance the row publishes through the payment modal', async () => {
    harness.apiGet.mockImplementation(async url => {
      if (url === '/api/payment-methods/active') return { data: [cashMethod] }
      return { data: rows, meta }
    })

    await mountPage({ global: { stubs: { teleport: true } } })

    const collect = wrapper.find('[data-collect-action="41"]')
    expect(collect.exists()).toBe(true)
    expect(wrapper.find('[data-preview-action="41"]').exists()).toBe(true)

    await collect.trigger('click')
    await flushPromises()

    expect(wrapper.findComponent(PaymentModal).exists()).toBe(true)

    const amount = wrapper.find('[data-payment-amount] input')
    expect(amount.exists()).toBe(true)
    expect(Number(amount.element.value.replace(/,/g, ''))).toBe(rows[0].balance)
    expect(wrapper.find('[data-payment-concept] input').element.value).toBe('Consulta')

    await choosePaymentMethod('Efectivo')

    const listCallsBefore = listCalls().length

    await wrapper.find('[data-payment-submit]').trigger('click')
    await flushPromises()
    await flushPromises()

    expect(harness.apiPost).toHaveBeenCalledWith('/api/pending-payments/41/pay', {
      payment_method_id: cashMethod.id,
      amount: rows[0].balance,
      description: 'Consulta'
    })

    // The collected row leaves the billable list: the page refreshes it.
    expect(listCalls().length).toBeGreaterThan(listCallsBefore)
    expect(globalToasts.value.some(toast => toast.type === 'success')).toBe(true)
  })
})

describe('ready-to-bill: quotation', () => {
  it('disables the action while pending, rejects a double submit and reports the API result', async () => {
    let resolveQuotation = null
    harness.apiPost.mockImplementationOnce(
      () =>
        new Promise(resolve => {
          resolveQuotation = resolve
        })
    )
    await mountPage()

    const action = () => wrapper.find('[data-quotation-action="41"]')
    expect(action().exists()).toBe(true)

    await action().trigger('click')
    await flushPromises()

    expect(action().attributes('disabled')).toBeDefined()
    await action().trigger('click')
    await flushPromises()
    expect(harness.apiPost).toHaveBeenCalledTimes(1)

    resolveQuotation({ data: { id: 77, quotation_number: 'COT-2026-0077' }, meta: {} })
    await flushPromises()

    expect(
      globalToasts.value.some(
        toast => toast.type === 'success' && toast.message.includes('COT-2026-0077')
      )
    ).toBe(true)
    expect(action().attributes('disabled')).toBeUndefined()

    harness.apiPost.mockRejectedValueOnce({
      response: {
        status: 422,
        data: {
          error: { code: 'NO_FINAL_AMOUNT', message: 'La cita no tiene un monto final definido.' }
        }
      }
    })
    await action().trigger('click')
    await flushPromises()

    expect(
      globalToasts.value.some(
        toast =>
          toast.type === 'error' &&
          toast.message.includes('La cita no tiene un monto final definido.')
      )
    ).toBe(true)
  })
})
