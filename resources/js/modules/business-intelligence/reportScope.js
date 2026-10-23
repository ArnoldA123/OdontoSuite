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
