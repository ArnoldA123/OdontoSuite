import { describe, it, expect } from 'vitest'
import {
  BOARD_DEFAULT_END_HOUR,
  BOARD_DEFAULT_START_HOUR,
  BOARD_COLUMN_MIN_PX,
  BOARD_HOUR_PX,
  MAX_BOOKABLE_GAP_MINUTES,
  MIN_FREE_SLOT_MINUTES,
  appointmentDurationMinutes,
  appointmentSizeTier,
  boardBlockHeight,
  boardHours,
  boardOffset,
  boardWindow,
  formatDurationMinutes,
  freeGaps,
  gapIsBookable,
  groupByChair,
  groupByProfessional,
  layoutOverlapLanes,
  minutesFromMidnight,
  resourceOccupancy,
  resourceState
} from '../../../resources/js/modules/appointments/calendarBoardMath.js'

// T4 — the Day board is a resource grid: one column per ambiente (or per
// professional) and one row per hour. Its geometry is arithmetic, so it is
// asserted here without a DOM: window, block position, bookable holes,
// occupancy and the state a column is in right now.

const at = (hour, minute = 0) => new Date(2026, 4, 13, hour, minute, 0)

const appointment = (overrides = {}) => ({
  id: 1,
  scheduled_at: '2026-05-13T09:00:00',
  ends_at: '2026-05-13T09:30:00',
  duration_minutes: 30,
  status: 'scheduled',
  user_id: 2,
  user: { id: 2, name: 'Dra. Ruiz' },
  dental_chair: { id: 4, name: 'Ambiente 1', code: 'AMB-001' },
  patient: { id: 7, first_name: 'Ana', last_name: 'Pérez' },
  ...overrides
})

describe('minutesFromMidnight', () => {
  it('reads local components and refuses unreadable values', () => {
    expect(minutesFromMidnight(new Date(2026, 4, 13, 9, 30))).toBe(570)
    expect(minutesFromMidnight('2026-05-13T09:30:00')).toBe(570)
    for (const value of [null, undefined, '', 'no-es-fecha']) {
      expect(minutesFromMidnight(value), String(value)).toBeNull()
    }
  })
})

describe('appointmentDurationMinutes', () => {
  it('prefers the published duration column', () => {
    expect(appointmentDurationMinutes(appointment())).toBe(30)
  })

  it('falls back to the scheduled range when the column is absent', () => {
    expect(
      appointmentDurationMinutes({
        scheduled_at: '2026-05-13T09:00:00',
        ends_at: '2026-05-13T10:15:00'
      })
    ).toBe(75)
  })

  it('never returns a negative or NaN length', () => {
    expect(appointmentDurationMinutes({})).toBe(0)
    expect(appointmentDurationMinutes({ duration_minutes: -20 })).toBe(0)
  })
})

describe('boardWindow', () => {
  it('opens the nominal business window when the day has no blocks', () => {
    const window = boardWindow([])
    expect(window.startHour).toBe(BOARD_DEFAULT_START_HOUR)
    expect(window.endHour).toBe(BOARD_DEFAULT_END_HOUR)
    expect(window.height).toBe((BOARD_DEFAULT_END_HOUR - BOARD_DEFAULT_START_HOUR) * BOARD_HOUR_PX)
  })

  it('widens to hold a block that falls outside the nominal window', () => {
    const window = boardWindow([
      appointment({ scheduled_at: '2026-05-13T06:30:00', ends_at: '2026-05-13T07:00:00' }),
      appointment({
        scheduled_at: '2026-05-13T20:30:00',
        ends_at: '2026-05-13T21:30:00',
        duration_minutes: 60
      })
    ])
    expect(window.startHour).toBe(6)
    expect(window.endHour).toBe(22)
    expect(window.startMinutes).toBe(360)
    expect(window.endMinutes).toBe(1320)
  })

  it('publishes the hour labels up to the closing hour', () => {
    const hours = boardHours(boardWindow([]))
    expect(hours[0]).toBe(BOARD_DEFAULT_START_HOUR)
    expect(hours.at(-1)).toBe(BOARD_DEFAULT_END_HOUR - 1)
    expect(boardHours(null)).toEqual([])
  })
})

describe('block geometry', () => {
  it('places the first hour at zero and each minute on its own pixel', () => {
    const window = boardWindow([])
    expect(boardOffset('2026-05-13T07:00:00', window)).toBe(0)
    expect(boardOffset('2026-05-13T09:00:00', window)).toBe(2 * BOARD_HOUR_PX)
    expect(boardOffset('nope', window)).toBeNull()
  })

  it('keeps a minimum height so a short block still paints', () => {
    const halfHour = boardBlockHeight(30)
    const hour = boardBlockHeight(60)
    expect(halfHour).toBeGreaterThan(20)
    expect(hour).toBeGreaterThan(halfHour)
    expect(boardBlockHeight(0)).toBeLessThanOrEqual(halfHour)
  })

  it('tiers the content by the length of the block so nothing is clipped', () => {
    expect(appointmentSizeTier(30)).toBe('compact')
    expect(appointmentSizeTier(45)).toBe('short')
    expect(appointmentSizeTier(59)).toBe('short')
    expect(appointmentSizeTier(60)).toBe('standard')
    expect(appointmentSizeTier(89)).toBe('standard')
    expect(appointmentSizeTier(90)).toBe('regular')
  })
})

describe('gapIsBookable (T5a)', () => {
  it('offers holes from 30 to 120 minutes only', () => {
    expect(MIN_FREE_SLOT_MINUTES).toBe(30)
    expect(MAX_BOOKABLE_GAP_MINUTES).toBe(120)

    expect(gapIsBookable({ minutes: 29 })).toBe(false)
    expect(gapIsBookable({ minutes: 30 })).toBe(true)
    expect(gapIsBookable({ minutes: 90 })).toBe(true)
    expect(gapIsBookable({ minutes: 120 })).toBe(true)
    // Un hueco de 330 minutos no se pinta como botón: se declara «Sin citas».
    expect(gapIsBookable({ minutes: 121 })).toBe(false)
    expect(gapIsBookable({ minutes: 330 })).toBe(false)
    expect(gapIsBookable(null)).toBe(false)
  })

  it('keeps the board column floor at 176 px', () => {
    expect(BOARD_COLUMN_MIN_PX).toBe(176)
  })
})

describe('freeGaps', () => {
  it('offers the holes of 30 minutes or more between blocks', () => {
    const window = boardWindow([appointment()])
    const gaps = freeGaps([appointment()], window)

    expect(gaps.map(gap => [gap.startMinutes, gap.endMinutes])).toEqual([
      [420, 540],
      [570, 1200]
    ])
    expect(gaps[0]).toMatchObject({ hour: 7, minute: 0, minutes: 120 })
    expect(gaps[0].top).toBe(0)
  })

  it('never offers a hole shorter than the minimum', () => {
    const window = boardWindow([])
    const tight = [
      appointment({ id: 1, scheduled_at: '2026-05-13T09:00:00', duration_minutes: 30 }),
      appointment({ id: 2, scheduled_at: '2026-05-13T09:45:00', duration_minutes: 30 })
    ]
    const gaps = freeGaps(tight, window)
    expect(gaps.every(gap => gap.minutes >= MIN_FREE_SLOT_MINUTES)).toBe(true)
    // El hueco de 15 min entre 09:30 y 09:45 no se ofrece.
    expect(gaps.some(gap => gap.startMinutes === 570 && gap.minutes === 15)).toBe(false)
  })

  it('merges overlapping blocks instead of inventing a hole between them', () => {
    const window = boardWindow([])
    const overlapping = [
      appointment({ id: 1, scheduled_at: '2026-05-13T09:00:00', duration_minutes: 60 }),
      appointment({ id: 2, scheduled_at: '2026-05-13T09:30:00', duration_minutes: 60 })
    ]
    const gaps = freeGaps(overlapping, window)
    expect(gaps.map(gap => [gap.startMinutes, gap.endMinutes])).toEqual([
      [420, 540],
      [630, 1200]
    ])
  })

  it('declares the exact start minute so a 10:30 hole is not booked at 10:00', () => {
    const window = boardWindow([])
    const gaps = freeGaps(
      [appointment({ scheduled_at: '2026-05-13T07:00:00', duration_minutes: 210 })],
      window
    )
    expect(gaps[0]).toMatchObject({ hour: 10, minute: 30, startMinutes: 630 })
  })
})

describe('resourceOccupancy', () => {
  it('measures the booked share of the window', () => {
    const window = boardWindow([])
    const { minutes, percent } = resourceOccupancy(
      [appointment({ duration_minutes: 60 }), appointment({ id: 2, duration_minutes: 60 })],
      window
    )
    expect(minutes).toBe(120)
    expect(percent).toBe(15)
  })

  it('does not count a cancelled block against the room', () => {
    const window = boardWindow([])
    expect(resourceOccupancy([appointment({ status: 'cancelled' })], window).minutes).toBe(0)
  })
})

describe('resourceState', () => {
  it('reports the block that covers now as busy', () => {
    const state = resourceState(
      [appointment({ status: 'in_consultation', duration_minutes: 60 })],
      at(9, 30)
    )
    expect(state).toMatchObject({ tone: 'busy', status: 'in_consultation', untilMinutes: 600 })
  })

  it('reports a waiting patient when no block covers now', () => {
    const state = resourceState([appointment({ status: 'arrived' })], at(10, 30))
    expect(state.tone).toBe('wait')
    expect(state.status).toBe('arrived')
  })

  it('reports free with the next booked minute', () => {
    const state = resourceState([appointment()], at(8, 0))
    expect(state).toMatchObject({ tone: 'free', nextMinutes: 540 })
  })

  it('reports free with no next block when the day is over', () => {
    const state = resourceState([appointment()], at(21, 0))
    expect(state.nextMinutes).toBeNull()
  })
})

describe('board columns', () => {
  const chairs = [
    { id: 4, name: 'Ambiente 1', code: 'AMB-001' },
    { id: 5, name: 'Ambiente 2', code: 'AMB-002' }
  ]

  it('gives the catalogue rooms a column, with or without blocks', () => {
    const columns = groupByChair([appointment()], chairs)
    expect(columns.map(column => column.name)).toEqual(['Ambiente 1', 'Ambiente 2'])
    expect(columns[0].appointments).toHaveLength(1)
    expect(columns[1].appointments).toHaveLength(0)
    expect(columns[0].meta).toBe('AMB-001')
  })

  it('keeps a room the catalogue does not publish instead of dropping its block', () => {
    const columns = groupByChair(
      [appointment({ dental_chair: { id: 9, name: 'Ambiente 9' } })],
      chairs
    )
    expect(columns.map(column => column.name)).toEqual(['Ambiente 1', 'Ambiente 2', 'Ambiente 9'])
  })

  it('buckets rows with no room under Sin ambiente', () => {
    const columns = groupByChair([appointment({ dental_chair: null })], chairs)
    expect(columns.at(-1)).toMatchObject({ key: 'chair-none', name: 'Sin ambiente' })
  })

  it('groups professionals by the name the row publishes', () => {
    const columns = groupByProfessional([
      appointment(),
      appointment({ id: 2, user_id: 3, user: { id: 3, name: 'Dr. Paredes' } }),
      appointment({ id: 3, user_id: null, user: null })
    ])
    expect(columns.map(column => column.name)).toEqual([
      'Dra. Ruiz',
      'Dr. Paredes',
      'Sin profesional'
    ])
    expect(columns[0].key).toBe('professional-2')
  })
})

describe('formatDurationMinutes (T7)', () => {
  it('escribe minutos hasta la hora y horas y minutos por encima', () => {
    expect(formatDurationMinutes(0)).toBe('0 min')
    expect(formatDurationMinutes(16)).toBe('16 min')
    expect(formatDurationMinutes(59)).toBe('59 min')
    expect(formatDurationMinutes(60)).toBe('1 h')
    expect(formatDurationMinutes(76)).toBe('1 h 16 min')
    expect(formatDurationMinutes(263)).toBe('4 h 23 min')
    expect(formatDurationMinutes(null)).toBe('—')
    expect(formatDurationMinutes(-5)).toBe('—')
  })
})

// T8 — capacidad: cuarenta citas en un día y citas simultáneas en la misma
// columna. El reparto en carriles es aritmética pura: quién va al lado de quién,
// cuántos caben y cuántos se agrupan en el indicador «+N».
describe('layoutOverlapLanes (T8)', () => {
  const apt = (id, start, minutes = 30) => ({
    id,
    scheduled_at: `2026-05-13T${start}:00`,
    duration_minutes: minutes
  })

  it('citas que no se solapan ocupan el ancho completo', () => {
    const { blocks, indicators } = layoutOverlapLanes([apt(1, '09:00'), apt(2, '10:00')])
    expect(indicators).toEqual([])
    expect(blocks.map(b => [b.appointment.id, b.slot, b.slots, b.hidden])).toEqual([
      [1, 0, 1, false],
      [2, 0, 1, false]
    ])
  })

  it('dos solapadas se reparten en dos carriles', () => {
    const { blocks } = layoutOverlapLanes([apt(1, '09:00', 60), apt(2, '09:30', 60)])
    expect(blocks.map(b => [b.appointment.id, b.slot, b.slots])).toEqual([
      [1, 0, 2],
      [2, 1, 2]
    ])
  })

  it('cuatro simultáneas dejan tres visibles lado a lado y «+1»', () => {
    const { blocks, indicators } = layoutOverlapLanes([
      apt(1, '09:00'),
      apt(2, '09:00'),
      apt(3, '09:00'),
      apt(4, '09:00')
    ])
    expect(blocks.filter(b => !b.hidden).map(b => [b.appointment.id, b.slot, b.slots])).toEqual([
      [1, 0, 4],
      [2, 1, 4],
      [3, 2, 4]
    ])
    expect(blocks.filter(b => b.hidden).map(b => b.appointment.id)).toEqual([4])
    expect(indicators).toEqual([
      { slot: 3, slots: 4, count: 1, startMinutes: 540, endMinutes: 570 }
    ])
  })

  it('cinco o más simultáneas agrupan el resto en un solo indicador', () => {
    const { indicators } = layoutOverlapLanes([
      apt(1, '09:00'),
      apt(2, '09:00'),
      apt(3, '09:00'),
      apt(4, '09:00'),
      apt(5, '09:10', 20)
    ])
    expect(indicators).toHaveLength(1)
    expect(indicators[0]).toMatchObject({ slot: 3, slots: 4, count: 2 })
  })

  it('cuarenta citas en una jornada: grupos independientes y ningún carril perdido', () => {
    // 8:00–14:00, un bloque de cuatro simultáneas cada media hora.
    const list = []
    let id = 1
    for (let slot = 0; slot < 12; slot++) {
      const hour = 8 + Math.floor(slot / 2)
      const minute = slot % 2 === 0 ? '00' : '30'
      for (let lane = 0; lane < 4; lane++) {
        if (list.length === 40) break
        list.push({
          id: id++,
          scheduled_at: `2026-05-13T${String(hour).padStart(2, '0')}:${minute}:00`,
          duration_minutes: 30
        })
      }
    }
    expect(list).toHaveLength(40)
    const { blocks, indicators } = layoutOverlapLanes(list)
    expect(blocks).toHaveLength(40)
    // Diez grupos de cuatro simultáneas: cada uno deja tres visibles y «+1».
    expect(indicators).toHaveLength(10)
    expect(indicators.every(indicator => indicator.count === 1 && indicator.slots === 4)).toBe(true)
    expect(blocks.filter(b => !b.hidden)).toHaveLength(30)
  })
})
