import { describe, it, expect } from 'vitest'
import {
  AUTHORIZATION_REASON_FIELD,
  AUTHORIZER_FIELD,
  TRANSACTION_TYPE_OPTIONS,
  requiresAuthorization,
  transactionTypeLabel
} from '../../../resources/js/modules/cash-register/moneyMeta.js'

// #320 / T2 — Caja's refund is a refund, not an expense. The modal and the
// smokes read the same vocabulary so the label cannot drift back to "Egreso"
// on one surface while the other says "Devolución".
describe('cash-register money vocabulary', () => {
  it('offers the two writable transaction types with honest labels', () => {
    expect(TRANSACTION_TYPE_OPTIONS).toEqual([
      { value: 'payment', label: 'Ingreso' },
      { value: 'refund', label: 'Devolución' }
    ])

    expect(TRANSACTION_TYPE_OPTIONS.map(option => option.label)).not.toContain('Egreso')
  })

  it('labels a transaction type and falls back to the raw value', () => {
    expect(transactionTypeLabel('payment')).toBe('Ingreso')
    expect(transactionTypeLabel('refund')).toBe('Devolución')
    // `discount` is a legacy column value with no write door (#287): the helper
    // never invents a label for it.
    expect(transactionTypeLabel('discount')).toBe('discount')
    expect(transactionTypeLabel(null)).toBe('—')
  })

  it('requires an administrator behind a refund only', () => {
    expect(requiresAuthorization('refund')).toBe(true)
    expect(requiresAuthorization('payment')).toBe(false)
    expect(requiresAuthorization('adjustment')).toBe(false)
    expect(requiresAuthorization(null)).toBe(false)
  })

  it('names the authorizer fields the API validates', () => {
    expect(AUTHORIZER_FIELD).toBe('authorized_by')
    expect(AUTHORIZATION_REASON_FIELD).toBe('authorization_reason')
  })
})
