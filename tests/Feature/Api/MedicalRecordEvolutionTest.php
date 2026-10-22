<?php

namespace Tests\Feature\Api;

use App\Models\ClinicalEvolution;
use App\Models\MedicalRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Route;
use Tests\TestCase;

/**
 * F3 (#292) — the history is neither edited nor erased.
 *
 * The record and the evolution used to offer PUT (correct in place) and DELETE
 * (soft delete). Both are gone: a correction is an addendum and the original
 * row is never rewritten or removed. These tests pin the removal at the route
 * table, where a future `apiResource` would silently bring either verb back,
 * and at the database, where the row must survive the attempt.
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

    /**
     * @return list<string>
     */
    private function updateRoutes(): array
    {
        return collect(app('router')->getRoutes()->getRoutes())
            ->filter(fn (Route $route) => array_intersect(['PUT', 'PATCH'], $route->methods()) !== [])
            ->filter(fn (Route $route) => str_starts_with($route->uri(), 'api/medical-records/evolutions')
                || $route->uri() === 'api/medical-records/{medical_record}')
            ->map(fn (Route $route) => implode('|', array_values(array_intersect(['PUT', 'PATCH'], $route->methods())))
                .' '.$route->uri())
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

        // #292 removed the last verb registered on this URI (the evolution PUT),
        // so the router answers 404 instead of 405. Both mean the delete is
        // unreachable; the route-table test above is the primary guard.
        $this->actingAs($this->receptionist(), 'sanctum')
            ->deleteJson("/api/medical-records/evolutions/{$evolution->id}")
            ->assertNotFound();

        $this->assertDatabaseHas('clinical_evolutions', ['id' => $evolution->id]);
    }

    public function test_no_route_updates_a_history_or_an_evolution(): void
    {
        $this->assertSame([], $this->updateRoutes());
    }

    public function test_updating_a_history_is_not_allowed(): void
    {
        $record = MedicalRecord::factory()->create(['diagnosis' => 'Caries oclusal']);
        $snapshot = $record->fresh()->toArray();

        $this->actingAs($this->clinician(), 'sanctum')
            ->putJson("/api/medical-records/{$record->id}", ['diagnosis' => 'Reescrito'])
            ->assertStatus(405);

        $this->actingAs($this->clinician(), 'sanctum')
            ->patchJson("/api/medical-records/{$record->id}", ['diagnosis' => 'Reescrito'])
            ->assertStatus(405);

        $this->assertSame($snapshot, $record->fresh()->toArray());
    }

    public function test_updating_an_evolution_is_not_allowed(): void
    {
        $record = MedicalRecord::factory()->create();
        $evolution = ClinicalEvolution::factory()->forRecord($record)->create([
            'assessment' => 'Caries',
        ]);
        $snapshot = $evolution->fresh()->toArray();

        // No verb is registered on the evolution URI anymore, so the router
        // answers 404 (405 would need another verb on the same URI).
        $this->actingAs($this->clinician(), 'sanctum')
            ->putJson("/api/medical-records/evolutions/{$evolution->id}", [
                'assessment' => 'Reescrito',
            ])
            ->assertNotFound();

        $this->assertSame($snapshot, $evolution->fresh()->toArray());
    }
}
