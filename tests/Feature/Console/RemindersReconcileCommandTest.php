<?php

namespace Tests\Feature\Console;

use App\Models\Appointment;
use App\Models\AppointmentType;
use App\Models\ReminderSchedule;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Modules\Concerns\BuildsReminderFixtures;
use Tests\TestCase;

/**
 * Issue #154 — `reminders:reconcile` is the safety net for the creation half
 * of the reminder pipeline: it backfills appointments created before the
 * wiring, repairs windows missed by a failed write, and cancels the pending
 * reminders of cancelled appointments. It must never duplicate a row or
 * resurrect one that was already sent or cancelled on purpose.
 */
class RemindersReconcileCommandTest extends TestCase
{
    use BuildsReminderFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->freezeReminderClock();
    }

    public function test_it_schedules_the_missing_reminders_of_future_appointments(): void
    {
        $this->activeTemplates('24h', '48h', '72h');
        $scheduledAt = $this->reminderNow->copy()->addHours(80);
        $appointment = $this->appointment($scheduledAt);

        $this->assertSame(0, $this->remindersOf($appointment)->count(), 'The fixture starts without reminders, like rows created before the fix.');

        $this->artisan('reminders:reconcile')->assertExitCode(0);

        $this->assertSame([24, 48, 72], $this->pendingHours($appointment), 'Every open window MUST be created for an appointment without reminders.');

        foreach ($this->remindersOf($appointment) as $reminder) {
            $expected = $scheduledAt->copy()->subHours((int) $reminder->hours_before);
            $this->assertTrue(
                $expected->equalTo($reminder->scheduled_at),
                "The reconciliation MUST place the {$reminder->hours_before}h reminder at appointment time minus {$reminder->hours_before}h."
            );
        }
    }

    public function test_it_does_not_duplicate_or_resurrect_existing_reminders(): void
    {
        $this->activeTemplates('24h', '48h', '72h');
        $scheduledAt = $this->reminderNow->copy()->addHours(80);
        $appointment = $this->appointment($scheduledAt);

        $sentAt = $this->reminderNow->copy()->subHour();
        ReminderSchedule::create([
            'appointment_id' => $appointment->id,
            'reminder_template_id' => $this->reminderTemplates['24h']->id,
            'hours_before' => 24,
            'scheduled_at' => $scheduledAt->copy()->subHours(24),
            'status' => 'sent',
            'sent_at' => $sentAt,
        ]);
        ReminderSchedule::create([
            'appointment_id' => $appointment->id,
            'reminder_template_id' => $this->reminderTemplates['48h']->id,
            'hours_before' => 48,
            'scheduled_at' => $scheduledAt->copy()->subHours(48),
            'status' => 'cancelled',
        ]);

        $this->artisan('reminders:reconcile')->assertExitCode(0);

        $rows = $this->remindersOf($appointment);
        $this->assertSame(3, $rows->count(), 'The reconciliation MUST NOT duplicate (appointment_id, hours_before).');
        $this->assertSame('sent', $rows->firstWhere('hours_before', 24)->status, 'A sent reminder MUST stay sent.');
        $this->assertSame('cancelled', $rows->firstWhere('hours_before', 48)->status, 'A reminder cancelled on purpose MUST NOT be resurrected.');
        $this->assertSame('pending', $rows->firstWhere('hours_before', 72)->status, 'Only the missing window is created.');
    }

    public function test_it_cancels_the_pending_reminders_of_cancelled_appointments(): void
    {
        $this->activeTemplates('24h', '48h', '72h');
        $scheduledAt = $this->reminderNow->copy()->addHours(80);
        $appointment = $this->appointment($scheduledAt);

        ReminderSchedule::create([
            'appointment_id' => $appointment->id,
            'reminder_template_id' => $this->reminderTemplates['24h']->id,
            'hours_before' => 24,
            'scheduled_at' => $scheduledAt->copy()->subHours(24),
            'status' => 'pending',
        ]);

        // Direct update: this proves the command is its own backstop, without
        // relying on the AppointmentService cancellation wiring.
        $appointment->update(['status' => 'cancelled']);

        $this->artisan('reminders:reconcile')->assertExitCode(0);

        $rows = $this->remindersOf($appointment);
        $this->assertSame([], $this->pendingHours($appointment), 'A cancelled appointment MUST NOT keep pending reminders.');
        $this->assertSame(1, $rows->where('status', 'cancelled')->count());
        $this->assertSame(1, $rows->count(), 'The reconciliation MUST NOT add windows to a cancelled appointment.');
    }

    public function test_it_skips_types_that_do_not_require_confirmation(): void
    {
        $this->activeTemplates('24h', '48h', '72h');
        $appointment = $this->appointment($this->reminderNow->copy()->addHours(80), requiresConfirmation: false);

        $this->artisan('reminders:reconcile')->assertExitCode(0);

        $this->assertSame(0, $this->remindersOf($appointment)->count(), 'requires_confirmation = false still gates the whole pipeline.');
    }

    public function test_it_reports_what_it_did(): void
    {
        $this->activeTemplates('24h', '48h', '72h');
        $this->appointment($this->reminderNow->copy()->addHours(80));

        $this->artisan('reminders:reconcile')
            ->expectsOutputToContain('Recordatorios creados: 3')
            ->assertExitCode(0);
    }

    // -------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------

    private function appointment(Carbon $scheduledAt, bool $requiresConfirmation = true): Appointment
    {
        return Appointment::factory()->create([
            'appointment_type_id' => AppointmentType::factory()->create([
                'requires_confirmation' => $requiresConfirmation,
                'is_active' => true,
            ])->id,
            'scheduled_at' => $scheduledAt->copy()->startOfSecond(),
            'status' => 'scheduled',
        ]);
    }
}
