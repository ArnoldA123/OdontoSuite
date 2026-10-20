<?php

namespace Tests\Feature\Api;

use App\Models\AiImageAnalysis;
use App\Models\ClinicalAttachment;
use App\Models\Patient;
use App\Models\User;
use App\Services\AiImageAnalysisService;
use App\Services\ClinicalAttachmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

/**
 * List contract for the AI analysis surface (slice T1, specs S5 and S7 of
 * `odd/tasks/ai-analysis-interface-redesign.md`):
 *  - `GET /api/ai-analysis` honours `per_page` (1..100) and `search`, and
 *    ignores blank filter values while keeping the existing filters working.
 *  - `DELETE /api/ai-analysis/{id}` answers 404 for an unknown id instead of 500.
 *  - No controller 500 leaks the exception message in an `error` key.
 */
class AiAnalysisContractTest extends TestCase
{
    use RefreshDatabase;

    protected function clinician(): User
    {
        return User::factory()->create([
            'role' => 'odontologo',
            'is_active' => true,
        ]);
    }

    protected function patient(string $firstName = 'Ana', string $lastName = 'Torres'): Patient
    {
        return Patient::factory()->create([
            'first_name' => $firstName,
            'last_name' => $lastName,
        ]);
    }

    /**
     * @param array<string, mixed> $attributes
     */
    protected function analysis(Patient $patient, User $user, array $attributes = []): AiImageAnalysis
    {
        return AiImageAnalysis::factory()->create(array_merge([
            'patient_id' => $patient->id,
            'requested_by' => $user->id,
        ], $attributes));
    }

    public function test_index_defaults_to_fifteen_items_per_page(): void
    {
        $user = $this->clinician();
        $patient = $this->patient();

        for ($i = 0; $i < 18; $i++) {
            $this->analysis($patient, $user);
        }

        $response = $this->actingAs($user)->getJson('/api/ai-analysis');

        $response->assertOk()
            ->assertJsonCount(15, 'data')
            ->assertJsonPath('meta.current_page', 1)
            ->assertJsonPath('meta.per_page', 15)
            ->assertJsonPath('meta.last_page', 2)
            ->assertJsonPath('meta.total', 18);

        $this->assertDatabaseCount('ai_image_analyses', 18);
    }

    public function test_index_honours_per_page_within_bounds(): void
    {
        $user = $this->clinician();
        $patient = $this->patient();

        for ($i = 0; $i < 18; $i++) {
            $this->analysis($patient, $user);
        }

        $this->actingAs($user)
            ->getJson('/api/ai-analysis?per_page=5')
            ->assertOk()
            ->assertJsonCount(5, 'data')
            ->assertJsonPath('meta.per_page', 5)
            ->assertJsonPath('meta.last_page', 4);

        $this->actingAs($user)
            ->getJson('/api/ai-analysis?per_page=1000')
            ->assertOk()
            ->assertJsonPath('meta.per_page', 100);

        $this->actingAs($user)
            ->getJson('/api/ai-analysis?per_page=0')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('meta.per_page', 1)
            ->assertJsonPath('meta.last_page', 18);

        $this->assertDatabaseCount('ai_image_analyses', 18);
    }

    public function test_index_search_matches_patient_name_case_insensitively(): void
    {
        $user = $this->clinician();
        $anaTorres = $this->analysis($this->patient('Ana', 'Torres'), $user);
        $betoRuiz = $this->analysis($this->patient('Beto', 'Ruiz'), $user);

        $this->actingAs($user)
            ->getJson('/api/ai-analysis?search=ana')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $anaTorres->id);

        $this->actingAs($user)
            ->getJson('/api/ai-analysis?search=TORRES')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $anaTorres->id);

        $this->actingAs($user)
            ->getJson('/api/ai-analysis?search=ana%20torres')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $anaTorres->id);

        $this->actingAs($user)
            ->getJson('/api/ai-analysis?search=ruiz')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $betoRuiz->id);

        $this->actingAs($user)
            ->getJson('/api/ai-analysis?search=nobody')
            ->assertOk()
            ->assertJsonCount(0, 'data')
            ->assertJsonPath('meta.total', 0);

        $this->assertDatabaseCount('ai_image_analyses', 2);
    }

    public function test_index_ignores_blank_filter_values(): void
    {
        $user = $this->clinician();
        $patient = $this->patient();
        $this->analysis($patient, $user, ['status' => 'completed']);
        $this->analysis($patient, $user, ['status' => 'pending']);
        $this->analysis($patient, $user, ['status' => 'failed']);

        $this->actingAs($user)
            ->getJson('/api/ai-analysis?status=&reviewed=&patient_id=&date_from=&date_to=&search=&per_page=')
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('meta.total', 3)
            ->assertJsonPath('meta.per_page', 15);

        $this->assertDatabaseCount('ai_image_analyses', 3);
    }

    public function test_index_keeps_existing_filters_working(): void
    {
        $user = $this->clinician();
        $firstPatient = $this->patient('Ana', 'Torres');
        $secondPatient = $this->patient('Beto', 'Ruiz');

        $this->analysis($firstPatient, $user, ['status' => 'completed', 'reviewed' => true]);
        $this->analysis($firstPatient, $user, ['status' => 'failed']);
        $this->analysis($secondPatient, $user, ['status' => 'completed']);

        $this->actingAs($user)
            ->getJson('/api/ai-analysis?status=failed')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.status', 'failed');

        $this->actingAs($user)
            ->getJson('/api/ai-analysis?reviewed=1')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.reviewed', true);

        $this->actingAs($user)
            ->getJson('/api/ai-analysis?patient_id='.$secondPatient->id)
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.patient_id', $secondPatient->id);

        $this->actingAs($user)
            ->getJson('/api/ai-analysis?date_from=2999-01-01')
            ->assertOk()
            ->assertJsonPath('meta.total', 0);

        $this->actingAs($user)
            ->getJson('/api/ai-analysis?date_to=1999-01-01')
            ->assertOk()
            ->assertJsonPath('meta.total', 0);

        $this->assertDatabaseCount('ai_image_analyses', 3);
    }

    public function test_destroy_missing_analysis_returns_not_found_without_internals(): void
    {
        $user = $this->clinician();

        $response = $this->actingAs($user)->deleteJson('/api/ai-analysis/999999');

        $response->assertStatus(404)
            ->assertJsonPath('message', 'Análisis no encontrado')
            ->assertJsonMissingPath('error');

        $this->assertStringNotContainsString('No query results', $response->getContent());
    }

    public function test_show_missing_analysis_returns_not_found_without_internals(): void
    {
        $user = $this->clinician();

        $response = $this->actingAs($user)->getJson('/api/ai-analysis/999999');

        $response->assertStatus(404)
            ->assertJsonPath('message', 'Análisis no encontrado')
            ->assertJsonMissingPath('error');

        $this->assertStringNotContainsString('No query results', $response->getContent());
    }

    public function test_destroy_rejects_a_reviewed_analysis_and_keeps_it(): void
    {
        $user = $this->clinician();
        $analysis = $this->analysis($this->patient(), $user, ['reviewed' => true]);

        $this->actingAs($user)
            ->deleteJson('/api/ai-analysis/'.$analysis->id)
            ->assertStatus(422)
            ->assertJsonPath('message', 'No se puede eliminar un análisis que ya ha sido revisado');

        $this->assertDatabaseHas('ai_image_analyses', ['id' => $analysis->id]);
    }

    public function test_destroy_removes_an_unreviewed_analysis(): void
    {
        $user = $this->clinician();
        $analysis = $this->analysis($this->patient(), $user);

        $this->actingAs($user)
            ->deleteJson('/api/ai-analysis/'.$analysis->id)
            ->assertOk()
            ->assertJsonPath('meta.message', 'Análisis eliminado exitosamente');

        $this->assertDatabaseMissing('ai_image_analyses', ['id' => $analysis->id]);
    }

    public function test_server_errors_do_not_leak_the_exception_message(): void
    {
        $user = $this->clinician();

        $this->mock(AiImageAnalysisService::class, function ($mock): void {
            $mock->shouldReceive('getUsageStats')
                ->andThrow(new RuntimeException('SQLSTATE[42000] access denied for user'));
        });

        $response = $this->actingAs($user)->getJson('/api/ai-analysis/stats');

        $response->assertStatus(500)
            ->assertJsonPath('message', 'Error al obtener estadísticas')
            ->assertJsonMissingPath('error');

        $this->assertStringNotContainsString('SQLSTATE', $response->getContent());
    }

    public function test_pending_accepts_per_page_within_bounds(): void
    {
        $user = $this->clinician();
        $patient = $this->patient();

        for ($i = 0; $i < 18; $i++) {
            $this->analysis($patient, $user, ['status' => 'completed', 'reviewed' => false]);
        }

        $this->actingAs($user)
            ->getJson('/api/ai-analysis/pending?per_page=5')
            ->assertOk()
            ->assertJsonCount(5, 'data')
            ->assertJsonPath('meta.per_page', 5)
            ->assertJsonPath('meta.last_page', 4)
            ->assertJsonPath('meta.total', 18);

        $this->actingAs($user)
            ->getJson('/api/ai-analysis/pending?per_page=1000')
            ->assertOk()
            ->assertJsonPath('meta.per_page', 100);

        $this->actingAs($user)
            ->getJson('/api/ai-analysis/pending?per_page=0')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('meta.per_page', 1);
    }

    public function test_by_patient_accepts_per_page_within_bounds(): void
    {
        $user = $this->clinician();
        $patient = $this->patient();

        for ($i = 0; $i < 18; $i++) {
            $this->analysis($patient, $user);
        }

        $this->actingAs($user)
            ->getJson('/api/ai-analysis/patient/'.$patient->id.'?per_page=5')
            ->assertOk()
            ->assertJsonCount(5, 'data')
            ->assertJsonPath('meta.per_page', 5)
            ->assertJsonPath('meta.last_page', 4)
            ->assertJsonPath('meta.total', 18);

        $this->actingAs($user)
            ->getJson('/api/ai-analysis/patient/'.$patient->id.'?per_page=1000')
            ->assertOk()
            ->assertJsonPath('meta.per_page', 100)
            ->assertJsonPath('meta.last_page', 1);
    }

    public function test_unparseable_date_filters_are_ignored(): void
    {
        $user = $this->clinician();
        $patient = $this->patient();
        $analysis = $this->analysis($patient, $user);

        $this->actingAs($user)
            ->getJson('/api/ai-analysis?date_from=abc&date_to=zzz')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id', $analysis->id);

        $this->actingAs($user)
            ->getJson('/api/ai-analysis/patient/'.$patient->id.'?date_from=abc&date_to=zzz')
            ->assertOk()
            ->assertJsonPath('meta.total', 1);
    }

    public function test_upload_and_analyze_answers_with_the_meta_envelope(): void
    {
        $user = $this->clinician();
        $patient = $this->patient();
        $attachment = ClinicalAttachment::factory()->create([
            'patient_id' => $patient->id,
            'created_by' => $user->id,
        ]);
        $analysis = $this->analysis($patient, $user, ['clinical_attachment_id' => $attachment->id]);

        $this->mock(ClinicalAttachmentService::class)
            ->shouldReceive('store')
            ->once()
            ->andReturn($attachment);

        $this->mock(AiImageAnalysisService::class)
            ->shouldReceive('analyzeImage')
            ->once()
            ->with($attachment->id)
            ->andReturn($analysis);

        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');

        $response = $this->actingAs($user)->post('/api/ai-analysis/upload-and-analyze', [
            'patient_id' => $patient->id,
            'category' => 'radiografia',
            'image' => UploadedFile::fake()->createWithContent('xray.png', $png),
        ]);

        $response->assertCreated()
            ->assertJsonPath('meta.message', 'Análisis completado exitosamente.')
            ->assertJsonPath('message', 'Análisis completado exitosamente.')
            ->assertJsonPath('data.id', $analysis->id);
    }

    public function test_usage_stats_propagates_a_query_failure_instead_of_zeroing_the_metrics(): void
    {
        $default = config('database.default');

        config([
            'database.default' => 'unreachable',
            'database.connections.unreachable' => [
                'driver' => 'sqlite',
                'database' => '/nonexistent/odontosuite/ai-stats.sqlite',
                'prefix' => '',
            ],
        ]);

        DB::purge('unreachable');

        try {
            $this->expectException(\Throwable::class);

            app(AiImageAnalysisService::class)->getUsageStats();
        } finally {
            config(['database.default' => $default]);
            DB::purge('unreachable');
        }
    }

    public function test_analysis_factory_builds_a_coherent_attachment_patient_and_requester(): void
    {
        $analysis = AiImageAnalysis::factory()->create();

        $this->assertSame('completed', $analysis->status);
        $this->assertFalse((bool) $analysis->reviewed);
        $this->assertNotNull($analysis->clinical_attachment_id);
        $this->assertSame($analysis->patient_id, $analysis->clinicalAttachment->patient_id);
        $this->assertSame($analysis->requested_by, $analysis->clinicalAttachment->created_by);
    }
}
