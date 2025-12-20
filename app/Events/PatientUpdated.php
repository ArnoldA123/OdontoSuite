<?php

namespace App\Events;

use App\Models\Patient;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PatientUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $patient;
    public $oldValues;

    public function __construct(Patient $patient, array $oldValues = [])
    {
        $this->patient = $patient;
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
        return 'patient.updated';
    }

    public function broadcastWith()
    {
        return [
            'patient' => $this->patient->load('appointments', 'waitingLists'),
        ];
    }
}

