<?php

namespace App\Support;

use App\Models\Appointment;

/**
 * citas-privacidad (T1) — the appointment payload a domain event publishes.
 *
 * One payload reaches every desk subscribed to `appointments`,
 * `appointment.{id}` and `dashboard-updates`, and `dashboard-updates`
 * authorizes all seven roles. Publishing the appointment model meant the
 * patient's name, document, phone and the clinical conduct travelled to desks
 * that only need to know that something moved.
 *
 * The payload is reduced to what a consumer needs to react: which appointment,
 * whose agenda it is, its status and when. Everything else is read from the
 * API with the caller's own permissions, which is the only place the read
 * boundary can be applied.
 */
final class AppointmentBroadcastPayload
{
    /**
     * @return array{id: int|null, user_id: int|null, status: string|null, scheduled_at: string|null, updated_at: string|null}
     */
    public static function for(Appointment $appointment): array
    {
        return [
            'id' => $appointment->id,
            'user_id' => $appointment->user_id,
            'status' => $appointment->status,
            'scheduled_at' => $appointment->scheduled_at?->toIso8601String(),
            'updated_at' => $appointment->updated_at?->toIso8601String(),
        ];
    }
}
