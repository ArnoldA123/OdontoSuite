<?php

namespace App\Events;

use App\Models\CashRegisterSession;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Sprint 0 fix (NF-2): este evento no tiene listener registrado en AppServiceProvider. Si implementa ShouldBroadcast, igual intenta transmitir por Reverb (sin consumidor registrado en frontend por defecto). Sprint 3 lo cablea correctamente (ver plan-mejoras-futuras-2026-06.md).
 */
class CashSessionClosed implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $session;

    public function __construct(CashRegisterSession $session)
    {
        $this->session = $session;
    }

    public function broadcastOn()
    {
        return [
            new Channel('cash-register'),
            new Channel('dashboard-updates'),
        ];
    }

    public function broadcastAs()
    {
        return 'cash-session.closed';
    }

    public function broadcastWith()
    {
        return [
            'session' => $this->session->load('user'),
        ];
    }
}

