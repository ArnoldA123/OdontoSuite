<?php

namespace App\Support;

use Carbon\Carbon;
use Throwable;

/**
 * Normalizes the `date_from`/`date_to` query filters.
 *
 * The list endpoints used to hand the raw string to `whereDate`; under MySQL
 * strict mode a value such as `abc` is a 500 instead of an ignored filter. The
 * contract treats every non-parseable value the same way it already treats a
 * blank one: the filter does not apply.
 */
final class DateFilter
{
    public static function normalize(mixed $value): ?string
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return Carbon::parse(trim($value))->toDateString();
        } catch (Throwable) {
            return null;
        }
    }
}
