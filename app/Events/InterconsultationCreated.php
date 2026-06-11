<?php

namespace App\Events;

use App\Models\Interconsultation;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Sprint 0 fix (NF-2): este evento no tiene listener registrado en AppServiceProvider. Si implementa ShouldBroadcast, igual intenta transmitir por Reverb (sin consumidor registrado en frontend por defecto). Sprint 3 lo cablea correctamente (ver plan-mejoras-futuras-2026-06.md).
 */
class InterconsultationCreated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $interconsultation;

    public function __construct(Interconsultation $interconsultation)
    {
        $this->interconsultation = $interconsultation;
    }

    public function broadcastOn()
    {
        $channels = [
            new Channel('interconsultations'),
        ];

        // Canal privado para el profesional destinatario
        if ($this->interconsultation->to_specialist_id) {
            $channels[] = new PrivateChannel("user.{$this->interconsultation->to_specialist_id}");
        }

        return $channels;
    }

    public function broadcastAs()
    {
        return 'interconsultation.created';
    }

    public function broadcastWith()
    {
        return [
            'interconsultation' => $this->interconsultation->load('patient', 'fromSpecialist', 'toSpecialist'),
        ];
    }
}

