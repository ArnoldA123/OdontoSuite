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
 * custody trail does not grow: announcing is not a transfer.
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
            ->assertJsonPath('meta.message', 'Paciente anunciado.');

        $this->assertSame('arrived', $appointment->fresh()->status);
        $this->assertSame(0, AppointmentCustodyLog::query()->count());

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
        $this->assertSame('confirmed', $appointment->fresh()->status);
    }

    public function test_a_clinical_role_cannot_announce_the_patient(): void
    {
        Event::fake([PatientReady::class]);

        $professional = $this->staff('odontologo');
        $appointment = $this->arrivedAppointment();

        $this->actingAs($professional, 'sanctum')
            ->postJson("/api/appointments/{$appointment->id}/announce")
            ->assertForbidden();

        Event::assertNotDispatched(PatientReady::class);
    }
}
