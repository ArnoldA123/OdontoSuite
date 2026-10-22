<?php

namespace App\Support;

/**
 * #289, S1 — the IGV policy of a quotation.
 *
 * The catalog and plan price is the price the patient pays, so the tax is
 * contained in it: the breakdown decomposes the rate out of the gross amount
 * instead of stacking it on top. One implementation for every writer, so the
 * manual door (`QuotationService`) and the closure door (`BillingService`)
 * cannot drift apart.
 */
final class QuotationTax
{
    /**
     * Rate a new quotation declares when the caller sends none.
     */
    public static function defaultPercentage(): float
    {
        return (float) config('billing.tax.default_percentage', 18.00);
    }

    /**
     * Split a gross amount into base imponible plus IGV.
     *
     * `total_amount` is the net amount the patient pays: the gross minus the
     * discount. The rate only decides how much of it is tax, so declaring a rate
     * never changes what the patient owes.
     *
     * @return array{subtotal: float, tax_amount: float, total_amount: float}
     */
    public static function breakDown(float $gross, float $discountAmount, float $percentage): array
    {
        $net = round($gross - $discountAmount, 2);
        $rate = max(0.0, $percentage);

        $taxAmount = $rate > 0
            ? round($net - round($net / (1 + $rate / 100), 2), 2)
            : 0.0;

        return [
            'subtotal' => round($net - $taxAmount, 2),
            'tax_amount' => $taxAmount,
            'total_amount' => $net,
        ];
    }
}
