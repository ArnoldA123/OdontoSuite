<?php

namespace App\Http\Requests\Concerns;

/**
 * LocalizedErrors trait — place to add common Spanish (es) error markers.
 *
 * Slice 02 / T-02.13 — FormRequests must return meta.locale = 'es' in
 * validation envelopes. The contract is enforced by per-FormRequest
 * assertions; this trait centralizes any cross-cutting behavior so each
 * FormRequest only declares its field-specific rules + messages.
 *
 * NOTE: meta.locale is normally added by the controller's response envelope;
 * the trait itself does not mutate the response. It exists as a single
 * reference point so future i18n switches don't need to grep every
 * FormRequest.
 */
trait LocalizedErrors
{
    /**
     * Return the locale code this FormRequest reports for error envelopes.
     */
    public function responseLocale(): string
    {
        return 'es';
    }
}
