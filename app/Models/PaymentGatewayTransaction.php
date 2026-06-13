<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentGatewayTransaction extends Model
{
    protected $fillable = [
        'transaction_id',
        'payment_method_id',
        'gateway',
        'external_id',
        'external_status',
        'amount',
        'currency',
        'payer_email',
        'raw_response',
        'webhook_received_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'raw_response' => 'array',
        'webhook_received_at' => 'datetime',
    ];

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class);
    }

    public function scopeByGateway($query, string $gateway)
    {
        return $query->where('gateway', $gateway);
    }

    public function scopePending($query)
    {
        return $query->where('external_status', 'pending');
    }

    public function scopeApproved($query)
    {
        return $query->where('external_status', 'approved');
    }
}
