<?php

namespace App\Events;

use App\Models\Appointment;
use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * F1 (#281) — `paciente_listo`.
 *
 * Reception announces the patient and this event tells the professional's
 * desk. Announcing does not create state (S2): the appointment stays `arrived`
 * until the professional starts the attention. The payload carries patient
 * data, so it travels on the professional's private channel (`user.{id}`),
 * which only that user can subscribe to.
 */
class PatientReady implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Appointment $appointment,
        public User $announcedBy,
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('user.'.$this->appointment->user_id),
        ];
    }

    public function broadcastAs(): string
    {
        return 'paciente_listo';
    }

    public function broadcastWith(): array
    {
        $appointment = $this->appointment;
        $patient = $appointment->patient;
        $professional = $appointment->user;

        return [
            'appointment' => [
                'id' => $appointment->id,
                'status' => $appointment->status,
                'scheduled_at' => $appointment->scheduled_at?->toIso8601String(),
                'duration_minutes' => $appointment->duration_minutes,
                'patient_id' => $appointment->patient_id,
                'user_id' => $appointment->user_id,
                'dental_chair_id' => $appointment->dental_chair_id,
                'appointment_type_id' => $appointment->appointment_type_id,
                'checked_in_at' => $appointment->checked_in_at?->toIso8601String(),
                'checked_in_by' => $appointment->checked_in_by,
            ],
            'patient' => [
                'id' => $patient?->id,
                'full_name' => trim(($patient?->first_name ?? '').' '.($patient?->last_name ?? '')),
            ],
            'professional' => [
                'id' => $appointment->user_id,
                'name' => $professional?->name,
            ],
            'announced_by' => [
                'id' => $this->announcedBy->id,
                'name' => $this->announcedBy->name,
            ],
            'announced_at' => now()->toIso8601String(),
        ];
    }
}
