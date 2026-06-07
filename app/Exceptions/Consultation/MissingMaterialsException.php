<?php

namespace App\Exceptions\Consultation;

class MissingMaterialsException extends ConsultationException
{
    public function __construct(string $reason)
    {
        parent::__construct(
            "Materiales requeridos: {$reason}",
            'missing_materials',
            422,
            ['reason' => $reason],
        );
    }
}
