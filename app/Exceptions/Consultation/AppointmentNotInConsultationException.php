<?php

namespace App\Exceptions\Consultation;

class AppointmentNotInConsultationException extends ConsultationException
{
    public static function make(string $currentStatus): self
    {
        return new self(
            "La cita no está en consulta (estado actual: {$currentStatus}). Inicie la consulta antes de cerrarla.",
            'appointment_not_in_consultation',
            409,
            ['current_status' => $currentStatus],
        );
    }
}
