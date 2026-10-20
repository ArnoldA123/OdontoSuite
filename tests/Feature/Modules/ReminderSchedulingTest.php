<?php

namespace Tests\Feature\Modules;

use App\Services\AppointmentService;
use App\Services\ReminderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Tests\Feature\Modules\Concerns\BuildsReminderFixtures;
use Tests\TestCase;

/**
 * Issue #154 — reminder creation was unreachable: `ReminderService` could
 * schedule, but no appointment path called it. These Feature tests pin the
 * creation half of the pipeline against MySQL and assert the persisted rows,
 * not only the HTTP response.
 *
 * Time is frozen (see the shared fixture trait) so the 24h/48h/72h windows and
 * their exact boundaries are deterministic instead of racing the wall clock.
 */
class ReminderSchedulingTest extends TestCase
{
    use BuildsReminderFixtures;
    use RefreshDatabase;

    private AppointmentService $appointments;

    private ReminderService $reminders;

    protected function setUp(): void
    {
        parent::setUp();

        $this->freezeReminderClock();

        $this->appointments = app(AppointmentService::class);
        $this->reminders = app(ReminderService::class);
    }

    // -------------------------------------------------------------------
    // S1 — creating an appointment with requires_confirmation schedules 24h
    // -------------------------------------------------------------------

    public function test_creating_an_appointment_schedules_the_24h_reminder(): void
    {
        $this->activeTemplates('24h', '48h', '72h');
        $scheduledAt = $this->reminderNow->copy()->addHours(30);

        $appointment = $this->book($scheduledAt);

        $this->assertSame([24], $this->pendingHours($appointment), 'A 30h-ahead appointment MUST schedule the 24h reminder and nothing else.');

        $reminder = $this->remindersOf($appointment)->firstWhere('hours_before', 24);
        $this->assertNotNull($reminder, 'The 24h ReminderSchedule row MUST exist.');
        $this->assertSame('pending', $reminder->status);
        $this->assertSame(
            $this->reminderTemplates['24h']->id,
            (int) $reminder->reminder_template_id,
            'The 24h reminder MUST reference the active 24h template.'
        );
        $this->assertTrue(
            $scheduledAt->copy()->subHours(24)->equalTo($reminder->scheduled_at),
            "scheduled_at MUST equal the appointment time minus 24h. Expected: {$scheduledAt->copy()->subHours(24)} Actual: {$reminder->scheduled_at}"
        );
    }

    // -------------------------------------------------------------------
    // S2 — 48h/72h are scheduled when the appointment is far enough
    // -------------------------------------------------------------------

    public function test_creating_a_distant_appointment_schedules_every_open_window(): void
    {
        $this->activeTemplates('24h', '48h', '72h');
        $scheduledAt = $this->reminderNow->copy()->addHours(80);

        $appointment = $this->book($scheduledAt);

        $this->assertSame([24, 48, 72], $this->pendingHours($appointment), 'An 80h-ahead appointment MUST schedule 24h, 48h and 72h.');

        foreach ($this->remindersOf($appointment) as $reminder) {
            $expected = $scheduledAt->copy()->subHours((int) $reminder->hours_before);
            $this->assertTrue(
                $expected->equalTo($reminder->scheduled_at),
                "The {$reminder->hours_before}h reminder MUST be scheduled {$reminder->hours_before}h before the appointment."
            );
        }
    }

    public function test_an_appointment_exactly_48h_ahead_still_schedules_the_48h_reminder(): void
    {
        $this->activeTemplates('24h', '48h', '72h');
        $scheduledAt = $this->reminderNow->copy()->addHours(48);

        $appointment = $this->book($scheduledAt);

        $this->assertSame(
            [24, 48],
            $this->pendingHours($appointment),
            'At exactly 48h the 48h window is still open (sent at this instant), so 24h and 48h MUST be scheduled and 72h MUST not.'
        );
    }

    // -------------------------------------------------------------------
    // S3 — the flag still gates the pipeline
    // -------------------------------------------------------------------

    public function test_requires_confirmation_false_schedules_nothing(): void
    {
        $this->activeTemplates('24h', '48h', '72h');
        $appointment = $this->book($this->reminderNow->copy()->addHours(80), $this->type(false));

        $this->assertSame([], $this->pendingHours($appointment), 'A type with requires_confirmation = false MUST NOT schedule any reminder.');
        $this->assertSame(0, $this->remindersOf($appointment)->count());
    }

    // -------------------------------------------------------------------
    // S7 — missing active template: warn and schedule nothing
    // -------------------------------------------------------------------

    public function test_missing_active_template_warns_and_schedules_nothing(): void
    {
        Log::spy();
        $this->activeTemplates('48h', '72h');

        $appointment = $this->book($this->reminderNow->copy()->addHours(30));

        Log::shouldHaveReceived('warning')->atLeast()->once();

        $this->assertSame([], $this->pendingHours($appointment), 'Without an active 24h template no reminder MAY be persisted.');
    }

    // -------------------------------------------------------------------
    // S5 — rescheduling cancels the pending rows and recreates them
    // -------------------------------------------------------------------

    public function test_rescheduling_recreates_pending_reminders_for_the_new_time(): void
    {
        $this->activeTemplates('24h', '48h', '72h');
        $appointment = $this->book($this->reminderNow->copy()->addHours(80));
        $this->assertCount(3, $this->remindersOf($appointment));

        $newTime = $this->reminderNow->copy()->addHours(100);
        $this->appointments->rescheduleAppointment($appointment, $newTime);

        $appointment->refresh();

        $this->assertSame([24, 48, 72], $this->pendingHours($appointment), 'All three windows MUST be pending again after rescheduling far enough ahead.');
        $this->assertSame(3, $this->remindersOf($appointment)->count(), 'Rescheduling MUST NOT duplicate rows: idempotency is (appointment_id, hours_before).');

        foreach ($this->remindersOf($appointment)->where('status', 'pending') as $reminder) {
            $expected = $newTime->copy()->subHours((int) $reminder->hours_before);
            $this->assertTrue(
                $expected->equalTo($reminder->scheduled_at),
                "Rescheduling MUST move the {$reminder->hours_before}h reminder to the new appointment time."
            );
        }
    }

    public function test_rescheduling_nearer_cancels_windows_that_no_longer_apply(): void
    {
        $this->activeTemplates('24h', '48h', '72h');
        $appointment = $this->book($this->reminderNow->copy()->addHours(80));

        $this->appointments->rescheduleAppointment($appointment, $this->reminderNow->copy()->addHours(30));

        $appointment->refresh();

        $this->assertSame([24], $this->pendingHours($appointment), 'Only the 24h window is still open when the appointment moves to 30h ahead.');
        $this->assertSame(
            2,
            $this->remindersOf($appointment)->whereIn('hours_before', [48, 72])->where('status', 'cancelled')->count(),
            'The 48h and 72h reminders MUST be cancelled, not left pending.'
        );
    }

    // -------------------------------------------------------------------
    // S4 — idempotency on (appointment_id, hours_before)
    // -------------------------------------------------------------------

    public function test_re_scheduling_reminders_is_idempotent(): void
    {
        $this->activeTemplates('24h', '48h', '72h');
        $appointment = $this->book($this->reminderNow->copy()->addHours(80));

        $this->reminders->scheduleReminders($appointment);
        $this->reminders->scheduleReminders($appointment);

        $this->assertSame(3, $this->remindersOf($appointment)->count(), 'Re-dispatching MUST NOT duplicate reminders.');
        $this->assertSame([24, 48, 72], $this->pendingHours($appointment));
    }
}
