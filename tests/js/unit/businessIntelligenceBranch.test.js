import { describe, it, expect } from 'vitest'
import {
  buildAppliedParams,
  canExportReport,
  hasGroupBranchScope,
  toBranchOptions
} from '../../../resources/js/modules/business-intelligence/reportScope.js'

// #303 S3 / #304 S3 — the pure rules of the BI sede scope: who sees the
// selector, what travels as an applied filter and which reports can be
// exported.
const applied = overrides => ({
  from: '2026-09-20',
  to: '2026-10-19',
  professionalId: '',
  environmentId: '',
  branchId: null,
  ...overrides
})

describe('business intelligence sede scope helpers (#303 S3)', () => {
  it('opens the sede selector only for group scope', () => {
    expect(hasGroupBranchScope({ id: 1, role: 'administrador', branch_id: null })).toBe(true)
    expect(hasGroupBranchScope({ id: 2, role: 'administrador', branch_id: 3 })).toBe(false)
    expect(hasGroupBranchScope(null)).toBe(false)
  })

  it('sends the branch only when a sede is applied', () => {
    const everySede = buildAppliedParams(applied())

    expect(everySede).toEqual({
      start_date: '2026-09-20',
      end_date: '2026-10-19',
      professional_id: '',
      environment_id: ''
    })
    expect('branch_id' in everySede).toBe(false)

    expect(buildAppliedParams(applied({ branchId: 3 })).branch_id).toBe(3)
  })

  it('keeps the professional and environment filters in the applied params', () => {
    const params = buildAppliedParams(applied({ professionalId: 7, environmentId: 4 }))

    expect(params.professional_id).toBe(7)
    expect(params.environment_id).toBe(4)
  })

  it('lists every sede as the first option and maps the catalogue', () => {
    expect(toBranchOptions([{ id: 3, name: 'Sede Norte' }])).toEqual([
      { value: null, label: 'Todas las sedes' },
      { value: 3, label: 'Sede Norte' }
    ])
    expect(toBranchOptions(null)).toEqual([{ value: null, label: 'Todas las sedes' }])
  })

  it('knows the consolidated report has no export endpoint', () => {
    expect(canExportReport('consolidated')).toBe(false)

    for (const report of [
      'dashboard',
      'appointments',
      'patients',
      'professionals',
      'revenue',
      'utilization'
    ]) {
      expect(canExportReport(report)).toBe(true)
    }
  })
})
