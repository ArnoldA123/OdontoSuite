<?php

namespace App\Events;

use App\Models\ClinicalAttachment;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Sprint 0 fix (NF-2): este evento no tiene listener registrado en AppServiceProvider. Si implementa ShouldBroadcast, igual intenta transmitir por Reverb (sin consumidor registrado en frontend por defecto). Sprint 3 lo cablea correctamente (ver plan-mejoras-futuras-2026-06.md).
 */
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

