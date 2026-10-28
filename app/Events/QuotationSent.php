<?php

namespace App\Events;

use App\Models\Quotation;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * #422 S4 — the act of sending a quotation to the patient.
 *
 * The payload carries patient data and amounts, so it travels only on the
 * private `quotations` channel, the same boundary `#200` fixed for the other
 * quotation events.
 */
class QuotationSent implements ShouldBroadcast
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
        return 'quotation.sent';
    }

    public function broadcastWith()
    {
        return [
            'quotation' => $this->quotation->load('patient', 'treatmentPlan', 'approvals'),
        ];
    }
}
