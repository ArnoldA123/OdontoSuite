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

  it('owns the status rule of each action and takes the permission as given', () => {
    expect(canCreateQuotation(true)).toBe(true)
    expect(canCreateQuotation(false)).toBe(false)

    expect(canEditQuotation({ status: 'draft' }, true)).toBe(true)
    expect(canEditQuotation({ status: 'approved' }, true)).toBe(false)
    expect(canEditQuotation({ status: 'draft' }, false)).toBe(false)

    expect(canApproveQuotation({ status: 'viewed' }, true)).toBe(true)
    expect(canApproveQuotation({ status: 'sent' }, true)).toBe(true)
    expect(canApproveQuotation({ status: 'draft' }, true)).toBe(false)
    expect(canApproveQuotation({ status: 'viewed' }, false)).toBe(false)

    expect(canRejectQuotation({ status: 'sent' }, true)).toBe(true)
    expect(canRejectQuotation({ status: 'approved' }, true)).toBe(false)
    expect(canRejectQuotation({ status: 'sent' }, false)).toBe(false)

    expect(canDeleteQuotation({ status: 'draft' }, true)).toBe(true)
    expect(canDeleteQuotation({ status: 'sent' }, true)).toBe(false)
    expect(canDeleteQuotation({ status: 'draft' }, false)).toBe(false)

    expect(canDownloadQuotation(true)).toBe(true)
    expect(canDownloadQuotation(false)).toBe(false)
  })

  it('fails closed when a stale caller passes a role instead of the permission', () => {
    // #280/#420: the permission is the membership check the API publishes, so
    // the module never resolves a role itself. A string is not the boolean the
    // contract asks for and must deny the action, never grant it.
    expect(canCreateQuotation('administrador')).toBe(false)
    expect(canEditQuotation({ status: 'draft' }, 'administrador')).toBe(false)
    expect(canApproveQuotation({ status: 'viewed' }, 'administrador')).toBe(false)
    expect(canDownloadQuotation('recepcionista')).toBe(false)
  })

  it('does not export the role lists that routes/api.php owns', async () => {
    const meta = await import('../../../resources/js/modules/quotations/quotationMeta.js')

    expect(meta.QUOTATION_WRITE_ROLES).toBeUndefined()
    expect(meta.QUOTATION_READ_ROLES).toBeUndefined()
    expect(meta.canWriteQuotations).toBeUndefined()
  })
})
