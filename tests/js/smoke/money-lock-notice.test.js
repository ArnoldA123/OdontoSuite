import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'

// #304 (S4/S8) — a money modal answers the accounting lock inline: the API
// message with its machine code, the override reason for whoever holds
// `override.accounting_lock`, and a retry that resends the same operation.
const { postMock, getMock, permissionState } = vi.hoisted(() => ({
  postMock: vi.fn(),
  getMock: vi.fn(),
  permissionState: { override: null }
}))

vi.mock('../../../resources/js/composables/useApi', () => ({
  useApi: () => ({
    get: getMock,
    post: postMock,
    put: vi.fn(async () => ({ data: {} })),
    patch: vi.fn(async () => ({ data: {} })),
    delete: vi.fn(async () => ({ data: {} })),
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

vi.mock('../../../resources/js/composables/usePermissions', async () => {
  const { computed, ref } = await import('vue')
  const grant = ref(false)
  permissionState.override = grant

  return {
    usePermissions: () => ({
      can: { overrideAccountingLock: computed(() => grant.value) },
      isAdministrador: computed(() => grant.value),
      isRecepcionista: computed(() => false),
      isClinical: computed(() => false),
      isFinanzas: computed(() => false),
      hasAnyPermission: () => grant.value,
      hasAllPermissions: () => grant.value
    })
  }
})

import uiComponents from '../../../resources/js/plugins/ui-components'
import TransactionModal from '../../../resources/js/modules/cash-register/components/TransactionModal.vue'

const LOCK_MESSAGE =
  'El módulo [cash_transactions] está bloqueado contablemente hasta el 2026-09-30: ' +
  'la operación [create] sobre un registro de esa fecha requiere autorización registrada.'

const LOCK_ERROR = {
  response: { data: { code: 'accounting_lock_blocked', message: LOCK_MESSAGE }, status: 403 }
}

const PATIENT = {
  id: 7,
  name: 'Ana',
  last_name: 'Torres',
  document_number: '12345678',
  email: 'ana@test.com'
}

const PAYMENT_METHODS = [{ id: 3, name: 'Efectivo', type: 'cash' }]

let wrapper = null

const notice = () => document.querySelector('[data-accounting-lock-notice]')
const reasonInput = () => document.querySelector('[data-accounting-lock-reason] input')
const retryButton = () => document.querySelector('[data-accounting-lock-retry]')
const submitButton = () => document.querySelector('[data-modal-submit]')

const typeInto = async (input, value) => {
  input.value = value
  input.dispatchEvent(new Event('input', { bubbles: true }))
  await flushPromises()
}

const chooseOption = async (field, label) => {
  const trigger = document.querySelector(`${field} button[aria-haspopup="true"]`)
  expect(trigger, `select "${field}" must render a trigger`).toBeTruthy()
  trigger.click()
  await flushPromises()

  const option = [...document.querySelectorAll(`${field} [role="listbox"] button`)].find(
    button => button.textContent.trim() === label
  )
  expect(option, `option "${label}" must exist in "${field}"`).toBeTruthy()
  option.click()
  await flushPromises()
}

const mountModal = async () => {
  wrapper = mount(TransactionModal, {
    props: { modelValue: true },
    global: { plugins: [uiComponents] },
    attachTo: document.body
  })
  await flushPromises()
}

const fillMoneyForm = async () => {
  await typeInto(document.querySelector('[data-modal-patient-search] input'), 'Ana')
  document.querySelector('[data-patient-result]').click()
  await flushPromises()

  await chooseOption('[data-modal-field="payment_method_id"]', 'Efectivo')
  await typeInto(document.querySelector('[data-modal-amount] input'), '150')
}

const submit = async () => {
  submitButton().click()
  await flushPromises()
}

describe('money modals answer the accounting lock inline (#304, S4)', () => {
  beforeEach(() => {
    postMock.mockReset()
    getMock.mockReset()
    getMock.mockImplementation(async url => {
      if (url === '/api/patients/search') return { data: [PATIENT] }
      if (url === '/api/payment-methods/active') return { data: PAYMENT_METHODS }
      if (url === '/api/appointments') return { data: [] }
      return { data: [] }
    })
    permissionState.override.value = false
  })

  afterEach(() => {
    wrapper?.unmount()
    wrapper = null
    document.body.innerHTML = ''
  })

  it('shows the API message without offering an override to a role that lacks it', async () => {
    postMock.mockRejectedValueOnce(LOCK_ERROR)
    await mountModal()
    await fillMoneyForm()
    await submit()

    expect(postMock).toHaveBeenCalledTimes(1)
    expect(postMock.mock.calls[0][0]).toBe('/api/transactions')
    expect(postMock.mock.calls[0][1]).not.toHaveProperty('lock_override_reason')

    expect(notice()).not.toBeNull()
    expect(notice().getAttribute('role')).toBe('alert')
    expect(notice().textContent).toContain(LOCK_MESSAGE)
    expect(document.querySelector('[data-modal-general-error]')).toBeNull()
    expect(reasonInput()).toBeNull()
    expect(retryButton()).toBeNull()
  })

  it('refuses a short reason locally and resends the operation with the long one', async () => {
    permissionState.override.value = true
    postMock.mockRejectedValueOnce(LOCK_ERROR)
    await mountModal()
    await fillMoneyForm()
    await submit()

    expect(reasonInput()).not.toBeNull()
    expect(retryButton().textContent).toContain('Autorizar y reintentar')

    await typeInto(reasonInput(), 'corto')
    expect(document.querySelector('[data-accounting-lock-counter]').textContent).toContain('5/10')

    retryButton().click()
    await flushPromises()

    expect(postMock).toHaveBeenCalledTimes(1)
    expect(notice().textContent).toContain('al menos 10 caracteres')

    await typeInto(reasonInput(), 'Gerencia autorizo la correccion')
    expect(document.querySelector('[data-accounting-lock-counter]').textContent).toContain('31/10')

    postMock.mockResolvedValueOnce({ data: { id: 91 } })
    retryButton().click()
    await flushPromises()

    expect(postMock).toHaveBeenCalledTimes(2)

    const [url, payload] = postMock.mock.calls[1]
    expect(url).toBe('/api/transactions')
    expect(payload).toMatchObject({
      patient_id: PATIENT.id,
      payment_method_id: 3,
      amount: 150,
      lock_override_reason: 'Gerencia autorizo la correccion'
    })

    expect(wrapper.emitted('success')).toBeTruthy()
  })

  it('keeps the server 422 of lock_override_reason in the notice and the modal open', async () => {
    permissionState.override.value = true
    postMock.mockRejectedValueOnce(LOCK_ERROR)
    await mountModal()
    await fillMoneyForm()
    await submit()

    const serverMessage =
      'Autorizar la operación [create] sobre el módulo [cash_transactions] bloqueado hasta el ' +
      '2026-09-30 requiere un motivo de al menos 10 caracteres.'

    postMock.mockRejectedValueOnce({
      response: {
        data: {
          message: 'Los datos proporcionados no son válidos.',
          errors: { lock_override_reason: [serverMessage] }
        },
        status: 422
      }
    })

    await typeInto(reasonInput(), 'motivo suficientemente largo')
    retryButton().click()
    await flushPromises()

    expect(postMock).toHaveBeenCalledTimes(2)
    expect(notice()).not.toBeNull()
    expect(notice().textContent).toContain(serverMessage)
    expect(document.querySelector('[role="dialog"]')).not.toBeNull()
    expect(document.querySelector('[data-modal-general-error]')).toBeNull()
  })
})
