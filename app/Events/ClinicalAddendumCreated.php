<?php

namespace App\Events;

use App\Models\ClinicalAddendum;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * F3 (#292) — a correction was added to a clinical history.
 *
 * It mirrors ClinicalEvolutionCreated so the open timeline can append the note
 * next to the original without a manual refresh. The payload carries the record
 * and patient ids because the client filters events by the record it is showing.
 */
class ClinicalAddendumCreated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $addendum;

    public function __construct(ClinicalAddendum $addendum)
    {
        $this->addendum = $addendum;
    }

    public function broadcastOn()
    {
        return [
            new PrivateChannel('medical-records'),
        ];
    }

    public function broadcastAs()
    {
        return 'clinical-addendum.created';
    }

    public function broadcastWith()
    {
        $addendum = $this->addendum->load('author', 'medicalRecord');

        return [
            'addendum' => $addendum,
            'medical_record_id' => $addendum->medical_record_id,
            'patient_id' => $addendum->medicalRecord?->patient_id,
        ];
    }
}
