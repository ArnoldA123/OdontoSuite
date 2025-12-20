<?php

namespace App\Events;

use App\Models\TreatmentPlan;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TreatmentPlanCreated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $treatmentPlan;

    public function __construct(TreatmentPlan $treatmentPlan)
    {
        $this->treatmentPlan = $treatmentPlan;
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
        return 'treatment-plan.created';
    }

    public function broadcastWith()
    {
        return [
            'treatment_plan' => $this->treatmentPlan->load('patient', 'createdBy', 'items'),
        ];
    }
}

