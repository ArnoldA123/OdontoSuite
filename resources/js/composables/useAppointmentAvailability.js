/**
 * Agenda del día como fuente de la disponibilidad del alta de cita.
 *
 * El backend no publica un endpoint de huecos: la única lectura que responde
 * "¿quién está ocupado a las 09:00?" es el índice de citas del día
 * (`GET /api/appointments?start_date=&end_date=`, rango inclusivo del día
 * completo). Este módulo separa esa lectura en piezas puras —sin red ni Vue—
 * y un wrapper que publica el estado de la lectura:
 *
 *   - `buildAppointmentAvailability()` — profesionales y sillones como dos
 *     listas independientes con su ocupación, más los pares que la agenda del
 *     día ya usó (atajos de un clic). No propone "la pareja" de nadie: un
 *     profesional puede estar en cualquier sillón libre y cambiarse a media
 *     marcha.
 *   - `buildHourGrid()` — rejilla de casillas de 15 minutos con las horas que
 *     chocan con la agenda del profesional o del sillón elegidos.
 *   - `groupAppointmentTypes()` — tipos agrupados por especialidad, con los de
 *     la especialidad del profesional elegido al frente.
 *   - `rankAppointmentTypes()` — atajo de los tipos más usados del día.
 *   - `useAppointmentAvailability()` — la lectura del día con su estado
 *     (`idle | loading | ready | unknown`). Un fallo de red deja `unknown`,
 *     que es la señal para que la superficie no afirme «Libre».
 *
 * Reglas del dominio:
 *   - Una cita `cancelled` no ocupa: el esquema libera su horario
 *     (`appointments.slot_reservation`) y este cálculo tiene que decir lo
 *     mismo que los índices únicos `unique_user_time_slot` /
 *     `unique_chair_time_slot`.
 *   - La ventana de la cita es semiabierta `[start, end)`: una cita que
 *     termina justo al empezar la ventana no la ocupa.
 *   - La cita que se está editando no se bloquea a sí misma.
 *   - La ocupación del profesional y la del sillón son independientes: un
 *     sillón ocupado por otra persona a esa hora está ocupado aunque el
 *     profesional elegido esté libre, y viceversa.
 *   - Sin dato (`appointments` sin leer, ventana incompleta) la respuesta es
 *     `unknown` y no se emite ninguna lista: es preferible no decir nada a
 *     decir «Libre» con una agenda que no se leyó.
 */

import { ref } from 'vue'
import { useApi } from './useApi'
import { formatTime } from './useFormatters'

const CANCELLED = 'cancelled'

/** Rejilla del alta: 07:00–20:00 en casillas de 15 minutos. */
export const DAY_START = '07:00'
export const DAY_END = '20:00'
export const SLOT_MINUTES = 15

/**
 * Los bloques en que se pagina la rejilla: tres horas por bloque y el último
 * con el resto del día. El bloque de apertura mide tres filas de 44 px, que es
 * lo que deja el alta completa por debajo de la pantalla a 1280×800 incluso con
 * sus dos atajos, sin abrir un scroll dentro de otro scroll y sin partir la
 * sección de tipos.
 */
export const HOUR_BLOCKS = Object.freeze([
  { id: 'morning', label: '07–10', until: '10:00' },
  { id: 'midday', label: '10–13', until: '13:00' },
  { id: 'afternoon', label: '13–16', until: '16:00' },
  { id: 'evening', label: '16–20', until: null }
])

/**
 * Las especialidades llegan por dos caminos que no comparten vocabulario:
 * `users.specialty` es el string legacy (ADR-0007) y `appointment_types`
 * habla el `code` del catálogo. El mapa es el mismo que usa
 * `UserSpecialtySeeder::LEGACY_TO_SPECIALTY_CODE`; solo traduce el string
 * legacy, nunca inventa una especialidad para los valores que nombran un
 * puesto (`dental_technician`, `assistant`, `finance`).
 */
const LEGACY_SPECIALTY_CODES = Object.freeze({
  orthodontics: 'ortodoncia',
  implantology: 'implantologia'
})

const NO_SPECIALTY = 'Sin especialidad'

/** `Dra. Salazar` → `ORTODONCIA`; `Cirugía Oral` → `CIRUGIA_ORAL`. */
const normalizeSpecialtyKey = value => {
  const raw = typeof value === 'string' ? value : value?.code || value?.name || ''

  return String(raw || '')
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .toLowerCase()
    .replace(/[^a-z0-9]+/g, '_')
    .replace(/^_+|_+$/g, '')
}

/**
 * La clave comparable de una especialidad, venga como `{ code, name }` del
 * catálogo de tipos o como el string legacy del profesional. `null` cuando el
 * valor no nombra una especialidad clínica.
 */
export function specialtyKeyOf(value) {
  const key = normalizeSpecialtyKey(value)
  if (!key) return null

  const resolved = LEGACY_SPECIALTY_CODES[key] || key
  const ignored = ['assistant', 'dental_technician', 'finance', 'admin']

  return ignored.includes(resolved) ? null : resolved
}

/**
 * Coerce a date-ish value (Date, 'YYYY-MM-DDTHH:mm', ISO with offset, number)
 * into a valid local `Date`. Invalid input returns `null`.
 */
function toDate(value) {
  if (value instanceof Date) {
    return Number.isNaN(value.getTime()) ? null : value
  }
  if (value === null || value === undefined || value === '') return null

  const parsed = new Date(value)

  return Number.isNaN(parsed.getTime()) ? null : parsed
}

/**
 * Every appointment reduced to the fields the availability math needs.
 * `ends_at` is authoritative; a row that only declares `duration_minutes`
 * (older payloads) still produces a usable span.
 */
function toSpan(appointment) {
  if (!appointment) return null

  const from = toDate(appointment.scheduled_at ?? appointment.start)
  let to = toDate(appointment.ends_at ?? appointment.end)

  if (!to && from) {
    const minutes = Number(appointment.duration_minutes)

    to = minutes > 0 ? new Date(from.getTime() + minutes * 60000) : null
  }

  return {
    id: appointment.id ?? null,
    userId: appointment.user_id ?? appointment.user?.id ?? null,
    chairId: appointment.dental_chair_id ?? appointment.dental_chair?.id ?? null,
    status: appointment.status ?? null,
    from,
    to
  }
}

const overlaps = (from, to, windowStart, windowEnd) => from < windowEnd && to > windowStart

/**
 * Un campo fecha (`YYYY-MM-DD`) se lee en el reloj de la sesión, no en UTC:
 * `new Date('2026-11-05')` es medianoche UTC y en Lima ya es el día anterior.
 */
function toLocalDay(value) {
  const match = String(value ?? '').match(/^(\d{4})-(\d{2})-(\d{2})/)

  if (match) {
    const parsed = new Date(Number(match[1]), Number(match[2]) - 1, Number(match[3]))

    return Number.isNaN(parsed.getTime()) ? null : parsed
  }

  return toDate(value)
}

const keyOf = value => (value === null || value === undefined ? null : String(value))

/** `09:00` → 540 minutos desde medianoche. */
function clockToMinutes(clock) {
  const match = String(clock || '').match(/^(\d{1,2}):(\d{2})$/)
  if (!match) return null

  const hours = Number(match[1])
  const minutes = Number(match[2])
  if (hours > 23 || minutes > 59) return null

  return hours * 60 + minutes
}

const pad = value => String(value).padStart(2, '0')

const minutesToClock = total => `${pad(Math.floor(total / 60))}:${pad(total % 60)}`

/**
 * Cada cita del día deja su intervalo en el profesional y en el sillón que
 * ocupa, sin cruzarlos: son dos recursos independientes. Los pares que la
 * agenda ya usó se recogen de **todo** el día (no solo de la ventana) porque
 * son un atajo para dos decisiones, no una medida de ocupación.
 */
function collectSpans(spans, excludeAppointmentId) {
  const excluded = keyOf(excludeAppointmentId)
  const dayPairs = new Map()
  const userSpans = new Map()
  const chairSpans = new Map()

  for (const span of spans) {
    if (span.status === CANCELLED) continue
    if (excluded !== null && keyOf(span.id) === excluded) continue
    if (!span.from || !span.to) continue

    const userKey = keyOf(span.userId)
    const chairKey = keyOf(span.chairId)

    if (userKey !== null && chairKey !== null) {
      const pairKey = `${userKey}::${chairKey}`
      const current = dayPairs.get(pairKey)

      if (!current || span.from < current.from) {
        dayPairs.set(pairKey, { userKey, chairKey, from: span.from })
      }
    }

    if (userKey !== null) {
      if (!userSpans.has(userKey)) userSpans.set(userKey, [])
      userSpans.get(userKey).push({ from: span.from, to: span.to })
    }
    if (chairKey !== null) {
      if (!chairSpans.has(chairKey)) chairSpans.set(chairKey, [])
      chairSpans.get(chairKey).push({ from: span.from, to: span.to })
    }
  }

  return { dayPairs, userSpans, chairSpans }
}

/** El fin de ocupación más tardío de una lista de intervalos que chocan. */
function latestEnd(intervals, from, to) {
  let latest = null

  for (const interval of intervals) {
    if (!interval.from || !interval.to) continue
    if (!overlaps(interval.from, interval.to, from, to)) continue
    if (!latest || interval.to > latest) latest = interval.to
  }

  return latest
}

const emptyAvailability = () => ({
  professionals: [],
  chairs: [],
  suggestions: [],
  busyUntil: { users: {}, chairs: {} },
  unknown: true
})

/**
 * Profesionales y sillones del alta, cada uno con su propia disponibilidad.
 *
 * @param {object} input
 * @param {Array<object>|null|undefined} input.appointments citas del día (sin leer → `unknown`)
 * @param {Array<object>} [input.professionals] profesionales activos
 * @param {Array<object>} [input.chairs] sillones activos
 * @param {string|Date|null} [input.start] inicio de la ventana de la cita
 * @param {string|Date|null} [input.end] fin de la ventana (exclusivo)
 * @param {number|string|null} [input.excludeAppointmentId] la cita que se edita no se bloquea a sí misma
 * @param {number} [input.suggestionLimit] cuántos atajos se ofrecen
 * @returns {{
 *   professionals: Array<{id: *, name: string, role: string, specialty: string, free: boolean, busyUntil: string|null}>,
 *   chairs: Array<{id: *, name: string, code: string, free: boolean, busyUntil: string|null}>,
 *   suggestions: Array<{user: object, chair: object, free: boolean, busyUntil: string|null}>,
 *   busyUntil: {users: Record<string, string>, chairs: Record<string, string>},
 *   unknown: boolean
 * }}
 */
export function buildAppointmentAvailability({
  appointments,
  professionals = [],
  chairs = [],
  start = null,
  end = null,
  excludeAppointmentId = null,
  suggestionLimit = 3
} = {}) {
  const windowStart = toDate(start)
  const windowEnd = toDate(end)
  const spans = Array.isArray(appointments) ? appointments.map(toSpan).filter(Boolean) : null

  // Sin la agenda leída, o sin una ventana que se pueda comparar, no hay
  // afirmación posible. Esta es la rama que la superficie lee para degradar.
  if (spans === null || !windowStart || !windowEnd || windowEnd <= windowStart) {
    return emptyAvailability()
  }

  const { dayPairs, userSpans, chairSpans } = collectSpans(spans, excludeAppointmentId)
  const busyUntilOf = intervals => {
    let latest = null

    for (const interval of intervals) {
      if (!overlaps(interval.from, interval.to, windowStart, windowEnd)) continue
      if (!latest || interval.to > latest) latest = interval.to
    }

    return latest
  }

  const people = (Array.isArray(professionals) ? professionals : [])
    .filter(person => person && person.id !== null && person.id !== undefined)
    .map(person => {
      const busyUntil = busyUntilOf(userSpans.get(keyOf(person.id)) || [])

      return {
        id: person.id,
        name: person.name || '',
        role: person.role || '',
        specialty: person.specialty || '',
        free: busyUntil === null,
        busyUntil: busyUntil ? formatTime(busyUntil) : null
      }
    })

  const seats = (Array.isArray(chairs) ? chairs : [])
    .filter(chair => chair && chair.id !== null && chair.id !== undefined)
    .map(chair => {
      const busyUntil = busyUntilOf(chairSpans.get(keyOf(chair.id)) || [])

      return {
        id: chair.id,
        name: chair.name || '',
        code: chair.code || '',
        free: busyUntil === null,
        busyUntil: busyUntil ? formatTime(busyUntil) : null
      }
    })

  const peopleById = new Map(people.map(person => [keyOf(person.id), person]))
  const seatsById = new Map(seats.map(chair => [keyOf(chair.id), chair]))

  // Atajos: los pares que la agenda del día ya usó, en orden cronológico. Solo
  // se ofrecen si ambos recursos siguen en el catálogo activo; un profesional
  // dado de baja no aparece aunque tenga historia hoy.
  const suggestions = [...dayPairs.values()]
    .sort((left, right) => left.from - right.from)
    .map(pair => {
      const user = peopleById.get(pair.userKey)
      const chair = seatsById.get(pair.chairKey)
      if (!user || !chair) return null

      const free = user.free && chair.free
      const candidates = [user.busyUntil, chair.busyUntil].filter(Boolean).sort()

      return { user, chair, free, busyUntil: free ? null : candidates[candidates.length - 1] }
    })
    .filter(Boolean)
    .slice(0, Math.max(0, Number(suggestionLimit) || 0))

  return {
    professionals: people,
    chairs: seats,
    suggestions,
    busyUntil: {
      users: Object.fromEntries(
        [...userSpans]
          .map(([id, intervals]) => {
            const at = busyUntilOf(intervals)

            return at ? [id, formatTime(at)] : null
          })
          .filter(Boolean)
      ),
      chairs: Object.fromEntries(
        [...chairSpans]
          .map(([id, intervals]) => {
            const at = busyUntilOf(intervals)

            return at ? [id, formatTime(at)] : null
          })
          .filter(Boolean)
      )
    },
    unknown: false
  }
}

/**
 * Rejilla de horas del alta, agrupada por hora en filas de cuatro casillas de
 * 15 minutos.
 *
 * Una casilla está ocupada cuando la ventana `[hora, hora + duración)` choca
 * con una cita del profesional elegido o del sillón elegido. El motivo es el
 * fin de la ocupación, no una adivinanza: `Ocupado hasta 09:30`.
 *
 * Sin agenda leída (`appointments` no es un arreglo) la rejilla se publica
 * igual —la hora sigue siendo una decisión del usuario— pero ningún horario
 * se marca ocupado: `unknown` avisa a la superficie que no hay dato.
 *
 * @param {object} input
 * @param {Array<object>|null|undefined} input.appointments citas del día
 * @param {number|string|null} [input.userId] profesional elegido
 * @param {number|string|null} [input.chairId] sillón elegido
 * @param {number|string} [input.durationMinutes]
 * @param {string|Date|null} [input.date] día de la cita (las citas del día ya vienen filtradas)
 * @param {string|null} [input.selectedTime] hora elegida; si cae fuera de la ventana nominal, la rejilla se extiende para mostrarla
 * @param {number|string|null} [input.excludeAppointmentId]
 * @param {string} [input.dayStart]
 * @param {string} [input.dayEnd]
 * @param {number} [input.stepMinutes]
 * @returns {{
 *   unknown: boolean,
 *   blocks: Array<{id: string, label: string, rows: Array<{hour: number, slots: Array<object>}>}>,
 *   slots: Array<object>
 * }}
 */
export function buildHourGrid({
  appointments,
  userId = null,
  chairId = null,
  durationMinutes = 30,
  date = null,
  selectedTime = null,
  excludeAppointmentId = null,
  dayStart = DAY_START,
  dayEnd = DAY_END,
  stepMinutes = SLOT_MINUTES,
  now = null
} = {}) {
  const spans = Array.isArray(appointments) ? appointments.map(toSpan).filter(Boolean) : null
  const unknown = spans === null
  const userKey = keyOf(userId)
  const chairKey = keyOf(chairId)
  const duration = Math.max(stepMinutes, Number(durationMinutes) || stepMinutes)

  const from = clockToMinutes(dayStart) ?? clockToMinutes(DAY_START)
  const to = clockToMinutes(dayEnd) ?? clockToMinutes(DAY_END)
  const step = Math.max(5, Number(stepMinutes) || SLOT_MINUTES)
  const day = toLocalDay(date)

  // #531 — una casilla anterior a este instante no es elegible cuando la fecha
  // es hoy: el backend exige un minuto de margen y el alta no puede proponer
  // un valor que la API rechaza. `now` es inyectable para que las pruebas no
  // dependan del reloj real; sin él la rejilla no juzga el pasado.
  const clock = now instanceof Date && !Number.isNaN(now.getTime()) ? now : null
  const today = clock ? new Date(clock.getFullYear(), clock.getMonth(), clock.getDate()) : null
  const isToday = Boolean(day && today && day.getTime() === today.getTime())

  // La ventana nominal es 07:00–20:00, pero una cita a las 06:00 sigue siendo
  // una hora real: si la elegida cae fuera, la rejilla crece lo justo para que
  // su casilla exista y se pueda cambiar sin perder el valor.
  const selectedMinutes = clockToMinutes(selectedTime)
  const windowFrom = selectedMinutes === null ? from : Math.min(from, selectedMinutes)
  const windowTo =
    selectedMinutes === null ? to : Math.max(to, Math.min(selectedMinutes + step, 24 * 60))

  let userSpans = []
  let chairSpans = []

  if (!unknown) {
    const collected = collectSpans(spans, excludeAppointmentId)
    if (userKey !== null) userSpans = collected.userSpans.get(userKey) || []
    if (chairKey !== null) chairSpans = collected.chairSpans.get(chairKey) || []
  }

  const slots = []
  const blocks = HOUR_BLOCKS.map(block => ({ id: block.id, label: block.label, rows: [] }))
  const boundaries = HOUR_BLOCKS.map(block =>
    block.until === null ? Number.POSITIVE_INFINITY : (clockToMinutes(block.until) ?? Infinity)
  )

  const blockOfMinute = minute => {
    const index = boundaries.findIndex(boundary => minute < boundary)

    return HOUR_BLOCKS[index === -1 ? HOUR_BLOCKS.length - 1 : index].id
  }

  for (let minute = windowFrom; minute < windowTo; minute += step) {
    const slotStart = day
      ? new Date(day.getFullYear(), day.getMonth(), day.getDate(), 0, minute, 0, 0)
      : null
    const slotEnd = slotStart ? new Date(slotStart.getTime() + duration * 60000) : null

    const userBusy = slotStart ? latestEnd(userSpans, slotStart, slotEnd) : null
    const chairBusy = slotStart ? latestEnd(chairSpans, slotStart, slotEnd) : null
    const busy = [userBusy, chairBusy].filter(Boolean).sort()
    const past = Boolean(isToday && clock && slotStart && slotStart.getTime() <= clock.getTime())

    const slot = {
      value: minutesToClock(minute),
      label: minutesToClock(minute),
      hour: Math.floor(minute / 60),
      minute: minute % 60,
      block: blockOfMinute(minute),
      past,
      disabled: past || busy.length > 0,
      busyUntil: busy.length > 0 ? formatTime(busy[busy.length - 1]) : null
    }

    slot.reason = past ? 'Ya pasó' : slot.disabled ? `Ocupado hasta ${slot.busyUntil}` : ''
    slot.ariaLabel = slot.disabled ? `${slot.label}, ${slot.reason.toLowerCase()}` : slot.label
    slot.index = slots.length
    slots.push(slot)
  }

  for (const slot of slots) {
    const block = blocks.find(candidate => candidate.id === slot.block)
    if (!block) continue

    let row = block.rows[block.rows.length - 1]
    if (!row || row.hour !== slot.hour) {
      row = { hour: slot.hour, slots: [] }
      block.rows.push(row)
    }
    row.slots.push(slot)
  }

  return {
    unknown,
    blocks: blocks.filter(block => block.rows.length > 0),
    slots
  }
}

/**
 * Los tipos de cita agrupados por especialidad.
 *
 * `preferred` son los del profesional elegido (vacío si no tiene especialidad
 * o si el catálogo no tiene tipos suyos); `groups` es el catálogo completo
 * agrupado, con los tipos sin especialidad en su propio grupo, que se lee al
 * final porque es el resto y no una especialidad.
 *
 * @param {Array<object>} types filas de `GET /api/appointment-types/active`
 * @param {object|null} [professional] el profesional elegido
 * @returns {{
 *   preferred: Array<object>,
 *   groups: Array<{key: string, label: string, types: Array<object>}>,
 *   specialtyLabel: string,
 *   hasSpecialties: boolean
 * }}
 */
export function groupAppointmentTypes(types = [], professional = null) {
  const catalog = (Array.isArray(types) ? types : []).filter(
    type => type && type.id !== null && type.id !== undefined
  )
  const personKey = specialtyKeyOf(professional?.specialty || professional?.specialty_code)

  const rows = catalog.map(type => ({
    type,
    key: specialtyKeyOf(type.specialty),
    label: type.specialty?.name || type.specialty?.code || NO_SPECIALTY
  }))

  const preferred = personKey ? rows.filter(row => row.key === personKey) : []
  const groups = new Map()

  for (const row of rows) {
    const label = row.key ? row.label : NO_SPECIALTY
    if (!groups.has(label)) groups.set(label, [])
    groups.get(label).push(row.type)
  }

  const ordered = [...groups.entries()]
    .map(([label, entries]) => ({ key: label, label, types: entries }))
    .sort((left, right) => {
      if (left.label === NO_SPECIALTY) return 1
      if (right.label === NO_SPECIALTY) return -1
      return left.label.localeCompare(right.label, 'es')
    })

  return {
    preferred: preferred.map(row => row.type),
    groups: ordered,
    specialtyLabel: preferred[0]?.label || '',
    hasSpecialties: ordered.filter(group => group.label !== NO_SPECIALTY).length > 1
  }
}

/**
 * Los tipos de cita que se ofrecen como atajo: los más usados del día y, a
 * igualdad de uso, el orden del catálogo. Sin agenda leída, el catálogo tal
 * como llega.
 *
 * @param {Array<object>} types
 * @param {Array<object>|null|undefined} appointments
 * @param {number} [limit]
 * @returns {Array<object>}
 */
export function rankAppointmentTypes(types = [], appointments = null, limit = 4) {
  const catalog = Array.isArray(types)
    ? types.filter(type => type && type.id !== null && type.id !== undefined)
    : []

  if (!Array.isArray(appointments) || appointments.length === 0) {
    return catalog.slice(0, limit)
  }

  const usage = new Map()

  for (const appointment of appointments) {
    if (!appointment || appointment.status === CANCELLED) continue

    const id = keyOf(appointment.appointment_type_id ?? appointment.appointment_type?.id)
    if (id === null) continue

    usage.set(id, (usage.get(id) || 0) + 1)
  }

  const catalogOrder = new Map(catalog.map((type, index) => [keyOf(type.id), index]))

  return [...catalog]
    .sort((left, right) => {
      const byUsage = (usage.get(keyOf(right.id)) || 0) - (usage.get(keyOf(left.id)) || 0)

      return byUsage !== 0
        ? byUsage
        : catalogOrder.get(keyOf(left.id)) - catalogOrder.get(keyOf(right.id))
    })
    .slice(0, limit)
}

/**
 * Vue wrapper: reads the day once per date and publishes the read state.
 * `status === 'ready'` is the ONLY state that authorizes a «Libre» label.
 */
export function useAppointmentAvailability() {
  const { get } = useApi()

  const appointments = ref(null)
  const status = ref('idle')

  let lastRequest = 0

  const load = async date => {
    if (!date) {
      appointments.value = null
      status.value = 'unknown'
      return
    }

    const request = ++lastRequest

    // Una lectura nueva invalida la anterior: una etiqueta de ayer no puede
    // sobrevivir a un cambio de fecha.
    appointments.value = null
    status.value = 'loading'

    try {
      const response = await get('/api/appointments', {
        params: { start_date: date, end_date: date, per_page: 500 }
      })

      if (request !== lastRequest) return

      appointments.value = Array.isArray(response?.data) ? response.data : []
      status.value = 'ready'
    } catch {
      if (request !== lastRequest) return

      appointments.value = null
      status.value = 'unknown'
    }
  }

  return { appointments, status, load }
}

export default useAppointmentAvailability
