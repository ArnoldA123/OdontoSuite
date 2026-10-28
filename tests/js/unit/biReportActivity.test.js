import { describe, it, expect } from 'vitest'
import {
  isReportPeriodEmpty,
  summaryShowsActivity
} from '../../../resources/js/modules/business-intelligence/reportScope.js'

// #447 S1/S2 — the empty period reads the ACTIVITY of the applied period, not
// the size of the table. Three of the tabular services return their whole
// census and only narrow the counters, and the dashboard always ships
// `professionalPerformance`, so `rows.length` could never report an idle
// period.
const rows = [{ id: 1, name: 'Lucía Ramos' }]
const idleSummary = { appointments: 0, revenue: 0 }
const activeSummary = { appointments: 4, revenue: 1200 }

describe('business intelligence period activity (#447 S1)', () => {
  it('reads the activity a summary reports, or null when it cannot tell', () => {
    expect(summaryShowsActivity(idleSummary)).toBe(false)
    expect(summaryShowsActivity({ appointments: 1, revenue: 0 })).toBe(true)
    expect(summaryShowsActivity({ appointments: 0, revenue: 250.5 })).toBe(true)
    expect(summaryShowsActivity({ appointments: '3', revenue: '0' })).toBe(true)

    // No summary at all is a pre-#447 payload: the caller must not read it as
    // "empty", it falls back to the row count.
    expect(summaryShowsActivity(null)).toBeNull()
    expect(summaryShowsActivity(undefined)).toBeNull()
    expect(summaryShowsActivity({ message: 'ok' })).toBeNull()
    // A summary that carries only one of the two counters is not conclusive.
    expect(summaryShowsActivity({ appointments: 0 })).toBeNull()
    expect(summaryShowsActivity({ revenue: 0 })).toBeNull()
  })

  it('reads the revenue report summary, which keeps its own key names', () => {
    expect(
      summaryShowsActivity({
        appointments_count: 0,
        total_revenue: 0,
        refunds: 0,
        average_per_appointment: 0
      })
    ).toBe(false)
    expect(summaryShowsActivity({ appointments_count: 2, total_revenue: 900 })).toBe(true)
    expect(summaryShowsActivity({ appointments_count: 0, total_revenue: 120 })).toBe(true)
  })

  it('calls an idle dashboard empty even when it still ships professional rows', () => {
    const empty = isReportPeriodEmpty({
      report: 'dashboard',
      dashboard: {
        totalAppointments: 0,
        totalRevenue: 0,
        professionalPerformance: rows
      }
    })

    expect(empty).toBe(true)
    expect(
      isReportPeriodEmpty({
        report: 'dashboard',
        dashboard: { totalAppointments: 1, totalRevenue: 0 }
      })
    ).toBe(false)
    expect(
      isReportPeriodEmpty({
        report: 'dashboard',
        dashboard: { totalAppointments: 0, totalRevenue: 0.5 }
      })
    ).toBe(false)
    expect(isReportPeriodEmpty({ report: 'dashboard' })).toBe(true)
  })

  it('calls an idle tabular period empty even when its census still has rows', () => {
    for (const report of ['patients', 'professionals', 'utilization']) {
      expect(isReportPeriodEmpty({ report, rows, summary: idleSummary })).toBe(true)
      expect(isReportPeriodEmpty({ report, rows, summary: activeSummary })).toBe(false)
    }
  })

  it('keeps the activity rule even when the report has no rows at all', () => {
    // The census can be narrowed to nothing while the period did have activity.
    expect(isReportPeriodEmpty({ report: 'patients', rows: [], summary: activeSummary })).toBe(
      false
    )
    expect(isReportPeriodEmpty({ report: 'patients', rows: [], summary: idleSummary })).toBe(true)
  })

  it('falls back to the row count for a payload without a summary', () => {
    expect(isReportPeriodEmpty({ report: 'appointments', rows: [], summary: null })).toBe(true)
    expect(isReportPeriodEmpty({ report: 'appointments', rows, summary: null })).toBe(false)
    expect(isReportPeriodEmpty({ report: 'appointments', rows: [] })).toBe(true)
  })

  it('keeps the consolidated rule untouched', () => {
    expect(isReportPeriodEmpty({ report: 'consolidated', consolidated: null })).toBe(true)
    expect(
      isReportPeriodEmpty({
        report: 'consolidated',
        consolidated: { by_branch: [], by_professional: [] }
      })
    ).toBe(true)
    expect(
      isReportPeriodEmpty({
        report: 'consolidated',
        consolidated: { by_branch: [{ id: 1, net: 0 }], by_professional: [] }
      })
    ).toBe(false)
    expect(
      isReportPeriodEmpty({
        report: 'consolidated',
        consolidated: { by_branch: [], by_professional: [{ id: 2, net: 0 }] }
      })
    ).toBe(false)
  })
})
