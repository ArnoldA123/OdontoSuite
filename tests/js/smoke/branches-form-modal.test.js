import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { createRouter, createMemoryHistory } from 'vue-router'

// Branch form modal contract: grouped fieldsets (identification / location /
// contact / operation / description), the immutable code on edit, the string
// bridge for the boolean is_active UiSelect, client-side validation, the
// server 422 error mapping and the create/update payload shape.
const { postMock, putMock } = vi.hoisted(() => ({
  postMock: vi.fn(),
  putMock: vi.fn()
}))

vi.mock('../../../resources/js/composables/useApi', () => ({
  useApi: () => ({
    get: vi.fn(async () => ({ data: [] })),
    post: postMock,
    put: putMock,
    patch: vi.fn(),
    delete: vi.fn(),
    setToken: () => {},
    normalizeError: () => ''
  })
}))
import uiComponents from '../../../resources/js/plugins/ui-components'
import BranchFormModal from '../../../resources/js/modules/settings/branches/BranchFormModal.vue'

const BRANCH = {
  id: 1,
  code: 'SC-LIM-01',
  name: 'Sede Central Lima',
  address: 'Av. Arequipa 123',
  city: 'Lima',
  state: 'Lima',
  country: 'Perú',
  postal_code: '15046',
  phone: '+51 1 426-0001',
  email: 'central@odontosuite.pe',
  timezone: 'America/Lima',
  description: 'Sede principal de la clínica.',
  is_active: true
}

const mountModal = async (branch = null) => {
  const router = createRouter({
    history: createMemoryHistory(),
    routes: [{ path: '/:pathMatch(.*)*', component: { template: '<div />' } }]
  })
  await router.push('/settings/branches')
  await router.isReady()
  const wrapper = mount(BranchFormModal, {
    props: { branch },
    global: { plugins: [router, uiComponents] }
  })
  await flushPromises()
  return wrapper
}

const field = name => document.querySelector(`[data-field="${name}"]`)

const setField = async (name, value) => {
  const element = field(name).querySelector('input, textarea')
  expect(element, `field "${name}" must render an input`).toBeTruthy()
  element.value = value
  element.dispatchEvent(new Event('input', { bubbles: true }))
  await flushPromises()
}

const chooseStatus = async label => {
  const trigger = field('is_active').querySelector('button[aria-haspopup="true"]')
  expect(trigger).toBeTruthy()
  trigger.click()
  await flushPromises()
  const option = [...field('is_active').querySelectorAll('[role="listbox"] button')].find(
    button => button.textContent.trim() === label
  )
  expect(option, `status option "${label}" must exist`).toBeTruthy()
  option.click()
  await flushPromises()
}

const submit = async () => {
  const button = document.querySelector('[data-modal-submit]')
  expect(button).toBeTruthy()
  button.click()
  await flushPromises()
}

describe('branch form modal smoke', () => {
  beforeEach(() => {
    document.body.innerHTML = ''
    postMock.mockReset()
    putMock.mockReset()
    postMock.mockImplementation(async (url, payload) => ({ data: { id: 9, ...payload } }))
    putMock.mockImplementation(async (url, payload) => ({ data: { ...BRANCH, ...payload } }))
  })

  it('creates a branch with the full payload and keeps the code editable', async () => {
    const wrapper = await mountModal()

    expect(document.body.textContent).toContain('Nueva sucursal')
    expect(field('code').querySelector('input').disabled).toBe(false)

    await setField('code', 'SC-NEW-01')
    await setField('name', 'Nueva sede')
    await setField('city', 'Lima')
    await setField('phone', '+51 1 555-0000')
    await setField('email', 'nueva@odontosuite.pe')
    await submit()

    expect(postMock).toHaveBeenCalledTimes(1)
    const [url, payload] = postMock.mock.calls[0]
    expect(url).toBe('/api/branches')
    expect(payload.code).toBe('SC-NEW-01')
    expect(payload.name).toBe('Nueva sede')
    expect(payload.city).toBe('Lima')
    expect(payload.phone).toBe('+51 1 555-0000')
    expect(payload.timezone).toBe('America/Lima')
    expect(payload.is_active).toBe(true)
    expect(wrapper.emitted('saved')).toBeTruthy()

    wrapper.unmount()
  })

  it('updates a branch prefilled from the record without sending the immutable code', async () => {
    const wrapper = await mountModal(BRANCH)

    expect(document.body.textContent).toContain('Editar sucursal')
    expect(field('code').querySelector('input').disabled).toBe(true)
    expect(field('name').querySelector('input').value).toBe('Sede Central Lima')
    expect(field('city').querySelector('input').value).toBe('Lima')
    expect(field('postal_code').querySelector('input').value).toBe('15046')
    expect(field('timezone').querySelector('input').value).toBe('America/Lima')

    await setField('name', 'Sede Central Lima Renovada')
    await chooseStatus('Inactiva')
    await submit()

    expect(putMock).toHaveBeenCalledTimes(1)
    const [url, payload] = putMock.mock.calls[0]
    expect(url).toBe('/api/branches/1')
    expect(payload.name).toBe('Sede Central Lima Renovada')
    expect(payload.is_active).toBe(false)
    expect('code' in payload).toBe(false)
    expect(wrapper.emitted('saved')).toBeTruthy()

    wrapper.unmount()
  })

  it('blocks submission when the required fields are empty', async () => {
    await mountModal()

    await submit()

    expect(postMock).not.toHaveBeenCalled()
    expect(field('code').textContent).toContain('Requerido')
    expect(field('name').textContent).toContain('Requerido')
    expect(field('city').textContent).toContain('Requerido')
  })

  it('rejects an invalid email before reaching the API', async () => {
    await mountModal()

    await setField('code', 'SC-NEW-02')
    await setField('name', 'Sede Norte')
    await setField('city', 'Lima')
    await setField('email', 'no-es-email')
    await submit()

    expect(postMock).not.toHaveBeenCalled()
    expect(field('email').textContent).toContain('Email inválido')
  })

  it('rejects values longer than the API contract allows', async () => {
    await mountModal()

    await setField('code', 'CODIGO-DEMASIADO-LARGO')
    await setField('name', 'Sede Norte')
    await setField('city', 'Lima')
    await setField('postal_code', '12345678901')
    await submit()

    expect(postMock).not.toHaveBeenCalled()
    expect(field('code').textContent).toContain('Máximo 10 caracteres')
    expect(field('postal_code').textContent).toContain('Máximo 10 caracteres')
  })

  it('maps the server 422 field errors onto the matching inputs', async () => {
    putMock.mockRejectedValueOnce({
      response: { data: { errors: { name: ['El nombre ya está registrado.'] } } }
    })
    const wrapper = await mountModal(BRANCH)

    await submit()

    expect(putMock).toHaveBeenCalledTimes(1)
    expect(field('name').textContent).toContain('El nombre ya está registrado.')
    expect(wrapper.emitted('saved')).toBeFalsy()

    wrapper.unmount()
  })
})
