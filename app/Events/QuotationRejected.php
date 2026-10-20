<?php

namespace App\Events;

use App\Models\Quotation;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class QuotationRejected implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $quotation;

    public $reason;

    public function __construct(Quotation $quotation, string $reason)
    {
        $this->quotation = $quotation;
        $this->reason = $reason;
    }

    public function broadcastOn()
    {
        return [
            new PrivateChannel('quotations'),
        ];
    }

    public function broadcastAs()
    {
        return 'quotation.rejected';
    }

    public function broadcastWith()
    {
        return [
            'quotation' => $this->quotation->load(['items', 'patient', 'treatmentPlan', 'createdBy']),
            'reason' => $this->reason,
        ];
    }
}
