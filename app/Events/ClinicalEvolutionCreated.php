<?php

namespace App\Events;

use App\Models\ClinicalEvolution;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

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

