<?php

namespace App\Exceptions\Consultation;

/**
 * F2 (#282) — a follow-up appointment needs the schedule row that holds it.
 *
 * `appointments` requires a chair and an appointment type, and an urgency act
 * has neither. Continuity for an act without an appointment belongs to F4
 * (#284), so the closure refuses the block instead of inventing an agenda row.
 */
class NextAppointmentRequiresAppointmentException extends ConsultationException
{
    public static function make(): self
    {
        return new self(
            'La próxima cita solo se agenda al cerrar la atención de una cita; una atención sin cita no inventa fila de agenda.',
            'next_appointment_requires_appointment',
            422,
        );
    }
}
