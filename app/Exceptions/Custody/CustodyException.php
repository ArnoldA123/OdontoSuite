<?php

namespace App\Exceptions\Custody;

use RuntimeException;

/**
 * F1 (#281) — the custody flow refused an act because the appointment is not
 * in the status that door expects. Mirrors the ConsultationException envelope
 * (`error.error_code` + `error.message`) so the SPA reads one shape.
 */
class CustodyException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly string $errorCode,
        public readonly int $httpStatus = 409,
        public readonly array $context = [],
    ) {
        parent::__construct($message);
    }

    public static function arrivalNotAllowed(string $currentStatus): self
    {
        return new self(
            "No se puede registrar la llegada de una cita en estado {$currentStatus}.",
            'arrival_not_allowed',
            409,
            ['current_status' => $currentStatus],
        );
    }

    public static function attentionNotAllowed(string $currentStatus): self
    {
        return new self(
            "La atención solo se inicia cuando la llegada está registrada (estado actual: {$currentStatus}).",
            'attention_not_allowed',
            409,
            ['current_status' => $currentStatus],
        );
    }

    public static function announcementNotAllowed(string $currentStatus): self
    {
        return new self(
            "El paciente aún no tiene la llegada registrada (estado actual: {$currentStatus}).",
            'announcement_not_allowed',
            409,
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
