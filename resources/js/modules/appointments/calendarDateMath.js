// Design decision D3: pure date math for the calendar views, extracted 1:1
// from CalendarPage.vue. No Vue, no mutation of caller inputs. Weeks are
// Monday-first and always CONTAIN the reference date: a Sunday reference ends
// its own week (D11 fix — it used to roll to the following week and the month
// grid lost the 1st when it fell on a Sunday). Every helper below shares this
// single start-of-week computation, so weekDays/monthDays/periodTitle/
// dateRangeForView stay consistent through their shared source.

import { formatDate } from '../../composables/useFormatters'

export const WEEK_DAY_NAMES = ['Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb', 'Dom']

// Issue 397 (S7): a 390 px la rejilla semanal comprimía cada día a 38 px y el chip a
// «1.». La vista con la que abre la agenda depende del ancho del viewport: por
// debajo del punto de quiebre de escritorio abre en día, que es la vista donde
// hora y paciente se leen. El selector de vista sigue ofreciendo «Semana» a
// mano, y ahí la rejilla vive en su propio scroll horizontal.
export const MOBILE_VIEW_BREAKPOINT_PX = 768
export const MOBILE_DEFAULT_VIEW = 'day'
export const DESKTOP_DEFAULT_VIEW = 'week'

// A viewport that cannot be measured (SSR, a width of 0) keeps the desktop
// behaviour: the breakpoint decides for measurable widths only.
export function initialViewForWidth(width, breakpoint = MOBILE_VIEW_BREAKPOINT_PX) {
  const measured = Number(width)
  if (!Number.isFinite(measured) || measured <= 0) return DESKTOP_DEFAULT_VIEW
  return measured < breakpoint ? MOBILE_DEFAULT_VIEW : DESKTOP_DEFAULT_VIEW
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
