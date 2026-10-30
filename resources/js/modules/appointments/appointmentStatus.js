// Design decision D2: single source of truth for appointment status
// presentation. Keys cover every status the API can return: `arrived` is
// written by POST /appointments/{id}/check-in, `in_consultation` by
// POST /appointments/{id}/start-attention, and the rest by
// AppointmentController@updateStatus and the appointment FormRequests. The
// legacy `in_progress` DB enum value is kept by
// database/migrations/2025_10_14_123001_fix_appointments_status_enum.php (the
// app writes `in_consultation`, but serialized rows may still carry it).

// T8b defect 2 — chip anatomy contract: the BASE classes carry shape only
// (border width here; padding/radius/shadow live on the chip templates per
// view). They must NOT set a background or a border colour: with both layers
// setting background-color/border-color the CSS cascade decides by
// stylesheet order and the registry status tint lost in the real browser
// (every chip computed to rgb(255,255,255)). The registry `chip` entry
// below is the single source of the tint (background + border colour).
export const APPOINTMENT_CHIP_BASE_CLASSES = 'border'

export const APPOINTMENT_STATUSES = {
  scheduled: {
    label: 'Programada',
    badgeVariant: 'info',
    showDot: true,
    chip: 'border-accent-200 bg-accent-100',
    tone: 'bg-accent-100 text-accent-700'
  },
  confirmed: {
    label: 'Confirmada',
    badgeVariant: 'success',
    showDot: true,
    chip: 'border-systemGreen-200 bg-systemGreen-50',
    tone: 'bg-systemGreen-50 text-systemGreen-700'
  },
  arrived: {
    label: 'En espera',
    badgeVariant: 'success',
    showDot: true,
    chip: 'border-systemGreen-200 bg-systemGreen-50',
    tone: 'bg-systemGreen-50 text-systemGreen-700'
  },
  in_consultation: {
    label: 'En Consulta',
    badgeVariant: 'warning',
    showDot: true,
    chip: 'border-systemYellow-200 bg-systemYellow-50',
    tone: 'bg-systemYellow-50 text-systemYellow-700'
  },
  in_progress: {
    label: 'En Consulta',
    badgeVariant: 'warning',
    showDot: true,
    chip: 'border-systemYellow-200 bg-systemYellow-50',
    tone: 'bg-systemYellow-50 text-systemYellow-700'
  },
  completed: {
    label: 'Completada',
    badgeVariant: 'neutral',
    showDot: true,
    chip: 'border-hairline bg-theme-surface',
    tone: 'bg-systemGray-100 text-systemGray-700'
  },
  cancelled: {
    label: 'Cancelada',
    badgeVariant: 'error',
    showDot: true,
    chip: 'border-systemRed-200 bg-systemRed-50',
    tone: 'bg-systemRed-50 text-systemRed-700'
  },
  no_show: {
    label: 'No se presentó',
    badgeVariant: 'neutral',
    showDot: true,
    chip: 'border-systemYellow-200 bg-systemYellow-50',
    tone: 'bg-systemYellow-50 text-systemYellow-700'
  },
  rescheduled: {
    label: 'Reprogramada',
    badgeVariant: 'warning',
    showDot: false,
    chip: 'border-accent-200 bg-accent-100',
    tone: 'bg-accent-100 text-accent-700'
  }
}

export const APPOINTMENT_STATUS_KEYS = Object.keys(APPOINTMENT_STATUSES)

const FALLBACK_STATUS = 'scheduled'

const entryFor = status =>
  Object.hasOwn(APPOINTMENT_STATUSES, status)
    ? APPOINTMENT_STATUSES[status]
    : APPOINTMENT_STATUSES[FALLBACK_STATUS]

export function statusLabel(status) {
  if (Object.hasOwn(APPOINTMENT_STATUSES, status)) return APPOINTMENT_STATUSES[status].label
  return typeof status === 'string' ? status : ''
}

export function statusBadgeVariant(status) {
  return entryFor(status).badgeVariant
}

export function statusBadgeProps(status) {
  const entry = entryFor(status)
  return { variant: entry.badgeVariant, showDot: entry.showDot }
}

export function statusChipClasses(status) {
  return entryFor(status).chip
}

export function statusToneClasses(status) {
  return entryFor(status).tone
}
