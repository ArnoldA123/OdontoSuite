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
import SpecialtyFormModal from '../../../resources/js/modules/specialties/SpecialtyFormModal.vue'

const existingSpecialty = {
  id: 2,
  code: 'ortodoncia',
  name: 'Ortodoncia',
  description: 'Brackets, alineadores y retención.',
  is_active: false,
  procedures_count: 4,
  professionals_count: 1
}

let wrapper = null

const mountModal = async (specialty = null) => {
  wrapper = mount(SpecialtyFormModal, {
    props: { specialty },
    global: { plugins: [uiComponents] },
    attachTo: document.body
  })
  await flushPromises()
  return wrapper
}

const root = () => document.querySelector('[data-specialty-form]')
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
  vi.clearAllMocks()
  harness.apiPost.mockResolvedValue({
    data: { id: 10, code: 'cirugia_oral', name: 'Cirugía oral' },
    meta: {}
  })
  harness.apiPut.mockResolvedValue({ data: { ...existingSpecialty, name: 'Ortodoncia' }, meta: {} })
})

afterEach(() => {
  wrapper?.unmount()
  wrapper = null
  document.body.innerHTML = ''
})

describe('SpecialtyFormModal', () => {
  it('renders the create form with an active default status and the delete contract', async () => {
    await mountModal()

    expect(dialogText()).toContain('Nueva especialidad')
    expect(root().getAttribute('data-specialty-form')).toBe('create')

    expect(inputOf('code').disabled).toBe(false)
    expect(inputOf('code').placeholder).toBe('ej. cirugia_oral')
    expect(field('code').textContent).toContain('Identificador corto en minúsculas')

    expect(field('status').querySelector('select')).toBeNull()
    expect(selectTrigger('status').textContent.trim()).toBe('Activa')
    expect(document.querySelector('[data-delete-contract]').textContent).toContain(
      'Las especialidades no se eliminan'
    )
  })

  it('rejects an empty form without calling the API', async () => {
    await mountModal()

    await submit()

    expect(harness.apiPost).not.toHaveBeenCalled()
    expect(field('code').textContent).toContain('Ingresa el código de la especialidad.')
    expect(field('name').textContent).toContain('Ingresa el nombre de la especialidad.')
  })

  it('rejects a code outside the lowercase snake format', async () => {
    await mountModal()

    await setValue('code', 'Cirugía Oral')
    await setValue('name', 'Cirugía oral')
    await submit()

    expect(harness.apiPost).not.toHaveBeenCalled()
    expect(field('code').textContent).toContain(
      'Usa minúsculas, números y guiones bajos. Por ejemplo: cirugia_oral.'
    )
  })

  it('creates a specialty sending the code only on create', async () => {
    await mountModal()

    await setValue('code', 'cirugia_oral')
    await setValue('name', 'Cirugía oral')
    await submit()

    expect(harness.apiPost).toHaveBeenCalledWith('/api/specialties', {
      code: 'cirugia_oral',
      name: 'Cirugía oral',
      description: null,
      is_active: true
    })
    expect(wrapper.emitted('saved')).toBeTruthy()
    expect(wrapper.emitted('saved')[0][0]).toMatchObject({ id: 10, code: 'cirugia_oral' })
  })

  it('prefills the edit form, locks the code and omits it from the payload', async () => {
    await mountModal(existingSpecialty)

    expect(dialogText()).toContain('Editar especialidad')
    expect(root().getAttribute('data-specialty-form')).toBe('edit')
    expect(inputOf('code').value).toBe('ortodoncia')
    expect(inputOf('code').disabled).toBe(true)
    expect(field('code').textContent).toContain('El código no puede modificarse')
    expect(inputOf('name').value).toBe('Ortodoncia')
    expect(selectTrigger('status').textContent.trim()).toBe('Inactiva')
    expect(document.querySelector('[data-usage-summary]').textContent).toContain(
      '4 procedimientos · 1 profesional'
    )

    await submit()

    expect(harness.apiPut).toHaveBeenCalledWith('/api/specialties/2', {
      name: 'Ortodoncia',
      description: 'Brackets, alineadores y retención.',
      is_active: false
    })
    expect(harness.apiPost).not.toHaveBeenCalled()
  })

  it('maps server validation errors to the field that caused them', async () => {
    harness.apiPost.mockRejectedValue({
      response: { data: { errors: { code: ['The code has already been taken.'] } } }
    })
    await mountModal()

    await setValue('code', 'general')
    await setValue('name', 'Odontología general')
    await submit()

    expect(field('code').textContent).toContain('The code has already been taken.')
    expect(wrapper.emitted('saved')).toBeFalsy()
  })
})
