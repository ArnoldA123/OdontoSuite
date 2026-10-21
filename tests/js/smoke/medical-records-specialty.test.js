import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'

// Issue #225: the evolution specialty is editable, but the form only offers
// the `specialties` catalog the page loads, and an edit that does not touch it
// must not rewrite the stored code with a default.
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
import EvolutionModal from '../../../resources/js/modules/medical-records/components/EvolutionModal.vue'
import EvolutionTimeline from '../../../resources/js/modules/medical-records/components/EvolutionTimeline.vue'

const record = { id: 21, record_number: 'HC-2026-000021' }

const specialties = [
  { id: 1, code: 'endodoncia', name: 'Endodoncia' },
  { id: 2, code: 'periodoncia', name: 'Periodoncia' }
]

const evolution = {
  id: 5,
  evolution_date: '2026-03-05',
  specialty: 'odontologia_general',
  subjective: 'Control'
}

const mountModal = async props => {
  const wrapper = mount(EvolutionModal, {
    props: { record, specialties, ...props },
    global: { plugins: [uiComponents] },
    attachTo: document.body
  })
  await flushPromises()

  return wrapper
}

const openSelect = async field => {
  const trigger = document.body.querySelector(
    `[data-evolution-field="${field}"] button[aria-haspopup="true"]`
  )
  expect(trigger, `missing select ${field}`).toBeTruthy()
  trigger.click()
  await flushPromises()
}

const chooseOption = async label => {
  const option = [...document.body.querySelectorAll('button[aria-selected]')].find(
    button => button.textContent.trim() === label
  )
  expect(option, `missing option ${label}`).toBeTruthy()
  option.click()
  await flushPromises()
}

const submit = async () => {
  const form = document.body.querySelector('[data-evolution-form]')
  form.dispatchEvent(new Event('submit', { cancelable: true, bubbles: true }))
  await flushPromises()
}

describe('evolution specialty selector (#225)', () => {
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

  it('offers the catalog in create mode and sends the chosen code', async () => {
    const wrapper = await mountModal({})

    await openSelect('specialty')
    await chooseOption('Endodoncia')
    await submit()

    expect(harness.addEvolution).toHaveBeenCalledTimes(1)
    const [, payload] = harness.addEvolution.mock.calls[0]
    expect(payload.specialty).toBe('endodoncia')

    wrapper.unmount()
  })

  it('keeps an unlisted stored code visible and does not resend it untouched', async () => {
    const wrapper = await mountModal({ evolution })

    const trigger = document.body.querySelector(
      '[data-evolution-field="specialty"] button[aria-haspopup="true"]'
    )
    expect(trigger.textContent).toContain('odontologia_general (actual)')

    await submit()

    expect(harness.updateEvolution).toHaveBeenCalledTimes(1)
    const [, payload] = harness.updateEvolution.mock.calls[0]
    expect(payload).not.toHaveProperty('specialty')

    wrapper.unmount()
  })

  it('sends the corrected code when the stored specialty is replaced', async () => {
    const wrapper = await mountModal({ evolution })

    await openSelect('specialty')
    await chooseOption('Periodoncia')
    await submit()

    const [, payload] = harness.updateEvolution.mock.calls[0]
    expect(payload.specialty).toBe('periodoncia')

    wrapper.unmount()
  })

  it('labels the timeline with the catalog name and falls back to the code', () => {
    const withCatalog = mount(EvolutionTimeline, {
      props: { evolutions: [evolution], specialties },
      global: { plugins: [uiComponents] }
    })

    expect(withCatalog.get('[data-evolution-specialty]').text()).toBe('odontologia_general')

    withCatalog.setProps({
      evolutions: [{ ...evolution, specialty: 'endodoncia' }]
    })

    return withCatalog.vm.$nextTick().then(() => {
      expect(withCatalog.get('[data-evolution-specialty]').text()).toBe('Endodoncia')
      withCatalog.unmount()
    })
  })
})
