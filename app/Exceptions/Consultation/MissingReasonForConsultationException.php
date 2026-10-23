<?php

namespace App\Exceptions\Consultation;

/**
 * F6 (#286) — NTS 139 asks for the reason of the consultation on every act.
 */
class MissingReasonForConsultationException extends ConsultationException
{
    public function __construct()
    {
        parent::__construct(
            'El motivo de consulta es obligatorio para cerrar la atención.',
            'missing_reason_for_consultation',
            422,
            ['required_fields' => ['reason_for_consultation']],
        );
    }
}
