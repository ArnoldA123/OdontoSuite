import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'

// Caja > Devoluciones contract (S1). A refund is not an expense: the modal
// asks who authorizes it and why, blocks locally when either is missing, posts
// both to POST /api/transactions, and shows the server 422 at the same two
// fields. The locking flow of the money modals (#304) is left untouched.
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
import TransactionModal from '../../../resources/js/modules/cash-register/components/TransactionModal.vue'

const PAYMENT_METHODS = [
  { id: 1, name: 'Efectivo', is_active: true },
  { id: 2, name: 'Tarjeta de crédito', is_active: true }
]

const PATIENTS = [
  {
    id: 3,
    name: 'Ana',
    last_name: 'Torres',
    document_number: '45678912',
    email: 'ana@test.com'
  }
]

const ADMINS = [
  { id: 5, name: 'Rosa Vega', role: 'administrador', specialty: null },
  { id: 8, name: 'Luis Prado', role: 'administrador', specialty: null }
]

const mockCatalog = () => {
  harness.apiGet.mockImplementation(async url => {
    const target = String(url)
    if (target === '/api/payment-methods/active') return { data: PAYMENT_METHODS }
    if (target === '/api/patients/search') return { data: PATIENTS }
    if (target === '/api/appointments') return { data: [] }
    if (target === '/api/users/active') return { data: ADMINS, meta: { total: ADMINS.length } }
    return { data: [] }
  })
}

let wrapper = null

const body = () => document.body
const field = selector => body().querySelector(`[data-modal-field="${selector}"]`)
const fieldError = name => body().querySelector(`[data-field-error="${name}"]`)

const chooseOption = async (root, optionText) => {
  expect(root, 'select root must be rendered').toBeTruthy()
  root.querySelector('button[aria-haspopup="true"]').click()
  await flushPromises()

  const option = [...root.querySelectorAll('[role="listbox"] button')].find(
    candidate => candidate.textContent.trim() === optionText
  )
  expect(option, `option "${optionText}" must be rendered`).toBeTruthy()
  option.click()
  await flushPromises()
}

const typeInto = async (input, value) => {
  expect(input, 'input must be rendered').toBeTruthy()
  input.value = value
  input.dispatchEvent(new Event('input', { bubbles: true }))
  await flushPromises()
}

const mountModal = async () => {
  mockCatalog()
  wrapper = mount(TransactionModal, {
    props: { modelValue: true },
    attachTo: document.body,
    global: { plugins: [uiComponents] }
  })
  await flushPromises()
}

const selectRefund = async () => {
  await chooseOption(field('type'), 'Devolución')
}

const fillBaseForm = async () => {
  await typeInto(body().querySelector('[data-modal-patient-search] input'), 'Ana')
  body().querySelector('[data-patient-result]').click()
  await flushPromises()

  await chooseOption(field('payment_method_id'), 'Efectivo')
  await typeInto(body().querySelector('[data-modal-amount] input'), '50')
}

const fillRefundDescription = async (description = 'Paciente desiste del tratamiento') => {
  await typeInto(body().querySelector('[data-modal-description] input'), description)
}

const fillAuthorizer = async (name = 'Rosa Vega') => {
  await chooseOption(field('authorized_by'), name)
}

const fillReason = async (reason = 'Gerencia autoriza la devolución') => {
  await typeInto(body().querySelector('[data-modal-field="authorization_reason"] input'), reason)
}

const submit = async () => {
  body().querySelector('[data-modal-submit]').click()
  await flushPromises()
}

const dialog = () => body().querySelector('[role="dialog"]')

beforeEach(() => {
  harness.apiGet.mockReset()
  harness.apiPost.mockReset()
  harness.apiPost.mockResolvedValue({ data: { id: 99 } })
})

afterEach(() => {
  wrapper?.unmount()
  wrapper = null
  document.body.innerHTML = ''
  vi.restoreAllMocks()
})

describe('Caja > modal de devolución (S1)', () => {
  it('speaks of a devolución, not an egreso, and loads the administrator lookup', async () => {
    await mountModal()
    await selectRefund()

    expect(dialog().textContent).toContain('Registrar devolución')
    expect(dialog().textContent).toContain('Motivo de la devolución')
    expect(dialog().textContent).toContain('Administrador que autoriza')
    expect(dialog().textContent).toContain('Motivo de la autorización')
    // The status badge and the type option keep the same vocabulary.
    expect(dialog().textContent).toContain('Devolución')

    const lookup = harness.apiGet.mock.calls.find(([url]) => url === '/api/users/active')
    expect(lookup, '/api/users/active must be loaded for a refund').toBeTruthy()
    expect(lookup[1]).toEqual({ params: { role: 'administrador' } })

    await fillAuthorizer('Luis Prado')
    expect(field('authorized_by').textContent).toContain('Luis Prado')
  })

  it('does not submit a refund without the authorizer and the reason', async () => {
    await mountModal()
    await selectRefund()
    await fillBaseForm()
    await fillRefundDescription()

    await submit()

    expect(harness.apiPost).not.toHaveBeenCalled()
    expect(fieldError('authorized_by')).toBeTruthy()
    expect(fieldError('authorized_by').textContent).toContain(
      'El administrador que autoriza es requerido'
    )
    expect(fieldError('authorization_reason')).toBeTruthy()
    expect(fieldError('authorization_reason').textContent).toContain(
      'El motivo de la autorización es requerido'
    )
    expect(dialog()).toBeTruthy()

    // With the authorizer alone the refund is still blocked by the reason.
    await fillAuthorizer()

    await submit()

    expect(harness.apiPost).not.toHaveBeenCalled()
    expect(fieldError('authorized_by')).toBeNull()
    expect(fieldError('authorization_reason')).toBeTruthy()

    await fillReason()
    await submit()

    expect(harness.apiPost).toHaveBeenCalledTimes(1)
  })

  it('posts the refund with the administrator and the trimmed reason', async () => {
    await mountModal()
    await selectRefund()
    await fillBaseForm()
    await fillRefundDescription()
    await fillAuthorizer('Rosa Vega')
    await fillReason('   Gerencia autoriza la devolución   ')

    await submit()

    expect(harness.apiPost).toHaveBeenCalledTimes(1)
    const [url, payload] = harness.apiPost.mock.calls[0]
    expect(url).toBe('/api/transactions')
    expect(payload).toMatchObject({
      type: 'refund',
      patient_id: 3,
      payment_method_id: 1,
      amount: 50,
      description: 'Paciente desiste del tratamiento',
      authorized_by: 5,
      authorization_reason: 'Gerencia autoriza la devolución'
    })
    expect(typeof payload.authorized_by).toBe('number')

    expect(wrapper.emitted('success')).toBeTruthy()
    expect(wrapper.emitted('update:modelValue')).toEqual([[false]])
  })

  it('surfaces the server 422 at both authorization fields and keeps the modal open', async () => {
    harness.apiPost.mockRejectedValue({
      response: {
        data: {
          message: 'Los datos proporcionados no son válidos.',
          errors: {
            authorized_by: ['El usuario autorizador debe tener rol de administrador.'],
            authorization_reason: [
              'El motivo de la autorización no puede exceder los 255 caracteres.'
            ]
          }
        },
        status: 422
      }
    })

    await mountModal()
    await selectRefund()
    await fillBaseForm()
    await fillRefundDescription()
    await fillAuthorizer()
    await fillReason('Motivo suficientemente largo')

    await submit()

    expect(harness.apiPost).toHaveBeenCalledTimes(1)
    expect(fieldError('authorized_by').textContent).toContain(
      'El usuario autorizador debe tener rol de administrador.'
    )
    expect(fieldError('authorization_reason').textContent).toContain(
      'El motivo de la autorización no puede exceder los 255 caracteres.'
    )
    expect(body().querySelector('[data-modal-general-error]')).toBeTruthy()
    expect(dialog()).toBeTruthy()
    expect(wrapper.emitted('success')).toBeUndefined()
  })

  it('caps the authorization reason at the server limit', async () => {
    await mountModal()
    await selectRefund()
    await fillBaseForm()
    await fillRefundDescription()
    await fillAuthorizer()
    await fillReason('x'.repeat(300))

    await submit()

    expect(harness.apiPost).toHaveBeenCalledTimes(1)
    const [, payload] = harness.apiPost.mock.calls[0]
    expect(payload.authorization_reason).toHaveLength(255)
  })

  it('resets the authorization fields when the type changes and leaves payments untouched', async () => {
    await mountModal()
    expect(harness.apiGet.mock.calls.map(([url]) => url)).not.toContain('/api/users/active')

    await selectRefund()
    await fillAuthorizer('Rosa Vega')
    await fillReason('Motivo de prueba')

    await chooseOption(field('type'), 'Ingreso')
    expect(field('authorized_by')).toBeNull()
    expect(body().querySelector('[data-modal-field="authorization_reason"]')).toBeNull()

    await fillBaseForm()
    await submit()

    const [, paymentPayload] = harness.apiPost.mock.calls[0]
    expect(paymentPayload.type).toBe('payment')
    expect(paymentPayload).not.toHaveProperty('authorized_by')
    expect(paymentPayload).not.toHaveProperty('authorization_reason')

    await selectRefund()
    expect(field('authorized_by').textContent).toContain('Seleccionar administrador')
    expect(body().querySelector('[data-modal-field="authorization_reason"] input').value).toBe('')
  })
})
