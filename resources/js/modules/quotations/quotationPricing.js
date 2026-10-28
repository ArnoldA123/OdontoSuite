/**
 * #414 S1 — the arithmetic of an estimate, mirrored from the server.
 *
 * `App\Support\QuotationTax` is the single implementation of the IGV policy and
 * `App\Services\QuotationService` is the single writer of a document's amounts.
 * The manual form must paint those same numbers, so this module reproduces both
 * rules without a Vue dependency: the rate that actually applies, the breakdown
 * of the gross amount, and the name of the tariff row that priced a line.
 *
 * The catalog and plan price is the price the patient pays, so the rate is
 * decomposed out of the amount instead of being stacked on top of it.
 */

/**
 * Mirror of `config/billing.php` → `tax.default_percentage`, which is what
 * `QuotationTax::defaultPercentage()` falls back to. The live value arrives with
 * the pricing context; this one only covers its absence.
 */
export const FALLBACK_TAX_PERCENTAGE = 18

/**
 * The origin of a resolved price, as the cascade in `ProcedurePriceResolver`
 * names it.
 */
export const PRICE_SOURCE_LABELS = {
  catalog: 'Catálogo',
  branch: 'Sede',
  payer: 'Convenio',
  payer_branch: 'Convenio y sede'
}

/**
 * Round to cents the way PHP's `round()` does: half away from zero. A fixed
 * decimal step first removes the binary representation error that would turn a
 * legitimate half cent into a lost one.
 */
const roundMoney = amount => {
  const cents = Number((Number(amount) * 100).toFixed(6))
  const rounded = (Math.sign(cents) * Math.round(Math.abs(cents))) / 100

  return rounded === 0 ? 0 : rounded
}

/**
 * The rate the document will be written with.
 *
 * An empty declaration is not a zero: it means "use the billing policy", which
 * is what the server does when the payload omits `tax_percentage`. A declared
 * zero is the exempt mode and stays a zero.
 */
export function effectiveTaxRate(declared, policy) {
  const fallback =
    Number.isFinite(Number(policy)) && policy !== null && policy !== ''
      ? Number(policy)
      : FALLBACK_TAX_PERCENTAGE

  if (declared === null || declared === undefined || declared === '') {
    return fallback
  }

  const rate = Number(declared)

  return Number.isFinite(rate) ? rate : fallback
}

/**
 * Split a gross amount into base imponible plus IGV, exactly like
 * `QuotationTax::breakDown()`:
 *
 *     net   = round(gross − discount, 2)
 *     tax   = rate > 0 ? round(net − round(net / (1 + rate / 100), 2), 2) : 0
 *     base  = round(net − tax, 2)
 *     total = net
 *
 * The total is what the patient owes and never moves because of the rate.
 */
export function breakDown(gross, discountAmount, rate) {
  const net = roundMoney(Number(gross) - Number(discountAmount))
  const percentage = Math.max(0, Number(rate) || 0)

  const taxAmount = percentage > 0 ? roundMoney(net - roundMoney(net / (1 + percentage / 100))) : 0

  return {
    subtotal: roundMoney(net - taxAmount),
    taxAmount,
    totalAmount: net
  }
}

/**
 * Spanish copy for the tariff row that priced a line; empty when the line
 * carries no tariff evidence (a free-text line).
 */
export function priceSourceLabel(source) {
  return PRICE_SOURCE_LABELS[source] ?? ''
}
