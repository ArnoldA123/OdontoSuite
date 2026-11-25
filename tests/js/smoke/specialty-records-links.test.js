import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import { createMemoryHistory, createRouter } from 'vue-router'

// Slice 1 of «Registros de especialidad integrados a la atención y la
// historia»: the page accepts the two links of a record. `?encounter_id=` is
// how the act sends the professional to document its specialty data and
// `?treatment_plan_item_id=` is how the plan line does it. Both resolve the
// patient (and the procedure) and open the create form prefilled; the detail
// reads the links back with their way home.
const harness = vi.hoisted(() => ({ apiGet: vi.fn(), apiPost: vi.fn(), apiPut: vi.fn() }))

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
    privateChannel: () => {
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
import Page from '../../../resources/js/modules/specialty-records/SpecialtyRecordsPage.vue'

const patient = {
  id: 7,
  first_name: 'Ana',
  last_name: 'Torres',
  document_number: '45678912',
  phone: '999888777',
  email: 'ana@test.com'
}

const encounter = {
  id: 55,
  patient_id: 7,
  appointment_id: 71,
  mode: 'consultation',
  opened_at: '2026-03-04T10:00:00-05:00',
  patient
}

const plan = {
  id: 3,
  patient_id: 7,
  items: [
    {
      id: 9,
      treatment_plan_id: 3,
      procedure_catalog_id: 12,
      procedure_name: 'Aparatología fija',
      quantity: 1,
      unit_cost: 900
    }
  ]
}

const linkedRecord = {
  id: 42,
  patient_id: 7,
  treatment_type: 'brackets',
  appliance_type: 'metálicos',
  treatment_start_date: '2026-02-01',
  treatment_phase: 'active',
  status: 'active',
  created_at: '2026-02-01T09:00:00.000000Z',
  clinical_encounter_id: 55,
  treatment_plan_item_id: 9,
  clinical_encounter: { id: 55, mode: 'consultation' },
  treatment_plan_item: { id: 9, treatment_plan_id: 3, procedure_catalog_id: 12 }
}

const grouped = {
  data: {
    implantologia: [],
    ortodoncia: [linkedRecord],
    endodoncia: [],
    rehabilitacion: [],
    cirugia_oral: []
  },
  meta: { total: 1 }
}

const buildRouter = async query => {
  const router = createRouter({
    history: createMemoryHistory(),
    routes: [{ path: '/:pathMatch(.*)*', component: { template: '<div />' } }]
  })

  await router.push({ path: '/specialty-records', query })
  await router.isReady()

  return router
}

const mountPage = async query => {
  const router = await buildRouter(query)
  const wrapper = mount(Page, {
    global: { plugins: [router, uiComponents] },
    attachTo: document.body
  })

  await flushPromises()

  return wrapper
}

const setInput = async (fieldKey, value) => {
  const input = document.querySelector(`[data-field="${fieldKey}"] input`)

  expect(input, `field "${fieldKey}" must render an input`).toBeTruthy()
  input.value = value
  input.dispatchEvent(new Event('input', { bubbles: true }))
  await flushPromises()
}

const submit = async () => {
  const button = document.querySelector('[data-modal-submit]')

  expect(button).toBeTruthy()
  button.click()
  await flushPromises()
}

describe('specialty-records origin links', () => {
  beforeEach(() => {
    localStorage.clear()
    localStorage.setItem(
      'user',
      JSON.stringify({ id: 1, name: 'Admin', role: 'administrador', specialty: null })
    )

    harness.apiGet.mockReset()
    harness.apiPost.mockReset()
    harness.apiPut.mockReset()
    harness.apiPost.mockImplementation(async (url, payload) => ({ data: { id: 91, ...payload } }))
    harness.apiPut.mockImplementation(async (url, payload) => ({ data: payload }))
    harness.apiGet.mockImplementation(url => {
      if (url === '/api/encounters/55') return Promise.resolve({ data: encounter })
      if (url === '/api/treatment-plans/3') return Promise.resolve({ data: plan })
      if (url === `/api/specialty-records/patient/7/all`) return Promise.resolve(grouped)
      if (url === '/api/patients/7') return Promise.resolve({ data: patient })
      if (url.startsWith('/api/procedure-catalog?')) {
        return Promise.resolve({
          data: [
            {
              id: 12,
              code: 'ORT-01',
              name: 'Aparatología fija',
              specialty_name: 'Ortodoncia',
              default_duration_minutes: 45,
              default_cost: 900
            }
          ],
          meta: { current_page: 1, last_page: 1, per_page: 12, total: 1 }
        })
      }

      return Promise.resolve({ data: [] })
    })
  })

  afterEach(() => {
    document.body.innerHTML = ''
    document.body.style.overflow = ''
  })

  it('resolves the patient and the act from ?encounter_id and opens the form with it', async () => {
    const wrapper = await mountPage({ encounter_id: '55', specialty: 'ortodoncia' })

    expect(harness.apiGet).toHaveBeenCalledWith('/api/encounters/55')
    expect(document.querySelector('[data-specialty-record-form]')).not.toBeNull()

    const link = document.querySelector('[data-form-link="clinical_encounter_id"]')

    expect(link).not.toBeNull()
    expect(link.textContent.trim()).toBe('Atención #55')
    expect(wrapper.text()).toContain('Ana Torres')

    await setInput('treatment_type', 'brackets')
    await setInput('treatment_start_date', '2026-02-01')
    await submit()

    expect(harness.apiPost).toHaveBeenCalledTimes(1)
    expect(harness.apiPost.mock.calls[0][1].clinical_encounter_id).toBe(55)
    expect(harness.apiPost.mock.calls[0][1].patient_id).toBe(7)

    wrapper.unmount()
  })

  it('resolves the plan line from ?treatment_plan_item_id and prefills its procedure', async () => {
    const wrapper = await mountPage({
      patient_id: '7',
      treatment_plan_id: '3',
      treatment_plan_item_id: '9',
      specialty: 'ortodoncia'
    })

    expect(harness.apiGet).toHaveBeenCalledWith('/api/treatment-plans/3')

    const link = document.querySelector('[data-form-link="treatment_plan_item_id"]')

    expect(link).not.toBeNull()
    expect(link.textContent).toContain('Plan #3')
    expect(link.textContent).toContain('Aparatología fija')

    await setInput('treatment_type', 'brackets')
    await setInput('treatment_start_date', '2026-02-01')
    await submit()

    const payload = harness.apiPost.mock.calls[0][1]

    expect(payload.treatment_plan_item_id).toBe(9)
    expect(payload.procedure_id).toBe(12)

    wrapper.unmount()
  })

  it('shows the act and the plan of a record with their way back', async () => {
    const wrapper = await mountPage({
      patient_id: '7',
      specialty: 'ortodoncia',
      record_id: '42'
    })

    const detail = document.querySelector('[data-specialty-record-detail]')

    expect(detail).not.toBeNull()

    const act = document.querySelector('[data-detail-link="encounter"]')
    const planLink = document.querySelector('[data-detail-link="plan"]')

    expect(act.textContent.trim()).toBe('Atención #55')
    expect(act.querySelector('a').getAttribute('href')).toBe('/medical-records?patient_id=7')

    expect(planLink.textContent.trim()).toBe('Plan #3')
    expect(planLink.querySelector('a').getAttribute('href')).toBe(
      '/treatment-plans?patient_id=7&plan_id=3'
    )

    wrapper.unmount()
  })

  it('keeps the manual start when the link names no origin', async () => {
    const wrapper = await mountPage({ patient_id: '7', specialty: 'ortodoncia' })

    expect(document.querySelector('[data-specialty-record-form]')).toBeNull()
    expect(wrapper.find('[data-specialty-records-panel]').exists()).toBe(true)

    wrapper.unmount()
  })
})
