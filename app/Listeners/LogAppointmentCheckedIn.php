<?php

namespace App\Listeners;

use App\Events\AppointmentCheckedIn;
use App\Models\AuditLog;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Slice 10 (T-10.3): audit-trail every check-in so reception activity
 * (patient arrival → appointment.status='in_consultation') is traceable.
 * AppointmentCheckedIn previously broadcast on a public channel with no
 * consumer; T-10.4 secured the channel and this listener provides the
 * missing backend side-effect.
 *
 * AGENTS.md §7: listener MUST wrap its body in try/catch + report() so
 * failure NEVER crashes the check-in flow.
 */
class LogAppointmentCheckedIn
{
    public function handle(AppointmentCheckedIn $event): void
    {
        try {
            $appointment = $event->appointment;
            $user = Auth::user();

            AuditLog::log(
                $user,
                'appointment_checked_in',
                $appointment,
                [],
                [
                    'checked_in_at' => optional($appointment->checked_in_at)->toIso8601String(),
                    'status' => $appointment->status,
                ]
            );

            Log::info('Appointment checked in', [
                'appointment_id' => $appointment->id,
                'patient_id' => $appointment->patient_id,
                'user_id' => $user?->id,
            ]);
        } catch (Throwable $e) {
            // AGENTS.md §7: swallow + log + report. Failure here MUST NOT
            // crash the consultation check-in flow.
            Log::error('LogAppointmentCheckedIn failed: ' . $e->getMessage(), [
                'appointment_id' => $event->appointment->id ?? null,
                'exception' => $e,
            ]);
            report($e);
        }
    }
}
