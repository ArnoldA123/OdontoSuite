<?php

namespace Tests\Feature\Api;

use App\Models\Appointment;
use App\Models\AppointmentCustodyLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * F1 (#281) — the professional starts the clinical act from the queue.
 *
 * `in_consultation` stops meaning "the patient arrived": it means the
 * professional is documenting the act. Reception cannot start it, and the
 * custody transfer is recorded with author, hour and door.
 */
class AppointmentCustodyAttentionTest extends TestCase
{
    use RefreshDatabase;

    private function staff(string $role): User
    {
        return User::factory()->create([
            'role' => $role,
            'is_active' => true,
        ]);
    }

    private function arrivedAppointment(): Appointment
    {
        return Appointment::factory()->create([
            'status' => 'arrived',
            'checked_in_at' => now(),
            'checked_in_by' => User::factory()->create(['role' => 'recepcionista', 'is_active' => true])->id,
            'scheduled_at' => now()->startOfDay()->addHours(9),
        ]);
    }

    public function test_the_professional_starts_the_attention_and_the_transfer_is_recorded(): void
    {
        $professional = $this->staff('odontologo');
        $appointment = $this->arrivedAppointment();

        $response = $this->actingAs($professional, 'sanctum')
            ->postJson("/api/appointments/{$appointment->id}/start-attention");

        $response->assertOk()
            ->assertJsonPath('data.status', 'in_consultation');

        $this->assertSame('in_consultation', $appointment->fresh()->status);

        $log = AppointmentCustodyLog::query()->sole();

        $this->assertSame(AppointmentCustodyLog::ACTION_ATTENTION_START, $log->action);
        $this->assertSame(AppointmentCustodyLog::DOOR_PROFESSIONAL, $log->door);
        $this->assertSame($professional->id, $log->actor_id);
        $this->assertSame('arrived', $log->from_status);
        $this->assertSame('in_consultation', $log->to_status);
    }

    public function test_reception_cannot_start_the_clinical_act(): void
    {
        $reception = $this->staff('recepcionista');
        $appointment = $this->arrivedAppointment();

        $this->actingAs($reception, 'sanctum')
            ->postJson("/api/appointments/{$appointment->id}/start-attention")
            ->assertForbidden();

        $this->assertSame('arrived', $appointment->fresh()->status);
        $this->assertSame(0, AppointmentCustodyLog::query()->count());
    }

    public function test_attention_cannot_start_before_the_arrival_is_registered(): void
    {
        $professional = $this->staff('odontologo');
        $appointment = Appointment::factory()->create([
            'status' => 'confirmed',
            'scheduled_at' => now()->startOfDay()->addHours(9),
        ]);

        $this->actingAs($professional, 'sanctum')
            ->postJson("/api/appointments/{$appointment->id}/start-attention")
            ->assertStatus(409)
            ->assertJsonPath('error.error_code', 'attention_not_allowed');

        $this->assertSame('confirmed', $appointment->fresh()->status);
        $this->assertSame(0, AppointmentCustodyLog::query()->count());
    }

    public function test_starting_twice_is_idempotent_and_records_one_transfer(): void
    {
        $professional = $this->staff('odontologo');
        $appointment = $this->arrivedAppointment();

        $this->actingAs($professional, 'sanctum')
            ->postJson("/api/appointments/{$appointment->id}/start-attention")
            ->assertOk();
        $this->actingAs($professional, 'sanctum')
            ->postJson("/api/appointments/{$appointment->id}/start-attention")
            ->assertOk();

        $this->assertSame(1, AppointmentCustodyLog::query()->count());
    }
}
