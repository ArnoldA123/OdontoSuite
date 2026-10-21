import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { createRouter, createMemoryHistory } from 'vue-router'

// business-intelligence-ui-redesign / T8 — Chart.js contract of the
// /business-intelligence page: two dashboard charts built from the payload, one
// "Por Día" revenue cut, recreated (previous instances destroyed) on refresh or
// report switch, destroyed on unmount, and never mounted without data.
const { getMock, downloadMock, chartMock } = vi.hoisted(() => {
  const instances = []
  const destroy = vi.fn()
  const register = vi.fn()

  class Chart {
    constructor(context, config) {
      this.context = context
      this.config = config
      this.destroy = destroy
      instances.push(this)
    }

    static register(...args) {
      register(...args)
    }
  }

  return {
    getMock: vi.fn(),
    downloadMock: vi.fn(),
    chartMock: { instances, destroy, register, Chart }
  }
})

vi.mock('../../../resources/js/composables/useApi', () => ({
  useApi: () => ({
    get: getMock,
    post: async () => ({ data: {} }),
    put: async () => ({ data: {} }),
    patch: async () => ({ data: {} }),
    delete: async () => ({ data: {} }),
    download: downloadMock,
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
vi.mock('chart.js', () => ({ Chart: chartMock.Chart, registerables: [{ id: 'stub' }] }))

import uiComponents from '../../../resources/js/plugins/ui-components'
import { colors } from '../../../resources/js/design-system/tokens'
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
    { month: '2026-09', revenue: 2100 },
    { month: '2026-10', revenue: 3220.5 }
  ],
  professionalPerformance: []
}

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
      type: 'Por Día',
      category: '2026-10-19',
      appointments_count: 2,
      total_revenue: 900,
      average_per_appointment: 450
    }
  ],
  columns: [
    { key: 'type', label: 'Tipo' },
    { key: 'category', label: 'Categoría' },
    { key: 'total_revenue', label: 'Ingresos netos (S/)' },
    { key: 'refunds', label: 'Devoluciones (S/)' }
  ],
  meta: {
    message: 'ok',
    summary: {
      appointments_count: 3,
      total_revenue: 1600,
      average_per_appointment: 533.33,
      period: '20/09/2026 - 19/10/2026'
    }
  }
}

const appointmentsPayload = {
  data: [
    {
      id: 9,
      patient_name: 'Lucía Ramos',
      professional_name: 'Dra. Ana Torres',
      status: 'completed'
    }
  ],
  columns: [
    { key: 'patient_name', label: 'Paciente' },
    { key: 'professional_name', label: 'Profesional' },
    { key: 'status', label: 'Estado' }
  ]
}

const emptyDashboardPayload = {
  ...dashboardPayload,
  appointmentsByDay: [],
  revenueByMonth: [],
  professionalPerformance: []
}

const signInAs = role => {
  localStorage.setItem('auth_token', 'test-token')
  localStorage.setItem('user', JSON.stringify({ id: 1, name: 'Ana Admin', role }))
}

const respondByUrl = dashboard => {
  const payload = dashboard ?? dashboardPayload
  return url => {
    if (url === '/api/users/active' || url === '/api/dental-chairs/active') {
      return Promise.resolve({ data: [] })
    }
    if (url === '/api/reports/dashboard') {
      return Promise.resolve({ data: payload })
    }
    if (url === '/api/reports/revenue') {
      return Promise.resolve(revenuePayload)
    }
    if (url === '/api/reports/appointments') {
      return Promise.resolve(appointmentsPayload)
    }
    return Promise.resolve({ data: [] })
  }
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

describe('business intelligence charts (T8)', () => {
  beforeEach(() => {
    signInAs('administrador')
    getMock.mockReset()
    getMock.mockImplementation(respondByUrl())
    chartMock.instances.length = 0
    chartMock.destroy.mockClear()
    chartMock.register.mockClear()
    vi.useFakeTimers({ toFake: ['Date'] })
    vi.setSystemTime(new Date(2026, 9, 19, 12, 0, 0))
  })

  afterEach(() => {
    vi.useRealTimers()
  })

  it('renders both dashboard canvases and builds them from the payload with token colours', async () => {
    const wrapper = await mountPage()

    const appointmentsCanvas = wrapper.find('canvas[data-chart="appointments-by-day"]')
    const revenueCanvas = wrapper.find('canvas[data-chart="revenue-by-month"]')
    expect(appointmentsCanvas.exists()).toBe(true)
    expect(revenueCanvas.exists()).toBe(true)
    expect(appointmentsCanvas.attributes('role')).toBe('img')
    expect(appointmentsCanvas.attributes('aria-label')).toBe('Citas por día')
    expect(revenueCanvas.attributes('role')).toBe('img')
    expect(revenueCanvas.attributes('aria-label')).toBe('Ingresos netos por mes')
    expect(appointmentsCanvas.element.closest('.h-64')).toBeTruthy()
    expect(revenueCanvas.element.closest('.h-64')).toBeTruthy()

    expect(chartMock.instances).toHaveLength(2)
    expect(chartMock.instances.map(instance => instance.config.type)).toEqual(['line', 'bar'])

    const appointmentsConfig = chartMock.instances[0].config
    expect(appointmentsConfig.data.labels).toEqual(['2026-10-18', '2026-10-19'])
    expect(appointmentsConfig.data.datasets[0].data).toEqual([3, 5])
    expect(appointmentsConfig.data.datasets[0].borderColor).toBe(colors.systemIndigo[500])
    expect(appointmentsConfig.options.responsive).toBe(true)
    expect(appointmentsConfig.options.maintainAspectRatio).toBe(false)

    const revenueConfig = chartMock.instances[1].config
    expect(revenueConfig.data.labels).toEqual(['2026-09', '2026-10'])
    expect(revenueConfig.data.datasets[0].data).toEqual([2100, 3220.5])
    expect(revenueConfig.data.datasets[0].backgroundColor).toBe(`${colors.systemGreen[500]}cc`)
    expect(revenueConfig.options.responsive).toBe(true)
    expect(revenueConfig.options.maintainAspectRatio).toBe(false)

    wrapper.unmount()
  })

  it('destroys the previous instances and recreates both on a refresh', async () => {
    const wrapper = await mountPage()

    expect(chartMock.instances).toHaveLength(2)
    expect(chartMock.destroy).not.toHaveBeenCalled()

    await wrapper.find('[data-hero-refresh]').trigger('click')
    await flushPromises()

    expect(chartMock.destroy).toHaveBeenCalledTimes(2)
    expect(chartMock.instances).toHaveLength(4)
    expect(chartMock.instances.slice(2).map(instance => instance.config.type)).toEqual([
      'line',
      'bar'
    ])
    expect(chartMock.register).toHaveBeenCalledTimes(2)

    wrapper.unmount()
  })

  it('switches to a single revenue chart built only from the Por Día rows', async () => {
    const wrapper = await mountPage()

    expect(chartMock.instances).toHaveLength(2)

    await selectOption(wrapper, '[data-filters-report]', 'Reporte de Ingresos')

    expect(chartMock.destroy).toHaveBeenCalledTimes(2)
    expect(chartMock.instances).toHaveLength(3)

    const revenueChart = chartMock.instances[2].config
    expect(revenueChart.type).toBe('bar')
    expect(revenueChart.data.labels).toEqual(['2026-10-18', '2026-10-19'])
    expect(revenueChart.data.datasets[0].data).toEqual([700, 900])
    expect(revenueChart.data.datasets[0].backgroundColor).toBe(`${colors.accent[500]}cc`)

    const chartCanvas = wrapper.find('canvas[data-chart="revenue-by-day"]')
    expect(chartCanvas.attributes('role')).toBe('img')
    expect(chartCanvas.attributes('aria-label')).toBe('Ingresos netos por día')
    expect(wrapper.find('canvas[data-chart="appointments-by-day"]').exists()).toBe(false)

    wrapper.unmount()
  })

  it('renders no chart for a tabular report without a daily cut', async () => {
    const wrapper = await mountPage()

    expect(chartMock.instances).toHaveLength(2)

    await selectOption(wrapper, '[data-filters-report]', 'Reporte de Citas')

    expect(chartMock.destroy).toHaveBeenCalledTimes(2)
    expect(chartMock.instances).toHaveLength(2)
    expect(wrapper.find('[data-bi-charts]').exists()).toBe(false)
    expect(wrapper.find('canvas[data-chart]').exists()).toBe(false)

    wrapper.unmount()
  })

  it('destroys the instances on unmount', async () => {
    const wrapper = await mountPage()

    expect(chartMock.instances).toHaveLength(2)
    expect(chartMock.destroy).not.toHaveBeenCalled()

    wrapper.unmount()

    expect(chartMock.destroy).toHaveBeenCalledTimes(2)
  })

  it('mounts no canvas and creates no chart when the period is empty', async () => {
    getMock.mockImplementation(respondByUrl(emptyDashboardPayload))

    const wrapper = await mountPage()

    expect(wrapper.find('[data-report-empty]').exists()).toBe(true)
    expect(wrapper.find('[data-bi-charts]').exists()).toBe(false)
    expect(wrapper.find('canvas[data-chart]').exists()).toBe(false)
    expect(chartMock.instances).toHaveLength(0)

    wrapper.unmount()
  })
})
