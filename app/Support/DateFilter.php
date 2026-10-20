<?php

namespace App\Support;

use Carbon\Carbon;

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
    /**
     * Explicit formats only: `Carbon::parse` also accepts relative
     * expressions ("next monday", "tomorrow"), which would silently move the
     * window instead of being ignored like any other unusable value.
     */
    private const FORMATS = [
        'Y-m-d',
        'Y-m-d H:i:s',
        'Y-m-d\TH:i:sP',
        'Y-m-d\TH:i:s.uP',
    ];

    public static function normalize(mixed $value): ?string
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        $value = trim($value);

        foreach (self::FORMATS as $format) {
            if (Carbon::canBeCreatedFromFormat($value, $format)) {
                return Carbon::createFromFormat($format, $value)->toDateString();
            }
        }

        return null;
    }
}
