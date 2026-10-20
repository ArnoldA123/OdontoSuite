<?php

namespace Tests\Feature\Modules;

use App\Models\User;
use App\Services\AppointmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Modules\Concerns\BuildsReminderFixtures;
use Tests\TestCase;

/**
 * Issue #154 — cancellation contract of the reminder pipeline: cancelling an
 * appointment MUST cancel its pending reminders, both through the update
 * service and through the live status endpoint the UI uses. Reactivating a
 * cancelled appointment MUST put its open windows back on the pipeline without
 * duplicating rows.
 */
class ReminderCancellationTest extends TestCase
{
    use BuildsReminderFixtures;
    use RefreshDatabase;

    private AppointmentService $appointments;

    protected function setUp(): void
    {
        parent::setUp();

        $this->freezeReminderClock();

        $this->appointments = app(AppointmentService::class);
    }

    // -------------------------------------------------------------------
    // S6 — cancelling cancels the pending reminders (service + live HTTP path)
    // -------------------------------------------------------------------

    public function test_cancelling_through_the_update_service_cancels_pending_reminders(): void
    {
        $this->activeTemplates('24h', '48h', '72h');
        $appointment = $this->book($this->reminderNow->copy()->addHours(80));
        $this->assertCount(3, $this->remindersOf($appointment));

        $this->appointments->updateAppointment($appointment, ['status' => 'cancelled']);

        $appointment->refresh();

        $this->assertSame([], $this->pendingHours($appointment), 'No reminder MAY stay pending after the appointment is cancelled.');
        $this->assertSame(3, $this->remindersOf($appointment)->where('status', 'cancelled')->count());
    }

    public function test_cancelling_through_the_status_endpoint_cancels_pending_reminders(): void
    {
        $this->activeTemplates('24h', '48h', '72h');
        $appointment = $this->book($this->reminderNow->copy()->addHours(80));
        $this->assertCount(3, $this->remindersOf($appointment));

        $admin = User::factory()->create(['role' => 'administrador', 'is_active' => true]);

        $this->actingAs($admin, 'sanctum')
            ->patchJson("/api/appointments/{$appointment->id}/status", ['status' => 'cancelled'])
            ->assertOk();

        $appointment->refresh();

        $this->assertSame('cancelled', $appointment->status);
        $this->assertSame(
            [],
            $this->pendingHours($appointment),
            'PATCH /appointments/{id}/status with cancelled MUST cancel the pending reminders (the live path used by the UI).'
        );
    }

    public function test_reactivating_a_cancelled_appointment_recreates_its_reminders(): void
    {
        $this->activeTemplates('24h', '48h', '72h');
        $appointment = $this->book($this->reminderNow->copy()->addHours(80));
        $admin = User::factory()->create(['role' => 'administrador', 'is_active' => true]);

        $this->actingAs($admin, 'sanctum')
            ->patchJson("/api/appointments/{$appointment->id}/status", ['status' => 'cancelled'])
            ->assertOk();
        $this->assertSame([], $this->pendingHours($appointment), 'Cancelling MUST leave no pending reminder.');

        $this->actingAs($admin, 'sanctum')
            ->patchJson("/api/appointments/{$appointment->id}/status", ['status' => 'confirmed'])
            ->assertOk();

        $appointment->refresh();

        $this->assertSame([24, 48, 72], $this->pendingHours($appointment), 'Reactivating the appointment MUST put its open windows back on the pipeline.');
        $this->assertSame(3, $this->remindersOf($appointment)->count(), 'Reactivating MUST NOT duplicate rows.');
    }
}
