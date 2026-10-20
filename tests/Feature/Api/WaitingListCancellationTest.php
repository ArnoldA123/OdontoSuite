<?php

namespace Tests\Feature\Api;

use App\Models\Appointment;
use App\Models\AppointmentType;
use App\Models\DentalChair;
use App\Models\Patient;
use App\Models\User;
use App\Models\WaitingList;
use App\Services\AppointmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Issue #166, finding 1 — `AppointmentService::cancelAppointment()` must
 * complete against the real `waiting_lists` schema, and `WaitingList` must not
 * reference columns its table does not have.
 *
 * Requires the MySQL runner (phpunit.mysql.xml) — see AGENTS.md §6 (decision #79).
 */
class WaitingListCancellationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Columns the model used to declare without a matching table column.
     *
     * @var array<int, string>
     */
    private const PHANTOM_COLUMNS = [
        'preferred_date',
        'preferred_time_start',
        'preferred_time_end',
        'expires_at',
    ];

    private function actor(string $role = 'administrador'): User
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

    private function appointmentFor(User $dentist, AppointmentType $type, Carbon $startsAt): Appointment
    {
        return Appointment::factory()->create([
            'patient_id' => Patient::factory()->create(['is_active' => true])->id,
            'user_id' => $dentist->id,
            'dental_chair_id' => DentalChair::factory()->create()->id,
            'appointment_type_id' => $type->id,
            'scheduled_at' => $startsAt,
            'duration_minutes' => 60,
        ]);
    }

    /**
     * @param array<string, mixed> $overrides
     */
    private function waitingEntry(int $actorId, AppointmentType $type, array $overrides = []): WaitingList
    {
        return WaitingList::create(array_merge([
            'patient_id' => Patient::factory()->create(['is_active' => true])->id,
            'appointment_type_id' => $type->id,
            'priority' => 1,
            'status' => 'active',
            'created_by' => $actorId,
        ], $overrides));
    }

    public function test_cancel_appointment_completes_and_persists_the_cancellation(): void
    {
        $actor = $this->actor();
        $this->actingAs($actor, 'sanctum');
        $appointment = $this->appointmentFor($this->dentist(), AppointmentType::factory()->create(), now()->addDays(2));

        app(AppointmentService::class)->cancelAppointment($appointment, 'El paciente no asistirá');

        $row = DB::table('appointments')->find($appointment->id);

        $this->assertSame('cancelled', $row->status);
        $this->assertStringContainsString('Cancelación: El paciente no asistirá', (string) $row->notes);
        $this->assertSame($actor->id, (int) $row->updated_by);
    }

    public function test_cancel_notifies_only_the_matching_active_waiting_list_entries(): void
    {
        $actor = $this->actor();
        $this->actingAs($actor, 'sanctum');

        $type = AppointmentType::factory()->create();
        $otherType = AppointmentType::factory()->create();
        $dentist = $this->dentist();
        $slot = Carbon::now(config('app.timezone'))->addDays(2)->setTime(10, 0);
        $appointment = $this->appointmentFor($dentist, $type, $slot);

        $anySlot = $this->waitingEntry($actor->id, $type);
        $coveringWindow = $this->waitingEntry($actor->id, $type, [
            'preferred_start_date' => $slot->copy()->startOfDay(),
            'preferred_end_date' => $slot->copy()->endOfDay(),
        ]);
        $laterWindow = $this->waitingEntry($actor->id, $type, [
            'preferred_start_date' => $slot->copy()->addDays(7)->startOfDay(),
            'preferred_end_date' => $slot->copy()->addDays(8)->endOfDay(),
        ]);
        $otherTypeEntry = $this->waitingEntry($actor->id, $otherType);
        $otherProfessional = $this->waitingEntry($actor->id, $type, [
            'preferred_user_id' => $this->dentist()->id,
        ]);
        $notActive = $this->waitingEntry($actor->id, $type, ['status' => 'scheduled']);

        app(AppointmentService::class)->cancelAppointment($appointment);

        foreach ([$anySlot, $coveringWindow] as $notified) {
            $row = DB::table('waiting_lists')->find($notified->id);
            $this->assertSame('notified', $row->status, "Waiting list entry {$notified->id} must be marked as notified.");
            $this->assertNotNull($row->notified_at, "Waiting list entry {$notified->id} must record notified_at.");
        }

        foreach ([$laterWindow, $otherTypeEntry, $otherProfessional, $notActive] as $untouched) {
            $row = DB::table('waiting_lists')->find($untouched->id);
            $this->assertSame(
                $untouched->status,
                $row->status,
                "Waiting list entry {$untouched->id} must keep its status: it does not match the cancelled slot."
            );
            $this->assertNull($row->notified_at, "Waiting list entry {$untouched->id} must not be notified.");
        }
    }

    public function test_waiting_list_fillable_and_casts_reference_columns_that_exist(): void
    {
        $columns = Schema::getColumnListing('waiting_lists');
        $model = new WaitingList;

        foreach ($model->getFillable() as $column) {
            $this->assertContains(
                $column,
                $columns,
                "WaitingList::\$fillable declares `{$column}`, which the waiting_lists table does not have."
            );
        }

        foreach (array_keys($model->getCasts()) as $column) {
            $this->assertContains(
                $column,
                $columns,
                "WaitingList::\$casts declares `{$column}`, which the waiting_lists table does not have."
            );
        }

        foreach (self::PHANTOM_COLUMNS as $phantom) {
            $this->assertNotContains($phantom, $model->getFillable(), "WaitingList::\$fillable must not declare `{$phantom}`.");
            $this->assertArrayNotHasKey($phantom, $model->getCasts(), "WaitingList::\$casts must not declare `{$phantom}`.");
        }
    }
}
