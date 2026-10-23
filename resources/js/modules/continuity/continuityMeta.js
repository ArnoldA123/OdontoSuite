// F4 (#310) — presentation helpers of the continuity desk. Pure functions with
// no Vue import: the page and its unit suite consume the same contract.
// `compliancePercent` deliberately only formats the API's `compliance_rate`; it
// never derives the rate from the counts, because the backend owns that rule.

const DATE_FORMATTER = new Intl.DateTimeFormat('es-PE', {
  day: '2-digit',
  month: 'short',
  year: 'numeric'
})

const TIME_FORMATTER = new Intl.DateTimeFormat('es-PE', {
  hour: '2-digit',
  minute: '2-digit',
  hourCycle: 'h23'
})

const INTERVAL_LABELS = {
  3: '3 meses',
  6: '6 meses',
  12: '12 meses'
}

const KIND_LABELS = {
  next_session: 'Próxima sesión',
  control: 'Control',
  recall: 'Recall'
}

const DATE_ONLY = /^\d{4}-\d{2}-\d{2}$/

// A date-only value (`2026-10-20`, the shape of `due_date`) parses as UTC
// midnight and renders as the previous day in a UTC-5 session. Anchoring it to
// local midnight keeps the label on the day the API named.
const toDate = value => {
  if (value instanceof Date) return Number.isNaN(value.getTime()) ? null : value
  if (value === null || value === undefined || value === '') return null
  const text = String(value)
  const parsed = new Date(DATE_ONLY.test(text) ? `${text}T00:00:00` : text)
  return Number.isNaN(parsed.getTime()) ? null : parsed
}

// es-PE renders the short month as `nov.`; the desk writes `nov`.
const dateLabel = date => DATE_FORMATTER.format(date).replace(/\./g, '').replace(/\s+/g, ' ').trim()

const timeLabel = date => TIME_FORMATTER.format(date)

const pointLabel = date => `${dateLabel(date)}, ${timeLabel(date)}`

export function patientFullName(patient) {
  if (!patient) return 'Paciente'
  if (patient.full_name) return patient.full_name
  const composed = [patient.first_name, patient.last_name].filter(Boolean).join(' ').trim()
  return composed || 'Paciente'
}

export function intervalLabel(months) {
  if (INTERVAL_LABELS[months]) return INTERVAL_LABELS[months]
  const numeric = Number(months)
  return Number.isFinite(numeric) && numeric > 0 ? `${numeric} meses` : '—'
}

export function originLabel(request) {
  if (request?.treatment_plan_id) return 'Plan'
  if (request?.clinical_encounter_id) return 'Atención'
  if (request?.appointment_id) return 'Cita'
  return '—'
}

export function kindLabel(kind) {
  return KIND_LABELS[kind] || kind || '—'
}

export function preferredWindow(from, to) {
  const start = toDate(from)
  const end = toDate(to)

  if (!start && !end) return '—'
  if (!end) return pointLabel(start)
  if (!start) return pointLabel(end)

  // Same day: the reader already knows the date from the start of the range.
  if (dateLabel(start) === dateLabel(end)) {
    return `${pointLabel(start)} – ${timeLabel(end)}`
  }

  return `${pointLabel(start)} – ${pointLabel(end)}`
}

export function compliancePercent(rate) {
  if (rate === null || rate === undefined || rate === '') return '—'
  const numeric = Number(rate)
  if (!Number.isFinite(numeric)) return '—'
  return `${Math.round(numeric * 100)}%`
}

export function formatDate(value) {
  const date = toDate(value)
  return date ? dateLabel(date) : '—'
}

// `datetime-local` speaks the session's wall clock (`YYYY-MM-DDTHH:mm`) while
// both schedule doors take `YYYY-MM-DD HH:mm:ss`. These two helpers are the only
// bridge between the form and the API, so the conversion lives here instead of
// in the modal.
const pad = value => String(value).padStart(2, '0')

const localInput = date =>
  `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}T${pad(date.getHours())}:${pad(date.getMinutes())}`

const NAIVE_DATETIME = /^(\d{4}-\d{2}-\d{2})[T ](\d{2}:\d{2})(?::\d{2}(?:\.\d+)?)?$/

// A naive `YYYY-MM-DDTHH:mm[:ss]` is already the wall clock the API stored, so
// it is sliced instead of parsed: `new Date()` would shift it through the
// session's timezone. Anything carrying an offset is converted to local time.
export function toDateTimeLocal(value) {
  if (value instanceof Date) return Number.isNaN(value.getTime()) ? '' : localInput(value)
  if (value === null || value === undefined || value === '') return ''

  const naive = NAIVE_DATETIME.exec(String(value))
  if (naive) return `${naive[1]}T${naive[2]}`

  const date = toDate(value)
  return date ? localInput(date) : ''
}

export function toApiDateTime(value) {
  if (value === null || value === undefined || value === '') return ''

  const matched = /^(\d{4}-\d{2}-\d{2})[T ](\d{2}:\d{2})(?::(\d{2}))?/.exec(String(value))
  if (!matched) return ''

  return `${matched[1]} ${matched[2]}:${matched[3] || '00'}`
}

// A recall has no preferred time, only an overdue due date. Reception is
// offered that date at 09:00; when the instant is already past, tomorrow at
// 09:00 keeps the prefilled form submittable.
export function defaultRecallSlot(dueDate, now = new Date()) {
  const due = toDate(dueDate)
  const dueStart = due ? new Date(due.getFullYear(), due.getMonth(), due.getDate(), 9, 0, 0) : null

  if (dueStart && dueStart.getTime() > now.getTime()) return localInput(dueStart)

  return localInput(new Date(now.getFullYear(), now.getMonth(), now.getDate() + 1, 9, 0, 0))
}
