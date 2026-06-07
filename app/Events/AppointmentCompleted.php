<?php

namespace App\Events;

use App\Models\Appointment;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AppointmentCompleted implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public Appointment $appointment)
    {
    }

    public function broadcastOn(): array
    {
        return [
            new Channel('appointments'),
            new Channel('dashboard-updates'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'appointment.completed';
    }

    public function broadcastWith(): array
    {
        $this->appointment->loadMissing([
            'patient',
            'user',
            'appointmentType',
            'dentalChair',
            'treatmentPlan',
        ]);

        return [
            'appointment' => $this->appointment,
            'final_amount' => $this->appointment->final_amount,
            'consultation_mode' => $this->appointment->consultation_mode,
            'treatment_plan_id' => $this->appointment->treatment_plan_id,
            'has_payment' => $this->appointment->has_payment,
        ];
    }
}
