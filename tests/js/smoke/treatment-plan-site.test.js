import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'

const harness = vi.hoisted(() => ({
  apiGet: vi.fn(),
  createPlan: vi.fn()
}))

const PIECES = [
  {
    id: 9,
    fdi_number: '16',
    name: 'Primer Molar Superior Derecho',
    type: 'molar',
    quadrant: 'superior_derecho',
    surfaces: [
      { id: 1, surface_code: 'D', surface_name: 'Distal', abbreviation: 'D' },
      { id: 2, surface_code: 'O', surface_name: 'Oclusal', abbreviation: 'O' }
    ]
  }
]

const PROCEDURES = [
  {
    id: 5,
    code: 'OBT-COMP',
    name: 'Obturación composite',
    site_type: 'tooth',
    default_cost: 180,
    default_duration_minutes: 45,
    specialty_name: 'Odontología general',
    is_active: true
  },
  {
    id: 6,
    code: 'LIMP',
    name: 'Limpieza dental',
    site_type: 'none',
    default_cost: 80,
    default_duration_minutes: 30,
    specialty_name: 'Odontología general',
    is_active: true
  }
]

const PATIENTS = [
  {
    id: 7,
    first_name: 'Ana',
    last_name: 'Torres',
    document_number: '12345678',
    phone: '999888777',
    email: 'ana@test.com',
    age: 30
  }
]

vi.mock('../../../resources/js/composables/useApi', () => ({
  useApi: () => ({
    get: harness.apiGet,
    post: async () => ({ data: {} }),
    put: async () => ({ data: {} }),
    patch: async () => ({ data: {} }),
    delete: async () => ({ data: {} }),
    setToken: () => {},
    normalizeError: () => ''
  })
}))

vi.mock('../../../resources/js/composables/useTreatmentPlans', async () => {
  const { ref } = await import('vue')
  return {
    useTreatmentPlans: () => ({
      createPlan: harness.createPlan,
      updatePlan: vi.fn(),
      loading: ref(false)
    })
  }
})

vi.mock('../../../resources/js/composables/useConfirm', async () => {
  const { ref } = await import('vue')
  return {
    confirmIsOpen: ref(false),
    confirmTitle: ref(''),
    confirmMessage: ref(''),
    confirmConfirmText: ref(''),
    confirmCancelText: ref(''),
    confirmVariant: ref('default'),
    useConfirm: () => ({ confirm: vi.fn(async () => true) })
  }
})

import uiComponents from '../../../resources/js/plugins/ui-components'
import TreatmentPlanModal from '../../../resources/js/modules/treatment-plans/components/TreatmentPlanModal.vue'

const setValue = async (selector, value) => {
  const input = document.querySelector(selector)
  expect(input, `"${selector}" must render an input`).toBeTruthy()
  input.value = value
  input.dispatchEvent(new Event('input', { bubbles: true }))
  await flushPromises()
}

const chooseOption = async (scopeSelector, label) => {
  const scope = document.querySelector(scopeSelector)
  expect(scope, `"${scopeSelector}" must be rendered`).toBeTruthy()

  const trigger = scope.querySelector('button[aria-haspopup="true"]')
  expect(trigger, `"${scopeSelector}" must render a select trigger`).toBeTruthy()
  trigger.click()
  await flushPromises()

  const option = [...scope.querySelectorAll('[role="listbox"] button')].find(
    button => button.textContent.trim() === label
  )
  expect(option, `option "${label}" must exist in "${scopeSelector}"`).toBeTruthy()
  option.click()
  await flushPromises()
}

const chooseProcedure = async procedureName => {
  document.querySelector('[data-plan-item-catalog-toggle="0"]').click()
  await flushPromises()

  const panel = document.querySelector('[data-plan-item-catalog-panel="0"]')
  expect(panel, 'the catalog panel must open').toBeTruthy()

  const option = [...panel.querySelectorAll('button')].find(button =>
    button.textContent.includes(procedureName)
  )
  expect(option, `procedure "${procedureName}" must be offered`).toBeTruthy()
  option.click()
  await flushPromises()
}

const selectPatient = async () => {
  const row = [...document.querySelectorAll('.patient-selector div')].find(
    element => element.className.includes('cursor-pointer') && element.textContent.includes('Ana')
  )
  expect(row, 'the patient list must render the mocked patient').toBeTruthy()
  row.click()
  await flushPromises()
}

const fillIdentity = async () => {
  await selectPatient()
  await setValue('[data-plan-field="title"] input', 'Rehabilitación superior')
}

const submit = async () => {
  document.querySelector('[data-plan-submit]').click()
  await flushPromises()
  await flushPromises()
}

const mountModal = async () => {
  const wrapper = mount(TreatmentPlanModal, {
    props: { plan: null, isEdit: false },
    global: { plugins: [uiComponents] },
    attachTo: document.body
  })
  await flushPromises()
  return wrapper
}

let wrapper = null

describe('treatment plan site', () => {
  beforeEach(() => {
    document.body.innerHTML = ''
    harness.createPlan.mockReset()
    harness.createPlan.mockResolvedValue({ data: { id: 21 } })
    harness.apiGet.mockReset()
    harness.apiGet.mockImplementation(async url => {
      if (url.includes('/api/dental-pieces')) return { data: PIECES }
      if (url.includes('/api/procedure-catalog')) {
        return {
          data: PROCEDURES,
          meta: { current_page: 1, last_page: 1, per_page: 12, total: PROCEDURES.length }
        }
      }
      if (url.includes('/api/patients')) return { data: PATIENTS }
      return { data: [] }
    })
  })

  afterEach(() => {
    wrapper?.unmount()
    wrapper = null
    document.body.innerHTML = ''
  })

  it('reveals the site field when the chosen procedure applies to a tooth', async () => {
    wrapper = await mountModal()

    expect(document.querySelector('[data-procedure-site]')).toBeNull()

    await chooseProcedure('Obturación composite')

    const site = document.querySelector('[data-procedure-site]')
    expect(site).toBeTruthy()
    expect(site.getAttribute('data-site-type')).toBe('tooth')
    expect(site.querySelector('[data-site-field="dental_piece_id"]')).toBeTruthy()

    // The picker fills the free-text name and the price, which stay editable.
    expect(document.querySelector('[data-plan-item-field="name"] input').value).toBe(
      'Obturación composite'
    )
  })

  it('blocks a tooth procedure without its piece with a Spanish message', async () => {
    wrapper = await mountModal()

    await fillIdentity()
    await chooseProcedure('Obturación composite')
    await submit()

    expect(harness.createPlan).not.toHaveBeenCalled()

    const alert = document.querySelector('[data-plan-modal] [role="alert"]')
    expect(alert).toBeTruthy()
    expect(alert.textContent).toContain(
      'El procedimiento «Obturación composite» se realiza sobre una pieza dental: selecciona la pieza.'
    )
  })

  it('sends the tooth of the line once the site is chosen', async () => {
    wrapper = await mountModal()

    await fillIdentity()
    await chooseProcedure('Obturación composite')
    await chooseOption('[data-site-field="dental_piece_id"]', '#16 · Primer Molar Superior Derecho')
    await submit()

    expect(harness.createPlan).toHaveBeenCalledTimes(1)

    const payload = harness.createPlan.mock.calls[0][0]
    expect(payload.items[0]).toMatchObject({
      procedure_name: 'Obturación composite',
      procedure_catalog_id: 5,
      unit_cost: 180,
      dental_piece_id: 9,
      tooth_surface_id: null,
      site_code: null
    })
  })

  it('keeps a procedure without applicability free of site fields', async () => {
    wrapper = await mountModal()

    await fillIdentity()
    await chooseProcedure('Limpieza dental')

    expect(document.querySelector('[data-procedure-site]')).toBeNull()

    await submit()

    expect(harness.createPlan).toHaveBeenCalledTimes(1)
    expect(harness.createPlan.mock.calls[0][0].items[0]).toMatchObject({
      procedure_catalog_id: 6,
      dental_piece_id: null
    })
  })
})
