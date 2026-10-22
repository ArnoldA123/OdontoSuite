import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { createRouter, createMemoryHistory } from 'vue-router'

// Historias clínicas shell (T3 of the interface redesign). Pins the DOM
// contract a reader sees: hero band in the login/dashboard language, the KPI
// strip over the real stats keys, the landing index of recent histories, the
// patient workspace with the `UiTabs` primitive, and both deep links
// (`?patient_id=` and `/medical-records/:id`).
const harness = vi.hoisted(() => ({ apiGet: vi.fn(), listeners: {} }))

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
  // #249: medical-records is a private channel, so the page subscribes with
  // `privateChannel`. The harness records the listeners there; the assertion
  // that matters is that a live event still reaches the timeline.
  const makeChannel = () => {
    const channel = {
      listen: (name, handler) => {
        harness.listeners[name] = handler
        return channel
      }
    }
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

const secondRecord = {
  id: 22,
  record_number: 'HC-2026-000022',
  first_visit_date: '2026-05-12',
  chief_complaint: 'Control',
  diagnosis: 'Sin hallazgos',
  treatment_plan: null,
  notes: null,
  is_active: true,
  patient,
  evolutions_count: 0,
  attachments_count: 0
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

const evolution = {
  id: 5,
  evolution_date: '2026-03-05',
  subjective: 'Dolor',
  objective: 'Caries oclusal',
  assessment: 'Caries',
  plan: 'Resina'
}

const installApi = ({ records = [record], failIndex = false, recordPages = null } = {}) =>
  harness.apiGet.mockImplementation(async url => {
    const parsed = new URL(url, 'http://localhost')
    const path = parsed.pathname

    if (path === '/api/medical-records') {
      if (failIndex) throw { response: { data: { message: 'Sin conexión con el servidor' } } }

      if (recordPages) {
        const page = Number(parsed.searchParams.get('page') || 1)
        const data = recordPages[page - 1] || []

        return {
          data,
          meta: {
            total: recordPages.flat().length,
            current_page: page,
            per_page: 50,
            last_page: recordPages.length
          }
        }
      }

      return {
        data: records,
        meta: { total: records.length, current_page: 1, per_page: 10, last_page: 1 }
      }
    }
    if (/^\/api\/medical-records\/\d+\/evolutions$/.test(path)) {
      return { data: [evolution], meta: { total: 1 } }
    }
    if (/^\/api\/medical-records\/\d+$/.test(path)) {
      return { data: records[0] ?? null }
    }
    if (/^\/api\/medical-records\/patient\/\d+\/attachments$/.test(path)) {
      return { data: [], meta: { total: 0 } }
    }
    if (/^\/api\/medical-records\/patient\/\d+\/stats$/.test(path)) {
      return { data: stats }
    }
    if (/^\/api\/patients\/\d+$/.test(path)) {
      return { data: patient }
    }

    return { data: [] }
  })

const calls = () => harness.apiGet.mock.calls.map(([url]) => url)

const mountPage = async ({ path = '/medical-records', query = {} } = {}) => {
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
  await router.push({ path, query })
  await router.isReady()

  const wrapper = mount(Page, { global: { plugins: [router, uiComponents] } })
  await flushPromises()

  return { wrapper, router }
}

describe('medical-records shell', () => {
  beforeEach(() => {
    localStorage.setItem('auth_token', 'test-token')
    localStorage.setItem(
      'user',
      JSON.stringify({ id: 1, name: 'Ana Admin', role: 'administrador' })
    )
    harness.apiGet.mockReset()
    harness.listeners = {}
    installApi()
  })

  afterEach(() => {
    localStorage.clear()
  })

  it('renders the hero band, the KPI strip and the recent histories landing', async () => {
    const { wrapper } = await mountPage()

    const hero = wrapper.get('[data-medical-records-hero]')
    for (const token of [
      'var(--color-canvas)',
      'var(--color-hairline)',
      'var(--radius-panel)',
      'var(--elevation-2)'
    ]) {
      expect(hero.attributes('style')).toContain(token)
    }

    const title = hero.get('.page-title')
    expect(title.element.tagName).toBe('P')
    expect(title.text()).toBe('Historias clínicas')
    expect(hero.find('h1').exists()).toBe(false)
    expect(hero.text()).toContain('OdontoSuite')

    expect(wrapper.get('[data-new-record-button]').attributes('disabled')).toBeDefined()

    for (const hook of ['records', 'evolutions', 'attachments', 'follow-ups']) {
      expect(wrapper.find(`[data-stat="${hook}"]`).exists()).toBe(true)
    }
    expect(wrapper.get('[data-stat="records"]').text()).toBe('—')

    const landing = wrapper.get('[data-recent-list]')
    expect(landing.text()).toContain('Ana Torres')
    expect(landing.text()).toContain('HC-2026-000021')
    expect(landing.text()).toContain('3 evoluciones')
    expect(landing.text()).toContain('2 adjuntos')

    wrapper.unmount()
  })

  it('filters the landing index through the data layer', async () => {
    const { wrapper } = await mountPage()

    const search = wrapper.get('input[placeholder="Buscar por paciente o número de historia..."]')
    await search.setValue('HC-2026')
    await new Promise(resolve => setTimeout(resolve, 350))
    await flushPromises()

    expect(calls().some(url => url.includes('search=HC-2026'))).toBe(true)

    wrapper.unmount()
  })

  it('shows the inline error with a retry when the index fails', async () => {
    installApi({ failIndex: true })
    const { wrapper } = await mountPage()

    const error = wrapper.get('[data-state="error-records"]')
    expect(error.text()).toContain('Sin conexión con el servidor')
    expect(wrapper.find('[data-retry-records]').exists()).toBe(true)

    wrapper.unmount()
  })

  it('explains the empty landing with the shared empty state', async () => {
    installApi({ records: [] })
    const { wrapper } = await mountPage()

    expect(wrapper.get('[data-state="empty-records"]').text()).toContain('Sin historias clínicas')

    wrapper.unmount()
  })

  it('opens a patient workspace from a recent history row', async () => {
    const { wrapper } = await mountPage()

    await wrapper.get('[data-recent-row]').trigger('click')
    await flushPromises()

    expect(wrapper.text()).toContain('Ana Torres')
    expect(wrapper.get('[data-patient-context]').text()).toContain('45678912')
    expect(wrapper.get('[data-new-record-button]').attributes('disabled')).toBeUndefined()

    const requested = calls()
    expect(requested.some(url => url.includes('/api/medical-records?patient_id=7'))).toBe(true)
    expect(requested.some(url => url.includes('/api/medical-records/patient/7/attachments'))).toBe(
      true
    )
    expect(requested.some(url => url.includes('/api/medical-records/patient/7/stats'))).toBe(true)
    expect(requested).toContain('/api/medical-records/21/evolutions')

    expect(wrapper.get('[data-stat="records"]').text()).toBe('1')
    expect(wrapper.get('[data-stat="follow-ups"]').text()).toBe('1')

    const labels = wrapper.findAll('[role="tab"]').map(tab => tab.text())
    expect(labels).toEqual(['Historia general', 'Evoluciones', 'Adjuntos', 'Estadísticas'])
    expect(wrapper.findAll('[role="tab"]')[0].attributes('aria-selected')).toBe('true')

    expect(wrapper.text()).toContain('HC-2026-000021')

    wrapper.unmount()
  })

  it('opens the workspace from the ?patient_id= deep link', async () => {
    const { wrapper } = await mountPage({ query: { patient_id: '7' } })

    expect(calls()).toContain('/api/patients/7')
    expect(wrapper.text()).toContain('Ana Torres')
    expect(wrapper.get('[data-new-record-button]').attributes('disabled')).toBeUndefined()

    wrapper.unmount()
  })

  it('opens the workspace from the /medical-records/:id deep link on the general tab', async () => {
    const { wrapper } = await mountPage({ path: '/medical-records/21' })

    expect(calls()).toContain('/api/medical-records/21')
    expect(calls()).toContain('/api/medical-records/21/evolutions')
    expect(calls().some(url => url.includes('patient_id=7'))).toBe(true)

    expect(wrapper.text()).toContain('Ana Torres')
    expect(wrapper.findAll('[role="tab"]')[0].attributes('aria-selected')).toBe('true')

    wrapper.unmount()
  })

  it('returns to the landing when the selection is cleared', async () => {
    const { wrapper } = await mountPage({ query: { patient_id: '7' } })

    await wrapper.get('[data-clear-selection]').trigger('click')
    await flushPromises()

    expect(wrapper.find('[data-patient-context]').exists()).toBe(false)
    expect(wrapper.text()).toContain('Historias recientes')
    expect(wrapper.get('[data-stat="records"]').text()).toBe('—')

    wrapper.unmount()
  })

  it('paginates every history of the patient instead of truncating at 50', async () => {
    installApi({ recordPages: [[record], [secondRecord]] })
    const { wrapper } = await mountPage({ query: { patient_id: '7' } })

    const requested = calls()
    expect(
      requested.some(url => url.includes('/api/medical-records?') && url.includes('page=1'))
    ).toBe(true)
    expect(
      requested.some(url => url.includes('/api/medical-records?') && url.includes('page=2'))
    ).toBe(true)

    const numbers = wrapper.get('[data-patient-record-numbers]').text()
    expect(numbers).toContain('HC-2026-000021')
    expect(numbers).toContain('HC-2026-000022')

    wrapper.unmount()
  })

  it('lets the chosen history feed the evolutions tab when the patient owns more than one', async () => {
    installApi({ records: [record, secondRecord] })
    const { wrapper } = await mountPage({ query: { patient_id: '7' } })

    expect(wrapper.find('[data-record-selector]').exists()).toBe(true)
    expect(wrapper.get('[data-active-record-label]').text()).toBe('HC-2026-000021')

    await wrapper.get('[data-record-selector] button[aria-haspopup="true"]').trigger('click')
    const option = wrapper
      .findAll('button[aria-selected]')
      .find(button => button.text().includes('HC-2026-000022'))
    expect(option).toBeTruthy()
    await option.trigger('click')
    await flushPromises()

    expect(calls()).toContain('/api/medical-records/22/evolutions')
    expect(wrapper.get('[data-active-record-label]').text()).toBe('HC-2026-000022')

    await wrapper.findAll('[role="tab"]')[1].trigger('click')
    await flushPromises()

    expect(wrapper.get('[data-evolution-record]').text()).toContain('HC-2026-000022')

    wrapper.unmount()
  })

  it('labels attachments and stats as patient-scoped', async () => {
    installApi({ records: [record, secondRecord] })
    const { wrapper } = await mountPage({ query: { patient_id: '7' } })

    await wrapper.findAll('[role="tab"]')[2].trigger('click')
    await flushPromises()
    expect(wrapper.get('[data-attachments-scope]').text()).toContain('expediente completo')

    await wrapper.findAll('[role="tab"]')[3].trigger('click')
    await flushPromises()
    expect(wrapper.get('[data-stats-scope]').text()).toContain('todo el expediente del paciente')

    wrapper.unmount()
  })

  it('refreshes the timeline when another tab updates an evolution', async () => {
    const { wrapper } = await mountPage({ query: { patient_id: '7' } })
    await wrapper.findAll('[role="tab"]')[1].trigger('click')
    await flushPromises()

    expect(wrapper.get('[data-evolution-id="5"]').text()).toContain('Dolor')

    await harness.listeners['.clinical-evolution.updated']({
      evolution: {
        ...evolution,
        subjective: 'Dolor agudo al masticar',
        medical_record_id: 21,
        medical_record: { id: 21, patient_id: 7 }
      }
    })
    await flushPromises()

    expect(wrapper.get('[data-evolution-id="5"]').text()).toContain('Dolor agudo al masticar')

    wrapper.unmount()
  })
})
