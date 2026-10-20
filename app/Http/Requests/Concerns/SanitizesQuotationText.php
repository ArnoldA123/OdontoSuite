<?php

namespace App\Http\Requests\Concerns;

use App\Support\QuotationText;

/**
 * Applies the shared quotation free-text hygiene before validation, so `store`
 * and `update` both persist the same sanitized value for the same input.
 */
trait SanitizesQuotationText
{
    protected function prepareForValidation(): void
    {
        if ($this->has('terms_conditions') && is_string($this->input('terms_conditions'))) {
            $this->merge([
                'terms_conditions' => QuotationText::sanitizeTerms($this->input('terms_conditions')),
            ]);
        }

        if ($this->has('notes') && is_string($this->input('notes'))) {
            $this->merge([
                'notes' => QuotationText::sanitizeNotes($this->input('notes')),
            ]);
        }
    }
}
