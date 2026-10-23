<?php

namespace App\Support;

use App\Models\Appointment;
use Illuminate\Support\Arr;

/**
 * F5 (#314, D2) — the appointment payload a domain event publishes.
 *
 * One payload reaches every desk subscribed to `appointments`,
 * `appointment.{id}` and `dashboard-updates`, so the field that copies the
 * clinical conduct (`treatment_notes`) never travels: reception and the
 * laboratory read the agenda, not the evolution. Each appointment event adds
 * its own operational keys on top of this array.
 */
final class AppointmentBroadcastPayload
{
    /**
     * The relations the appointment events already published.
     *
     * @var list<string>
     */
    private const RELATIONS = ['patient', 'user', 'appointmentType', 'dentalChair'];

    /**
     * @param  list<string>         $relations extra relations the event already loaded
     * @return array<string, mixed>
     */
    public static function for(Appointment $appointment, array $relations = []): array
    {
        $appointment->loadMissing([...self::RELATIONS, ...$relations]);

        return Arr::except($appointment->toArray(), ['treatment_notes']);
    }
}
