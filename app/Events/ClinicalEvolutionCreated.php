<?php

namespace App\Events;

use App\Models\ClinicalEvolution;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Sprint 0 fix (NF-2): este evento no tiene listener registrado en AppServiceProvider. Si implementa ShouldBroadcast, igual intenta transmitir por Reverb (sin consumidor registrado en frontend por defecto). Sprint 3 lo cablea correctamente (ver plan-mejoras-futuras-2026-06.md).
 */
class ClinicalEvolutionCreated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $evolution;

    public function __construct(ClinicalEvolution $evolution)
    {
        $this->evolution = $evolution;
    }

    public function broadcastOn()
    {
        return [
            new Channel('medical-records'),
        ];
    }

    public function broadcastAs()
    {
        return 'clinical-evolution.created';
    }

    public function broadcastWith()
    {
        return [
            'evolution' => $this->evolution->load('medicalRecord.patient', 'createdBy'),
        ];
    }
}

