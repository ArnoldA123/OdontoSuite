import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'

// F5 (#316) — the work order is opened from the patient's record and from the
// closure of the attention, so the form modal owns the create/edit contract:
// the preset patient is fixed instead of re-selected, the catalogues are
// filtered in the client to the two role sets the request validates, and a
// rejected field answers with the Spanish sentence the API already writes.
// Transport and toasts are mocked: the modal only consumes their contracts.
const harness = vi.hoisted(() => ({
  get: vi.fn(),
  post: vi.fn(),
  put: vi.fn(),
  toast: { success: vi.fn(), error: vi.fn(), info: vi.fn() }
}))

vi.mock('../../../resources/js/composables/useApi', () => ({
  useApi: () => ({
    get: harness.get,
    post: harness.post,
    put: harness.put,
    patch: vi.fn(),
    delete: vi.fn(),
    setToken: () => {},
    normalizeError: () => ''
  })
}))

vi.mock('../../../resources/js/composables/useToast', () => ({
  useToast: () => harness.toast,
  globalToasts: { value: [] }
}))

import uiComponents from '../../../resources/js/plugins/ui-components'
import Form from '../../../resources/js/modules/lab-cases/LabCaseFormModal.vue'

const USERS = [
  { id: 7, name: 'Dr. Luis Rojas', role: 'odontologo' },
  { id: 8, name: 'Dra. Ana Salas', role: 'implantologo' },
  { id: 9, name: 'Tec. Marco Díaz', role: 'tecnico_dental' },
  { id: 10, name: 'Asist. Rosa Vega', role: 'asistente' }
]

const PIECES = [
  { id: 4, fdi_number: '16', is_active: true },
  { id: 5, fdi_number: '26', is_active: true },
  { id: 6, fdi_number: '36', is_active: false }
]

const EXISTING = {
  id: 11,
  patient_id: 3,
  professional_id: 7,
  technician_id: 9,
  dental_piece_id: 4,
  work: 'Corona metal-cerámica',
  material: 'Zirconio',
  sent_at: '2026-10-20T00:00:00.000000Z',
  promised_at: '2026-10-24T00:00:00.000000Z',
  status: 'in_laboratory',
  notes: 'Pieza 16, ajuste oclusal',
  patient: { id: 3, first_name: 'Ana', last_name: 'Torres' }
}

const catalogGet = async url => {
  if (url === '/api/users/active') return { data: USERS }
  if (url === '/api/dental-pieces') return { data: PIECES }
  return { data: [] }
}

const node = selector => document.querySelector(selector)

const mountForm = async (props = {}) => {
  const wrapper = mount(Form, {
    props: { modelValue: true, ...props },
    global: { plugins: [uiComponents] },
    attachTo: document.body
  })
  await flushPromises()
  return wrapper
}

// UiInput/UiTextarea forward the hooks, so the native control is found inside
// the wrapper the hook is painted on.
const control = hook => node(`${hook} input, ${hook} textarea`) || node(hook)

const setValue = async (hook, value) => {
  const target = control(hook)
  target.value = value
  target.dispatchEvent(new Event('input', { bubbles: true }))
  await flushPromises()
}

const pick = async (hook, label) => {
  const select = node(hook)
  expect(select, `${hook} must render`).toBeTruthy()

  select.querySelector('button').click()
  await flushPromises()

  const option = [...document.querySelectorAll('[role="listbox"] button')].find(button =>
    button.textContent.includes(label)
  )
  expect(option, `option ${label} must render`).toBeTruthy()

  option.click()
  await flushPromises()
}

const submit = async () => {
  node('[data-lab-case-form-submit]').click()
  await flushPromises()
}

let wrapper = null

beforeEach(() => {
  document.body.innerHTML = ''
  harness.get.mockReset()
  harness.post.mockReset()
  harness.put.mockReset()
  harness.toast.success.mockReset()
  harness.toast.error.mockReset()
  harness.toast.info.mockReset()

  harness.get.mockImplementation(catalogGet)
  harness.post.mockImplementation(async () => ({
    data: { ...EXISTING, id: 21 },
    meta: { message: 'Caso de laboratorio registrado exitosamente' }
  }))
  harness.put.mockImplementation(async () => ({
    data: { ...EXISTING },
    meta: { message: 'Caso de laboratorio actualizado exitosamente' }
  }))
})

afterEach(() => {
  if (wrapper) {
    wrapper.unmount()
    wrapper = null
  }
  document.body.innerHTML = ''
})

describe('lab case form modal (#316)', () => {
  it('creates the case for the preset patient without rendering the selector', async () => {
    wrapper = await mountForm({ patientId: 7, patientName: 'Ana Torres' })

    expect(node('[data-lab-case-form-patient]')).toBeNull()
    expect(node('[data-lab-case-form-patient-fixed]').textContent).toContain('Ana Torres')

    // The two role sets the request validates are filtered in the client.
    const professional = wrapper.findComponent('[data-lab-case-form-professional]')
    expect(professional.props('options').map(option => option.label)).toEqual([
      'Sin asignar',
      'Dr. Luis Rojas',
      'Dra. Ana Salas'
    ])

    const technician = wrapper.findComponent('[data-lab-case-form-technician]')
    expect(technician.props('options').map(option => option.label)).toEqual([
      'Sin asignar',
      'Tec. Marco Díaz'
    ])

    // An inactive piece is not offered.
    const piece = wrapper.findComponent('[data-lab-case-form-piece]')
    expect(piece.props('options').map(option => option.label)).toEqual(['Sin pieza', '16', '26'])

    await setValue('[data-lab-case-form-work]', 'Corona metal-cerámica')
    await setValue('[data-lab-case-form-material]', 'Zirconio')
    await setValue('[data-lab-case-form-sent-at]', '2026-10-20')
    await setValue('[data-lab-case-form-promised-at]', '2026-10-24')
    await setValue('[data-lab-case-form-notes]', 'Pieza 16, ajuste oclusal')

    await pick('[data-lab-case-form-technician]', 'Tec. Marco Díaz')
    await pick('[data-lab-case-form-piece]', '16')

    await submit()

    expect(harness.post).toHaveBeenCalledTimes(1)
    const [url, body] = harness.post.mock.calls[0]
    expect(url).toBe('/api/lab-cases')
    expect(body).toMatchObject({
      patient_id: 7,
      technician_id: 9,
      dental_piece_id: 4,
      professional_id: null,
      work: 'Corona metal-cerámica',
      material: 'Zirconio',
      sent_at: '2026-10-20',
      promised_at: '2026-10-24',
      notes: 'Pieza 16, ajuste oclusal'
    })

    expect(harness.toast.success).toHaveBeenCalledWith(
      'Caso de laboratorio registrado exitosamente'
    )
    expect(wrapper.emitted('saved')[0][0]).toMatchObject({ id: 21 })
    expect(wrapper.emitted('update:modelValue').at(-1)).toEqual([false])
  })

  it('asks for the patient when the form is not tied to one', async () => {
    wrapper = await mountForm()

    expect(node('[data-lab-case-form-patient]')).toBeTruthy()
    expect(node('[data-lab-case-form-patient-fixed]')).toBeNull()

    await setValue('[data-lab-case-form-work]', 'Prótesis parcial')
    await submit()

    expect(harness.post).not.toHaveBeenCalled()
    expect(node('[data-lab-case-form-error="patient_id"]').textContent).toContain(
      'Selecciona un paciente.'
    )
  })

  it('prefills every field of the case and updates it', async () => {
    wrapper = await mountForm({ labCase: EXISTING })

    expect(node('[data-lab-case-form-patient]')).toBeNull()
    expect(node('[data-lab-case-form-patient-fixed]').textContent).toContain('Ana Torres')
    expect(control('[data-lab-case-form-work]').value).toBe('Corona metal-cerámica')
    expect(control('[data-lab-case-form-material]').value).toBe('Zirconio')
    expect(control('[data-lab-case-form-sent-at]').value).toBe('2026-10-20')
    expect(control('[data-lab-case-form-promised-at]').value).toBe('2026-10-24')
    expect(control('[data-lab-case-form-notes]').value).toBe('Pieza 16, ajuste oclusal')

    expect(wrapper.findComponent('[data-lab-case-form-professional]').props('modelValue')).toBe(7)
    expect(wrapper.findComponent('[data-lab-case-form-technician]').props('modelValue')).toBe(9)
    expect(wrapper.findComponent('[data-lab-case-form-piece]').props('modelValue')).toBe(4)

    await setValue('[data-lab-case-form-material]', 'Disilicato de litio')
    await submit()

    expect(harness.post).not.toHaveBeenCalled()
    expect(harness.put).toHaveBeenCalledTimes(1)
    const [url, body] = harness.put.mock.calls[0]
    expect(url).toBe('/api/lab-cases/11')
    expect(body).toMatchObject({
      patient_id: 3,
      professional_id: 7,
      technician_id: 9,
      dental_piece_id: 4,
      material: 'Disilicato de litio'
    })
  })

  it('prints the 422 sentence of the field and keeps the modal open', async () => {
    harness.post.mockRejectedValue({
      response: {
        data: {
          message: 'Los datos enviados no son válidos.',
          errors: { work: ['El trabajo a realizar es obligatorio.'] }
        }
      },
      status: 422
    })

    wrapper = await mountForm({ patientId: 7, patientName: 'Ana Torres' })

    await setValue('[data-lab-case-form-work]', 'Corona')
    await submit()

    expect(harness.post).toHaveBeenCalledTimes(1)
    expect(node('[data-lab-case-form-error="work"]').textContent).toContain(
      'El trabajo a realizar es obligatorio.'
    )
    expect(node('[data-lab-case-form-error="message"]')).toBeNull()
    expect(node('[data-lab-case-form]')).toBeTruthy()
    expect(wrapper.emitted('saved')).toBeFalsy()
  })

  it('blocks a promise date that precedes the sending date', async () => {
    wrapper = await mountForm({ patientId: 7, patientName: 'Ana Torres' })

    await setValue('[data-lab-case-form-work]', 'Corona metal-cerámica')
    await setValue('[data-lab-case-form-sent-at]', '2026-10-24')
    await setValue('[data-lab-case-form-promised-at]', '2026-10-20')
    await submit()

    expect(harness.post).not.toHaveBeenCalled()
    expect(node('[data-lab-case-form-error="promised_at"]').textContent).toContain(
      'La fecha prometida no puede ser anterior a la fecha de envío.'
    )
    expect(node('[data-lab-case-form]')).toBeTruthy()
  })

  it('reports a top-level failure when the answer carries no field errors', async () => {
    harness.post.mockRejectedValue({
      response: { data: { message: 'Error al registrar el caso de laboratorio' } },
      status: 500
    })

    wrapper = await mountForm({ patientId: 7, patientName: 'Ana Torres' })

    await setValue('[data-lab-case-form-work]', 'Corona metal-cerámica')
    await submit()

    expect(node('[data-lab-case-form-error="message"]').textContent).toContain(
      'Error al registrar el caso de laboratorio'
    )
  })

  it('prints the sentence of a field it cannot paint in the notice', async () => {
    harness.put.mockRejectedValue({
      response: {
        data: {
          message: 'Los datos proporcionados no son válidos.',
          errors: { status: ['El caso ya está colocado; no se puede editar.'] }
        }
      },
      status: 422
    })

    wrapper = await mountForm({ labCase: EXISTING })

    await submit()

    expect(harness.put).toHaveBeenCalledTimes(1)
    expect(node('[data-lab-case-form-error="message"]').textContent).toContain(
      'El caso ya está colocado; no se puede editar.'
    )
    expect(node('[data-lab-case-form]')).toBeTruthy()
  })
})
