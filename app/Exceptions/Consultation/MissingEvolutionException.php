<?php

namespace App\Exceptions\Consultation;

class MissingEvolutionException extends ConsultationException
{
    public function __construct()
    {
        parent::__construct(
            'La evolución clínica (SOAP) es obligatoria para cerrar la consulta.',
            'missing_evolution',
            422,
            ['required_fields' => ['subjective', 'objective', 'assessment', 'plan']],
        );
    }
}
