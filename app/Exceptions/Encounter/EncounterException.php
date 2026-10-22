<?php

namespace App\Exceptions\Encounter;

use RuntimeException;

/**
 * F2 (#282) — the encounter state machine refused the act.
 *
 * Mirrors the CustodyException envelope (`error.error_code` + `error.message`)
 * so the SPA reads one shape. A closed encounter holds clinical documentation
 * and is never erased (domain rule of #292); voiding is only reachable while
 * the act is still open.
 */
class EncounterException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly string $errorCode,
        public readonly int $httpStatus = 422,
        public readonly array $context = [],
    ) {
        parent::__construct($message);
    }

    public static function voidNotAllowed(string $currentStatus): self
    {
        return new self(
            "Solo se anula una atención abierta; no hay documentos clínicos que descartar (estado actual: {$currentStatus}).",
            'encounter_void_not_allowed',
            422,
            ['current_status' => $currentStatus],
        );
    }

    public static function alreadyClosed(string $currentStatus): self
    {
        return new self(
            "La atención ya está {$currentStatus}; el cierre se registra una sola vez.",
            'encounter_not_open',
            422,
            ['current_status' => $currentStatus],
        );
    }

    public function toArray(): array
    {
        return [
            'error_code' => $this->errorCode,
            'message' => $this->getMessage(),
            'context' => $this->context,
        ];
    }
}
