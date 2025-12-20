<?php

namespace App\Events;

use App\Models\Transaction;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TransactionCreated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $transaction;

    public function __construct(Transaction $transaction)
    {
        $this->transaction = $transaction;
    }

    public function broadcastOn()
    {
        return [
            new Channel('transactions'),
            new Channel('dashboard-updates'),
        ];
    }

    public function broadcastAs()
    {
        return 'transaction.created';
    }

    public function broadcastWith()
    {
        return [
            'transaction' => $this->transaction->load('patient', 'appointment', 'paymentMethod', 'createdBy'),
        ];
    }
}

