<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\ReminderSchedule;
use App\Models\ReminderTemplate;
use App\Models\ConfirmationToken;
use App\Events\ReminderSent;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Collection;

class ReminderService
{
    /**
     * Schedule reminders for an appointment.
     */
    public function scheduleReminders(Appointment $appointment): void
    {
        $appointmentType = $appointment->appointmentType;

        if (!$appointmentType->requires_confirmation) {
            return;
        }

        // Schedule 24h reminder
        $this->scheduleReminder($appointment, '24h', 24);

        // Schedule 48h reminder if appointment is more than 48h away
        if ($appointment->scheduled_at->diffInHours(now()) > 48) {
            $this->scheduleReminder($appointment, '48h', 48);
        }

        // Schedule 72h reminder if appointment is more than 72h away
        if ($appointment->scheduled_at->diffInHours(now()) > 72) {
            $this->scheduleReminder($appointment, '72h', 72);
        }
    }

    /**
     * Schedule a specific reminder.
     *
     * Slice 07a (reminder-schedule-write-contract): writes the canonical
     * `hours_before` column (NOT the phantom `anticipation_hours` / `type`
     * columns that the previous implementation carried). Idempotent on
     * `(appointment_id, hours_before)` via `updateOrCreate`, so re-dispatching
     * the same kind of reminder for the same appointment is a no-op and
     * never produces duplicates.
     */
    public function scheduleReminder(Appointment $appointment, string $type, int $hoursBefore): void
    {
        $template = ReminderTemplate::where('type', $type)->where('is_active', true)->first();

        if (!$template) {
            Log::warning("No reminder template found for type: {$type}");
            return;
        }

        $scheduledAt = $appointment->scheduled_at->copy()->subHours($hoursBefore);

        // Don't schedule if the reminder time has clearly passed (use a
        // 1-second grace period so microsecond clock drift between
        // appointment creation and the service call does NOT silently
        // swallow the boundary case where the appointment is exactly
        // `$hoursBefore` hours away).
        if ($scheduledAt->lessThan(now()->subSecond())) {
            return;
        }

        ReminderSchedule::updateOrCreate(
            [
                'appointment_id' => $appointment->id,
                'hours_before' => $hoursBefore,
            ],
            [
                'reminder_template_id' => $template->id,
                'scheduled_at' => $scheduledAt,
                'status' => 'pending',
            ]
        );
    }

    /**
     * Process due reminders.
     */
    public function processDueReminders(): int
    {
        $dueReminders = ReminderSchedule::where('status', 'pending')
            ->where('scheduled_at', '<=', now())
            ->with(['appointment.patient', 'appointment.user', 'appointment.appointmentType', 'reminderTemplate'])
            ->get();

        $processed = 0;

        foreach ($dueReminders as $reminder) {
            try {
                $this->sendReminder($reminder);
                $reminder->markAsSent();
                $processed++;
            } catch (\Exception $e) {
                Log::error("Failed to send reminder {$reminder->id}: " . $e->getMessage());
            }
        }

        return $processed;
    }

    /**
     * Send a reminder.
     */
    public function sendReminder(ReminderSchedule $reminder): void
    {
        $appointment = $reminder->appointment;
        $template = $reminder->reminderTemplate;

        // Create confirmation token if it doesn't exist
        $token = ConfirmationToken::where('appointment_id', $appointment->id)
            ->where('type', 'confirmation')
            ->where('expires_at', '>', now())
            ->first();

        if (!$token) {
            $token = ConfirmationToken::createForAppointment($appointment, 'confirmation', 24);
        }

        // Prepare template variables
        $variables = [
            'patient_name' => $appointment->patient->full_name,
            'appointment_date' => $appointment->scheduled_at->format('d/m/Y'),
            'appointment_time' => $appointment->scheduled_at->format('H:i'),
            'appointment_type' => $appointment->appointmentType->name,
            'doctor_name' => $appointment->user->name,
            'clinic_name' => 'EasyDent',
            'confirmation_url' => $token->getConfirmationUrl(),
            'reschedule_url' => $token->getRescheduleUrl(),
            'cancellation_url' => $token->getCancellationUrl(),
        ];

        // Render template
        $rendered = $template->render($variables);

        // Send email (you would implement actual email sending here)
        $this->sendEmail($appointment->patient->email, $rendered['subject'], $rendered['body']);

        // Log the reminder
        Log::info("Reminder sent for appointment {$appointment->id} to {$appointment->patient->email}");

        // Emitir evento de WebSocket
        $reminder->load('appointment.patient', 'reminderTemplate');
        event(new ReminderSent($reminder));
    }

    /**
     * Send email (placeholder - implement with your email service).
     */
    private function sendEmail(string $email, string $subject, string $body): void
    {
        // This is a placeholder - implement with your preferred email service
        // For example, using Laravel Mail:
        // Mail::to($email)->send(new AppointmentReminder($subject, $body));

        Log::info("Email would be sent to {$email} with subject: {$subject}");
    }

    /**
     * Cancel reminders for an appointment.
     */
    public function cancelReminders(Appointment $appointment): void
    {
        ReminderSchedule::where('appointment_id', $appointment->id)
            ->where('status', 'pending')
            ->update(['status' => 'cancelled']);
    }

    /**
     * Reschedule reminders for an appointment.
     */
    public function rescheduleReminders(Appointment $appointment): void
    {
        // Cancel existing reminders
        $this->cancelReminders($appointment);

        // Schedule new reminders
        $this->scheduleReminders($appointment);
    }

    /**
     * Get reminder statistics.
     */
    public function getReminderStats(Carbon $startDate, Carbon $endDate): array
    {
        $totalScheduled = ReminderSchedule::whereBetween('scheduled_at', [$startDate, $endDate])->count();
        $totalSent = ReminderSchedule::whereBetween('scheduled_at', [$startDate, $endDate])
            ->where('status', 'sent')->count();
        $totalFailed = ReminderSchedule::whereBetween('scheduled_at', [$startDate, $endDate])
            ->where('status', 'failed')->count();

        return [
            'total_scheduled' => $totalScheduled,
            'total_sent' => $totalSent,
            'total_failed' => $totalFailed,
            'success_rate' => $totalScheduled > 0 ? round(($totalSent / $totalScheduled) * 100, 2) : 0,
        ];
    }

    /**
     * Create a custom reminder.
     *
     * Slice 07a: writes the canonical `hours_before` column. Idempotent on
     * `(appointment_id, hours_before)` via `updateOrCreate`.
     */
    public function createCustomReminder(Appointment $appointment, string $type, int $hoursBefore, string $customMessage = null): ReminderSchedule
    {
        $template = ReminderTemplate::where('type', $type)->where('is_active', true)->first();

        if (!$template) {
            throw new \Exception("No reminder template found for type: {$type}");
        }

        $scheduledAt = $appointment->scheduled_at->copy()->subHours($hoursBefore);

        if ($scheduledAt->isPast()) {
            throw new \Exception("Cannot schedule reminder in the past");
        }

        return ReminderSchedule::updateOrCreate(
            [
                'appointment_id' => $appointment->id,
                'hours_before' => $hoursBefore,
            ],
            [
                'reminder_template_id' => $template->id,
                'scheduled_at' => $scheduledAt,
                'status' => 'pending',
            ]
        );
    }

    /**
     * Send immediate reminder.
     *
     * Slice 07a: writes `hours_before = 0` (the canonical zero-anticipation
     * value) instead of the phantom `anticipation_hours` / `type` columns.
     * The dispatch is intentionally NOT idempotent on
     * (appointment_id, hours_before=0) because each `sendImmediate` call
     * materialises a fresh reminder row (the original semantics).
     */
    public function sendImmediateReminder(Appointment $appointment, string $type = 'immediate'): void
    {
        $template = ReminderTemplate::where('type', $type)->where('is_active', true)->first();

        if (!$template) {
            throw new \Exception("No reminder template found for type: {$type}");
        }

        // Create a temporary reminder schedule for immediate sending.
        $reminder = ReminderSchedule::create([
            'appointment_id' => $appointment->id,
            'reminder_template_id' => $template->id,
            'hours_before' => 0,
            'scheduled_at' => now(),
            'status' => 'pending',
        ]);

        $this->sendReminder($reminder);
        $reminder->markAsSent();
    }

    /**
     * Get upcoming reminders.
     */
    public function getUpcomingReminders(int $hours = 24): Collection
    {
        return ReminderSchedule::where('status', 'pending')
            ->whereBetween('scheduled_at', [now(), now()->addHours($hours)])
            ->with(['appointment.patient', 'appointment.user', 'appointment.appointmentType'])
            ->orderBy('scheduled_at')
            ->get();
    }

    /**
     * Clean up old reminders.
     */
    public function cleanupOldReminders(int $days = 30): int
    {
        return ReminderSchedule::where('status', 'sent')
            ->where('sent_at', '<', now()->subDays($days))
            ->delete();
    }
}
