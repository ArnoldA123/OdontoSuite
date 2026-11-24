<?php

namespace Tests\Feature\Api;

use App\Events\PatientReady;
use App\Models\Appointment;
use App\Models\AppointmentCustodyLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

/**
 * F1 (#281) — announcing the patient is a notification, not a state change.
 *
 * S2 is explicit: `paciente_listo` leaves on announcement and the announcement
 * creates no state. The appointment keeps the status it had (arrived) and the
 * custody trail records the announcement as its own action (T1): the toast
 * used to confirm an act that left no visible trace.
 */
class PatientReadyAnnouncementTest extends TestCase
{
    use RefreshDatabase;

    private function staff(string $role): User
    {
        return User::factory()->create([
            'role' => $role,
            'is_active' => true,
        ]);
    }

    private function arrivedAppointment(?User $professional = null): Appointment
    {
        return Appointment::factory()->create([
            'status' => 'arrived',
            'checked_in_at' => now(),
            'scheduled_at' => now()->startOfDay()->addHours(9),
            'user_id' => $professional?->id ?? User::factory()->create([
                'role' => 'odontologo',
                'is_active' => true,
            ])->id,
        ]);
    }

    public function test_reception_announces_the_patient_without_changing_the_status(): void
    {
        Event::fake([PatientReady::class]);

        $reception = $this->staff('recepcionista');
        $appointment = $this->arrivedAppointment();

        $this->actingAs($reception, 'sanctum')
            ->postJson("/api/appointments/{$appointment->id}/announce")
            ->assertOk()
            ->assertJsonPath('meta.message', 'Paciente anunciado.')
            ->assertJsonPath('data.announced_by', $reception->id)
            ->assertJsonPath('data.announced_by_user.id', $reception->id);

        $appointment->refresh();

        $this->assertSame('arrived', $appointment->status);
        $this->assertNotNull($appointment->announced_at, 'The announcement hour MUST be stored.');
        $this->assertSame($reception->id, $appointment->announced_by);

        // Announcing is not a transfer: the row says both ends are `arrived`.
        $log = AppointmentCustodyLog::query()->sole();

        $this->assertSame($appointment->id, $log->appointment_id);
        $this->assertSame(AppointmentCustodyLog::ACTION_ANNOUNCEMENT, $log->action);
        $this->assertSame(AppointmentCustodyLog::DOOR_RECEPTION, $log->door);
        $this->assertSame($reception->id, $log->actor_id);
        $this->assertSame('arrived', $log->from_status);
        $this->assertSame('arrived', $log->to_status);

        Event::assertDispatched(
            PatientReady::class,
            fn (PatientReady $event) => $event->appointment->id === $appointment->id
                && $event->announcedBy->id === $reception->id,
        );
    }

    public function test_the_patient_ready_event_broadcasts_to_the_professional_private_channel(): void
    {
        $professional = $this->staff('odontologo');
        $appointment = $this->arrivedAppointment($professional);
        $event = new PatientReady($appointment, $this->staff('recepcionista'));

        $this->assertSame('paciente_listo', $event->broadcastAs());

        $channels = $event->broadcastOn();

        $this->assertCount(1, $channels);
        $this->assertSame("private-user.{$professional->id}", $channels[0]->name);

        $payload = $event->broadcastWith();

        $this->assertSame($appointment->id, $payload['appointment']['id']);
        $this->assertSame($professional->id, $payload['professional']['id'] ?? $appointment->user_id);
    }

    public function test_the_agenda_publishes_when_and_by_whom_the_patient_was_announced(): void
    {
        Event::fake([PatientReady::class]);

        $reception = $this->staff('recepcionista');
        $appointment = $this->arrivedAppointment();

        $this->actingAs($reception, 'sanctum')
            ->postJson("/api/appointments/{$appointment->id}/announce")
            ->assertOk();

        $row = $this->actingAs($reception, 'sanctum')
            ->getJson('/api/appointments')
            ->assertOk()
            ->json('data.0');

        $this->assertSame($appointment->id, $row['id']);
        $this->assertSame('arrived', $row['status']);
        $this->assertNotNull($row['announced_at'], 'The agenda MUST show when the announcement happened.');
        $this->assertSame($reception->id, $row['announced_by']);
    }

    public function test_the_announcement_is_rejected_before_the_arrival_exists(): void
    {
        Event::fake([PatientReady::class]);

        $reception = $this->staff('recepcionista');
        $appointment = Appointment::factory()->create([
            'status' => 'confirmed',
            'scheduled_at' => now()->startOfDay()->addHours(9),
        ]);

        $this->actingAs($reception, 'sanctum')
            ->postJson("/api/appointments/{$appointment->id}/announce")
            ->assertStatus(409)
            ->assertJsonPath('error.error_code', 'announcement_not_allowed');

        Event::assertNotDispatched(PatientReady::class);
        $appointment->refresh();
        $this->assertSame('confirmed', $appointment->status);
        $this->assertNull($appointment->announced_at);
        $this->assertSame(0, AppointmentCustodyLog::query()->count());
    }

    public function test_the_assigned_professional_announces_their_own_patient(): void
    {
        Event::fake([PatientReady::class]);

        $professional = $this->staff('odontologo');
        $appointment = $this->arrivedAppointment($professional);

        $this->actingAs($professional, 'sanctum')
            ->postJson("/api/appointments/{$appointment->id}/announce")
            ->assertOk()
            ->assertJsonPath('data.announced_by', $professional->id)
            ->assertJsonPath('data.status', 'arrived');

        $appointment->refresh();

        // Announcing stays a notification: the status is untouched and the row
        // keeps the trace T1 writes, now with the clinical door.
        $this->assertSame('arrived', $appointment->status);
        $this->assertNotNull($appointment->announced_at);
        $this->assertSame($professional->id, $appointment->announced_by);

        $log = AppointmentCustodyLog::query()->sole();

        $this->assertSame(AppointmentCustodyLog::ACTION_ANNOUNCEMENT, $log->action);
        $this->assertSame(AppointmentCustodyLog::DOOR_PROFESSIONAL, $log->door);
        $this->assertSame($professional->id, $log->actor_id);

        Event::assertDispatched(
            PatientReady::class,
            fn (PatientReady $event) => $event->appointment->id === $appointment->id
                && $event->announcedBy->id === $professional->id,
        );
    }

    public function test_a_professional_cannot_announce_someone_elses_appointment(): void
    {
        Event::fake([PatientReady::class]);

        $owner = $this->staff('odontologo');
        $other = $this->staff('odontologo');
        $appointment = $this->arrivedAppointment($owner);

        $this->actingAs($other, 'sanctum')
            ->postJson("/api/appointments/{$appointment->id}/announce")
            ->assertForbidden();

        Event::assertNotDispatched(PatientReady::class);
        $appointment->refresh();
        $this->assertNull($appointment->announced_at);
        $this->assertSame(0, AppointmentCustodyLog::query()->count());
    }

    public function test_finance_stays_out_of_the_announcement(): void
    {
        Event::fake([PatientReady::class]);

        $finance = $this->staff('finanzas');
        $appointment = $this->arrivedAppointment();

        $this->actingAs($finance, 'sanctum')
            ->postJson("/api/appointments/{$appointment->id}/announce")
            ->assertForbidden();

        Event::assertNotDispatched(PatientReady::class);
    }
}
