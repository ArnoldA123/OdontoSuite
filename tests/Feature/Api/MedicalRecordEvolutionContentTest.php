<?php

namespace Tests\Feature\Api;

use App\Models\MedicalRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * #425 S3 — an evolution must document something.
 *
 * The alta accepted an empty body: `POST /api/medical-records/{id}/evolutions`
 * answered 201 and the history gained an act with no content. Since #292 the
 * history is evidence — neither edited nor erased — so a blank act could never
 * be corrected, only addended. The request now refuses it before the service
 * runs, and nothing is written.
 *
 * Content is the clinical record itself: a non-empty SOAP block or indication,
 * a vital sign, or a measurement. `specialty`, `evolution_date`,
 * `requires_follow_up` and `cited_analysis_ids` travel with the act but do not
 * document it.
 *
 * Must run against the MySQL harness (AGENTS.md §6).
 */
class MedicalRecordEvolutionContentTest extends TestCase
{
    use RefreshDatabase;

    private const CONTENT_MESSAGE = 'La evolución debe registrar al menos un bloque SOAP, una indicación o una medición.';

    private function clinician(): User
    {
        return User::factory()->create(['role' => 'odontologo', 'is_active' => true]);
    }

    private function record(): MedicalRecord
    {
        return MedicalRecord::factory()->create();
    }

    #[Test]
    public function an_empty_body_is_rejected_and_writes_no_evolution(): void
    {
        $record = $this->record();

        $this->actingAs($this->clinician(), 'sanctum')
            ->postJson("/api/medical-records/{$record->id}/evolutions", [])
            ->assertStatus(422)
            ->assertJsonPath('errors.content.0', self::CONTENT_MESSAGE);

        $this->assertDatabaseCount('clinical_evolutions', 0);
    }

    #[Test]
    public function only_metadata_is_not_content(): void
    {
        $record = $this->record();

        $this->actingAs($this->clinician(), 'sanctum')
            ->postJson("/api/medical-records/{$record->id}/evolutions", [
                'evolution_date' => now()->toDateString(),
                'requires_follow_up' => true,
                'cited_analysis_ids' => [1],
            ])
            ->assertStatus(422)
            ->assertJsonPath('errors.content.0', self::CONTENT_MESSAGE);

        $this->assertDatabaseCount('clinical_evolutions', 0);
    }

    #[Test]
    public function a_blank_soap_block_is_not_content(): void
    {
        $record = $this->record();

        $this->actingAs($this->clinician(), 'sanctum')
            ->postJson("/api/medical-records/{$record->id}/evolutions", [
                'subjective' => '   ',
                'assessment' => "\n\t",
                'vital_signs' => ['blood_pressure' => ''],
            ])
            ->assertStatus(422)
            ->assertJsonPath('errors.content.0', self::CONTENT_MESSAGE);

        $this->assertDatabaseCount('clinical_evolutions', 0);
    }

    #[Test]
    public function a_vital_sign_alone_is_content(): void
    {
        $record = $this->record();

        $this->actingAs($this->clinician(), 'sanctum')
            ->postJson("/api/medical-records/{$record->id}/evolutions", [
                'vital_signs' => ['heart_rate' => 72],
            ])
            ->assertCreated()
            ->assertJsonPath('data.vital_signs.heart_rate', 72);

        $this->assertDatabaseCount('clinical_evolutions', 1);
    }

    #[Test]
    public function a_measurement_alone_is_content(): void
    {
        $record = $this->record();

        $this->actingAs($this->clinician(), 'sanctum')
            ->postJson("/api/medical-records/{$record->id}/evolutions", [
                'clinical_measurements' => ['pocket_depth_mm' => 3.5],
            ])
            ->assertCreated()
            ->assertJsonPath('data.clinical_measurements.pocket_depth_mm', 3.5);

        $this->assertDatabaseCount('clinical_evolutions', 1);
    }

    #[Test]
    public function a_single_soap_block_is_content(): void
    {
        $record = $this->record();

        $this->actingAs($this->clinician(), 'sanctum')
            ->postJson("/api/medical-records/{$record->id}/evolutions", [
                'plan' => 'Control en dos semanas',
            ])
            ->assertCreated()
            ->assertJsonPath('data.plan', 'Control en dos semanas');

        $this->assertDatabaseCount('clinical_evolutions', 1);
    }
}
