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

// S2 — the five stages a live appointment walks, in order. `railStep()`
// answers the index of the current one.
export const APPOINTMENT_RAIL_STAGES = [
  { key: 'scheduled', label: 'Programada' },
  { key: 'confirmed', label: 'Confirmada' },
  { key: 'arrived', label: 'En espera' },
  { key: 'in_consultation', label: 'En consulta' },
  { key: 'completed', label: 'Completada' }
]

// S2 — statuses that leave the rail: the detail shows one outcome band
// instead of the five stages.
export const APPOINTMENT_TERMINAL_OUTCOMES = ['cancelled', 'no_show', 'rescheduled']

// S2 — a rail stage publishes its time only from a field the API actually
// returns. `confirmed_at` is not serialized by AppointmentResource today, so
// that stage renders without a time; the consultation start has no published
// field at all. Nothing is derived or invented.
export const APPOINTMENT_STAGE_TIME_FIELDS = {
  scheduled: 'created_at',
  confirmed: 'confirmed_at',
  arrived: 'checked_in_at',
  in_consultation: null,
  completed: 'completed_at'
}

const EN_CONSULTA = {
  label: 'En consulta',
  badgeVariant: 'warning',
  showDot: true,
  active: true,
  chip: 'border-systemYellow-200 bg-systemYellow-50',
  tone: 'bg-systemYellow-50 text-systemYellow-700',
  rail: {
    node: 'border-systemYellow-500',
    dot: 'bg-systemYellow-500',
    label: 'text-systemYellow-700'
  }
}

export const APPOINTMENT_STATUSES = {
  // One identity per status, never the brand accent: `accent-500` is the
  // primary action's colour only.
  scheduled: {
    label: 'Programada',
    badgeVariant: 'neutral',
    showDot: true,
    active: false,
    chip: 'border-systemGray-200 bg-systemGray-50',
    tone: 'bg-systemGray-50 text-systemGray-700',
    rail: {
      node: 'border-systemGray-500',
      dot: 'bg-systemGray-500',
      label: 'text-systemGray-700'
    }
  },
  confirmed: {
    label: 'Confirmada',
    badgeVariant: 'info',
    showDot: true,
    active: false,
    chip: 'border-systemSteel-200 bg-systemSteel-50',
    tone: 'bg-systemSteel-50 text-systemSteel-700',
    rail: {
      node: 'border-systemSteel-500',
      dot: 'bg-systemSteel-500',
      label: 'text-systemSteel-700'
    }
  },
  arrived: {
    label: 'En espera',
    badgeVariant: 'indigo',
    showDot: true,
    active: true,
    chip: 'border-systemIndigo-200 bg-systemIndigo-50',
    tone: 'bg-systemIndigo-50 text-systemIndigo-700',
    rail: {
      node: 'border-systemIndigo-500',
      dot: 'bg-systemIndigo-500',
      label: 'text-systemIndigo-700'
    }
  },
  in_consultation: { ...EN_CONSULTA },
  in_progress: { ...EN_CONSULTA },
  completed: {
    label: 'Completada',
    badgeVariant: 'success',
    showDot: true,
    active: false,
    chip: 'border-systemGreen-200 bg-systemGreen-50',
    tone: 'bg-systemGreen-50 text-systemGreen-700',
    rail: {
      node: 'border-systemGreen-500',
      dot: 'bg-systemGreen-500',
      label: 'text-systemGreen-700'
    }
  },
  cancelled: {
    label: 'Cancelada',
    badgeVariant: 'error',
    showDot: true,
    active: false,
    chip: 'border-systemRed-200 bg-systemRed-50',
    tone: 'bg-systemRed-50 text-systemRed-700',
    rail: {
      node: 'border-systemRed-500',
      dot: 'bg-systemRed-500',
      label: 'text-systemRed-700'
    }
  },
  no_show: {
    label: 'No se presentó',
    badgeVariant: 'orange',
    showDot: true,
    active: false,
    chip: 'border-dashed border-systemOrange-200 bg-systemOrange-50',
    tone: 'bg-systemOrange-50 text-systemOrange-700',
    rail: {
      node: 'border-systemOrange-500',
      dot: 'bg-systemOrange-500',
      label: 'text-systemOrange-700'
    }
  },
  rescheduled: {
    label: 'Reprogramada',
    badgeVariant: 'purple',
    showDot: false,
    active: false,
    chip: 'border-systemPurple-200 bg-systemPurple-50',
    tone: 'bg-systemPurple-50 text-systemPurple-700',
    rail: {
      node: 'border-systemPurple-500',
      dot: 'bg-systemPurple-500',
      label: 'text-systemPurple-700'
    }
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

export function statusRailClasses(status) {
  return entryFor(status).rail
}

export function statusIsActive(status) {
  return entryFor(status).active
}

// Day board — two presentation rules that depend on the status identity and
// therefore live with it: a cancelled block is struck through so it reads as
// cancelled at a glance, and a completed block dims its secondary line so the
// live blocks keep the contrast.
export function statusBlockTitleClasses(status) {
  return status === 'cancelled'
    ? 'text-systemRed-700 line-through decoration-1'
    : 'text-theme-primary'
}

export function statusBlockMetaClasses(status) {
  return status === 'completed' ? 'text-systemGray-600' : 'text-theme-secondary'
}

export function isTerminalOutcome(status) {
  return APPOINTMENT_TERMINAL_OUTCOMES.includes(status)
}

// Index of the current stage in APPOINTMENT_RAIL_STAGES, or -1 for a status
// that left the rail. `in_progress` is the DB twin of `in_consultation` and
// walks the same stage; an unknown status falls back with the registry.
export function railStep(status) {
  if (isTerminalOutcome(status)) return -1
  const key = status === 'in_progress' ? 'in_consultation' : status
  const index = APPOINTMENT_RAIL_STAGES.findIndex(stage => stage.key === key)
  return index === -1 ? railStep(FALLBACK_STATUS) : index
}
