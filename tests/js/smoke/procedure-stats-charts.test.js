import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { createRouter, createMemoryHistory } from 'vue-router'

// procedure-stats-ui-redesign / T3 — S5 charts: two Chart.js instances built
// from the payload, recreated (previous ones destroyed) on reload, destroyed on
// unmount, and never created when the period has no usage data.
const { getMock, chartMock } = vi.hoisted(() => {
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

  return { getMock: vi.fn(), chartMock: { instances, destroy, register, Chart } }
})

vi.mock('../../../resources/js/composables/useApi', () => ({
  useApi: () => ({
    get: getMock,
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
vi.mock('chart.js', () => ({ Chart: chartMock.Chart, registerables: [{ id: 'stub' }] }))

import uiComponents from '../../../resources/js/plugins/ui-components'
import { colors } from '../../../resources/js/design-system/tokens'
import Page from '../../../resources/js/modules/procedure-catalog/ProcedureStatsPage.vue'

const payload = {
  period: { from: '2026-07-22', to: '2026-10-19' },
  catalog: { total: 42, active: 30, inactive: 12 },
  top_procedures: [
    {
      procedure_id: 7,
      code: 'P-007',
      name: 'Limpieza dental',
      usage_count: 9,
      total_quantity: 12,
      total_revenue: 1200.5
    },
    {
      procedure_id: 8,
      code: 'P-008',
      name: 'Extracción simple',
      usage_count: 4,
      total_quantity: 4,
      total_revenue: 800
    }
  ],
  by_specialty: [
    {
      specialty_id: 1,
      specialty_code: 'ODON',
      specialty_name: 'Odontología general',
      usage_count: 9,
      total_revenue: 1200.5
    },
    {
      specialty_id: null,
      specialty_code: null,
      specialty_name: null,
      usage_count: 1,
      total_revenue: 100
    }
  ]
}

const emptyPayload = {
  ...payload,
  top_procedures: [],
  by_specialty: []
}

const longNamePayload = {
  ...payload,
  top_procedures: [
    {
      procedure_id: 9,
      code: 'P-009',
      name: 'Brackets metálicos, control mensual',
      usage_count: 2,
      total_quantity: 2,
      total_revenue: 360
    }
  ]
}

const signInAs = role => {
  localStorage.setItem('auth_token', 'test-token')
  localStorage.setItem('user', JSON.stringify({ id: 1, name: 'Ana Admin', role }))
}

const mountPage = async () => {
  const router = createRouter({
    history: createMemoryHistory(),
    routes: [{ path: '/:pathMatch(.*)*', component: { template: '<div />' } }]
  })
  await router.push('/procedure-stats')
  await router.isReady()

  const wrapper = mount(Page, { global: { plugins: [router, uiComponents] } })
  await flushPromises()
  await flushPromises()
  return wrapper
}

describe('procedure stats charts (T3 / S5)', () => {
  beforeEach(() => {
    signInAs('administrador')
    getMock.mockReset()
    getMock.mockResolvedValue({ data: payload })
    vi.useFakeTimers({ toFake: ['Date'] })
    vi.setSystemTime(new Date(2026, 9, 19, 12, 0, 0))

    chartMock.instances.length = 0
    chartMock.destroy.mockClear()
    chartMock.register.mockClear()
  })

  afterEach(() => {
    vi.useRealTimers()
  })

  it('renders both canvases and creates one bar and one doughnut from the payload', async () => {
    const wrapper = await mountPage()

    const usageCard = wrapper.find('[data-chart-card="usage"]')
    const specialtyCard = wrapper.find('[data-chart-card="specialty"]')
    expect(usageCard.exists()).toBe(true)
    expect(specialtyCard.exists()).toBe(true)
    expect(usageCard.text()).toContain('Usos por procedimiento')
    expect(specialtyCard.text()).toContain('Participación por especialidad')

    const usageCanvas = usageCard.find('canvas[data-chart="usage"]')
    const specialtyCanvas = specialtyCard.find('canvas[data-chart="specialty"]')
    expect(usageCanvas.attributes('role')).toBe('img')
    expect(usageCanvas.attributes('aria-label')).toBe('Usos por procedimiento')
    expect(specialtyCanvas.attributes('role')).toBe('img')
    expect(specialtyCanvas.attributes('aria-label')).toBe('Participación por especialidad')

    expect(chartMock.instances).toHaveLength(2)
    expect(chartMock.instances.map(instance => instance.config.type)).toEqual(['bar', 'doughnut'])

    const usageChart = chartMock.instances[0].config
    expect(usageChart.data.labels).toEqual(['Limpieza dental', 'Extracción simple'])
    expect(usageChart.data.datasets[0].data).toEqual([9, 4])
    expect(usageChart.options.indexAxis).toBe('y')
    expect(usageChart.options.responsive).toBe(true)
    expect(usageChart.options.maintainAspectRatio).toBe(false)
    expect(usageChart.options.plugins.legend.display).toBe(false)

    const specialtyChart = chartMock.instances[1].config
    expect(specialtyChart.data.labels).toEqual(['Odontología general', 'Sin especialidad'])
    expect(specialtyChart.data.datasets[0].data).toEqual([9, 1])
    expect(specialtyChart.data.datasets[0].backgroundColor.slice(0, 2)).toEqual([
      colors.accent[500],
      colors.systemIndigo[500]
    ])

    // The tabular data is never replaced by the charts.
    expect(wrapper.find('[data-top-procedures]').exists()).toBe(true)
    expect(wrapper.find('[data-specialty-distribution]').exists()).toBe(true)

    wrapper.unmount()
  })

  it('destroys the previous instances and recreates both on a period change', async () => {
    const wrapper = await mountPage()

    expect(chartMock.instances).toHaveLength(2)
    expect(chartMock.destroy).not.toHaveBeenCalled()

    await wrapper.find('[data-preset="7d"]').trigger('click')
    await flushPromises()

    expect(chartMock.destroy).toHaveBeenCalledTimes(2)
    expect(chartMock.instances).toHaveLength(4)
    expect(chartMock.instances.slice(2).map(instance => instance.config.type)).toEqual([
      'bar',
      'doughnut'
    ])
    expect(chartMock.register).toHaveBeenCalledTimes(2)

    wrapper.unmount()
  })

  it('destroys both instances on unmount', async () => {
    const wrapper = await mountPage()

    expect(chartMock.instances).toHaveLength(2)
    expect(chartMock.destroy).not.toHaveBeenCalled()

    wrapper.unmount()

    expect(chartMock.destroy).toHaveBeenCalledTimes(2)
  })

  it('shortens the axis labels only when the rendered canvas is narrow', async () => {
    getMock.mockResolvedValue({ data: longNamePayload })

    const setCanvasWidth = width =>
      Object.defineProperty(HTMLCanvasElement.prototype, 'clientWidth', {
        configurable: true,
        get: () => width
      })

    // jsdom has no layout: a canvas reads 0 without an explicit width, and 0
    // must fall back to the desktop budget instead of the narrow one. 419/421
    // pin the threshold itself, not just values far away from it.
    const cases = [
      [320, 'Brackets metáli…'],
      [419, 'Brackets metáli…'],
      [421, 'Brackets metálicos, control…'],
      [800, 'Brackets metálicos, control…']
    ]

    try {
      for (const [width, label] of cases) {
        setCanvasWidth(width)
        const wrapper = await mountPage()
        expect(chartMock.instances[0].config.data.labels).toEqual([label])
        wrapper.unmount()
        chartMock.instances.length = 0
      }
    } finally {
      delete HTMLCanvasElement.prototype.clientWidth
    }
  })

  it('re-derives the label budget when a resize crosses the threshold', async () => {
    getMock.mockResolvedValue({ data: longNamePayload })

    const wrapper = await mountPage()
    const usageConfig = chartMock.instances[0].config
    const chart = { data: { labels: [...usageConfig.data.labels] }, update: vi.fn() }
    const resizeTo = width => usageConfig.options.onResize(chart, { width })

    // jsdom has no layout, so the first paint keeps the desktop budget.
    expect(chart.data.labels).toEqual(['Brackets metálicos, control…'])

    resizeTo(419)
    expect(chart.data.labels).toEqual(['Brackets metáli…'])
    expect(chart.update).toHaveBeenCalledTimes(1)

    resizeTo(500)
    expect(chart.data.labels).toEqual(['Brackets metálicos, control…'])
    expect(chart.update).toHaveBeenCalledTimes(2)

    // Staying inside the same band must not redraw the chart.
    resizeTo(600)
    expect(chart.update).toHaveBeenCalledTimes(2)

    wrapper.unmount()
  })

  it('creates no charts and no canvases when the period is empty', async () => {
    getMock.mockResolvedValue({ data: emptyPayload })

    const wrapper = await mountPage()

    expect(wrapper.find('[data-period-empty]').exists()).toBe(true)
    expect(wrapper.find('[data-chart-card="usage"]').exists()).toBe(false)
    expect(wrapper.find('[data-chart-card="specialty"]').exists()).toBe(false)
    expect(chartMock.instances).toHaveLength(0)

    wrapper.unmount()
  })
})
