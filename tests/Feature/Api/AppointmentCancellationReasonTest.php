<?php

namespace Tests\Feature\Api;

use App\Models\Appointment;
use App\Models\AppointmentType;
use App\Models\AuditLog;
use App\Models\DentalChair;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Modules\Concerns\BuildsReminderFixtures;
use Tests\TestCase;

/**
 * #394 — cancelling an appointment is a deliberate act.
 *
 * The motive lives in the appointment's own column (`cancellation_reason`),
 * never in the generic `notes` another edit can overwrite, and both doors
 * (PATCH status and PUT) declare the same rule so neither one cancels without
 * it. The status door also publishes how many pending reminders it cancelled,
 * which is the consequence the success notice must name.
 */
class AppointmentCancellationReasonTest extends TestCase
{
    use BuildsReminderFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->freezeReminderClock();
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => 'administrador', 'is_active' => true]);
    }

    public function test_cancelling_without_a_reason_is_rejected_and_cancels_nothing(): void
    {
        $this->activeTemplates('24h', '48h', '72h');
        $appointment = $this->book($this->reminderNow->copy()->addHours(80));
        $this->assertSame([24, 48, 72], $this->pendingHours($appointment));

        $this->actingAs($this->admin(), 'sanctum')
            ->patchJson("/api/appointments/{$appointment->id}/status", ['status' => 'cancelled'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('cancellation_reason');

        $appointment->refresh();

        $this->assertSame('scheduled', $appointment->status, 'A rejected cancellation MUST NOT move the appointment.');
        $this->assertNull($appointment->cancellation_reason);
        $this->assertSame(
            [24, 48, 72],
            $this->pendingHours($appointment),
            'A rejected cancellation MUST leave the pending reminders pending.'
        );
    }

    public function test_cancelling_with_a_reason_persists_it_and_reports_the_cancelled_reminders(): void
    {
        $this->activeTemplates('24h', '48h', '72h');
        $appointment = $this->book($this->reminderNow->copy()->addHours(80));

        $this->actingAs($this->admin(), 'sanctum')
            ->patchJson("/api/appointments/{$appointment->id}/status", [
                'status' => 'cancelled',
                'cancellation_reason' => 'El paciente no puede asistir esta semana.',
            ])
            ->assertOk()
            ->assertJsonPath('data.cancellation_reason', 'El paciente no puede asistir esta semana.')
            ->assertJsonPath('meta.reminders_cancelled', 3);

        $appointment->refresh();

        $this->assertSame('cancelled', $appointment->status);
        $this->assertSame(
            'El paciente no puede asistir esta semana.',
            $appointment->cancellation_reason,
            'The motive MUST live in the appointment record, not in notes.'
        );
        $this->assertSame([], $this->pendingHours($appointment));

        $metadata = AuditLog::where('action', 'appointment_status_changed')
            ->where('auditable_id', $appointment->id)
            ->latest('id')
            ->first()
            ->metadata;

        $this->assertSame(
            'El paciente no puede asistir esta semana.',
            $metadata['cancellation_reason'] ?? null,
            'The audit log MUST keep the documented motive.'
        );
    }

    public function test_another_status_change_needs_no_reason_and_reports_no_cancelled_reminders(): void
    {
        $this->activeTemplates('24h', '48h', '72h');
        $appointment = $this->book($this->reminderNow->copy()->addHours(80));

        $this->actingAs($this->admin(), 'sanctum')
            ->patchJson("/api/appointments/{$appointment->id}/status", ['status' => 'confirmed'])
            ->assertOk()
            ->assertJsonPath('meta.reminders_cancelled', 0);

        $appointment->refresh();

        $this->assertSame('confirmed', $appointment->status);
        $this->assertNull($appointment->cancellation_reason);
        $this->assertSame([24, 48, 72], $this->pendingHours($appointment));
    }

    public function test_reactivating_a_cancelled_appointment_clears_the_reason(): void
    {
        $this->activeTemplates('24h', '48h', '72h');
        $appointment = $this->book($this->reminderNow->copy()->addHours(80));
        $admin = $this->admin();

        $this->actingAs($admin, 'sanctum')
            ->patchJson("/api/appointments/{$appointment->id}/status", [
                'status' => 'cancelled',
                'cancellation_reason' => 'Motivo documentado',
            ])
            ->assertOk();

        $this->actingAs($admin, 'sanctum')
            ->patchJson("/api/appointments/{$appointment->id}/status", ['status' => 'confirmed'])
            ->assertOk();

        $appointment->refresh();

        $this->assertSame('confirmed', $appointment->status);
        $this->assertNull(
            $appointment->cancellation_reason,
            'A reactivated appointment MUST NOT keep the motive of the cancellation that was undone.'
        );
        $this->assertSame([24, 48, 72], $this->pendingHours($appointment));
    }

    public function test_creating_an_appointment_already_cancelled_requires_the_same_motive(): void
    {
        $doctor = User::factory()->create(['role' => 'odontologo', 'is_active' => true]);
        $admin = $this->admin();
        $payload = [
            'patient_id' => Patient::factory()->create(['is_active' => true])->id,
            'user_id' => $doctor->id,
            'dental_chair_id' => DentalChair::factory()->create()->id,
            'appointment_type_id' => AppointmentType::factory()->create()->id,
            'scheduled_at' => $this->reminderNow->copy()->addDays(5)->setTime(10, 0)->format('Y-m-d H:i:s'),
            'duration_minutes' => 60,
            'status' => 'cancelled',
        ];

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/appointments', $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors('cancellation_reason');

        $this->assertSame(0, Appointment::count(), 'A rejected create MUST NOT leave an appointment behind.');

        $response = $this->actingAs($admin, 'sanctum')
            ->postJson('/api/appointments', [...$payload, 'cancellation_reason' => 'Se registra ya cancelada']);
        $response->assertCreated()->assertJsonPath('data.cancellation_reason', 'Se registra ya cancelada');

        $this->assertSame(
            'Se registra ya cancelada',
            Appointment::findOrFail((int) $response->json('data.id'))->cancellation_reason,
            'The create door MUST persist the motive in the appointment, not drop it.'
        );
    }

    public function test_the_put_door_enforces_the_same_rule(): void
    {
        $this->activeTemplates('24h', '48h', '72h');
        $appointment = $this->book($this->reminderNow->copy()->addHours(80));
        $admin = $this->admin();

        $this->actingAs($admin, 'sanctum')
            ->putJson("/api/appointments/{$appointment->id}", ['status' => 'cancelled'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('cancellation_reason');

        $this->assertSame('scheduled', $appointment->fresh()->status);
        $this->assertSame([24, 48, 72], $this->pendingHours($appointment));

        $this->actingAs($admin, 'sanctum')
            ->putJson("/api/appointments/{$appointment->id}", [
                'status' => 'cancelled',
                'cancellation_reason' => 'Cancelada desde la edición de la cita',
            ])
            ->assertOk();

        $appointment->refresh();

        $this->assertSame('cancelled', $appointment->status);
        $this->assertSame('Cancelada desde la edición de la cita', $appointment->cancellation_reason);
        $this->assertSame([], $this->pendingHours($appointment));
    }
}
