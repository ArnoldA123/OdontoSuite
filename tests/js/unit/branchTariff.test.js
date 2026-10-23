import { describe, expect, it } from 'vitest'
import {
  TARIFF_STATUSES,
  formatTariffDate,
  formatTariffPrice,
  formatValidityWindow,
  tariffStatus,
  tariffStatusLabel,
  tariffStatusVariant
} from '../../../resources/js/modules/procedure-catalog/branchTariff'

const REFERENCE = '2026-10-15'

const row = overrides => ({
  is_active: true,
  valid_from: '2026-10-01',
  valid_to: '2026-10-31',
  ...overrides
})

const flat = value => String(value).replace(/\s+/g, ' ')

describe('branch tariff status', () => {
  it('derives the four states against a fixed reference date', () => {
    expect(tariffStatus(row(), REFERENCE)).toBe('vigente')
    expect(tariffStatus(row({ valid_from: '2026-11-01', valid_to: null }), REFERENCE)).toBe(
      'futuro'
    )
    expect(tariffStatus(row({ valid_from: '2026-08-01', valid_to: '2026-09-30' }), REFERENCE)).toBe(
      'vencido'
    )
    expect(tariffStatus(row({ is_active: false }), REFERENCE)).toBe('inactivo')

    expect(TARIFF_STATUSES).toEqual(['vigente', 'futuro', 'vencido', 'inactivo'])
  })

  it('treats an open window as in force and ignores an inactive row', () => {
    expect(tariffStatus(row({ valid_from: null, valid_to: null }), REFERENCE)).toBe('vigente')
    expect(tariffStatus(row({ valid_from: null, valid_to: '2026-10-31' }), REFERENCE)).toBe(
      'vigente'
    )
    expect(tariffStatus(row({ valid_from: '2026-10-01', valid_to: null }), REFERENCE)).toBe(
      'vigente'
    )
    expect(tariffStatus(row({ is_active: false, valid_from: '2027-01-01' }), REFERENCE)).toBe(
      'inactivo'
    )
    expect(tariffStatus(null, REFERENCE)).toBe('inactivo')
  })

  it('labels and colours every status for the shared badge primitive', () => {
    expect(tariffStatusLabel('vigente')).toBe('Vigente')
    expect(tariffStatusLabel('futuro')).toBe('Futuro')
    expect(tariffStatusLabel('vencido')).toBe('Vencido')
    expect(tariffStatusLabel('inactivo')).toBe('Inactivo')
    expect(tariffStatusVariant('vigente')).toBe('success')
    expect(tariffStatusVariant('futuro')).toBe('info')
    expect(tariffStatusVariant('vencido')).toBe('warning')
    expect(tariffStatusVariant('inactivo')).toBe('neutral')
  })
})

describe('branch tariff formatting', () => {
  it('formats amounts as the shared PEN currency label', () => {
    expect(flat(formatTariffPrice(75.5))).toBe('S/ 75.50')
    expect(flat(formatTariffPrice('1234.5'))).toBe('S/ 1,234.50')
    expect(flat(formatTariffPrice(null))).toBe('S/ 0.00')
  })

  it('formats a closed and an open validity window', () => {
    expect(formatValidityWindow('2026-10-01', '2026-10-31')).toBe('01/10/2026 — 31/10/2026')
    expect(formatValidityWindow('2026-10-01', null)).toBe('Desde 01/10/2026')
    expect(formatValidityWindow(null, '2026-10-31')).toBe('Hasta 31/10/2026')
    expect(formatValidityWindow(null, null)).toBe('Sin límite')
  })

  it('formats date-only values without shifting the calendar day', () => {
    expect(formatTariffDate('2026-10-01')).toBe('01/10/2026')
    expect(formatTariffDate('2026-10-01T23:30:00-05:00')).toBe('01/10/2026')
    expect(formatTariffDate('')).toBe('')
  })
})
