<?php

namespace App\Events;

use App\Models\MedicalRecord;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Sprint 0 fix (NF-2): este evento no tiene listener registrado en AppServiceProvider. Si implementa ShouldBroadcast, igual intenta transmitir por Reverb (sin consumidor registrado en frontend por defecto). Sprint 3 lo cablea correctamente (ver plan-mejoras-futuras-2026-06.md).
 */
class MedicalRecordUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $medicalRecord;

    public function __construct(MedicalRecord $medicalRecord)
    {
        $this->medicalRecord = $medicalRecord;
    }

    public function broadcastOn()
    {
        return [
            new Channel('medical-records'),
            new Channel('dashboard-updates'),
        ];
    }

    public function broadcastAs()
    {
        return 'medical-record.updated';
    }

    public function broadcastWith()
    {
        return [
            'medical_record' => $this->medicalRecord->load('patient', 'createdBy', 'evolutions'),
        ];
    }
}

