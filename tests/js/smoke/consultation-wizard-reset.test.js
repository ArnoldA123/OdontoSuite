import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'

// #311: /my-day and /calendar mount the wizard permanently and only toggle its
// visibility through `useConsultation().openForAppointment()` / `close()`, so
// the component is never re-created between one act and the next. The payload
// is local state: a second act must not inherit the clinical content already
// documented for another patient.
const harness = vi.hoisted(() => ({
  apiGet: vi.fn(),
  fetch: vi.fn()
}))

vi.mock('../../../resources/js/composables/useApi', () => ({
  useApi: () => ({
    get: harness.apiGet,
    post: vi.fn(async () => ({ data: {} })),
    put: vi.fn(async () => ({ data: {} })),
    patch: vi.fn(async () => ({ data: {} })),
    del: vi.fn(async () => ({ data: {} })),
    setToken: () => {},
    normalizeError: () => ''
  })
}))

import { useConsultation } from '../../../resources/js/composables/useConsultation'
import ConsultationWizard from '../../../resources/js/modules/appointments/ConsultationWizard.vue'

const APPOINTMENT_A = {
  id: 501,
  status: 'in_consultation',
  scheduled_at: '2026-10-24T10:00:00',
  patient: { id: 7, first_name: 'Ana', last_name: 'Pérez' },
  user: { id: 2, name: 'Dra. Ruiz' }
}

const APPOINTMENT_B = {
  id: 502,
  status: 'in_consultation',
  scheduled_at: '2026-10-24T12:00:00',
  patient: { id: 8, first_name: 'Luis', last_name: 'Torres' },
  user: { id: 2, name: 'Dra. Ruiz' }
}

const CATALOG = [{ id: 11, cie_code: 'K02.1', name: 'Caries de la dentina' }]

const PASSWORD = 'secreto-clinico-2026'
const REASON = 'Me duele la muela desde hace tres días'

const element = selector => {
  const found = document.querySelector(selector)
  expect(found, `${selector} must render`).toBeTruthy()
  return found
}

const setValue = async (selector, value) => {
  const target = element(selector)
  target.value = value
  target.dispatchEvent(new Event('input', { bubbles: true }))
  await flushPromises()
}

const buttonWithText = text =>
  Array.from(document.querySelectorAll('button')).find(button => button.textContent.includes(text))

const clickButton = async text => {
  const button = buttonWithText(text)
  expect(button, `a button labelled "${text}" must render`).toBeTruthy()
  button.click()
  await flushPromises()
}

const goToStep = async id => {
  element(`#tab-${id}`).click()
  await flushPromises()
}

let activeWrapper = null

const mountWizard = async appointment => {
  const wrapper = mount(ConsultationWizard, {
    props: { appointment },
    attachTo: document.body
  })
  activeWrapper = wrapper
  // `isOpen` lives in the composable's singleton, so opening it is what makes
  // the Teleport render the dialog.
  await useConsultation().openForAppointment(appointment)
  await flushPromises()
  return wrapper
}

const fillSoap = async () => {
  await setValue('#cw-soap-s', 'Dolor al masticar')
  await setValue('#cw-soap-o', 'Caries oclusal en pieza 26')
  await setValue('#cw-soap-a', 'Caries de la dentina')
  await setValue('#cw-soap-p', 'Restauración con resina')
}

// Documents a full act: mode, plan title, reason, SOAP, one coded diagnosis,
// one plan item, one material, next appointment and the signature.
const documentAct = async () => {
  await goToStep('mode')
  await clickButton('Ejecutar procedimiento')
  await setValue('#cw-plan-title', 'Rehabilitación cuadrante 3')

  await goToStep('evolution')
  await setValue('#cw-reason', REASON)
  await fillSoap()
  await setValue('#cw-diagnosis-search', 'caries')
  element('[data-diagnosis-option]').click()
  await flushPromises()

  await goToStep('procedures')
  await clickButton('Agregar procedimiento')
  expect(element('input[placeholder="Especialidad"]')).toBeTruthy()

  await goToStep('materials')
  await clickButton('Agregar material')
  expect(element('input[placeholder="Buscar producto por nombre o código"]')).toBeTruthy()

  await goToStep('next')
  await setValue('#cw-next-date', '2026-11-02T09:00')
  await setValue('#cw-next-notes', 'Control de la restauración')

  await goToStep('signature')
  await setValue('#cw-signature', PASSWORD)
}

// The two pages clear `wizardAppointment` on close, so the next opening swaps
// the prop from null to the new appointment.
const openNextAppointment = async (wrapper, appointment) => {
  await wrapper.setProps({ appointment: null })
  await flushPromises()
  await wrapper.setProps({ appointment })
  await useConsultation().openForAppointment(appointment)
  await flushPromises()
}

const expectEmptyAct = async () => {
  // A fresh act starts on the first step: the evolution fields do not even
  // exist in the dialog yet.
  expect(document.querySelector('#cw-reason')).toBeFalsy()

  await goToStep('evolution')
  expect(element('#cw-reason').value).toBe('')
  expect(element('#cw-soap-s').value).toBe('')
  expect(element('#cw-soap-o').value).toBe('')
  expect(element('#cw-soap-a').value).toBe('')
  expect(element('#cw-soap-p').value).toBe('')

  await goToStep('mode')
  await clickButton('Ejecutar procedimiento')
  expect(element('#cw-plan-title').value).toBe('')

  await goToStep('procedures')
  expect(document.querySelector('input[placeholder="Especialidad"]')).toBeFalsy()

  await goToStep('materials')
  expect(
    document.querySelector('input[placeholder="Buscar producto por nombre o código"]')
  ).toBeFalsy()

  await goToStep('next')
  expect(element('#cw-next-date').value).toBe('')
  expect(element('#cw-next-notes').value).toBe('')

  await goToStep('signature')
  expect(element('#cw-signature').value).toBe('')
}

describe('consultation wizard reset between acts (#311)', () => {
  beforeEach(() => {
    document.body.innerHTML = ''
    localStorage.clear()
    harness.apiGet.mockReset()
    harness.fetch.mockReset()
    harness.apiGet.mockImplementation(async url => {
      if (url === '/api/diagnoses') return { data: CATALOG, meta: { total: CATALOG.length } }
      if (url.endsWith('/consultation-context')) {
        return { data: { requires_materials: false, appointment_type: { name: 'Consulta' } } }
      }
      return { data: [] }
    })
    harness.fetch.mockResolvedValue({
      ok: true,
      json: async () => ({ data: { id: 900 }, meta: { quotation_generated: false } })
    })
    vi.stubGlobal('fetch', harness.fetch)
  })

  afterEach(() => {
    useConsultation().close()
    activeWrapper?.unmount()
    activeWrapper = null
    document.body.innerHTML = ''
    vi.unstubAllGlobals()
  })

  it('starts the next act empty after a signed close', async () => {
    const wrapper = await mountWizard(APPOINTMENT_A)
    await documentAct()

    const submit = buttonWithText('Completar consulta')
    expect(submit.disabled).toBe(false)
    submit.click()
    await flushPromises()

    // The single-appointment flow still closes: one request with the signed
    // payload, and the wizard reports the completion to its page.
    expect(harness.fetch).toHaveBeenCalledTimes(1)
    expect(harness.fetch.mock.calls[0][1].body.get('reason_for_consultation')).toBe(REASON)
    expect(harness.fetch.mock.calls[0][1].body.get('signature[password]')).toBe(PASSWORD)
    expect(wrapper.emitted('completed')).toBeTruthy()

    await openNextAppointment(wrapper, APPOINTMENT_B)
    await expectEmptyAct()
  })

  it('discards the draft of a cancelled act', async () => {
    const wrapper = await mountWizard(APPOINTMENT_A)

    await goToStep('evolution')
    await setValue('#cw-reason', REASON)
    await fillSoap()
    await setValue('#cw-diagnosis-search', 'caries')
    element('[data-diagnosis-option]').click()
    await flushPromises()

    await clickButton('Cancelar')
    expect(useConsultation().isOpen.value).toBe(false)

    await openNextAppointment(wrapper, APPOINTMENT_B)
    await expectEmptyAct()
  })
})
