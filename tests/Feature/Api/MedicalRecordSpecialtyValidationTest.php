<?php

namespace Tests\Feature\Api;

use App\Models\ClinicalEvolution;
use App\Models\MedicalRecord;
use App\Models\Specialty;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Specialty source of truth for evolutions (issue #225).
 *
 * `clinical_evolutions.specialty` used to accept any string; the interface
 * could not even offer a selector because there was no contract. The catalog
 * is `specialties` (the same master the admin surface manages), and the
 * evolution stores its `code` — compatible with the legacy users.specialty
 * string and with the 50-character column.
 */
class MedicalRecordSpecialtyValidationTest extends TestCase
{
    use RefreshDatabase;

    private function clinician(): User
    {
        return User::factory()->create(['role' => 'odontologo', 'is_active' => true]);
    }

    private function specialty(string $code, string $name, bool $active = true): Specialty
    {
        return Specialty::create([
            'code' => $code,
            'name' => $name,
            'is_active' => $active,
        ]);
    }

    public function test_a_catalog_code_is_accepted_when_creating_an_evolution(): void
    {
        $this->specialty('endodoncia', 'Endodoncia');
        $record = MedicalRecord::factory()->create();

        $this->actingAs($this->clinician(), 'sanctum')
            ->postJson("/api/medical-records/{$record->id}/evolutions", [
                'subjective' => 'Dolor espontáneo',
                'specialty' => 'endodoncia',
            ])
            ->assertCreated()
            ->assertJsonPath('data.specialty', 'endodoncia');

        $this->assertDatabaseHas('clinical_evolutions', [
            'medical_record_id' => $record->id,
            'specialty' => 'endodoncia',
        ]);
    }

    public function test_a_value_outside_the_catalog_is_rejected(): void
    {
        $this->specialty('endodoncia', 'Endodoncia');
        $record = MedicalRecord::factory()->create();

        $this->actingAs($this->clinician(), 'sanctum')
            ->postJson("/api/medical-records/{$record->id}/evolutions", [
                'specialty' => 'inventada',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['specialty']);

        $this->assertDatabaseCount('clinical_evolutions', 0);
    }

    public function test_updating_an_evolution_accepts_only_catalog_codes(): void
    {
        $this->specialty('endodoncia', 'Endodoncia');
        $this->specialty('periodoncia', 'Periodoncia');
        $record = MedicalRecord::factory()->create();
        $evolution = ClinicalEvolution::factory()->forRecord($record)->create([
            'specialty' => 'endodoncia',
        ]);

        $this->actingAs($this->clinician(), 'sanctum')
            ->putJson("/api/medical-records/evolutions/{$evolution->id}", [
                'specialty' => 'periodoncia',
            ])
            ->assertOk()
            ->assertJsonPath('data.specialty', 'periodoncia');

        $this->actingAs($this->clinician(), 'sanctum')
            ->putJson("/api/medical-records/evolutions/{$evolution->id}", [
                'specialty' => 'inventada',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['specialty']);

        $evolution->refresh();
        $this->assertSame('periodoncia', $evolution->specialty);
    }

    public function test_omitting_the_specialty_keeps_the_stored_value(): void
    {
        $record = MedicalRecord::factory()->create();
        $evolution = ClinicalEvolution::factory()->forRecord($record)->create([
            'specialty' => 'odontologia_general',
        ]);

        $this->actingAs($this->clinician(), 'sanctum')
            ->putJson("/api/medical-records/evolutions/{$evolution->id}", [
                'subjective' => 'Sin cambios de especialidad',
            ])
            ->assertOk();

        $evolution->refresh();
        $this->assertSame('odontologia_general', $evolution->specialty);
    }

    public function test_an_inactive_catalog_code_still_validates_for_historical_rows(): void
    {
        $this->specialty('periodoncia', 'Periodoncia', active: false);
        $record = MedicalRecord::factory()->create();

        $this->actingAs($this->clinician(), 'sanctum')
            ->postJson("/api/medical-records/{$record->id}/evolutions", [
                'specialty' => 'periodoncia',
            ])
            ->assertCreated();
    }
}
