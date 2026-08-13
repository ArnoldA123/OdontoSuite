/**
 * full-user-browser-audit-2026-08-05 / PR1 / currency-format-helper.
 * PR-pagos-01 / ui-rollout-all-modules-2026-08 — canonicalize formatCurrency.
 *
 * Single source of truth for PEN (Peruvian Sol) rendering. Replaces the
 * inline `Intl.NumberFormat('es-PE', {style:'currency',currency:'PEN'})`
 * call sites that previously doubled the `S/` prefix when concatenated
 * with a literal `S/` in template bindings (DashboardPage.vue line 400
 * and SessionList.vue lines 161, 173, 184).
 *
 * Contract:
 *   - `formatCurrency(amount, options)` and `formatPENLabel(amount)`
 *     return a string of the form `S/ <amount>` with exactly ONE `S/`
 *     prefix. `formatCurrency` is the canonical name adopted by
 *     PR-pagos-01; `formatPENLabel` is a backwards-compatible alias so
 *     DashboardPage.vue / SessionList.vue (PR1 callers) keep working
 *     without import-line edits.
 *   - `amount` MAY be a number, a numeric string, null, undefined, or
 *     any non-numeric value; non-numeric inputs collapse to `S/ 0.00`.
 *   - The `options` second argument is reserved for future use and is
 *     currently ignored (per PAGOS-SCP-001: no new currencies or
 *     formats).
 *   - The helper never throws and never returns `NaN`.
 *   - This module is the ONLY declaration site of
 *     `Intl.NumberFormat('es-PE', {style:'currency',currency:'PEN'})`
 *     for the `formatCurrency` / `formatPENLabel` helper in the entire
 *     frontend (`tests/Unit/Composables/FormatPENLabelTest` enforces
 *     this). `CurrencyInput.vue` keeps its own `Intl.NumberFormat`
 *     shape (no `currency` key) for in-field display — that is
 *     consumed as-is, no formatting fork.
 */
export function useFormatters () {
  return {
    formatPENLabel,
    formatCurrency
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
 * Canonical entry point for PR-pagos-01. The signature is
 * `(amount, options) => string`; `options` is reserved for future use
 * and is currently ignored (per PAGOS-SCP-001).
 *
 * Examples (es-PE / Node 22):
 *   formatCurrency(759)       -> "S/ 759.00"
 *   formatCurrency(1234.50)   -> "S/ 1,234.50"
 *   formatCurrency(-5)        -> "-S/ 5.00"
 *   formatCurrency(0)         -> "S/ 0.00"
 *   formatCurrency(null)      -> "S/ 0.00"
 *   formatCurrency(undefined) -> "S/ 0.00"
 *   formatCurrency('123.45')  -> "S/ 123.45"
 *
 * @param {number|string|null|undefined} amount
 * @param {object} [options] Reserved for future use (ignored today).
 * @returns {string}
 */
export function formatCurrency (amount, options) {
  const numeric = Number(amount)
  const safe = Number.isFinite(numeric) ? numeric : 0
  return PEN_FORMATTER.format(safe)
}

/**
 * Backwards-compatible alias for `formatCurrency`. Preserves the
 * PR1 call sites in DashboardPage.vue / SessionList.vue that import
 * `formatPENLabel` by name. New code (PR-pagos-01 and later) MUST
 * import `formatCurrency` instead.
 *
 * @param {number|string|null|undefined} amount
 * @returns {string}
 */
export const formatPENLabel = formatCurrency
