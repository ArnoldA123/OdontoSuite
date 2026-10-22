<?php

namespace Tests\Feature\Api;

use App\Models\ClinicalEvolution;
use App\Models\MedicalRecord;
use App\Models\User;
use App\Services\MedicalRecordService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;
use RuntimeException;
use Tests\TestCase;

/**
 * F3 (#292) — the addendum write doors never echo the exception.
 *
 * Both addenda are clinical writes; a database failure must leave the diagnosis
 * in the log and answer a generic envelope, the same rule the cash register and
 * quotation controllers already follow (#191, #200-#204).
 */
class ClinicalAddendumErrorEnvelopeTest extends TestCase
{
    use RefreshDatabase;

    private function clinician(): User
    {
        return User::factory()->create(['role' => 'odontologo', 'is_active' => true]);
    }

    private function failingService(string $method): void
    {
        $this->mock(MedicalRecordService::class, function (MockInterface $mock) use ($method) {
            $mock->shouldReceive($method)
                ->once()
                ->andThrow(new RuntimeException('SQLSTATE[HY000] clinical_addenda unavailable'));
        });
    }

    public function test_a_failed_record_addendum_hides_the_exception(): void
    {
        $record = MedicalRecord::factory()->create();
        $this->failingService('addRecordAddendum');

        $response = $this->actingAs($this->clinician(), 'sanctum')
            ->postJson("/api/medical-records/{$record->id}/addenda", [
                'reason' => 'Error de digitación',
                'content' => 'La pieza correcta es la 36',
            ]);

        $response->assertStatus(500);
        $response->assertJsonPath('message', 'Error al registrar el addendum');
        $this->assertStringNotContainsString('SQLSTATE', $response->getContent());
        $this->assertNull($response->json('error'));
    }

    public function test_a_failed_evolution_addendum_hides_the_exception(): void
    {
        $evolution = ClinicalEvolution::factory()->create();
        $this->failingService('addEvolutionAddendum');

        $response = $this->actingAs($this->clinician(), 'sanctum')
            ->postJson("/api/medical-records/evolutions/{$evolution->id}/addenda", [
                'reason' => 'Error de digitación',
                'content' => 'La pieza correcta es la 36',
            ]);

        $response->assertStatus(500);
        $response->assertJsonPath('message', 'Error al registrar el addendum');
        $this->assertStringNotContainsString('SQLSTATE', $response->getContent());
        $this->assertNull($response->json('error'));
    }
}
