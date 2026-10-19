import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { createRouter, createMemoryHistory } from 'vue-router'

// Appointment-types CRUD contract (2026-10-19 audit). Every rule here pins an
// observable behavior of the page against the API contract:
//  - a delete refused with 409 shows the server message (the type still has
//    appointments), never a generic "could not delete" toast;
//  - a null price renders "No especificado", never `S/ 0.00`;
//  - the modal footers submit their <form> (native required validation);
//  - the create payload carries the three catalog flags.
const { getMock, postMock, putMock, deleteMock, makeType } = vi.hoisted(() => {
  const makeType = (id, overrides = {}) => ({
    id,
    name: `Tipo ${id}`,
    description: `Descripción del tipo ${id}`,
    default_duration_minutes: 30,
    price: 100,
    color: '#3B82F6',
    requires_confirmation: false,
    requires_materials: false,
    is_consultation_mode: false,
    is_active: true,
    ...overrides
  })

  return {
    getMock: vi.fn(async () => ({ data: [] })),
    postMock: vi.fn(async () => ({ data: {} })),
    putMock: vi.fn(async () => ({ data: {} })),
    deleteMock: vi.fn(async () => ({ data: {} })),
    makeType
  }
})

vi.mock('../../../resources/js/composables/useApi', () => ({
  useApi: () => ({
    get: getMock,
    post: postMock,
    put: putMock,
    patch: async () => ({ data: {} }),
    delete: deleteMock,
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
import Page from '../../../resources/js/modules/appointment-types/AppointmentTypesPage.vue'
import { globalToasts } from '../../../resources/js/composables/useToast'
import { colors } from '../../../resources/js/design-system/tokens.js'

const router = createRouter({
  history: createMemoryHistory(),
  routes: [{ path: '/:pathMatch(.*)*', component: { template: '<div />' } }]
})

const mountPage = async (catalog = []) => {
  getMock.mockImplementation(async url => {
    if (String(url).startsWith('/api/appointment-types')) return { data: catalog }
    return { data: [] }
  })
  const wrapper = mount(Page, { global: { plugins: [router, uiComponents] } })
  await flushPromises()
  return wrapper
}

const confirmDeletion = async wrapper => {
  await wrapper.find('[data-row-action="delete"]').trigger('click')
  await flushPromises()
  const confirmBtn = [...document.body.querySelectorAll('button')].find(
    b => b.textContent.trim() === 'Eliminar'
  )
  expect(confirmBtn).toBeTruthy()
  confirmBtn.click()
  await flushPromises()
}

const buttonByText = text =>
  [...document.body.querySelectorAll('button')].find(b => b.textContent.trim() === text)

const openNewModal = async wrapper => {
  await wrapper.find('[data-hero-action]').trigger('click')
  await flushPromises()
}

const openEditModal = async wrapper => {
  const row = wrapper.findAll('[data-type-row]').find(r => r.text().includes('Limpieza dental'))
  await row.find('[data-row-action="edit"]').trigger('click')
  await flushPromises()
}

const flagSelect = key => {
  const container = document.body.querySelector(`[data-flag-select="${key}"]`)
  expect(container).toBeTruthy()
  return container
}

const flagLabel = key => flagSelect(key).querySelector('button').textContent.trim()

const chooseFlag = async (key, label) => {
  flagSelect(key).querySelector('button').click()
  await flushPromises()
  const option = [...flagSelect(key).querySelectorAll('button')].find(
    b => b.textContent.trim() === label
  )
  expect(option).toBeTruthy()
  option.click()
  await flushPromises()
}

const fieldByLabel = labelText => {
  const label = [...document.body.querySelectorAll('label')].find(
    l => l.textContent.trim() === labelText
  )
  expect(label).toBeTruthy()
  const input = document.getElementById(label.getAttribute('for'))
  expect(input).toBeTruthy()
  return input
}

const setField = (labelText, value) => {
  const input = fieldByLabel(labelText)
  input.value = String(value)
  input.dispatchEvent(new Event('input', { bubbles: true }))
}

describe('appointment-types CRUD contract', () => {
  beforeEach(() => {
    globalToasts.value = []
    getMock.mockReset()
    postMock.mockReset()
    postMock.mockImplementation(async () => ({ data: {} }))
    putMock.mockReset()
    putMock.mockImplementation(async () => ({ data: {} }))
    deleteMock.mockReset()
    deleteMock.mockImplementation(async () => ({ data: {} }))
  })

  afterEach(() => {
    globalToasts.value = []
  })

  it('surfaces the API message when deletion is blocked by existing appointments', async () => {
    const message =
      'No se puede eliminar el tipo de cita: tiene 1 cita asociada. Desactívalo en su lugar.'
    deleteMock.mockRejectedValueOnce({
      response: { status: 409, data: { message } },
      status: 409
    })

    const wrapper = await mountPage([makeType(1, { name: 'Limpieza dental' })])
    await confirmDeletion(wrapper)

    expect(deleteMock).toHaveBeenCalledTimes(1)
    expect(globalToasts.value.map(t => t.message)).toContain(message)
    expect(globalToasts.value.map(t => t.message)).not.toContain(
      'Error al eliminar el tipo de cita'
    )
    wrapper.unmount()
  })

  it('renders a null price as No especificado in the list cell and its aria-label', async () => {
    const wrapper = await mountPage([
      makeType(1, { name: 'Limpieza dental', price: null }),
      makeType(2, { name: 'Ortodoncia', price: 150 })
    ])

    const cell = wrapper.find('[data-type-row] [aria-label^="Precio"]')
    expect(cell.text()).toBe('No especificado')
    expect(cell.attributes('aria-label')).toBe('Precio No especificado')

    const priced = wrapper.findAll('[data-type-row]')[1].find('[aria-label^="Precio"]')
    expect(priced.text()).toMatch(/S\/\s*150\.00/)
    expect(priced.attributes('aria-label')).toMatch(/Precio S\/\s*150\.00/)
    wrapper.unmount()
  })

  it('exposes the three catalog flags in the create modal and starts confirmation on', async () => {
    const wrapper = await mountPage([makeType(1, { name: 'Limpieza dental' })])
    await openNewModal(wrapper)

    expect(document.body.textContent).toContain('Requiere confirmación')
    expect(document.body.textContent).toContain('Requiere materiales')
    expect(document.body.textContent).toContain('Modo consulta')
    expect(flagLabel('requires_confirmation')).toBe('Sí')
    expect(flagLabel('requires_materials')).toBe('No')
    expect(flagLabel('is_consultation_mode')).toBe('No')

    await chooseFlag('requires_confirmation', 'Sí')
    await chooseFlag('requires_materials', 'Sí')
    await chooseFlag('is_consultation_mode', 'Sí')
    setField('Nombre del Tipo', 'Control de ortodoncia')
    setField('Duración (minutos)', '45')
    buttonByText('Crear').click()
    await flushPromises()

    expect(postMock).toHaveBeenCalledTimes(1)
    expect(postMock.mock.calls[0][1]).toMatchObject({
      requires_confirmation: true,
      requires_materials: true,
      is_consultation_mode: true,
      is_active: true
    })
    wrapper.unmount()
  })

  it('exposes the three catalog flags in the edit modal and saves them', async () => {
    const wrapper = await mountPage([
      makeType(1, {
        name: 'Limpieza dental',
        requires_confirmation: true,
        requires_materials: false,
        is_consultation_mode: true
      })
    ])
    await openEditModal(wrapper)

    expect(flagLabel('requires_confirmation')).toBe('Sí')
    expect(flagLabel('requires_materials')).toBe('No')
    expect(flagLabel('is_consultation_mode')).toBe('Sí')

    await chooseFlag('requires_materials', 'Sí')
    buttonByText('Actualizar').click()
    await flushPromises()

    expect(putMock).toHaveBeenCalledTimes(1)
    expect(putMock.mock.calls[0][1]).toMatchObject({
      requires_confirmation: true,
      requires_materials: true,
      is_consultation_mode: true
    })
    wrapper.unmount()
  })

  it('submits the create form natively from the modal footer button', async () => {
    const wrapper = await mountPage([makeType(1, { name: 'Limpieza dental' })])
    await openNewModal(wrapper)

    const createButton = buttonByText('Crear')
    expect(createButton.getAttribute('type')).toBe('submit')
    expect(createButton.getAttribute('form')).toBe('form-new-type')
    const form = document.getElementById('form-new-type')
    expect(createButton.form).toBe(form)

    // Cancel only the native submit activation: a footer @click would still
    // run the create handler, so no post proves the button has no handler.
    const preventActivation = event => event.preventDefault()
    document.addEventListener('click', preventActivation, true)
    createButton.click()
    await flushPromises()
    document.removeEventListener('click', preventActivation, true)
    expect(postMock).not.toHaveBeenCalled()

    setField('Nombre del Tipo', 'Control de ortodoncia')
    setField('Duración (minutos)', '30')
    form.dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }))
    await flushPromises()
    expect(postMock).toHaveBeenCalledTimes(1)
    wrapper.unmount()
  })

  it('puts the native constraints on the real input, not on the wrapper', async () => {
    const wrapper = await mountPage([makeType(1, { name: 'Limpieza dental' })])
    await openNewModal(wrapper)

    const nameInput = fieldByLabel('Nombre del Tipo')
    const durationInput = fieldByLabel('Duración (minutos)')

    expect(nameInput.required).toBe(true)
    expect(durationInput.required).toBe(true)
    expect(durationInput.getAttribute('min')).toBe('15')
    expect(durationInput.getAttribute('max')).toBe('480')
    expect(durationInput.getAttribute('step')).toBe('15')
    wrapper.unmount()
  })

  it('normalizes an invalid stored color before saving the edit form', async () => {
    const wrapper = await mountPage([makeType(1, { name: 'Limpieza dental', color: 'steelblue' })])
    await openEditModal(wrapper)

    buttonByText('Actualizar').click()
    await flushPromises()

    expect(putMock).toHaveBeenCalledTimes(1)
    expect(putMock.mock.calls[0][1].color).toBe(colors.accent[500])
    expect(putMock.mock.calls[0][1].color).toMatch(/^#[0-9A-Fa-f]{6}$/)
    wrapper.unmount()
  })
})
