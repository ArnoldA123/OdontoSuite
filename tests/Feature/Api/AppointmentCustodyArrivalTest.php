<?php

namespace Tests\Feature\Api;

use App\Models\Appointment;
use App\Models\AppointmentCustodyLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * F1 (#281) — arrival is reception's act, with author, hour and door.
 *
 * S1 keeps `arrived` apart from `in_consultation`: marking the patient's
 * presence is not the same act as starting the clinical work. S4 adds the
 * fallback: a clinical user registers the arrival when reception is absent,
 * and the record says who did it. S7 makes every custody change auditable.
 */
class AppointmentCustodyArrivalTest extends TestCase
{
    use RefreshDatabase;

    private function staff(string $role): User
    {
        return User::factory()->create([
            'role' => $role,
            'is_active' => true,
        ]);
    }

    private function appointment(string $status = 'confirmed'): Appointment
    {
        return Appointment::factory()->create([
            'status' => $status,
            'scheduled_at' => now()->startOfDay()->addHours(9),
        ]);
    }

    public function test_reception_registers_arrival_with_author_hour_and_door(): void
    {
        $reception = $this->staff('recepcionista');
        $appointment = $this->appointment();

        $response = $this->actingAs($reception, 'sanctum')
            ->postJson("/api/appointments/{$appointment->id}/check-in");

        $response->assertOk()
            ->assertJsonPath('data.status', 'arrived')
            ->assertJsonPath('data.checked_in_by', $reception->id);

        $appointment->refresh();

        $this->assertSame('arrived', $appointment->status);
        $this->assertNotNull($appointment->checked_in_at, 'The arrival hour MUST be stored.');
        $this->assertSame($reception->id, $appointment->checked_in_by);

        $log = AppointmentCustodyLog::query()->sole();

        $this->assertSame($appointment->id, $log->appointment_id);
        $this->assertSame(AppointmentCustodyLog::ACTION_ARRIVAL, $log->action);
        $this->assertSame(AppointmentCustodyLog::DOOR_RECEPTION, $log->door);
        $this->assertSame($reception->id, $log->actor_id);
        $this->assertSame('confirmed', $log->from_status);
        $this->assertSame('arrived', $log->to_status);
        $this->assertNotNull($log->created_at, 'The custody record MUST keep its hour.');
    }

    public function test_the_professional_can_register_the_arrival_when_reception_is_absent(): void
    {
        $professional = $this->staff('odontologo');
        $appointment = $this->appointment('scheduled');

        $this->actingAs($professional, 'sanctum')
            ->postJson("/api/appointments/{$appointment->id}/check-in")
            ->assertOk()
            ->assertJsonPath('data.status', 'arrived');

        $log = AppointmentCustodyLog::query()->sole();

        $this->assertSame(AppointmentCustodyLog::DOOR_PROFESSIONAL, $log->door);
        $this->assertSame($professional->id, $log->actor_id);
        $this->assertSame($professional->id, $appointment->fresh()->checked_in_by);
    }

    public function test_arrival_is_idempotent_and_never_duplicates_the_custody_record(): void
    {
        $reception = $this->staff('recepcionista');
        $appointment = $this->appointment();

        $this->actingAs($reception, 'sanctum')
            ->postJson("/api/appointments/{$appointment->id}/check-in")
            ->assertOk();
        $this->actingAs($reception, 'sanctum')
            ->postJson("/api/appointments/{$appointment->id}/check-in")
            ->assertOk();

        $this->assertSame(1, AppointmentCustodyLog::query()->count());
    }

    public function test_arrival_from_an_invalid_status_is_rejected_without_writing_anything(): void
    {
        $reception = $this->staff('recepcionista');
        $appointment = $this->appointment('completed');

        $this->actingAs($reception, 'sanctum')
            ->postJson("/api/appointments/{$appointment->id}/check-in")
            ->assertStatus(409)
            ->assertJsonPath('error.error_code', 'arrival_not_allowed');

        $this->assertSame('completed', $appointment->fresh()->status);
        $this->assertSame(0, AppointmentCustodyLog::query()->count());
    }

    public function test_a_role_without_the_arrival_permission_cannot_register_it(): void
    {
        $finance = $this->staff('finanzas');
        $appointment = $this->appointment();

        $this->actingAs($finance, 'sanctum')
            ->postJson("/api/appointments/{$appointment->id}/check-in")
            ->assertForbidden();

        $this->assertSame('confirmed', $appointment->fresh()->status);
        $this->assertSame(0, AppointmentCustodyLog::query()->count());
    }
}
