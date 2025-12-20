<?php

namespace App\Events;

use App\Models\CashRegisterSession;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

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

