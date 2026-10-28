import { readFileSync } from 'node:fs'
import { resolve } from 'node:path'
import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { createRouter, createMemoryHistory } from 'vue-router'

// Cobros tab (cash-register-interface-redesign T5). Pins the contract at the
// component boundary: both surfaces render through UI primitives (no
// hand-written table, no native select), the list reads the server meta for
// its pagination, a rejected request becomes a visible alert with a retry
// instead of an empty list, and the modal posts the pending appointment to
// its own pay endpoint while surfacing the API error message.
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
import UiDataTable from '../../../resources/js/components/ui/DataTable.vue'
import PendingPaymentsList from '../../../resources/js/modules/cash-register/components/PendingPaymentsList.vue'
import PaymentModal from '../../../resources/js/modules/cash-register/components/PaymentModal.vue'
import { formatCurrency } from '../../../resources/js/composables/useFormatters'

const componentSource = name =>
  readFileSync(resolve('resources/js/modules/cash-register/components', name), 'utf8')

const pendingPayment = {
  id: 7,
  patient: {
    id: 5,
    name: 'Ana Torres',
    document_number: '45123456',
    email: 'ana@test.com',
    phone: '999888777'
  },
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

const paymentMethod = {
  id: 1,
  name: 'Efectivo',
  description: 'Pago en efectivo',
  gateway_type: null,
  commission_percentage: 0
}

const cardPaymentMethod = {
  id: 4,
  name: 'Tarjeta de crédito',
  description: 'Visa, Mastercard',
  gateway_type: null,
  type: 'card',
  requires_reference: true,
  commission_percentage: 3.5
}

const walletPaymentMethod = {
  id: 11,
  name: 'Yape',
  description: 'Billetera digital',
  gateway_type: null,
  type: 'wallet',
  requires_reference: true,
  commission_percentage: 0
}

const mpPaymentMethod = {
  id: 9,
  name: 'Mercado Pago',
  description: 'Cobro con Mercado Pago',
  gateway_type: 'mercadopago',
  type: 'wallet',
  requires_reference: false,
  commission_percentage: 0
}

const router = createRouter({
  history: createMemoryHistory(),
  routes: [{ path: '/:pathMatch(.*)*', component: { template: '<div />' } }]
})

let wrapper = null

const mountList = async () => {
  wrapper = mount(PendingPaymentsList, { global: { plugins: [uiComponents] } })
  await flushPromises()
  return wrapper
}

const mountPaymentModal = async (props = {}) => {
  wrapper = mount(PaymentModal, {
    props: {
      modelValue: true,
      selectedPatient: {
        id: 5,
        name: 'Ana Torres',
        document_number: '45123456',
        email: 'ana@test.com',
        phone: '999888777',
        concept: 'Consulta',
        amount: 120
      },
      selectedAppointment: {
        id: 7,
        date: '2026-10-01T10:00:00',
        appointment_type: { name: 'Consulta' }
      },
      pendingPatients: [pendingPayment.patient],
      ...props
    },
    global: { plugins: [uiComponents, router] },
    attachTo: document.body
  })
  await flushPromises()
  return wrapper
}

const chooseOption = async (field, label) => {
  document.querySelector(`[data-payment-${field}] button`).click()
  await flushPromises()
  const option = Array.from(document.querySelectorAll('[role="listbox"] button')).find(button =>
    button.textContent.includes(label)
  )
  expect(option, `option "${label}" must be listed for ${field}`).toBeTruthy()
  option.click()
  await flushPromises()
}

const submitPayment = async () => {
  document.querySelector('[data-payment-submit]').click()
  await flushPromises()
  await flushPromises()
}

const pickAdHocPatient = async label => {
  const rows = [...document.querySelectorAll('[data-payment-patient-selector] .cursor-pointer')]
  const row = rows.find(item => item.textContent.includes(label))
  expect(row, `patient "${label}" must be listed by PatientSelector`).toBeTruthy()
  row.click()
  await flushPromises()
}

const anaPatient = {
  id: 5,
  first_name: 'Ana',
  last_name: 'Torres',
  document_number: '45123456',
  phone: '999888777'
}

const patientsWithoutPendingPayments = [
  {
    id: 42,
    first_name: 'Luis',
    last_name: 'Paredes',
    document_number: '70000001',
    phone: '988777666'
  }
]

beforeEach(() => {
  harness.apiGet.mockReset()
  harness.apiPost.mockReset()
  harness.apiGet.mockImplementation(url => {
    if (url === '/api/payment-methods/active') {
      return Promise.resolve({ data: [paymentMethod] })
    }
    if (url === '/api/patients') {
      return Promise.resolve({ data: [anaPatient] })
    }
    return Promise.resolve({ data: [], meta: { total: 0 } })
  })
  harness.apiPost.mockResolvedValue({
    data: { id: 91, transaction_number: 'TX-91' },
    meta: {}
  })
})

afterEach(() => {
  wrapper?.unmount()
  wrapper = null
  document.body.innerHTML = ''
  localStorage.clear()
})

describe('Cobros: primitives', () => {
  it('drops the hand-written table and the native selects', () => {
    for (const name of ['PendingPaymentsList.vue', 'PaymentModal.vue']) {
      const source = componentSource(name)
      expect(source, `${name} must not hand-write a <table>`).not.toMatch(/<table\b/)
      expect(source, `${name} must not render a native <select>`).not.toMatch(/<select\b/)
    }
  })
})

describe('PendingPaymentsList', () => {
  it('renders the pending rows through UiDataTable and emits the payment to collect', async () => {
    harness.apiGet.mockResolvedValue({ data: [pendingPayment], meta: { total: 1 } })
    await mountList()

    expect(wrapper.findComponent(UiDataTable).exists()).toBe(true)
    const table = wrapper.find('[data-pending-payments-table]')
    expect(table.exists()).toBe(true)
    expect(table.text()).toContain('Ana Torres')
    expect(table.text()).toContain('45123456')
    expect(table.text()).toContain('Consulta')
    expect(table.text()).toContain('Dr. Rojas')
    expect(table.text()).toContain(formatCurrency(pendingPayment.amount))
    expect(wrapper.html()).not.toContain('<select')

    const payButton = wrapper.find('[data-pay-pending="7"]')
    expect(payButton.exists()).toBe(true)
    await payButton.trigger('click')

    expect(wrapper.emitted('payment')).toHaveLength(1)
    expect(wrapper.emitted('payment')[0][0]).toMatchObject({
      id: 7,
      amount: 120,
      concept: 'Consulta',
      patient: { id: 5, name: 'Ana Torres' },
      appointment: { id: 7 }
    })

    const loaded = wrapper.emitted('paymentsLoaded')
    expect(loaded.at(-1)[0]).toEqual([pendingPayment])
  })

  it('sends the server filters instead of filtering the loaded rows', async () => {
    harness.apiGet.mockResolvedValue({ data: [pendingPayment], meta: { total: 1 } })
    await mountList()

    const search = wrapper.find('[data-filter="search"] input')
    await search.setValue('Ana')
    await new Promise(resolve => setTimeout(resolve, 350))
    await flushPromises()

    const call = harness.apiGet.mock.calls.at(-1)
    expect(call[0]).toBe('/api/cash-register/pending-payments')
    expect(call[1].params.search).toBe('Ana')
    expect(wrapper.find('[data-pending-payments-table]').text()).toContain('Ana Torres')
  })

  it('pages with the meta returned by the API', async () => {
    harness.apiGet.mockResolvedValue({
      data: [pendingPayment],
      meta: { current_page: 2, last_page: 3, per_page: 10, total: 25 }
    })
    await mountList()

    const pager = wrapper.find('[data-pending-payments-pagination]')
    expect(pager.exists()).toBe(true)
    expect(pager.text()).toContain('25')

    await pager.find('[aria-label="Ir a la página 3"]').trigger('click')
    await flushPromises()

    const { params } = harness.apiGet.mock.calls.at(-1)[1]
    expect(params.page).toBe(3)
    expect(params.per_page).toBe(10)
  })

  it('turns a 403 into a visible alert with a retry instead of an empty list', async () => {
    harness.apiGet.mockRejectedValue({
      response: { status: 403, data: { message: 'This action is unauthorized.' } }
    })
    await mountList()

    const alert = wrapper.find('[role="alert"]')
    expect(alert.exists()).toBe(true)
    expect(alert.text()).toMatch(/perfil de caja/)
    expect(wrapper.find('[data-pending-payments-table]').exists()).toBe(false)
    expect(wrapper.emitted('paymentsLoaded')[0][0]).toEqual([])

    const retry = wrapper.find('[data-pending-payments-retry]')
    expect(retry.exists()).toBe(true)

    harness.apiGet.mockResolvedValue({ data: [pendingPayment], meta: { total: 1 } })
    await retry.trigger('click')
    await flushPromises()

    expect(wrapper.find('[role="alert"]').exists()).toBe(false)
    expect(wrapper.find('[data-pending-payments-table]').text()).toContain('Ana Torres')
  })

  it('gates an expired session with the same administrative profile message', async () => {
    harness.apiGet.mockRejectedValue({
      response: { status: 401, data: { message: 'Unauthenticated.' } }
    })
    await mountList()

    const alert = wrapper.find('[role="alert"]')
    expect(alert.exists()).toBe(true)
    expect(alert.text()).toMatch(/perfil de caja/)
    expect(alert.text()).not.toContain('Unauthenticated.')
  })
})

describe('PaymentModal', () => {
  it('reads the active payment methods and never the admin-only catalog', async () => {
    await mountPaymentModal()

    const urls = harness.apiGet.mock.calls.map(call => call[0])
    expect(urls).toContain('/api/payment-methods/active')
    expect(urls).not.toContain('/api/payment-methods')
    expect(document.querySelector('[data-payment-form]')).not.toBeNull()
    expect(document.body.innerHTML).not.toContain('<select')
    expect(document.querySelector('[data-payment-error]')).toBeNull()
  })

  it('renders the API message when the payment is rejected', async () => {
    harness.apiPost.mockRejectedValue({
      response: { status: 422, data: { message: 'La cita ya tiene una transacción activa.' } }
    })
    await mountPaymentModal()

    await chooseOption('method', 'Efectivo')
    await submitPayment()

    const error = document.querySelector('[data-payment-error]')
    expect(error).not.toBeNull()
    expect(error.textContent).toContain('La cita ya tiene una transacción activa.')
    expect(wrapper.emitted('success')).toBeUndefined()
  })

  it('asks for the reference when the chosen method demands one', async () => {
    harness.apiGet.mockImplementation(url => {
      if (url === '/api/payment-methods/active') {
        return Promise.resolve({ data: [paymentMethod, cardPaymentMethod] })
      }
      return Promise.resolve({ data: [], meta: { total: 0 } })
    })

    await mountPaymentModal()
    await chooseOption('method', 'Tarjeta de crédito')

    const reference = document.querySelector('[data-payment-reference]')
    expect(reference.querySelector('input').required).toBe(true)
    expect(reference.textContent).toContain('Obligatorio para este método de pago.')
  })

  it('shows the reference error of the API on the reference field', async () => {
    harness.apiGet.mockImplementation(url => {
      if (url === '/api/payment-methods/active') {
        return Promise.resolve({ data: [cardPaymentMethod] })
      }
      return Promise.resolve({ data: [], meta: { total: 0 } })
    })
    harness.apiPost.mockRejectedValue({
      response: {
        status: 422,
        data: {
          message: 'El método de pago Tarjeta de crédito exige un número de referencia.',
          errors: {
            reference_number: [
              'El método de pago Tarjeta de crédito exige un número de referencia.'
            ]
          }
        }
      }
    })

    await mountPaymentModal()
    await chooseOption('method', 'Tarjeta de crédito')
    await submitPayment()

    const reference = document.querySelector('[data-payment-reference]')
    expect(reference.textContent).toContain('exige un número de referencia')
    expect(wrapper.emitted('success')).toBeUndefined()
  })

  it('surfaces the error keys with no visible field in the general notice', async () => {
    // #409 — a 422 whose reason hangs from a key the form cannot paint
    // (`session`) used to be copied to state and never rendered.
    harness.apiPost.mockRejectedValue({
      response: {
        status: 422,
        data: {
          message: 'Los datos proporcionados no son válidos.',
          errors: {
            session: ['No hay una sesión de caja abierta para esta sucursal.']
          }
        }
      }
    })

    await mountPaymentModal()
    await chooseOption('method', 'Efectivo')
    await submitPayment()

    const error = document.querySelector('[data-payment-error]')
    expect(error).not.toBeNull()
    expect(error.textContent).toContain('No hay una sesión de caja abierta para esta sucursal.')
    expect(wrapper.emitted('success')).toBeUndefined()
  })

  it('shows the wallet rejection on the reference field when it travels empty', async () => {
    harness.apiGet.mockImplementation(url => {
      if (url === '/api/payment-methods/active') {
        return Promise.resolve({ data: [walletPaymentMethod] })
      }
      return Promise.resolve({ data: [], meta: { total: 0 } })
    })
    harness.apiPost.mockRejectedValue({
      response: {
        status: 422,
        data: {
          message: 'El método de pago Yape exige un número de referencia.',
          errors: {
            reference_number: ['El método de pago Yape exige un número de referencia.']
          }
        }
      }
    })

    await mountPaymentModal()
    await chooseOption('method', 'Yape')
    await submitPayment()

    expect(document.querySelector('[data-payment-reference]').textContent).toContain(
      'exige un número de referencia'
    )
    expect(wrapper.emitted('success')).toBeUndefined()
  })

  it('sends the wallet reference and the notes with a pending appointment payment', async () => {
    harness.apiGet.mockImplementation(url => {
      if (url === '/api/payment-methods/active') {
        return Promise.resolve({ data: [walletPaymentMethod] })
      }
      return Promise.resolve({ data: [], meta: { total: 0 } })
    })

    await mountPaymentModal()
    await chooseOption('method', 'Yape')

    const reference = document.querySelector('[data-payment-reference] input')
    reference.value = 'OP-778812'
    reference.dispatchEvent(new Event('input'))
    await flushPromises()
    const notes = document.querySelector('[data-payment-notes]')
    notes.value = 'Pago con Yape del titular'
    notes.dispatchEvent(new Event('input'))
    await flushPromises()

    await submitPayment()

    expect(harness.apiPost).toHaveBeenCalledTimes(1)
    expect(harness.apiPost).toHaveBeenCalledWith('/api/pending-payments/7/pay', {
      payment_method_id: 11,
      amount: 120,
      description: 'Consulta',
      reference_number: 'OP-778812',
      notes: 'Pago con Yape del titular'
    })
    expect(wrapper.emitted('success')).toHaveLength(1)
  })

  it('pays the pending appointment through its own endpoint', async () => {
    await mountPaymentModal()

    await chooseOption('method', 'Efectivo')
    await submitPayment()

    expect(harness.apiPost).toHaveBeenCalledTimes(1)
    expect(harness.apiPost).toHaveBeenCalledWith('/api/pending-payments/7/pay', {
      payment_method_id: 1,
      amount: 120,
      description: 'Consulta'
    })

    const success = wrapper.emitted('success')
    expect(success).toHaveLength(1)
    expect(success[0][0]).toMatchObject({
      amount: 120,
      concept: 'Consulta',
      transactionNumber: 'TX-91',
      patient: { id: 5, name: 'Ana Torres' },
      paymentMethod: { name: 'Efectivo' }
    })
  })

  it('falls back to the transactions endpoint for an ad-hoc charge', async () => {
    await mountPaymentModal({ selectedPatient: null, selectedAppointment: null })

    await pickAdHocPatient('Ana Torres')
    const concept = document.querySelector('[data-payment-concept] input')
    concept.value = 'Limpieza'
    concept.dispatchEvent(new Event('input'))
    await flushPromises()
    const amount = document.querySelector('[data-payment-amount] input')
    amount.value = '80'
    amount.dispatchEvent(new Event('input'))
    await flushPromises()

    await chooseOption('method', 'Efectivo')
    await submitPayment()

    expect(harness.apiPost).toHaveBeenCalledTimes(1)
    const [url, payload] = harness.apiPost.mock.calls[0]
    expect(url).toBe('/api/transactions')
    expect(payload).toMatchObject({
      patient_id: 5,
      amount: 80,
      description: 'Limpieza',
      payment_method_id: 1
    })
    const success = wrapper.emitted('success')
    expect(success).toHaveLength(1)
    expect(success[0][0]).toMatchObject({
      amount: 80,
      concept: 'Limpieza',
      transactionNumber: 'TX-91',
      patient: { id: 5, name: 'Ana Torres' },
      paymentMethod: { name: 'Efectivo' }
    })
  })

  it('lets an ad-hoc charge pick a patient with no pending payments', async () => {
    harness.apiGet.mockImplementation(url => {
      if (url === '/api/payment-methods/active') {
        return Promise.resolve({ data: [paymentMethod] })
      }
      if (url === '/api/patients') {
        return Promise.resolve({ data: patientsWithoutPendingPayments })
      }
      return Promise.resolve({ data: [] })
    })

    await mountPaymentModal({
      selectedPatient: null,
      selectedAppointment: null,
      pendingPatients: []
    })

    expect(document.querySelector('[data-payment-patient-selector]')).not.toBeNull()

    await pickAdHocPatient('Luis Paredes')
    const concept = document.querySelector('[data-payment-concept] input')
    concept.value = 'Venta de producto'
    concept.dispatchEvent(new Event('input'))
    await flushPromises()
    const amount = document.querySelector('[data-payment-amount] input')
    amount.value = '35'
    amount.dispatchEvent(new Event('input'))
    await flushPromises()

    await chooseOption('method', 'Efectivo')
    await submitPayment()

    expect(document.body.textContent).toContain('Luis Paredes')
    expect(harness.apiPost).toHaveBeenCalledTimes(1)
    const [url, payload] = harness.apiPost.mock.calls[0]
    expect(url).toBe('/api/transactions')
    expect(payload).toMatchObject({
      patient_id: 42,
      amount: 35,
      description: 'Venta de producto',
      payment_method_id: 1
    })
  })

  it('never posts while the form is incomplete', async () => {
    await mountPaymentModal({ selectedPatient: null, selectedAppointment: null })

    await submitPayment()

    expect(harness.apiPost).not.toHaveBeenCalled()
    expect(document.querySelector('[data-payment-error]')).toBeNull()
  })

  it('answers the accounting lock with its notice and retries with the override reason', async () => {
    // #410 (S9): the override reason is offered only to the role that holds it.
    localStorage.setItem(
      'user',
      JSON.stringify({
        id: 1,
        name: 'Admin',
        role: 'administrador',
        permissions: ['override.accounting_lock']
      })
    )

    const lockMessage =
      'El módulo [cash_transactions] está bloqueado contablemente hasta el 2026-09-30: ' +
      'la operación [create] sobre un registro de esa fecha requiere autorización registrada.'

    harness.apiPost.mockRejectedValueOnce({
      response: { status: 403, data: { code: 'accounting_lock_blocked', message: lockMessage } }
    })

    await mountPaymentModal()
    await chooseOption('method', 'Efectivo')
    await submitPayment()

    const notice = document.querySelector('[data-accounting-lock-notice]')
    expect(notice).not.toBeNull()
    expect(notice.getAttribute('role')).toBe('alert')
    expect(notice.textContent).toContain(lockMessage)
    expect(document.querySelector('[data-payment-error]')).toBeNull()

    const reason = document.querySelector('[data-accounting-lock-reason] input')
    expect(reason).not.toBeNull()
    reason.value = 'Gerencia autorizo el cierre'
    reason.dispatchEvent(new Event('input', { bubbles: true }))
    await flushPromises()

    harness.apiPost.mockResolvedValueOnce({
      data: { id: 91, transaction_number: 'TX-91' },
      meta: {}
    })
    document.querySelector('[data-accounting-lock-retry]').click()
    await flushPromises()
    await flushPromises()

    const [url, payload] = harness.apiPost.mock.calls.at(-1)
    expect(url).toBe('/api/pending-payments/7/pay')
    expect(payload).toMatchObject({
      payment_method_id: paymentMethod.id,
      amount: 120,
      lock_override_reason: 'Gerencia autorizo el cierre'
    })

    const success = wrapper.emitted('success')
    expect(success).toHaveLength(1)
    expect(document.querySelector('[data-accounting-lock-notice]')).toBeNull()
  })

  it('answers a blocked Mercado Pago preparation and retries that same path', async () => {
    // #467 — the Mercado Pago preparation is a second door to /api/transactions,
    // so it owes the same notice and its retry must resume it, not the manual
    // submit.
    localStorage.setItem(
      'user',
      JSON.stringify({
        id: 1,
        name: 'Admin',
        role: 'administrador',
        permissions: ['override.accounting_lock']
      })
    )

    harness.apiGet.mockImplementation(url => {
      if (url === '/api/payment-methods/active') {
        return Promise.resolve({ data: [mpPaymentMethod] })
      }
      return Promise.resolve({ data: [], meta: { total: 0 } })
    })

    const lockMessage =
      'El módulo [cash_transactions] está bloqueado contablemente hasta el 2026-09-30: ' +
      'la operación [create] sobre un registro de esa fecha requiere autorización registrada.'

    harness.apiPost.mockRejectedValueOnce({
      response: { status: 403, data: { code: 'accounting_lock_blocked', message: lockMessage } }
    })

    await mountPaymentModal()
    await chooseOption('method', 'Mercado Pago')
    document.querySelector('#tab-mercadopago').click()
    await flushPromises()
    await flushPromises()

    expect(harness.apiPost).toHaveBeenCalledTimes(1)
    expect(harness.apiPost.mock.calls[0][0]).toBe('/api/transactions')
    expect(harness.apiPost.mock.calls[0][1]).not.toHaveProperty('lock_override_reason')
    expect(document.querySelector('[data-payment-error]')).toBeNull()

    const notice = document.querySelector('[data-accounting-lock-notice]')
    expect(notice).not.toBeNull()
    expect(notice.textContent).toContain(lockMessage)

    const reason = document.querySelector('[data-accounting-lock-reason] input')
    expect(reason).not.toBeNull()
    reason.value = 'Gerencia autorizo el cobro'
    reason.dispatchEvent(new Event('input', { bubbles: true }))
    await flushPromises()

    harness.apiPost.mockResolvedValueOnce({
      data: { id: 91, transaction_number: 'TX-91' },
      meta: {}
    })
    document.querySelector('[data-accounting-lock-retry]').click()
    await flushPromises()
    await flushPromises()

    const transactionCalls = harness.apiPost.mock.calls.filter(
      ([url]) => url === '/api/transactions'
    )
    expect(transactionCalls).toHaveLength(2)
    expect(transactionCalls.at(-1)[1]).toMatchObject({
      patient_id: 5,
      amount: 120,
      description: 'Consulta',
      payment_method_id: mpPaymentMethod.id,
      lock_override_reason: 'Gerencia autorizo el cobro'
    })

    expect(document.querySelector('[data-accounting-lock-notice]')).toBeNull()
    expect(document.querySelector('[data-payment-modal]')).not.toBeNull()
  })

  it('withdraws a blocked manual notice when the operator moves to another tab', async () => {
    // #472 — the notice and its retry belong to the action that was blocked: a
    // tab change must not leave the reason stranded over a different door.
    localStorage.setItem(
      'user',
      JSON.stringify({
        id: 1,
        name: 'Admin',
        role: 'administrador',
        permissions: ['override.accounting_lock']
      })
    )

    harness.apiGet.mockImplementation(url => {
      if (url === '/api/payment-methods/active') {
        return Promise.resolve({ data: [mpPaymentMethod] })
      }
      return Promise.resolve({ data: [], meta: { total: 0 } })
    })

    harness.apiPost.mockRejectedValueOnce({
      response: {
        status: 403,
        data: {
          code: 'accounting_lock_blocked',
          message: 'El módulo [cash_transactions] está bloqueado contablemente.'
        }
      }
    })

    await mountPaymentModal()
    await chooseOption('method', 'Mercado Pago')
    await submitPayment()

    expect(document.querySelector('[data-accounting-lock-notice]')).not.toBeNull()

    // Moving to the Mercado Pago tab starts a different action, and its failure
    // is not the lock: the stale notice must be gone.
    harness.apiPost.mockRejectedValueOnce({
      response: { status: 500, data: { message: 'No se pudo preparar el pago' } }
    })
    document.querySelector('#tab-mercadopago').click()
    await flushPromises()
    await flushPromises()

    expect(document.querySelector('[data-accounting-lock-notice]')).toBeNull()
    expect(document.querySelector('[data-accounting-lock-reason]')).toBeNull()
  })
})
