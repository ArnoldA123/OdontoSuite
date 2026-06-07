<?php

namespace App\Exceptions\Consultation;

class InvalidTreatmentPlanException extends ConsultationException
{
    public static function notFound(?int $planId): self
    {
        return new self(
            "El plan de tratamiento {$planId} no existe o no pertenece al paciente.",
            'invalid_treatment_plan',
            422,
            ['plan_id' => $planId],
        );
    }

    public static function requiresPlanId(): self
    {
        return new self(
            'Para el modo "plan_session" debe especificar el plan de tratamiento a avanzar.',
            'invalid_treatment_plan',
            422,
            ['required_field' => 'treatment_plan.id'],
        );
    }
}
