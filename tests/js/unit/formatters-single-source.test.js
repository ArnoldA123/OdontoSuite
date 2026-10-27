import { describe, it, expect } from 'vitest'
import { execFileSync } from 'node:child_process'
import { resolve } from 'node:path'
import { pathToFileURL } from 'node:url'
import * as formatModule from '../../../resources/js/composables/useFormatters.js'

// Issue #451 / T1 — the single format module. The contract (S1-S4):
//   * one locale (es-PE) and one clock (24 h, hourCycle 'h23');
//   * `formatCurrency` never renders `S/ NaN`;
//   * a `YYYY-MM-DD` value is read by local components, never through
//     `new Date('YYYY-MM-DD')` (UTC), so it never drifts one day back.
//
// Intl renders U+00A0 between the currency glyph and the amount
// (`S/\u00a01,234.50`). That is locale-correct and the rest of the suite
// normalizes it the same way (`FormatPENLabelTest`, the dashboard and cash
// smokes), so the assertions below compare the collapsed glyph.
const collapseSpaces = value => String(value).replace(/[\u00a0\u202f]/g, ' ')

// The date/time guarantees are only meaningful under a non-UTC session
// timezone, so they run in a child Node process pinned to America/Lima
// (UTC-5). The in-process cases below build local Dates explicitly and are
// therefore timezone independent. Vitest runs with the project root as cwd
// and the child process inherits it, so an absolute module path is enough.
const MODULE_PATH = resolve(process.cwd(), 'resources/js/composables/useFormatters.js')
const MODULE_URL = pathToFileURL(MODULE_PATH).href

const runInLima = script => {
  const raw = execFileSync(process.execPath, ['--input-type=module', '-e', script], {
    env: { ...process.env, TZ: 'America/Lima' },
    encoding: 'utf8'
  })
  return JSON.parse(raw)
}

const LIMA_SCRIPT = `
const mod = await import(${JSON.stringify(MODULE_URL)})
const call = (name, ...args) => (typeof mod[name] === 'function' ? mod[name](...args) : null)
process.stdout.write(
  JSON.stringify({
    dateOnly: call('formatDate', '2026-10-27'),
    dateOnlyJune: call('formatDate', '2026-06-02'),
    dateOnlyWithNaiveTime: call('formatDate', '2026-10-27T20:07:00'),
    naiveDateTime: call('formatDateTime', '2026-10-27T20:07:00'),
    timeFromInstant: call('formatTime', '2026-10-27T20:07:00Z'),
    dateTimeFromInstant: call('formatDateTime', '2026-10-27T20:07:00Z'),
    timeFromOffset: call('formatTime', '2026-10-27T20:07:00-05:00'),
    controlDateOnlyUtcDay: new Date('2026-10-27').getDate()
  })
)
`

let lima = null
const limaOutput = () => {
  if (!lima) lima = runInLima(LIMA_SCRIPT)
  return lima
}

describe('formatCurrency (S2)', () => {
  it('renders a PEN amount with two fixed decimals and one S/ prefix', () => {
    expect(collapseSpaces(formatModule.formatCurrency(759))).toBe('S/ 759.00')
    expect(collapseSpaces(formatModule.formatCurrency(1234.5))).toBe('S/ 1,234.50')
    expect(collapseSpaces(formatModule.formatCurrency(0))).toBe('S/ 0.00')
  })

  it('accepts a numeric string', () => {
    expect(collapseSpaces(formatModule.formatCurrency('123.45'))).toBe('S/ 123.45')
  })

  it('collapses null, undefined and non-numeric values to S/ 0.00 (never S/ NaN)', () => {
    const inputs = [null, undefined, 'abc', NaN, Infinity]

    for (const input of inputs) {
      const out = formatModule.formatCurrency(input)
      expect(collapseSpaces(out)).toBe('S/ 0.00')
      expect(out).not.toContain('NaN')
    }
  })

  it('keeps formatPENLabel as the alias of formatCurrency', () => {
    expect(formatModule.formatPENLabel).toBe(formatModule.formatCurrency)
  })
})

describe('formatUsd', () => {
  it('renders USD with two decimals by default', () => {
    expect(collapseSpaces(formatModule.formatUsd(1.5))).toBe('USD 1.50')
  })

  it('lets the caller override the fraction digits', () => {
    const out = formatModule.formatUsd(0.0042, {
      minimumFractionDigits: 4,
      maximumFractionDigits: 4
    })

    expect(collapseSpaces(out)).toBe('USD 0.0042')
  })

  it('falls back to the canonical zero for a non-numeric amount', () => {
    expect(collapseSpaces(formatModule.formatUsd('abc'))).toBe('USD 0.00')
  })
})

describe('formatNumber', () => {
  it('renders numbers with the es-PE grouping separators', () => {
    expect(formatModule.formatNumber(1234.5)).toBe('1,234.5')
    expect(formatModule.formatNumber(1234567.891)).toBe('1,234,567.891')
  })

  it('honours Intl.NumberFormat options', () => {
    const out = formatModule.formatNumber(1234.5, {
      minimumFractionDigits: 2,
      maximumFractionDigits: 2
    })

    expect(out).toBe('1,234.50')
  })

  it('renders non-finite values as the canonical zero', () => {
    expect(formatModule.formatNumber('abc')).toBe('0')
    expect(formatModule.formatNumber(null)).toBe('0')
    expect(formatModule.formatNumber(undefined)).toBe('0')
    expect(formatModule.formatNumber(NaN)).toBe('0')
  })
})

describe('formatDate', () => {
  it('renders d/m/Y by default', () => {
    expect(formatModule.formatDate(new Date(2026, 9, 27))).toBe('27/10/2026')
  })

  it('accepts the long-month override through Intl.DateTimeFormat options', () => {
    const out = formatModule.formatDate(new Date(2026, 9, 27), {
      day: 'numeric',
      month: 'long',
      year: 'numeric'
    })

    expect(out).toBe('27 de octubre de 2026')
  })

  it('returns an empty string for missing or unparseable values', () => {
    for (const input of [null, undefined, '', 'not-a-date', new Date('nope')]) {
      expect(formatModule.formatDate(input)).toBe('')
    }
  })
})

describe('formatDateTime', () => {
  it('renders d/m/Y, HH:mm on a 24 hour clock by default', () => {
    expect(formatModule.formatDateTime(new Date(2026, 9, 27, 15, 7))).toBe('27/10/2026, 15:07')
  })

  it('accepts Intl.DateTimeFormat field overrides', () => {
    const out = formatModule.formatDateTime(new Date(2026, 9, 27, 15, 7), {
      month: 'short'
    })

    expect(out).toContain('oct')
    expect(out).toContain('15:07')
  })

  it('returns an empty string for missing or unparseable values', () => {
    for (const input of [null, undefined, '', 'not-a-date', new Date('nope')]) {
      expect(formatModule.formatDateTime(input)).toBe('')
    }
  })
})

describe('formatTime', () => {
  it('renders HH:mm on a 24 hour clock', () => {
    expect(formatModule.formatTime(new Date(2026, 9, 27, 15, 7))).toBe('15:07')
    expect(formatModule.formatTime(new Date(2026, 9, 27, 0, 5))).toBe('00:05')
  })

  it('never emits a meridiem marker', () => {
    const out = formatModule.formatTime(new Date(2026, 9, 27, 15, 7))

    expect(out).not.toMatch(/a\.\s?m\.|p\.\s?m\.|AM|PM/i)
  })

  it('returns an empty string for missing or unparseable values', () => {
    for (const input of [null, undefined, '', 'not-a-date', new Date('nope')]) {
      expect(formatModule.formatTime(input)).toBe('')
    }
  })
})

describe('date-only parsing and the 24 hour clock under TZ=America/Lima (S3, S4)', () => {
  it('reads a date-only value by local components instead of UTC', () => {
    const out = limaOutput()

    // Control: proves the child process really runs in UTC-5, where
    // `new Date('2026-10-27')` is still 26 October.
    expect(out.controlDateOnlyUtcDay).toBe(26)

    expect(out.dateOnly).toBe('27/10/2026')
    expect(out.dateOnlyJune).toBe('02/06/2026')
    expect(out.dateOnlyWithNaiveTime).toBe('27/10/2026')
    expect(out.naiveDateTime).toBe('27/10/2026, 20:07')
  })

  it('renders an instant in the session timezone on the 24 hour clock', () => {
    const out = limaOutput()

    expect(out.timeFromInstant).toBe('15:07')
    expect(out.dateTimeFromInstant).toBe('27/10/2026, 15:07')
    expect(out.timeFromOffset).toBe('20:07')
  })
})

describe('dateOnly for date-typed fields (S3)', () => {
  it('reduces a UTC-midnight payload to the calendar date it declares', () => {
    const declared = formatModule.dateOnly('2026-10-24T00:00:00.000000Z')

    expect(declared).toBe('2026-10-24')
    // Reading the declared date through the canonical formatter builds local
    // components, so no reader timezone can move it to the previous day.
    expect(formatModule.formatDate(declared)).toBe('24/10/2026')
  })

  it('reduces a naive date-time and leaves a Date or an empty value alone', () => {
    expect(formatModule.dateOnly('2026-10-24T09:00:00')).toBe('2026-10-24')
    expect(formatModule.dateOnly('2026-10-24')).toBe('2026-10-24')
    expect(formatModule.dateOnly('')).toBe('')

    const date = new Date(2026, 9, 24)

    expect(formatModule.dateOnly(date)).toBe(date)
  })
})

describe('useFormatters', () => {
  it('exposes every shared helper as a function', () => {
    const formatters = formatModule.useFormatters()
    const names = [
      'formatCurrency',
      'formatPENLabel',
      'formatUsd',
      'formatNumber',
      'formatDate',
      'formatDateTime',
      'formatTime',
      'dateOnly',
      'formatCompactTime',
      'formatCompactTimeRange'
    ]

    for (const name of names) {
      expect(typeof formatters[name]).toBe('function')
    }
  })
})

describe('robustness', () => {
  it('never throws on values with no primitive representation', () => {
    const hostile = Symbol('amount')
    const broken = {
      toString() {
        throw new Error('boom')
      }
    }

    expect(() => formatModule.formatCurrency(hostile)).not.toThrow()
    expect(() => formatModule.formatCurrency(broken)).not.toThrow()
    expect(() => formatModule.formatNumber(hostile)).not.toThrow()
    expect(() => formatModule.formatUsd(broken)).not.toThrow()
    expect(() => formatModule.formatDate(broken)).not.toThrow()
    expect(() => formatModule.formatTime(hostile)).not.toThrow()

    expect(collapseSpaces(formatModule.formatCurrency(hostile))).toBe('S/ 0.00')
    expect(formatModule.formatDate(broken)).toBe('')
    expect(formatModule.formatTime(hostile)).toBe('')
  })
})
