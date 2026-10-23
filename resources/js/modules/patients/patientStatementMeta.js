// #320 (S4/S7) — single source of the patient statement vocabulary. The API
// publishes six ledger kinds and nine period totals; the panel and the smoke
// suite read them from here instead of repeating the words of the book.

export const STATEMENT_KINDS = [
  'charge',
  'discount',
  'payment',
  'refund',
  'write_off',
  'adjustment'
]

export const STATEMENT_KIND_LABELS = {
  charge: 'Cargo',
  discount: 'Descuento',
  payment: 'Pago',
  refund: 'Devolución',
  write_off: 'Condonación',
  adjustment: 'Ajuste'
}

// A kind the backend adds later must not print a raw snake_case token in the
// ledger: the neutral label keeps the table readable until the vocabulary
// catches up.
export const statementKindLabel = kind => STATEMENT_KIND_LABELS[kind] || 'Movimiento'

const KIND_VARIANTS = {
  charge: 'warning',
  discount: 'info',
  payment: 'success',
  refund: 'error',
  write_off: 'neutral',
  adjustment: 'neutral'
}

export const statementKindVariant = kind => KIND_VARIANTS[kind] || 'neutral'

// The summary is the reconciliation the API already wrote; these rows only
// name its totals. The order is the book order of the issue: what the patient
// owed, what reduced it, and where it closed.
const SUMMARY_ROWS = [
  { key: 'charges', label: 'Cargos' },
  { key: 'discounts', label: 'Descuentos' },
  { key: 'payments', label: 'Pagos' },
  { key: 'refunds', label: 'Devoluciones' },
  { key: 'write_offs', label: 'Condonaciones' },
  { key: 'adjustments', label: 'Ajustes' },
  { key: 'collected', label: 'Cobrado' },
  { key: 'forgiven', label: 'Condonado' },
  { key: 'balance', label: 'Saldo' }
]

const toAmount = value => {
  const amount = Number(value)
  return Number.isFinite(amount) ? amount : 0
}

export const statementSummaryRows = summary =>
  SUMMARY_ROWS.map(row => ({
    key: row.key,
    label: row.label,
    amount: toAmount(summary?.[row.key])
  }))
