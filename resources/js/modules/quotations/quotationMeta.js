/**
 * Single source of truth for the presupuestos vocabulary: statuses of the DB
 * enum, money and date formatting, item field mapping and the status rule of
 * each action. The permission itself is not decided here (#280/#420): the SPA
 * passes the boolean of `usePermissions().can.*`, a membership check on the
 * list the API publishes, so this module never repeats the role matrix that
 * routes/api.php owns.
 */

export const QUOTATION_STATUSES = Object.freeze([
  'draft',
  'sent',
  'viewed',
  'approved',
  'rejected',
  'expired'
])

// `sent` and `viewed` share the info hue on purpose: both mean "the patient
// still owes a decision". The label carries the distinction.
const STATUS_META = Object.freeze({
  draft: { label: 'Borrador', variant: 'neutral' },
  sent: { label: 'Enviado', variant: 'info' },
  viewed: { label: 'Visto', variant: 'info' },
  approved: { label: 'Aprobado', variant: 'success' },
  rejected: { label: 'Rechazado', variant: 'error' },
  expired: { label: 'Expirado', variant: 'warning' }
})

export const STATUS_FILTER_OPTIONS = Object.freeze([
  { value: '', label: 'Todos los estados' },
  { value: 'draft', label: 'Borrador' },
  { value: 'sent', label: 'Enviado' },
  { value: 'viewed', label: 'Visto' },
  { value: 'approved', label: 'Aprobado' },
  { value: 'rejected', label: 'Rechazado' },
  { value: 'expired', label: 'Expirado' }
])

export const PENDING_STATUSES = Object.freeze(['sent', 'viewed'])

const MONTH_LABELS = Object.freeze([
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

const DATE_KEY = /^(\d{4})-(\d{2})-(\d{2})/

export function getStatusMeta(status) {
  return STATUS_META[status] || { label: status || '—', variant: 'neutral' }
}

/**
 * Reads the calendar date from an API date or timestamp. The API sends date
 * casts as UTC midnight (`2026-10-20T00:00:00.000000Z`); converting that to
 * local time would render the previous day west of Greenwich.
 */
export function calendarDateKey(value) {
  if (!value) return null

  if (value instanceof Date) {
    if (Number.isNaN(value.getTime())) return null
    const month = String(value.getMonth() + 1).padStart(2, '0')
    const day = String(value.getDate()).padStart(2, '0')

    return `${value.getFullYear()}-${month}-${day}`
  }

  const match = String(value).match(DATE_KEY)

  return match ? `${match[1]}-${match[2]}-${match[3]}` : null
}

export function formatQuotationDate(value) {
  const key = calendarDateKey(value)
  if (!key) return '—'

  const [year, month, day] = key.split('-')

  return `${day} ${MONTH_LABELS[Number(month) - 1]} ${year}`
}

function epochDay(key) {
  const [year, month, day] = key.split('-').map(Number)

  return Date.UTC(year, month - 1, day) / 86400000
}

export function daysUntil(value, today = new Date()) {
  const target = calendarDateKey(value)
  const base = calendarDateKey(today)
  if (!target || !base) return null

  return epochDay(target) - epochDay(base)
}

/**
 * Expiry only matters while the patient owes a decision; terminal statuses
 * return null so the list never shows "Vencido" next to an approved quote.
 */
export function expiryState(quotation, today = new Date()) {
  if (!quotation || !PENDING_STATUSES.includes(quotation.status)) return null

  const days = daysUntil(quotation.valid_until, today)
  if (days === null) return null
  if (days < 0) return 'expired'
  if (days <= 7) return 'soon'

  return 'ok'
}

export function expiryBadge(quotation, today = new Date()) {
  const state = expiryState(quotation, today)
  if (state === 'expired') return { label: 'Vencido', variant: 'error' }
  if (state !== 'soon') return null

  const days = daysUntil(quotation.valid_until, today)
  const label = days === 0 ? 'Vence hoy' : `Vence en ${days} ${days === 1 ? 'día' : 'días'}`

  return { label, variant: 'warning' }
}

export function resolveItemTitle(item) {
  return item?.item_name || item?.description || '—'
}

export function resolveItemDescription(item) {
  return item?.item_description || ''
}

/**
 * The boolean is `usePermissions().can.*`, never a role: an unresolved string
 * denies the action instead of granting it.
 */
const granted = writable => writable === true

export function canCreateQuotation(writable) {
  return granted(writable)
}

export function canEditQuotation(quotation, writable) {
  return granted(writable) && quotation?.status === 'draft'
}

export function canApproveQuotation(quotation, writable) {
  return granted(writable) && PENDING_STATUSES.includes(quotation?.status)
}

export function canRejectQuotation(quotation, writable) {
  return granted(writable) && PENDING_STATUSES.includes(quotation?.status)
}

export function canDeleteQuotation(quotation, writable) {
  return granted(writable) && quotation?.status === 'draft'
}

export function canDownloadQuotation(readable) {
  return granted(readable)
}
