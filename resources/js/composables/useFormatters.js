/**
 * full-user-browser-audit-2026-08-05 / PR1 / currency-format-helper.
 *
 * Single source of truth for PEN (Peruvian Sol) rendering. Replaces the
 * inline `Intl.NumberFormat('es-PE', {style:'currency',currency:'PEN'})`
 * call sites that previously doubled the `S/` prefix when concatenated
 * with a literal `S/` in template bindings (DashboardPage.vue line 400
 * and SessionList.vue lines 161, 173, 184).
 *
 * Contract:
 *   - `formatPENLabel(amount)` returns a string of the form `S/ <amount>`
 *     with exactly ONE `S/` prefix.
 *   - `amount` MAY be a number, a numeric string, null, undefined, or
 *     any non-numeric value; non-numeric inputs collapse to `S/ 0.00`.
 *   - The helper never throws and never returns `NaN`.
 *   - This module is the ONLY call site of
 *     `Intl.NumberFormat('es-PE', {style:'currency',currency:'PEN'})`
 *     in the entire frontend (`tests/Unit/Composables/FormatPENLabelTest`
 *     enforces this).
 */
export function useFormatters () {
  return {
    formatPENLabel
  }
}

const PEN_FORMATTER = new Intl.NumberFormat('es-PE', {
  style: 'currency',
  currency: 'PEN',
  minimumFractionDigits: 2,
  maximumFractionDigits: 2
})

/**
 * Render a numeric amount as a PEN currency label.
 *
 * Examples (es-PE / Node 22):
 *   formatPENLabel(759)       -> "S/ 759.00"
 *   formatPENLabel(1234.50)   -> "S/ 1,234.50"
 *   formatPENLabel(-5)        -> "-S/ 5.00"
 *   formatPENLabel(0)         -> "S/ 0.00"
 *   formatPENLabel(null)      -> "S/ 0.00"
 *   formatPENLabel(undefined) -> "S/ 0.00"
 *   formatPENLabel('123.45')  -> "S/ 123.45"
 *
 * @param {number|string|null|undefined} amount
 * @returns {string}
 */
export function formatPENLabel (amount) {
  const numeric = Number(amount)
  const safe = Number.isFinite(numeric) ? numeric : 0
  return PEN_FORMATTER.format(safe)
}
