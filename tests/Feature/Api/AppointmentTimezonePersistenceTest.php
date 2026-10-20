<?php

namespace Tests\Feature\Api;

use App\Models\Appointment;
use App\Models\AppointmentType;
use App\Models\DentalChair;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Issue #166, finding 2 — `scheduled_at` must persist the same instant in the
 * application timezone whatever offset the client sends, and `ends_at` must
 * keep being `scheduled_at + duration_minutes`.
 *
 * CITAS-TZ-001 keeps the UI sending naive-local `YYYY-MM-DDTHH:mm:ss`; those
 * inputs must keep their wall clock, which is the regression case these tests
 * pin alongside the offset case.
 *
 * Needs a real schema, so Tests\TestCase skips it on any non-MySQL runner
 * (issue #170); run it with phpunit.mysql.xml — see AGENTS.md §6 (decision #79).
 */
class AppointmentTimezonePersistenceTest extends TestCase
{
    use RefreshDatabase;

    private const DURATION = 45;

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

    /**
     * A future wall-clock slot in the application timezone.
     */
    private function localSlot(): Carbon
    {
        return Carbon::now(config('app.timezone'))->addDays(30)->setTime(15, 0);
    }

    /**
     * @return array<string, mixed>
     */
    private function createPayload(User $dentist, string $value): array
    {
        return [
            'patient_id' => Patient::factory()->create(['is_active' => true])->id,
            'user_id' => $dentist->id,
            'dental_chair_id' => DentalChair::factory()->create()->id,
            'appointment_type_id' => AppointmentType::factory()->create()->id,
            'scheduled_at' => $value,
            'duration_minutes' => self::DURATION,
        ];
    }

    private function appointmentFor(User $dentist, Carbon $startsAt): Appointment
    {
        return Appointment::factory()->create([
            'patient_id' => Patient::factory()->create(['is_active' => true])->id,
            'user_id' => $dentist->id,
            'dental_chair_id' => DentalChair::factory()->create()->id,
            'appointment_type_id' => AppointmentType::factory()->create()->id,
            'scheduled_at' => $startsAt,
            'duration_minutes' => self::DURATION,
        ]);
    }

    private function assertRowHoldsSlot(int $appointmentId, Carbon $slot): void
    {
        $row = DB::table('appointments')->find($appointmentId);

        $this->assertNotNull($row, "Appointment {$appointmentId} must exist in the database.");
        $this->assertSame(
            $slot->format('Y-m-d H:i:s'),
            Carbon::parse($row->scheduled_at)->format('Y-m-d H:i:s'),
            'scheduled_at must be stored as the same instant in the application timezone.'
        );
        $this->assertSame(
            $slot->copy()->addMinutes(self::DURATION)->format('Y-m-d H:i:s'),
            Carbon::parse($row->ends_at)->format('Y-m-d H:i:s'),
            'ends_at must equal scheduled_at + duration_minutes.'
        );
    }

    public function test_create_with_offset_persists_the_same_instant_in_app_timezone(): void
    {
        $slot = $this->localSlot();
        $offsetIso = $slot->copy()->utc()->format('Y-m-d\TH:i:s.u\Z');

        $this->assertNotSame(
            $slot->format('Y-m-d H:i:s'),
            Carbon::parse($offsetIso)->format('Y-m-d H:i:s'),
            'Fixture must carry an offset that differs from the application timezone wall clock.'
        );

        $response = $this->actingAs($this->actor(), 'sanctum')
            ->postJson('/api/appointments', $this->createPayload($this->dentist(), $offsetIso));

        $response->assertCreated();
        $this->assertRowHoldsSlot((int) $response->json('data.id'), $slot);
    }

    public function test_create_with_naive_local_datetime_keeps_the_wall_clock(): void
    {
        $slot = $this->localSlot();

        $response = $this->actingAs($this->actor(), 'sanctum')
            ->postJson('/api/appointments', $this->createPayload($this->dentist(), $slot->format('Y-m-d\TH:i:s')));

        $response->assertCreated();
        $this->assertRowHoldsSlot((int) $response->json('data.id'), $slot);
    }

    public function test_update_with_offset_persists_the_same_instant_in_app_timezone(): void
    {
        $dentist = $this->dentist();
        $appointment = $this->appointmentFor($dentist, $this->localSlot()->addDays(2));
        $slot = $this->localSlot();
        $offsetIso = $slot->copy()->utc()->format('Y-m-d\TH:i:s.u\Z');

        $response = $this->actingAs($this->actor(), 'sanctum')
            ->patchJson("/api/appointments/{$appointment->id}", [
                'scheduled_at' => $offsetIso,
                'duration_minutes' => self::DURATION,
            ]);

        $response->assertOk();
        $this->assertRowHoldsSlot($appointment->id, $slot);
    }

    public function test_update_with_naive_local_datetime_keeps_the_wall_clock(): void
    {
        $dentist = $this->dentist();
        $appointment = $this->appointmentFor($dentist, $this->localSlot()->addDays(2));
        $slot = $this->localSlot();

        $response = $this->actingAs($this->actor(), 'sanctum')
            ->patchJson("/api/appointments/{$appointment->id}", [
                'scheduled_at' => $slot->format('Y-m-d\TH:i:s'),
                'duration_minutes' => self::DURATION,
            ]);

        $response->assertOk();
        $this->assertRowHoldsSlot($appointment->id, $slot);
    }

    public function test_update_of_duration_only_recomputes_ends_at_from_the_stored_start(): void
    {
        $dentist = $this->dentist();
        $slot = $this->localSlot();
        $appointment = $this->appointmentFor($dentist, $slot);

        $response = $this->actingAs($this->actor(), 'sanctum')
            ->patchJson("/api/appointments/{$appointment->id}", ['duration_minutes' => self::DURATION]);

        $response->assertOk();
        $this->assertRowHoldsSlot($appointment->id, $slot);
    }

    public function test_reschedule_with_offset_persists_the_same_instant_in_app_timezone(): void
    {
        $dentist = $this->dentist();
        $appointment = $this->appointmentFor($dentist, $this->localSlot()->addDays(2));
        $slot = $this->localSlot();
        $offsetIso = $slot->copy()->utc()->format('Y-m-d\TH:i:s.u\Z');

        $response = $this->actingAs($this->actor(), 'sanctum')
            ->patchJson("/api/appointments/{$appointment->id}/reschedule", [
                'scheduled_at' => $offsetIso,
                'duration_minutes' => self::DURATION,
            ]);

        $response->assertOk();
        $this->assertRowHoldsSlot($appointment->id, $slot);
    }

    public function test_reschedule_with_naive_local_datetime_keeps_the_wall_clock(): void
    {
        $dentist = $this->dentist();
        $appointment = $this->appointmentFor($dentist, $this->localSlot()->addDays(2));
        $slot = $this->localSlot();

        $response = $this->actingAs($this->actor(), 'sanctum')
            ->patchJson("/api/appointments/{$appointment->id}/reschedule", [
                'scheduled_at' => $slot->format('Y-m-d\TH:i:s'),
                'duration_minutes' => self::DURATION,
            ]);

        $response->assertOk();
        $this->assertRowHoldsSlot($appointment->id, $slot);
    }
}
