<?php

namespace App\Exceptions\Consultation;

/**
 * F2 (#282) — S7: an appointment is `completed` only with a closed act.
 *
 * The closure closes the encounter and then projects the status on the
 * appointment, so this guard can only fire if that order was broken. It is the
 * runtime half of the rule; the other half is that no other door accepts
 * `completed`.
 */
class MissingClosedEncounterException extends ConsultationException
{
    public static function make(): self
    {
        return new self(
            'La cita no puede quedar completada sin una atención cerrada.',
            'appointment_without_closed_encounter',
            500,
        );
    }
}
