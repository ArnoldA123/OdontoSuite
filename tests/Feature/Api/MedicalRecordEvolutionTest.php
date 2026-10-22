<?php

namespace Tests\Feature\Api;

use App\Models\ClinicalEvolution;
use App\Models\MedicalRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Route;
use Tests\TestCase;

/**
 * F3 (#292) — the history is not erased.
 *
 * The evolution write surface used to offer PUT (edit) and DELETE (soft
 * delete). Both are gone: a correction is an addendum and the original row is
 * never removed. These tests pin the removal at the route table, where a future
 * `apiResource` would silently bring DELETE back, and at the model, where a
 * controller method alone could not stop it.
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

    /**
     * @return list<string>
     */
    private function deleteRoutes(): array
    {
        return collect(app('router')->getRoutes()->getRoutes())
            ->filter(fn (Route $route) => in_array('DELETE', $route->methods(), true))
            ->filter(fn (Route $route) => str_starts_with($route->uri(), 'api/medical-records'))
            ->map(fn (Route $route) => $route->uri())
            ->sort()
            ->values()
            ->all();
    }

    public function test_no_route_deletes_a_history_or_an_evolution(): void
    {
        // The only clinical delete that survives is the attachment archive
        // marker (#244): it removes a binary, not the documented act.
        $this->assertSame(
            ['api/medical-records/attachments/{attachment}'],
            $this->deleteRoutes(),
        );
    }

    public function test_deleting_a_history_is_not_allowed(): void
    {
        $record = MedicalRecord::factory()->create();

        $this->actingAs($this->clinician(), 'sanctum')
            ->deleteJson("/api/medical-records/{$record->id}")
            ->assertStatus(405);

        $this->assertDatabaseHas('medical_records', [
            'id' => $record->id,
            'is_active' => true,
        ]);
    }

    public function test_reception_cannot_reach_a_clinical_delete(): void
    {
        $evolution = ClinicalEvolution::factory()->create();

        $this->actingAs($this->receptionist(), 'sanctum')
            ->deleteJson("/api/medical-records/evolutions/{$evolution->id}")
            ->assertStatus(405);

        $this->assertDatabaseHas('clinical_evolutions', ['id' => $evolution->id]);
    }
}
