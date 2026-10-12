// Design decision (T5 drag-and-drop): pure math for the calendar drag
// reschedule flow, extracted from CalendarPage.vue so the drop rules are unit
// testable without a DOM. No Vue, no Date parsing of untrusted free-form
// strings: slot keys are `<YYYY-MM-DD>-<hour>` (hour-granular, minute always
// :00) and payloads are naive-local `YYYY-MM-DDTHH:mm` strings, never
// `toISOString()` (CITAS-TZ-001 — the server reads local time as app.timezone).

import { toLocalDateString } from './calendarDateMath.js'

/** Pointer travel (px) before a chip press becomes a drag. */
export const DRAG_THRESHOLD_PX = 6

/** Mirrors the endpoint rule: `scheduled_at` must be >= 1 minute in the future. */
const MIN_FUTURE_MS = 60 * 1000

const SLOT_KEY_PATTERN = /^(\d{4}-\d{2}-\d{2})-(\d{1,2})$/
const LOCAL_DATE_TIME_PATTERN = /^(\d{4})-(\d{2})-(\d{2})T(\d{2}):(\d{2})$/

const parseLocalDateTime = value => {
  const match = LOCAL_DATE_TIME_PATTERN.exec(typeof value === 'string' ? value : '')
  if (!match) return null
  const [year, month, day, hour, minute] = match.slice(1).map(Number)
  const date = new Date(year, month - 1, day, hour, minute)
  if (
    date.getFullYear() !== year ||
    date.getMonth() !== month - 1 ||
    date.getDate() !== day ||
    date.getHours() !== hour ||
    date.getMinutes() !== minute
  ) {
    return null
  }
  return date
}

const formatLocalDateTime = date => {
  const pad = value => String(value).padStart(2, '0')
  return (
    `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}` +
    `T${pad(date.getHours())}:${pad(date.getMinutes())}`
  )
}

/**
 * Resolve the drop slot under the pointer from slot-cell geometry.
 *
 * @param {Array<{key: string, rect: {left: number, top: number, right: number, bottom: number}}>} rects
 * @param {number} x clientX
 * @param {number} y clientY
 * @returns {string|null} slot key or null when the pointer hits no cell
 */
export function resolveDropSlot(rects, x, y) {
  if (!Array.isArray(rects)) return null
  for (const entry of rects) {
    if (!entry || !entry.key || !entry.rect) continue
    const { left, top, right, bottom } = entry.rect
    if (x >= left && x <= right && y >= top && y <= bottom) {
      return entry.key
    }
  }
  return null
}

/**
 * Convert a `<YYYY-MM-DD>-<hour>` slot key into the naive-local payload
 * string `YYYY-MM-DDTHH:00`. Returns null for keys that do not name a real
 * calendar date/hour (year and month boundaries included).
 *
 * @param {string} slotKey
 * @returns {string|null}
 */
export function slotKeyToScheduledAt(slotKey) {
  const match = SLOT_KEY_PATTERN.exec(typeof slotKey === 'string' ? slotKey : '')
  if (!match) return null
  const hour = Number(match[2])
  const candidate = `${match[1]}T${String(hour).padStart(2, '0')}:00`
  return parseLocalDateTime(candidate) ? candidate : null
}

/**
 * Add minutes to a naive-local `YYYY-MM-DDTHH:mm` string, crossing day,
 * month and year boundaries through calendar components.
 *
 * @param {string} dateTime
 * @param {number} minutes
 * @returns {string|null}
 */
export function addMinutesToLocal(dateTime, minutes) {
  const start = parseLocalDateTime(dateTime)
  if (!start || !Number.isFinite(Number(minutes))) return null
  return formatLocalDateTime(new Date(start.getTime() + Number(minutes) * 60 * 1000))
}

/**
 * Drop validity for an appointment dragged onto a slot. Fail closed:
 * rejects an unresolvable target, the appointment's own current slot (no-op,
 * no request), appointments whose current start cannot be parsed, and any
 * target closer than one minute ahead (the endpoint's validation rule), so
 * the UI rolls back before calling the API.
 *
 * @param {string} slotKey
 * @param {{scheduled_at?: string}} appointment
 * @param {Date} [now]
 * @returns {boolean}
 */
export function canDropOn(slotKey, appointment, now = new Date()) {
  const target = slotKeyToScheduledAt(slotKey)
  if (!target) return false
  const current = new Date(appointment?.scheduled_at)
  if (Number.isNaN(current.getTime())) return false
  const currentSlotKey = `${toLocalDateString(current)}-${current.getHours()}`
  if (currentSlotKey === slotKey) return false
  const targetTime = parseLocalDateTime(target).getTime()
  return targetTime >= now.getTime() + MIN_FUTURE_MS
}
