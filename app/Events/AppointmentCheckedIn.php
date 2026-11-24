<?php

namespace App\Events;

use App\Models\Appointment;
use App\Support\AppointmentBroadcastPayload;
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

    public function __construct(public Appointment $appointment) {}

    /**
     * citas-privacidad (T2): the arrival also travels on `appointments`, the
     * channel the agenda and Mi día read, so both boards refresh in real time.
     * The envelope stays operational; the patient is read from the API.
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('appointment.'.$this->appointment->id),
            new PrivateChannel('appointments'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'appointment.checked_in';
    }

    public function broadcastWith(): array
    {
        return [
            'appointment' => AppointmentBroadcastPayload::for($this->appointment),
        ];
    }
}
