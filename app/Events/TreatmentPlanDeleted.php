<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

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

