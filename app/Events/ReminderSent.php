<?php

namespace App\Events;

use App\Models\ReminderSchedule;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Slice 10: TrackReminderDelivery listener was wired in slice 09 (commit
 * aafb9ed). The NF-2 marker is stale; removed for clarity.
 */
class ReminderSent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $reminder;

    public function __construct(ReminderSchedule $reminder)
    {
        $this->reminder = $reminder;
    }

    public function broadcastOn()
    {
        return [
            new Channel('reminders'),
            new Channel('appointments'),
        ];
    }

    public function broadcastAs()
    {
        return 'reminder.sent';
    }

    public function broadcastWith()
    {
        return [
            'reminder' => $this->reminder->load('appointment.patient', 'reminderTemplate'),
        ];
    }
}

