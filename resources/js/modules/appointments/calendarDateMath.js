// Design decision D3: pure date math for the calendar views, extracted 1:1
// from CalendarPage.vue. No Vue, no mutation of caller inputs. Weeks are
// Monday-first and always CONTAIN the reference date: a Sunday reference ends
// its own week (D11 fix — it used to roll to the following week and the month
// grid lost the 1st when it fell on a Sunday). Every helper below shares this
// single start-of-week computation, so weekDays/monthDays/periodTitle/
// dateRangeForView stay consistent through their shared source.

import { formatDate } from '../../composables/useFormatters'

export const WEEK_DAY_NAMES = ['Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb', 'Dom']

// T6: la agenda abre en Día —también en escritorio— porque es la vista donde
// hora, paciente y ambiente se leen de un vistazo; Semana y Mes se eligen a
// mano desde el mismo segmentado. La vista elegida se recuerda en
// `localStorage` y esta resolución es la única puerta: cualquier valor que no
// sea una vista real (vacío, manipulado, de una versión anterior) cae al valor
// por omisión.
export const CALENDAR_VIEWS = ['day', 'week', 'month']
export const DEFAULT_CALENDAR_VIEW = 'day'
export const CALENDAR_VIEW_STORAGE_KEY = 'calendar-view'

export function isCalendarView(value) {
  return CALENDAR_VIEWS.includes(value)
}

export function initialCalendarView(stored) {
  return isCalendarView(stored) ? stored : DEFAULT_CALENDAR_VIEW
}

export function buildHours(startHour = 6, endHour = 21) {
  const hours = []
  for (let i = startHour; i <= endHour; i++) {
    hours.push(i)
  }
  return hours
}

export function toLocalDateString(date) {
  const d = date instanceof Date ? date : new Date(date)
  const year = d.getFullYear()
  const month = String(d.getMonth() + 1).padStart(2, '0')
  const day = String(d.getDate()).padStart(2, '0')
  return `${year}-${month}-${day}`
}

// La ventana de la rejilla del alta (07:00–20:00, pasos de 15). Se repiten aquí
// para que el alta pueda proponer una hora válida sin importar el composable
// de disponibilidad desde el módulo de fechas.
const FIRST_APPOINTMENT_MINUTES = 7 * 60
const LAST_APPOINTMENT_MINUTES = 19 * 60 + 45

const clockLabel = minutes => {
  const hours = String(Math.floor(minutes / 60)).padStart(2, '0')
  const rest = String(minutes % 60).padStart(2, '0')
  return `${hours}:${rest}`
}

/**
 * Propone un inicio válido para el alta de cita.
 *
 * Issue 531 pintaba la fecha de la vista a las 09:00, así que abrir «Nueva cita» un
 * día a las 10:30 proponía una hora que el backend rechaza por pasada. En el
 * día de hoy propone el siguiente cuarto de hora (la API exige al menos un
 * minuto de margen) y, si ya no queda ventana, el día siguiente a las 07:00.
 * Un día futuro conserva las 09:00; una vista en el pasado propone hoy.
 *
 * @param {Date|string|null} viewDate día que se está mirando
 * @param {Date} now reloj de referencia (inyectable para las pruebas)
 * @returns {string} valor `YYYY-MM-DDTHH:mm` que entiende el formulario
 */
export function nextAppointmentSlot(viewDate = null, now = new Date()) {
  const clock = now instanceof Date && !Number.isNaN(now.getTime()) ? now : new Date()
  const today = new Date(clock.getFullYear(), clock.getMonth(), clock.getDate())

  let target = today
  if (viewDate) {
    const parsed = viewDate instanceof Date ? new Date(viewDate) : new Date(`${viewDate}T00:00:00`)
    if (!Number.isNaN(parsed.getTime())) {
      target = new Date(parsed.getFullYear(), parsed.getMonth(), parsed.getDate())
    }
  }

  if (target.getTime() < today.getTime()) {
    target = today
  }

  if (target.getTime() === today.getTime()) {
    const nowMinutes = clock.getHours() * 60 + clock.getMinutes()
    const next = Math.max(FIRST_APPOINTMENT_MINUTES, Math.ceil((nowMinutes + 1) / 15) * 15)

    if (next <= LAST_APPOINTMENT_MINUTES) {
      return `${toLocalDateString(target)}T${clockLabel(next)}`
    }

    const tomorrow = addDays(target, 1)
    return `${toLocalDateString(tomorrow)}T${clockLabel(FIRST_APPOINTMENT_MINUTES)}`
  }

  return `${toLocalDateString(target)}T09:00`
}

const addDays = (date, amount) => {
  const result = new Date(date)
  result.setDate(date.getDate() + amount)
  return result
}

const startOfWeek = date => {
  const start = new Date(date)
  start.setDate(date.getDate() - ((date.getDay() + 6) % 7))
  return start
}

export function weekDays(date) {
  const start = startOfWeek(date)
  return WEEK_DAY_NAMES.map((name, i) => {
    const day = addDays(start, i)
    return {
      date: toLocalDateString(day),
      name,
      number: day.getDate()
    }
  })
}

export function monthDays(date, today = new Date()) {
  const year = date.getFullYear()
  const month = date.getMonth()
  const firstDay = new Date(year, month, 1)
  const startDate = startOfWeek(firstDay)
  const todayStr = toLocalDateString(today)

  const cells = []
  for (let i = 0; i < 42; i++) {
    const day = addDays(startDate, i)
    const dayStr = toLocalDateString(day)
    cells.push({
      date: dayStr,
      number: day.getDate(),
      isCurrentMonth: day.getMonth() === month,
      isToday: dayStr === todayStr
    })
  }
  return cells
}

// Titles render through the canonical format module (issue 451): one locale and one
// field set. `undefined` drops a field the module's defaults would otherwise
// keep (the week start carries no year, the month title carries no day).
export function periodTitle(date, view) {
  switch (view) {
    case 'day':
      return formatDate(date, {
        weekday: 'long',
        year: 'numeric',
        month: 'long',
        day: 'numeric'
      })
    case 'week': {
      const start = startOfWeek(date)
      const end = addDays(start, 6)
      const startTitle = formatDate(start, {
        day: 'numeric',
        month: 'short',
        year: undefined
      })
      const endTitle = formatDate(end, { day: 'numeric', month: 'short', year: 'numeric' })
      return `${startTitle} - ${endTitle}`
    }
    case 'month':
      return formatDate(date, { year: 'numeric', month: 'long', day: undefined })
    default:
      return ''
  }
}

// Day board header: the visible day is the page title and reads as a sentence
// ("Miércoles 18 de noviembre"), so the year is dropped and the locale's
// comma is written as a space. Both changes are copy, not formatting: the
// date still comes from the canonical module.
export function dayTitle(date) {
  const label = formatDate(date, {
    weekday: 'long',
    year: undefined,
    month: 'long',
    day: 'numeric'
  })
  if (!label) return ''
  const capitalized = label.charAt(0).toUpperCase() + label.slice(1)
  return capitalized.replace(/,\s*/, ' ')
}

export function dateRangeForView(date, view) {
  if (view === 'day') {
    return {
      start_date: toLocalDateString(date),
      end_date: toLocalDateString(date)
    }
  }
  if (view === 'week') {
    const start = startOfWeek(date)
    const end = addDays(start, 6)
    return {
      start_date: toLocalDateString(start),
      end_date: toLocalDateString(end)
    }
  }
  const firstDay = new Date(date.getFullYear(), date.getMonth(), 1)
  const startDate = startOfWeek(firstDay)
  const lastDay = addDays(startDate, 41)
  return {
    start_date: toLocalDateString(startDate),
    end_date: toLocalDateString(lastDay)
  }
}

// D8/T6 month-cell overflow split: how many compact chips a day cell shows
// before the "N más" drill-down control. Pure and presentation-free (the
// template owns how the two fields render).
export const MONTH_CELL_CHIP_LIMIT = 3

export function splitMonthCellAppointments(list, max = MONTH_CELL_CHIP_LIMIT) {
  const safeMax = Math.max(0, Math.floor(max))
  return {
    visible: list.slice(0, safeMax),
    overflowCount: Math.max(0, list.length - safeMax)
  }
}

export function addPeriod(date, view, delta = 1) {
  const result = new Date(date)
  switch (view) {
    case 'day':
      result.setDate(date.getDate() + delta)
      break
    case 'week':
      result.setDate(date.getDate() + 7 * delta)
      break
    case 'month': {
      // D12: month steps clamp to the last day of the target month instead of
      // overflowing through setMonth (31 ene +1 -> 28/29 feb, not 3 mar).
      // Target month/year are resolved on day 1 first so the reference day
      // never sees a 29..31-day overflow while the month is being shifted.
      const targetMonthStart = new Date(date.getFullYear(), date.getMonth() + delta, 1)
      const lastDayOfTarget = new Date(
        targetMonthStart.getFullYear(),
        targetMonthStart.getMonth() + 1,
        0
      ).getDate()
      result.setFullYear(
        targetMonthStart.getFullYear(),
        targetMonthStart.getMonth(),
        Math.min(date.getDate(), lastDayOfTarget)
      )
      break
    }
  }
  return result
}
