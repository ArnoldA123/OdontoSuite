import { describe, it, expect } from 'vitest'
import {
  ACCOUNTING_LOCK_MODULES,
  buildLockDeletePath,
  buildLockModulePath,
  buildLockPayload,
  buildLocksPath,
  formatLockDate,
  formatLockMoment,
  getModuleLabel,
  getSourceLabel,
  getSourceVariant,
  hasLock
} from '../../../resources/js/modules/accounting-locks/accountingLockMeta.js'

const statusBadgeVariants = ['success', 'warning', 'error', 'info', 'neutral']

describe('accounting lock vocabulary (#304)', () => {
  it('keeps the four modules and their order from the API', () => {
    expect(ACCOUNTING_LOCK_MODULES.map(option => option.value)).toEqual([
      'cash_transactions',
      'cash_movements',
      'quotations',
      'treatment_plans'
    ])
  })

  it('labels every module and falls back to the raw code', () => {
    expect(getModuleLabel('cash_transactions')).toBe('Transacciones de caja')
    expect(getModuleLabel('cash_movements')).toBe('Movimientos de caja')
    expect(getModuleLabel('quotations')).toBe('Presupuestos')
    expect(getModuleLabel('treatment_plans')).toBe('Planes de tratamiento')
    expect(getModuleLabel('unknown_module')).toBe('unknown_module')
    expect(getModuleLabel(null)).toBe('')
  })

  it('labels the three origins the API publishes', () => {
    expect(getSourceLabel('branch')).toBe('Sede')
    expect(getSourceLabel('global')).toBe('Global')
    expect(getSourceLabel('none')).toBe('Sin bloqueo')
    expect(getSourceLabel(null)).toBe('Sin bloqueo')
  })

  it('maps every origin to a valid badge variant', () => {
    expect(getSourceVariant('branch')).toBe('info')
    expect(getSourceVariant('global')).toBe('warning')
    expect(getSourceVariant('none')).toBe('neutral')
    expect(getSourceVariant(undefined)).toBe('neutral')
    ;['branch', 'global', 'none'].forEach(source => {
      expect(statusBadgeVariants).toContain(getSourceVariant(source))
    })
  })

  it('treats a module as locked only when it inherits or owns a date', () => {
    expect(hasLock({ source: 'branch', locked_through: '2026-09-30' })).toBe(true)
    expect(hasLock({ source: 'global', locked_through: '2026-09-30' })).toBe(true)
    expect(hasLock({ source: 'none', locked_through: null })).toBe(false)
    expect(hasLock({ source: 'branch', locked_through: null })).toBe(false)
    expect(hasLock(null)).toBe(false)
  })

  it('formats the effective date without shifting the wall clock', () => {
    expect(formatLockDate('2026-03-05')).toBe('05 mar 2026')
    expect(formatLockDate('2026-09-30')).toBe('30 set 2026')
    expect(formatLockDate('2026-12-31T23:59:00-05:00')).toBe('31 dic 2026')
    expect(formatLockDate('2026-01-01')).toBe('01 ene 2026')
  })

  it('returns an empty string for a missing or invalid date', () => {
    expect(formatLockDate(null)).toBe('')
    expect(formatLockDate(undefined)).toBe('')
    expect(formatLockDate('')).toBe('')
    expect(formatLockDate('sin fecha')).toBe('')
    expect(formatLockDate('2026-13-01')).toBe('')
    expect(formatLockDate(20260305)).toBe('')
  })

  it('formats the update moment with the time the API serialised', () => {
    expect(formatLockMoment('2026-10-01T10:15:00-05:00')).toBe('01 oct 2026, 10:15')
    expect(formatLockMoment('2026-10-01T23:59:59-05:00')).toBe('01 oct 2026, 23:59')
    expect(formatLockMoment('2026-10-01')).toBe('01 oct 2026')
    expect(formatLockMoment(null)).toBe('')
  })

  it('addresses the group row when no sede is selected', () => {
    expect(buildLocksPath()).toBe('/api/accounting-locks')
    expect(buildLocksPath(null)).toBe('/api/accounting-locks')
    expect(buildLocksPath('')).toBe('/api/accounting-locks')
    expect(buildLocksPath(3)).toBe('/api/accounting-locks?branch_id=3')
    expect(buildLocksPath('3')).toBe('/api/accounting-locks?branch_id=3')
  })

  it('addresses one module for a sede or for the whole group', () => {
    expect(buildLockModulePath('quotations')).toBe('/api/accounting-locks/quotations')
    expect(buildLockModulePath('cash_transactions')).toBe('/api/accounting-locks/cash_transactions')
  })

  it('carries the scope of a DELETE in the query string only', () => {
    expect(buildLockDeletePath('quotations')).toBe('/api/accounting-locks/quotations')
    expect(buildLockDeletePath('quotations', null)).toBe('/api/accounting-locks/quotations')
    expect(buildLockDeletePath('quotations', '')).toBe('/api/accounting-locks/quotations')
    expect(buildLockDeletePath('quotations', 5)).toBe(
      '/api/accounting-locks/quotations?branch_id=5'
    )
  })

  it('omits branch_id from the payload unless a sede is in scope', () => {
    expect(buildLockPayload('2026-10-15')).toEqual({ locked_through: '2026-10-15' })
    expect(buildLockPayload(' 2026-10-15 ', '')).toEqual({ locked_through: '2026-10-15' })
    expect(buildLockPayload('2026-10-15', 5)).toEqual({
      locked_through: '2026-10-15',
      branch_id: 5
    })
    expect(buildLockPayload('2026-10-15', '5')).toEqual({
      locked_through: '2026-10-15',
      branch_id: 5
    })
  })
})
