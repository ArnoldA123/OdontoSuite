<?php

namespace Tests\Feature\Api;

use App\Models\AiImageAnalysis;
use App\Models\Appointment;
use App\Models\ClinicalAttachment;
use App\Models\ClinicalEncounter;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * #296 — decision support with AI (specs S4, S6 and S7).
 *
 * S4: an analysis hangs from the clinical act through its attachment, and the
 * act publishes only reviewed, non-annulled analyses. S6: both creation doors
 * require the professional to declare that the patient was informed the result
 * is assistive, and the row records who declared it and when. S7: the provider
 * usage is priced, the patient's daily use is capped by configuration, and the
 * reads publish the cost and the tokens of the population they describe.
 */
class AiAnalysisDecisionSupportTest extends TestCase
{
    use RefreshDatabase;

    /**
     * 1x1 PNG: enough for `getimagesize()` and for the `image` validation rule.
     */
    private const PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
    }

    private function clinician(?Patient $patient = null): User
    {
        $user = User::factory()->create([
            'role' => 'odontologo',
            'is_active' => true,
        ]);

        // ClinicalAccess grants the patient through an appointment or an
        // evolution; the AI surface adds the requester on top.
        if ($patient !== null) {
            Appointment::factory()->create([
                'user_id' => $user->id,
                'patient_id' => $patient->id,
            ]);
        }

        return $user;
    }

    private function patient(string $firstName = 'Ana', string $lastName = 'Torres'): Patient
    {
        return Patient::factory()->create([
            'first_name' => $firstName,
            'last_name' => $lastName,
        ]);
    }

    /**
     * @param array<string, mixed> $attributes
     */
    private function encounter(Patient $patient, User $user, array $attributes = []): ClinicalEncounter
    {
        return ClinicalEncounter::factory()->create(array_merge([
            'patient_id' => $patient->id,
            'user_id' => $user->id,
        ], $attributes));
    }

    /**
     * The analyze/{attachment} door reads the file from the public disk, so the
     * fixture writes one instead of only describing it in the database.
     *
     * @param array<string, mixed> $attributes
     */
    private function storedAttachment(Patient $patient, User $user, array $attributes = []): ClinicalAttachment
    {
        $path = UploadedFile::fake()
            ->createWithContent('xray.png', base64_decode(self::PNG))
            ->store('clinical-attachments', 'public');

        return ClinicalAttachment::factory()->create(array_merge([
            'patient_id' => $patient->id,
            'created_by' => $user->id,
            'file_path' => $path,
            'file_type' => 'image',
            'mime_type' => 'image/png',
            'category' => 'radiografia',
        ], $attributes));
    }

    /**
     * @return array<string, mixed>
     */
    private function uploadPayload(Patient $patient, array $overrides = []): array
    {
        return array_merge([
            'patient_id' => $patient->id,
            'category' => 'radiografia',
            'image' => UploadedFile::fake()->createWithContent('xray.png', base64_decode(self::PNG)),
            'notice_acknowledged' => '1',
        ], $overrides);
    }

    private function fakeProvider(int $tokens = 1200): void
    {
        Http::fake([
            'api.openai.com/*' => Http::response([
                'choices' => [[
                    'message' => [
                        'content' => json_encode([
                            'findings' => [[
                                'diagnosis' => 'Caries oclusal',
                                'location' => 'pieza 16',
                                'confidence' => 85,
                                'description' => 'Lesión radiolúcida en esmalte.',
                                'severity' => 'leve',
                            ]],
                            'recommendations' => ['Control radiográfico en 30 días'],
                            'confidence_score' => 85,
                        ]),
                    ],
                ]],
                'usage' => ['total_tokens' => $tokens],
            ], 200),
        ]);
    }

    public function test_the_analysis_inherits_the_encounter_of_its_attachment(): void
    {
        $this->fakeProvider();

        $patient = $this->patient();
        $user = $this->clinician($patient);
        $encounter = $this->encounter($patient, $user);
        $attachment = $this->storedAttachment($patient, $user, [
            'clinical_encounter_id' => $encounter->id,
        ]);

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/ai-analysis/analyze/{$attachment->id}", ['notice_acknowledged' => true])
            ->assertCreated()
            ->assertJsonPath('data.clinical_encounter_id', $encounter->id);

        $this->assertDatabaseHas('ai_image_analyses', [
            'clinical_attachment_id' => $attachment->id,
            'clinical_encounter_id' => $encounter->id,
        ]);
    }

    public function test_an_explicit_encounter_of_the_same_patient_is_stored(): void
    {
        $this->fakeProvider();

        $patient = $this->patient();
        $user = $this->clinician($patient);
        $encounter = $this->encounter($patient, $user);
        $attachment = $this->storedAttachment($patient, $user);

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/ai-analysis/analyze/{$attachment->id}", [
                'notice_acknowledged' => true,
                'clinical_encounter_id' => $encounter->id,
            ])
            ->assertCreated()
            ->assertJsonPath('data.clinical_encounter_id', $encounter->id);

        $this->assertDatabaseHas('ai_image_analyses', [
            'clinical_attachment_id' => $attachment->id,
            'clinical_encounter_id' => $encounter->id,
        ]);
    }

    public function test_an_encounter_of_another_patient_is_rejected(): void
    {
        $patient = $this->patient();
        $otherPatient = $this->patient('Beto', 'Ruiz');
        $user = $this->clinician($patient);
        $foreignEncounter = $this->encounter($otherPatient, $user);
        $attachment = $this->storedAttachment($patient, $user);

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/ai-analysis/analyze/{$attachment->id}", [
                'notice_acknowledged' => true,
                'clinical_encounter_id' => $foreignEncounter->id,
            ])
            ->assertStatus(422)
            ->assertJsonPath(
                'errors.clinical_encounter_id.0',
                'La atención seleccionada no pertenece al paciente.'
            );

        $this->assertDatabaseCount('ai_image_analyses', 0);
    }

    public function test_a_rejected_upload_leaves_no_orphan_attachment(): void
    {
        $patient = $this->patient();
        $otherPatient = $this->patient('Beto', 'Ruiz');
        $user = $this->clinician($patient);
        $foreignEncounter = $this->encounter($otherPatient, $user);

        $this->actingAs($user, 'sanctum')
            ->post(
                '/api/ai-analysis/upload-and-analyze',
                $this->uploadPayload($patient, ['clinical_encounter_id' => $foreignEncounter->id]),
                ['Accept' => 'application/json']
            )
            ->assertStatus(422)
            ->assertJsonPath(
                'errors.clinical_encounter_id.0',
                'La atención seleccionada no pertenece al paciente.'
            );

        $this->assertDatabaseCount('clinical_attachments', 0);
        $this->assertDatabaseCount('ai_image_analyses', 0);
    }

    public function test_an_upload_rejected_by_the_daily_cap_leaves_no_orphan_attachment(): void
    {
        $this->fakeProvider();
        config(['services.openai.daily_analysis_limit_per_patient' => 1]);

        $patient = $this->patient();
        $user = $this->clinician($patient);

        $this->actingAs($user, 'sanctum')
            ->post('/api/ai-analysis/upload-and-analyze', $this->uploadPayload($patient), [
                'Accept' => 'application/json',
            ])
            ->assertCreated();

        $this->assertDatabaseCount('clinical_attachments', 1);

        $this->actingAs($user, 'sanctum')
            ->post('/api/ai-analysis/upload-and-analyze', $this->uploadPayload($patient), [
                'Accept' => 'application/json',
            ])
            ->assertStatus(422)
            ->assertJsonPath(
                'errors.patient_id.0',
                'Se alcanzó el límite diario de análisis para este paciente.'
            );

        $this->assertDatabaseCount('clinical_attachments', 1);
        $this->assertDatabaseCount('ai_image_analyses', 1);
    }

    public function test_the_upload_door_requires_the_patient_notice(): void
    {
        $patient = $this->patient();
        $user = $this->clinician($patient);

        $this->actingAs($user, 'sanctum')
            ->post(
                '/api/ai-analysis/upload-and-analyze',
                $this->uploadPayload($patient, ['notice_acknowledged' => null]),
                ['Accept' => 'application/json']
            )
            ->assertStatus(422)
            ->assertJsonPath(
                'errors.notice_acknowledged.0',
                'Debe declarar que informó al paciente que el resultado es apoyo asistido por IA.'
            );

        $this->assertDatabaseCount('ai_image_analyses', 0);
    }

    public function test_the_upload_door_stores_the_notice_declaration(): void
    {
        $this->fakeProvider();

        $patient = $this->patient();
        $user = $this->clinician($patient);

        $response = $this->actingAs($user, 'sanctum')
            ->post('/api/ai-analysis/upload-and-analyze', $this->uploadPayload($patient), [
                'Accept' => 'application/json',
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.notice_acknowledged_by.id', $user->id);

        $this->assertNotNull($response->json('data.notice_acknowledged_at'));

        $this->assertDatabaseHas('ai_image_analyses', [
            'patient_id' => $patient->id,
            'notice_acknowledged_by' => $user->id,
        ]);

        $this->assertNotNull(
            AiImageAnalysis::where('patient_id', $patient->id)->first()->notice_acknowledged_at
        );
    }

    public function test_the_analyze_door_requires_the_patient_notice(): void
    {
        $patient = $this->patient();
        $user = $this->clinician($patient);
        $attachment = $this->storedAttachment($patient, $user);

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/ai-analysis/analyze/{$attachment->id}")
            ->assertStatus(422)
            ->assertJsonPath(
                'errors.notice_acknowledged.0',
                'Debe declarar que informó al paciente que el resultado es apoyo asistido por IA.'
            );

        $this->assertDatabaseCount('ai_image_analyses', 0);
    }

    public function test_the_cost_is_persisted_from_the_tokens_and_the_configured_price(): void
    {
        config(['services.openai.price_per_1k_tokens' => 0.02]);
        $this->fakeProvider(1500);
        $patient = $this->patient();
        $user = $this->clinician($patient);
        $attachment = $this->storedAttachment($patient, $user);

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/ai-analysis/analyze/{$attachment->id}", ['notice_acknowledged' => true])
            ->assertCreated()
            ->assertJsonPath('data.status', 'completed')
            ->assertJsonPath('data.tokens_used', 1500)
            ->assertJsonPath('data.estimated_cost', 0.03);

        $this->assertDatabaseHas('ai_image_analyses', [
            'clinical_attachment_id' => $attachment->id,
            'tokens_used' => 1500,
            'estimated_cost' => 0.03,
        ]);
    }

    public function test_a_failed_analysis_keeps_its_cost_empty(): void
    {
        Http::fake([
            'api.openai.com/*' => Http::response('provider unavailable', 500),
        ]);

        $patient = $this->patient();
        $user = $this->clinician($patient);
        $attachment = $this->storedAttachment($patient, $user);

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/ai-analysis/analyze/{$attachment->id}", ['notice_acknowledged' => true])
            ->assertCreated()
            ->assertJsonPath('data.status', 'failed')
            ->assertJsonPath('data.estimated_cost', null);

        $analysis = AiImageAnalysis::where('clinical_attachment_id', $attachment->id)->firstOrFail();

        $this->assertNull($analysis->tokens_used);
        $this->assertNull($analysis->estimated_cost);
    }

    public function test_the_daily_limit_rejects_a_new_analysis_of_the_patient(): void
    {
        config(['services.openai.daily_analysis_limit_per_patient' => 1]);
        $this->fakeProvider();

        $patient = $this->patient();
        $user = $this->clinician($patient);

        $this->actingAs($user, 'sanctum')
            ->post('/api/ai-analysis/upload-and-analyze', $this->uploadPayload($patient), [
                'Accept' => 'application/json',
            ])
            ->assertCreated();

        $this->actingAs($user, 'sanctum')
            ->post('/api/ai-analysis/upload-and-analyze', $this->uploadPayload($patient), [
                'Accept' => 'application/json',
            ])
            ->assertStatus(422)
            ->assertJsonPath(
                'errors.patient_id.0',
                'Se alcanzó el límite diario de análisis para este paciente.'
            );

        $this->assertDatabaseCount('ai_image_analyses', 1);
    }

    public function test_re_requesting_the_same_attachment_is_not_a_new_use(): void
    {
        config(['services.openai.daily_analysis_limit_per_patient' => 1]);
        $this->fakeProvider();

        $patient = $this->patient();
        $user = $this->clinician($patient);
        $attachment = $this->storedAttachment($patient, $user);

        $first = $this->actingAs($user, 'sanctum')
            ->postJson("/api/ai-analysis/analyze/{$attachment->id}", ['notice_acknowledged' => true])
            ->assertCreated();

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/ai-analysis/analyze/{$attachment->id}", ['notice_acknowledged' => true])
            ->assertCreated()
            ->assertJsonPath('data.id', $first->json('data.id'));

        $this->assertDatabaseCount('ai_image_analyses', 1);
    }

    public function test_index_filters_by_clinical_encounter(): void
    {
        $patient = $this->patient();
        $user = $this->clinician($patient);
        $encounter = $this->encounter($patient, $user);
        $otherEncounter = $this->encounter($patient, $user);

        $linked = AiImageAnalysis::factory()->create([
            'patient_id' => $patient->id,
            'requested_by' => $user->id,
            'clinical_encounter_id' => $encounter->id,
        ]);
        AiImageAnalysis::factory()->create([
            'patient_id' => $patient->id,
            'requested_by' => $user->id,
            'clinical_encounter_id' => $otherEncounter->id,
        ]);

        $this->actingAs($user, 'sanctum')
            ->getJson("/api/ai-analysis?clinical_encounter_id={$encounter->id}")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $linked->id)
            ->assertJsonPath('meta.total', 1);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/ai-analysis?clinical_encounter_id=')
            ->assertOk()
            ->assertJsonPath('meta.total', 2);
    }

    public function test_the_usage_stats_publish_the_tokens_and_the_cost_of_the_active_population(): void
    {
        $patient = $this->patient();
        $user = $this->clinician($patient);

        AiImageAnalysis::factory()->create([
            'patient_id' => $patient->id,
            'requested_by' => $user->id,
            'tokens_used' => 1000,
            'estimated_cost' => 0.01,
        ]);
        AiImageAnalysis::factory()->create([
            'patient_id' => $patient->id,
            'requested_by' => $user->id,
            'tokens_used' => 500,
            'estimated_cost' => 0.005,
        ]);
        AiImageAnalysis::factory()->create([
            'patient_id' => $patient->id,
            'requested_by' => $user->id,
            'tokens_used' => 9999,
            'estimated_cost' => 9.99,
            'voided_at' => now(),
            'voided_by' => $user->id,
            'void_reason' => 'Duplicado del estudio previo',
        ]);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/ai-analysis/stats')
            ->assertOk()
            ->assertJsonPath('data.total_tokens', 1500)
            ->assertJsonPath('data.estimated_cost', 0.015)
            ->assertJsonPath('data.total_analyses', 2);
    }

    public function test_the_patient_meta_carries_the_cost_and_the_tokens(): void
    {
        $patient = $this->patient();
        $user = $this->clinician($patient);

        AiImageAnalysis::factory()->create([
            'patient_id' => $patient->id,
            'requested_by' => $user->id,
            'tokens_used' => 2000,
            'estimated_cost' => 0.02,
        ]);
        AiImageAnalysis::factory()->create([
            'patient_id' => $patient->id,
            'requested_by' => $user->id,
            'tokens_used' => 400,
            'estimated_cost' => 0.004,
            'voided_at' => now(),
            'voided_by' => $user->id,
            'void_reason' => 'Se subió la radiografía equivocada',
        ]);

        $this->actingAs($user, 'sanctum')
            ->getJson("/api/ai-analysis/patient/{$patient->id}")
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('meta.total_tokens', 2000)
            ->assertJsonPath('meta.estimated_cost', 0.02);
    }

    public function test_the_resource_publishes_the_assistive_notice_and_its_declaration(): void
    {
        $patient = $this->patient();
        $user = $this->clinician($patient);
        $analysis = AiImageAnalysis::factory()->create([
            'patient_id' => $patient->id,
            'requested_by' => $user->id,
            'notice_acknowledged_at' => now()->startOfSecond(),
            'notice_acknowledged_by' => $user->id,
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson("/api/ai-analysis/{$analysis->id}")
            ->assertOk()
            ->assertJsonPath('data.assistive', true)
            ->assertJsonPath('data.assistive_notice', AiImageAnalysis::ASSISTIVE_NOTICE)
            ->assertJsonPath('data.notice_acknowledged_by.id', $user->id)
            ->assertJsonPath('data.notice_acknowledged_by.name', $user->name);

        $this->assertNotNull($response->json('data.notice_acknowledged_at'));
    }
}
