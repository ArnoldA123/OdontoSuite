<?php

namespace Tests\Feature\Modules;

use App\Models\Appointment;
use App\Models\AppointmentType;
use App\Models\DentalChair;
use App\Models\Patient;
use App\Models\ReminderSchedule;
use App\Models\ReminderTemplate;
use App\Models\User;
use App\Services\ReminderService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * full-user-browser-audit-2026-08-05 / PR5 / Phase 4a — Reminder Dispatch.
 *
 * Validation-first Feature test for the ReminderProvider dispatch contract
 * documented in specs/module-validation-tests. The spec references
 * `ReminderProvider::dispatchForAppointment($appointment)` which does not
 * exist in the codebase; the production equivalent — and the entry point
 * that the existing console schedule actually invokes — is
 * `ReminderService::scheduleReminder($appointment, '24h', 24)`. This test
 * exercises that EXISTING contract.
 *
 * Spec scenarios under test:
 *   - 24h queue: scheduling a 24h reminder creates exactly one ReminderSchedule
 *     row with scheduled_at = appointment.scheduled_at - 1h
 *   - past no-op: a reminder in the past is not persisted
 *   - idempotency: re-dispatch does not duplicate reminders (RED: the
 *     current code creates a new row on every call; this test documents
 *     the gap and is marked as discovered deviation)
 *   - missing-appointment: a domain exception is raised (TypeError from the
 *     strict `Appointment` parameter when the appointment is missing)
 *
 * Rollback boundary: delete this file. No production code is touched.
 *
 * Known runtime caveat: the local SQLite test runner hits the pre-existing
 * `transactions.type` DROP COLUMN tech debt (AGENTS.md §6). The bounded
 * runtime fallback is the dev MySQL harness (`DB_CONNECTION=mysql`). This
 * test must be run against MySQL to exercise the service contract; the
 * SQLite path will fail at the RefreshDatabase bootstrap before any
 * assertion runs.
 */
class ReminderDispatchTest extends TestCase
{
    use RefreshDatabase;

    private ReminderService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = $this->app->make(ReminderService::class);
    }

    private function odontologo(): User
    {
        // Direct create: User::factory() is blocked against MySQL because the
        // default factory does not emit `username` (MySQL users.username is
        // NOT NULL without default). Bypassing the factory is the safest
        // available path for Feature tests until the factory is fixed.
        return User::create([
            'name' => 'Test Odontologo',
            'email' => 'odontologo.reminder.test@example.com',
            'username' => 'odonto_reminder_' . uniqid(),
            'password' => bcrypt('password'),
            'role' => 'odontologo',
            'is_active' => true,
        ]);
    }

    private function activeTemplate(string $type = '24h'): ReminderTemplate
    {
        return ReminderTemplate::create([
            'name' => "Template {$type}",
            'type' => $type,
            'subject' => 'Reminder',
            'body_html' => '<p>Hi</p>',
            'body_text' => 'Hi',
            'is_active' => true,
        ]);
    }

    /**
     * Build a real appointment with the patient + type + chair + doctor
     * FK chain (all NOT NULL constraints per the migrations).
     */
    private function appointment(Carbon $scheduledAt): Appointment
    {
        $doctor = $this->odontologo();
        $patient = Patient::create([
            'first_name' => 'Test',
            'last_name' => 'Patient',
            'email' => 'test.patient.reminder@example.com',
            'phone' => '+51 999 000 000',
            'is_active' => true,
        ]);
        $type = AppointmentType::create([
            'name' => 'Control',
            'default_duration_minutes' => 30,
            'requires_confirmation' => true,
            'is_active' => true,
        ]);
        $chair = DentalChair::create([
            'name' => 'Chair 1',
            'code' => 'C-1',
            'is_active' => true,
        ]);

        return Appointment::create([
            'patient_id' => $patient->id,
            'user_id' => $doctor->id,
            'dental_chair_id' => $chair->id,
            'appointment_type_id' => $type->id,
            'scheduled_at' => $scheduledAt,
            'ends_at' => $scheduledAt->copy()->addMinutes(30),
            'duration_minutes' => 30,
            'status' => 'scheduled',
            'created_by' => $doctor->id,
        ]);
    }

    // -------------------------------------------------------------------
    // 24h queue: exactly one reminder, scheduled_at = appointment - 1h
    // -------------------------------------------------------------------

    public function test_24h_reminder_creates_one_schedule_at_minus_one_hour(): void
    {
        $this->activeTemplate('24h');
        $appointment = $this->appointment(Carbon::now()->addHours(24));

        $this->service->scheduleReminder($appointment, '24h', 24);

        $count = ReminderSchedule::where('appointment_id', $appointment->id)->count();
        $this->assertSame(1, $count, 'Exactly one ReminderSchedule row MUST be created for a 24h queue.');

        $reminder = ReminderSchedule::where('appointment_id', $appointment->id)->first();
        $expected = $appointment->scheduled_at->copy()->subHours(24);
        $this->assertTrue(
            $expected->equalTo($reminder->scheduled_at),
            "scheduled_at MUST equal appointment.scheduled_at - 24h. Expected: {$expected->toDateTimeString()} Actual: {$reminder->scheduled_at->toDateTimeString()}"
        );
        $this->assertSame('pending', $reminder->status);
        // Slice 07a (reminder-schedule-write-contract): the canonical column
        // is `hours_before`, not the now-removed `anticipation_hours`. The
        // 24h-queue test MUST prove the model + service agree on the new
        // schema.
        $this->assertSame(
            24,
            (int) $reminder->hours_before,
            'hours_before MUST equal 24 for the 24h-queue (canonical column from migration 2025_09_20_082355).'
        );
        $this->assertNull(
            $reminder->getAttributes()['anticipation_hours'] ?? null,
            'ReminderSchedule MUST NOT carry the phantom `anticipation_hours` attribute (no migration ever added it).'
        );
        $this->assertNull(
            $reminder->getAttributes()['type'] ?? null,
            'ReminderSchedule MUST NOT carry the phantom `type` attribute (no migration ever added it).'
        );
    }

    // -------------------------------------------------------------------
    // Past no-op: no row created when the reminder window has already passed
    // -------------------------------------------------------------------

    public function test_past_reminder_does_not_persist(): void
    {
        $this->activeTemplate('24h');
        // Appointment is 1h from now → 24h-before reminder window is already 23h in the past.
        $appointment = $this->appointment(Carbon::now()->addHour());

        $this->service->scheduleReminder($appointment, '24h', 24);

        $count = ReminderSchedule::where('appointment_id', $appointment->id)->count();
        $this->assertSame(0, $count, 'No ReminderSchedule row MUST be created when the scheduled reminder time is in the past.');
    }

    // -------------------------------------------------------------------
    // Idempotency: re-dispatch on the same appointment MUST remain at 1
    // (RED — current code creates a new row on every call; document gap)
    // -------------------------------------------------------------------

    public function test_redispatch_does_not_duplicate_reminder(): void
    {
        $this->activeTemplate('24h');
        $appointment = $this->appointment(Carbon::now()->addHours(48));

        $this->service->scheduleReminder($appointment, '24h', 24);
        $this->service->scheduleReminder($appointment, '24h', 24);

        $count = ReminderSchedule::where('appointment_id', $appointment->id)->count();
        $this->assertSame(
            1,
            $count,
            'ReminderSchedule count for a single 24h dispatch MUST remain 1 across re-dispatches (spec scenario for idempotency on (appointment_id, hours_before)).'
        );

        $reminder = ReminderSchedule::where('appointment_id', $appointment->id)->first();
        $this->assertSame(
            24,
            (int) $reminder->hours_before,
            'After redispatch, the surviving row MUST still carry hours_before = 24 (the lookup key used by the idempotency contract).'
        );
    }

    // -------------------------------------------------------------------
    // Missing appointment: surface a clear error (TypeError from strict type)
    // -------------------------------------------------------------------

    public function test_missing_appointment_surfaces_error(): void
    {
        $this->activeTemplate('24h');
        $this->expectException(\TypeError::class);

        // @phpstan-ignore-next-line — intentional invalid input for the contract test
        $this->service->scheduleReminder(null, '24h', 24);
    }
}
