<?php

namespace App\Events;

use App\Http\Resources\PatientResource;
use App\Models\Patient;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
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

    /**
     * Canal privado: el payload lleva datos clínicos del paciente
     * (medical_history, allergies), así que la suscripción exige el
     * handshake de /api/broadcasting/auth.
     */
    public function broadcastOn()
    {
        return [
            new PrivateChannel('patients'),
        ];
    }

    public function broadcastAs()
    {
        return 'patient.created';
    }

    public function broadcastWith()
    {
        return [
            'patient' => PatientResource::make($this->patient)->resolve(),
        ];
    }
}
