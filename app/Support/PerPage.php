<?php

namespace App\Support;

/**
 * Single source for the `per_page` contract of the paginated list endpoints.
 *
 * `GET /api/ai-analysis` shipped the 1..100 clamp first; its sibling listings
 * kept a hard-coded `paginate(15)` and, once they accepted the parameter, three
 * different implementations of the same rule would drift apart.
 */
final class PerPage
{
    public const DEFAULT = 15;

    public const MAX = 100;

    public static function resolve(mixed $value): int
    {
        $perPage = is_numeric($value) ? (int) $value : self::DEFAULT;

        return max(1, min(self::MAX, $perPage));
    }
}
