<?php

namespace App\Events;

use App\Models\Quotation;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Sprint 0 fix (NF-2): este evento no tiene listener registrado en AppServiceProvider. Si implementa ShouldBroadcast, igual intenta transmitir por Reverb (sin consumidor registrado en frontend por defecto). Sprint 3 lo cablea correctamente (ver plan-mejoras-futuras-2026-06.md).
 */
class QuotationUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $quotation;

    public function __construct(Quotation $quotation)
    {
        $this->quotation = $quotation;
    }

    public function broadcastOn()
    {
        return [
            new Channel('quotations'),
            new Channel('dashboard-updates'),
        ];
    }

    public function broadcastAs()
    {
        return 'quotation.updated';
    }

    public function broadcastWith()
    {
        return [
            'quotation' => $this->quotation->load('patient', 'treatmentPlan', 'items', 'approvals'),
        ];
    }
}

