<?php

namespace Tests\Feature\Api;

use App\Models\Appointment;
use App\Models\AppointmentType;
use App\Models\ContinuityRequest;
use App\Models\DentalChair;
use App\Models\Patient;
use App\Models\PatientRecall;
use App\Models\User;
use App\Services\ContinuityService;
use App\Services\RecallService;
use App\Support\SlotConflict;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PDOException;
use Tests\TestCase;

/**
 * #504 — the agenda's unique slot key is a backstop the pre-check cannot see.
 *
 * A soft-deleted appointment used to keep its generated reservation, so the
 * recall and continuity doors reached the engine with a slot the read had
 * already declared free. The engine answered duplicate key and the door turned
 * it into a 500. These tests pin both halves: the release migration frees a
 * deleted row, and a collision that still reaches a door answers the agenda
 * contract (422, `conflict.type = slot_taken`) without writing anything.
 *
 * Needs a real schema, so Tests\TestCase skips it on any non-MySQL runner
 * (issue #170); run it with phpunit.mysql.xml — see AGENTS.md §6 (decision #79).
 */
class SlotCollisionDoorsTest extends TestCase
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

    private function day(): Carbon
    {
        return now()->addDays(5)->startOfDay();
    }

    private function at(string $clock): Carbon
    {
        [$hour, $minute] = array_map('intval', explode(':', $clock));

        return $this->day()->copy()->setTime($hour, $minute);
    }

    private function recall(User $actor, Patient $patient): PatientRecall
    {
        return PatientRecall::create([
            'patient_id' => $patient->id,
            'interval_months' => 6,
            'reason' => 'Control semestral.',
            'due_date' => $this->day()->toDateString(),
            'status' => PatientRecall::STATUS_PENDING,
            'created_by' => $actor->id,
        ]);
    }

    private function continuityRequest(User $actor, Patient $patient, User $dentist): ContinuityRequest
    {
        return ContinuityRequest::create([
            'patient_id' => $patient->id,
            'appointment_type_id' => AppointmentType::factory()->create()->id,
            'kind' => ContinuityRequest::KIND_NEXT_SESSION,
            'status' => ContinuityRequest::STATUS_PENDING,
            'preferred_from' => $this->at('09:00'),
            'preferred_to' => $this->at('09:30'),
            'duration_minutes' => 30,
            'created_by' => $dentist->id,
            'notes' => 'Control.',
        ]);
    }

    /**
     * @param array<string, mixed> $slot
     */
    private function scheduleRecall(User $actor, PatientRecall $recall, array $slot)
    {
        return $this->actingAs($actor, 'sanctum')
            ->postJson("/api/patient-recalls/{$recall->id}/schedule", $slot);
    }

    /**
     * @param array<string, mixed> $slot
     */
    private function scheduleContinuity(User $actor, ContinuityRequest $request, array $slot)
    {
        return $this->actingAs($actor, 'sanctum')
            ->postJson("/api/continuity-requests/{$request->id}/schedule", $slot);
    }

    private function slot(User $dentist, DentalChair $chair, AppointmentType $type): array
    {
        return [
            'scheduled_at' => $this->at('09:00')->toDateTimeString(),
            'user_id' => $dentist->id,
            'dental_chair_id' => $chair->id,
            'appointment_type_id' => $type->id,
            'duration_minutes' => 30,
        ];
    }

    public function test_a_soft_deleted_appointment_releases_its_slot_for_a_recall(): void
    {
        $actor = $this->actor();
        $dentist = $this->dentist();
        $chair = DentalChair::factory()->create();
        $type = AppointmentType::factory()->create();
        $patient = Patient::factory()->create();

        $deleted = Appointment::factory()->create([
            'patient_id' => $patient->id,
            'user_id' => $dentist->id,
            'dental_chair_id' => $chair->id,
            'appointment_type_id' => $type->id,
            'scheduled_at' => $this->at('09:00'),
            'ends_at' => $this->at('09:30'),
            'duration_minutes' => 30,
        ]);
        $deleted->delete();

        $recall = $this->recall($actor, $patient);

        $this->scheduleRecall($actor, $recall, $this->slot($dentist, $chair, $type))
            ->assertOk();

        $this->assertSame(PatientRecall::STATUS_SCHEDULED, $recall->fresh()->status);
        $this->assertSame(1, Appointment::count());
        $this->assertSame($this->at('09:00')->format('Y-m-d H:i:s'), $recall->fresh()->scheduledAppointment->scheduled_at->format('Y-m-d H:i:s'));
    }

    public function test_a_recall_over_a_live_slot_answers_422_without_writing(): void
    {
        $actor = $this->actor();
        $dentist = $this->dentist();
        $chair = DentalChair::factory()->create();
        $type = AppointmentType::factory()->create();
        $patient = Patient::factory()->create();

        Appointment::factory()->create([
            'patient_id' => Patient::factory()->create()->id,
            'user_id' => $dentist->id,
            'dental_chair_id' => $chair->id,
            'appointment_type_id' => $type->id,
            'scheduled_at' => $this->at('09:00'),
            'ends_at' => $this->at('09:30'),
            'duration_minutes' => 30,
        ]);

        $recall = $this->recall($actor, $patient);

        $this->scheduleRecall($actor, $recall, $this->slot($dentist, $chair, $type))
            ->assertStatus(422)
            ->assertJsonValidationErrors('scheduled_at');

        $this->assertSame(PatientRecall::STATUS_PENDING, $recall->fresh()->status);
        $this->assertSame(1, Appointment::count());
    }

    public function test_the_recall_door_maps_an_engine_slot_collision_to_slot_taken(): void
    {
        $actor = $this->actor();
        $dentist = $this->dentist();
        $chair = DentalChair::factory()->create();
        $type = AppointmentType::factory()->create();
        $patient = Patient::factory()->create();
        $recall = $this->recall($actor, $patient);

        $this->mock(RecallService::class, function ($mock): void {
            $mock->shouldReceive('schedule')->once()->andThrow($this->wrappedSlotCollision());
        });

        $this->scheduleRecall($actor, $recall, $this->slot($dentist, $chair, $type))
            ->assertStatus(422)
            ->assertJsonPath('conflict.type', 'slot_taken')
            ->assertJsonPath('errors.scheduled_at.0', SlotConflict::MESSAGE);

        $this->assertSame(PatientRecall::STATUS_PENDING, $recall->fresh()->status);
        $this->assertSame(0, Appointment::count());
    }

    public function test_the_continuity_door_maps_an_engine_slot_collision_to_slot_taken(): void
    {
        $actor = $this->actor();
        $dentist = $this->dentist();
        $chair = DentalChair::factory()->create();
        $patient = Patient::factory()->create();
        $request = $this->continuityRequest($actor, $patient, $dentist);

        $this->mock(ContinuityService::class, function ($mock): void {
            $mock->shouldReceive('schedule')->once()->andThrow($this->wrappedSlotCollision());
        });

        $this->scheduleContinuity($actor, $request, [
            'scheduled_at' => $this->at('09:00')->toDateTimeString(),
            'user_id' => $dentist->id,
            'dental_chair_id' => $chair->id,
            'appointment_type_id' => $request->appointment_type_id,
            'duration_minutes' => 30,
        ])
            ->assertStatus(422)
            ->assertJsonPath('conflict.type', 'slot_taken')
            ->assertJsonPath('errors.scheduled_at.0', SlotConflict::MESSAGE);

        $this->assertSame(ContinuityRequest::STATUS_PENDING, $request->fresh()->status);
        $this->assertSame(0, Appointment::count());
    }

    /**
     * The driver error arrives inside the service's wrapper; the door has to
     * walk the chain, not read only the outer message.
     */
    private function wrappedSlotCollision(): \RuntimeException
    {
        $driver = new QueryException(
            'mysql',
            'insert into `appointments` ...',
            [],
            new PDOException(
                'SQLSTATE[23000]: Integrity constraint violation: 1062 Duplicate entry '
                ."'1-2026-11-28 09:00:00-2026-11-28 09:30:00-1' for key 'appointments.unique_user_time_slot'"
            )
        );

        return new \RuntimeException('AppointmentService::createAppointment - Unexpected error', 0, $driver);
    }
}
