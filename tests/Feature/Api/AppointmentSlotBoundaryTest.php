<?php

namespace Tests\Feature\Api;

use App\Models\Appointment;
use App\Models\AppointmentType;
use App\Models\DentalChair;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * The slot rule is a half-open interval `[inicio, fin)`:
 *
 *   existing.scheduled_at < new.ends_at   AND   existing.ends_at > new.scheduled_at
 *
 * `AppointmentRepository::findConflicts` compared with `whereBetween`, which
 * includes both bounds, so an appointment ending at 09:30 was read as a clash
 * with a new one starting at 09:30 — for the professional and for the chair,
 * on every door that asks the repository (create, update, reschedule).
 *
 * These tests pin both sides of the boundary: 09:00–09:30 and 09:30–10:00
 * coexist in either creation order, while a partial overlap (09:00–09:30 and
 * 09:15–09:45) and an identical start are still conflicts. The chair mirrors
 * the professional because the rule is written once, and a cancelled
 * appointment still releases its slot (issue #392) — including a new booking
 * that overlaps it.
 *
 * Needs a real schema, so Tests\TestCase skips it on any non-MySQL runner
 * (issue #170); run it with phpunit.mysql.xml — see AGENTS.md §6 (decision #79).
 */
class AppointmentSlotBoundaryTest extends TestCase
{
    use RefreshDatabase;

    private function actor(): User
    {
        return User::factory()->create([
            'role' => 'recepcionista',
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

    /** A working day far enough ahead for the future rule of the doors. */
    private function day(): Carbon
    {
        return now()->addDays(5)->startOfDay();
    }

    private function at(string $clock): Carbon
    {
        [$hour, $minute] = array_map('intval', explode(':', $clock));

        return $this->day()->copy()->setTime($hour, $minute);
    }

    private function book(
        User $actor,
        User $dentist,
        DentalChair $chair,
        Carbon $startsAt,
        int $duration = 30
    ): TestResponse {
        return $this->actingAs($actor, 'sanctum')
            ->postJson('/api/appointments', [
                'patient_id' => Patient::factory()->create()->id,
                'user_id' => $dentist->id,
                'dental_chair_id' => $chair->id,
                'appointment_type_id' => AppointmentType::factory()->create()->id,
                'scheduled_at' => $startsAt->toDateTimeString(),
                'duration_minutes' => $duration,
            ]);
    }

    private function assertConflict(TestResponse $response): void
    {
        $response->assertStatus(422)
            ->assertJsonValidationErrors('scheduled_at');

        $this->assertStringContainsString(
            'Conflicto de horario',
            (string) $response->json('errors.scheduled_at.0')
        );
    }

    public function test_an_appointment_can_start_exactly_when_another_ends(): void
    {
        $actor = $this->actor();
        $dentist = $this->dentist();
        $chair = DentalChair::factory()->create();

        $first = $this->book($actor, $dentist, $chair, $this->at('09:00'));
        $first->assertCreated();

        $second = $this->book($actor, $dentist, $chair, $this->at('09:30'));
        $second->assertCreated();

        $this->assertSame(
            $this->at('09:30')->format('Y-m-d H:i:s'),
            Appointment::findOrFail((int) $first->json('data.id'))->ends_at->format('Y-m-d H:i:s')
        );
        $this->assertSame(2, Appointment::count());
    }

    public function test_the_adjacent_pair_is_accepted_in_the_reverse_creation_order(): void
    {
        $actor = $this->actor();
        $dentist = $this->dentist();
        $chair = DentalChair::factory()->create();

        $later = $this->book($actor, $dentist, $chair, $this->at('09:30'));
        $later->assertCreated();

        $earlier = $this->book($actor, $dentist, $chair, $this->at('09:00'));
        $earlier->assertCreated();

        $this->assertSame(2, Appointment::count());
        $this->assertSame(
            [$this->at('09:00')->format('Y-m-d H:i:s'), $this->at('09:30')->format('Y-m-d H:i:s')],
            Appointment::orderBy('scheduled_at')->pluck('scheduled_at')
                ->map(fn ($value) => Carbon::parse($value)->format('Y-m-d H:i:s'))
                ->all()
        );
    }

    public function test_a_partial_overlap_is_still_a_conflict(): void
    {
        $actor = $this->actor();
        $dentist = $this->dentist();
        $chair = DentalChair::factory()->create();

        $this->book($actor, $dentist, $chair, $this->at('09:00'))->assertCreated();

        $overlapping = $this->book($actor, $dentist, $chair, $this->at('09:15'), 30);

        $this->assertConflict($overlapping);
        $this->assertSame(1, Appointment::count());
    }

    public function test_the_same_start_is_still_a_conflict(): void
    {
        $actor = $this->actor();
        $dentist = $this->dentist();
        $chair = DentalChair::factory()->create();

        $this->book($actor, $dentist, $chair, $this->at('09:00'))->assertCreated();

        $this->assertConflict($this->book($actor, $dentist, $chair, $this->at('09:00'), 15));
        $this->assertSame(1, Appointment::count());
    }

    public function test_a_reschedule_can_land_exactly_on_the_end_of_another_appointment(): void
    {
        $actor = $this->actor();
        $dentist = $this->dentist();
        $chair = DentalChair::factory()->create();

        $this->book($actor, $dentist, $chair, $this->at('09:00'))->assertCreated();

        $moving = Appointment::factory()->create([
            'patient_id' => Patient::factory()->create()->id,
            'user_id' => $dentist->id,
            'dental_chair_id' => $chair->id,
            'appointment_type_id' => AppointmentType::factory()->create()->id,
            'scheduled_at' => $this->at('11:00'),
            'duration_minutes' => 30,
        ]);

        $response = $this->actingAs($actor, 'sanctum')
            ->patchJson("/api/appointments/{$moving->id}/reschedule", [
                'scheduled_at' => $this->at('09:30')->toDateTimeString(),
                'duration_minutes' => 30,
            ]);

        $response->assertOk();

        $moved = $moving->fresh();
        $this->assertSame($this->at('09:30')->format('Y-m-d H:i:s'), $moved->scheduled_at->format('Y-m-d H:i:s'));
        $this->assertSame($this->at('10:00')->format('Y-m-d H:i:s'), $moved->ends_at->format('Y-m-d H:i:s'));
    }

    public function test_the_chair_accepts_the_adjacent_slot_of_another_professionals_appointment(): void
    {
        $actor = $this->actor();
        $first = $this->dentist();
        $second = $this->dentist();
        $chair = DentalChair::factory()->create();

        // Different professional, same chair: only the chair rule can answer.
        $this->book($actor, $first, $chair, $this->at('09:00'))->assertCreated();
        $this->book($actor, $second, $chair, $this->at('09:30'))->assertCreated();

        $this->assertSame(2, Appointment::count());
    }

    public function test_the_chair_rejects_a_partial_overlap_of_another_professionals_appointment(): void
    {
        $actor = $this->actor();
        $first = $this->dentist();
        $second = $this->dentist();
        $chair = DentalChair::factory()->create();

        $this->book($actor, $first, $chair, $this->at('09:00'))->assertCreated();

        $this->assertConflict($this->book($actor, $second, $chair, $this->at('09:15'), 30));
        $this->assertSame(1, Appointment::count());
    }

    public function test_the_chair_rejects_a_second_appointment_at_the_same_start(): void
    {
        $actor = $this->actor();
        $first = $this->dentist();
        $second = $this->dentist();
        $chair = DentalChair::factory()->create();

        $this->book($actor, $first, $chair, $this->at('09:00'))->assertCreated();

        $this->assertConflict($this->book($actor, $second, $chair, $this->at('09:00'), 30));
        $this->assertSame(1, Appointment::count());
    }

    public function test_a_containing_appointment_is_still_a_conflict_in_both_directions(): void
    {
        $actor = $this->actor();
        $dentist = $this->dentist();
        $chair = DentalChair::factory()->create();

        // 09:00–10:00 already holds the whole 09:15–09:45 window: contained.
        $this->book($actor, $dentist, $chair, $this->at('09:00'), 60)->assertCreated();
        $this->assertConflict($this->book($actor, $dentist, $chair, $this->at('09:15'), 30));

        // 10:15–10:45 is adjacent to the 09:00–10:00 one, so it is accepted;
        // 10:00–11:00 would swallow it whole: containing.
        $this->book($actor, $dentist, $chair, $this->at('10:15'), 30)->assertCreated();
        $this->assertConflict($this->book($actor, $dentist, $chair, $this->at('10:00'), 60));

        $this->assertSame(2, Appointment::count());
    }

    public function test_a_cancelled_appointment_still_releases_its_slot(): void
    {
        $actor = $this->actor();
        $dentist = $this->dentist();
        $chair = DentalChair::factory()->create();

        $cancelled = [];

        foreach ([['09:00', '09:30'], ['11:00', '11:30']] as [$from, $to]) {
            $cancelled[] = Appointment::factory()->create([
                'patient_id' => Patient::factory()->create()->id,
                'user_id' => $dentist->id,
                'dental_chair_id' => $chair->id,
                'appointment_type_id' => AppointmentType::factory()->create()->id,
                'scheduled_at' => $this->at($from),
                'ends_at' => $this->at($to),
                'duration_minutes' => 30,
                'status' => 'cancelled',
            ]);
        }

        // A slot that overlaps the cancelled one, and the slot that starts
        // exactly when it ends: both are free, because a released appointment
        // is not part of the population the rule reads.
        $this->book($actor, $dentist, $chair, $this->at('09:15'))->assertCreated();
        $this->book($actor, $dentist, $chair, $this->at('11:30'))->assertCreated();

        foreach ($cancelled as $appointment) {
            $this->assertSame('cancelled', $appointment->fresh()->status);
        }

        $this->assertSame(4, Appointment::count());
    }

    public function test_the_unique_indexes_key_on_the_exact_slot_so_adjacency_cannot_collide(): void
    {
        $dentist = $this->dentist();
        $chair = DentalChair::factory()->create();

        // Two adjacent rows written straight to the engine: the unique indexes
        // must not read them as the same slot.
        foreach ([['09:00', '09:30'], ['09:30', '10:00']] as [$from, $to]) {
            Appointment::factory()->create([
                'patient_id' => Patient::factory()->create()->id,
                'user_id' => $dentist->id,
                'dental_chair_id' => $chair->id,
                'appointment_type_id' => AppointmentType::factory()->create()->id,
                'scheduled_at' => $this->at($from),
                'ends_at' => $this->at($to),
                'duration_minutes' => 30,
            ]);
        }

        $this->assertSame(2, Appointment::where('user_id', $dentist->id)->count());

        $indexes = collect(Schema::getIndexes('appointments'))->keyBy('name');

        // The indexes encode the exact slot (start + end + reservation), not
        // the start alone, so adjacency is never a duplicate key.
        $this->assertSame(
            ['user_id', 'scheduled_at', 'ends_at', 'slot_reservation'],
            $indexes['unique_user_time_slot']['columns']
        );
        $this->assertSame(
            ['dental_chair_id', 'scheduled_at', 'ends_at', 'slot_reservation'],
            $indexes['unique_chair_time_slot']['columns']
        );
    }
}
