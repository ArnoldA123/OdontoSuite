<?php

namespace App\Events;

use App\Models\Appointment;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AppointmentUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $appointment;

    public $oldValues;

    public function __construct(Appointment $appointment, array $oldValues = [])
    {
        $this->appointment = $appointment;
        $this->oldValues = $oldValues;
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
        return 'appointment.updated';
    }

    public function broadcastWith()
    {
        return [
            'appointment' => $this->appointment->load('patient', 'user', 'appointmentType', 'dentalChair'),
        ];
    }
}
