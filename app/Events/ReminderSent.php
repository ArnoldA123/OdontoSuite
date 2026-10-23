<?php

namespace App\Events;

use App\Models\ReminderSchedule;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Arr;

/**
 * Slice 10: TrackReminderDelivery listener was wired in slice 09 (commit
 * aafb9ed). The NF-2 marker is stale; removed for clarity.
 *
 * F5 (#314, D2): the payload embeds the appointment, and this event travels on
 * `reminders` and `appointments`, both authorized to reception and the
 * laboratory. The appointment payload therefore drops `treatment_notes`, the
 * same boundary `AppointmentBroadcastPayload` applies to the appointment
 * events.
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
            new PrivateChannel('reminders'),
            new PrivateChannel('appointments'),
        ];
    }

    public function broadcastAs()
    {
        return 'reminder.sent';
    }

    public function broadcastWith()
    {
        // `loadMissing` keeps the payload testable without a database and
        // behaves like `load` when the relations were not eager loaded.
        $this->reminder->loadMissing('appointment.patient', 'reminderTemplate');

        return [
            'reminder' => Arr::except(
                $this->reminder->toArray(),
                ['appointment.treatment_notes'],
            ),
        ];
    }
}
