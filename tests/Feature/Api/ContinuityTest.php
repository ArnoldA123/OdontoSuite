<?php

namespace Tests\Feature\Api;

use App\Models\Appointment;
use App\Models\Branch;
use App\Models\ContinuityRequest;
use App\Models\Patient;
use App\Models\PatientRecall;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * F4 (#284) — the closure leaves a conduct, reception books it.
 *
 * The old wizard wrote the next appointment directly and skipped the calendar
 * conflict check. The closure now records a continuity request, and the only
 * door that turns it into an appointment is the agenda door.
 */
class ContinuityTest extends TestCase
{
    use RefreshDatabase;

    private function staff(string $role, array $attributes = []): User
    {
        return User::factory()->create(array_merge([
            'role' => $role,
            'is_active' => true,
        ], $attributes));
    }

    private function inConsultation(User $professional, Patient $patient): Appointment
    {
        return Appointment::factory()->create([
            'patient_id' => $patient->id,
            'user_id' => $professional->id,
            'status' => 'in_consultation',
            'checked_in_at' => now()->subMinutes(30),
            'scheduled_at' => now()->startOfDay()->addHours(9),
        ]);
    }

    /**
     * @param  array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private function closurePayload(array $overrides = []): array
    {
        return array_merge([
            'mode' => 'consultation',
            'skip_materials' => true,
            'evolution' => [
                'subjective' => 'Control de restauración.',
                'objective' => 'Restauración en buen estado.',
                'assessment' => 'Evolución favorable.',
                'plan' => 'Control en seis meses.',
            ],
        ], $overrides);
    }

    public function test_the_closure_records_the_conduct_instead_of_inventing_an_appointment(): void
    {
        $professional = $this->staff('odontologo');
        $patient = Patient::factory()->create();
        $appointment = $this->inConsultation($professional, $patient);
        $preferred = now()->addWeek()->setTime(10, 0);

        $this->actingAs($professional, 'sanctum')
            ->postJson("/api/appointments/{$appointment->id}/complete", $this->closurePayload([
                'next_appointment' => [
                    'scheduled_at' => $preferred->toDateTimeString(),
                    'duration_minutes' => 45,
                    'notes' => 'Control postoperatorio.',
                ],
            ]))
            ->assertOk();

        $this->assertSame(1, Appointment::count(), 'The closure must not create the next appointment.');
        $this->assertSame(1, ContinuityRequest::count());

        $request = ContinuityRequest::sole();
        $this->assertSame(ContinuityRequest::STATUS_PENDING, $request->status);
        $this->assertSame('45', (string) $request->duration_minutes);
        $this->assertSame($preferred->toDateTimeString(), $request->preferred_from->toDateTimeString());
        $this->assertSame($appointment->id, $request->appointment_id);
        $this->assertSame('Control postoperatorio.', $request->notes);
    }

    public function test_the_closure_records_the_recall_interval_and_its_reason(): void
    {
        $professional = $this->staff('odontologo');
        $patient = Patient::factory()->create();
        $appointment = $this->inConsultation($professional, $patient);

        $this->actingAs($professional, 'sanctum')
            ->postJson("/api/appointments/{$appointment->id}/complete", $this->closurePayload([
                'recall' => [
                    'interval_months' => 6,
                    'reason' => 'Control semestral de restauración.',
                ],
            ]))
            ->assertOk();

        $recall = PatientRecall::sole();
        $this->assertSame(6, (int) $recall->interval_months);
        $this->assertSame('Control semestral de restauración.', $recall->reason);
        $this->assertSame(now()->addMonths(6)->toDateString(), $recall->due_date->toDateString());
        $this->assertSame(PatientRecall::STATUS_PENDING, $recall->status);
    }

    public function test_reception_materialises_the_conduct_through_the_agenda_door(): void
    {
        $receptionist = $this->staff('recepcionista');
        $professional = $this->staff('odontologo');
        $patient = Patient::factory()->create();
        $source = Appointment::factory()->create([
            'patient_id' => $patient->id,
            'user_id' => $professional->id,
        ]);

        $request = ContinuityRequest::create([
            'patient_id' => $patient->id,
            'appointment_id' => $source->id,
            'appointment_type_id' => $source->appointment_type_id,
            'kind' => ContinuityRequest::KIND_NEXT_SESSION,
            'status' => ContinuityRequest::STATUS_PENDING,
            'preferred_from' => now()->addWeek(),
            'preferred_to' => now()->addWeek()->addMinutes(30),
            'duration_minutes' => 30,
            'created_by' => $professional->id,
        ]);

        $scheduledAt = now()->addWeek()->setTime(11, 0);

        $this->actingAs($receptionist, 'sanctum')
            ->postJson("/api/continuity-requests/{$request->id}/schedule", [
                'scheduled_at' => $scheduledAt->toDateTimeString(),
                'user_id' => $professional->id,
                'dental_chair_id' => $source->dental_chair_id,
                'appointment_type_id' => $source->appointment_type_id,
                'duration_minutes' => 30,
            ])
            ->assertCreated();

        $appointment = Appointment::where('patient_id', $patient->id)
            ->where('scheduled_at', $scheduledAt)
            ->sole();

        $this->assertSame('scheduled', $appointment->status);
        $this->assertSame($appointment->id, $request->fresh()->scheduled_appointment_id);
        $this->assertSame(ContinuityRequest::STATUS_SCHEDULED, $request->fresh()->status);
    }

    public function test_a_conflicting_slot_is_rejected_and_the_conduct_stays_pending(): void
    {
        $receptionist = $this->staff('recepcionista');
        $professional = $this->staff('odontologo');
        $patient = Patient::factory()->create();
        $source = Appointment::factory()->create([
            'patient_id' => $patient->id,
            'user_id' => $professional->id,
        ]);

        $slot = now()->addDays(2)->setTime(9, 0);

        Appointment::factory()->create([
            'patient_id' => $patient->id,
            'user_id' => $professional->id,
            'dental_chair_id' => $source->dental_chair_id,
            'appointment_type_id' => $source->appointment_type_id,
            'scheduled_at' => $slot,
            'duration_minutes' => 60,
            'status' => 'scheduled',
        ]);

        $request = ContinuityRequest::create([
            'patient_id' => $patient->id,
            'appointment_id' => $source->id,
            'appointment_type_id' => $source->appointment_type_id,
            'kind' => ContinuityRequest::KIND_CONTROL,
            'status' => ContinuityRequest::STATUS_PENDING,
            'duration_minutes' => 30,
            'created_by' => $professional->id,
        ]);

        $before = Appointment::count();

        $this->actingAs($receptionist, 'sanctum')
            ->postJson("/api/continuity-requests/{$request->id}/schedule", [
                'scheduled_at' => $slot->copy()->addMinutes(15)->toDateTimeString(),
                'user_id' => $professional->id,
                'dental_chair_id' => $source->dental_chair_id,
                'appointment_type_id' => $source->appointment_type_id,
                'duration_minutes' => 30,
            ])
            ->assertStatus(422);

        $this->assertSame($before, Appointment::count());
        $this->assertSame(ContinuityRequest::STATUS_PENDING, $request->fresh()->status);
        $this->assertNull($request->fresh()->scheduled_appointment_id);
    }

    public function test_the_reception_desk_reads_overdue_recalls_and_pending_conduct(): void
    {
        $receptionist = $this->staff('recepcionista');
        $professional = $this->staff('odontologo');
        $patient = Patient::factory()->create();

        PatientRecall::create([
            'patient_id' => $patient->id,
            'interval_months' => 3,
            'reason' => 'Control trimestral.',
            'due_date' => now()->subWeek()->toDateString(),
            'status' => PatientRecall::STATUS_PENDING,
            'created_by' => $professional->id,
        ]);

        ContinuityRequest::create([
            'patient_id' => $patient->id,
            'kind' => ContinuityRequest::KIND_CONTROL,
            'status' => ContinuityRequest::STATUS_PENDING,
            'preferred_from' => now()->addWeek(),
            'duration_minutes' => 30,
            'created_by' => $professional->id,
        ]);

        $response = $this->actingAs($receptionist, 'sanctum')
            ->getJson('/api/continuity/queue')
            ->assertOk();

        $this->assertSame(1, $response->json('data.counts.recalls'));
        $this->assertSame(1, $response->json('data.counts.requests'));
        $this->assertSame(2, $response->json('data.counts.total'));
        $this->assertSame($patient->id, $response->json('data.recalls.0.patient_id'));
    }

    public function test_the_queue_respects_the_branch_of_the_receptionist(): void
    {
        $branchA = Branch::factory()->create();
        $branchB = Branch::factory()->create();
        $receptionist = $this->staff('recepcionista', ['branch_id' => $branchA->id]);
        $professional = $this->staff('odontologo');
        $patientA = Patient::factory()->create(['branch_id' => $branchA->id]);
        $patientB = Patient::factory()->create(['branch_id' => $branchB->id]);

        foreach ([[$patientA, $branchA], [$patientB, $branchB]] as [$patient, $branch]) {
            PatientRecall::create([
                'patient_id' => $patient->id,
                'branch_id' => $branch->id,
                'interval_months' => 3,
                'reason' => 'Control trimestral.',
                'due_date' => now()->subDay()->toDateString(),
                'status' => PatientRecall::STATUS_PENDING,
                'created_by' => $professional->id,
            ]);
        }

        $response = $this->actingAs($receptionist, 'sanctum')
            ->getJson('/api/continuity/queue')
            ->assertOk();

        $this->assertSame(1, $response->json('data.counts.recalls'));
        $this->assertSame($patientA->id, $response->json('data.recalls.0.patient_id'));
    }
}
