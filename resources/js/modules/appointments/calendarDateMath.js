// Design decision D3: pure date math for the calendar views, extracted 1:1
// from CalendarPage.vue. No Vue, no mutation of caller inputs. Week/month
// grids keep the original convention exactly: a grid or week starts on the
// Monday given by `date - getDay() + 1`, which for a Sunday reference rolls
// to the FOLLOWING Monday.

export const WEEK_DAY_NAMES = ['Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb', 'Dom']

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
  start.setDate(date.getDate() - date.getDay() + 1)
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

export function periodTitle(date, view) {
  switch (view) {
    case 'day':
      return date.toLocaleDateString('es-ES', {
        weekday: 'long',
        year: 'numeric',
        month: 'long',
        day: 'numeric'
      })
    case 'week': {
      const start = startOfWeek(date)
      const end = addDays(start, 6)
      return `${start.toLocaleDateString('es-ES', { day: 'numeric', month: 'short' })} - ${end.toLocaleDateString('es-ES', { day: 'numeric', month: 'short', year: 'numeric' })}`
    }
    case 'month':
      return date.toLocaleDateString('es-ES', { year: 'numeric', month: 'long' })
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

export function addPeriod(date, view, delta = 1) {
  const result = new Date(date)
  switch (view) {
    case 'day':
      result.setDate(date.getDate() + delta)
      break
    case 'week':
      result.setDate(date.getDate() + 7 * delta)
      break
    case 'month':
      result.setMonth(date.getMonth() + delta)
      break
  }
  return result
}
