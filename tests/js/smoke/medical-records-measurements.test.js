import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'

// Issue #222: `clinical_evolutions.clinical_measurements` is part of the
// contract the backend validates (`StoreEvolutionRequest`), so the modal must
// capture it and the timeline must render it with units. The backend feature
// test pins the validation; this one pins the two surfaces against the same
// five keys.
const harness = vi.hoisted(() => ({
  addEvolution: vi.fn(),
  updateEvolution: vi.fn(),
  success: vi.fn(),
  error: vi.fn()
}))

vi.mock('../../../resources/js/composables/useMedicalRecords', () => ({
  useMedicalRecords: () => ({
    addEvolution: harness.addEvolution,
    updateEvolution: harness.updateEvolution
  })
}))
vi.mock('../../../resources/js/composables/useToast', () => ({
  useToast: () => ({ success: harness.success, error: harness.error })
}))

import uiComponents from '../../../resources/js/plugins/ui-components'
import EvolutionTimeline from '../../../resources/js/modules/medical-records/components/EvolutionTimeline.vue'
import EvolutionModal from '../../../resources/js/modules/medical-records/components/EvolutionModal.vue'

const record = { id: 21, record_number: 'HC-2026-000021' }

const evolution = {
  id: 5,
  evolution_date: '2026-03-05',
  specialty: 'general',
  subjective: 'Sangrado al cepillarse',
  clinical_measurements: {
    pocket_depth_mm: 4.5,
    gingival_recession_mm: 2,
    plaque_index_percent: 35,
    bleeding_on_probing: false,
    tooth_mobility: 2
  }
}

const mountModal = async () => {
  const wrapper = mount(EvolutionModal, {
    props: { record },
    global: { plugins: [uiComponents] },
    attachTo: document.body
  })
  await flushPromises()

  return wrapper
}

const typeInto = (selector, value) => {
  const input = document.body.querySelector(selector)
  expect(input, `missing input ${selector}`).toBeTruthy()
  input.value = String(value)
  input.dispatchEvent(new Event('input'))
}

const submit = async () => {
  const form = document.body.querySelector('[data-evolution-form]')
  expect(form).toBeTruthy()
  form.dispatchEvent(new Event('submit', { cancelable: true, bubbles: true }))
  await flushPromises()
}

describe('clinical measurements surface (#222)', () => {
  beforeEach(() => {
    localStorage.setItem('auth_token', 'test-token')
    localStorage.setItem(
      'user',
      JSON.stringify({ id: 1, name: 'Ana Odontóloga', role: 'odontologo' })
    )
    vi.clearAllMocks()
    harness.addEvolution.mockResolvedValue({ id: 6 })
    harness.updateEvolution.mockResolvedValue({ id: 5 })
  })

  afterEach(() => {
    document.body.innerHTML = ''
    localStorage.clear()
  })

  it('renders every recorded measurement with its unit, including a negative bleeding finding', () => {
    const wrapper = mount(EvolutionTimeline, {
      props: { evolutions: [evolution] },
      global: { plugins: [uiComponents] }
    })

    const block = wrapper.get('[data-evolution-measurements]')
    const text = block.text()

    expect(text).toContain('Sondaje')
    expect(text).toContain('4.5 mm')
    expect(text).toContain('Recesión')
    expect(text).toContain('2 mm')
    expect(text).toContain('Índice de placa')
    expect(text).toContain('35 %')
    expect(text).toContain('Movilidad')
    expect(text).toContain('Grado 2')
    expect(text).toContain('Sangrado al sondaje')
    expect(text).toContain('No')

    wrapper.unmount()
  })

  it('omits the measurements block when the evolution has none', () => {
    const wrapper = mount(EvolutionTimeline, {
      props: { evolutions: [{ ...evolution, clinical_measurements: null }] },
      global: { plugins: [uiComponents] }
    })

    expect(wrapper.find('[data-evolution-measurements]').exists()).toBe(false)

    wrapper.unmount()
  })

  it('captures the contract in create mode and sends it to the API', async () => {
    const wrapper = await mountModal()

    typeInto('[data-evolution-field="pocket_depth_mm"] input', '3.5')
    typeInto('[data-evolution-field="gingival_recession_mm"] input', '1')
    typeInto('[data-evolution-field="plaque_index_percent"] input', '42')
    typeInto('[data-evolution-field="tooth_mobility"] input', '1')

    // The bleeding selector is a three-state UiSelect: open it and choose "Sí".
    const trigger = document.body.querySelector(
      '[data-evolution-field="bleeding_on_probing"] button[aria-haspopup="true"]'
    )
    expect(trigger).toBeTruthy()
    trigger.click()
    await flushPromises()

    const yes = [...document.body.querySelectorAll('button[aria-selected]')].find(
      button => button.textContent.trim() === 'Sí'
    )
    expect(yes).toBeTruthy()
    yes.click()
    await flushPromises()

    await submit()

    expect(harness.addEvolution).toHaveBeenCalledTimes(1)
    const [recordId, payload] = harness.addEvolution.mock.calls[0]
    expect(recordId).toBe(21)
    expect(payload.clinical_measurements).toEqual({
      pocket_depth_mm: 3.5,
      gingival_recession_mm: 1,
      plaque_index_percent: 42,
      tooth_mobility: 1,
      bleeding_on_probing: true
    })

    wrapper.unmount()
  })

  it('prefills the measurements in edit mode and keeps the tri-state value', async () => {
    const wrapper = mount(EvolutionModal, {
      props: { record, evolution },
      global: { plugins: [uiComponents] },
      attachTo: document.body
    })
    await flushPromises()

    expect(
      document.body.querySelector('[data-evolution-field="pocket_depth_mm"] input').value
    ).toBe('4.5')
    expect(document.body.querySelector('[data-evolution-field="tooth_mobility"] input').value).toBe(
      '2'
    )

    await submit()

    expect(harness.updateEvolution).toHaveBeenCalledTimes(1)
    const [evolutionId, payload] = harness.updateEvolution.mock.calls[0]
    expect(evolutionId).toBe(5)
    expect(payload.clinical_measurements).toEqual({
      pocket_depth_mm: 4.5,
      gingival_recession_mm: 2,
      plaque_index_percent: 35,
      tooth_mobility: 2,
      bleeding_on_probing: false
    })

    wrapper.unmount()
  })
})
