<?php

namespace Tests\Feature\Api;

use App\Models\Appointment;
use App\Models\AppointmentType;
use App\Models\DentalChair;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * RED test (calendar-scheduling-redesign T1) for
 * PATCH /api/appointments/{appointment}/reschedule.
 *
 * Acceptance:
 *  - 200 with {data, meta.message}; persists scheduled_at, ends_at, duration_minutes
 *  - 422 with structured conflict payload on overlap (conflict.type =
 *    user_conflict | chair_conflict, conflicting_appointment summary)
 *  - 422 validation error on missing fields, bad duration bounds, past datetime
 *  - 403 for roles outside the appointments group, 401 unauthenticated
 *  - the moved appointment never conflicts with its own previous slot
 *
 * Requires the MySQL runner (phpunit.mysql.xml) — see AGENTS.md §6 (decision #79).
 */
class AppointmentRescheduleTest extends TestCase
{
    use RefreshDatabase;

    private function actor(string $role = 'recepcionista'): User
    {
        return User::factory()->create([
            'role' => $role,
            'is_active' => true,
        ]);
    }

    private function dentist(): User
    {
        return User::factory()->create([
            'role' => 'odontologo',
            'is_active' => true,
        ]);
    }

    private function appointmentFor(User $dentist, DentalChair $chair, Carbon $startsAt, int $duration = 60): Appointment
    {
        return Appointment::factory()->create([
            'patient_id' => Patient::factory()->create()->id,
            'user_id' => $dentist->id,
            'dental_chair_id' => $chair->id,
            'appointment_type_id' => AppointmentType::factory()->create()->id,
            'scheduled_at' => $startsAt,
            'duration_minutes' => $duration,
        ]);
    }

    public function test_reschedule_moves_appointment_and_returns_updated_resource(): void
    {
        $actor = $this->actor();
        $dentist = $this->dentist();
        $chair = DentalChair::factory()->create();
        $appointment = $this->appointmentFor($dentist, $chair, now()->addDays(2)->setTime(10, 0));

        $newStart = now()->addDays(3)->setTime(15, 30);

        $response = $this->actingAs($actor, 'sanctum')
            ->patchJson("/api/appointments/{$appointment->id}/reschedule", [
                'scheduled_at' => $newStart->toDateTimeString(),
                'duration_minutes' => 30,
            ]);

        $response->assertOk()
            ->assertJsonStructure([
                'data' => ['id', 'scheduled_at', 'ends_at', 'duration_minutes'],
                'meta' => ['message'],
            ]);

        $this->assertSame($appointment->id, (int) $response->json('data.id'));
        $this->assertSame(30, (int) $response->json('data.duration_minutes'));

        $fresh = $appointment->fresh();
        $this->assertSame($newStart->format('Y-m-d H:i:s'), $fresh->scheduled_at->format('Y-m-d H:i:s'));
        $this->assertSame($newStart->copy()->addMinutes(30)->format('Y-m-d H:i:s'), $fresh->ends_at->format('Y-m-d H:i:s'));
        $this->assertSame(30, (int) $fresh->duration_minutes);
    }

    public function test_reschedule_overlapping_same_professional_returns_user_conflict(): void
    {
        $actor = $this->actor();
        $dentist = $this->dentist();
        $chair = DentalChair::factory()->create();
        $otherChair = DentalChair::factory()->create();
        $start = now()->addDays(2)->setTime(10, 0);

        $appointment = $this->appointmentFor($dentist, $chair, $start);
        $blocking = $this->appointmentFor($dentist, $otherChair, $start->copy()->addMinutes(30));

        $response = $this->actingAs($actor, 'sanctum')
            ->patchJson("/api/appointments/{$appointment->id}/reschedule", [
                'scheduled_at' => $start->copy()->addMinutes(30)->toDateTimeString(),
                'duration_minutes' => 60,
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('conflict.type', 'user_conflict')
            ->assertJsonStructure([
                'message',
                'errors' => ['scheduled_at'],
                'conflict' => [
                    'type',
                    'conflicting_appointment' => ['id', 'scheduled_at', 'ends_at', 'user_id', 'dental_chair_id'],
                ],
            ]);

        $this->assertSame($blocking->id, (int) $response->json('conflict.conflicting_appointment.id'));

        $this->assertSame(
            $start->format('Y-m-d H:i:s'),
            $appointment->fresh()->scheduled_at->format('Y-m-d H:i:s')
        );
    }

    public function test_reschedule_overlapping_same_chair_returns_chair_conflict(): void
    {
        $actor = $this->actor();
        $dentist = $this->dentist();
        $otherDentist = $this->dentist();
        $chair = DentalChair::factory()->create();
        $start = now()->addDays(2)->setTime(10, 0);

        $appointment = $this->appointmentFor($dentist, $chair, $start);
        $blocking = $this->appointmentFor($otherDentist, $chair, $start->copy()->addMinutes(30));

        $response = $this->actingAs($actor, 'sanctum')
            ->patchJson("/api/appointments/{$appointment->id}/reschedule", [
                'scheduled_at' => $start->copy()->addMinutes(30)->toDateTimeString(),
                'duration_minutes' => 60,
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('conflict.type', 'chair_conflict');

        $this->assertSame($blocking->id, (int) $response->json('conflict.conflicting_appointment.id'));

        $this->assertSame(
            $start->format('Y-m-d H:i:s'),
            $appointment->fresh()->scheduled_at->format('Y-m-d H:i:s')
        );
    }

    public function test_reschedule_requires_scheduled_at(): void
    {
        $actor = $this->actor();
        $dentist = $this->dentist();
        $appointment = $this->appointmentFor($dentist, DentalChair::factory()->create(), now()->addDays(2)->setTime(10, 0));

        $this->actingAs($actor, 'sanctum')
            ->patchJson("/api/appointments/{$appointment->id}/reschedule", [])
            ->assertStatus(422)
            ->assertJsonStructure(['message', 'errors' => ['scheduled_at']]);
    }

    public function test_reschedule_rejects_invalid_datetime_and_duration_bounds(): void
    {
        $actor = $this->actor();
        $dentist = $this->dentist();
        $appointment = $this->appointmentFor($dentist, DentalChair::factory()->create(), now()->addDays(2)->setTime(10, 0));

        $this->actingAs($actor, 'sanctum')
            ->patchJson("/api/appointments/{$appointment->id}/reschedule", [
                'scheduled_at' => 'not-a-date',
                'duration_minutes' => 60,
            ])
            ->assertStatus(422)
            ->assertJsonStructure(['errors' => ['scheduled_at']]);

        $this->actingAs($actor, 'sanctum')
            ->patchJson("/api/appointments/{$appointment->id}/reschedule", [
                'scheduled_at' => now()->addDays(3)->toDateTimeString(),
                'duration_minutes' => 5,
            ])
            ->assertStatus(422)
            ->assertJsonStructure(['errors' => ['duration_minutes']]);

        $this->actingAs($actor, 'sanctum')
            ->patchJson("/api/appointments/{$appointment->id}/reschedule", [
                'scheduled_at' => now()->addDays(3)->toDateTimeString(),
                'duration_minutes' => 481,
            ])
            ->assertStatus(422)
            ->assertJsonStructure(['errors' => ['duration_minutes']]);
    }

    public function test_reschedule_rejects_past_datetime(): void
    {
        $actor = $this->actor();
        $dentist = $this->dentist();
        $appointment = $this->appointmentFor($dentist, DentalChair::factory()->create(), now()->addDays(2)->setTime(10, 0));

        $this->actingAs($actor, 'sanctum')
            ->patchJson("/api/appointments/{$appointment->id}/reschedule", [
                'scheduled_at' => now()->subDay()->toDateTimeString(),
                'duration_minutes' => 60,
            ])
            ->assertStatus(422)
            ->assertJsonStructure(['errors' => ['scheduled_at']]);
    }

    public function test_reschedule_forbidden_for_roles_outside_appointments_group(): void
    {
        $actor = $this->actor('finanzas');
        $dentist = $this->dentist();
        $appointment = $this->appointmentFor($dentist, DentalChair::factory()->create(), now()->addDays(2)->setTime(10, 0));

        $this->actingAs($actor, 'sanctum')
            ->patchJson("/api/appointments/{$appointment->id}/reschedule", [
                'scheduled_at' => now()->addDays(3)->toDateTimeString(),
            ])
            ->assertForbidden();
    }

    public function test_reschedule_requires_authentication(): void
    {
        $dentist = $this->dentist();
        $appointment = $this->appointmentFor($dentist, DentalChair::factory()->create(), now()->addDays(2)->setTime(10, 0));

        $this->patchJson("/api/appointments/{$appointment->id}/reschedule", [
            'scheduled_at' => now()->addDays(3)->toDateTimeString(),
        ])->assertUnauthorized();
    }

    public function test_reschedule_does_not_conflict_with_its_own_previous_slot(): void
    {
        $actor = $this->actor();
        $dentist = $this->dentist();
        $chair = DentalChair::factory()->create();
        $start = now()->addDays(2)->setTime(10, 0);
        $appointment = $this->appointmentFor($dentist, $chair, $start, 60);

        $newStart = $start->copy()->addMinutes(30);

        $response = $this->actingAs($actor, 'sanctum')
            ->patchJson("/api/appointments/{$appointment->id}/reschedule", [
                'scheduled_at' => $newStart->toDateTimeString(),
                'duration_minutes' => 60,
            ]);

        $response->assertOk();
        $this->assertSame($appointment->id, (int) $response->json('data.id'));

        $this->assertSame(
            $newStart->format('Y-m-d H:i:s'),
            $appointment->fresh()->scheduled_at->format('Y-m-d H:i:s')
        );
    }

    public function test_reschedule_without_duration_keeps_current_duration(): void
    {
        $actor = $this->actor();
        $dentist = $this->dentist();
        $chair = DentalChair::factory()->create();
        $appointment = $this->appointmentFor($dentist, $chair, now()->addDays(2)->setTime(10, 0), 45);

        $newStart = now()->addDays(4)->setTime(9, 0);

        $response = $this->actingAs($actor, 'sanctum')
            ->patchJson("/api/appointments/{$appointment->id}/reschedule", [
                'scheduled_at' => $newStart->toDateTimeString(),
            ]);

        $response->assertOk();
        $this->assertSame(45, (int) $response->json('data.duration_minutes'));

        $fresh = $appointment->fresh();
        $this->assertSame(45, (int) $fresh->duration_minutes);
        $this->assertSame($newStart->copy()->addMinutes(45)->format('Y-m-d H:i:s'), $fresh->ends_at->format('Y-m-d H:i:s'));
    }
}
