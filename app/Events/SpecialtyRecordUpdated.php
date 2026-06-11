<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Sprint 0 fix (NF-2): este evento no tiene listener registrado en AppServiceProvider. Si implementa ShouldBroadcast, igual intenta transmitir por Reverb (sin consumidor registrado en frontend por defecto). Sprint 3 lo cablea correctamente (ver plan-mejoras-futuras-2026-06.md).
 */
class SpecialtyRecordUpdated implements ShouldBroadcast
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
            new Channel('specialty-records'),
            new Channel('dashboard-updates'),
        ];
    }

    public function broadcastAs()
    {
        return 'specialty-record.updated';
    }

    public function broadcastWith()
    {
        return [
            'record' => $this->record,
            'specialty' => $this->specialty,
        ];
    }
}

