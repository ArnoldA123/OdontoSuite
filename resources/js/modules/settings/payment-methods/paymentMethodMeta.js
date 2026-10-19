export const GATEWAY_TYPE_OPTIONS = Object.freeze([
  { value: 'manual', label: 'Manual (sin pasarela)' },
  { value: 'mercadopago', label: 'Mercado Pago' }
])

export const STATUS_FILTER_OPTIONS = Object.freeze([
  { value: '', label: 'Todos los estados' },
  { value: 'true', label: 'Activos' },
  { value: 'false', label: 'Inactivos' }
])

export const STATUS_FORM_OPTIONS = Object.freeze([
  { value: 'active', label: 'Activo' },
  { value: 'inactive', label: 'Inactivo' }
])

const GATEWAY_LABELS = Object.freeze({
  manual: 'Manual',
  mercadopago: 'Mercado Pago'
})

export function getGatewayLabel (gatewayType) {
  return GATEWAY_LABELS[gatewayType] || GATEWAY_LABELS.manual
}

export function hasGateway (gatewayType) {
  return Boolean(gatewayType) && gatewayType !== 'manual'
}

export function getScopeLabel (isSystem) {
  return isSystem ? 'Sistema' : 'Personalizado'
}

export function getScopeVariant (isSystem) {
  return isSystem ? 'warning' : 'neutral'
}

export function getStatusLabel (isActive) {
  return isActive ? 'Activo' : 'Inactivo'
}

export function getStatusVariant (isActive) {
  return isActive ? 'success' : 'neutral'
}

export function transactionCount (method) {
  const count = Number(method?.transactions_count ?? 0)
  return Number.isFinite(count) ? count : 0
}

export function canDelete (method) {
  return Boolean(method) && !method.is_system && transactionCount(method) === 0
}

export function getDeleteBlockedReason (method) {
  if (!method || canDelete(method)) return null
  if (method.is_system) return 'Los métodos del sistema no se pueden eliminar.'

  const count = transactionCount(method)
  const noun = count === 1 ? 'transacción registrada' : 'transacciones registradas'

  return `Tiene ${count} ${noun}.`
}

export function formatCommission (value) {
  const numeric = Number(value ?? 0)
  if (!Number.isFinite(numeric)) return '0%'

  const rounded = Math.round(numeric * 100) / 100
  return Number.isInteger(rounded) ? `${rounded}%` : `${rounded.toFixed(2)}%`
}
