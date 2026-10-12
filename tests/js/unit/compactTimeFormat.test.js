import { describe, it, expect } from 'vitest'
import {
  formatCompactTime,
  formatCompactTimeRange,
  useFormatters
} from '../../../resources/js/composables/useFormatters.js'

// T8b defect 3 — compact chip time contract: calendar chips render
// `HH:mm - HH:mm` (24h, tnum-friendly digits) in all three views. The long
// es-PE locale form ("08:00 a. m.-0…") clipped the patient name inside the
// Week chip; that full locale format stays in the details modal only.
describe('compact time formatting (T8b defect 3)', () => {
  it('formats a single time as 24h HH:mm with padded parts', () => {
    expect(formatCompactTime('2026-05-13T10:00:00')).toBe('10:00')
    expect(formatCompactTime('2026-05-13T08:05:00')).toBe('08:05')
    expect(formatCompactTime('2026-05-13T23:59:00')).toBe('23:59')
  })

  it('formats a range as compact "HH:mm - HH:mm"', () => {
    expect(formatCompactTimeRange('2026-05-13T10:00:00', '2026-05-13T10:30:00')).toBe(
      '10:00 - 10:30'
    )
    expect(formatCompactTimeRange('2026-05-13T08:00:00', '2026-05-13T08:30:00')).toBe(
      '08:00 - 08:30'
    )
  })

  it('never emits locale meridiem markers', () => {
    const range = formatCompactTimeRange('2026-05-13T08:00:00', '2026-05-13T20:30:00')
    expect(range).not.toMatch(/a\. m\.|p\. m\.|AM|PM/i)
    expect(formatCompactTime('2026-05-13T20:30:00')).not.toMatch(/a\. m\.|p\. m\.|AM|PM/i)
  })

  it('repeats the start when the end is missing (single-point chips)', () => {
    expect(formatCompactTimeRange('2026-05-13T10:00:00', null)).toBe('10:00 - 10:00')
  })

  it('returns an empty string for invalid input instead of throwing', () => {
    expect(formatCompactTime(null)).toBe('')
    expect(formatCompactTime(undefined)).toBe('')
    expect(formatCompactTime('not-a-date')).toBe('')
    expect(formatCompactTimeRange(null, null)).toBe('')
    expect(formatCompactTimeRange('not-a-date', 'also-not')).toBe('')
  })

  it('is exposed through the useFormatters composable', () => {
    const formatters = useFormatters()
    expect(typeof formatters.formatCompactTime).toBe('function')
    expect(typeof formatters.formatCompactTimeRange).toBe('function')
  })
})
