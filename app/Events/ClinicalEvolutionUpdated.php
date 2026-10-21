<?php

namespace App\Events;

use App\Models\ClinicalEvolution;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Emitted when an existing evolution is edited (issue #223). Mirrors
 * ClinicalEvolutionCreated so every client can refresh the timeline of the
 * patient it is showing.
 */
class ClinicalEvolutionUpdated implements ShouldBroadcast
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
            new PrivateChannel('medical-records'),
        ];
    }

    public function broadcastAs()
    {
        return 'clinical-evolution.updated';
    }

    public function broadcastWith()
    {
        return [
            'evolution' => $this->evolution->load('medicalRecord.patient', 'createdBy'),
        ];
    }
}
