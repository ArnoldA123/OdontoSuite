<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PatientDeleted implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $patientId;
    public $oldValues;

    public function __construct(int $patientId, array $oldValues = [])
    {
        $this->patientId = $patientId;
        $this->oldValues = $oldValues;
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
        return 'patient.deleted';
    }

    public function broadcastWith()
    {
        return [
            'patient_id' => $this->patientId,
        ];
    }
}

