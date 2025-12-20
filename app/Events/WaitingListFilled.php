<?php

namespace App\Events;

use App\Models\WaitingList;
use App\Models\Appointment;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class WaitingListFilled implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $waitingList;
    public $appointment;

    public function __construct(WaitingList $waitingList, Appointment $appointment)
    {
        $this->waitingList = $waitingList;
        $this->appointment = $appointment;
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
        return 'waiting-list.filled';
    }

    public function broadcastWith()
    {
        return [
            'waiting_list' => $this->waitingList->load('patient'),
            'appointment' => $this->appointment->load('patient', 'user', 'appointmentType'),
        ];
    }
}

