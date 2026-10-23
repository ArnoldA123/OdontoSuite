<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Sprint 0 fix (NF-2): este evento no tiene listener registrado en AppServiceProvider. Si implementa ShouldBroadcast, igual intenta transmitir por Reverb (sin consumidor registrado en frontend por defecto). Sprint 3 lo cablea correctamente (ver plan-mejoras-futuras-2026-06.md).
 *
 * F5 (#313, D3): el registro de especialidad es narrativa clínica —notas
 * quirúrgicas, complicaciones y seguimiento—, así que viaja sólo por
 * `specialty-records`. `dashboard-updates` autoriza a los siete roles y un
 * canal no puede variar su payload por rol, de modo que el registro completo
 * no cruza al escritorio no clínico.
 */
class SpecialtyRecordCreated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $record;

    public $specialty;

    public function __construct($record, string $specialty)
    {
        $this->record = $record;
        $this->specialty = $specialty;
    }

    public function broadcastOn()
    {
        return [
            new PrivateChannel('specialty-records'),
        ];
    }

    public function broadcastAs()
    {
        return 'specialty-record.created';
    }

    public function broadcastWith()
    {
        return [
            'record' => $this->record,
            'specialty' => $this->specialty,
        ];
    }
}
