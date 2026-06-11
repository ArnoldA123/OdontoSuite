<?php

namespace App\Events;

use App\Models\ReminderSchedule;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Sprint 0 fix (NF-2): este evento no tiene listener registrado en AppServiceProvider. Si implementa ShouldBroadcast, igual intenta transmitir por Reverb (sin consumidor registrado en frontend por defecto). Sprint 3 lo cablea correctamente (ver plan-mejoras-futuras-2026-06.md).
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

