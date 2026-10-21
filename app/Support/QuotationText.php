<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Single source of truth for the free-text hygiene of a quotation.
 *
 * `store` and `update` share these caps, so the same input can never be
 * persisted in two different shapes depending on the endpoint. Terms and notes
 * are plain text: the PDF escapes them before rendering, so markup is dropped
 * here instead of being stored and inherited by every future consumer.
 */
final class QuotationText
{
    public const TERMS_LIMIT = 2000;

    public const NOTES_LIMIT = 1000;

    private const REJECTION_PREFIX = "\n\nRechazado: ";

    public static function sanitizeTerms(?string $value): ?string
    {
        return self::sanitize($value, self::TERMS_LIMIT);
    }

    public static function sanitizeNotes(?string $value): ?string
    {
        return self::sanitize($value, self::NOTES_LIMIT);
    }

    /**
     * Append the rejection reason without ever dropping it: the base note is
     * trimmed to whatever room the reason leaves inside the notes cap.
     */
    public static function appendRejection(?string $notes, string $reason): string
    {
        $suffix = self::REJECTION_PREFIX.strip_tags($reason);
        $base = self::sanitizeNotes($notes) ?? '';
        $room = max(0, self::NOTES_LIMIT - Str::length($suffix));

        return trim(Str::limit($base, $room, '').$suffix);
    }

    private static function sanitize(?string $value, int $limit): ?string
    {
        if ($value === null) {
            return null;
        }

        $plain = trim(strip_tags($value));

        if ($plain === '') {
            return null;
        }

        return Str::limit($plain, $limit, '');
    }
}
