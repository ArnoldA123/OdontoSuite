<?php

/**
 * Billing policy (#289).
 *
 * `tax.default_percentage` is the rate a new quotation declares when the caller
 * does not send one. The catalog price is the price the patient pays, so the
 * rate never adds money to the quotation: it only splits the amount into base
 * imponible plus IGV. See `App\Support\QuotationTax` and ADR-0011.
 */
return [
    'tax' => [
        'default_percentage' => (float) env('BILLING_TAX_PERCENTAGE', 18.00),
    ],
];
