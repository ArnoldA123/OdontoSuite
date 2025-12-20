<?php

namespace App\Events;

use App\Models\CashMovement;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CashMovementCreated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $movement;
    public $sessionId;

    public function __construct(CashMovement $movement, $sessionId = null)
    {
        $this->movement = $movement;
        $this->sessionId = $sessionId ?? $movement->cash_register_session_id;
    }

    public function broadcastOn()
    {
        $channels = [
            new Channel('cash-register'),
        ];

        // Canal privado para la sesión específica
        if ($this->sessionId) {
            $channels[] = new PrivateChannel("cash-session.{$this->sessionId}");
        }

        return $channels;
    }

    public function broadcastAs()
    {
        return 'cash-movement.created';
    }

    public function broadcastWith()
    {
        return [
            'movement' => $this->movement->load('cashRegisterSession'),
            'session_id' => $this->sessionId,
        ];
    }
}

