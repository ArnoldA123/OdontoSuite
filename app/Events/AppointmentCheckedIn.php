<?php

namespace App\Events;

use App\Models\Appointment;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Slice 10 (T-10.4): broadcast moved to a PrivateChannel to avoid leaking
 * patient appointment data on a public channel (BF-018). Authorization for
 * the private channel lives in routes/channels.php.
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
            new PrivateChannel('private-appointment.' . $this->appointment->id),
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
