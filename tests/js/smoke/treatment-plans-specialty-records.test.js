import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import { createMemoryHistory, createRouter } from 'vue-router'

// Slice 1 of «Registros de especialidad integrados a la atención y la
// historia»: the plan detail lists the structured clinical records its lines
// executed. The block is read-only, it filters by line or by catalog procedure,
// and it only exists for the session that may read the specialty module.
const harness = vi.hoisted(() => ({ apiGet: vi.fn() }))

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
vi.mock('../../../resources/js/composables/useEcho', () => ({
  useEcho: () => ({
    echo: {},
    channel: () => ({}),
    privateChannel: () => ({}),
    connectionStatus: { value: 'connected' }
  })
}))
vi.mock('../../../resources/js/composables/useWebSocketNotifications', () => ({
  useWebSocketNotifications: () => ({})
}))

import uiComponents from '../../../resources/js/plugins/ui-components'
import Detail from '../../../resources/js/modules/treatment-plans/components/TreatmentPlanDetail.vue'

const plan = {
  id: 3,
  patient_id: 7,
  plan_number: 'PT-2026-0003',
  title: 'Rehabilitación oral',
  status: 'approved',
  total_cost: 1500,
  final_cost: 1500,
  created_at: '2026-10-20T14:30:00.000Z',
  patient: { id: 7, first_name: 'Lucía', last_name: 'Ramírez' },
  items: [
    {
      id: 9,
      procedure_catalog_id: 12,
      procedure_name: 'Aparatología fija',
      quantity: 1,
      unit_cost: 900,
      total_cost: 900
    },
    {
      id: 10,
      procedure_catalog_id: null,
      procedure_name: 'Control',
      quantity: 1,
      unit_cost: 100,
      total_cost: 100
    }
  ],
  quotations: []
}

const grouped = {
  data: {
    implantologia: [
      {
        id: 41,
        patient_id: 7,
        treatment_plan_item_id: 9,
        clinical_encounter_id: 55,
        implant_brand: 'Straumann',
        implant_model: 'BLT',
        implant_diameter: '4.1',
        placement_date: '2026-03-04',
        created_at: '2026-03-04T10:15:00.000000Z'
      }
    ],
    ortodoncia: [
      {
        id: 42,
        patient_id: 7,
        treatment_plan_item_id: null,
        procedure_id: 12,
        treatment_type: 'brackets',
        appliance_type: 'metálicos',
        treatment_start_date: '2026-02-01',
        created_at: '2026-02-01T09:00:00.000000Z'
      }
    ],
    endodoncia: [
      {
        id: 43,
        patient_id: 7,
        treatment_plan_item_id: null,
        procedure_id: 99,
        tooth_number: '16',
        canal_count: 3,
        created_at: '2026-01-05T09:00:00.000000Z'
      }
    ],
    rehabilitacion: [],
    cirugia_oral: []
  },
  meta: { total: 3 }
}

const buildRouter = async () => {
  const router = createRouter({
    history: createMemoryHistory(),
    routes: [{ path: '/:pathMatch(.*)*', component: { template: '<div />' } }]
  })
  await router.push('/')
  await router.isReady()

  return router
}

const mountDetail = async (props = {}) => {
  const router = await buildRouter()
  const wrapper = mount(Detail, {
    props: { plan, ...props },
    global: { plugins: [router, uiComponents] },
    attachTo: document.body
  })

  await flushPromises()

  return wrapper
}

const grant = permissions => {
  localStorage.setItem('auth_token', 'test-token')
  localStorage.setItem(
    'user',
    JSON.stringify({ id: 1, name: 'Ana Admin', role: 'administrador', permissions })
  )
}

describe('treatment plan detail specialty records', () => {
  beforeEach(() => {
    localStorage.clear()
    harness.apiGet.mockReset()
    harness.apiGet.mockImplementation(async () => grouped)
  })

  afterEach(() => {
    document.body.innerHTML = ''
    document.body.style.overflow = ''
  })

  it('lists the records of its lines and of its procedures, and nothing else', async () => {
    grant(['manage.specialty_record'])

    const wrapper = await mountDetail()

    expect(harness.apiGet).toHaveBeenCalledWith('/api/specialty-records/patient/7/all')

    const section = document.querySelector('[data-detail-section="specialty-records"]')

    expect(section).not.toBeNull()

    const rows = [...document.querySelectorAll('[data-detail-specialty-record]')]

    expect(rows).toHaveLength(2)
    expect(rows.some(row => row.textContent.includes('Straumann'))).toBe(true)
    expect(rows.some(row => row.textContent.includes('brackets'))).toBe(true)
    expect(rows.some(row => row.textContent.includes('conductos'))).toBe(false)

    const open = document.querySelector('[data-detail-specialty-open]')

    expect(open.getAttribute('href')).toMatch(
      /^\/specialty-records\?patient_id=7&specialty=[a-z_]+&record_id=(41|42)$/
    )

    wrapper.unmount()
  })

  it('stays out of the dialog for a session without the specialty permission', async () => {
    grant([])

    const wrapper = await mountDetail()

    expect(harness.apiGet).not.toHaveBeenCalled()
    expect(document.querySelector('[data-detail-section="specialty-records"]')).toBeNull()

    wrapper.unmount()
  })

  it('reports a failed read instead of claiming the plan has no records', async () => {
    grant(['manage.specialty_record'])
    harness.apiGet.mockRejectedValueOnce({ response: { status: 500 } })

    const wrapper = await mountDetail()

    expect(document.querySelector('[data-detail-specialty-error]')).not.toBeNull()
    expect(document.querySelector('[data-detail-empty="specialty-records"]')).toBeNull()

    wrapper.unmount()
  })

  it('keeps the empty message when the plan lines have no records', async () => {
    grant(['manage.specialty_record'])
    harness.apiGet.mockResolvedValueOnce({ data: { implantologia: [], ortodoncia: [] } })

    const wrapper = await mountDetail()

    expect(document.querySelector('[data-detail-empty="specialty-records"]')).not.toBeNull()

    wrapper.unmount()
  })
})
