// Branch scope of the BI page (#303 S3, #304 S3). Group scope reads every sede
// and may narrow the report to one; a user with `users.branch_id` is answered
// their own sede by the API, so the page never sends `branch_id` on their
// behalf and hides the selector.
export const ALL_BRANCHES = null

const EXPORTABLE_REPORTS = [
  'dashboard',
  'appointments',
  'patients',
  'professionals',
  'revenue',
  'utilization'
]

export function hasGroupBranchScope(user) {
  return Boolean(user) && !user.branch_id
}

export function toBranchOptions(branches) {
  return [
    { value: ALL_BRANCHES, label: 'Todas las sedes' },
    ...(Array.isArray(branches) ? branches : []).map(branch => ({
      value: branch.id,
      label: branch.name
    }))
  ]
}

export function buildAppliedParams(applied) {
  const params = {
    start_date: applied.from,
    end_date: applied.to,
    professional_id: applied.professionalId || '',
    environment_id: applied.environmentId || ''
  }

  // "Todas las sedes" is the absence of a filter, not an empty one.
  if (applied.branchId) {
    params.branch_id = applied.branchId
  }

  return params
}

export function canExportReport(report) {
  return EXPORTABLE_REPORTS.includes(report)
}

// #447 S1: three of the tabular services return their whole census and only
// narrow the counters, and the dashboard always ships `professionalPerformance`,
// so an idle period still arrived with rows. The empty state reads the ACTIVITY
// of the period, never the size of the table.
//
// The three tabular reports (#447) publish `appointments`/`revenue`; the revenue
// report keeps its own summary (`appointments_count`/`total_revenue`), so both
// spellings are read before giving up.
const SUMMARY_APPOINTMENTS_KEYS = ['appointments', 'appointments_count']
const SUMMARY_REVENUE_KEYS = ['revenue', 'total_revenue']

const readSummaryAmount = (summary, keys) => {
  for (const key of keys) {
    const value = summary[key]
    if (value !== undefined && value !== null) {
      const parsed = Number(value)
      return Number.isFinite(parsed) ? parsed : 0
    }
  }

  return null
}

/**
 * Reads the activity a report summary publishes.
 *
 * Returns `true` when the period reports any appointment or any money, `false`
 * when both are zero and `null` when the summary is missing or does not carry
 * both counters: `null` is "cannot tell", never "empty", so the caller falls
 * back to what it always used.
 *
 * @param {object|null|undefined} summary
 * @returns {boolean|null}
 */
export function summaryShowsActivity(summary) {
  if (!summary || typeof summary !== 'object') {
    return null
  }

  const appointments = readSummaryAmount(summary, SUMMARY_APPOINTMENTS_KEYS)
  const revenue = readSummaryAmount(summary, SUMMARY_REVENUE_KEYS)

  if (appointments === null || revenue === null) {
    return null
  }

  return appointments > 0 || revenue > 0
}

/**
 * Decides whether the applied period has nothing to report (#447 S2).
 *
 * @param {object} state
 * @param {string} state.report Applied report id.
 * @param {Array<object>} [state.rows] Tabular rows of the applied period.
 * @param {object|null} [state.summary] `meta.summary` of the tabular payload.
 * @param {object|null} [state.dashboard] Dashboard payload.
 * @param {object|null} [state.consolidated] Consolidated payload.
 * @returns {boolean}
 */
export function isReportPeriodEmpty({
  report,
  rows = [],
  summary = null,
  dashboard = null,
  consolidated = null
} = {}) {
  if (report === 'dashboard') {
    return (
      Number(dashboard?.totalAppointments ?? 0) === 0 && Number(dashboard?.totalRevenue ?? 0) === 0
    )
  }

  if (report === 'consolidated') {
    if (!consolidated) {
      return true
    }

    return (
      (Array.isArray(consolidated.by_branch) ? consolidated.by_branch : []).length === 0 &&
      (Array.isArray(consolidated.by_professional) ? consolidated.by_professional : []).length === 0
    )
  }

  const activity = summaryShowsActivity(summary)

  // A payload from before #447 carries no activity summary: the row count is
  // the only signal left.
  return activity === null ? rows.length === 0 : !activity
}
