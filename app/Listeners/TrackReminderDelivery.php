<?php

namespace App\Listeners;

use App\Events\ReminderSent;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Slice 03 (T-03.9): listener that records ReminderSent delivery audit
 * trail. Per AGENTS.md §7, the listener MUST wrap in try/catch + report()
 * so failures never propagate into the request lifecycle or the scheduled
 * provider tick.
 */
class TrackReminderDelivery
{
    public function handle(ReminderSent $event): void
    {
        try {
            $reminder = $event->reminder;

            DB::table('audit_logs')->insert([
                'user_id' => null,
                'action' => 'reminder.sent',
                'auditable_type' => $reminder->getMorphClass(),
                'auditable_id' => $reminder->id,
                'old_values' => null,
                'new_values' => json_encode([
                    'status' => $reminder->status,
                    'channel' => $reminder->channel,
                    'sent_at' => optional($reminder->sent_at)->toIso8601String(),
                ]),
                'ip_address' => null,
                'user_agent' => null,
                'metadata' => json_encode([
                    'reminder_id' => $reminder->id,
                    'appointment_id' => $reminder->appointment_id,
                ]),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (Throwable $e) {
            // AGENTS.md §7: swallow + log + report. Listener must NEVER
            // crash the request lifecycle.
            Log::error('TrackReminderDelivery failed: ' . $e->getMessage(), [
                'reminder_id' => $event->reminder->id ?? null,
                'exception' => $e,
            ]);
            report($e);
        }
    }
}
