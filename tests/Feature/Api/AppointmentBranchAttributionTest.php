<?php

namespace Tests\Feature\Api;

use App\Models\Appointment;
use App\Models\AppointmentType;
use App\Models\Branch;
use App\Models\DentalChair;
use App\Models\Patient;
use App\Models\User;
use App\Services\AppointmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * citas-privacidad (T1) — the appointment inherits the sede of its people.
 *
 * An appointment booked by group reception (a user with no sede) was stored
 * with `branch_id = null`, and the index of a professional assigned to a sede
 * filters by `branch_id = their sede`: the professional could not see the
 * appointment a receptionist had just created for them. The writer now derives
 * the sede —explicit value, then the professional's, then the chair's, then the
 * one the row already had— and the data migration backfills what was stored.
 *
 * The `ScopesToBranch` writer guard keeps its authority: reception of sede 4
 * assigning a professional of sede 5 is a 403, not a silent move.
 *
 * Must run against the MySQL harness (AGENTS.md §6).
 */
class AppointmentBranchAttributionTest extends TestCase
{
    use RefreshDatabase;

    private function staff(string $role, ?Branch $branch = null): User
    {
        return User::factory()->create([
            'role' => $role,
            'is_active' => true,
            'branch_id' => $branch?->id,
        ]);
    }

    private function chair(?Branch $branch = null): DentalChair
    {
        return DentalChair::factory()->create(['branch_id' => $branch?->id]);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(User $professional, DentalChair $chair, string $time = '10:00'): array
    {
        return [
            'patient_id' => Patient::factory()->create()->id,
            'user_id' => $professional->id,
            'dental_chair_id' => $chair->id,
            'appointment_type_id' => AppointmentType::factory()->create()->id,
            'scheduled_at' => now()->addDays(2)->startOfDay()->setTimeFromTimeString($time)->format('Y-m-d H:i:s'),
            'duration_minutes' => 60,
        ];
    }

    private function storedBranch(string $appointmentId): ?int
    {
        $branchId = Appointment::withTrashed()->findOrFail($appointmentId)->branch_id;

        return $branchId === null ? null : (int) $branchId;
    }

    public function test_a_group_reception_stamps_the_professionals_sede_and_the_professional_sees_it(): void
    {
        $branch = Branch::factory()->create();
        $professional = $this->staff('odontologo', $branch);
        $chair = $this->chair($branch);
        // Recepción sin sede: alcance de grupo, el caso que rompía la agenda.
        $reception = $this->staff('recepcionista');

        $response = $this->actingAs($reception, 'sanctum')
            ->postJson('/api/appointments', $this->payload($professional, $chair));

        $response->assertCreated();

        $this->assertSame(
            $branch->id,
            $this->storedBranch($response->json('data.id')),
            'The appointment MUST inherit the sede of its professional.'
        );

        $ids = collect(
            $this->actingAs($professional, 'sanctum')->getJson('/api/appointments')->assertOk()->json('data')
        )->pluck('id')->all();

        $this->assertContains(
            (int) $response->json('data.id'),
            $ids,
            'The professional with a sede MUST find the appointment booked for them.'
        );
    }

    public function test_the_chair_sede_is_the_fallback_when_the_professional_has_none(): void
    {
        $branch = Branch::factory()->create();
        $professional = $this->staff('odontologo');
        $chair = $this->chair($branch);

        $response = $this->actingAs($this->staff('recepcionista'), 'sanctum')
            ->postJson('/api/appointments', $this->payload($professional, $chair));

        $response->assertCreated();
        $this->assertSame($branch->id, $this->storedBranch($response->json('data.id')));
    }

    public function test_an_explicit_branch_wins_over_the_inherited_one(): void
    {
        $professionalBranch = Branch::factory()->create();
        $explicitBranch = Branch::factory()->create();
        $professional = $this->staff('odontologo', $professionalBranch);

        $response = $this->actingAs($this->staff('recepcionista'), 'sanctum')
            ->postJson('/api/appointments', [
                ...$this->payload($professional, $this->chair($professionalBranch)),
                'branch_id' => $explicitBranch->id,
            ]);

        $response->assertCreated();
        $this->assertSame($explicitBranch->id, $this->storedBranch($response->json('data.id')));
    }

    public function test_without_any_declared_sede_the_appointment_stays_without_one(): void
    {
        $response = $this->actingAs($this->staff('recepcionista'), 'sanctum')
            ->postJson('/api/appointments', $this->payload($this->staff('odontologo'), $this->chair()));

        $response->assertCreated();
        $this->assertNull($this->storedBranch($response->json('data.id')));
    }

    public function test_reception_of_a_sede_cannot_assign_a_professional_of_another_one(): void
    {
        $ownBranch = Branch::factory()->create();
        $foreignBranch = Branch::factory()->create();

        $reception = $this->staff('recepcionista', $ownBranch);
        $foreignProfessional = $this->staff('odontologo', $foreignBranch);
        $payload = $this->payload($foreignProfessional, $this->chair($foreignBranch));

        $this->actingAs($reception, 'sanctum')
            ->postJson('/api/appointments', $payload)
            ->assertForbidden();

        $this->assertSame(0, Appointment::withTrashed()->count(), 'A forbidden create MUST NOT leave a row.');
    }

    public function test_update_recalculates_the_sede_when_the_professional_changes(): void
    {
        $firstBranch = Branch::factory()->create();
        $secondBranch = Branch::factory()->create();

        $author = $this->staff('administrador');
        $professional = $this->staff('odontologo', $firstBranch);
        $successor = $this->staff('implantologo', $secondBranch);
        $chair = $this->chair($firstBranch);

        $appointment = Appointment::factory()->create([
            'user_id' => $professional->id,
            'dental_chair_id' => $chair->id,
            'branch_id' => $firstBranch->id,
            'scheduled_at' => now()->addDays(2)->startOfDay()->setTime(10, 0),
            'duration_minutes' => 60,
        ]);

        $this->actingAs($author, 'sanctum');

        app(AppointmentService::class)->updateAppointment($appointment, ['user_id' => $successor->id]);

        $this->assertSame($secondBranch->id, (int) $appointment->fresh()->branch_id);
    }

    public function test_update_without_a_participant_change_keeps_the_sede(): void
    {
        $branch = Branch::factory()->create();
        $professional = $this->staff('odontologo');

        $appointment = Appointment::factory()->create([
            'user_id' => $professional->id,
            'branch_id' => $branch->id,
        ]);

        // El profesional pasa a estar afiliado a otra sede después de la cita:
        // editar las notas no reescribe la sede del documento agendado.
        $professional->forceFill(['branch_id' => Branch::factory()->create()->id])->save();

        $this->actingAs($this->staff('administrador'), 'sanctum');

        app(AppointmentService::class)->updateAppointment($appointment, ['notes' => 'Llamar antes.']);

        $this->assertSame($branch->id, (int) $appointment->fresh()->branch_id);
    }

    public function test_a_null_branch_in_the_payload_does_not_erase_the_sede(): void
    {
        $branch = Branch::factory()->create();
        $professional = $this->staff('odontologo', $branch);

        $appointment = Appointment::factory()->create([
            'user_id' => $professional->id,
            'dental_chair_id' => $this->chair($branch)->id,
            'branch_id' => $branch->id,
        ]);

        $this->actingAs($this->staff('administrador'), 'sanctum');

        app(AppointmentService::class)->updateAppointment($appointment, ['branch_id' => null]);

        $this->assertSame($branch->id, (int) $appointment->fresh()->branch_id);
    }

    public function test_the_resolver_cascades_and_falls_back_to_the_stored_sede(): void
    {
        $professionalBranch = Branch::factory()->create();
        $chairBranch = Branch::factory()->create();
        $explicitBranch = Branch::factory()->create();

        $professional = $this->staff('odontologo', $professionalBranch);
        $chair = $this->chair($chairBranch);
        $withoutSede = $this->staff('odontologo');
        $chairless = $this->chair();

        $service = app(AppointmentService::class);

        $this->assertSame(
            $explicitBranch->id,
            $service->resolveBranchId($professional->id, $chair->id, $explicitBranch->id)
        );
        $this->assertSame($professionalBranch->id, $service->resolveBranchId($professional->id, $chair->id));
        $this->assertSame($chairBranch->id, $service->resolveBranchId($withoutSede->id, $chair->id));
        $this->assertNull($service->resolveBranchId($withoutSede->id, $chairless->id));

        $stored = Appointment::factory()->create(['branch_id' => $professionalBranch->id]);

        $this->assertSame(
            $professionalBranch->id,
            $service->resolveBranchId($withoutSede->id, $chairless->id, null, $stored),
            'The sede the row already had is the last step of the cascade.'
        );
    }

    public function test_the_migration_backfills_the_null_rows_from_the_professional_and_then_the_chair(): void
    {
        $professionalBranch = Branch::factory()->create();
        $chairBranch = Branch::factory()->create();
        $keptBranch = Branch::factory()->create();

        $professional = $this->staff('odontologo', $professionalBranch);
        $withoutSede = $this->staff('odontologo');

        $fromProfessional = Appointment::factory()->create([
            'user_id' => $professional->id,
            'dental_chair_id' => $this->chair($chairBranch)->id,
            'branch_id' => null,
            'scheduled_at' => now()->addDays(3)->startOfDay()->setTime(9, 0),
        ]);

        $fromChair = Appointment::factory()->create([
            'user_id' => $withoutSede->id,
            'dental_chair_id' => $this->chair($chairBranch)->id,
            'branch_id' => null,
            'scheduled_at' => now()->addDays(3)->startOfDay()->setTime(10, 0),
        ]);

        $alreadySet = Appointment::factory()->create([
            'user_id' => $professional->id,
            'branch_id' => $keptBranch->id,
            'scheduled_at' => now()->addDays(3)->startOfDay()->setTime(11, 0),
        ]);

        $migration = require database_path(
            'migrations/2026_11_24_100100_backfill_appointment_branch_from_participants.php'
        );

        $migration->up();

        $this->assertSame($professionalBranch->id, (int) $fromProfessional->fresh()->branch_id);
        $this->assertSame($chairBranch->id, (int) $fromChair->fresh()->branch_id);
        $this->assertSame($keptBranch->id, (int) $alreadySet->fresh()->branch_id);

        // Idempotente: una segunda corrida no mueve nada.
        $migration->up();

        $this->assertSame($professionalBranch->id, (int) $fromProfessional->fresh()->branch_id);
        $this->assertSame($chairBranch->id, (int) $fromChair->fresh()->branch_id);
        $this->assertSame($keptBranch->id, (int) $alreadySet->fresh()->branch_id);

        $this->assertSame(
            0,
            DB::table('appointments')->whereNull('branch_id')->count(),
            'No appointment with participants that declare a sede may stay null.'
        );
    }
}
