import { describe, it, expect } from 'vitest'
import {
  DRAG_THRESHOLD_PX,
  resolveDropSlot,
  slotKeyToScheduledAt,
  addMinutesToLocal,
  canDropOn
} from '../../../resources/js/modules/appointments/dragDropMath.js'

// Geometry fixtures mirror getBoundingClientRect's DOMRect shape.
const rect = (left, top, width, height) => ({
  left,
  top,
  right: left + width,
  bottom: top + height,
  width,
  height
})

const targets = [
  { key: '2026-06-05-10', rect: rect(0, 0, 200, 80) },
  { key: '2026-06-05-11', rect: rect(200, 0, 200, 80) },
  { key: '2026-06-05-12', rect: rect(400, 0, 200, 80) }
]

describe('DRAG_THRESHOLD_PX', () => {
  it('is a small positive pixel threshold', () => {
    expect(DRAG_THRESHOLD_PX).toBeGreaterThan(0)
    expect(DRAG_THRESHOLD_PX).toBeLessThanOrEqual(10)
  })
})

describe('resolveDropSlot', () => {
  it('resolves the slot whose rect contains the pointer', () => {
    expect(resolveDropSlot(targets, 100, 40)).toBe('2026-06-05-10')
    expect(resolveDropSlot(targets, 250, 10)).toBe('2026-06-05-11')
    expect(resolveDropSlot(targets, 599, 79)).toBe('2026-06-05-12')
  })

  it('resolves shared cell edges deterministically to the first matching rect', () => {
    expect(resolveDropSlot(targets, 200, 40)).toBe('2026-06-05-10')
    expect(resolveDropSlot(targets, 400, 40)).toBe('2026-06-05-11')
  })

  it('returns null when the pointer is outside every rect', () => {
    expect(resolveDropSlot(targets, -1, 40)).toBeNull()
    expect(resolveDropSlot(targets, 601, 40)).toBeNull()
    expect(resolveDropSlot(targets, 300, 81)).toBeNull()
  })

  it('returns null for an empty target list', () => {
    expect(resolveDropSlot([], 10, 10)).toBeNull()
    expect(resolveDropSlot(undefined, 10, 10)).toBeNull()
  })

  it('skips malformed entries instead of throwing', () => {
    const mixed = [
      null,
      { key: 'x' },
      { rect: rect(0, 0, 10, 10) },
      { key: 'ok', rect: rect(0, 0, 10, 10) }
    ]
    expect(resolveDropSlot(mixed, 5, 5)).toBe('ok')
  })
})

describe('slotKeyToScheduledAt', () => {
  it('formats the hour slot key as a local YYYY-MM-DDTHH:mm payload with minute :00', () => {
    expect(slotKeyToScheduledAt('2026-06-05-10')).toBe('2026-06-05T10:00')
    expect(slotKeyToScheduledAt('2026-06-05-9')).toBe('2026-06-05T09:00')
    expect(slotKeyToScheduledAt('2026-06-05-6')).toBe('2026-06-05T06:00')
    expect(slotKeyToScheduledAt('2026-06-05-21')).toBe('2026-06-05T21:00')
  })

  it('crosses year and month boundaries via calendar components, not string math', () => {
    expect(slotKeyToScheduledAt('2026-12-31-21')).toBe('2026-12-31T21:00')
    expect(slotKeyToScheduledAt('2027-01-01-6')).toBe('2027-01-01T06:00')
    expect(slotKeyToScheduledAt('2028-02-29-10')).toBe('2028-02-29T10:00')
  })

  it('rejects malformed or impossible slot keys', () => {
    expect(slotKeyToScheduledAt('2026-02-30-10')).toBeNull()
    expect(slotKeyToScheduledAt('2026-13-01-10')).toBeNull()
    expect(slotKeyToScheduledAt('2026-01-32-10')).toBeNull()
    expect(slotKeyToScheduledAt('2026-06-05-24')).toBeNull()
    expect(slotKeyToScheduledAt('2026-6-5-10')).toBeNull()
    expect(slotKeyToScheduledAt('garbage')).toBeNull()
    expect(slotKeyToScheduledAt('')).toBeNull()
    expect(slotKeyToScheduledAt(undefined)).toBeNull()
  })
})

describe('addMinutesToLocal', () => {
  it('adds minutes across day, month and year boundaries', () => {
    expect(addMinutesToLocal('2026-06-05T10:00', 30)).toBe('2026-06-05T10:30')
    expect(addMinutesToLocal('2026-12-31T23:30', 45)).toBe('2027-01-01T00:15')
    expect(addMinutesToLocal('2026-02-28T23:00', 120)).toBe('2026-03-01T01:00')
    expect(addMinutesToLocal('2028-02-29T23:30', 60)).toBe('2028-03-01T00:30')
  })

  it('rejects malformed input', () => {
    expect(addMinutesToLocal('2026-06-05 10:00', 30)).toBeNull()
    expect(addMinutesToLocal('garbage', 30)).toBeNull()
    expect(addMinutesToLocal(undefined, 30)).toBeNull()
  })
})

describe('canDropOn', () => {
  // Fixed "now": Wednesday 2026-05-13 07:00 local.
  const now = new Date(2026, 4, 13, 7, 0, 0)
  const appointmentAt = scheduledAt => ({ scheduled_at: scheduledAt })

  it('rejects dropping onto the appointment current slot (no-op, no request)', () => {
    const appointment = appointmentAt('2026-05-13T10:00:00')
    expect(canDropOn('2026-05-13-10', appointment, now)).toBe(false)
  })

  it('accepts a different slot at least one minute in the future', () => {
    const appointment = appointmentAt('2026-05-13T10:00:00')
    expect(canDropOn('2026-05-13-14', appointment, now)).toBe(true)
    expect(canDropOn('2026-05-14-6', appointment, now)).toBe(true)
    expect(canDropOn('2026-06-01-6', appointment, now)).toBe(true)
  })

  it('rejects past slots', () => {
    const appointment = appointmentAt('2026-05-13T10:00:00')
    expect(canDropOn('2026-05-12-14', appointment, now)).toBe(false)
    expect(canDropOn('2026-04-30-6', appointment, now)).toBe(false)
  })

  it('enforces the endpoint >= 1-minute-future rule at the minute boundary', () => {
    const appointment = appointmentAt('2026-05-13T10:00:00')
    const almostNow = new Date(2026, 4, 13, 6, 59, 30)
    expect(canDropOn('2026-05-13-7', appointment, almostNow)).toBe(false)
    const twoMinutesAhead = new Date(2026, 4, 13, 6, 58, 0)
    expect(canDropOn('2026-05-13-7', appointment, twoMinutesAhead)).toBe(true)
  })

  it('rejects unresolvable slot keys and appointments without a valid start', () => {
    expect(canDropOn('garbage', appointmentAt('2026-05-13T10:00:00'), now)).toBe(false)
    expect(canDropOn('2026-05-13-14', appointmentAt('not-a-date'), now)).toBe(false)
  })
})
