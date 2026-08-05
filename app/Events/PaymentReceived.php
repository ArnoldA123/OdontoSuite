<?php

namespace App\Events;

use App\Models\Transaction;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Slice 10 (T-10.3 + T-10.4): broadcast moved to a PrivateChannel so
 * patient transaction data (patient_name, amount, status) is not exposed
 * on a public channel. The LogPaymentReceived listener records an audit
 * log entry per external payment. Authorization for the private channel
 * lives in routes/channels.php.
 */
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

    public function broadcastOn(): PrivateChannel
    {
        // Canal privado por sucursal de la transaccion.
        $branchId = $this->transaction->branch_id ?? 'global';
        return new PrivateChannel("private-cash-register.{$branchId}");
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
