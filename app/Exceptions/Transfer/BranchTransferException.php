<?php

namespace App\Exceptions\Transfer;

use RuntimeException;

/**
 * #305 — the refusals of the branch transfer flow.
 *
 * The four rules of the request (one open row per user, origin still matching
 * the affiliation, active destination, pending state) are conflicts with the
 * current state, not malformed payloads: the payload was valid when it was
 * sent. They answer 409 and carry their own status so the controller never
 * turns a domain refusal into a 500.
 */
class BranchTransferException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly int $httpStatus = 409,
    ) {
        parent::__construct($message);
    }

    public static function alreadyOpen(): self
    {
        return new self('Ya tiene una solicitud de traslado en curso.');
    }

    public static function alreadyResolved(): self
    {
        return new self('La solicitud ya fue resuelta.');
    }

    public static function originMismatch(): self
    {
        return new self('La sede de origen de la solicitud ya no coincide con su afiliación vigente.');
    }

    public static function destinationInactive(): self
    {
        return new self('La sede de destino ya no está activa.');
    }
}
