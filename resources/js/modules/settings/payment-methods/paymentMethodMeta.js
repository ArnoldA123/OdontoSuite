export const GATEWAY_TYPE_OPTIONS = Object.freeze([
  { value: 'manual', label: 'Manual (sin pasarela)' },
  { value: 'mercadopago', label: 'Mercado Pago' }
])

// #291 — the behaviour families the daily reconciliation groups by. The list
// mirrors PaymentMethod::TYPES on the API.
export const TYPE_OPTIONS = Object.freeze([
  { value: 'cash', label: 'Efectivo' },
  { value: 'card', label: 'Tarjeta' },
  { value: 'transfer', label: 'Transferencia' },
  { value: 'wallet', label: 'Billetera digital' }
])

const TYPE_LABELS = Object.freeze({
  cash: 'Efectivo',
  card: 'Tarjeta',
  transfer: 'Transferencia',
  wallet: 'Billetera digital'
})

export const STATUS_FILTER_OPTIONS = Object.freeze([
  { value: '', label: 'Todos los estados' },
  { value: 'true', label: 'Activos' },
  { value: 'false', label: 'Inactivos' }
])

// #445: the family filter reuses the four families the API publishes, so the
// option list never drifts from the read contract.
export const TYPE_FILTER_OPTIONS = Object.freeze([
  { value: '', label: 'Todas las familias' },
  ...TYPE_OPTIONS
])

export const STATUS_FORM_OPTIONS = Object.freeze([
  { value: 'active', label: 'Activo' },
  { value: 'inactive', label: 'Inactivo' }
])

const GATEWAY_LABELS = Object.freeze({
  manual: 'Manual',
  mercadopago: 'Mercado Pago'
})

export function getGatewayLabel(gatewayType) {
  return GATEWAY_LABELS[gatewayType] || GATEWAY_LABELS.manual
}

export function hasGateway(gatewayType) {
  return Boolean(gatewayType) && gatewayType !== 'manual'
}

export function getScopeLabel(isSystem) {
  return isSystem ? 'Sistema' : 'Personalizado'
}

export function getTypeLabel(type) {
  return TYPE_LABELS[type] || TYPE_LABELS.cash
}

export function getTypeVariant(type) {
  return type === 'cash' ? 'neutral' : 'info'
}

export function getScopeVariant(isSystem) {
  return isSystem ? 'warning' : 'neutral'
}

export function getStatusLabel(isActive) {
  return isActive ? 'Activo' : 'Inactivo'
}

export function getStatusVariant(isActive) {
  return isActive ? 'success' : 'neutral'
}

export function transactionCount(method) {
  const count = Number(method?.transactions_count ?? 0)
  return Number.isFinite(count) ? count : 0
}

export function canDelete(method) {
  return Boolean(method) && !method.is_system && transactionCount(method) === 0
}

export function getDeleteBlockedReason(method) {
  if (!method || canDelete(method)) return null
  if (method.is_system) return 'Los métodos del sistema no se pueden eliminar.'

  const count = transactionCount(method)
  const noun = count === 1 ? 'transacción registrada' : 'transacciones registradas'

  return `Tiene ${count} ${noun}.`
}

export function formatCommission(value) {
  const numeric = Number(value ?? 0)
  if (!Number.isFinite(numeric)) return '0%'

  const rounded = Math.round(numeric * 100) / 100
  const formatted = rounded.toFixed(2).replace(/\.?0+$/, '')

  return `${formatted || '0'}%`
}
