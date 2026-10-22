import { afterEach, beforeEach, describe, expect, it } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'

// F3 (#292): a correction is an addendum. The original evolution keeps every
// field it had, and the timeline and the record detail show the signed note
// next to it: author, date, reason and content.
import uiComponents from '../../../resources/js/plugins/ui-components'
import EvolutionTimeline from '../../../resources/js/modules/medical-records/components/EvolutionTimeline.vue'
import MedicalRecordDetail from '../../../resources/js/modules/medical-records/components/MedicalRecordDetail.vue'

const addendum = {
  id: 31,
  medical_record_id: 21,
  clinical_evolution_id: 5,
  reason: 'Error de digitación',
  content: 'La pieza correcta es la 36',
  created_at: '2026-03-06T10:00:00Z',
  author: { id: 1, name: 'Ana Odontóloga' }
}

const evolution = {
  id: 5,
  evolution_date: '2026-03-05',
  specialty: 'endodoncia',
  subjective: 'Dolor al masticar',
  objective: 'Caries oclusal',
  assessment: 'Caries',
  plan: 'Resina',
  addenda: [addendum]
}

const record = {
  id: 21,
  record_number: 'HC-2026-000021',
  first_visit_date: '2026-03-04',
  chief_complaint: 'Dolor molar',
  diagnosis: 'Caries',
  treatment_plan: 'Resina',
  is_active: true,
  patient: { id: 7, first_name: 'Ana', last_name: 'Torres' },
  addenda: [
    {
      ...addendum,
      id: 32,
      clinical_evolution_id: null,
      reason: 'Dato omitido',
      content: 'Antecedente de penicilina'
    },
    addendum
  ]
}

const signIn = () => {
  localStorage.setItem('auth_token', 'test-token')
  localStorage.setItem(
    'user',
    JSON.stringify({
      id: 1,
      name: 'Ana Odontóloga',
      role: 'odontologo',
      permissions: ['manage.evolution', 'manage.medical_record']
    })
  )
}

describe('clinical addenda surface (#292)', () => {
  beforeEach(signIn)

  afterEach(() => {
    localStorage.clear()
  })

  it('renders the correction next to the untouched evolution', () => {
    const wrapper = mount(EvolutionTimeline, {
      props: { evolutions: [evolution] },
      global: { plugins: [uiComponents] }
    })

    // The documented act is still the one the professional signed.
    expect(wrapper.get('[data-evolution-id="5"]').text()).toContain('Dolor al masticar')

    const block = wrapper.get('[data-evolution-addenda]')
    expect(block.get('[data-addendum-reason]').text()).toContain('Motivo: Error de digitación')
    expect(block.get('[data-addendum-content]').text()).toContain('La pieza correcta es la 36')
    expect(block.get('[data-addendum-meta]').text()).toContain('Ana Odontóloga')

    wrapper.unmount()
  })

  it('offers the addendum action on an evolution the user may manage', async () => {
    const wrapper = mount(EvolutionTimeline, {
      props: { evolutions: [{ ...evolution, addenda: [] }] },
      global: { plugins: [uiComponents] }
    })

    await wrapper.get('[data-evolution-addendum]').trigger('click')

    expect(wrapper.emitted('addendum')[0][0]).toMatchObject({ id: 5 })

    wrapper.unmount()
  })

  it('renders the record-level corrections and leaves the evolution ones to the timeline', () => {
    // The detail rides the shared modal primitive, which teleports to body.
    const wrapper = mount(MedicalRecordDetail, {
      props: { record },
      global: { plugins: [uiComponents] },
      attachTo: document.body
    })

    const section = document.body.querySelector('[data-detail-section="addenda"]')
    expect(section, 'missing addenda section').toBeTruthy()

    const entries = section.querySelectorAll('[data-addendum]')
    expect(entries).toHaveLength(1)
    expect(section.textContent).toContain('Dato omitido')
    expect(section.textContent).not.toContain('La pieza correcta es la 36')

    wrapper.unmount()
    document.body.innerHTML = ''
  })

  it('emits the record the addendum corrects from the detail footer', async () => {
    const wrapper = mount(MedicalRecordDetail, {
      props: { record },
      global: { plugins: [uiComponents] },
      attachTo: document.body
    })

    const action = document.body.querySelector('[data-detail-action="addendum"]')
    expect(action, 'missing addendum action').toBeTruthy()
    action.click()
    await flushPromises()

    expect(wrapper.emitted('addendum')[0][0]).toMatchObject({ id: 21 })

    wrapper.unmount()
    document.body.innerHTML = ''
  })
})
