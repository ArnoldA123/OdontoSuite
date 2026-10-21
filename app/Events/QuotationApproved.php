<?php

namespace App\Events;

use App\Models\Quotation;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Issue #200: el payload lleva datos del paciente y montos, por eso el evento
 * viaja solo por el canal privado `quotations`.
 */
class QuotationApproved implements ShouldBroadcast
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
            new PrivateChannel('quotations'),
        ];
    }

    public function broadcastAs()
    {
        return 'quotation.approved';
    }

    public function broadcastWith()
    {
        return [
            'quotation' => $this->quotation->load('patient', 'treatmentPlan', 'approvals'),
        ];
    }
}
