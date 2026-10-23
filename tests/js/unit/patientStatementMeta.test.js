import { describe, it, expect } from 'vitest'
import {
  STATEMENT_KINDS,
  STATEMENT_KIND_LABELS,
  statementKindLabel,
  statementKindVariant,
  statementSummaryRows
} from '../../../resources/js/modules/patients/patientStatementMeta.js'

// #320 (S4/S7) — pure presentation rules of the patient statement: the six
// ledger kinds the API publishes, the nine totals of the period summary and the
// badge variant of each kind. The panel and the smoke suite consume the same
// vocabulary, so a kind the backend adds surfaces here first.

const SUMMARY = {
  charges: 400,
  discounts: 20.5,
  payments: 210,
  refunds: 10,
  write_offs: 15,
  adjustments: -5,
  collected: 200,
  forgiven: 15,
  balance: 240
}

describe('patientStatementMeta kind vocabulary', () => {
  it('keeps the six kinds the statement publishes, in book order', () => {
    expect(STATEMENT_KINDS).toEqual([
      'charge',
      'discount',
      'payment',
      'refund',
      'write_off',
      'adjustment'
    ])
  })

  it('labels every kind in Spanish', () => {
    expect(STATEMENT_KIND_LABELS).toEqual({
      charge: 'Cargo',
      discount: 'Descuento',
      payment: 'Pago',
      refund: 'Devolución',
      write_off: 'Condonación',
      adjustment: 'Ajuste'
    })
    expect(statementKindLabel('charge')).toBe('Cargo')
    expect(statementKindLabel('write_off')).toBe('Condonación')
  })

  it('falls back to a neutral label instead of printing an unknown kind', () => {
    expect(statementKindLabel('legacy')).toBe('Movimiento')
    expect(statementKindLabel(undefined)).toBe('Movimiento')
    expect(statementKindLabel(null)).toBe('Movimiento')
  })

  it('maps each kind to a badge variant', () => {
    expect(statementKindVariant('charge')).toBe('warning')
    expect(statementKindVariant('discount')).toBe('info')
    expect(statementKindVariant('payment')).toBe('success')
    expect(statementKindVariant('refund')).toBe('error')
    expect(statementKindVariant('write_off')).toBe('neutral')
    expect(statementKindVariant('adjustment')).toBe('neutral')
    expect(statementKindVariant('legacy')).toBe('neutral')
  })
})

describe('patientStatementMeta summary rows', () => {
  it('lists the nine API totals with their human labels, in book order', () => {
    const rows = statementSummaryRows(SUMMARY)

    expect(rows.map(row => row.key)).toEqual([
      'charges',
      'discounts',
      'payments',
      'refunds',
      'write_offs',
      'adjustments',
      'collected',
      'forgiven',
      'balance'
    ])
    expect(rows.map(row => row.label)).toEqual([
      'Cargos',
      'Descuentos',
      'Pagos',
      'Devoluciones',
      'Condonaciones',
      'Ajustes',
      'Cobrado',
      'Condonado',
      'Saldo'
    ])
    expect(rows.map(row => row.amount)).toEqual([400, 20.5, 210, 10, 15, -5, 200, 15, 240])
  })

  it('coerces missing or non-numeric totals to zero without inventing rows', () => {
    const rows = statementSummaryRows({ charges: 12, payments: '30.5' })

    expect(rows).toHaveLength(9)
    expect(rows.find(row => row.key === 'charges').amount).toBe(12)
    expect(rows.find(row => row.key === 'payments').amount).toBe(30.5)
    expect(rows.find(row => row.key === 'refunds').amount).toBe(0)
    expect(statementSummaryRows(undefined).every(row => row.amount === 0)).toBe(true)
    expect(statementSummaryRows(null).every(row => row.amount === 0)).toBe(true)
  })
})
