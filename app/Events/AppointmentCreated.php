<?php

namespace App\Events;

use App\Models\Appointment;
use App\Support\AppointmentBroadcastPayload;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AppointmentCreated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $appointment;

    public function __construct(Appointment $appointment)
    {
        $this->appointment = $appointment;
    }

    public function broadcastOn()
    {
        return [
            new PrivateChannel('appointments'),
            new PrivateChannel('dashboard-updates'),
        ];
    }

    public function broadcastAs()
    {
        return 'appointment.created';
    }

    public function broadcastWith()
    {
        return [
            'appointment' => AppointmentBroadcastPayload::for($this->appointment),
        ];
    }
}
