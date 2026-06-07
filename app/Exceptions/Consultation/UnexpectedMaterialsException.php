<?php

namespace App\Exceptions\Consultation;

class UnexpectedMaterialsException extends ConsultationException
{
    public function __construct()
    {
        parent::__construct(
            'En modo "consultation" no se esperan materiales. Use "skip_materials" = true o cámbielo a modo "execution"/"plan_session".',
            'unexpected_materials',
            422,
        );
    }
}
