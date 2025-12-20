<?php

namespace App\Events;

use App\Models\Patient;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PatientCreated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $patient;

    public function __construct(Patient $patient)
    {
        $this->patient = $patient;
    }

    public function broadcastOn()
    {
        return [
            new Channel('patients'),
            new Channel('dashboard-updates'),
        ];
    }

    public function broadcastAs()
    {
        return 'patient.created';
    }

    public function broadcastWith()
    {
        return [
            'patient' => $this->patient,
        ];
    }
}

