<?php

namespace Tests\Feature\Api;

use App\Models\ClinicalEvolution;
use App\Models\MedicalRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Contract tests for the evolution write endpoints behind the Evoluciones
 * surface: PUT /api/medical-records/evolutions/{evolution} and
 * DELETE /api/medical-records/evolutions/{evolution}.
 *
 * The interface offers "editar" and "eliminar" on every timeline entry, so
 * those two verbs need a route, the clinical role gate, and a soft delete that
 * keeps clinical history auditable.
 */
class MedicalRecordEvolutionTest extends TestCase
{
    use RefreshDatabase;

    protected function clinician(): User
    {
        return User::factory()->create(['role' => 'odontologo', 'is_active' => true]);
    }

    protected function receptionist(): User
    {
        return User::factory()->create(['role' => 'recepcionista', 'is_active' => true]);
    }

    public function test_clinician_can_update_an_evolution(): void
    {
        $user = $this->clinician();
        $record = MedicalRecord::factory()->create();
        $evolution = ClinicalEvolution::factory()->forRecord($record)->create([
            'subjective' => 'Dolor al masticar',
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->putJson("/api/medical-records/evolutions/{$evolution->id}", [
                'subjective' => 'Dolor agudo al masticar',
                'assessment' => 'Pulpitis irreversible',
                'requires_follow_up' => true,
            ]);

        $response->assertOk();
        $this->assertSame('Dolor agudo al masticar', $response->json('data.subjective'));

        $this->assertDatabaseHas('clinical_evolutions', [
            'id' => $evolution->id,
            'subjective' => 'Dolor agudo al masticar',
            'assessment' => 'Pulpitis irreversible',
            'requires_follow_up' => true,
        ]);
    }

    public function test_non_clinical_role_cannot_update_an_evolution(): void
    {
        $evolution = ClinicalEvolution::factory()->create(['subjective' => 'Original']);

        $this->actingAs($this->receptionist(), 'sanctum')
            ->putJson("/api/medical-records/evolutions/{$evolution->id}", [
                'subjective' => 'Alterado',
            ])
            ->assertForbidden();

        $this->assertDatabaseHas('clinical_evolutions', [
            'id' => $evolution->id,
            'subjective' => 'Original',
        ]);
    }

    public function test_clinician_can_soft_delete_an_evolution(): void
    {
        $user = $this->clinician();
        $record = MedicalRecord::factory()->create();
        $evolution = ClinicalEvolution::factory()->forRecord($record)->create();

        $this->actingAs($user, 'sanctum')
            ->deleteJson("/api/medical-records/evolutions/{$evolution->id}")
            ->assertOk();

        $this->assertSoftDeleted('clinical_evolutions', ['id' => $evolution->id]);

        $list = $this->actingAs($user, 'sanctum')
            ->getJson("/api/medical-records/{$record->id}/evolutions");
        $list->assertOk();
        $this->assertSame([], $list->json('data'));
    }

    public function test_non_clinical_role_cannot_delete_an_evolution(): void
    {
        $evolution = ClinicalEvolution::factory()->create();

        $this->actingAs($this->receptionist(), 'sanctum')
            ->deleteJson("/api/medical-records/evolutions/{$evolution->id}")
            ->assertForbidden();

        $this->assertNotSoftDeleted('clinical_evolutions', ['id' => $evolution->id]);
    }

    public function test_delete_returns_404_for_an_unknown_evolution(): void
    {
        $this->actingAs($this->clinician(), 'sanctum')
            ->deleteJson('/api/medical-records/evolutions/999999')
            ->assertNotFound();
    }
}
