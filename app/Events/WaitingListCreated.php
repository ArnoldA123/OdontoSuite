<?php

namespace App\Events;

use App\Models\WaitingList;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Sprint 0 fix (NF-2): este evento no tiene listener registrado en AppServiceProvider. Si implementa ShouldBroadcast, igual intenta transmitir por Reverb (sin consumidor registrado en frontend por defecto). Sprint 3 lo cablea correctamente (ver plan-mejoras-futuras-2026-06.md).
 */
class WaitingListCreated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $waitingList;

    public function __construct(WaitingList $waitingList)
    {
        $this->waitingList = $waitingList;
    }

    public function broadcastOn()
    {
        return [
            new Channel('waiting-lists'),
            new Channel('appointments'),
        ];
    }

    public function broadcastAs()
    {
        return 'waiting-list.created';
    }

    public function broadcastWith()
    {
        return [
            'waiting_list' => $this->waitingList->load('patient', 'appointmentType', 'preferredUser'),
        ];
    }
}

