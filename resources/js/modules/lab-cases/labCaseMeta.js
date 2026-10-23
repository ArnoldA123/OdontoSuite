// F5 (#316) — single source of phase presentation for the prosthetic
// laboratory desk. The five phases are the machine's order (LabCase::PHASES);
// the surface never invents one and never offers a phase the service would
// reject, so `nextPhases()` is the only door the UI can open.

export const PHASES = ['in_preparation', 'in_laboratory', 'try_in', 'delivered', 'placed']

export const PHASE_LABELS = {
  in_preparation: 'En preparación',
  in_laboratory: 'En laboratorio',
  try_in: 'Prueba en boca',
  delivered: 'Recibido del taller',
  placed: 'Colocado'
}

const BADGE_VARIANTS = {
  placed: 'success',
  delivered: 'info',
  try_in: 'warning'
}

const CLOSED_PHASES = ['delivered', 'placed']

const MONTHS = ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'set', 'oct', 'nov', 'dic']

const EMPTY = '—'

const DATE_ONLY = /^(\d{4})-(\d{2})-(\d{2})/
const DATE_TIME = /^(\d{4})-(\d{2})-(\d{2})[T ](\d{2}):(\d{2})/

export const phaseLabel = status => PHASE_LABELS[status] || status || EMPTY

export const phaseBadgeVariant = status => BADGE_VARIANTS[status] || 'neutral'

// The machine only moves forward, so the door offers what comes after the
// current phase. An unknown status (a row from an older payload) offers the
// whole machine instead of an empty door.
export const nextPhases = status => {
  const index = PHASES.indexOf(status)
  if (index === -1) return [...PHASES]
  return PHASES.slice(index + 1)
}

const partsOf = value => {
  if (!value) return null

  if (value instanceof Date) {
    if (Number.isNaN(value.getTime())) return null
    return { year: value.getFullYear(), month: value.getMonth() + 1, day: value.getDate() }
  }

  const match = String(value).match(DATE_ONLY)
  if (!match) return null
  return { year: Number(match[1]), month: Number(match[2]), day: Number(match[3]) }
}

const timeOf = value => {
  if (!value || value instanceof Date) return null
  const match = String(value).match(DATE_TIME)
  if (!match) return null
  return { hour: match[4], minute: match[5] }
}

const shortDate = parts =>
  `${String(parts.day).padStart(2, '0')} ${MONTHS[parts.month - 1]} ${parts.year}`

const sortKey = parts => parts.year * 10000 + parts.month * 100 + parts.day

export const formatDate = value => {
  const parts = partsOf(value)
  return parts ? shortDate(parts) : EMPTY
}

export const formatDateTime = value => {
  const parts = partsOf(value)
  if (!parts) return EMPTY

  const time = timeOf(value)
  return time ? `${shortDate(parts)}, ${time.hour}:${time.minute}` : shortDate(parts)
}

// `promised_at` is a date, not an instant: the case is due from the day after
// the promise, and a delivered/placed case can no longer be late.
export const isOverdue = (row, today = new Date()) => {
  if (!row?.promised_at) return false
  if (CLOSED_PHASES.includes(row.status)) return false

  const promised = partsOf(row.promised_at)
  const reference = partsOf(today)
  if (!promised || !reference) return false

  return sortKey(promised) < sortKey(reference)
}

export const patientFullName = patient => {
  if (!patient) return EMPTY
  if (patient.full_name) return patient.full_name
  const composed = [patient.first_name, patient.last_name].filter(Boolean).join(' ').trim()
  return composed || EMPTY
}

export const pieceLabel = dentalPiece => {
  if (!dentalPiece || dentalPiece.fdi_number === null || dentalPiece.fdi_number === undefined) {
    return EMPTY
  }
  return String(dentalPiece.fdi_number)
}
