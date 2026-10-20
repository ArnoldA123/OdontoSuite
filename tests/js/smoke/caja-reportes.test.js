import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'

// cash-register-interface-redesign / T9 — S1, S6, D7, D14, F7, F10, G1, G2, G14.
// CashReports owns its period payload: KPIs, payment-method mix, daily summary,
// export through useApi and a retryable error state. No summary prop, no
// fetch + localStorage, no window.print.
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
    normalizeError: () => ''
  })
}))

import uiComponents from '../../../resources/js/plugins/ui-components'
import CashReports from '../../../resources/js/modules/cash-register/components/CashReports.vue'

const reportPayload = {
  period: { start_date: '2026-10-01', end_date: '2026-10-30' },
  sessions_count: 12,
  total_opening_amount: 1500.5,
  total_closing_amount: 1800.25,
  total_expected_amount: 1750,
  total_difference: -12.5,
  average_difference: -1.04,
  total_income: 4321.5,
  total_expenses: 210.75,
  daily_summary: [
    {
      date: '2026-10-01',
      sessions_count: 2,
      total_opening: 200,
      total_closing: 320.5,
      total_difference: 0
    }
  ],
  payment_methods_analysis: [
    {
      payment_method: 'Efectivo',
      count: 4,
      total: 540,
      average: 135,
      percentage: 12.5
    }
  ],
  summary_by_branch: [],
  summary_by_user: []
}

const mountReports = () => mount(CashReports, { global: { plugins: [uiComponents] } })

const originalCreateObjectURL = URL.createObjectURL
const originalRevokeObjectURL = URL.revokeObjectURL

let clicks = []
let createObjectURL
let revokeObjectURL
let clickSpy
let printSpy

describe('cash reports (T9)', () => {
  beforeEach(() => {
    vi.useFakeTimers({ toFake: ['Date'] })
    vi.setSystemTime(new Date(2026, 9, 30, 12, 0, 0))

    getMock.mockReset()
    getMock.mockResolvedValue({ data: reportPayload })
    downloadMock.mockReset()
    downloadMock.mockImplementation(async url => ({
      blob: new Blob(['report']),
      filename: `caja-2026-10-01-2026-10-30.${url.split('/').pop()}`
    }))

    clicks = []
    createObjectURL = vi.fn(() => 'blob:http://localhost:3000/caja-reportes')
    revokeObjectURL = vi.fn()
    URL.createObjectURL = createObjectURL
    URL.revokeObjectURL = revokeObjectURL
    clickSpy = vi
      .spyOn(HTMLAnchorElement.prototype, 'click')
      .mockImplementation(function handleClick() {
        clicks.push({ href: this.href, download: this.download })
      })
    printSpy = vi.spyOn(window, 'print').mockImplementation(() => {})
  })

  afterEach(() => {
    vi.useRealTimers()
    clickSpy.mockRestore()
    printSpy.mockRestore()
    if (originalCreateObjectURL === undefined) {
      delete URL.createObjectURL
    } else {
      URL.createObjectURL = originalCreateObjectURL
    }
    if (originalRevokeObjectURL === undefined) {
      delete URL.revokeObjectURL
    } else {
      URL.revokeObjectURL = originalRevokeObjectURL
    }
  })

  it('requests the default 30-day period and renders KPIs from the period payload', async () => {
    const wrapper = mountReports()

    expect(wrapper.find('[data-report-kpi="sessions"]').text()).toBe('N/D')
    expect(wrapper.find('[data-report-kpi="income"]').text()).toBe('N/D')

    await flushPromises()

    expect(getMock).toHaveBeenCalledWith('/api/cash-register/reports/period', {
      params: { start_date: '2026-10-01', end_date: '2026-10-30' }
    })
    expect(wrapper.find('[data-report-kpi="sessions"]').text()).toBe('12')
    expect(wrapper.find('[data-report-kpi="opening"]').text()).toContain('1,500.50')
    expect(wrapper.find('[data-report-kpi="closing"]').text()).toContain('1,800.25')
    expect(wrapper.find('[data-report-kpi="difference"]').text()).toContain('12.50')
    expect(wrapper.find('[data-report-kpi="income"]').text()).toContain('4,321.50')
    expect(wrapper.find('[data-report-kpi="expenses"]').text()).toContain('210.75')
    expect(wrapper.text()).not.toContain('N/D')

    wrapper.unmount()
  })

  it('renders the payment-method mix with the period percentage', async () => {
    const wrapper = mountReports()
    await flushPromises()

    const table = wrapper.find('[data-report-table="payment-methods"]')
    expect(table.exists()).toBe(true)
    expect(table.text()).toContain('Efectivo')
    expect(table.text()).toContain('12.5%')
    expect(table.text()).toContain('135.00')

    const daily = wrapper.find('[data-report-table="daily"]')
    expect(daily.exists()).toBe(true)
    expect(daily.text()).toContain('01/10/2026')

    expect(wrapper.find('[data-report-table="summary-branches"]').exists()).toBe(false)
    expect(wrapper.find('[data-report-table="summary-users"]').exists()).toBe(false)

    wrapper.unmount()
  })

  it('surfaces a failed request as an alert with a working retry', async () => {
    getMock.mockRejectedValueOnce({
      response: { data: { message: 'No se pudo generar el reporte' } }
    })

    const wrapper = mountReports()
    await flushPromises()

    const alert = wrapper.find('[data-report-error]')
    expect(alert.exists()).toBe(true)
    expect(alert.attributes('role')).toBe('alert')
    expect(alert.text()).toContain('No se pudo generar el reporte')
    expect(wrapper.find('[data-report-kpi="sessions"]').text()).toBe('N/D')
    expect(wrapper.find('[data-report-kpi="income"]').text()).toBe('N/D')

    await wrapper.find('[data-report-retry]').trigger('click')
    await flushPromises()

    expect(getMock).toHaveBeenCalledTimes(2)
    expect(wrapper.find('[data-report-error]').exists()).toBe(false)
    expect(wrapper.find('[data-report-kpi="sessions"]').text()).toBe('12')

    wrapper.unmount()
  })

  it('exports the applied period through useApi without printing the page', async () => {
    const wrapper = mountReports()
    await flushPromises()

    // Draft changes are not applied: the export must use the applied period.
    await wrapper.find('#cash-reports-start').setValue('2026-09-01')
    await wrapper.find('#cash-reports-end').setValue('2026-09-15')
    await flushPromises()

    await wrapper.find('[data-export="csv"]').trigger('click')
    await flushPromises()

    expect(downloadMock).toHaveBeenCalledTimes(1)
    expect(downloadMock).toHaveBeenCalledWith('/api/cash-register/reports/export/csv', {
      method: 'POST',
      data: { start_date: '2026-10-01', end_date: '2026-10-30', report_type: 'period' },
      fallbackFilename: 'caja-2026-10-01-2026-10-30.csv'
    })
    expect(clicks).toEqual([
      {
        href: 'blob:http://localhost:3000/caja-reportes',
        download: 'caja-2026-10-01-2026-10-30.csv'
      }
    ])

    await wrapper.find('[data-export="pdf"]').trigger('click')
    await flushPromises()
    await wrapper.find('[data-export="excel"]').trigger('click')
    await flushPromises()

    expect(downloadMock).toHaveBeenNthCalledWith(
      2,
      '/api/cash-register/reports/export/pdf',
      expect.objectContaining({ method: 'POST' })
    )
    expect(downloadMock).toHaveBeenNthCalledWith(
      3,
      '/api/cash-register/reports/export/excel',
      expect.objectContaining({ method: 'POST' })
    )
    expect(printSpy).not.toHaveBeenCalled()

    wrapper.unmount()
  })

  it('applies the 7-day preset and no longer declares a summary prop', async () => {
    const wrapper = mountReports()
    await flushPromises()

    await wrapper.find('[data-preset="7"]').trigger('click')
    await flushPromises()

    expect(getMock).toHaveBeenLastCalledWith('/api/cash-register/reports/period', {
      params: { start_date: '2026-10-24', end_date: '2026-10-30' }
    })

    expect(CashReports.props?.summary).toBeUndefined()

    wrapper.unmount()
  })
})
