<?php

namespace App\Events;

use App\Models\ClinicalEvolution;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Emitted after an evolution is soft-deleted (issue #223).
 *
 * The payload is flat and carries `medical_record_id` and `patient_id` on
 * purpose: the client has to decide whether the deleted row belongs to the
 * history and patient it is showing before it refreshes anything.
 */
class ClinicalEvolutionDeleted implements ShouldBroadcast
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
        return 'clinical-evolution.deleted';
    }

    public function broadcastWith()
    {
        return [
            'evolution_id' => $this->evolution->id,
            'medical_record_id' => $this->evolution->medical_record_id,
            'patient_id' => $this->evolution->patient_id,
        ];
    }
}
