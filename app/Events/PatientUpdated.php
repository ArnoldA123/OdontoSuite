<?php

namespace App\Events;

use App\Http\Resources\PatientResource;
use App\Models\Patient;
use Illuminate\Broadcasting\PrivateChannel;
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

    /**
     * Canal privado: el payload lleva datos clínicos del paciente.
     */
    public function broadcastOn()
    {
        return [
            new PrivateChannel('patients'),
        ];
    }

    public function broadcastAs()
    {
        return 'patient.updated';
    }

    public function broadcastWith()
    {
        return [
            'patient' => PatientResource::make($this->patient)->resolve(),
        ];
    }
}

