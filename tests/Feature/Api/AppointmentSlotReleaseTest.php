<?php

namespace Tests\Feature\Api;

use App\Models\Appointment;
use App\Models\AppointmentType;
use App\Models\DentalChair;
use App\Models\Patient;
use App\Models\User;
use App\Services\AppointmentService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use PDOException;
use Tests\TestCase;

/**
 * Issue #392 — a cancelled appointment releases the slot it occupied.
 *
 * `AppointmentRepository::findConflicts` has always excluded `cancelled` from
 * the overlap check, but `unique_user_time_slot` and `unique_chair_time_slot`
 * covered every row, cancellation included. Cancelling an appointment and
 * booking the slot it freed therefore passed the pre-check and died on the
 * unique index with SQLSTATE 23000, which the reschedule door turned into a
 * 500.
 *
 * The rule now lives in the schema and nowhere else: `appointments.slot_reservation`
 * is a stored generated column that is NULL for a cancelled row and 1 for any
 * other status, and both unique indexes include it (MySQL/MariaDB treat NULLs
 * in a unique index as distinct). These tests pin the behaviour of the two
 * doors that wrote the slot (create and reschedule), the chair rule in
 * isolation, the reverse direction (a live appointment still reserves its
 * slot), the schema contract itself, and the reactivation of a cancelled
 * appointment whose slot was retaken meanwhile.
 *
 * Needs a real schema, so Tests\TestCase skips it on any non-MySQL runner
 * (issue #170); run it with phpunit.mysql.xml — see AGENTS.md §6 (decision #79).
 */
class AppointmentSlotReleaseTest extends TestCase
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

    /**
     * Cancel through the status column, which is what the generated column
     * reads: the appointment keeps its row, its professional and its chair.
     */
    private function cancelledAppointmentFor(User $dentist, DentalChair $chair, Carbon $startsAt, int $duration = 60): Appointment
    {
        $appointment = $this->appointmentFor($dentist, $chair, $startsAt, $duration);
        $appointment->update(['status' => 'cancelled']);

        return $appointment->fresh();
    }

    public function test_reschedule_takes_the_slot_of_a_cancelled_appointment_of_the_same_professional(): void
    {
        $actor = $this->actor();
        $dentist = $this->dentist();
        $slot = now()->addDays(3)->setTime(10, 0);

        $cancelled = $this->cancelledAppointmentFor($dentist, DentalChair::factory()->create(), $slot);
        $live = $this->appointmentFor($dentist, DentalChair::factory()->create(), now()->addDays(4)->setTime(9, 0));

        $response = $this->actingAs($actor, 'sanctum')
            ->patchJson("/api/appointments/{$live->id}/reschedule", [
                'scheduled_at' => $slot->toDateTimeString(),
                'duration_minutes' => 60,
            ]);

        $response->assertOk();

        $moved = $live->fresh();
        $this->assertSame($live->id, (int) $response->json('data.id'));
        $this->assertSame($slot->format('Y-m-d H:i:s'), $moved->scheduled_at->format('Y-m-d H:i:s'));
        $this->assertSame($slot->copy()->addMinutes(60)->format('Y-m-d H:i:s'), $moved->ends_at->format('Y-m-d H:i:s'));

        $this->assertNotSame($cancelled->id, $moved->id);
        $this->assertSame('cancelled', $cancelled->fresh()->status);
    }

    public function test_create_takes_the_slot_of_a_cancelled_appointment_of_the_same_professional(): void
    {
        $actor = $this->actor();
        $dentist = $this->dentist();
        $chair = DentalChair::factory()->create();
        $slot = now()->addDays(3)->setTime(10, 0);

        $cancelled = $this->cancelledAppointmentFor($dentist, DentalChair::factory()->create(), $slot);

        $response = $this->actingAs($actor, 'sanctum')
            ->postJson('/api/appointments', [
                'patient_id' => Patient::factory()->create()->id,
                'user_id' => $dentist->id,
                'dental_chair_id' => $chair->id,
                'appointment_type_id' => AppointmentType::factory()->create()->id,
                'scheduled_at' => $slot->toDateTimeString(),
                'duration_minutes' => 60,
            ]);

        $response->assertCreated();

        $created = Appointment::findOrFail((int) $response->json('data.id'));
        $this->assertSame($slot->format('Y-m-d H:i:s'), $created->scheduled_at->format('Y-m-d H:i:s'));
        $this->assertSame($chair->id, $created->dental_chair_id);
        $this->assertNotSame($cancelled->id, $created->id);
    }

    public function test_chair_rule_is_released_on_its_own_even_for_another_professional(): void
    {
        $actor = $this->actor();
        $dentist = $this->dentist();
        $otherDentist = $this->dentist();
        $chair = DentalChair::factory()->create();
        $slot = now()->addDays(3)->setTime(10, 0);

        // Reschedule moves time, never the chair: the live appointment already
        // sits on the chair the cancelled one freed, so only the chair index
        // can stand in its way.
        $cancelled = $this->cancelledAppointmentFor($otherDentist, $chair, $slot);
        $live = $this->appointmentFor($dentist, $chair, now()->addDays(4)->setTime(9, 0));

        $response = $this->actingAs($actor, 'sanctum')
            ->patchJson("/api/appointments/{$live->id}/reschedule", [
                'scheduled_at' => $slot->toDateTimeString(),
                'duration_minutes' => 60,
            ]);

        $response->assertOk();

        $moved = $live->fresh();
        $this->assertSame($chair->id, $moved->dental_chair_id);
        $this->assertSame($slot->format('Y-m-d H:i:s'), $moved->scheduled_at->format('Y-m-d H:i:s'));
        $this->assertSame('cancelled', $cancelled->fresh()->status);
    }

    public function test_a_live_appointment_still_reserves_its_slot(): void
    {
        $actor = $this->actor();
        $dentist = $this->dentist();
        $slot = now()->addDays(3)->setTime(10, 0);

        $blocking = $this->appointmentFor($dentist, DentalChair::factory()->create(), $slot);
        $live = $this->appointmentFor($dentist, DentalChair::factory()->create(), now()->addDays(4)->setTime(9, 0));

        $response = $this->actingAs($actor, 'sanctum')
            ->patchJson("/api/appointments/{$live->id}/reschedule", [
                'scheduled_at' => $slot->toDateTimeString(),
                'duration_minutes' => 60,
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('conflict.type', 'user_conflict')
            ->assertJsonStructure([
                'message',
                'errors' => ['scheduled_at'],
                'conflict' => ['type', 'conflicting_appointment' => ['id']],
            ]);

        $this->assertSame($blocking->id, (int) $response->json('conflict.conflicting_appointment.id'));

        $this->assertSame(
            now()->addDays(4)->setTime(9, 0)->format('Y-m-d H:i:s'),
            $live->fresh()->scheduled_at->format('Y-m-d H:i:s')
        );
    }

    public function test_the_database_declares_the_slot_rule_over_a_generated_column(): void
    {
        $dentist = $this->dentist();
        $cancelled = $this->cancelledAppointmentFor($dentist, DentalChair::factory()->create(), now()->addDays(3)->setTime(10, 0));

        // Cancelling keeps the row: the slot is released, the appointment is not deleted.
        $this->assertNotNull(Appointment::find($cancelled->id));
        $this->assertSame('cancelled', Appointment::find($cancelled->id)->status);

        $columns = collect(Schema::getColumns('appointments'))->keyBy('name');
        $this->assertArrayHasKey('slot_reservation', $columns->all());
        $this->assertSame('stored', $columns['slot_reservation']['generation']['type'] ?? null);
        $this->assertStringContainsString(
            'cancelled',
            strtolower((string) ($columns['slot_reservation']['generation']['expression'] ?? ''))
        );

        $indexes = collect(Schema::getIndexes('appointments'))->keyBy('name');

        $this->assertArrayHasKey('unique_user_time_slot', $indexes->all());
        $this->assertTrue($indexes['unique_user_time_slot']['unique']);
        $this->assertSame(
            ['user_id', 'scheduled_at', 'ends_at', 'slot_reservation'],
            $indexes['unique_user_time_slot']['columns']
        );

        $this->assertArrayHasKey('unique_chair_time_slot', $indexes->all());
        $this->assertTrue($indexes['unique_chair_time_slot']['unique']);
        $this->assertSame(
            ['dental_chair_id', 'scheduled_at', 'ends_at', 'slot_reservation'],
            $indexes['unique_chair_time_slot']['columns']
        );
    }

    public function test_reactivating_a_cancelled_appointment_whose_slot_was_retaken_is_a_conflict(): void
    {
        $actor = $this->actor();
        $dentist = $this->dentist();
        $slot = now()->addDays(3)->setTime(10, 0);

        $cancelled = $this->cancelledAppointmentFor($dentist, DentalChair::factory()->create(), $slot);
        $this->appointmentFor($dentist, DentalChair::factory()->create(), $slot);

        $response = $this->actingAs($actor, 'sanctum')
            ->patchJson("/api/appointments/{$cancelled->id}/status", ['status' => 'scheduled']);

        $response->assertStatus(422)
            ->assertJsonPath('conflict.type', 'slot_taken')
            ->assertJsonStructure(['message', 'errors' => ['scheduled_at']]);

        $this->assertSame('cancelled', $cancelled->fresh()->status);
    }

    public function test_reschedule_translates_a_slot_uniqueness_violation_into_a_conflict(): void
    {
        $actor = $this->actor();
        $dentist = $this->dentist();
        $slot = now()->addDays(3)->setTime(10, 0);
        $live = $this->appointmentFor($dentist, DentalChair::factory()->create(), now()->addDays(4)->setTime(9, 0));

        // The pre-check answered "free" and the engine answered "taken" — the
        // window a concurrent booking opens. It must surface as a conflict.
        $this->mock(AppointmentService::class, function ($mock) {
            $mock->shouldReceive('checkForConflicts')->once()->andReturn(null);
            $mock->shouldReceive('rescheduleAppointment')->once()->andThrow($this->duplicateSlotKey());
        });

        $response = $this->actingAs($actor, 'sanctum')
            ->patchJson("/api/appointments/{$live->id}/reschedule", [
                'scheduled_at' => $slot->toDateTimeString(),
                'duration_minutes' => 60,
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('conflict.type', 'slot_taken')
            ->assertJsonPath('conflict.conflicting_appointment', null)
            ->assertJsonStructure(['message', 'errors' => ['scheduled_at']]);

        $this->assertSame(
            now()->addDays(4)->setTime(9, 0)->format('Y-m-d H:i:s'),
            $live->fresh()->scheduled_at->format('Y-m-d H:i:s')
        );
    }

    private function duplicateSlotKey(): QueryException
    {
        $previous = new PDOException(
            'SQLSTATE[23000]: Integrity constraint violation: 1062 Duplicate entry '
            ."'7-2026-11-01 10:00:00-2026-11-01 11:00:00-1' for key 'appointments.unique_user_time_slot'"
        );
        $previous->errorInfo = ['23000', 1062, 'Duplicate entry'];

        return new QueryException(
            'mysql',
            'update `appointments` set `scheduled_at` = ? where `id` = ?',
            [],
            $previous
        );
    }
}
