<?php

namespace App\Events;

use App\Models\ClinicalAttachment;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ClinicalAttachmentCreated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $attachment;

    public function __construct(ClinicalAttachment $attachment)
    {
        $this->attachment = $attachment;
    }

    public function broadcastOn()
    {
        return [
            new Channel('medical-records'),
        ];
    }

    public function broadcastAs()
    {
        return 'clinical-attachment.created';
    }

    public function broadcastWith()
    {
        return [
            'attachment' => $this->attachment->load('patient', 'clinicalEvolution.medicalRecord'),
        ];
    }
}

