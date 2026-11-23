<?php

namespace Tests\Feature\Api;

use App\Models\Appointment;
use App\Models\AppointmentType;
use App\Models\DentalChair;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * A soft-deleted appointment releases the instant it reserved.
 *
 * `appointments.slot_reservation` is a stored generated column included in both
 * unique indexes (`unique_user_time_slot`, `unique_chair_time_slot`). Before
 * `2026_11_02_100000`, the expression released the slot only for `cancelled`,
 * so a soft-deleted appointment kept `1`: the model's global scope hid the row
 * from the pre-check — which therefore said the slot was free — while the
 * engine kept the key reserved and answered with a duplicate key. The rebooking
 * failed without a motive any screen could show.
 *
 * The migration redefines the expression to read `deleted_at` as well. These
 * tests pin the two doors that write the rebooking (create and reschedule), the
 * release of a row deleted before the new expression existed — the ALTER
 * rebuilds the table and recomputes it —, and the other half of the contract:
 * a deleted row frees only its own instant, so the half-open `[inicio, fin)`
 * rule still governs every live row around it.
 *
 * `DatabaseTruncation` instead of `RefreshDatabase` because the last test
 * applies DDL, which commits implicitly in MySQL and would break a wrapping
 * transaction.
 *
 * Needs a real schema, so Tests\TestCase skips it on any non-MySQL runner
 * (issue #170); run it with phpunit.mysql.xml — see AGENTS.md §6 (decision #79).
 */
class AppointmentSoftDeletedSlotTest extends TestCase
{
    use DatabaseTruncation;

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
        $response->assertStatus(422)->assertJsonValidationErrors('scheduled_at');

        $this->assertStringContainsString(
            'Conflicto de horario',
            (string) $response->json('errors.scheduled_at.0')
        );
    }

    private function reservationOf(int $appointmentId): ?int
    {
        $value = DB::table('appointments')->where('id', $appointmentId)->value('slot_reservation');

        return $value === null ? null : (int) $value;
    }

    public function test_rebooking_the_instant_of_a_soft_deleted_appointment_is_created(): void
    {
        $actor = $this->actor();
        $dentist = $this->dentist();
        $chair = DentalChair::factory()->create();

        $removed = $this->book($actor, $dentist, $chair, $this->at('09:00'));
        $removed->assertCreated();

        $removedId = (int) $removed->json('data.id');

        // The door reception uses: the row leaves the agenda, status untouched.
        $this->actingAs($actor, 'sanctum')
            ->deleteJson("/api/appointments/{$removedId}")
            ->assertOk();

        $this->assertNotNull(DB::table('appointments')->where('id', $removedId)->value('deleted_at'));
        $this->assertNull($this->reservationOf($removedId));

        // Same professional, same chair, same start and end: the instant the
        // deleted row kept reserved is free again and the unique indexes accept
        // it without a duplicate key.
        $rebooked = $this->book($actor, $dentist, $chair, $this->at('09:00'));
        $rebooked->assertCreated();

        $this->assertSame(2, Appointment::withTrashed()->count());
        $this->assertSame(1, Appointment::count());
        $this->assertSame(
            $this->at('09:00')->format('Y-m-d H:i:s'),
            Appointment::findOrFail((int) $rebooked->json('data.id'))->scheduled_at->format('Y-m-d H:i:s')
        );
    }

    public function test_a_soft_deleted_appointment_releases_only_its_own_instant(): void
    {
        $actor = $this->actor();
        $dentist = $this->dentist();
        $chair = DentalChair::factory()->create();

        $removed = $this->book($actor, $dentist, $chair, $this->at('09:00'));
        $removed->assertCreated();
        Appointment::findOrFail((int) $removed->json('data.id'))->delete();

        // The instant is free for the same interval...
        $live = $this->book($actor, $dentist, $chair, $this->at('09:00'));
        $live->assertCreated();

        // ...but the live booking that took it now owns the slot: the
        // half-open rule still rejects the partial overlap and still accepts
        // the adjacent one.
        $this->assertConflict($this->book($actor, $dentist, $chair, $this->at('09:15')));

        $adjacent = $this->book($actor, $dentist, $chair, $this->at('09:30'));
        $adjacent->assertCreated();

        $this->assertSame(1, Appointment::withTrashed()->onlyTrashed()->count());
        $this->assertSame(2, Appointment::count());
    }

    /**
     * The ALTER rebuilds the table, so a row written under the previous
     * expression is recomputed when the new one is applied. The old expression
     * is restored first to reproduce those rows, exactly as they were stored
     * before this migration.
     */
    public function test_the_alter_recomputes_the_rows_already_stored(): void
    {
        $migration = require database_path('migrations/2026_11_02_100000_release_slot_reservation_on_soft_delete.php');

        $dentist = $this->dentist();
        $chair = DentalChair::factory()->create();

        // Back to the cancellation-only expression.
        $migration->down();

        $appointment = null;

        try {
            $appointment = Appointment::factory()->create([
                'patient_id' => Patient::factory()->create()->id,
                'user_id' => $dentist->id,
                'dental_chair_id' => $chair->id,
                'appointment_type_id' => AppointmentType::factory()->create()->id,
                'scheduled_at' => $this->at('09:00'),
                'ends_at' => $this->at('09:30'),
                'duration_minutes' => 30,
            ]);

            $appointment->delete();

            // Under the old expression a deleted row still reserves its key.
            $this->assertSame(1, $this->reservationOf($appointment->id));
        } finally {
            $migration->up();
        }

        // The same row, recomputed by the ALTER, no longer reserves it.
        $this->assertNotNull($appointment);
        $this->assertNull($this->reservationOf($appointment->id));

        $rebooked = $this->book($this->actor(), $dentist, $chair, $this->at('09:00'));
        $rebooked->assertCreated();
    }
}
