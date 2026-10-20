<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
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

    /**
     * Canal privado: el evento de borrado solo expone el id, pero el canal
     * es el mismo que el de alta/edición y vive detrás del mismo handshake.
     */
    public function broadcastOn()
    {
        return [
            new PrivateChannel('patients'),
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
