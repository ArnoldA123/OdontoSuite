<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * PaymentGatewayWebhookEvent — registro inmutable de cada evento
 * de webhook recibido de una pasarela de pago.
 *
 * Idempotency: unique(external_id, event_type) evita doble procesamiento.
 */
class PaymentGatewayWebhookEvent extends Model
{
    protected $fillable = [
        'gateway',
        'event_type',
        'external_id',
        'payload',
        'signature',
        'signature_valid',
        'processed',
        'processed_at',
        'error',
    ];

    protected $casts = [
        'payload' => 'array',
        'signature_valid' => 'boolean',
        'processed' => 'boolean',
        'processed_at' => 'datetime',
    ];
}
