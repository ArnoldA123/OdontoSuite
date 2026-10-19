import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'

const harness = vi.hoisted(() => ({
  apiPost: vi.fn(),
  apiPut: vi.fn()
}))

vi.mock('../../../resources/js/composables/useApi', () => ({
  useApi: () => ({
    get: vi.fn(),
    post: harness.apiPost,
    put: harness.apiPut,
    patch: vi.fn(),
    delete: vi.fn(),
    setToken: () => {},
    normalizeError: () => ''
  })
}))

import uiComponents from '../../../resources/js/plugins/ui-components'
import PaymentMethodFormModal from '../../../resources/js/modules/settings/payment-methods/PaymentMethodFormModal.vue'

const systemGatewayMethod = {
  id: 3,
  code: 'cash',
  name: 'Efectivo',
  description: 'Pago en efectivo',
  gateway_type: 'mercadopago',
  commission_percentage: 3.5,
  requires_authorization: false,
  allows_change: true,
  is_active: false,
  is_system: true,
  has_gateway_config: true,
  gateway_config: { access_token: 'APP_USR-SECRETO-QUE-NO-DEBE-VERSE' }
}

let wrapper = null

const mountModal = async (method = null) => {
  wrapper = mount(PaymentMethodFormModal, {
    props: { method },
    global: { plugins: [uiComponents] },
    attachTo: document.body
  })
  await flushPromises()
  return wrapper
}

const root = () => document.querySelector('[data-payment-method-form]')
const dialog = () => document.querySelector('[role="dialog"]')
const dialogText = () => dialog().textContent
const field = name => root().querySelector(`[data-field="${name}"]`)
const inputOf = name => field(name).querySelector('input, textarea')
const selectTrigger = name => field(name).querySelector('button')

const setValue = async (name, value) => {
  const input = inputOf(name)
  input.value = value
  input.dispatchEvent(new Event('input'))
  await flushPromises()
}

const submit = async () => {
  document.querySelector('[data-modal-submit]').click()
  await flushPromises()
  await flushPromises()
}

beforeEach(() => {
  harness.apiPost.mockResolvedValue({ data: { id: 11, name: 'Yape' }, meta: {} })
  harness.apiPut.mockResolvedValue({ data: { id: 3, name: 'Efectivo' }, meta: {} })
})

afterEach(() => {
  wrapper?.unmount()
  wrapper = null
  document.body.innerHTML = ''
})

describe('PaymentMethodFormModal', () => {
  it('renders the create form with an active default status and no credentials', async () => {
    await mountModal()

    expect(dialogText()).toContain('Nuevo método de pago')
    expect(root().getAttribute('data-payment-method-form')).toBe('create')

    expect(inputOf('code').disabled).toBe(false)
    expect(inputOf('code').placeholder).toBe('ej. yape')
    expect(field('code').textContent).toContain('Identificador corto en minúsculas')

    expect(field('status').querySelector('select')).toBeNull()
    expect(selectTrigger('status').textContent.trim()).toBe('Activo')

    expect(document.querySelector('[data-redacted="true"]')).toBeNull()
  })

  it('prefills the edit form from the method and locks the code', async () => {
    await mountModal(systemGatewayMethod)

    expect(dialogText()).toContain('Editar método de pago')
    expect(root().getAttribute('data-payment-method-form')).toBe('edit')

    expect(inputOf('name').value).toBe('Efectivo')
    expect(inputOf('code').value).toBe('cash')
    expect(inputOf('code').disabled).toBe(true)
    expect(field('code').textContent).toContain('El código no puede modificarse')
    expect(field('code').textContent).toContain('Sistema')

    expect(inputOf('commission_percentage').value).toBe('3.5')
    expect(selectTrigger('status').textContent.trim()).toBe('Inactivo')
    expect(selectTrigger('gateway_type').textContent.trim()).toBe('Mercado Pago')
  })

  it('keeps every system method field editable and offers no dead-end footer', async () => {
    await mountModal(systemGatewayMethod)

    expect(inputOf('name').disabled).toBe(false)
    expect(inputOf('commission_percentage').disabled).toBe(false)
    expect(field('requires_authorization').querySelector('input').disabled).toBe(false)
    expect(field('allows_change').querySelector('input').disabled).toBe(false)
    expect(selectTrigger('status').disabled).toBe(false)

    const primary = document.querySelector('[data-modal-submit]')
    expect(primary).not.toBeNull()
    expect(primary.disabled).toBe(false)
    expect(primary.textContent).toContain('Actualizar método')
    expect(dialogText()).not.toContain('Método del sistema')

    expect(document.querySelector('[data-system-notice]').textContent).toContain(
      'Los métodos del sistema se pueden desactivar, pero no eliminar.'
    )
  })

  it('renders gateway credentials in a redacted block with a masked access token', async () => {
    await mountModal(systemGatewayMethod)

    const redacted = document.querySelector('[data-redacted="true"]')
    expect(redacted).not.toBeNull()

    const accessToken = inputOf('access_token')
    expect(accessToken.type).toBe('password')
    expect(accessToken.value).toBe('')
    expect(accessToken.placeholder).toBe('••••••')
    expect(inputOf('public_key').placeholder).toBe('••••••')

    expect(dialogText()).not.toContain('APP_USR-SECRETO-QUE-NO-DEBE-VERSE')
    const renderedValues = Array.from(redacted.querySelectorAll('input')).map(input => input.value)
    expect(renderedValues.join(' ')).not.toContain('APP_USR-SECRETO-QUE-NO-DEBE-VERSE')
  })

  it('blocks the submit when the public key is filled without an access token', async () => {
    await mountModal(systemGatewayMethod)

    await setValue('public_key', 'TEST-public-key')
    await submit()

    expect(harness.apiPut).not.toHaveBeenCalled()
    expect(root().textContent).toContain(
      'Ingresa el Access Token para actualizar las credenciales.'
    )
  })

  it('sends the create payload without gateway_config or is_system and emits saved', async () => {
    await mountModal()

    await setValue('code', 'yape')
    await setValue('name', 'Yape')
    await setValue('description', 'Pago con billetera')
    await setValue('commission_percentage', '2.5')
    await submit()

    expect(harness.apiPost).toHaveBeenCalledTimes(1)
    expect(harness.apiPost).toHaveBeenCalledWith('/api/payment-methods', {
      code: 'yape',
      name: 'Yape',
      description: 'Pago con billetera',
      gateway_type: 'manual',
      commission_percentage: 2.5,
      requires_authorization: false,
      allows_change: true,
      is_active: true
    })

    const payload = harness.apiPost.mock.calls[0][1]
    expect(payload).not.toHaveProperty('gateway_config')
    expect(payload).not.toHaveProperty('is_system')

    expect(wrapper.emitted('saved')).toHaveLength(1)
    expect(wrapper.emitted('saved')[0][0]).toEqual({ id: 11, name: 'Yape' })
  })

  it('sends gateway_config on edit only when the access token is filled', async () => {
    await mountModal(systemGatewayMethod)

    await setValue('access_token', 'APP_USR-token')
    await setValue('public_key', 'APP_USR-public')
    await submit()

    expect(harness.apiPut).toHaveBeenCalledTimes(1)
    const [path, payload] = harness.apiPut.mock.calls[0]
    expect(path).toBe('/api/payment-methods/3')
    expect(payload.gateway_config).toEqual({
      access_token: 'APP_USR-token',
      public_key: 'APP_USR-public'
    })
    expect(payload.is_active).toBe(false)
    expect(payload).not.toHaveProperty('code')
    expect(payload).not.toHaveProperty('is_system')
    expect(wrapper.emitted('saved')).toHaveLength(1)
  })
})
