import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { createRouter, createMemoryHistory } from 'vue-router'

// business-intelligence-ui-redesign / T7 + T9 — data states and export of the
// redesigned /business-intelligence page: tabular reports, revenue summary,
// retryable errors without stale data, empty period, skeletons and the
// applied-period export through useApi().download.
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
import { useToast } from '../../../resources/js/composables/useToast'
import Page from '../../../resources/js/modules/business-intelligence/BusinessIntelligencePage.vue'

const dashboardPayload = {
  totalAppointments: 42,
  totalPatients: 27,
  newPatients: 5,
  totalRevenue: 5320.5,
  occupancyRate: 73.4,
  usedHours: 120,
  availableHours: 160,
  appointmentsByDay: [{ date: '2026-10-19', count: 5 }],
  revenueByMonth: [{ month: '2026-10', revenue: 3220.5 }],
  professionalPerformance: []
}

const appointmentsColumns = [
  { key: 'patient_name', label: 'Paciente' },
  { key: 'professional_name', label: 'Profesional' },
  { key: 'status', label: 'Estado' }
]

const appointmentsPayload = {
  data: [
    {
      id: 9,
      patient_name: 'Lucía Ramos',
      professional_name: 'Dra. Ana Torres',
      status: 'completed'
    }
  ],
  columns: appointmentsColumns
}

const emptyAppointmentsPayload = { data: [], columns: appointmentsColumns }

const revenuePayload = {
  data: [
    {
      type: 'Por Tipo de Cita',
      category: 'Limpieza dental',
      appointments_count: 2,
      total_revenue: 1200.5,
      average_per_appointment: 600.25
    },
    {
      type: 'Por Día',
      category: '2026-10-18',
      appointments_count: 1,
      total_revenue: 700,
      average_per_appointment: 700
    },
    {
      type: 'Sin cita asociada',
      category: 'Pagos sin cita resoluble',
      appointments_count: 0,
      total_revenue: 300,
      average_per_appointment: 0
    }
  ],
  columns: [
    { key: 'type', label: 'Tipo' },
    { key: 'category', label: 'Categoría' },
    { key: 'appointments_count', label: 'Número de Citas' },
    { key: 'total_revenue', label: 'Ingresos Cobrados (S/)' },
    { key: 'average_per_appointment', label: 'Promedio por Cita (S/)' }
  ],
  meta: {
    message: 'Revenue report retrieved successfully',
    summary: {
      appointments_count: 3,
      total_revenue: 1900.5,
      average_per_appointment: 633.5,
      period: '20/09/2026 - 19/10/2026'
    }
  }
}

const signInAs = role => {
  localStorage.setItem('auth_token', 'test-token')
  localStorage.setItem('user', JSON.stringify({ id: 1, name: 'Ana Admin', role }))
}

const baseResponses = url => {
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
  if (url === '/api/reports/revenue') {
    return Promise.resolve(revenuePayload)
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
  return wrapper
}

const selectOption = async (wrapper, containerSelector, label) => {
  const select = wrapper.find(containerSelector)
  await select.find('button').trigger('click')
  await flushPromises()
  const option = select.findAll('button').find(candidate => candidate.text() === label)
  expect(option).toBeTruthy()
  await option.trigger('click')
  await flushPromises()
}

const lastReportCall = () =>
  getMock.mock.calls.filter(([url]) => String(url).startsWith('/api/reports/')).at(-1)

describe('business intelligence reports and states (T7)', () => {
  beforeEach(() => {
    signInAs('administrador')
    getMock.mockReset()
    getMock.mockImplementation(baseResponses)
    downloadMock.mockReset()
    useToast().clearAll()
    vi.useFakeTimers({ toFake: ['Date'] })
    vi.setSystemTime(new Date(2026, 9, 19, 12, 0, 0))
  })

  afterEach(() => {
    vi.useRealTimers()
    vi.restoreAllMocks()
  })

  it('shows skeletons and aria-busy while the applied period is loading', async () => {
    let resolveDashboard
    const dashboardPromise = new Promise(resolve => {
      resolveDashboard = resolve
    })

    getMock.mockImplementation(url =>
      url === '/api/reports/dashboard' ? dashboardPromise : baseResponses(url)
    )

    const wrapper = await mountPage()

    expect(wrapper.find('[data-bi-loading]').exists()).toBe(true)
    expect(wrapper.find('[data-bi-loading]').attributes('aria-busy')).toBe('true')
    expect(wrapper.find('[aria-busy="true"]').exists()).toBe(true)
    expect(wrapper.findAll('[data-kpi-card]')).toHaveLength(0)
    expect(wrapper.findAll('[data-bi-loading] .skeleton-item').length).toBeGreaterThan(0)

    resolveDashboard({ data: dashboardPayload })
    await flushPromises()
    await flushPromises()

    expect(wrapper.find('[data-bi-loading]').exists()).toBe(false)
    expect(wrapper.find('[data-kpi-value="appointments"]').text()).toBe('42')

    wrapper.unmount()
  })

  it('renders a retryable error state and never leaves the previous table mounted', async () => {
    let failAppointments = false

    getMock.mockImplementation(url => {
      if (url === '/api/reports/appointments') {
        if (failAppointments) {
          return Promise.reject({ status: 500, response: { data: { message: 'boom' } } })
        }
        return Promise.resolve(appointmentsPayload)
      }
      return baseResponses(url)
    })

    const wrapper = await mountPage()

    await selectOption(wrapper, '[data-filters-report]', 'Reporte de Citas')
    expect(wrapper.find('[data-report-table]').text()).toContain('Lucía Ramos')

    failAppointments = true
    await wrapper.find('[data-filters-apply]').trigger('click')
    await flushPromises()

    const alert = wrapper.find('[data-state="error"]')
    expect(alert.exists()).toBe(true)
    expect(alert.attributes('role')).toBe('alert')
    expect(alert.text()).toContain('No pudimos cargar el reporte')
    expect(alert.text()).toContain('boom')
    expect(wrapper.find('[data-report-table]').exists()).toBe(false)
    expect(wrapper.find('[data-report-empty]').exists()).toBe(false)

    failAppointments = false
    await wrapper.find('[data-retry]').trigger('click')
    await flushPromises()

    expect(wrapper.find('[data-state="error"]').exists()).toBe(false)
    expect(wrapper.find('[data-report-table]').text()).toContain('Lucía Ramos')

    wrapper.unmount()
  })

  it('renders the shared empty state and widens the period to ninety days', async () => {
    getMock.mockImplementation(url =>
      url === '/api/reports/appointments'
        ? Promise.resolve(emptyAppointmentsPayload)
        : baseResponses(url)
    )

    const wrapper = await mountPage()

    await selectOption(wrapper, '[data-filters-report]', 'Reporte de Citas')

    expect(wrapper.find('[data-report-empty]').exists()).toBe(true)
    expect(wrapper.text()).toContain('Sin datos en el período')
    expect(wrapper.find('[data-report-table]').exists()).toBe(false)

    await wrapper.find('[data-widen-period]').trigger('click')
    await flushPromises()

    expect(lastReportCall()[1].params.start_date).toBe('2026-07-22')
    expect(lastReportCall()[1].params.end_date).toBe('2026-10-19')
    expect(wrapper.find('[data-period-applied]').text()).toContain('90 días')

    wrapper.unmount()
  })

  it('renders the revenue summary and only one revenue cut at a time', async () => {
    const wrapper = await mountPage()

    await selectOption(wrapper, '[data-filters-report]', 'Reporte de Ingresos')

    expect(lastReportCall()[0]).toBe('/api/reports/revenue')
    expect(wrapper.find('[data-revenue-summary="appointments"]').text()).toBe('3')
    expect(wrapper.find('[data-revenue-summary="revenue"]').text()).toMatch(/S\/\s1,900\.50/)
    expect(wrapper.find('[data-revenue-summary="average"]').text()).toMatch(/S\/\s633\.50/)

    // The four cuts describe the same money; showing them together invites
    // adding the column and quadrupling the total.
    const defaultRows = wrapper.findAll('[data-report-table] tbody tr')
    expect(defaultRows).toHaveLength(1)
    expect(defaultRows[0].text()).toContain('Por Tipo de Cita')
    expect(defaultRows[0].text()).toMatch(/S\/\s1,200\.50/)
    expect(wrapper.find('[data-report-table]').text()).not.toContain('2026-10-18')

    await wrapper.find('[data-revenue-dimension="Por Día"]').trigger('click')
    await flushPromises()

    const dayRows = wrapper.findAll('[data-report-table] tbody tr')
    expect(dayRows).toHaveLength(1)
    expect(dayRows[0].text()).toContain('2026-10-18')
    expect(dayRows[0].text()).toMatch(/S\/\s700\.00/)

    await wrapper.find('[data-revenue-dimension="Sin cita asociada"]').trigger('click')
    await flushPromises()

    const residualRows = wrapper.findAll('[data-report-table] tbody tr')
    expect(residualRows).toHaveLength(1)
    expect(residualRows[0].text()).toMatch(/S\/\s300\.00/)

    wrapper.unmount()
  })

  it('exports the applied period, not the draft filters, through the api client', async () => {
    const fetchSpy = vi.spyOn(globalThis, 'fetch')
    downloadMock.mockResolvedValue({ blob: {}, filename: 'dashboard.csv' })

    const wrapper = await mountPage()

    await wrapper.find('#bi-from').setValue('2026-10-31')
    await flushPromises()

    await wrapper.find('[data-export-csv]').trigger('click')
    await flushPromises()

    expect(downloadMock).toHaveBeenCalledTimes(1)
    const [url, options] = downloadMock.mock.calls[0]
    expect(url).toBe('/api/reports/dashboard/export')
    expect(options.params.format).toBe('csv')
    expect(options.params.start_date).toBe('2026-09-20')
    expect(options.params.end_date).toBe('2026-10-19')
    expect(options.params.start_date).not.toBe('2026-10-31')
    expect(fetchSpy).not.toHaveBeenCalled()

    wrapper.unmount()
  })

  it('shows a toast when the export fails and never bypasses the api client', async () => {
    const fetchSpy = vi.spyOn(globalThis, 'fetch')
    downloadMock.mockRejectedValue({
      status: 500,
      response: { data: { message: 'No se pudo generar el archivo' } }
    })

    const wrapper = await mountPage()
    const { toasts } = useToast()

    await wrapper.find('[data-export-pdf]').trigger('click')
    await flushPromises()

    expect(downloadMock).toHaveBeenCalledTimes(1)
    expect(downloadMock.mock.calls[0][1].params.format).toBe('pdf')
    expect(toasts.value.at(-1)?.message).toContain('No se pudo generar el archivo')
    expect(fetchSpy).not.toHaveBeenCalled()

    wrapper.unmount()
  })
})

describe('useApi download contract (T9)', () => {
  beforeEach(() => {
    localStorage.setItem('auth_token', 'token-123')
  })

  afterEach(() => {
    vi.restoreAllMocks()
    localStorage.removeItem('auth_token')
  })

  it('returns the blob and the Content-Disposition filename with the auth header', async () => {
    const { useApi } = await vi.importActual('../../../resources/js/composables/useApi')
    const fetchMock = vi.spyOn(globalThis, 'fetch').mockImplementation(
      async () =>
        new Response(new Blob(['csv-bytes']), {
          status: 200,
          headers: { 'content-disposition': 'attachment; filename="revenue.csv"' }
        })
    )

    const { download } = useApi()
    const result = await download('/api/reports/revenue/export', {
      params: { format: 'csv', start_date: '2026-09-20' }
    })

    expect(fetchMock).toHaveBeenCalledTimes(1)
    const [url, init] = fetchMock.mock.calls[0]
    expect(url).toContain('/api/reports/revenue/export?')
    expect(url).toContain('format=csv')
    expect(url).toContain('start_date=2026-09-20')
    expect(init.headers.Authorization).toBe('Bearer token-123')
    expect(result.filename).toBe('revenue.csv')
    expect(result.blob).toBeInstanceOf(Blob)
  })

  it('rejects with the normalized error shape on a failed download', async () => {
    const { useApi } = await vi.importActual('../../../resources/js/composables/useApi')
    vi.spyOn(globalThis, 'fetch').mockImplementation(
      async () =>
        new Response(JSON.stringify({ message: 'Tipo de reporte no soportado.' }), {
          status: 422,
          headers: { 'content-type': 'application/json' }
        })
    )

    const { download, normalizeError } = useApi()

    await expect(download('/api/reports/unknown/export')).rejects.toMatchObject({
      status: 422,
      response: { data: { message: 'Tipo de reporte no soportado.' } }
    })

    try {
      await download('/api/reports/unknown/export')
    } catch (error) {
      expect(normalizeError(error)).toBe('Tipo de reporte no soportado.')
    }
  })
})
