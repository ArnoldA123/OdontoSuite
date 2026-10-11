import { describe, it, expect } from 'vitest'
import {
  WEEK_DAY_NAMES,
  buildHours,
  toLocalDateString,
  weekDays,
  monthDays,
  periodTitle,
  dateRangeForView,
  addPeriod
} from '../../../resources/js/modules/appointments/calendarDateMath.js'

// All dates below are built from explicit local components so assertions never
// depend on the host timezone.
const d = (year, month, day) => new Date(year, month - 1, day)

describe('buildHours', () => {
  it('returns the 6..21 range used by the day and week grids', () => {
    const hours = buildHours()
    expect(hours).toHaveLength(16)
    expect(hours[0]).toBe(6)
    expect(hours[hours.length - 1]).toBe(21)
    expect(hours).toEqual([...Array(16)].map((_, i) => i + 6))
  })

  it('honors explicit inclusive bounds', () => {
    expect(buildHours(8, 10)).toEqual([8, 9, 10])
    expect(buildHours(21, 21)).toEqual([21])
    expect(buildHours(10, 8)).toEqual([])
  })
})

describe('toLocalDateString', () => {
  it('formats local calendar components with zero padding', () => {
    expect(toLocalDateString(d(2026, 2, 1))).toBe('2026-02-01')
    expect(toLocalDateString(d(2026, 1, 5))).toBe('2026-01-05')
    expect(toLocalDateString(d(2025, 12, 29))).toBe('2025-12-29')
  })
})

describe('weekDays', () => {
  it('returns 7 days starting Monday for a mid-week reference', () => {
    const days = weekDays(d(2026, 3, 11))
    expect(days).toHaveLength(7)
    expect(days.map(day => day.date)).toEqual([
      '2026-03-09',
      '2026-03-10',
      '2026-03-11',
      '2026-03-12',
      '2026-03-13',
      '2026-03-14',
      '2026-03-15'
    ])
    expect(days.map(day => day.name)).toEqual(WEEK_DAY_NAMES)
    expect(days.map(day => day.number)).toEqual([9, 10, 11, 12, 13, 14, 15])
    expect(days[0].name).toBe('Lun')
  })

  it('starts on the same day as the reference when it is a Monday', () => {
    const days = weekDays(d(2026, 3, 9))
    expect(days[0].date).toBe('2026-03-09')
    expect(days[6].date).toBe('2026-03-15')
  })

  it('resolves to the containing Monday for a Saturday reference', () => {
    const days = weekDays(d(2026, 3, 14))
    expect(days[0].date).toBe('2026-03-09')
    expect(days[6].date).toBe('2026-03-15')
  })

  it('yields the week CONTAINING a Sunday reference (Mon..Sun ending on it)', () => {
    const days = weekDays(d(2026, 3, 8))
    expect(days[0].date).toBe('2026-03-02')
    expect(days[6].date).toBe('2026-03-08')
    expect(days.map(day => day.date)).toEqual([
      '2026-03-02',
      '2026-03-03',
      '2026-03-04',
      '2026-03-05',
      '2026-03-06',
      '2026-03-07',
      '2026-03-08'
    ])
  })

  it('yields the containing week for every Sunday reference (D11 regression guard)', () => {
    const sunday = new Date(2026, 0, 4) // 2026-01-04 is a Sunday
    while (sunday.getFullYear() === 2026) {
      const reference = new Date(sunday)
      const days = weekDays(reference)
      const expectedMonday = new Date(sunday)
      expectedMonday.setDate(sunday.getDate() - 6)
      expect(days).toHaveLength(7)
      expect(days[0].date, toLocalDateString(reference)).toBe(toLocalDateString(expectedMonday))
      expect(days[6].date, toLocalDateString(reference)).toBe(toLocalDateString(sunday))
      sunday.setDate(sunday.getDate() + 7)
    }
  })

  it('spans year boundaries correctly', () => {
    const days = weekDays(d(2026, 1, 1))
    expect(days[0].date).toBe('2025-12-29')
    expect(days[6].date).toBe('2026-01-04')
    expect(days.map(day => day.number)).toEqual([29, 30, 31, 1, 2, 3, 4])
  })

  it('does not mutate the input date', () => {
    const input = d(2026, 3, 11)
    const before = input.getTime()
    weekDays(input)
    expect(input.getTime()).toBe(before)
  })
})

describe('monthDays', () => {
  it('pads a 42-cell grid for a month whose 1st is a Wednesday', () => {
    const cells = monthDays(d(2026, 7, 15), d(2026, 7, 15))
    expect(cells).toHaveLength(42)
    expect(cells[0].date).toBe('2026-06-29')
    expect(cells[41].date).toBe('2026-08-09')
    expect(cells.filter(cell => cell.isCurrentMonth)).toHaveLength(31)
    expect(cells.slice(0, 2).map(cell => cell.date)).toEqual(['2026-06-29', '2026-06-30'])
    expect(cells.slice(33).map(cell => cell.date)).toEqual([
      '2026-08-01',
      '2026-08-02',
      '2026-08-03',
      '2026-08-04',
      '2026-08-05',
      '2026-08-06',
      '2026-08-07',
      '2026-08-08',
      '2026-08-09'
    ])
  })

  it('starts on the 1st when it falls on a Monday (2026-06-01)', () => {
    const cells = monthDays(d(2026, 6, 15), d(2026, 6, 15))
    expect(cells[0]).toEqual({
      date: '2026-06-01',
      number: 1,
      isCurrentMonth: true,
      isToday: false
    })
    expect(cells[41].date).toBe('2026-07-12')
    expect(cells.filter(cell => cell.isCurrentMonth)).toHaveLength(30)
  })

  it('never drops the 1st when it falls on a Sunday (2026-02-01)', () => {
    const cells = monthDays(d(2026, 2, 1), d(2026, 2, 1))
    expect(cells).toHaveLength(42)
    expect(cells[0].date).toBe('2026-01-26')
    expect(cells[41].date).toBe('2026-03-08')
    expect(cells.filter(cell => cell.isCurrentMonth)).toHaveLength(28)
    expect(cells.some(cell => cell.date === '2026-02-01')).toBe(true)
  })

  it('never drops the 1st when it falls on a Sunday (2026-11-01)', () => {
    const cells = monthDays(d(2026, 11, 1), d(2026, 11, 1))
    expect(cells).toHaveLength(42)
    expect(cells[0].date).toBe('2026-10-26')
    expect(cells[41].date).toBe('2026-12-06')
    expect(cells.filter(cell => cell.isCurrentMonth)).toHaveLength(30)
    expect(cells.some(cell => cell.date === '2026-11-01')).toBe(true)
  })

  it('always contains every day of the month (D11 regression guard)', () => {
    for (let year = 2024; year <= 2028; year++) {
      for (let month = 1; month <= 12; month++) {
        const anchor = new Date(year, month - 1, 15)
        const cells = monthDays(anchor, anchor)
        const dates = new Set(cells.map(cell => cell.date))
        const daysInMonth = new Date(year, month, 0).getDate()
        expect(cells).toHaveLength(42)
        for (let day = 1; day <= daysInMonth; day++) {
          const key = toLocalDateString(new Date(year, month - 1, day))
          expect(dates.has(key), `${year}-${month}-${day}`).toBe(true)
        }
      }
    }
  })

  it('marks exactly one cell as today against the provided anchor', () => {
    const cells = monthDays(d(2026, 6, 15), d(2026, 6, 15))
    const todayCells = cells.filter(cell => cell.isToday)
    expect(todayCells).toHaveLength(1)
    expect(todayCells[0].date).toBe('2026-06-15')
  })

  it('marks no cell as today when the anchor is outside the padded grid (2026-08-15 viewing July)', () => {
    const cells = monthDays(d(2026, 7, 15), d(2026, 8, 15))
    expect(cells.filter(cell => cell.isToday)).toHaveLength(0)
  })

  it('marks the Sunday 1st as today instead of dropping it (2026-02-01)', () => {
    const cells = monthDays(d(2026, 2, 1), d(2026, 2, 1))
    const todayCells = cells.filter(cell => cell.isToday)
    expect(todayCells).toHaveLength(1)
    expect(todayCells[0].date).toBe('2026-02-01')
  })

  it('marks padding cells as today when the anchor falls in them (2026-06-29 viewing July)', () => {
    const cells = monthDays(d(2026, 7, 15), d(2026, 6, 29))
    const todayCells = cells.filter(cell => cell.isToday)
    expect(todayCells).toHaveLength(1)
    expect(todayCells[0]).toEqual({
      date: '2026-06-29',
      number: 29,
      isCurrentMonth: false,
      isToday: true
    })
  })

  it('does not mutate the input dates', () => {
    const input = d(2026, 7, 15)
    const today = d(2026, 7, 4)
    const before = input.getTime()
    const todayBefore = today.getTime()
    monthDays(input, today)
    expect(input.getTime()).toBe(before)
    expect(today.getTime()).toBe(todayBefore)
  })
})

describe('periodTitle', () => {
  it('produces the exact day titles the component shows today', () => {
    expect(periodTitle(d(2026, 2, 1), 'day')).toBe('domingo, 1 de febrero de 2026')
    expect(periodTitle(d(2026, 7, 15), 'day')).toBe('miércoles, 15 de julio de 2026')
  })

  it('produces the exact week titles the component shows today', () => {
    expect(periodTitle(d(2026, 3, 11), 'week')).toBe('9 mar - 15 mar 2026')
    expect(periodTitle(d(2026, 3, 9), 'week')).toBe('9 mar - 15 mar 2026')
    // D11 fix: a Sunday reference titles the week that CONTAINS it.
    expect(periodTitle(d(2026, 2, 1), 'week')).toBe('26 ene - 1 feb 2026')
    // Cross-year week.
    expect(periodTitle(d(2026, 1, 1), 'week')).toBe('29 dic - 4 ene 2026')
  })

  it('produces the exact month titles the component shows today', () => {
    expect(periodTitle(d(2026, 2, 1), 'month')).toBe('febrero de 2026')
    expect(periodTitle(d(2026, 11, 1), 'month')).toBe('noviembre de 2026')
    expect(periodTitle(d(2025, 12, 29), 'month')).toBe('diciembre de 2025')
  })

  it('returns an empty string for unknown views', () => {
    expect(periodTitle(d(2026, 2, 1), 'agenda')).toBe('')
    expect(periodTitle(d(2026, 2, 1), undefined)).toBe('')
  })
})

describe('addPeriod', () => {
  it('shifts days across year boundaries', () => {
    expect(toLocalDateString(addPeriod(d(2025, 12, 31), 'day', 1))).toBe('2026-01-01')
    expect(toLocalDateString(addPeriod(d(2026, 1, 1), 'day', -1))).toBe('2025-12-31')
    expect(toLocalDateString(addPeriod(d(2026, 2, 27), 'day', 3))).toBe('2026-03-02')
  })

  it('shifts weeks across year boundaries', () => {
    expect(toLocalDateString(addPeriod(d(2025, 12, 29), 'week', 1))).toBe('2026-01-05')
    expect(toLocalDateString(addPeriod(d(2026, 1, 1), 'week', -1))).toBe('2025-12-25')
    expect(toLocalDateString(addPeriod(d(2026, 2, 25), 'week', 1))).toBe('2026-03-04')
  })

  it('shifts months across year boundaries', () => {
    expect(toLocalDateString(addPeriod(d(2025, 12, 15), 'month', 1))).toBe('2026-01-15')
    expect(toLocalDateString(addPeriod(d(2026, 1, 15), 'month', -1))).toBe('2025-12-15')
    expect(toLocalDateString(addPeriod(d(2026, 1, 15), 'month', 3))).toBe('2026-04-15')
  })

  it('keeps setMonth overflow semantics (2026-01-31 + 1 month)', () => {
    expect(toLocalDateString(addPeriod(d(2026, 1, 31), 'month', 1))).toBe('2026-03-03')
  })

  it('defaults to a single step', () => {
    expect(toLocalDateString(addPeriod(d(2026, 7, 15), 'day'))).toBe('2026-07-16')
    expect(toLocalDateString(addPeriod(d(2026, 7, 15), 'week'))).toBe('2026-07-22')
    expect(toLocalDateString(addPeriod(d(2026, 7, 15), 'month'))).toBe('2026-08-15')
  })

  it('returns an unchanged copy for unknown views', () => {
    const input = d(2026, 7, 15)
    const result = addPeriod(input, 'agenda', 1)
    expect(result).not.toBe(input)
    expect(result.getTime()).toBe(input.getTime())
  })

  it('returns an unchanged copy for a zero delta', () => {
    for (const view of ['day', 'week', 'month']) {
      const result = addPeriod(d(2026, 1, 31), view, 0)
      expect(toLocalDateString(result), view).toBe('2026-01-31')
    }
  })

  it('does not mutate the input date', () => {
    for (const view of ['day', 'week', 'month']) {
      const input = d(2026, 1, 31)
      const before = input.getTime()
      addPeriod(input, view, 1)
      expect(input.getTime(), view).toBe(before)
    }
  })
})

describe('dateRangeForView', () => {
  it('returns a single-day range for the day view', () => {
    expect(dateRangeForView(d(2026, 2, 1), 'day')).toEqual({
      start_date: '2026-02-01',
      end_date: '2026-02-01'
    })
    // Day view is not affected by the week-start computation.
    expect(dateRangeForView(d(2026, 3, 8), 'day')).toEqual({
      start_date: '2026-03-08',
      end_date: '2026-03-08'
    })
  })

  it('returns Monday..Sunday ranges for the week view', () => {
    expect(dateRangeForView(d(2026, 3, 11), 'week')).toEqual({
      start_date: '2026-03-09',
      end_date: '2026-03-15'
    })
    // D11 fix: a Sunday reference belongs to the week ending on it.
    expect(dateRangeForView(d(2026, 3, 8), 'week')).toEqual({
      start_date: '2026-03-02',
      end_date: '2026-03-08'
    })
    expect(dateRangeForView(d(2026, 1, 1), 'week')).toEqual({
      start_date: '2025-12-29',
      end_date: '2026-01-04'
    })
  })

  it('returns the 42-day padded grid range for the month view', () => {
    // D11 fix: the padded range always covers the whole month, even when
    // the 1st is a Sunday.
    expect(dateRangeForView(d(2026, 2, 1), 'month')).toEqual({
      start_date: '2026-01-26',
      end_date: '2026-03-08'
    })
    expect(dateRangeForView(d(2026, 7, 15), 'month')).toEqual({
      start_date: '2026-06-29',
      end_date: '2026-08-09'
    })
    expect(dateRangeForView(d(2026, 11, 1), 'month')).toEqual({
      start_date: '2026-10-26',
      end_date: '2026-12-06'
    })
  })

  it('falls back to the month range for unknown views', () => {
    expect(dateRangeForView(d(2026, 7, 15), 'agenda')).toEqual({
      start_date: '2026-06-29',
      end_date: '2026-08-09'
    })
    expect(dateRangeForView(d(2026, 7, 15), undefined)).toEqual({
      start_date: '2026-06-29',
      end_date: '2026-08-09'
    })
  })

  it('does not mutate the input date', () => {
    const input = d(2026, 2, 1)
    const before = input.getTime()
    dateRangeForView(input, 'week')
    dateRangeForView(input, 'month')
    expect(input.getTime()).toBe(before)
  })
})
