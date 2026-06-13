<?php

namespace App\Events;

use App\Models\Transaction;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PaymentReceived implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public Transaction $transaction;
    public string $gateway;
    public string $externalStatus;

    public function __construct(Transaction $transaction, string $gateway = 'mercadopago', string $externalStatus = 'approved')
    {
        $this->transaction = $transaction;
        $this->gateway = $gateway;
        $this->externalStatus = $externalStatus;
    }

    public function broadcastOn(): Channel
    {
        // Canal privado para la sucursal de la transaccion
        $branchId = $this->transaction->branch_id ?? 'global';
        return new Channel("cash-register.{$branchId}");
    }

    public function broadcastWith(): array
    {
        return [
            'transaction_id' => $this->transaction->id,
            'amount' => (float) $this->transaction->amount,
            'status' => $this->externalStatus,
            'gateway' => $this->gateway,
            'patient_name' => $this->transaction->patient?->full_name ?? 'N/A',
            'description' => $this->transaction->description ?? '',
        ];
    }

    public function broadcastAs(): string
    {
        return 'payment.received';
    }
}
