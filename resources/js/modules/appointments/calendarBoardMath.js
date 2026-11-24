// Day board layout math — pure and presentation-free, so the resource board
// can be unit-tested without a DOM (same rule as `dragDropMath.js`).
//
// The Day view is one column per resource (ambiente by default, professional
// on demand) and one row per hour. Every number below is derived here:
// the visible window, the pixel geometry of a block, the free gaps that can be
// booked, the occupancy of a column and the state a column is in right now.
// The template owns copy and tokens; this module owns arithmetic.

export const BOARD_DEFAULT_START_HOUR = 7
export const BOARD_DEFAULT_END_HOUR = 20
export const BOARD_HOUR_PX = 72
export const BOARD_MINUTE_PX = BOARD_HOUR_PX / 60
export const BOARD_BLOCK_GAP_PX = 4
// Un bloque de 15 minutos no llega a las dos líneas: el piso garantiza que la
// hora y el paciente quepan en una sola línea sin recortarse.
export const BOARD_MIN_BLOCK_PX = 24
// T5b: la semana comparte el bloque del tablero pero con una hora más alta, así
// que los bloques respiran: 84 px por hora y la ventana arranca a las 07:00.
export const WEEK_HOUR_PX = 84
export const WEEK_MINUTE_PX = WEEK_HOUR_PX / 60
export const BOARD_GUTTER_PX = 56
// T5a: una columna más angosta deja aire entre recursos sin cortar el nombre
// del ambiente ni la línea de estado.
export const BOARD_COLUMN_MIN_PX = 176
export const MIN_FREE_SLOT_MINUTES = 30
// Un hueco mayor que dos horas no es una cita probable: se declara «Sin citas»
// en vez de ofrecer un botón que ocupa media tarde.
export const MAX_BOOKABLE_GAP_MINUTES = 120

const toDate = value => {
  if (value instanceof Date) return Number.isNaN(value.getTime()) ? null : value
  if (value === null || value === undefined || value === '') return null
  const parsed = new Date(value)
  return Number.isNaN(parsed.getTime()) ? null : parsed
}

/** Minutes since local midnight for a date-ish value, or `null`. */
export function minutesFromMidnight(value) {
  const date = toDate(value)
  if (!date) return null
  return date.getHours() * 60 + date.getMinutes()
}

export function appointmentStartMinutes(appointment) {
  return minutesFromMidnight(appointment?.scheduled_at)
}

/**
 * Block length in minutes. `duration_minutes` is the stored column the API
 * publishes, so it is the first answer; the scheduled range is the fallback
 * when a payload omits it. Neither path returns zero for a real block.
 */
export function appointmentDurationMinutes(appointment) {
  const declared = Number(appointment?.duration_minutes)
  if (Number.isFinite(declared) && declared > 0) return declared

  const start = toDate(appointment?.scheduled_at)
  const end = toDate(appointment?.ends_at)
  if (!start || !end) return 0
  const diff = Math.round((end.getTime() - start.getTime()) / 60000)
  return diff > 0 ? diff : 0
}

/**
 * The visible band of the board: the nominal business window, widened for any
 * appointment that falls outside it. Never narrower than one hour.
 */
export function boardWindow(appointments = []) {
  let startHour = BOARD_DEFAULT_START_HOUR
  let endHour = BOARD_DEFAULT_END_HOUR

  for (const appointment of appointments) {
    const start = appointmentStartMinutes(appointment)
    if (start === null) continue
    const end = start + appointmentDurationMinutes(appointment)
    startHour = Math.min(startHour, Math.floor(start / 60))
    endHour = Math.max(endHour, Math.ceil(end / 60))
  }

  startHour = Math.max(0, Math.min(startHour, 23))
  endHour = Math.max(startHour + 1, Math.min(endHour, 24))

  return {
    startHour,
    endHour,
    startMinutes: startHour * 60,
    endMinutes: endHour * 60,
    height: (endHour - startHour) * BOARD_HOUR_PX
  }
}

/** Hour labels of the window: the opening hour through the one before closing. */
export function boardHours(window) {
  const hours = []
  if (!window) return hours
  for (let hour = window.startHour; hour < window.endHour; hour += 1) {
    hours.push(hour)
  }
  return hours
}

/** Vertical offset in px of a date-ish value inside the window, or `null`. */
export function boardOffset(value, window, minutePx = BOARD_MINUTE_PX) {
  const minutes = minutesFromMidnight(value)
  if (minutes === null || !window) return null
  return (minutes - window.startMinutes) * minutePx
}

/** Height in px of a block of `minutes`, leaving the inter-block gap. */
export function boardBlockHeight(minutes, minutePx = BOARD_MINUTE_PX) {
  const safe = Number.isFinite(minutes) && minutes > 0 ? minutes : 0
  return Math.max(BOARD_MIN_BLOCK_PX, safe * minutePx - BOARD_BLOCK_GAP_PX)
}

/** Height in px of a whole window at the given hour height. */
export function boardWindowHeight(window, hourPx = BOARD_HOUR_PX) {
  if (!window) return 0
  return ((window.endMinutes - window.startMinutes) / 60) * hourPx
}

/**
 * Bookable holes of one resource inside the window: 30 minutes or more between
 * rendered blocks. A cancelled appointment keeps its block on the board, so it
 * also keeps its minutes out of the gaps (no button is painted over a chip).
 */
export function freeGaps(appointments = [], window, minimum = MIN_FREE_SLOT_MINUTES) {
  if (!window) return []

  const busy = []
  for (const appointment of appointments) {
    const start = appointmentStartMinutes(appointment)
    if (start === null) continue
    const from = Math.max(start, window.startMinutes)
    const to = Math.min(start + appointmentDurationMinutes(appointment), window.endMinutes)
    if (to <= from) continue
    busy.push([from, to])
  }
  busy.sort((a, b) => a[0] - b[0])

  const merged = []
  for (const interval of busy) {
    const last = merged[merged.length - 1]
    if (last && interval[0] <= last[1]) {
      last[1] = Math.max(last[1], interval[1])
    } else {
      merged.push([interval[0], interval[1]])
    }
  }

  const gaps = []
  const pushGap = (from, to) => {
    const minutes = to - from
    if (minutes < minimum) return
    gaps.push({
      startMinutes: from,
      endMinutes: to,
      minutes,
      hour: Math.floor(from / 60),
      minute: from % 60,
      top: (from - window.startMinutes) * BOARD_MINUTE_PX,
      height: minutes * BOARD_MINUTE_PX - BOARD_BLOCK_GAP_PX
    })
  }

  let cursor = window.startMinutes
  for (const [from, to] of merged) {
    pushGap(cursor, from)
    cursor = Math.max(cursor, to)
  }
  pushGap(cursor, window.endMinutes)

  return gaps
}

/**
 * Whether a free hole is still an offer: only holes between 30 and 120 minutes
 * are bookable, so the board never paints a three-hour gap as a button the
 * size of the afternoon.
 */
export function gapIsBookable(gap) {
  if (!gap) return false
  const minutes = Number(gap.minutes)
  return (
    Number.isFinite(minutes) &&
    minutes >= MIN_FREE_SLOT_MINUTES &&
    minutes <= MAX_BOOKABLE_GAP_MINUTES
  )
}

/** Minutes of the window a resource has booked (cancelled blocks do not count). */
export function resourceOccupancy(appointments = [], window) {
  if (!window) return { minutes: 0, percent: 0 }

  let minutes = 0
  for (const appointment of appointments) {
    if (appointment?.status === 'cancelled') continue
    const start = appointmentStartMinutes(appointment)
    if (start === null) continue
    const from = Math.max(start, window.startMinutes)
    const to = Math.min(start + appointmentDurationMinutes(appointment), window.endMinutes)
    if (to > from) minutes += to - from
  }

  const capacity = Math.max(1, window.endMinutes - window.startMinutes)
  const bounded = Math.min(minutes, capacity)
  return { minutes: bounded, percent: Math.round((bounded / capacity) * 100) }
}

/**
 * What a column is doing right now, as data (the template composes the copy):
 * `busy` when a block covers the current minute, `wait` when a patient is in
 * the waiting room, `free` otherwise alongside the next booked start.
 */
export function resourceState(appointments = [], now = new Date()) {
  const nowMinutes = minutesFromMidnight(now)
  const sorted = [...appointments].sort(
    (a, b) => (appointmentStartMinutes(a) ?? 0) - (appointmentStartMinutes(b) ?? 0)
  )

  let current = null
  let waiting = null
  let next = null

  for (const appointment of sorted) {
    if (appointment?.status === 'cancelled') continue
    const start = appointmentStartMinutes(appointment)
    if (start === null) continue
    const end = start + appointmentDurationMinutes(appointment)

    if (nowMinutes !== null && start <= nowMinutes && nowMinutes < end) {
      current = { appointment, start, end }
    }
    if (appointment?.status === 'arrived' && !waiting) {
      waiting = { appointment, start, end }
    }
    if (nowMinutes !== null && start > nowMinutes && (!next || start < next.start)) {
      next = { appointment, start, end }
    }
  }

  if (current) {
    return {
      tone: 'busy',
      status: current.appointment.status,
      appointment: current.appointment,
      untilMinutes: current.end,
      nextMinutes: next ? next.start : null
    }
  }

  if (waiting) {
    return {
      tone: 'wait',
      status: waiting.appointment.status,
      appointment: waiting.appointment,
      untilMinutes: null,
      nextMinutes: next ? next.start : null
    }
  }

  return {
    tone: 'free',
    status: null,
    appointment: null,
    untilMinutes: null,
    nextMinutes: next ? next.start : null
  }
}

/**
 * How much a block shows, from its own length: a 30-minute block prints only
 * its start (one line), 45–59 minutes print the range and the name, 60–89
 * minutes add the procedure (or the state tag), and 90 minutes or more have
 * room for the footer. The tiers exist so no block is ever clipped by its
 * content.
 */
export function appointmentSizeTier(minutes) {
  const safe = Number.isFinite(minutes) ? minutes : 0
  if (safe < 45) return 'compact'
  if (safe < 60) return 'short'
  if (safe < 90) return 'standard'
  return 'regular'
}

/**
 * Duración legible para una espera o una atención: minutos por debajo de la
 * hora y horas + minutos por encima (`76` → «1 h 16 min», `60` → «1 h»,
 * `263` → «4 h 23 min»). Un valor que no es un número de minutos responde «—».
 */
export function formatDurationMinutes(minutes) {
  if (minutes === null || minutes === undefined || minutes === '') return '—'
  const value = Number(minutes)
  if (!Number.isFinite(value) || value < 0) return '—'
  const total = Math.round(value)
  if (total < 60) return `${total} min`
  const hours = Math.floor(total / 60)
  const rest = total % 60
  return rest === 0 ? `${hours} h` : `${hours} h ${rest} min`
}

// T8 — capacidad. Tres carriles por grupo de solape: por encima de eso, los
// que no caben no se apilan — se agrupan en un indicador «+N», y el carril del
// indicador se reserva para que nadie quede debajo de nadie.
export const OVERLAP_MAX_LANES = 3

/**
 * Reparte en carriles las citas de una columna que se solapan en el tiempo.
 *
 * Devuelve `{ blocks, indicators }`:
 *   - `blocks`: `{ appointment, slot, slots, hidden }` — `slots` es la cantidad
 *     de huecos del grupo y `slot` el índice; el ancho de cada uno es
 *     `100 / slots`. `hidden` marca las citas que no caben (carril ≥ máximo).
 *   - `indicators`: `{ slot, slots, count, startMinutes, endMinutes }` — un
 *     «+count» por grupo, con la ventana que ocupa lo agrupado.
 *
 * Los grupos son transitivos: una cadena de solapes comparte carriles, aunque
 * sus extremos no se toquen.
 */
export function layoutOverlapLanes(appointments = [], { maxLanes = OVERLAP_MAX_LANES } = {}) {
  const lanesLimit = Math.max(1, Math.floor(Number(maxLanes)) || 1)
  const items = (Array.isArray(appointments) ? appointments : [])
    .map(appointment => {
      const start = appointmentStartMinutes(appointment)
      const duration = appointmentDurationMinutes(appointment)
      return { appointment, start, end: start === null ? null : start + duration }
    })
    .filter(item => item.start !== null)
    .sort(
      (a, b) =>
        a.start - b.start ||
        b.end - a.end ||
        String(a.appointment?.id ?? '').localeCompare(String(b.appointment?.id ?? ''))
    )

  const blocks = []
  const indicators = []
  let cluster = []
  let clusterEnd = -1

  const flush = () => {
    if (cluster.length === 0) return

    // Carril libre: el primero que ya terminó. Si ninguno, se abre uno nuevo.
    const laneEnds = []
    const laneOf = new Map()
    for (const item of cluster) {
      let lane = laneEnds.findIndex(end => end <= item.start)
      if (lane === -1) {
        lane = laneEnds.length
        laneEnds.push(item.end)
      } else {
        laneEnds[lane] = item.end
      }
      laneOf.set(item, lane)
    }

    const used = laneEnds.length
    const slots = used <= lanesLimit ? used : lanesLimit + 1
    const hidden = cluster.filter(item => laneOf.get(item) >= lanesLimit)
    const hiddenSlot = slots - 1

    for (const item of cluster) {
      const lane = laneOf.get(item)
      blocks.push({
        appointment: item.appointment,
        slot: lane >= lanesLimit ? hiddenSlot : lane,
        slots,
        hidden: lane >= lanesLimit
      })
    }

    if (hidden.length > 0) {
      indicators.push({
        slot: hiddenSlot,
        slots,
        count: hidden.length,
        startMinutes: Math.min(...hidden.map(item => item.start)),
        endMinutes: Math.max(...hidden.map(item => item.end))
      })
    }

    cluster = []
    clusterEnd = -1
  }

  for (const item of items) {
    if (cluster.length > 0 && item.start >= clusterEnd) flush()
    cluster.push(item)
    clusterEnd = Math.max(clusterEnd, item.end)
  }
  flush()

  return { blocks, indicators }
}

export const UNASSIGNED_RESOURCE_NAME = 'Sin ambiente'
export const UNASSIGNED_PROFESSIONAL_NAME = 'Sin profesional'

const byStart = (a, b) =>
  (appointmentStartMinutes(a) ?? 0) - (appointmentStartMinutes(b) ?? 0) ||
  String(a?.id ?? '').localeCompare(String(b?.id ?? ''))

const buildColumn = (key, resource, appointments) => ({
  key,
  id: resource?.id ?? null,
  name: resource?.name || '',
  meta: resource?.code || '',
  appointments: [...appointments].sort(byStart)
})

/**
 * Columns grouped by ambiente. The active catalogue is the population — a room
 * exists whether or not it holds appointments — and any room a row names but
 * the catalogue does not publish still gets its column, so a block is never
 * dropped. Rows with no room land in the `Sin ambiente` bucket.
 */
export function groupByChair(appointments = [], chairs = []) {
  const buckets = new Map()

  for (const chair of chairs) {
    if (chair?.id === null || chair?.id === undefined) continue
    buckets.set(String(chair.id), { resource: chair, appointments: [] })
  }

  for (const appointment of appointments) {
    const chair = appointment?.dental_chair
    if (!chair?.id) continue
    const key = String(chair.id)
    if (!buckets.has(key)) {
      buckets.set(key, {
        resource: { id: chair.id, name: chair.name, code: chair.code },
        appointments: []
      })
    }
    buckets.get(key).appointments.push(appointment)
  }

  const columns = [...buckets.entries()].map(([key, bucket]) =>
    buildColumn(`chair-${key}`, bucket.resource, bucket.appointments)
  )

  const unassigned = appointments.filter(appointment => !appointment?.dental_chair?.id)
  if (unassigned.length) {
    columns.push({
      key: 'chair-none',
      id: null,
      name: UNASSIGNED_RESOURCE_NAME,
      meta: '',
      appointments: [...unassigned].sort(byStart)
    })
  }

  return columns
}

/**
 * Columns grouped by professional. A professional only becomes a column when
 * the day holds a block of theirs; rows with no professional land in the
 * `Sin profesional` bucket. The name always comes from the row, never from a
 * guess.
 */
export function groupByProfessional(appointments = []) {
  const buckets = new Map()

  for (const appointment of appointments) {
    const user = appointment?.user
    const id = appointment?.user_id ?? user?.id
    const key = id === null || id === undefined ? '' : String(id)
    if (!buckets.has(key)) {
      buckets.set(key, {
        resource: key ? { id, name: user?.name || '' } : null,
        appointments: []
      })
    }
    buckets.get(key).appointments.push(appointment)
  }

  return [...buckets.entries()].map(([key, bucket]) =>
    key
      ? buildColumn(`professional-${key}`, bucket.resource, bucket.appointments)
      : {
          key: 'professional-none',
          id: null,
          name: UNASSIGNED_PROFESSIONAL_NAME,
          meta: '',
          appointments: [...bucket.appointments].sort(byStart)
        }
  )
}
