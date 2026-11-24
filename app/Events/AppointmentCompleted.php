<?php

namespace App\Events;

use App\Models\Appointment;
use App\Support\AppointmentBroadcastPayload;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AppointmentCompleted implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public Appointment $appointment) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('appointments'),
            new PrivateChannel('dashboard-updates'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'appointment.completed';
    }

    public function broadcastWith(): array
    {
        return [
            'appointment' => AppointmentBroadcastPayload::for($this->appointment),
        ];
    }
}
