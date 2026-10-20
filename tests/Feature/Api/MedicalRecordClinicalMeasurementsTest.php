<?php

namespace Tests\Feature\Api;

use App\Models\ClinicalEvolution;
use App\Models\MedicalRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Contract tests for `clinical_evolutions.clinical_measurements` (issue #222).
 *
 * The column is a JSON array that `MedicalRecordService` persisted and
 * `ClinicalEvolution` casted from the first migration, but the evolution
 * surfaces never captured or rendered it. The contract the interface now
 * writes is flat and unit-bearing:
 *
 *   - pocket_depth_mm         numeric 0..20   (periodontal probing depth, mm)
 *   - gingival_recession_mm   numeric 0..20   (gingival margin, mm)
 *   - plaque_index_percent    numeric 0..100  (O'Leary plaque index, %)
 *   - bleeding_on_probing     boolean         (bleeding on probing)
 *   - tooth_mobility          integer 0..3    (Miller mobility grade)
 *
 * Every key is optional and the whole array is optional; an undeclared key is
 * rejected so the contract cannot drift key by key again.
 */
class MedicalRecordClinicalMeasurementsTest extends TestCase
{
    use RefreshDatabase;

    private function clinician(): User
    {
        return User::factory()->create(['role' => 'odontologo', 'is_active' => true]);
    }

    private function record(): MedicalRecord
    {
        return MedicalRecord::factory()->create();
    }

    public function test_clinician_can_record_the_full_measurement_contract(): void
    {
        $record = $this->record();
        $measurements = [
            'pocket_depth_mm' => 4.5,
            'gingival_recession_mm' => 2,
            'plaque_index_percent' => 35,
            'bleeding_on_probing' => true,
            'tooth_mobility' => 2,
        ];

        $response = $this->actingAs($this->clinician(), 'sanctum')
            ->postJson("/api/medical-records/{$record->id}/evolutions", [
                'subjective' => 'Sangrado al cepillarse',
                'clinical_measurements' => $measurements,
            ]);

        $response->assertCreated();
        $this->assertSame($measurements, $response->json('data.clinical_measurements'));

        $evolution = ClinicalEvolution::firstOrFail();
        $this->assertSame($measurements, $evolution->clinical_measurements);
    }

    public function test_measurements_are_optional(): void
    {
        $record = $this->record();

        $response = $this->actingAs($this->clinician(), 'sanctum')
            ->postJson("/api/medical-records/{$record->id}/evolutions", [
                'subjective' => 'Control de rutina',
            ]);

        $response->assertCreated();
        $this->assertNull($response->json('data.clinical_measurements'));
    }

    public function test_out_of_range_measurements_are_rejected_key_by_key(): void
    {
        $record = $this->record();

        $response = $this->actingAs($this->clinician(), 'sanctum')
            ->postJson("/api/medical-records/{$record->id}/evolutions", [
                'clinical_measurements' => [
                    'pocket_depth_mm' => 21,
                    'gingival_recession_mm' => -1,
                    'plaque_index_percent' => 101,
                    'bleeding_on_probing' => 'a veces',
                    'tooth_mobility' => 4,
                ],
            ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors([
            'clinical_measurements.pocket_depth_mm',
            'clinical_measurements.gingival_recession_mm',
            'clinical_measurements.plaque_index_percent',
            'clinical_measurements.bleeding_on_probing',
            'clinical_measurements.tooth_mobility',
        ]);

        $this->assertDatabaseCount('clinical_evolutions', 0);
    }

    public function test_unknown_measurement_keys_are_rejected(): void
    {
        $record = $this->record();

        $response = $this->actingAs($this->clinician(), 'sanctum')
            ->postJson("/api/medical-records/{$record->id}/evolutions", [
                'clinical_measurements' => [
                    'pocket_depth_mm' => 3,
                    'invented_measure' => 7,
                ],
            ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['clinical_measurements']);
        $this->assertDatabaseCount('clinical_evolutions', 0);
    }

    public function test_updating_an_evolution_validates_and_persists_measurements(): void
    {
        $user = $this->clinician();
        $record = $this->record();
        $evolution = ClinicalEvolution::factory()->forRecord($record)->create([
            'clinical_measurements' => ['tooth_mobility' => 1],
        ]);

        $this->actingAs($user, 'sanctum')
            ->putJson("/api/medical-records/evolutions/{$evolution->id}", [
                'clinical_measurements' => [
                    'pocket_depth_mm' => 3.5,
                    'bleeding_on_probing' => false,
                    'tooth_mobility' => 0,
                ],
            ])
            ->assertOk()
            ->assertJsonPath('data.clinical_measurements.tooth_mobility', 0);

        $evolution->refresh();
        $this->assertSame([
            'pocket_depth_mm' => 3.5,
            'bleeding_on_probing' => false,
            'tooth_mobility' => 0,
        ], $evolution->clinical_measurements);

        $this->actingAs($user, 'sanctum')
            ->putJson("/api/medical-records/evolutions/{$evolution->id}", [
                'clinical_measurements' => ['plaque_index_percent' => 150],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['clinical_measurements.plaque_index_percent']);

        $evolution->refresh();
        $this->assertSame(3.5, $evolution->clinical_measurements['pocket_depth_mm']);
    }
}
