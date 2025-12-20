<?php

namespace App\Events;

use App\Models\WaitingList;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class WaitingListCreated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $waitingList;

    public function __construct(WaitingList $waitingList)
    {
        $this->waitingList = $waitingList;
    }

    public function broadcastOn()
    {
        return [
            new Channel('waiting-lists'),
            new Channel('appointments'),
        ];
    }

    public function broadcastAs()
    {
        return 'waiting-list.created';
    }

    public function broadcastWith()
    {
        return [
            'waiting_list' => $this->waitingList->load('patient', 'appointmentType', 'preferredUser'),
        ];
    }
}

