import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { createRouter, createMemoryHistory } from 'vue-router'

// business-intelligence-ui-redesign / T7 — surface contract of the redesigned
// /business-intelligence page: hero band, four-card KPI strip, shared filter
// controls, applied-period presets and the report selector.
const { getMock, downloadMock } = vi.hoisted(() => ({
  getMock: vi.fn(),
  downloadMock: vi.fn()
}))

vi.mock('../../../resources/js/composables/useApi', () => ({
  useApi: () => ({
    get: getMock,
    post: async () => ({ data: {} }),
    put: async () => ({ data: {} }),
    patch: async () => ({ data: {} }),
    delete: async () => ({ data: {} }),
    download: downloadMock,
    setToken: () => {},
    normalizeError: err => err?.response?.data?.message || ''
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
import Page from '../../../resources/js/modules/business-intelligence/BusinessIntelligencePage.vue'

const dashboardPayload = {
  totalAppointments: 42,
  totalPatients: 27,
  newPatients: 5,
  totalRevenue: 5320.5,
  totalRefunds: 120,
  occupancyRate: 73.4,
  usedHours: 120,
  availableHours: 160,
  appointmentsByDay: [
    { date: '2026-10-18', count: 3 },
    { date: '2026-10-19', count: 5 }
  ],
  revenueByMonth: [
    { month: '2026-09', revenue: 2100, refunds: 0 },
    { month: '2026-10', revenue: 3220.5, refunds: 120 }
  ],
  professionalPerformance: [
    {
      id: 1,
      name: 'Dra. Ana Torres',
      appointments: 10,
      revenue: 2500,
      averagePerAppointment: 250
    }
  ]
}

const appointmentsPayload = {
  data: [
    {
      id: 9,
      patient_name: 'Lucía Ramos',
      professional_name: 'Dra. Ana Torres',
      scheduled_date: '2026-10-19',
      status: 'completed',
      price: 120
    }
  ],
  columns: [
    { key: 'patient_name', label: 'Paciente' },
    { key: 'professional_name', label: 'Profesional' },
    { key: 'scheduled_date', label: 'Fecha' },
    { key: 'status', label: 'Estado' },
    { key: 'price', label: 'Precio (S/)' }
  ]
}

const signInAs = role => {
  localStorage.setItem('auth_token', 'test-token')
  localStorage.setItem('user', JSON.stringify({ id: 1, name: 'Ana Admin', role }))
}

const respondByUrl = url => {
  if (url === '/api/users/active') {
    return Promise.resolve({ data: [{ id: 1, name: 'Dra. Ana Torres' }] })
  }
  if (url === '/api/dental-chairs/active') {
    return Promise.resolve({ data: [{ id: 4, name: 'Sillón 1' }] })
  }
  if (url === '/api/reports/dashboard') {
    return Promise.resolve({ data: dashboardPayload })
  }
  if (url === '/api/reports/appointments') {
    return Promise.resolve(appointmentsPayload)
  }
  return Promise.resolve({ data: [] })
}

const mountPage = async () => {
  const router = createRouter({
    history: createMemoryHistory(),
    routes: [{ path: '/:pathMatch(.*)*', component: { template: '<div />' } }]
  })
  await router.push('/business-intelligence')
  await router.isReady()

  const wrapper = mount(Page, { global: { plugins: [router, uiComponents] } })
  await flushPromises()
  await flushPromises()
  return { wrapper, router }
}

const reportCalls = () =>
  getMock.mock.calls.filter(([url]) => String(url).startsWith('/api/reports/'))

const lastReportCall = () => reportCalls().at(-1)

const selectOption = async (wrapper, containerSelector, label) => {
  const select = wrapper.find(containerSelector)
  await select.find('button').trigger('click')
  await flushPromises()
  const option = select.findAll('button').find(candidate => candidate.text() === label)
  expect(option).toBeTruthy()
  await option.trigger('click')
  await flushPromises()
}

describe('business intelligence page (T7)', () => {
  beforeEach(() => {
    signInAs('administrador')
    getMock.mockReset()
    getMock.mockImplementation(respondByUrl)
    downloadMock.mockReset()
    vi.useFakeTimers({ toFake: ['Date'] })
    vi.setSystemTime(new Date(2026, 9, 19, 12, 0, 0))
  })

  afterEach(() => {
    vi.useRealTimers()
  })

  it('renders the hero, the applied period and the four KPI cards from the payload', async () => {
    const { wrapper } = await mountPage()

    expect(wrapper.find('[data-bi-hero]').exists()).toBe(true)
    expect(wrapper.find('[data-hero-eyebrow]').text()).toBe('Inteligencia de negocio')
    expect(wrapper.find('[data-hero-title]').text()).toBe('Business Intelligence')
    expect(wrapper.find('[data-hero-caption]').text()).toContain('Citas, pacientes')
    expect(wrapper.find('[data-period-applied]').text()).toContain('20/09/2026 — 19/10/2026')
    expect(wrapper.find('[data-period-applied]').text()).toContain('30 días')

    expect(wrapper.findAll('h1')).toHaveLength(1)
    expect(wrapper.find('[data-hero-title]').element.tagName).toBe('H2')

    const kpiCards = wrapper.findAll('[data-kpi-card]')
    expect(kpiCards).toHaveLength(4)
    expect(wrapper.find('[data-kpi-value="appointments"]').text()).toBe('42')
    expect(wrapper.find('[data-kpi-value="patients"]').text()).toBe('27')
    expect(wrapper.find('[data-kpi-caption="patients"]').text()).toContain('5 nuevos')
    expect(wrapper.find('[data-kpi-caption="revenue"]').text()).toContain('Devoluciones')
    expect(wrapper.find('[data-kpi-caption="revenue"]').text()).toMatch(/S\/\s120\.00/)
    expect(wrapper.find('[data-kpi-value="revenue"]').text()).toMatch(/S\/\s5,320\.50/)
    expect(wrapper.find('[data-kpi-value="occupancy"]').text()).toBe('73.4%')
    expect(wrapper.find('[data-kpi-caption="occupancy"]').text()).toContain('120 / 160 h')

    expect(wrapper.find('[data-professional-table]').text()).toContain('Dra. Ana Torres')
    expect(wrapper.find('[data-professional-table]').text()).toMatch(/S\/\s2,500\.00/)
    wrapper.unmount()
  })

  it('renders shared filter controls instead of raw selects and debug noise', async () => {
    const { wrapper } = await mountPage()

    expect(wrapper.findAll('select')).toHaveLength(0)
    expect(wrapper.text()).not.toContain('Debug:')
    expect(wrapper.find('[data-filters-report]').exists()).toBe(true)
    expect(wrapper.find('[data-filters-professional]').exists()).toBe(true)
    expect(wrapper.find('[data-filters-environment]').exists()).toBe(true)
    expect(wrapper.find('[data-bi-filters]').exists()).toBe(true)

    wrapper.unmount()
  })

  it('applies a seven-day preset and re-queries the dashboard with that period', async () => {
    const { wrapper } = await mountPage()

    expect(reportCalls()).toHaveLength(1)
    expect(lastReportCall()[0]).toBe('/api/reports/dashboard')
    expect(lastReportCall()[1].params.start_date).toBe('2026-09-20')
    expect(lastReportCall()[1].params.end_date).toBe('2026-10-19')

    await wrapper.find('[data-preset="7d"]').trigger('click')
    await flushPromises()

    expect(reportCalls()).toHaveLength(2)
    expect(lastReportCall()[1].params.start_date).toBe('2026-10-13')
    expect(lastReportCall()[1].params.end_date).toBe('2026-10-19')
    expect(wrapper.find('[data-period-applied]').text()).toContain('7 días')

    wrapper.unmount()
  })

  it('switches reports through the shared selector and renders the tabular report', async () => {
    const { wrapper } = await mountPage()

    await selectOption(wrapper, '[data-filters-report]', 'Reporte de Citas')

    expect(lastReportCall()[0]).toBe('/api/reports/appointments')
    expect(lastReportCall()[1].params.start_date).toBe('2026-09-20')
    expect(wrapper.find('[data-report-table]').exists()).toBe(true)
    expect(wrapper.find('[data-report-table]').text()).toContain('Lucía Ramos')
    expect(wrapper.find('[data-report-table]').text()).toMatch(/S\/\s120\.00/)

    wrapper.unmount()
  })

  it('sends the selected professional and environment as applied filters', async () => {
    const { wrapper } = await mountPage()

    await selectOption(wrapper, '[data-filters-professional]', 'Dra. Ana Torres')
    await selectOption(wrapper, '[data-filters-environment]', 'Sillón 1')
    await wrapper.find('[data-filters-apply]').trigger('click')
    await flushPromises()

    expect(lastReportCall()[1].params.professional_id).toBe(1)
    expect(lastReportCall()[1].params.environment_id).toBe(4)

    wrapper.unmount()
  })
})
