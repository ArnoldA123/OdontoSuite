import { beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import { createMemoryHistory, createRouter } from 'vue-router'

// Slice 1 of «Registros de especialidad integrados a la atención y la
// historia»: the patient workspace gains the «Especialidades» tab. It is a
// reader of the same aggregate the specialty page consumes, it only exists for
// the session that may read that module, and it never mounts the specialty
// form.
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
vi.mock('../../../resources/js/composables/useEcho', () => {
  const makeChannel = () => {
    const channel = { listen: () => channel }
    return channel
  }

  return {
    useEcho: () => ({
      echo: { leave: () => {} },
      channel: makeChannel,
      privateChannel: makeChannel,
      connectionStatus: { value: 'connected' }
    })
  }
})
vi.mock('../../../resources/js/composables/useWebSocketNotifications', () => ({
  useWebSocketNotifications: () => ({})
}))

import uiComponents from '../../../resources/js/plugins/ui-components'
import Page from '../../../resources/js/modules/medical-records/MedicalRecordsPage.vue'

const patient = {
  id: 7,
  first_name: 'Ana',
  last_name: 'Torres',
  document_number: '45678912',
  phone: '999888777',
  email: 'ana@test.com'
}

const record = {
  id: 21,
  record_number: 'HC-2026-000021',
  first_visit_date: '2026-03-04',
  chief_complaint: 'Dolor molar',
  diagnosis: 'Caries',
  treatment_plan: 'Resina',
  notes: null,
  is_active: true,
  patient,
  evolutions_count: 3,
  attachments_count: 2
}

const stats = {
  total_records: 1,
  total_evolutions: 3,
  total_attachments: 2,
  last_evolution: null,
  specialties_involved: ['endodoncia'],
  attachment_categories: { radiografia: 2 },
  pending_follow_ups: 1
}

const specialtyPayload = {
  data: {
    implantologia: [
      {
        id: 41,
        patient_id: 7,
        implant_brand: 'Straumann',
        implant_model: 'BLT',
        implant_diameter: '4.1',
        placement_date: '2026-03-04',
        clinical_encounter_id: 55,
        treatment_plan_item_id: 9,
        procedure: { id: 12, code: 'IMPL-16', name: 'Implante unitario' },
        created_at: '2026-03-04T10:15:00.000000Z'
      }
    ],
    ortodoncia: [
      {
        id: 42,
        patient_id: 7,
        treatment_type: 'brackets',
        appliance_type: 'metálicos',
        treatment_start_date: '2026-02-01',
        clinical_encounter_id: null,
        treatment_plan_item_id: null,
        procedure: null,
        created_at: '2026-02-01T09:00:00.000000Z'
      }
    ],
    endodoncia: [],
    rehabilitacion: [],
    cirugia_oral: []
  },
  meta: { total: 2 }
}

const apiResponse = async url => {
  const parsed = new URL(url, 'http://localhost')
  const path = parsed.pathname

  if (path === '/api/medical-records') {
    return { data: [record], meta: { total: 1, current_page: 1, per_page: 10, last_page: 1 } }
  }
  if (/^\/api\/medical-records\/\d+\/evolutions$/.test(path)) {
    return { data: [], meta: { total: 0 } }
  }
  if (/^\/api\/medical-records\/patient\/\d+\/attachments$/.test(path)) {
    return { data: [], meta: { total: 0 } }
  }
  if (/^\/api\/medical-records\/patient\/\d+\/stats$/.test(path)) {
    return { data: stats }
  }
  if (path === '/api/specialty-records/patient/7/all') {
    return specialtyPayload
  }
  if (/^\/api\/patients\/\d+$/.test(path)) {
    return { data: patient }
  }

  return { data: [] }
}

const installApi = () => harness.apiGet.mockImplementation(apiResponse)

const mountWorkspace = async () => {
  const router = createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: '/medical-records', name: 'medical-records', component: { template: '<div />' } },
      {
        path: '/medical-records/:id(\\d+)',
        name: 'medical-record-detail',
        component: { template: '<div />' }
      },
      { path: '/:pathMatch(.*)*', component: { template: '<div />' } }
    ]
  })
  await router.push({ path: '/medical-records', query: { patient_id: '7' } })
  await router.isReady()

  const wrapper = mount(Page, { global: { plugins: [router, uiComponents] } })
  await flushPromises()

  return wrapper
}

const grant = permissions =>
  localStorage.setItem(
    'user',
    JSON.stringify({ id: 1, name: 'Ana Admin', role: 'administrador', permissions })
  )

describe('medical-records specialty tab', () => {
  beforeEach(() => {
    localStorage.clear()
    localStorage.setItem('auth_token', 'test-token')
    harness.apiGet.mockReset()
    installApi()
  })

  it('lists the specialty records of the patient with type, procedure, act and plan', async () => {
    grant(['manage.specialty_record'])

    const wrapper = await mountWorkspace()
    const labels = wrapper.findAll('[role="tab"]').map(tab => tab.text())

    expect(labels).toEqual([
      'Historia general',
      'Evoluciones',
      'Adjuntos',
      'Estadísticas',
      'Especialidades'
    ])

    wrapper.findAll('[role="tab"]')[4].trigger('click')
    await flushPromises()

    expect(harness.apiGet).toHaveBeenCalledWith('/api/specialty-records/patient/7/all')

    const rows = wrapper.findAll('[data-specialty-record-row]')

    expect(rows).toHaveLength(2)

    const implant = rows.find(row => row.text().includes('Implantología'))

    expect(implant.text()).toContain('Straumann')
    expect(implant.get('[data-specialty-record-procedure]').text()).toContain('IMPL-16')
    expect(implant.get('[data-specialty-record-encounter]').text()).toBe('Atención #55')
    expect(implant.get('[data-specialty-record-plan]').text()).toBe('Plan de tratamiento')
    expect(implant.get('[data-specialty-record-open]').attributes('href')).toBe(
      '/specialty-records?patient_id=7&specialty=implantologia&record_id=41'
    )

    // The reader never mounts the specialty form.
    expect(document.querySelector('[data-specialty-record-form]')).toBeNull()

    wrapper.unmount()
  })

  it('hides the tab from a session without the specialty record permission', async () => {
    grant([])

    const wrapper = await mountWorkspace()
    const labels = wrapper.findAll('[role="tab"]').map(tab => tab.text())

    expect(labels).toEqual(['Historia general', 'Evoluciones', 'Adjuntos', 'Estadísticas'])
    expect(harness.apiGet).not.toHaveBeenCalledWith('/api/specialty-records/patient/7/all')

    wrapper.unmount()
  })

  it('explains an empty list instead of rendering an empty panel', async () => {
    grant(['manage.specialty_record'])
    harness.apiGet.mockImplementation(async url => {
      if (url === '/api/specialty-records/patient/7/all') {
        return {
          data: {
            implantologia: [],
            ortodoncia: [],
            endodoncia: [],
            rehabilitacion: [],
            cirugia_oral: []
          },
          meta: { total: 0 }
        }
      }

      return apiResponse(url)
    })

    const wrapper = await mountWorkspace()

    wrapper.findAll('[role="tab"]')[4].trigger('click')
    await flushPromises()

    expect(wrapper.find('[data-state="empty-specialty-records"]').exists()).toBe(true)

    wrapper.unmount()
  })
})
