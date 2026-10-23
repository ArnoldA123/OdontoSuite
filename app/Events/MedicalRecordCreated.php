<?php

namespace App\Events;

use App\Models\MedicalRecord;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Sprint 0 fix (NF-2): este evento no tiene listener registrado en AppServiceProvider. Si implementa ShouldBroadcast, igual intenta transmitir por Reverb (sin consumidor registrado en frontend por defecto). Sprint 3 lo cablea correctamente (ver plan-mejoras-futuras-2026-06.md).
 *
 * F5 (#315, D3): el payload es el documento clínico, así que viaja sólo por
 * `medical-records`. `dashboard-updates` autoriza a los siete roles y un canal
 * no puede variar su payload por rol: cualquier suscriptor recibe el mismo
 * mensaje, incluido el escritorio de laboratorio. El panel refresca sus
 * contadores con los eventos de agenda, pacientes y dinero, que no llevan
 * narrativa clínica.
 */
class MedicalRecordCreated implements ShouldBroadcast
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
            new PrivateChannel('medical-records'),
        ];
    }

    public function broadcastAs()
    {
        return 'medical-record.created';
    }

    public function broadcastWith()
    {
        return [
            'medical_record' => $this->medicalRecord->load('patient', 'createdBy'),
        ];
    }
}
