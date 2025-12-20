<?php

namespace App\Events;

use App\Models\Transaction;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PaymentRegistered implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $transaction;
    public $sessionId;

    public function __construct(Transaction $transaction, $sessionId = null)
    {
        $this->transaction = $transaction;
        $this->sessionId = $sessionId ?? $transaction->cash_register_session_id;
    }

    public function broadcastOn()
    {
        $channels = [
            new Channel('cash-register'),
            new Channel('dashboard-updates'),
        ];

        // Canal privado para la sesión específica
        if ($this->sessionId) {
            $channels[] = new PrivateChannel("cash-session.{$this->sessionId}");
        }

        return $channels;
    }

    public function broadcastAs()
    {
        return 'payment.registered';
    }

    public function broadcastWith()
    {
        return [
            'transaction' => $this->transaction->load('patient', 'paymentMethod', 'cashRegisterSession'),
            'session_id' => $this->sessionId,
        ];
    }
}

