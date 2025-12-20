<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AppointmentDeleted implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $appointmentId;
    public $oldValues;
    public $appointment;

    public function __construct($appointmentId, array $oldValues = [], $appointment = null)
    {
        $this->appointmentId = $appointmentId;
        $this->oldValues = $oldValues;
        $this->appointment = $appointment;
    }

    public function broadcastOn()
    {
        return [
            new Channel('appointments'),
            new Channel('dashboard-updates'),
        ];
    }

    public function broadcastAs()
    {
        return 'appointment.deleted';
    }

    public function broadcastWith()
    {
        return [
            'appointment_id' => $this->appointmentId,
        ];
    }
}

