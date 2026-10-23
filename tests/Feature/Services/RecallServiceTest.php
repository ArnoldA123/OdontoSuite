<?php

namespace Tests\Feature\Services;

use App\Http\Requests\StorePatientRecallRequest;
use App\Models\Appointment;
use App\Models\AppointmentType;
use App\Models\Branch;
use App\Models\DentalChair;
use App\Models\Patient;
use App\Models\PatientRecall;
use App\Models\User;
use App\Services\RecallService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class RecallServiceTest extends TestCase
{
    use RefreshDatabase;

    private function service(): RecallService
    {
        return app(RecallService::class);
    }

    /**
     * @param array<string, mixed> $attributes
     */
    private function user(string $role = 'recepcionista', array $attributes = []): User
    {
        return User::factory()->create(array_merge([
            'role' => $role,
            'is_active' => true,
        ], $attributes));
    }

    /**
     * @param array<string, mixed> $attributes
     */
    private function recall(User $actor, array $attributes = []): PatientRecall
    {
        return $this->service()->create(array_merge([
            'patient_id' => Patient::factory()->create()->id,
            'interval_months' => 6,
            'reason' => 'Control semestral',
        ], $attributes), $actor);
    }

    public function test_the_due_date_is_computed_from_the_interval_and_the_recall_starts_pending(): void
    {
        $actor = $this->user();
        $patient = Patient::factory()->create();

        $recall = $this->service()->create([
            'patient_id' => $patient->id,
            'interval_months' => 6,
            'reason' => 'Control semestral',
        ], $actor);

        $this->assertSame(Carbon::today()->addMonths(6)->toDateString(), $recall->due_date->toDateString());
        $this->assertSame(PatientRecall::STATUS_PENDING, $recall->status);
        $this->assertSame($actor->id, $recall->created_by);
        $this->assertNull($recall->scheduled_appointment_id);
    }

    public function test_an_explicit_due_date_is_preserved(): void
    {
        $actor = $this->user();
        $dueDate = Carbon::today()->addDays(10)->toDateString();

        $recall = $this->recall($actor, [
            'interval_months' => 3,
            'due_date' => $dueDate,
        ]);

        $this->assertSame($dueDate, $recall->due_date->toDateString());
    }

    public function test_only_three_six_and_twelve_month_intervals_are_valid(): void
    {
        $patient = Patient::factory()->create();
        $rules = (new StorePatientRecallRequest)->rules();

        foreach ([4, 9, 18] as $interval) {
            $validator = Validator::make([
                'patient_id' => $patient->id,
                'interval_months' => $interval,
                'reason' => 'Control',
            ], $rules);

            $this->assertTrue($validator->fails(), "Interval {$interval} should not be accepted.");
            $this->assertArrayHasKey('interval_months', $validator->errors()->toArray());
        }

        foreach ([3, 6, 12] as $interval) {
            $validator = Validator::make([
                'patient_id' => $patient->id,
                'interval_months' => $interval,
                'reason' => 'Control',
            ], $rules);

            $this->assertFalse($validator->fails(), "Interval {$interval} should be accepted.");
        }
    }

    public function test_the_queue_contains_due_recalls_and_excludes_future_ones(): void
    {
        $actor = $this->user();

        $due = $this->recall($actor, ['due_date' => Carbon::today()->toDateString()]);
        $future = $this->recall($actor, ['due_date' => Carbon::today()->addDay()->toDateString()]);
        $completed = $this->recall($actor, ['due_date' => Carbon::today()->subDay()->toDateString()]);
        $completed->update(['status' => PatientRecall::STATUS_COMPLETED, 'completed_at' => now()]);

        $queue = $this->service()->queue(null);

        $this->assertSame([$due->id], $queue->pluck('id')->all());
        $this->assertNotContains($future->id, $queue->pluck('id')->all());
        $this->assertNotContains($completed->id, $queue->pluck('id')->all());
    }

    public function test_the_queue_filters_by_branch(): void
    {
        $actor = $this->user();
        $branchA = Branch::factory()->create();
        $branchB = Branch::factory()->create();

        $dueA = $this->recall($actor, [
            'branch_id' => $branchA->id,
            'due_date' => Carbon::today()->toDateString(),
        ]);
        $this->recall($actor, [
            'branch_id' => $branchB->id,
            'due_date' => Carbon::today()->toDateString(),
        ]);

        $this->assertSame([$dueA->id], $this->service()->queue($branchA->id)->pluck('id')->all());
    }

    public function test_scheduling_a_recall_creates_the_control_appointment(): void
    {
        $actor = $this->user();
        $branch = Branch::factory()->create();
        $patient = Patient::factory()->create();
        $professional = $this->user('odontologo');
        $chair = DentalChair::factory()->create();
        $type = AppointmentType::factory()->create();

        $recall = $this->recall($actor, [
            'patient_id' => $patient->id,
            'branch_id' => $branch->id,
        ]);

        $scheduledAt = Carbon::now()->addDays(2)->setTime(10, 0);

        $this->actingAs($actor);

        $result = $this->service()->schedule($recall, [
            'scheduled_at' => $scheduledAt->toDateTimeString(),
            'user_id' => $professional->id,
            'dental_chair_id' => $chair->id,
            'appointment_type_id' => $type->id,
            'duration_minutes' => 30,
            'notes' => 'Cita de control del recall',
        ], $actor);

        $appointment = $result['appointment'];

        $this->assertInstanceOf(Appointment::class, $appointment);
        $this->assertSame(PatientRecall::STATUS_SCHEDULED, $result['recall']->status);
        $this->assertSame($appointment->id, $result['recall']->scheduled_appointment_id);
        $this->assertSame($patient->id, $appointment->patient_id);
        $this->assertSame($branch->id, $appointment->branch_id);
        $this->assertSame($professional->id, $appointment->user_id);
        $this->assertSame($chair->id, $appointment->dental_chair_id);
        $this->assertSame($type->id, $appointment->appointment_type_id);
        $this->assertSame(30, $appointment->duration_minutes);
        $this->assertSame('Cita de control del recall', $appointment->notes);
        $this->assertSame($scheduledAt->toDateTimeString(), $appointment->scheduled_at->toDateTimeString());
    }

    public function test_a_conflicting_slot_leaves_the_recall_pending_without_a_new_appointment(): void
    {
        $actor = $this->user();
        $branch = Branch::factory()->create();
        $patient = Patient::factory()->create();
        $professional = $this->user('odontologo');
        $chair = DentalChair::factory()->create();
        $type = AppointmentType::factory()->create();

        $recall = $this->recall($actor, [
            'patient_id' => $patient->id,
            'branch_id' => $branch->id,
        ]);

        $scheduledAt = Carbon::now()->addDays(2)->setTime(10, 0);

        Appointment::factory()->create([
            'patient_id' => Patient::factory()->create()->id,
            'user_id' => $professional->id,
            'dental_chair_id' => $chair->id,
            'appointment_type_id' => $type->id,
            'branch_id' => $branch->id,
            'scheduled_at' => $scheduledAt,
            'duration_minutes' => 30,
        ]);

        $countBefore = Appointment::count();

        $this->actingAs($actor);

        try {
            $this->service()->schedule($recall, [
                'scheduled_at' => $scheduledAt->toDateTimeString(),
                'user_id' => $professional->id,
                'dental_chair_id' => $chair->id,
                'appointment_type_id' => $type->id,
                'duration_minutes' => 30,
            ], $actor);

            $this->fail('A conflicting slot should not schedule the recall.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('scheduled_at', $e->errors());
        }

        $this->assertSame($countBefore, Appointment::count());
        $this->assertSame(PatientRecall::STATUS_PENDING, $recall->fresh()->status);
        $this->assertNull($recall->fresh()->scheduled_appointment_id);
    }

    public function test_complete_stamps_the_completion(): void
    {
        $actor = $this->user();
        $recall = $this->recall($actor);
        $recall->update(['status' => PatientRecall::STATUS_SCHEDULED]);

        $completed = $this->service()->complete($recall, $actor);

        $this->assertSame(PatientRecall::STATUS_COMPLETED, $completed->status);
        $this->assertNotNull($completed->completed_at);
    }

    public function test_cancel_stamps_the_cancellation_for_pending_and_scheduled_recalls(): void
    {
        $actor = $this->user();

        foreach ([PatientRecall::STATUS_PENDING, PatientRecall::STATUS_SCHEDULED] as $status) {
            $recall = $this->recall($actor, ['due_date' => Carbon::today()->addMonth()->toDateString()]);
            $recall->update(['status' => $status]);

            $cancelled = $this->service()->cancel($recall, $actor);

            $this->assertSame(PatientRecall::STATUS_CANCELLED, $cancelled->status);
            $this->assertNotNull($cancelled->cancelled_at);
        }
    }

    public function test_metrics_report_the_compliance_rate(): void
    {
        $actor = $this->user();

        $completed = $this->recall($actor, ['due_date' => Carbon::today()->subDays(10)->toDateString()]);
        $completed->update(['status' => PatientRecall::STATUS_COMPLETED, 'completed_at' => now()]);

        $this->recall($actor, ['due_date' => Carbon::today()->subDays(5)->toDateString()]);

        $scheduled = $this->recall($actor, ['due_date' => Carbon::today()->addDays(3)->toDateString()]);
        $scheduled->update(['status' => PatientRecall::STATUS_SCHEDULED]);

        $this->recall($actor, ['due_date' => Carbon::today()->addDays(7)->toDateString()]);

        $cancelled = $this->recall($actor, ['due_date' => Carbon::today()->subDays(2)->toDateString()]);
        $cancelled->update(['status' => PatientRecall::STATUS_CANCELLED, 'cancelled_at' => now()]);

        $metrics = $this->service()->metrics(
            null,
            Carbon::today()->subDays(30)->toDateString(),
            Carbon::today()->addDays(30)->toDateString(),
        );

        $this->assertSame(5, $metrics['total']);
        $this->assertSame(1, $metrics['completed']);
        $this->assertSame(2, $metrics['pending']);
        $this->assertSame(1, $metrics['scheduled']);
        $this->assertSame(1, $metrics['cancelled']);
        $this->assertSame(1, $metrics['overdue']);
        $this->assertSame(0.5, $metrics['compliance_rate']);

        $windowed = $this->service()->metrics(
            null,
            Carbon::today()->subDays(10)->toDateString(),
            Carbon::today()->addDays(3)->toDateString(),
        );

        $this->assertSame(4, $windowed['total']);
    }

    public function test_metrics_compliance_is_null_when_there_is_nothing_to_comply_with(): void
    {
        $actor = $this->user();
        $branchA = Branch::factory()->create();
        $branchB = Branch::factory()->create();

        $this->recall($actor, [
            'branch_id' => $branchA->id,
            'due_date' => Carbon::today()->addDays(5)->toDateString(),
        ]);

        $completed = $this->recall($actor, [
            'branch_id' => $branchB->id,
            'due_date' => Carbon::today()->subDays(5)->toDateString(),
        ]);
        $completed->update(['status' => PatientRecall::STATUS_COMPLETED, 'completed_at' => now()]);

        $metrics = $this->service()->metrics(
            $branchA->id,
            Carbon::today()->subDays(1)->toDateString(),
            Carbon::today()->addDays(30)->toDateString(),
        );

        $this->assertSame(1, $metrics['total']);
        $this->assertSame(0, $metrics['completed']);
        $this->assertSame(0, $metrics['overdue']);
        $this->assertNull($metrics['compliance_rate']);
    }
}
