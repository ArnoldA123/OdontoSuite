<?php

namespace App\Exceptions\Consultation;

class InvalidConsultationModeException extends ConsultationException
{
    public static function make(?string $mode): self
    {
        return new self(
            "Modo de consulta inválido o ausente: " . ($mode ?? 'null'),
            'invalid_consultation_mode',
            422,
            ['provided_mode' => $mode, 'valid_modes' => ['consultation', 'execution', 'plan_session']],
        );
    }
}
