import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { readFileSync } from 'node:fs'
import { dirname, resolve } from 'node:path'
import { fileURLToPath } from 'node:url'

// F6 (#286, NTS 139): the closure of the appointment now needs three members
// the SPA did not send — the patient's complaint, at least one CIE-10 coded
// diagnosis and the professional's re-entered password. Without them the door
// answers 422, so the wizard must collect all three and gate its submit on
// them.
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

const ROOT = resolve(dirname(fileURLToPath(import.meta.url)), '../../..')

const APPOINTMENT = {
  id: 501,
  status: 'in_consultation',
  scheduled_at: '2026-10-24T10:00:00',
  patient: { id: 7, first_name: 'Ana', last_name: 'Pérez' },
  user: { id: 2, name: 'Dra. Ruiz' }
}

const CATALOG = [
  { id: 11, cie_code: 'K02.1', name: 'Caries de la dentina' },
  { id: 12, cie_code: 'K05.1', name: 'Gingivitis crónica' }
]

const PASSWORD = 'secreto-clinico-2026'
const REASON = 'Me duele la muela desde hace tres días'

const consoleSpies = []

const element = selector => {
  const found = document.querySelector(selector)
  expect(found, `${selector} must render`).toBeTruthy()
  return found
}

const mountWizard = async () => {
  const wrapper = mount(ConsultationWizard, {
    props: { appointment: APPOINTMENT },
    attachTo: document.body
  })
  // `isOpen` lives in the composable's singleton, so opening it is what makes
  // the Teleport render the dialog.
  await useConsultation().openForAppointment(APPOINTMENT)
  await flushPromises()
  return wrapper
}

const setValue = async (selector, value) => {
  const target = element(selector)
  target.value = value
  target.dispatchEvent(new Event('input', { bubbles: true }))
  await flushPromises()
}

const goToStep = async id => {
  const tab = element(`#tab-${id}`)
  tab.click()
  await flushPromises()
}

const submitButton = () =>
  Array.from(document.querySelectorAll('button')).find(button =>
    button.textContent.includes('Completar consulta')
  )

const fillSoap = async () => {
  await setValue('#cw-soap-s', 'Dolor al masticar')
  await setValue('#cw-soap-o', 'Caries oclusal en pieza 26')
  await setValue('#cw-soap-a', 'Caries de la dentina')
  await setValue('#cw-soap-p', 'Restauración con resina')
}

const searchDiagnosis = async term => {
  await setValue('#cw-diagnosis-search', term)
  await flushPromises()
}

const addFirstDiagnosis = async () => {
  element('[data-diagnosis-option]').click()
  await flushPromises()
}

describe('consultation closure NTS 139 smoke (#286)', () => {
  beforeEach(() => {
    document.body.innerHTML = ''
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

    for (const method of ['log', 'info', 'warn', 'error']) {
      consoleSpies.push(vi.spyOn(console, method))
    }
  })

  afterEach(() => {
    useConsultation().close()
    document.body.innerHTML = ''
    vi.unstubAllGlobals()
    for (const spy of consoleSpies.splice(0)) spy.mockRestore()
  })

  it('blocks the close while the professional has not signed', async () => {
    const wrapper = await mountWizard()

    await goToStep('evolution')
    await fillSoap()
    await setValue('#cw-reason', REASON)
    await searchDiagnosis('caries')
    await addFirstDiagnosis()

    await goToStep('signature')
    const button = submitButton()
    expect(button).toBeTruthy()
    expect(button.disabled).toBe(true)

    button.click()
    await flushPromises()

    expect(harness.fetch).not.toHaveBeenCalled()
    expect(wrapper.emitted('completed')).toBeFalsy()

    wrapper.unmount()
  })

  it('blocks the close while the reason for consultation is missing', async () => {
    const wrapper = await mountWizard()

    await goToStep('evolution')
    await fillSoap()
    await searchDiagnosis('caries')
    await addFirstDiagnosis()

    await goToStep('signature')
    await setValue('#cw-signature', PASSWORD)

    const button = submitButton()
    expect(button.disabled).toBe(true)

    button.click()
    await flushPromises()
    expect(harness.fetch).not.toHaveBeenCalled()

    wrapper.unmount()
  })

  it('blocks the close while no diagnosis is coded', async () => {
    const wrapper = await mountWizard()

    await goToStep('evolution')
    await fillSoap()
    await setValue('#cw-reason', REASON)

    await goToStep('signature')
    await setValue('#cw-signature', PASSWORD)

    const button = submitButton()
    expect(button.disabled).toBe(true)

    button.click()
    await flushPromises()
    expect(harness.fetch).not.toHaveBeenCalled()

    wrapper.unmount()
  })

  it('searches the CIE-10 catalogue with the typed term and adds a removable coded row', async () => {
    const wrapper = await mountWizard()

    await goToStep('evolution')
    await searchDiagnosis('caries')

    expect(harness.apiGet).toHaveBeenCalledWith('/api/diagnoses', {
      params: { q: 'caries', per_page: 20 }
    })

    const option = element('[data-diagnosis-option]')
    expect(option.textContent).toContain('K02.1')
    expect(option.textContent).toContain('Caries de la dentina')

    option.click()
    await flushPromises()

    const row = element('[data-diagnosis-row]')
    expect(row.textContent).toContain('K02.1')
    expect(row.textContent).toContain('Caries de la dentina')
    // `definitive` is the type a coded diagnosis gets by default.
    expect(row.textContent).toContain('Definitivo')

    element('[data-diagnosis-remove]').click()
    await flushPromises()

    expect(document.querySelector('[data-diagnosis-row]')).toBeFalsy()

    wrapper.unmount()
  })

  it('sends reason, diagnoses and signature password on submit', async () => {
    const wrapper = await mountWizard()

    await goToStep('evolution')
    await fillSoap()
    await setValue('#cw-reason', REASON)
    await searchDiagnosis('caries')
    await addFirstDiagnosis()

    await goToStep('signature')
    await setValue('#cw-signature', PASSWORD)

    const button = submitButton()
    expect(button.disabled).toBe(false)

    button.click()
    await flushPromises()

    expect(harness.fetch).toHaveBeenCalledTimes(1)
    const [url, init] = harness.fetch.mock.calls[0]
    expect(url).toContain('/api/appointments/501/complete')

    const { body } = init
    expect(body).toBeInstanceOf(FormData)
    expect(body.get('reason_for_consultation')).toBe(REASON)
    expect(body.get('diagnoses[0][diagnosis_catalog_id]')).toBe('11')
    expect(body.get('diagnoses[0][type]')).toBe('definitive')
    expect(body.get('signature[password]')).toBe(PASSWORD)

    // The password signs one request; it is neither persisted nor logged.
    for (let index = 0; index < localStorage.length; index += 1) {
      const key = localStorage.key(index)
      expect(String(localStorage.getItem(key) ?? '')).not.toContain(PASSWORD)
    }
    for (const spy of consoleSpies) {
      const logged = spy.mock.calls.map(args => args.map(String).join(' ')).join('\n')
      expect(logged).not.toContain(PASSWORD)
    }

    expect(wrapper.emitted('completed')).toBeTruthy()

    wrapper.unmount()
  })

  it('does not reuse the signature password when the wizard opens for another act', async () => {
    const wrapper = await mountWizard()

    await goToStep('signature')
    await setValue('#cw-signature', PASSWORD)
    expect(element('#cw-signature').value).toBe(PASSWORD)

    // F6 (#286): la firma es del acto que se cierra. Otra cita es otra
    // re-autenticación, aunque el asistente siga montado.
    await wrapper.setProps({ appointment: { ...APPOINTMENT, id: 502 } })
    await flushPromises()

    expect(element('#cw-signature').value).toBe('')

    wrapper.unmount()
  })

  // F5 (#316) — el caso de laboratorio es una entidad propia: el cierre ofrece
  // la puerta, abre el mismo formulario que la ficha del paciente con el
  // paciente de la cita precargado y no añade ningún miembro al payload.
  it('offers the laboratory door and opens the form with the patient of the act', async () => {
    localStorage.setItem('user', JSON.stringify({ id: 2, role: 'odontologo' }))
    const wrapper = await mountWizard()

    await goToStep('next')
    expect(element('[data-wizard-lab-case]')).toBeTruthy()
    expect(element('[data-wizard-lab-case-new]').textContent).toContain(
      'Registrar caso de laboratorio'
    )

    element('[data-wizard-lab-case-new]').click()
    await flushPromises()

    expect(element('[data-lab-case-form]')).toBeTruthy()
    expect(element('[data-lab-case-form-patient-fixed]').textContent).toContain('Ana Pérez')
    expect(document.querySelector('[data-lab-case-form-patient]')).toBeFalsy()

    localStorage.removeItem('user')
    wrapper.unmount()
  })

  it('hides the laboratory door from a profile that cannot write the module', async () => {
    localStorage.removeItem('user')
    const wrapper = await mountWizard()

    await goToStep('next')

    expect(document.querySelector('[data-wizard-lab-case]')).toBeFalsy()
    expect(document.querySelector('[data-wizard-lab-case-new]')).toBeFalsy()

    wrapper.unmount()
  })

  it('keeps the closure payload free of laboratory members', async () => {
    localStorage.setItem('user', JSON.stringify({ id: 2, role: 'odontologo' }))
    const wrapper = await mountWizard()

    await goToStep('evolution')
    await fillSoap()
    await setValue('#cw-reason', REASON)
    await searchDiagnosis('caries')
    await addFirstDiagnosis()

    await goToStep('signature')
    await setValue('#cw-signature', PASSWORD)

    submitButton().click()
    await flushPromises()

    const [, init] = harness.fetch.mock.calls[0]
    expect([...init.body.keys()].filter(key => key.toLowerCase().includes('lab'))).toEqual([])
    expect(init.body.get('reason_for_consultation')).toBe(REASON)

    localStorage.removeItem('user')
    wrapper.unmount()
  })

  it('keeps the three NTS 139 members in the wizard payload defaults', () => {
    const source = readFileSync(
      resolve(ROOT, 'resources/js/modules/appointments/ConsultationWizard.vue'),
      'utf8'
    )

    expect(source).toContain('Motivo de consulta')
    expect(source).toContain('Diagnóstico (CIE-10)')
    expect(source).toContain('Firma del profesional')
    expect(source).toContain("reason_for_consultation: ''")
    expect(source).toContain('diagnoses: []')
    expect(source).toContain("signature: { password: '' }")
  })
})
