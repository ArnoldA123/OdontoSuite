<?php

namespace Tests\Feature\Api;

use App\Models\Appointment;
use App\Models\AppointmentCustodyLog;
use App\Models\AppointmentType;
use App\Models\DentalChair;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * F1 (#281) — each custody status has exactly one door.
 *
 * `PATCH /appointments/{id}/status` used to move an appointment into any
 * status, including the three that belong to the custody flow. That made
 * reception able to close the clinical act and made `arrived` meaningless.
 * The generic endpoint keeps the operational statuses (confirm, cancel, mark
 * no-show, reschedule) and answers 422 naming the real door for the rest.
 */
class AppointmentStatusDoorGuardTest extends TestCase
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

    public function test_the_generic_status_endpoint_refuses_the_custody_statuses_with_the_real_door(): void
    {
        $professional = $this->staff('odontologo');
        $appointment = $this->appointment();

        $cases = [
            'arrived' => 'La llegada la registra recepción con POST /api/appointments/'.$appointment->id.'/check-in.',
            'in_consultation' => 'La atención la inicia el profesional con POST /api/appointments/'.$appointment->id.'/start-attention.',
            'completed' => 'El cierre del acto clínico se registra con POST /api/appointments/'.$appointment->id.'/complete.',
        ];

        foreach ($cases as $status => $message) {
            $this->actingAs($professional, 'sanctum')
                ->patchJson("/api/appointments/{$appointment->id}/status", ['status' => $status])
                ->assertStatus(422)
                ->assertJsonPath('message', $message);

            $this->assertSame('confirmed', $appointment->fresh()->status);
        }

        $this->assertSame(0, AppointmentCustodyLog::query()->count());
    }

    public function test_reception_cannot_close_the_clinical_act_through_the_generic_endpoint(): void
    {
        $reception = $this->staff('recepcionista');
        $appointment = $this->appointment();

        $this->actingAs($reception, 'sanctum')
            ->patchJson("/api/appointments/{$appointment->id}/status", ['status' => 'completed'])
            ->assertStatus(422);

        $this->assertSame('confirmed', $appointment->fresh()->status);
    }

    public function test_the_operational_statuses_still_work_through_the_generic_endpoint(): void
    {
        $reception = $this->staff('recepcionista');
        $appointment = $this->appointment('scheduled');

        $this->actingAs($reception, 'sanctum')
            ->patchJson("/api/appointments/{$appointment->id}/status", ['status' => 'confirmed'])
            ->assertOk()
            ->assertJsonPath('data.status', 'confirmed');

        $this->actingAs($reception, 'sanctum')
            ->patchJson("/api/appointments/{$appointment->id}/status", ['status' => 'cancelled'])
            ->assertOk();

        $this->assertSame('cancelled', $appointment->fresh()->status);
    }

    public function test_appointment_create_and_update_cannot_write_a_custody_status(): void
    {
        $reception = $this->staff('recepcionista');

        $payload = [
            'patient_id' => Patient::factory()->create()->id,
            'user_id' => $this->staff('odontologo')->id,
            'dental_chair_id' => DentalChair::factory()->create()->id,
            'appointment_type_id' => AppointmentType::factory()->create()->id,
            'scheduled_at' => now()->addDay()->format('Y-m-d H:i:s'),
            'duration_minutes' => 60,
            'status' => 'completed',
        ];

        foreach (['arrived', 'in_consultation', 'completed'] as $status) {
            $payload['status'] = $status;

            $this->actingAs($reception, 'sanctum')
                ->postJson('/api/appointments', $payload)
                ->assertStatus(422)
                ->assertJsonValidationErrors('status');
        }

        $appointment = $this->appointment();

        $this->actingAs($reception, 'sanctum')
            ->putJson("/api/appointments/{$appointment->id}", ['status' => 'completed'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('status');

        $this->assertSame('confirmed', $appointment->fresh()->status);
    }
}
