<?php

namespace Tests\Feature\Api;

use App\Models\LabCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * #449 (S6/S7) — who may edit a prosthetic work order.
 *
 * The role door (`manage.lab_case`) was already there; what the route never
 * asked is ownership. A case belongs to the technician it is assigned to, so
 * that technician corrects it, the desks that prescribe and administer the
 * workshop correct any case of their branch, and a placed case is finished
 * work no matter who asks.
 *
 * Every refusal asserts the stored row as well as the status: a 403 that still
 * wrote would be the same defect wearing a different code.
 */
class LabCaseOwnershipTest extends TestCase
{
    use RefreshDatabase;

    private function staff(string $role, array $attributes = []): User
    {
        return User::factory()->create(array_merge([
            'role' => $role,
            'is_active' => true,
        ], $attributes));
    }

    private function caseOf(User $technician, array $overrides = []): LabCase
    {
        return LabCase::factory()->create(array_merge([
            'technician_id' => $technician->id,
        ], $overrides));
    }

    #[Test]
    public function the_assigned_technician_edits_its_own_case(): void
    {
        $technician = $this->staff('tecnico_dental');
        $case = $this->caseOf($technician);

        $this->actingAs($technician, 'sanctum')
            ->putJson("/api/lab-cases/{$case->id}", ['work' => 'Corona de disilicato'])
            ->assertOk()
            ->assertJsonPath('data.work', 'Corona de disilicato')
            ->assertJsonPath('data.can_edit', true);

        $this->assertSame('Corona de disilicato', $case->fresh()->work);
    }

    #[Test]
    public function another_technician_is_refused_and_the_case_is_untouched(): void
    {
        $owner = $this->staff('tecnico_dental');
        $intruder = $this->staff('tecnico_dental');
        $case = $this->caseOf($owner, ['work' => 'Puente metal-cerámica']);

        $this->actingAs($intruder, 'sanctum')
            ->putJson("/api/lab-cases/{$case->id}", ['work' => 'Prótesis total'])
            ->assertForbidden();

        $stored = $case->fresh();

        $this->assertSame('Puente metal-cerámica', $stored->work);
        $this->assertSame($owner->id, (int) $stored->technician_id);
    }

    #[Test]
    public function administration_edits_any_case(): void
    {
        $admin = $this->staff('administrador');
        $technician = $this->staff('tecnico_dental');
        $case = $this->caseOf($technician);

        $this->actingAs($admin, 'sanctum')
            ->putJson("/api/lab-cases/{$case->id}", ['work' => 'Incrustación de disilicato'])
            ->assertOk()
            ->assertJsonPath('data.work', 'Incrustación de disilicato');

        $this->assertSame('Incrustación de disilicato', $case->fresh()->work);
    }

    #[Test]
    public function the_prescribing_desks_edit_a_case_assigned_to_another_technician(): void
    {
        $technician = $this->staff('tecnico_dental');
        $case = $this->caseOf($technician);

        foreach (['odontologo', 'implantologo'] as $role) {
            $doctor = $this->staff($role);

            $this->actingAs($doctor, 'sanctum')
                ->putJson("/api/lab-cases/{$case->id}", ['work' => "Corrección del {$role}"])
                ->assertOk()
                ->assertJsonPath('data.work', "Corrección del {$role}");
        }

        $this->assertSame('Corrección del implantologo', $case->fresh()->work);
    }

    #[Test]
    public function a_placed_case_is_not_editable_even_for_administration(): void
    {
        $admin = $this->staff('administrador');
        $technician = $this->staff('tecnico_dental');
        $case = $this->caseOf($technician, [
            'status' => LabCase::PHASE_PLACED,
            'work' => 'Corona metal-cerámica',
            'placed_at' => now(),
        ]);

        $this->actingAs($admin, 'sanctum')
            ->putJson("/api/lab-cases/{$case->id}", ['work' => 'Otra cosa'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('status')
            ->assertJsonPath(
                'errors.status.0',
                'No se puede editar un caso de laboratorio ya colocado: el trabajo concluyó.'
            );

        $this->assertSame('Corona metal-cerámica', $case->fresh()->work);
    }

    #[Test]
    public function the_list_publishes_the_edit_capability_of_every_case(): void
    {
        $technician = $this->staff('tecnico_dental');
        $other = $this->staff('tecnico_dental');

        $own = $this->caseOf($technician);
        $foreign = $this->caseOf($other);
        $placed = $this->caseOf($technician, [
            'status' => LabCase::PHASE_PLACED,
            'placed_at' => now(),
        ]);

        $response = $this->actingAs($technician, 'sanctum')
            ->getJson('/api/lab-cases?per_page=100')
            ->assertOk();

        $capabilities = collect($response->json('data'))->pluck('can_edit', 'id');

        $this->assertTrue($capabilities[$own->id]);
        $this->assertFalse($capabilities[$foreign->id], 'A case of another technician is never offered for edit.');
        $this->assertFalse($capabilities[$placed->id], 'A placed case is closed work.');
    }

    #[Test]
    public function the_detail_publishes_the_same_capability(): void
    {
        $technician = $this->staff('tecnico_dental');
        $owner = $this->staff('tecnico_dental');

        $own = $this->caseOf($technician);
        $foreign = $this->caseOf($owner);
        $placed = $this->caseOf($technician, [
            'status' => LabCase::PHASE_PLACED,
            'placed_at' => now(),
        ]);

        $this->actingAs($technician, 'sanctum')
            ->getJson("/api/lab-cases/{$own->id}")
            ->assertOk()
            ->assertJsonPath('data.can_edit', true);

        $this->actingAs($technician, 'sanctum')
            ->getJson("/api/lab-cases/{$foreign->id}")
            ->assertOk()
            ->assertJsonPath('data.can_edit', false);

        $this->actingAs($technician, 'sanctum')
            ->getJson("/api/lab-cases/{$placed->id}")
            ->assertOk()
            ->assertJsonPath('data.can_edit', false);
    }

    #[Test]
    public function another_technician_cannot_move_the_phase_of_a_foreign_case(): void
    {
        $owner = $this->staff('tecnico_dental');
        $intruder = $this->staff('tecnico_dental');
        $case = $this->caseOf($owner, ['status' => LabCase::PHASE_IN_PREPARATION]);

        $this->actingAs($intruder, 'sanctum')
            ->postJson("/api/lab-cases/{$case->id}/phase", ['phase' => LabCase::PHASE_PLACED])
            ->assertForbidden();

        $stored = $case->fresh();

        $this->assertSame(LabCase::PHASE_IN_PREPARATION, $stored->status);
        $this->assertNull($stored->placed_at);
    }

    #[Test]
    public function the_assigned_technician_moves_the_phase_of_its_own_case(): void
    {
        $technician = $this->staff('tecnico_dental');
        $case = $this->caseOf($technician);

        $this->actingAs($technician, 'sanctum')
            ->postJson("/api/lab-cases/{$case->id}/phase", ['phase' => LabCase::PHASE_IN_LABORATORY])
            ->assertOk()
            ->assertJsonPath('data.status', LabCase::PHASE_IN_LABORATORY);
    }

    #[Test]
    public function an_unassigned_case_is_the_shared_bench_for_the_laboratory_desk(): void
    {
        $technician = $this->staff('tecnico_dental');
        $case = $this->caseOf($technician, ['technician_id' => null]);

        $this->actingAs($technician, 'sanctum')
            ->putJson("/api/lab-cases/{$case->id}", ['work' => 'Trabajo sin asignar'])
            ->assertOk()
            ->assertJsonPath('data.can_edit', true);

        $this->assertSame('Trabajo sin asignar', $case->fresh()->work);
    }
}
