import { describe, it, expect } from 'vitest'
import {
  QUOTATION_STATUSES,
  STATUS_FILTER_OPTIONS,
  canApproveQuotation,
  canCreateQuotation,
  canDeleteQuotation,
  canDownloadQuotation,
  canEditQuotation,
  canRejectQuotation,
  calendarDateKey,
  expiryBadge,
  expiryState,
  formatQuotationDate,
  getStatusMeta,
  resolveItemDescription,
  resolveItemTitle
} from '../../../resources/js/modules/quotations/quotationMeta.js'

const statusBadgeVariants = ['success', 'warning', 'error', 'info', 'neutral']

describe('quotations vocabulary', () => {
  it('labels every status of the database enum', () => {
    expect(QUOTATION_STATUSES).toEqual([
      'draft',
      'sent',
      'viewed',
      'approved',
      'rejected',
      'expired'
    ])

    QUOTATION_STATUSES.forEach(status => {
      const meta = getStatusMeta(status)
      expect(meta.label).not.toBe(status)
      expect(statusBadgeVariants).toContain(meta.variant)
    })

    expect(getStatusMeta('viewed').label).toBe('Visto')
    expect(getStatusMeta('expired').label).toBe('Expirado')
  })

  it('falls back to the raw value for a status outside the enum', () => {
    expect(getStatusMeta('archived')).toEqual({ label: 'archived', variant: 'neutral' })
    expect(getStatusMeta(null).label).toBe('—')
  })

  it('offers the whole enum in the status filter', () => {
    expect(STATUS_FILTER_OPTIONS.map(option => option.value)).toEqual(['', ...QUOTATION_STATUSES])
    STATUS_FILTER_OPTIONS.forEach(option => expect(typeof option.value).toBe('string'))
  })

  it('keeps the calendar date of a UTC-midnight timestamp', () => {
    expect(calendarDateKey('2026-10-20T00:00:00.000000Z')).toBe('2026-10-20')
    expect(formatQuotationDate('2026-10-20T00:00:00.000000Z')).toBe('20 oct 2026')
    expect(formatQuotationDate('2026-01-05T00:00:00.000000Z')).toBe('05 ene 2026')
    expect(formatQuotationDate(null)).toBe('—')
  })

  it('reports expiry only for quotations awaiting a patient decision', () => {
    const today = new Date('2026-10-20T12:00:00')

    expect(expiryState({ status: 'sent', valid_until: '2026-10-19' }, today)).toBe('expired')
    expect(expiryState({ status: 'viewed', valid_until: '2026-10-23' }, today)).toBe('soon')
    expect(expiryState({ status: 'sent', valid_until: '2026-11-30' }, today)).toBe('ok')
    expect(expiryState({ status: 'approved', valid_until: '2026-10-01' }, today)).toBeNull()
    expect(expiryState({ status: 'rejected', valid_until: '2026-10-01' }, today)).toBeNull()
  })

  it('describes the expiry as a badge with a human label', () => {
    const today = new Date('2026-10-20T12:00:00')

    expect(expiryBadge({ status: 'sent', valid_until: '2026-10-19' }, today)).toEqual({
      label: 'Vencido',
      variant: 'error'
    })
    expect(expiryBadge({ status: 'sent', valid_until: '2026-10-20' }, today)).toEqual({
      label: 'Vence hoy',
      variant: 'warning'
    })
    expect(expiryBadge({ status: 'sent', valid_until: '2026-10-22' }, today)).toEqual({
      label: 'Vence en 2 días',
      variant: 'warning'
    })
    expect(expiryBadge({ status: 'approved', valid_until: '2026-10-22' }, today)).toBeNull()
  })

  it('reads item titles from the persisted column names', () => {
    expect(resolveItemTitle({ item_name: 'Limpieza', description: 'legacy' })).toBe('Limpieza')
    expect(resolveItemTitle({ description: 'Limpieza' })).toBe('Limpieza')
    expect(resolveItemTitle({})).toBe('—')
    expect(resolveItemDescription({ item_description: 'Con ultrasonido' })).toBe('Con ultrasonido')
    expect(resolveItemDescription({})).toBe('')
  })

  it('mirrors the write middleware: only admins, finance and clinicians act', () => {
    expect(canCreateQuotation('implantologo')).toBe(true)
    expect(canCreateQuotation('recepcionista')).toBe(false)

    expect(canEditQuotation({ status: 'draft' }, 'odontologo')).toBe(true)
    expect(canEditQuotation({ status: 'approved' }, 'odontologo')).toBe(false)

    expect(canApproveQuotation({ status: 'viewed' }, 'implantologo')).toBe(true)
    expect(canApproveQuotation({ status: 'draft' }, 'administrador')).toBe(false)

    expect(canRejectQuotation({ status: 'sent' }, 'finanzas')).toBe(true)
    expect(canRejectQuotation({ status: 'sent' }, 'recepcionista')).toBe(false)

    expect(canDeleteQuotation({ status: 'draft' }, 'administrador')).toBe(true)
    expect(canDeleteQuotation({ status: 'sent' }, 'administrador')).toBe(false)

    expect(canDownloadQuotation('recepcionista')).toBe(true)
    expect(canDownloadQuotation('asistente')).toBe(false)
  })
})
