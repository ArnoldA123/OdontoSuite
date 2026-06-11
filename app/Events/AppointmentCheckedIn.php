<?php

namespace App\Events;

use App\Models\Appointment;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Sprint 0 fix (NF-2): este evento no tiene listener registrado en AppServiceProvider. Si implementa ShouldBroadcast, igual intenta transmitir por Reverb (sin consumidor registrado en frontend por defecto). Sprint 3 lo cablea correctamente (ver plan-mejoras-futuras-2026-06.md).
 */
class AppointmentCheckedIn implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public Appointment $appointment)
    {
    }

    public function broadcastOn(): array
    {
        return [
            new Channel('appointments'),
            new Channel('dashboard-updates'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'appointment.checked_in';
    }

    public function broadcastWith(): array
    {
        return [
            'appointment' => $this->appointment->loadMissing('patient', 'user', 'appointmentType', 'dentalChair'),
            'checked_in_at' => $this->appointment->checked_in_at?->toIso8601String(),
        ];
    }
}
