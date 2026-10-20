<?php

namespace App\Console\Commands;

use App\Models\Appointment;
use App\Models\ReminderSchedule;
use App\Services\ReminderService;
use Illuminate\Console\Command;

/**
 * Issue #154 — reconciliation half of the reminder pipeline.
 *
 * Appointments create their confirmation reminders inside the appointment
 * transaction, but rows written before that wiring existed (or a window lost to
 * a failed write) still need a deterministic repair path. The command is
 * idempotent: it only creates windows that have no row at all, so a reminder
 * that was sent or cancelled on purpose is never resurrected, and it cancels
 * whatever is still pending for a cancelled appointment.
 */
class ReconcileReminders extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'reminders:reconcile';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Agenda los recordatorios faltantes de citas futuras y cancela los de citas canceladas';

    /**
     * Execute the console command.
     */
    public function handle(ReminderService $reminders): int
    {
        $reviewed = 0;
        $created = 0;

        Appointment::query()
            ->where('scheduled_at', '>', now())
            ->whereNotIn('status', ['cancelled', 'completed', 'no_show'])
            ->whereHas('appointmentType', fn ($query) => $query->where('requires_confirmation', true))
            ->chunkById(100, function ($appointments) use ($reminders, &$reviewed, &$created) {
                foreach ($appointments as $appointment) {
                    $reviewed++;
                    $created += $reminders->reconcileAppointment($appointment);
                }
            });

        $cancelled = ReminderSchedule::query()
            ->where('status', 'pending')
            ->whereHas('appointment', fn ($query) => $query->where('status', 'cancelled'))
            ->update(['status' => 'cancelled']);

        $this->info("Citas futuras revisadas: {$reviewed}");
        $this->info("Recordatorios creados: {$created}");
        $this->info("Recordatorios cancelados: {$cancelled}");

        return self::SUCCESS;
    }
}
