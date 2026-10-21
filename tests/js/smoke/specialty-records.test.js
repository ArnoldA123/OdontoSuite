import { beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import { createMemoryHistory, createRouter } from 'vue-router'

// Registros de Especialidades (#212): the page reads the Spanish keyed
// `/patient/{id}/all` payload, renders one card per real record, and shows the
// no-patient / no-specialties states when there is nothing to show.
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

const router = createRouter({
  history: createMemoryHistory(),
  routes: [{ path: '/:pathMatch(.*)*', component: { template: '<div />' } }]
})

const patient = {
  id: 7,
  first_name: 'Ana',
  last_name: 'Torres',
  document_number: '45678912',
  phone: '999888777',
  email: 'ana@test.com'
}

const implantRecord = {
  id: 41,
  patient_id: 7,
  implant_brand: 'Straumann',
  implant_model: 'BLT',
  implant_diameter: '4.1',
  implant_length: '10',
  batch_number: 'L-778',
  placement_date: '2026-03-04',
  status: 'placed',
  created_at: '2026-03-04T10:15:00.000000Z'
}

const orthoRecord = {
  id: 42,
  patient_id: 7,
  treatment_type: 'Alineadores',
  appliance_type: 'Transparente',
  treatment_start_date: '2026-02-01',
  treatment_phase: 'active',
  created_at: '2026-02-01T09:00:00.000000Z'
}

const keyedPayload = {
  data: {
    implantologia: [implantRecord],
    ortodoncia: [orthoRecord],
    endodoncia: [],
    rehabilitacion: [],
    cirugia_oral: []
  },
  meta: { total: 2 }
}

const mountPage = async () => {
  const wrapper = mount(Page, { global: { plugins: [router, uiComponents] } })
  await flushPromises()

  return wrapper
}

const selectPatient = async wrapper => {
  const row = wrapper
    .findAll('.patient-selector p')
    .find(node => node.text().includes('Ana Torres'))

  expect(row).toBeTruthy()
  await row.trigger('click')
  await flushPromises()
}

describe('specialty-records smoke', () => {
  beforeEach(() => {
    localStorage.clear()
    localStorage.setItem(
      'user',
      JSON.stringify({ id: 1, name: 'Admin', role: 'administrador', specialty: null })
    )
    harness.apiGet.mockReset()
    harness.apiGet.mockImplementation(url => {
      if (url === '/api/patients') return Promise.resolve({ data: [patient] })
      if (url === '/api/specialty-records/patient/7/all') return Promise.resolve(keyedPayload)

      return Promise.resolve({ data: [] })
    })
  })

  it('mounts SpecialtyRecordsPage.vue and renders content', async () => {
    const wrapper = await mountPage()

    expect(wrapper.text()).toContain('Registros de Especialidades')
    wrapper.unmount()
  })

  it('asks for a patient when none is selected', async () => {
    const wrapper = await mountPage()

    expect(wrapper.find('[data-specialty-records-no-patient]').exists()).toBe(true)
    expect(wrapper.find('[data-specialty-records-grid]').exists()).toBe(false)
    expect(wrapper.find('[data-new-record]').attributes('disabled')).toBeDefined()

    wrapper.unmount()
  })

  it('renders the no-specialties state for a role without clinical specialties', async () => {
    localStorage.setItem(
      'user',
      JSON.stringify({ id: 30, name: 'Técnico', role: 'tecnico_dental', specialty: null })
    )

    const wrapper = await mountPage()

    expect(wrapper.find('[data-specialty-records-no-specialties]').exists()).toBe(true)
    expect(wrapper.text()).toContain('Sin especialidades asignadas')

    wrapper.unmount()
  })

  it('renders one card per real record once a patient is selected', async () => {
    const wrapper = await mountPage()

    await selectPatient(wrapper)

    expect(harness.apiGet).toHaveBeenCalledWith('/api/specialty-records/patient/7/all')
    expect(wrapper.find('[data-specialty-records-grid]').exists()).toBe(true)
    expect(wrapper.find('[data-specialty-records-no-patient]').exists()).toBe(false)

    const cards = wrapper.findAll('[data-specialty-record-card]')
    expect(cards).toHaveLength(1)
    expect(cards[0].attributes('data-specialty')).toBe('implantologia')
    expect(cards[0].text()).toContain('Straumann')
    expect(cards[0].text()).toContain('04 mar 2026')
    expect(cards[0].text()).toContain('Colocado')

    expect(wrapper.find('[data-kpi-value="total"]').text()).toBe('1')
    expect(wrapper.find('[data-kpi-value="in_progress"]').text()).toBe('1')
    expect(wrapper.find('[data-kpi-value="completed"]').text()).toBe('0')
    expect(wrapper.find('[data-kpi-value="last"]').text()).toBe('04 mar 2026')

    expect(wrapper.text()).toContain('Implantología')
    expect(wrapper.text()).toContain('Ortodoncia')

    wrapper.unmount()
  })
})
