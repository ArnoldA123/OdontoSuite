<?php

namespace Tests\Feature\Api;

use App\Models\AiImageAnalysis;
use App\Models\Appointment;
use App\Models\ClinicalAttachment;
use App\Models\ClinicalEvolution;
use App\Models\MedicalRecord;
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
 *  - No controller 500 leaks the exception message in an `error` key.
 *
 * #296 — an analysis is annulled, never erased (specs S3 and S5):
 *  - `DELETE /api/ai-analysis/{id}` is gone (405) and
 *    `POST /api/ai-analysis/{id}/void` requires a reason.
 *  - The row survives with `voided_at`, `voided_by` and `void_reason`, and the
 *    review trace is untouched.
 *  - List reads exclude annulled rows; `index?voided=1` is the only way to
 *    consult the trace besides `show`.
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

    /**
     * @param array<string, mixed> $attributes
     */
    protected function voidedAnalysis(Patient $patient, User $user, array $attributes = []): AiImageAnalysis
    {
        return $this->analysis($patient, $user, array_merge([
            'voided_at' => now(),
            'voided_by' => $user->id,
            'void_reason' => 'Duplicado del estudio previo',
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

    public function test_delete_route_is_gone(): void
    {
        $user = $this->clinician();
        $analysis = $this->analysis($this->patient(), $user);

        $this->actingAs($user)
            ->deleteJson('/api/ai-analysis/'.$analysis->id)
            ->assertStatus(405);

        $this->assertDatabaseHas('ai_image_analyses', [
            'id' => $analysis->id,
            'voided_at' => null,
        ]);
    }

    public function test_void_missing_analysis_returns_not_found_without_internals(): void
    {
        $user = $this->clinician();

        $response = $this->actingAs($user)
            ->postJson('/api/ai-analysis/999999/void', ['reason' => 'Duplicado']);

        $response->assertStatus(404)
            ->assertJsonPath('message', 'Análisis no encontrado')
            ->assertJsonMissingPath('error');

        $this->assertStringNotContainsString('No query results', $response->getContent());
    }

    public function test_void_requires_a_reason(): void
    {
        $user = $this->clinician();
        $analysis = $this->analysis($this->patient(), $user);

        $this->actingAs($user)
            ->postJson('/api/ai-analysis/'.$analysis->id.'/void')
            ->assertStatus(422)
            ->assertJsonPath('errors.reason.0', 'El motivo de la anulación es obligatorio.');

        $this->actingAs($user)
            ->postJson('/api/ai-analysis/'.$analysis->id.'/void', ['reason' => str_repeat('a', 256)])
            ->assertStatus(422)
            ->assertJsonPath('errors.reason.0', 'El motivo de la anulación no puede exceder los 255 caracteres.');

        $this->assertDatabaseHas('ai_image_analyses', [
            'id' => $analysis->id,
            'voided_at' => null,
        ]);
    }

    public function test_void_keeps_the_row_and_records_the_annulment_trace(): void
    {
        $user = $this->clinician();
        $analysis = $this->analysis($this->patient(), $user);

        $this->actingAs($user)
            ->postJson('/api/ai-analysis/'.$analysis->id.'/void', [
                'reason' => '<b>Duplicado</b> del estudio previo',
            ])
            ->assertOk()
            ->assertJsonPath('meta.message', 'Análisis anulado exitosamente')
            ->assertJsonPath('data.id', $analysis->id)
            ->assertJsonPath('data.voided', true)
            ->assertJsonPath('data.void_reason', 'Duplicado del estudio previo')
            ->assertJsonPath('data.voided_by.name', $user->name);

        $this->assertDatabaseHas('ai_image_analyses', [
            'id' => $analysis->id,
            'voided_by' => $user->id,
            'void_reason' => 'Duplicado del estudio previo',
        ]);
        $this->assertNotNull($analysis->fresh()->voided_at);
    }

    public function test_void_preserves_the_review_trace_of_a_reviewed_analysis(): void
    {
        $requester = $this->clinician();
        $reviewer = $this->clinician();
        $reviewedAt = now()->subDay()->startOfSecond();
        $analysis = $this->analysis($this->patient(), $requester, [
            'reviewed' => true,
            'reviewed_by' => $reviewer->id,
            'reviewed_at' => $reviewedAt,
            'review_decision' => 'accepted',
            'review_notes' => 'Hallazgo compatible con caries oclusal',
        ]);

        $this->actingAs($requester)
            ->postJson('/api/ai-analysis/'.$analysis->id.'/void', ['reason' => 'Se subió la radiografía equivocada'])
            ->assertOk()
            ->assertJsonPath('data.voided', true)
            ->assertJsonPath('data.reviewed', true)
            ->assertJsonPath('data.review_decision', 'accepted')
            ->assertJsonPath('data.review_notes', 'Hallazgo compatible con caries oclusal')
            ->assertJsonPath('data.reviewed_by.id', $reviewer->id);

        $this->assertDatabaseHas('ai_image_analyses', [
            'id' => $analysis->id,
            'reviewed' => true,
            'reviewed_by' => $reviewer->id,
            'review_decision' => 'accepted',
            'review_notes' => 'Hallazgo compatible con caries oclusal',
            'void_reason' => 'Se subió la radiografía equivocada',
        ]);

        $this->assertSame(
            $reviewedAt->toDateTimeString(),
            $analysis->fresh()->reviewed_at->toDateTimeString()
        );
    }

    public function test_a_second_void_is_rejected(): void
    {
        $user = $this->clinician();
        $analysis = $this->voidedAnalysis($this->patient(), $user);

        $this->actingAs($user)
            ->postJson('/api/ai-analysis/'.$analysis->id.'/void', ['reason' => 'Otra vez'])
            ->assertStatus(422)
            ->assertJsonPath('errors.analysis.0', 'Este análisis ya ha sido anulado.');

        $this->assertDatabaseHas('ai_image_analyses', [
            'id' => $analysis->id,
            'void_reason' => 'Duplicado del estudio previo',
        ]);
    }

    public function test_voided_analyses_leave_the_working_set_but_stay_reachable_through_show(): void
    {
        $user = $this->clinician();
        $patient = $this->patient();

        Appointment::factory()->create([
            'user_id' => $user->id,
            'patient_id' => $patient->id,
        ]);

        $active = $this->analysis($patient, $user, ['status' => 'completed', 'reviewed' => false]);
        $voided = $this->voidedAnalysis($patient, $user, ['status' => 'completed', 'reviewed' => false]);

        $this->actingAs($user)
            ->getJson('/api/ai-analysis')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $active->id);

        $this->actingAs($user)
            ->getJson('/api/ai-analysis?voided=0')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $active->id);

        $this->actingAs($user)
            ->getJson('/api/ai-analysis?voided=1')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $voided->id)
            ->assertJsonPath('data.0.voided', true)
            ->assertJsonPath('data.0.void_reason', 'Duplicado del estudio previo')
            ->assertJsonPath('data.0.voided_by.id', $user->id);

        $this->actingAs($user)
            ->getJson('/api/ai-analysis/pending')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id', $active->id);

        $this->actingAs($user)
            ->getJson('/api/ai-analysis/patient/'.$patient->id)
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id', $active->id);

        $this->actingAs($user)
            ->getJson('/api/ai-analysis/attachment/'.$active->clinical_attachment_id)
            ->assertOk()
            ->assertJsonPath('data.id', $active->id);

        $this->actingAs($user)
            ->getJson('/api/ai-analysis/attachment/'.$voided->clinical_attachment_id)
            ->assertOk()
            ->assertJsonPath('data', null);

        $this->actingAs($user)
            ->getJson('/api/ai-analysis/stats')
            ->assertOk()
            ->assertJsonPath('data.total_analyses', 1)
            ->assertJsonPath('data.voided_analyses', 1);

        $this->actingAs($user)
            ->getJson('/api/ai-analysis/'.$voided->id)
            ->assertOk()
            ->assertJsonPath('data.id', $voided->id)
            ->assertJsonPath('data.voided', true)
            ->assertJsonPath('data.void_reason', 'Duplicado del estudio previo')
            ->assertJsonPath('data.voided_by.name', $user->name);
    }

    public function test_reviewing_a_voided_analysis_is_rejected(): void
    {
        $user = $this->clinician();
        $analysis = $this->voidedAnalysis($this->patient(), $user);

        $this->actingAs($user)
            ->postJson('/api/ai-analysis/'.$analysis->id.'/review', ['decision' => 'accepted'])
            ->assertStatus(422)
            ->assertJsonPath('errors.analysis.0', 'No se puede revisar un análisis anulado.');

        $this->assertDatabaseHas('ai_image_analyses', [
            'id' => $analysis->id,
            'reviewed' => false,
        ]);
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

        Appointment::factory()->create([
            'user_id' => $user->id,
            'patient_id' => $patient->id,
        ]);

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

        Appointment::factory()->create([
            'user_id' => $user->id,
            'patient_id' => $patient->id,
        ]);

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

        Appointment::factory()->create([
            'user_id' => $user->id,
            'patient_id' => $patient->id,
        ]);

        $this->mock(ClinicalAttachmentService::class)
            ->shouldReceive('store')
            ->once()
            ->andReturn($attachment);

        $this->mock(AiImageAnalysisService::class, function ($mock) use ($patient, $attachment, $analysis): void {
            $mock->shouldReceive('assertAnalysisAllowed')
                ->once()
                ->with($patient->id, null)
                ->andReturnNull();

            $mock->shouldReceive('analyzeImage')
                ->once()
                ->with($attachment->id, null)
                ->andReturn($analysis);
        });

        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');

        $response = $this->actingAs($user)->post('/api/ai-analysis/upload-and-analyze', [
            'patient_id' => $patient->id,
            'category' => 'radiografia',
            'notice_acknowledged' => '1',
            'image' => UploadedFile::fake()->createWithContent('xray.png', $png),
        ]);

        $response->assertCreated()
            ->assertJsonPath('meta.message', 'Análisis completado exitosamente.')
            ->assertJsonPath('message', 'Análisis completado exitosamente.')
            ->assertJsonPath('data.id', $analysis->id);
    }

    public function test_index_and_show_keep_the_prompt_and_raw_response_out_of_the_payload(): void
    {
        $user = $this->clinician();
        $analysis = $this->analysis($this->patient(), $user, [
            'tokens_used' => 4321,
            'findings' => ['Lesión radiolúcida en pieza 36'],
            'recommendations' => ['Derivar a endodoncia'],
        ]);

        $this->actingAs($user)
            ->getJson('/api/ai-analysis')
            ->assertOk()
            ->assertJsonMissingPath('data.0.prompt_sent')
            ->assertJsonMissingPath('data.0.raw_response')
            ->assertJsonMissingPath('data.0.requested_by.email')
            ->assertJsonMissingPath('data.0.requested_by.password')
            ->assertJsonPath('data.0.requested_by.name', $user->name)
            ->assertJsonPath('data.0.tokens_used', 4321)
            ->assertJsonPath('data.0.findings.0', 'Lesión radiolúcida en pieza 36')
            ->assertJsonPath('data.0.recommendations.0', 'Derivar a endodoncia')
            ->assertJsonPath('data.0.confidence_score', '72.50')
            ->assertJsonPath('data.0.model_used', 'gpt-4o');

        $this->actingAs($user)
            ->getJson('/api/ai-analysis/'.$analysis->id)
            ->assertOk()
            ->assertJsonMissingPath('data.prompt_sent')
            ->assertJsonMissingPath('data.raw_response')
            ->assertJsonMissingPath('data.requested_by.email')
            ->assertJsonPath('data.requested_by.name', $user->name)
            ->assertJsonPath('data.patient.id', $analysis->patient_id)
            ->assertJsonPath('data.patient.full_name', $analysis->patient->full_name)
            ->assertJsonPath('data.clinical_attachment.id', $analysis->clinical_attachment_id)
            ->assertJsonPath('data.clinical_attachment.file_path', $analysis->clinicalAttachment->file_path)
            ->assertJsonPath('data.status', 'completed')
            ->assertJsonPath('data.tokens_used', 4321);
    }

    public function test_a_clinician_without_a_clinical_relationship_is_forbidden(): void
    {
        $requestedBy = $this->clinician();
        $outsider = $this->clinician();
        $analysis = $this->analysis($this->patient(), $requestedBy);

        $this->actingAs($outsider)
            ->getJson('/api/ai-analysis/'.$analysis->id)
            ->assertForbidden();

        $this->actingAs($outsider)
            ->getJson('/api/ai-analysis/patient/'.$analysis->patient_id)
            ->assertForbidden();

        $this->actingAs($outsider)
            ->getJson('/api/ai-analysis/attachment/'.$analysis->clinical_attachment_id)
            ->assertForbidden();

        $this->actingAs($outsider)
            ->postJson('/api/ai-analysis/'.$analysis->id.'/review', ['decision' => 'accepted'])
            ->assertForbidden();

        $this->actingAs($outsider)
            ->postJson('/api/ai-analysis/'.$analysis->id.'/void', ['reason' => 'Duplicado'])
            ->assertForbidden();

        $this->assertDatabaseHas('ai_image_analyses', [
            'id' => $analysis->id,
            'reviewed' => false,
            'voided_at' => null,
        ]);
    }

    public function test_a_clinician_reaches_the_analyses_of_a_patient_with_her_own_appointment(): void
    {
        $doctor = $this->clinician();
        $requestedBy = $this->clinician();
        $analysis = $this->analysis($this->patient(), $requestedBy);

        Appointment::factory()->create([
            'user_id' => $doctor->id,
            'patient_id' => $analysis->patient_id,
        ]);

        $this->actingAs($doctor)
            ->getJson('/api/ai-analysis/'.$analysis->id)
            ->assertOk()
            ->assertJsonPath('data.id', $analysis->id);

        $this->actingAs($doctor)
            ->getJson('/api/ai-analysis/patient/'.$analysis->patient_id)
            ->assertOk()
            ->assertJsonPath('meta.total', 1);

        $this->actingAs($doctor)
            ->getJson('/api/ai-analysis/attachment/'.$analysis->clinical_attachment_id)
            ->assertOk()
            ->assertJsonPath('data.id', $analysis->id);
    }

    public function test_a_clinician_reaches_the_analyses_of_a_patient_with_her_own_evolution(): void
    {
        $doctor = $this->clinician();
        $analysis = $this->analysis($this->patient(), $this->clinician());

        ClinicalEvolution::factory()->create([
            'patient_id' => $analysis->patient_id,
            'medical_record_id' => MedicalRecord::factory()->create(['patient_id' => $analysis->patient_id])->id,
            'created_by' => $doctor->id,
        ]);

        $this->actingAs($doctor)
            ->getJson('/api/ai-analysis/'.$analysis->id)
            ->assertOk();
    }

    public function test_a_requester_and_a_reviewer_reach_their_own_analysis(): void
    {
        $requester = $this->clinician();
        $reviewer = $this->clinician();
        $ownRequest = $this->analysis($this->patient(), $requester);
        $ownReview = $this->analysis($this->patient(), $this->clinician(), [
            'reviewed' => true,
            'reviewed_by' => $reviewer->id,
        ]);

        $this->actingAs($requester)
            ->getJson('/api/ai-analysis/'.$ownRequest->id)
            ->assertOk();

        $this->actingAs($reviewer)
            ->getJson('/api/ai-analysis/'.$ownReview->id)
            ->assertOk();
    }

    public function test_an_administrator_reaches_every_analysis(): void
    {
        $admin = User::factory()->create(['role' => 'administrador', 'is_active' => true]);
        $colleague = $this->clinician();
        $analysis = $this->analysis($this->patient(), $colleague);

        $this->actingAs($admin)
            ->getJson('/api/ai-analysis')
            ->assertOk()
            ->assertJsonPath('meta.total', 1);

        $this->actingAs($admin)
            ->getJson('/api/ai-analysis/'.$analysis->id)
            ->assertOk();

        $this->actingAs($admin)
            ->getJson('/api/ai-analysis/stats')
            ->assertOk()
            ->assertJsonPath('data.total_analyses', 1);
    }

    public function test_index_pending_and_stats_are_scoped_to_the_viewer(): void
    {
        $doctor = $this->clinician();
        $colleague = $this->clinician();
        $relatedPatient = $this->patient('Ana', 'Torres');
        $hiddenPatient = $this->patient('Beto', 'Ruiz');

        Appointment::factory()->create([
            'user_id' => $doctor->id,
            'patient_id' => $relatedPatient->id,
        ]);

        $relatedAnalysis = $this->analysis($relatedPatient, $colleague);
        $ownRequest = $this->analysis($hiddenPatient, $doctor, ['status' => 'pending']);
        $hiddenAnalysis = $this->analysis($hiddenPatient, $colleague);

        $this->actingAs($doctor)
            ->getJson('/api/ai-analysis')
            ->assertOk()
            ->assertJsonPath('meta.total', 2)
            ->assertJsonCount(2, 'data')
            ->assertJsonMissingPath('data.0.prompt_sent');

        $this->actingAs($doctor)
            ->getJson('/api/ai-analysis/pending')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id', $relatedAnalysis->id);

        $this->actingAs($doctor)
            ->getJson('/api/ai-analysis/stats')
            ->assertOk()
            ->assertJsonPath('data.total_analyses', 2)
            ->assertJsonPath('data.completed_analyses', 1);

        $this->actingAs($doctor)
            ->getJson('/api/ai-analysis/'.$ownRequest->id)
            ->assertOk();

        $this->actingAs($doctor)
            ->getJson('/api/ai-analysis/'.$hiddenAnalysis->id)
            ->assertForbidden();
    }

    public function test_uploading_an_analysis_requires_access_to_the_patient(): void
    {
        $doctor = $this->clinician();
        $patient = $this->patient();
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');

        $this->actingAs($doctor)
            ->post('/api/ai-analysis/upload-and-analyze', [
                'patient_id' => $patient->id,
                'category' => 'radiografia',
                'image' => UploadedFile::fake()->createWithContent('xray.png', $png),
            ])
            ->assertForbidden();

        $this->assertDatabaseCount('ai_image_analyses', 0);
        $this->assertDatabaseCount('clinical_attachments', 0);
    }

    public function test_a_soft_deleted_appointment_does_not_grant_access(): void
    {
        $doctor = $this->clinician();
        $analysis = $this->analysis($this->patient(), $this->clinician());

        $appointment = Appointment::factory()->create([
            'user_id' => $doctor->id,
            'patient_id' => $analysis->patient_id,
        ]);
        $appointment->delete();

        $this->actingAs($doctor)
            ->getJson('/api/ai-analysis/'.$analysis->id)
            ->assertForbidden();
    }

    public function test_requesting_an_analysis_requires_access_to_the_attachment_patient(): void
    {
        $outsider = $this->clinician();
        $analysis = $this->analysis($this->patient(), $this->clinician());

        $this->actingAs($outsider)
            ->postJson('/api/ai-analysis/analyze/'.$analysis->clinical_attachment_id)
            ->assertForbidden();

        $this->assertDatabaseCount('ai_image_analyses', 1);
    }

    public function test_requesting_an_analysis_for_an_unknown_attachment_answers_not_found(): void
    {
        $this->actingAs($this->clinician())
            ->postJson('/api/ai-analysis/analyze/999999')
            ->assertNotFound()
            ->assertJsonPath('message', 'Adjunto no encontrado');
    }

    public function test_pending_ignores_blank_filters(): void
    {
        $user = $this->clinician();
        $patient = $this->patient();
        $this->analysis($patient, $user, ['status' => 'completed', 'reviewed' => false]);

        $this->actingAs($user)
            ->getJson('/api/ai-analysis/pending?requested_by=&patient_id=')
            ->assertOk()
            ->assertJsonPath('meta.total', 1);

        $this->actingAs($user)
            ->getJson('/api/ai-analysis/pending?requested_by=abc&patient_id=abc')
            ->assertOk()
            ->assertJsonPath('meta.total', 1);
    }

    public function test_by_attachment_does_not_reveal_analyses_out_of_scope(): void
    {
        $outsider = $this->clinician();
        $hiddenAttachment = ClinicalAttachment::factory()->create([
            'patient_id' => $this->patient()->id,
            'created_by' => $this->clinician()->id,
        ]);

        $this->actingAs($outsider)
            ->getJson('/api/ai-analysis/attachment/'.$hiddenAttachment->id)
            ->assertForbidden();
    }

    public function test_relative_date_expressions_are_ignored(): void
    {
        $user = $this->clinician();
        $analysis = $this->analysis($this->patient(), $user);

        $this->actingAs($user)
            ->getJson('/api/ai-analysis?date_from=next%20monday&date_to=tomorrow')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id', $analysis->id);
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
