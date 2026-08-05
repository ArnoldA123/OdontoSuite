<?php

namespace App\Providers;

use App\Models\ReminderProviderRun;
use App\Models\ReminderSchedule;
use App\Services\ReminderService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Slice 03 (T-03.5): scheduled provider for the reminder dispatch pipeline.
 *
 * Wired in routes/console.php via Schedule::call hourly. The provider:
 *   1. Skips if a previous run is still in flight (60s idempotency window)
 *   2. Updates the singleton reminder_provider_runs row with last_run_at
 *   3. Delegates to ReminderService::processDueReminders for actual delivery
 *   4. Wraps the whole tick in try/catch + Log::error so a single bad
 *      reminder does not poison the entire queue (per AGENTS.md §7).
 *
 * Note: this is NOT a Laravel ServiceProvider (no register/boot). It is a
 * service-style class invoked from the console schedule. The Laravel-idiom
 * alternative would be `php artisan make:command ReminderProvider` — we
 * chose the Schedule::call form because the spec (T-03.5) explicitly
 * requested it and it keeps the provider testable in isolation.
 */
class ReminderProvider
{
    /**
     * Minimum gap (seconds) between successful runs to keep the schedule
     * idempotent against overlapping ticks if a previous run stalls.
     */
    public const IDEMPOTENCY_WINDOW_SECONDS = 60;

    public function __construct(private readonly ReminderService $reminderService)
    {
    }

    /**
     * Entry point invoked by the scheduler. Returns the number of reminders
     * processed (0 when skipped or errored).
     */
    public function dispatch(): int
    {
        if ($this->shouldSkip()) {
            return 0;
        }

        $startedAt = now();

        try {
            $processed = $this->reminderService->processDueReminders();

            $this->recordRun($startedAt, $processed);

            return $processed;
        } catch (Throwable $e) {
            // AGENTS.md §7: a single provider failure must NEVER escalate
            // into the request lifecycle. Log + record sentinel run, swallow.
            Log::error('ReminderProvider tick failed: ' . $e->getMessage(), [
                'exception' => $e,
            ]);
            $this->recordRun($startedAt, 0);

            return 0;
        }
    }

    /**
     * Idempotency gate. We track last_run_at in reminder_provider_runs and
     * skip ticks that land within the idempotency window after the last
     * successful run.
     */
    private function shouldSkip(): bool
    {
        $run = $this->getOrCreateRunRow();

        if (!$run->last_run_at) {
            return false;
        }

        $secondsSinceLast = abs(now()->diffInSeconds($run->last_run_at));

        return $secondsSinceLast < self::IDEMPOTENCY_WINDOW_SECONDS;
    }

    private function recordRun(\DateTimeInterface $startedAt, int $processed): void
    {
        $run = $this->getOrCreateRunRow();
        $run->forceFill([
            'last_run_at' => $startedAt,
            'runs_count' => $run->runs_count + 1,
            'last_processed' => $processed,
        ])->save();
    }

    private function getOrCreateRunRow(): ReminderProviderRun
    {
        // DB::transaction makes the singleton seed safe under concurrent ticks.
        return DB::transaction(function () {
            $row = ReminderProviderRun::query()->lockForUpdate()->first();
            if (!$row) {
                $row = ReminderProviderRun::create([
                    'last_run_at' => null,
                    'runs_count' => 0,
                    'last_processed' => 0,
                ]);
            }
            return $row;
        });
    }

    /**
     * Returns the number of reminders currently due (informational).
     */
    public function pendingDueCount(): int
    {
        return ReminderSchedule::due()->count();
    }
}
