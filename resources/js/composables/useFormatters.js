/**
 * Single shared format module (issue 451 / S1-S4).
 *
 * One locale (`es-PE`) and one clock (24 h, `hourCycle: 'h23'`) for money,
 * numbers, dates, date-times and times. Every other module consumes these
 * helpers; `tests/Unit/Composables/FormatPENLabelTest` enforces the
 * single-source sweep over `resources/js`.
 *
 * Contract:
 *   - `formatCurrency(amount)` renders `S/ 1,234.50` (alias `formatPENLabel`).
 *   - `formatUsd(amount, options?)` renders `USD 0.0042`; `options` overrides
 *     the fraction digits.
 *   - `formatNumber(value, options?)` renders `1,234.5` (`Intl.NumberFormat`).
 *   - `formatDate(value, options?)` renders `27/10/2026` by default.
 *   - `dateOnly(value)` reduces a date-ish value to the calendar date it
 *     declares (`YYYY-MM-DD`); date-typed fields pass it to `formatDate` so a
 *     UTC-midnight payload never shifts the day for the reader.
 *   - `formatDateTime(value, options?)` renders `27/10/2026, 15:07`.
 *   - `formatTime(value)` renders `15:07`.
 *   - Money and numbers collapse non-finite input to their canonical zero
 *     (`S/ 0.00`, `USD 0.00`, `0`); dates return `''`. Nothing throws and
 *     nothing emits `NaN`.
 *   - `options` carries `Intl` field overrides only, never a locale or a
 *     time zone.
 *
 * Dates (S3): a `YYYY-MM-DD` value (or a naive `YYYY-MM-DD[T ]HH:mm[:ss]`) is
 * built from its components as LOCAL time, so it never drifts a day outside
 * UTC. Anything carrying `Z` or an explicit offset, a `Date` or a numeric
 * timestamp is an instant and is rendered in the session time zone.
 *
 * `Intl` instances are created once: module constants for the defaults and a
 * small keyed cache for caller-provided field sets.
 */
export function useFormatters() {
  return {
    formatPENLabel,
    formatCurrency,
    formatUsd,
    formatNumber,
    formatDate,
    formatDateTime,
    formatTime,
    dateOnly,
    formatCompactTime,
    formatCompactTimeRange
  }
}

const LOCALE = 'es-PE'

/**
 * Canonical PEN formatter: the only currency declaration in the frontend.
 * The locale stays a literal because the PHP guard pins this exact
 * `Intl.NumberFormat('es-PE', { style: 'currency' })` declaration.
 */
const PEN_FORMATTER = new Intl.NumberFormat('es-PE', {
  style: 'currency',
  currency: 'PEN',
  minimumFractionDigits: 2,
  maximumFractionDigits: 2
})

const USD_FIELDS = {
  style: 'currency',
  currency: 'USD',
  minimumFractionDigits: 2,
  maximumFractionDigits: 2
}

const DATE_FIELDS = {
  day: '2-digit',
  month: '2-digit',
  year: 'numeric'
}

const DATE_TIME_FIELDS = {
  ...DATE_FIELDS,
  hour: '2-digit',
  minute: '2-digit',
  hourCycle: 'h23'
}

const TIME_FIELDS = {
  hour: '2-digit',
  minute: '2-digit',
  hourCycle: 'h23'
}

const DATE_FORMATTER = new Intl.DateTimeFormat(LOCALE, DATE_FIELDS)
const DATE_TIME_FORMATTER = new Intl.DateTimeFormat(LOCALE, DATE_TIME_FIELDS)
const TIME_FORMATTER = new Intl.DateTimeFormat(LOCALE, TIME_FIELDS)

/**
 * Build a memoizing formatter factory keyed by the option fields, so a call
 * site that passes options does not rebuild an `Intl` instance per render.
 */
const cachedFactory = build => {
  const cache = new Map()

  return fields => {
    const key = Object.keys(fields)
      .sort()
      .map(field => `${field}:${fields[field]}`)
      .join('|')
    let formatter = cache.get(key)

    if (!formatter) {
      formatter = build(fields)
      cache.set(key, formatter)
    }

    return formatter
  }
}

const numberFormatter = cachedFactory(fields => new Intl.NumberFormat(LOCALE, fields))
const dateFormatter = cachedFactory(fields => new Intl.DateTimeFormat(LOCALE, fields))

const toFiniteNumber = value => {
  try {
    const numeric = Number(value)

    return Number.isFinite(numeric) ? numeric : 0
  } catch {
    // Values with no primitive representation (Symbol, hostile objects).
    return 0
  }
}

/** `YYYY-MM-DD` or a naive `YYYY-MM-DD[T ]HH:mm[:ss[.SSS]]` — no time zone. */
const LOCAL_DATE_TIME =
  /^(\d{4})-(\d{2})-(\d{2})(?:[T ](\d{2}):(\d{2})(?::(\d{2})(?:\.(\d{1,3})\d*)?)?)?$/

/**
 * Build a local date from its components and reject impossible values
 * (2026-02-31 rolls over in the Date constructor, so it is validated).
 */
const buildLocalDate = match => {
  const [, year, month, day, hour, minute, second, millisecond] = match
  const parts = {
    year: Number(year),
    month: Number(month) - 1,
    day: Number(day),
    hour: Number(hour || 0),
    minute: Number(minute || 0),
    second: Number(second || 0),
    millisecond: Number((millisecond || '0').padEnd(3, '0'))
  }

  const date = new Date(
    parts.year,
    parts.month,
    parts.day,
    parts.hour,
    parts.minute,
    parts.second,
    parts.millisecond
  )

  const roundTrips =
    date.getFullYear() === parts.year &&
    date.getMonth() === parts.month &&
    date.getDate() === parts.day &&
    date.getHours() === parts.hour &&
    date.getMinutes() === parts.minute &&
    date.getSeconds() === parts.second

  return roundTrips ? date : null
}

/**
 * Resolve a date-ish value to a valid `Date` or `null`.
 *
 * `YYYY-MM-DD` and naive date-times are read by components (local time).
 * Instants (`Z`, explicit offset), `Date` and numeric timestamps go through
 * `new Date`.
 *
 * @param {string|number|Date|null|undefined} value
 * @returns {Date|null}
 */
const toValidDate = value => {
  if (value instanceof Date) return Number.isNaN(value.getTime()) ? null : value
  if (value === null || value === undefined || value === '') return null

  if (typeof value === 'string') {
    const local = value.match(LOCAL_DATE_TIME)

    if (local) return buildLocalDate(local)
  }

  if (typeof value !== 'string' && typeof value !== 'number') return null

  const parsed = new Date(value)

  return Number.isNaN(parsed.getTime()) ? null : parsed
}

/**
 * Render a numeric amount as a PEN currency label.
 *
 * Examples (es-PE / Node 22):
 *   formatCurrency(759)       -> "S/ 759.00"
 *   formatCurrency(1234.50)   -> "S/ 1,234.50"
 *   formatCurrency(-5)        -> "-S/ 5.00"
 *   formatCurrency(null)      -> "S/ 0.00"
 *   formatCurrency('abc')     -> "S/ 0.00"
 *
 * @param {number|string|null|undefined} amount
 * @returns {string}
 */
export function formatCurrency(amount) {
  return PEN_FORMATTER.format(toFiniteNumber(amount))
}

/**
 * Backwards-compatible alias for `formatCurrency`. New code must import
 * `formatCurrency`; call sites from PR1 keep importing this name.
 *
 * @param {number|string|null|undefined} amount
 * @returns {string}
 */
export const formatPENLabel = formatCurrency

/**
 * Render a numeric amount as a USD currency label, in the canonical locale.
 * `options` may override the fraction digits.
 *
 * @param {number|string|null|undefined} amount
 * @param {object} [options]
 * @returns {string}
 */
export function formatUsd(amount, options) {
  const fields = options ? { ...USD_FIELDS, ...options } : USD_FIELDS

  return numberFormatter(fields).format(toFiniteNumber(amount))
}

/**
 * Render a number with the canonical locale and optional `Intl.NumberFormat`
 * fields. Non-finite input collapses to the canonical zero.
 *
 * @param {number|string|null|undefined} value
 * @param {object} [options]
 * @returns {string}
 */
export function formatNumber(value, options) {
  return numberFormatter(options || {}).format(toFiniteNumber(value))
}

/**
 * Reduce a date-ish value to the calendar date it declares (`YYYY-MM-DD`).
 *
 * A date-typed field (a birth date, a promised date, a recall due date) is a
 * calendar day, not an instant: the API may serialize it as a plain date or as
 * UTC midnight, and converting the instant would shift the day for a reader in
 * another timezone. Callers pass the result to `formatDate`, which builds local
 * components from the declared day.
 *
 * @param {string|number|Date|null|undefined} value
 * @returns {string|number|Date|null|undefined}
 */
export function dateOnly(value) {
  if (value === null || value === undefined || value === '') return value

  const match = String(value).match(/^(\d{4}-\d{2}-\d{2})/)

  return match ? match[1] : value
}

/**
 * Render a date-ish value as `d/m/Y` by default. `options` adds or overrides
 * `Intl.DateTimeFormat` fields. Invalid or missing input returns `''`.
 *
 * @param {string|number|Date|null|undefined} value
 * @param {object} [options]
 * @returns {string}
 */
export function formatDate(value, options) {
  const date = toValidDate(value)

  if (!date) return ''
  if (!options) return DATE_FORMATTER.format(date)

  return dateFormatter({ ...DATE_FIELDS, ...options }).format(date)
}

/**
 * Render a date-ish value as `d/m/Y, HH:mm` on the 24 h clock by default.
 * `options` adds or overrides `Intl.DateTimeFormat` fields. Invalid or
 * missing input returns `''`.
 *
 * @param {string|number|Date|null|undefined} value
 * @param {object} [options]
 * @returns {string}
 */
export function formatDateTime(value, options) {
  const date = toValidDate(value)

  if (!date) return ''
  if (!options) return DATE_TIME_FORMATTER.format(date)

  return dateFormatter({ ...DATE_TIME_FIELDS, ...options }).format(date)
}

/**
 * Render a date-ish value as a 24 h `HH:mm`. Invalid or missing input
 * returns `''`.
 *
 * @param {string|number|Date|null|undefined} value
 * @returns {string}
 */
export function formatTime(value) {
  const date = toValidDate(value)

  return date ? TIME_FORMATTER.format(date) : ''
}

/**
 * Compact chip time (T8b defect 3). The calendar chips render this 24 h
 * `HH:mm` form instead of the locale meridiem form, which clipped the patient
 * name inside the Week chip.
 *
 * @param {string|number|Date|null|undefined} value
 * @returns {string}
 */
export function formatCompactTime(value) {
  const date = toValidDate(value)

  return date ? TIME_FORMATTER.format(date) : ''
}

/**
 * Compact `HH:mm - HH:mm` range for calendar chips. A missing/invalid end
 * repeats the start (single-point chips); a missing/invalid start collapses
 * the whole range to `''`.
 *
 * @param {string|number|Date|null|undefined} start
 * @param {string|number|Date|null|undefined} end
 * @returns {string}
 */
export function formatCompactTimeRange(start, end) {
  const begin = formatCompactTime(start)

  if (!begin) return ''

  const finish = formatCompactTime(end) || begin

  return `${begin} - ${finish}`
}
