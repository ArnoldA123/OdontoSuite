import { readFileSync } from 'node:fs'
import { resolve } from 'node:path'
import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'

// Caja > depósitos contract (#320, T3, S3/S6/S7). Pins the deposit surface at
// the component boundary, mirroring the cash smokes next to it:
//  - `CashDepositModal` shows the cash the drawer reports, speaks v-model, and
//    blocks locally an amount above it and a missing bank reference;
//  - the API stays the truth: `POST /api/cash-register/deposits` receives the
//    session, amount, reference and date, and every 422 lands on its field
//    (`data-field-error`) plus the general alert;
//  - a rejected deposit creates nothing: no `success`, no close, no local row;
//  - `CloseCashModal` renders the deposits of the shift with its count and
//    total, and says the expected amount already discounts them;
//  - `CashRegisterPage` gates the deposit action on the permission alone (S6)
//    and refreshes the shift's deposits through the session filter.
const harness = vi.hoisted(() => ({ apiGet: vi.fn(), apiPost: vi.fn() }))

vi.mock('../../../resources/js/composables/useApi', () => ({
  useApi: () => ({
    get: harness.apiGet,
    post: harness.apiPost,
    put: async () => ({ data: {} }),
    patch: async () => ({ data: {} }),
    delete: async () => ({ data: {} }),
    download: async () => new Blob([]),
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
import CashDepositModal from '../../../resources/js/modules/cash-register/components/CashDepositModal.vue'
import CloseCashModal from '../../../resources/js/modules/cash-register/components/CloseCashModal.vue'

const readComponent = name =>
  readFileSync(resolve('resources/js/modules/cash-register/components', name), 'utf8')

const depositSource = readComponent('CashDepositModal.vue')
const pageSource = readFileSync(
  resolve('resources/js/modules/cash-register/CashRegisterPage.vue'),
  'utf8'
)

const SESSION = { id: 5, status: 'open', branch_id: 1, opening_amount: 100 }

// The drawer reports 250 available; the deposit below sits under it so the
// local guard cannot mask a server rejection.
const AVAILABLE_CASH = 250

const SUMMARY = {
  opening_amount: 100,
  total_income: 320,
  total_expenses: 70,
  expected_amount: 250,
  transactions_count: 7,
  movements_count: 3
}

const DEPOSIT = {
  id: 11,
  cash_register_session_id: 5,
  amount: 120,
  reference: 'OP-778812',
  deposited_at: '2026-10-21',
  notes: null
}

const normalize = value => (value || '').replace(/\u00a0/g, ' ')

const todayIso = () => {
  const now = new Date()
  const month = String(now.getMonth() + 1).padStart(2, '0')
  const day = String(now.getDate()).padStart(2, '0')
  return `${now.getFullYear()}-${month}-${day}`
}

let wrapper = null

const mountDepositModal = async (props = {}) => {
  wrapper = mount(CashDepositModal, {
    props: { modelValue: true, session: SESSION, availableCash: AVAILABLE_CASH, ...props },
    attachTo: document.body,
    global: { plugins: [uiComponents] }
  })
  await flushPromises()
  return wrapper
}

const mountCloseModal = async (props = {}) => {
  wrapper = mount(CloseCashModal, {
    props: { modelValue: true, session: SESSION, summary: SUMMARY, ...props },
    attachTo: document.body,
    global: { plugins: [uiComponents] }
  })
  await flushPromises()
  return wrapper
}

const query = selector => document.body.querySelector(selector)

const setInput = async (selector, value) => {
  const input = query(selector)
  expect(input, `${selector} must render an input`).toBeTruthy()
  input.value = String(value)
  input.dispatchEvent(new Event('input'))
  await flushPromises()
}

const fillDeposit = async ({ amount = 120, reference = 'OP-778812', date } = {}) => {
  await setInput('[data-deposit-amount] input', amount)
  await setInput('[data-deposit-reference] input', reference)
  if (date) await setInput('[data-deposit-date] input', date)
}

const submit = async () => {
  query('[data-modal-submit]').click()
  await flushPromises()
}

const fieldErrorText = field => {
  const node = query(`[data-field-error="${field}"]`)
  expect(node, `field error hook for ${field} must render`).toBeTruthy()
  return normalize(node.textContent)
}

beforeEach(() => {
  harness.apiGet.mockReset()
  harness.apiPost.mockReset()
  harness.apiGet.mockResolvedValue({ data: [] })
  harness.apiPost.mockResolvedValue({ data: DEPOSIT, meta: { message: 'Depósito registrado' } })
})

afterEach(() => {
  if (wrapper) {
    wrapper.unmount()
    wrapper = null
  }
  document.body.innerHTML = ''
})

describe('Caja > deposit modal', () => {
  it('renders the cash the drawer reports as the ceiling of the deposit', async () => {
    await mountDepositModal()

    const hint = query('[data-deposit-available]')
    expect(hint).toBeTruthy()
    expect(normalize(hint.textContent)).toContain('Efectivo disponible')
    expect(normalize(hint.textContent)).toContain('250.00')
    expect(depositSource).not.toContain('<style scoped>')
  })

  it('defaults the deposit date to today and refuses a future one', async () => {
    await mountDepositModal()

    const dateInput = query('[data-deposit-date] input')
    expect(dateInput).toBeTruthy()
    expect(dateInput.getAttribute('type')).toBe('date')
    expect(dateInput.value).toBe(todayIso())
    expect(dateInput.getAttribute('max')).toBe(todayIso())
  })

  it('blocks the submit locally when the bank reference is missing', async () => {
    await mountDepositModal()

    await setInput('[data-deposit-amount] input', '120')
    await submit()

    expect(harness.apiPost).not.toHaveBeenCalled()
    expect(fieldErrorText('reference')).toContain('referencia bancaria')
    expect(wrapper.emitted('success')).toBeUndefined()
  })

  it('blocks locally an amount above the available cash and never asks the API', async () => {
    await mountDepositModal()

    await fillDeposit({ amount: 300 })

    expect(normalize(query('[data-deposit-over-available]').textContent)).toContain('250.00')

    await submit()

    expect(harness.apiPost).not.toHaveBeenCalled()
    expect(fieldErrorText('amount')).toContain('250.00')
    expect(wrapper.emitted('success')).toBeUndefined()
  })

  it('leaves the ceiling to the API when the session summary is unavailable', async () => {
    await mountDepositModal({ availableCash: null })

    expect(normalize(query('[data-deposit-available]').textContent)).toContain('N/D')

    await fillDeposit({ amount: 300 })
    await submit()

    expect(query('[data-deposit-over-available]')).toBeNull()
    expect(harness.apiPost).toHaveBeenCalledTimes(1)
    expect(harness.apiPost.mock.calls[0][1].amount).toBe(300)
  })

  it('posts the session, amount, reference and date and emits the response data', async () => {
    await mountDepositModal()

    await fillDeposit({ date: '2026-10-21' })
    await submit()

    expect(harness.apiPost).toHaveBeenCalledTimes(1)
    const [url, payload] = harness.apiPost.mock.calls[0]
    expect(url).toBe('/api/cash-register/deposits')
    expect(payload).toEqual({
      cash_register_session_id: 5,
      amount: 120,
      reference: 'OP-778812',
      deposited_at: '2026-10-21',
      notes: null
    })
    expect(wrapper.emitted('success')).toEqual([[DEPOSIT]])
    expect(wrapper.emitted('update:modelValue')).toEqual([[false]])
  })

  it('caps the reference at 100 characters and the notes at 1000', async () => {
    await mountDepositModal()

    await setInput('[data-deposit-reference] input', 'r'.repeat(140))
    await setInput('#cash-deposit-notes', 'n'.repeat(1200))

    expect(query('[data-deposit-reference] input').value).toHaveLength(100)
    expect(query('#cash-deposit-notes').value).toHaveLength(1000)
    expect(normalize(query('[data-deposit-notes-counter]').textContent)).toContain('1000/1000')
  })

  it('maps the 422 of amount and reference to both fields plus the general alert', async () => {
    harness.apiPost.mockRejectedValue({
      response: {
        data: {
          message: 'Datos inválidos',
          errors: {
            amount: ['El monto del depósito no puede superar el efectivo disponible en la gaveta.'],
            reference: ['La referencia bancaria del depósito es requerida.']
          }
        }
      }
    })

    await mountDepositModal()
    await fillDeposit()
    await submit()

    const general = query('[data-cash-deposit-error]')
    expect(general).toBeTruthy()
    expect(general.getAttribute('role')).toBe('alert')
    expect(normalize(general.textContent)).toContain('Datos inválidos')

    expect(fieldErrorText('amount')).toContain('no puede superar el efectivo disponible')
    expect(fieldErrorText('reference')).toContain('referencia bancaria del depósito es requerida')
  })

  it('maps the 422 of deposited_at and cash_register_session_id to their fields', async () => {
    harness.apiPost.mockRejectedValue({
      response: {
        data: {
          message: 'Datos inválidos',
          errors: {
            deposited_at: ['La fecha del depósito no puede ser futura.'],
            cash_register_session_id: ['Solo se puede depositar en una sesión de caja abierta.']
          }
        }
      }
    })

    await mountDepositModal()
    await fillDeposit()
    await submit()

    expect(fieldErrorText('deposited_at')).toContain('no puede ser futura')
    expect(fieldErrorText('cash_register_session_id')).toContain('sesión de caja abierta')
  })

  it('creates nothing locally when the API rejects the deposit', async () => {
    harness.apiPost.mockRejectedValue({
      response: {
        data: {
          message: 'Datos inválidos',
          errors: { reference: ['La referencia bancaria del depósito es requerida.'] }
        }
      }
    })

    await mountDepositModal()
    await fillDeposit({ reference: 'OP-1' })
    await submit()

    expect(wrapper.emitted('success')).toBeUndefined()
    expect(wrapper.emitted('update:modelValue')).toBeUndefined()
    expect(harness.apiGet).not.toHaveBeenCalled()
    expect(query('[data-deposit-row]')).toBeNull()
    // The typed values survive the rejection: the form was never reset into a
    // local row the page would later duplicate.
    expect(query('[data-deposit-reference] input').value).toBe('OP-1')
    expect(depositSource).not.toMatch(/\.push\(|\.unshift\(|\.splice\(/)
  })
})

describe('Caja > close cash modal deposits', () => {
  it('renders the deposits of the shift with their count and total', async () => {
    await mountCloseModal({ depositsCount: 2, depositsTotal: 320 })

    const row = query('[data-close-deposits]')
    expect(row).toBeTruthy()
    expect(normalize(row.textContent)).toContain('Depósitos del turno')
    expect(normalize(row.textContent)).toContain('2')
    expect(normalize(row.textContent)).toContain('320.00')

    const caption = query('[data-close-deposits-caption]')
    expect(caption).toBeTruthy()
    expect(normalize(caption.textContent)).toContain('saldo esperado')
    expect(normalize(caption.textContent)).toContain('descuenta')
  })

  it('falls back to zero deposits when the page has none', async () => {
    await mountCloseModal()

    const row = normalize(query('[data-close-deposits]').textContent)
    expect(row).toContain('0')
    expect(row).toContain('0.00')
  })
})

describe('Caja > deposit action (S6)', () => {
  it('gates the hero action on the deposit permission alone', () => {
    expect(pageSource).toContain('data-hero-action="deposit"')
    expect(pageSource).toContain('can.manageCashDeposit')
    expect(pageSource).toContain('hasActiveSession.value && can.manageCashDeposit.value')
    // No role list: the clinical desks must never see the action, and the
    // permission matrix is the only source (#280).
    expect(pageSource).not.toMatch(/hasRole\(|user\.value\?\.role|role === 'administrador'/)
  })

  it('loads the deposits of the open shift and feeds the closing summary', () => {
    expect(pageSource).toContain('cash_register_session_id: currentSession.value.id')
    expect(pageSource).toContain('per_page: 100')
    expect(pageSource).toContain('summary.value?.expected_amount ?? null')
    expect(pageSource).toContain(':deposits-count="depositsCount"')
    expect(pageSource).toContain(':deposits-total="depositsTotal"')
  })
})
