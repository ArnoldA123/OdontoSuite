/**
 * validationErrors — read the body of a 422 the API publishes and hand it to
 * the surface that shows it.
 *
 * The consistency matrix fixes the canon for the Feedback axis: the 422 lands
 * on its field (or in its dialog) with the reason the API published, never as
 * a generic text or a toast-only notice. `useApi` throws the normalized
 * `{ response: { data }, status }` shape, and controllers put the rule in
 * `errors` while `message` may stay generic ("Error de validación").
 *
 * The module is pure and Node-loadable on purpose: the PHPUnit suite and the
 * Vitest suite can both exercise it without a runtime.
 */

/**
 * Messages a controller or the framework sends when it did not state the rule.
 * A 422 whose `message` is one of these carries the real reason in `errors`.
 */
export const GENERIC_VALIDATION_MESSAGES = Object.freeze([
  'error de validación',
  'datos no válidos',
  'the given data was invalid.',
  'the given data was invalid',
  'validation failed'
])

const normalizeForComparison = message =>
  String(message ?? '')
    .trim()
    .toLowerCase()

/**
 * Pick the first message out of a Laravel error entry (string or array).
 *
 * @param {unknown} value
 * @returns {string}
 */
export function firstMessage(value) {
  if (Array.isArray(value)) {
    return value.length ? firstMessage(value[0]) : ''
  }

  if (value === undefined || value === null) {
    return ''
  }

  return String(value).trim()
}

/**
 * Campo → primer mensaje. Arrays collapse to their first entry so a form can
 * paint one message per field.
 *
 * @param {Record<string, unknown> | null | undefined} rawErrors
 * @returns {Record<string, string>}
 */
export function normalizeValidationErrors(rawErrors) {
  if (!rawErrors || typeof rawErrors !== 'object') {
    return {}
  }

  const normalized = {}

  for (const [field, message] of Object.entries(rawErrors)) {
    const text = firstMessage(message)
    if (text) {
      normalized[field] = text
    }
  }

  return normalized
}

/**
 * True when the message does not state the rule the server rejected.
 *
 * @param {unknown} message
 * @returns {boolean}
 */
export function isGenericValidationMessage(message) {
  return GENERIC_VALIDATION_MESSAGES.includes(normalizeForComparison(message))
}

/**
 * The reason to show: the server's own message when it states one, otherwise
 * the first entry of `errors`, otherwise the caller's fallback.
 *
 * @param {{ message?: unknown, errors?: Record<string, unknown> } | null | undefined} payload
 * @param {string} [fallback]
 * @returns {string}
 */
export function pickValidationMessage(payload, fallback = '') {
  const message = firstMessage(payload?.message)

  if (message && !isGenericValidationMessage(message)) {
    return message
  }

  const normalized = normalizeValidationErrors(payload?.errors)
  const firstError = Object.values(normalized)[0]

  return firstError || message || fallback
}

/**
 * Collapse a server key onto the form field that contains it, so a line error
 * (`items.0.description`) lands on the `items` field the form paints.
 *
 * @param {string} key
 * @param {string[]} fields
 * @returns {string}
 */
export function collapseFieldKey(key, fields) {
  if (!Array.isArray(fields) || fields.length === 0) {
    return key
  }

  if (fields.includes(key)) {
    return key
  }

  const match = fields
    .filter(field => key.startsWith(`${field}.`))
    .sort((a, b) => b.length - a.length)[0]

  return match || key
}

/**
 * Normalize the server errors and keep only the keys a declared field can
 * paint, collapsing nested keys onto their field.
 *
 * @param {Record<string, unknown> | null | undefined} rawErrors
 * @param {string[]} [fields]
 * @returns {Record<string, string>}
 */
export function mapFieldErrors(rawErrors, fields = []) {
  const normalized = normalizeValidationErrors(rawErrors)
  const mapped = {}

  for (const [key, message] of Object.entries(normalized)) {
    const field = collapseFieldKey(key, fields)
    if (fields.length > 0 && field === key && !fields.includes(key)) {
      continue
    }
    if (!mapped[field]) {
      mapped[field] = message
    }
  }

  return mapped
}

export default {
  GENERIC_VALIDATION_MESSAGES,
  firstMessage,
  normalizeValidationErrors,
  isGenericValidationMessage,
  pickValidationMessage,
  collapseFieldKey,
  mapFieldErrors
}
