<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SpecialtyRecordUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $record;
    public $specialty;

    public function __construct($record, string $specialty)
    {
        $this->record = $record;
        $this->specialty = $specialty;
    }

    public function broadcastOn()
    {
        return [
            new Channel('specialty-records'),
            new Channel('dashboard-updates'),
        ];
    }

    public function broadcastAs()
    {
        return 'specialty-record.updated';
    }

    public function broadcastWith()
    {
        return [
            'record' => $this->record,
            'specialty' => $this->specialty,
        ];
    }
}

