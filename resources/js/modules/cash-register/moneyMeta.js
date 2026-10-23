// #320 / T2 — Caja's money vocabulary. `refund` is a devolución, not an
// egreso: the drawer's expenses live in the cash-movement ledger. The modal and
// its smokes read the same list so the labels cannot drift apart again.
const TYPE_LABELS = Object.freeze({
  payment: 'Ingreso',
  refund: 'Devolución'
})

export const TRANSACTION_TYPE_OPTIONS = Object.freeze(
  Object.entries(TYPE_LABELS).map(([value, label]) => Object.freeze({ value, label }))
)

// #287 — only the refund needs a registered administrator behind it.
const AUTHORIZED_TYPES = Object.freeze(['refund'])

export const AUTHORIZER_FIELD = 'authorized_by'
export const AUTHORIZATION_REASON_FIELD = 'authorization_reason'

export const AUTHORIZATION_REASON_MAX_LENGTH = 255

export function transactionTypeLabel(type) {
  return TYPE_LABELS[type] || type || '—'
}

export function requiresAuthorization(type) {
  return AUTHORIZED_TYPES.includes(type)
}
