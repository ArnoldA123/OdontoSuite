<?php

namespace App\Exceptions\Consultation;

/**
 * F6 (#286) — NTS 139 asks for the diagnosis with its CIE-10 code.
 */
class MissingDiagnosisException extends ConsultationException
{
    public function __construct()
    {
        parent::__construct(
            'El diagnóstico codificado (CIE-10) es obligatorio para cerrar la atención.',
            'missing_diagnosis',
            422,
            ['required_fields' => ['diagnoses']],
        );
    }
}
