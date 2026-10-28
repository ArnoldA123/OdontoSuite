import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { createRouter, createMemoryHistory } from 'vue-router'
import { readFileSync } from 'node:fs'

// Shared create/edit patient form (PatientFormModal). Pins the
// consolidation contract at the DOM level: one `data-patient-form` root
// serves BOTH flows from a single template source, create and edit modes
// render the same 15 fields, the gender/status option lists actually
// populate their UiSelect dropdowns, and the long-text fields render
// through UiTextarea (never `UiInput type="textarea"`).
const harness = vi.hoisted(() => ({
  apiGet: vi.fn(),
  apiPost: vi.fn(),
  apiPut: vi.fn()
}))

vi.mock('../../../resources/js/composables/useApi', () => ({
  useApi: () => ({
    get: harness.apiGet,
    post: harness.apiPost,
    put: harness.apiPut,
    patch: async () => ({ data: {} }),
    delete: async () => ({ data: {} }),
    setToken: () => {},
    normalizeError: () => ''
  })
}))
vi.mock('../../../resources/js/composables/useEcho', () => ({
  useEcho: () => ({
    echo: { leave: () => {} },
    channel: () => {
      const channel = { listen: () => channel }
      return channel
    },
    connectionStatus: { value: 'connected' }
  })
}))
vi.mock('../../../resources/js/composables/useWebSocketNotifications', () => ({
  useWebSocketNotifications: () => ({})
}))

import uiComponents from '../../../resources/js/plugins/ui-components'
import { useToast } from '../../../resources/js/composables/useToast'
import Page from '../../../resources/js/modules/patients/PatientsPage.vue'
import pageSource from '../../../resources/js/modules/patients/PatientsPage.vue?raw'

const patient = {
  id: 7,
  first_name: 'Ana',
  last_name: 'Torres',
  document_number: '45678912',
  phone: '999888777',
  email: 'ana@test.com',
  birth_date: '1990-04-12',
  age: 36,
  is_active: true
}

const defaultMeta = {
  current_page: 1,
  last_page: 1,
  per_page: 15,
  total: 1,
  active_count: 1,
  inactive_count: 0
}

// The shared form MUST render these fields, in this order, in BOTH modes.
const EXPECTED_FIELDS = [
  'first_name',
  'last_name',
  'document_number',
  'email',
  'phone',
  'birth_date',
  'gender',
  'is_active',
  'branch_id',
  'address',
  'emergency_contact_name',
  'emergency_contact_phone',
  'medical_history',
  'allergies',
  'notes'
]

const mountPage = async () => {
  const router = createRouter({
    history: createMemoryHistory(),
    routes: [{ path: '/:pathMatch(.*)*', component: { template: '<div />' } }]
  })
  const wrapper = mount(Page, { global: { plugins: [router, uiComponents] } })
  await flushPromises()
  return wrapper
}

const formRoot = () => document.querySelector('[data-patient-form]')

const formFields = () =>
  Array.from(formRoot().querySelectorAll('[data-patient-field]')).map(el =>
    el.getAttribute('data-patient-field')
  )

const field = name => formRoot().querySelector(`[data-patient-field="${name}"]`)

const formButtons = () => Array.from(formRoot().querySelectorAll('button'))

const openCreate = async wrapper => {
  await wrapper.get('[data-new-patient-button]').trigger('click')
  await flushPromises()
}

const openEdit = async wrapper => {
  const edit = wrapper.findAll('button').find(button => button.text().trim() === 'Editar')
  expect(edit).toBeTruthy()
  await edit.trigger('click')
  await flushPromises()
}

const closeForm = async () => {
  const cancel = formButtons().find(button => button.textContent.trim() === 'Cancelar')
  expect(cancel).toBeTruthy()
  cancel.click()
  await flushPromises()
}

describe('shared patient form (one source for both flows)', () => {
  beforeEach(() => {
    localStorage.setItem('user', JSON.stringify({ id: 1, role: 'administrador' }))
    harness.apiGet.mockReset()
    harness.apiGet.mockImplementation(async url => {
      if (url.includes('/api/branches/active')) {
        return { data: [] }
      }
      return { data: [patient], meta: defaultMeta }
    })
  })

  afterEach(() => {
    localStorage.clear()
    document.body.innerHTML = ''
  })

  it('renders exactly one data-patient-form root in create mode and one in edit mode', async () => {
    const wrapper = await mountPage()

    await openCreate(wrapper)
    expect(document.querySelectorAll('[data-patient-form]')).toHaveLength(1)

    await closeForm()
    expect(formRoot()).toBeNull()

    await openEdit(wrapper)
    expect(document.querySelectorAll('[data-patient-form]')).toHaveLength(1)

    wrapper.unmount()
  })

  it('renders both flows from the single PatientFormModal source', async () => {
    const wrapper = await mountPage()

    expect(pageSource).toContain("import PatientFormModal from './PatientFormModal.vue'")
    expect(pageSource.match(/<PatientFormModal\b/g) || []).toHaveLength(1)

    const formSource = readFileSync('resources/js/modules/patients/PatientFormModal.vue', 'utf8')
    expect(formSource.match(/data-patient-form/g) || []).toHaveLength(1)

    await openCreate(wrapper)
    expect(formRoot().textContent).toContain('Crear Paciente')
    expect(document.body.textContent).toContain('Nuevo Paciente')
    await closeForm()

    await openEdit(wrapper)
    expect(formRoot().textContent).toContain('Actualizar Paciente')
    expect(document.body.textContent).toContain('Editar Paciente')

    wrapper.unmount()
  })

  it('starts blank in create mode and prefills the patient payload in edit mode', async () => {
    const wrapper = await mountPage()

    await openCreate(wrapper)
    expect(field('first_name').querySelector('input').value).toBe('')
    expect(field('email').querySelector('input').value).toBe('')
    await closeForm()

    await openEdit(wrapper)
    expect(field('first_name').querySelector('input').value).toBe('Ana')
    expect(field('email').querySelector('input').value).toBe('ana@test.com')

    wrapper.unmount()
  })
})

describe('patient form field parity between modes', () => {
  beforeEach(() => {
    localStorage.setItem('user', JSON.stringify({ id: 1, role: 'administrador' }))
    harness.apiGet.mockReset()
    harness.apiGet.mockImplementation(async url => {
      if (url.includes('/api/branches/active')) {
        return { data: [] }
      }
      return { data: [patient], meta: defaultMeta }
    })
  })

  afterEach(() => {
    localStorage.clear()
    document.body.innerHTML = ''
  })

  it('renders the same 15 fields in create and edit', async () => {
    const wrapper = await mountPage()

    await openCreate(wrapper)
    const createFields = formFields()
    await closeForm()

    await openEdit(wrapper)
    const editFields = formFields()

    expect(createFields).toEqual(EXPECTED_FIELDS)
    expect(editFields).toEqual(EXPECTED_FIELDS)
    expect(editFields).toEqual(createFields)

    wrapper.unmount()
  })
})

describe('patient form option lists populate their selects', () => {
  beforeEach(() => {
    localStorage.setItem('user', JSON.stringify({ id: 1, role: 'administrador' }))
    harness.apiGet.mockReset()
    harness.apiGet.mockImplementation(async url => {
      if (url.includes('/api/branches/active')) {
        return { data: [] }
      }
      return { data: [patient], meta: defaultMeta }
    })
  })

  afterEach(() => {
    localStorage.clear()
    document.body.innerHTML = ''
  })

  it('fills the gender select with the gender options in create mode', async () => {
    const wrapper = await mountPage()

    await openCreate(wrapper)
    const genderField = field('gender')
    expect(genderField).toBeTruthy()

    genderField.querySelector('button').click()
    await flushPromises()

    const labels = Array.from(genderField.querySelectorAll('button')).map(button =>
      button.textContent.trim()
    )
    expect(labels).toEqual(expect.arrayContaining(['Masculino', 'Femenino', 'Otro']))

    wrapper.unmount()
  })

  it('fills the status select with Activo/Inactivo in edit mode', async () => {
    const wrapper = await mountPage()

    await openEdit(wrapper)
    const statusField = field('is_active')
    expect(statusField).toBeTruthy()

    statusField.querySelector('button').click()
    await flushPromises()

    const labels = Array.from(statusField.querySelectorAll('button')).map(button =>
      button.textContent.trim()
    )
    expect(labels).toEqual(expect.arrayContaining(['Activo', 'Inactivo']))

    wrapper.unmount()
  })
})

describe('patient form long-text fields', () => {
  beforeEach(() => {
    localStorage.setItem('user', JSON.stringify({ id: 1, role: 'administrador' }))
    harness.apiGet.mockReset()
    harness.apiGet.mockImplementation(async url => {
      if (url.includes('/api/branches/active')) {
        return { data: [] }
      }
      return { data: [patient], meta: defaultMeta }
    })
  })

  afterEach(() => {
    localStorage.clear()
    document.body.innerHTML = ''
  })

  it('renders medical_history, allergies and notes as UiTextarea controls', async () => {
    const wrapper = await mountPage()

    await openCreate(wrapper)
    for (const name of ['medical_history', 'allergies', 'notes']) {
      const longTextField = field(name)
      expect(longTextField).toBeTruthy()
      expect(longTextField.querySelector('textarea')).not.toBeNull()
      expect(longTextField.querySelector('input')).toBeNull()
    }
    expect(formRoot().querySelectorAll('textarea')).toHaveLength(3)

    wrapper.unmount()
  })

  it('never uses UiInput type="textarea" for the long-text fields', async () => {
    const formSource = readFileSync('resources/js/modules/patients/PatientFormModal.vue', 'utf8')

    expect(formSource).not.toContain('type="textarea"')
    expect(formSource.match(/<UiTextarea\b/g) || []).toHaveLength(3)
  })
})

// #403 — a rejected create paints its reason on the field and in the
// application's language; the empty submit uses the same channel instead of the
// browser's English bubble; the success toast does not print `null`.
const setFieldValue = (name, value) => {
  const input = field(name).querySelector('input')
  input.value = value
  input.dispatchEvent(new Event('input'))
}

const submitForm = async () => {
  const submit = formButtons().find(button => button.getAttribute('type') === 'submit')
  expect(submit).toBeTruthy()
  submit.click()
  await flushPromises()
}

describe('patient create feedback (#403)', () => {
  const { toasts } = useToast()

  beforeEach(() => {
    toasts.value = []
    localStorage.setItem('user', JSON.stringify({ id: 1, role: 'administrador' }))
    harness.apiGet.mockReset()
    harness.apiPost.mockReset()
    harness.apiPost.mockResolvedValue({ data: { id: 99 } })
    harness.apiGet.mockImplementation(async url => {
      if (url.includes('/api/branches/active')) return { data: [] }
      return { data: [patient], meta: defaultMeta }
    })
  })

  afterEach(() => {
    localStorage.clear()
    document.body.innerHTML = ''
  })

  it('paints the required fields inline instead of letting the browser block the submit', async () => {
    const wrapper = await mountPage()
    await openCreate(wrapper)

    expect(formRoot().hasAttribute('novalidate')).toBe(true)

    await submitForm()

    expect(harness.apiPost).not.toHaveBeenCalled()
    expect(field('first_name').querySelector('[aria-invalid="true"]')).toBeTruthy()
    expect(field('last_name').querySelector('[aria-invalid="true"]')).toBeTruthy()
    expect(field('first_name').textContent).toContain('El nombre es obligatorio.')
    expect(field('last_name').textContent).toContain('El apellido es obligatorio.')

    wrapper.unmount()
  })

  it('paints the duplicate document 422 on its field, in Spanish', async () => {
    harness.apiPost.mockRejectedValue({
      response: {
        status: 422,
        data: {
          message: 'Los datos proporcionados no son válidos.',
          errors: {
            document_number: ['El campo número de documento ya ha sido tomado.']
          }
        }
      }
    })

    const wrapper = await mountPage()
    await openCreate(wrapper)
    setFieldValue('first_name', 'Luis')
    setFieldValue('last_name', 'Ramos')
    setFieldValue('document_number', '45678912')
    await flushPromises()

    await submitForm()

    expect(harness.apiPost).toHaveBeenCalledTimes(1)
    expect(field('document_number').querySelector('[aria-invalid="true"]')).toBeTruthy()
    expect(field('document_number').textContent).toContain(
      'El campo número de documento ya ha sido tomado.'
    )
    expect(formRoot()).not.toBeNull()

    wrapper.unmount()
  })

  it('paints the future birth date 422 on its field, in Spanish', async () => {
    harness.apiPost.mockRejectedValue({
      response: {
        status: 422,
        data: {
          message: 'Los datos proporcionados no son válidos.',
          errors: {
            birth_date: ['La fecha de nacimiento debe ser anterior a hoy.']
          }
        }
      }
    })

    const wrapper = await mountPage()
    await openCreate(wrapper)
    setFieldValue('first_name', 'Luis')
    setFieldValue('last_name', 'Ramos')
    setFieldValue('birth_date', '2099-01-01')
    await flushPromises()

    await submitForm()

    expect(harness.apiPost).toHaveBeenCalledTimes(1)
    expect(field('birth_date').querySelector('[aria-invalid="true"]')).toBeTruthy()
    expect(field('birth_date').textContent).toContain(
      'La fecha de nacimiento debe ser anterior a hoy.'
    )

    wrapper.unmount()
  })

  it('confirms the create with the data the record actually has', async () => {
    harness.apiPost.mockResolvedValue({
      data: {
        id: 99,
        first_name: 'Ana',
        last_name: 'Luz',
        email: null,
        phone: null,
        is_active: true
      }
    })

    const wrapper = await mountPage()
    await openCreate(wrapper)
    setFieldValue('first_name', 'Ana')
    setFieldValue('last_name', 'Luz')
    await flushPromises()

    await submitForm()

    const message = toasts.value.map(toast => toast.message).join('\n')
    expect(message).toContain('Paciente registrado: Ana Luz')
    expect(message).not.toContain('null')
    expect(message).not.toContain('Email: null')

    wrapper.unmount()
  })
})
