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
class TreatmentPlanDeleted implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $treatmentPlanId;
    public $patientId;

    public function __construct(int $treatmentPlanId, int $patientId)
    {
        $this->treatmentPlanId = $treatmentPlanId;
        $this->patientId = $patientId;
    }

    public function broadcastOn()
    {
        return [
            new Channel('treatment-plans'),
            new Channel('dashboard-updates'),
        ];
    }

    public function broadcastAs()
    {
        return 'treatment-plan.deleted';
    }

    public function broadcastWith()
    {
        return [
            'treatment_plan_id' => $this->treatmentPlanId,
            'patient_id' => $this->patientId,
        ];
    }
}

