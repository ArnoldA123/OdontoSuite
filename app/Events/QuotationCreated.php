<?php

namespace App\Events;

use App\Models\Quotation;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class QuotationCreated implements ShouldBroadcast
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
        return 'quotation.created';
    }

    public function broadcastWith()
    {
        return [
            'quotation' => $this->quotation->load('patient', 'treatmentPlan', 'items', 'createdBy'),
        ];
    }
}

