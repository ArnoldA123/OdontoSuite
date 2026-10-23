// #304 — vocabulary of the accounting freeze surface. The four modules and the
// three origins mirror App\Services\AccountingLockService::MODULES and
// App\Models\AccountingLock::SOURCE_*; the API is the authority, this file only
// names them in Spanish.
export const ACCOUNTING_LOCK_MODULES = Object.freeze([
  { value: 'cash_transactions', label: 'Transacciones de caja' },
  { value: 'cash_movements', label: 'Movimientos de caja' },
  { value: 'quotations', label: 'Presupuestos' },
  { value: 'treatment_plans', label: 'Planes de tratamiento' }
])

const MODULE_LABELS = Object.freeze(
  Object.fromEntries(ACCOUNTING_LOCK_MODULES.map(({ value, label }) => [value, label]))
)

export const SOURCE_NONE = 'none'

const SOURCE_LABELS = Object.freeze({
  branch: 'Sede',
  global: 'Global',
  none: 'Sin bloqueo'
})

const SOURCE_VARIANTS = Object.freeze({
  branch: 'info',
  global: 'warning',
  none: 'neutral'
})

// The API serialises `locked_through` as a date and `set_at` as ISO-8601 in the
// application timezone (America/Lima). Both render from the literal parts of
// the string: parsing them through `new Date()` would shift the wall clock the
// administrator saw when they froze the module.
const DATE_PARTS = /^(\d{4})-(\d{2})-(\d{2})/
const TIME_PARTS = /^\d{4}-\d{2}-\d{2}T(\d{2}):(\d{2})/

const MONTHS = Object.freeze([
  'ene',
  'feb',
  'mar',
  'abr',
  'may',
  'jun',
  'jul',
  'ago',
  'set',
  'oct',
  'nov',
  'dic'
])

export function getModuleLabel(module) {
  return MODULE_LABELS[module] || String(module ?? '')
}

export function getSourceLabel(source) {
  return SOURCE_LABELS[source] || SOURCE_LABELS[SOURCE_NONE]
}

export function getSourceVariant(source) {
  return SOURCE_VARIANTS[source] || SOURCE_VARIANTS[SOURCE_NONE]
}

export function hasLock(row) {
  return Boolean(row) && Boolean(row.locked_through) && row.source !== SOURCE_NONE
}

export function formatLockDate(value) {
  if (typeof value !== 'string') return ''

  const match = value.match(DATE_PARTS)
  if (!match) return ''

  const monthIndex = Number(match[2]) - 1
  if (monthIndex < 0 || monthIndex > 11) return ''

  return `${match[3]} ${MONTHS[monthIndex]} ${match[1]}`
}

export function formatLockMoment(value) {
  const date = formatLockDate(value)
  if (!date) return ''

  const time = typeof value === 'string' ? value.match(TIME_PARTS) : null
  return time ? `${date}, ${time[1]}:${time[2]}` : date
}

// `null` means the group row (toda la organización); any other value is the
// sede the surface operates on.
function normalizeBranchId(branchId) {
  if (branchId === null || branchId === undefined || branchId === '') return null

  const numeric = Number(branchId)
  return Number.isFinite(numeric) ? numeric : null
}

export function buildLocksPath(branchId = null) {
  const id = normalizeBranchId(branchId)
  return id === null ? '/api/accounting-locks' : `/api/accounting-locks?branch_id=${id}`
}

export function buildLockModulePath(module) {
  return `/api/accounting-locks/${module}`
}

// A DELETE carries its scope in the query string; a PUT carries it in the body.
export function buildLockDeletePath(module, branchId = null) {
  const id = normalizeBranchId(branchId)
  const base = buildLockModulePath(module)

  return id === null ? base : `${base}?branch_id=${id}`
}

export function buildLockPayload(date, branchId = null) {
  const id = normalizeBranchId(branchId)
  const payload = { locked_through: String(date ?? '').trim() }

  if (id !== null) payload.branch_id = id

  return payload
}
