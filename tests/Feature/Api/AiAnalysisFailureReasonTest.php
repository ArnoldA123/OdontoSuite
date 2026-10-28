<?php

namespace Tests\Feature\Api;

use App\Models\AiImageAnalysis;
use App\Models\Appointment;
use App\Models\ClinicalAttachment;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * #427 (specs S6 and S7) — the result contract of an analysis that failed.
 *
 * The provider call is caught by the service, so the row is created and the
 * door answers 201 either way. What the door may not do is present the failure
 * as a completed analysis: an `upload-and-analyze` whose row ends `failed`
 * publishes `El análisis no se pudo completar.`, and the row carries a readable
 * `failure_reason` for the professional while the technical message stays in
 * the audit-only `raw_response`.
 */
class AiAnalysisFailureReasonTest extends TestCase
{
    use RefreshDatabase;

    /**
     * 1x1 PNG: enough for `getimagesize()` and for the `image` validation rule.
     */
    private const PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';

    private const FAILURE_MESSAGE = 'El análisis no se pudo completar.';

    private const PROVIDER_REASON = 'El proveedor de IA no devolvió un resultado válido.';

    private const CONNECTION_REASON = 'No se pudo conectar con el proveedor de IA.';

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
    }

    private function clinician(Patient $patient): User
    {
        $user = User::factory()->create([
            'role' => 'odontologo',
            'is_active' => true,
        ]);

        Appointment::factory()->create([
            'user_id' => $user->id,
            'patient_id' => $patient->id,
        ]);

        return $user;
    }

    private function patient(): Patient
    {
        return Patient::factory()->create([
            'first_name' => 'Ana',
            'last_name' => 'Torres',
        ]);
    }

    /**
     * @param  array<string, mixed> $overrides
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

    /**
     * The `analyze/{attachment}` door reads the file from the public disk, so the
     * fixture writes one instead of only describing it in the database.
     *
     * @param array<string, mixed> $overrides
     */
    private function storedAttachment(Patient $patient, User $user, array $overrides = []): ClinicalAttachment
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
        ], $overrides));
    }

    private function fakeProvider(): void
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
                'usage' => ['total_tokens' => 1200],
            ], 200),
        ]);
    }

    public function test_a_failed_upload_answers_with_a_failure_message_and_a_readable_reason(): void
    {
        Http::fake([
            'api.openai.com/*' => Http::response([
                'error' => ['message' => 'invalid api key'],
            ], 401),
        ]);

        $patient = $this->patient();
        $user = $this->clinician($patient);

        $response = $this->actingAs($user, 'sanctum')
            ->post('/api/ai-analysis/upload-and-analyze', $this->uploadPayload($patient), [
                'Accept' => 'application/json',
            ]);

        $response->assertCreated()
            ->assertJsonPath('message', self::FAILURE_MESSAGE)
            ->assertJsonPath('meta.message', self::FAILURE_MESSAGE)
            ->assertJsonPath('data.status', 'failed')
            ->assertJsonPath('data.failure_reason', self::PROVIDER_REASON)
            ->assertJsonMissingPath('data.raw_response')
            ->assertJsonMissingPath('data.prompt_sent');

        // The provider body is audit material: it never reaches the reader.
        $this->assertStringNotContainsString('invalid api key', $response->getContent());

        $this->assertDatabaseHas('ai_image_analyses', [
            'patient_id' => $patient->id,
            'status' => 'failed',
            'failure_reason' => self::PROVIDER_REASON,
        ]);

        $analysis = AiImageAnalysis::where('patient_id', $patient->id)->firstOrFail();

        // The technical message stays in the audit column.
        $this->assertStringContainsString('invalid api key', (string) $analysis->raw_response);
        $this->assertSame(self::PROVIDER_REASON, $analysis->failure_reason);
    }

    public function test_a_successful_upload_keeps_the_completion_message_and_no_failure_reason(): void
    {
        $this->fakeProvider();

        $patient = $this->patient();
        $user = $this->clinician($patient);

        $response = $this->actingAs($user, 'sanctum')
            ->post('/api/ai-analysis/upload-and-analyze', $this->uploadPayload($patient), [
                'Accept' => 'application/json',
            ]);

        $response->assertCreated()
            ->assertJsonPath('message', 'Análisis completado exitosamente.')
            ->assertJsonPath('meta.message', 'Análisis completado exitosamente.')
            ->assertJsonPath('data.status', 'completed')
            ->assertJsonPath('data.failure_reason', null);

        $this->assertDatabaseHas('ai_image_analyses', [
            'patient_id' => $patient->id,
            'status' => 'completed',
            'failure_reason' => null,
        ]);
    }

    public function test_a_connection_failure_gets_its_own_reason(): void
    {
        Http::fake(function (): void {
            throw new ConnectionException('cURL error 28: timed out');
        });

        $patient = $this->patient();
        $user = $this->clinician($patient);

        $response = $this->actingAs($user, 'sanctum')
            ->post('/api/ai-analysis/upload-and-analyze', $this->uploadPayload($patient), [
                'Accept' => 'application/json',
            ]);

        $response->assertCreated()
            ->assertJsonPath('message', self::FAILURE_MESSAGE)
            ->assertJsonPath('data.status', 'failed')
            ->assertJsonPath('data.failure_reason', self::CONNECTION_REASON)
            ->assertJsonMissingPath('data.raw_response');

        $this->assertStringNotContainsString('cURL error 28', $response->getContent());

        $this->assertDatabaseHas('ai_image_analyses', [
            'patient_id' => $patient->id,
            'status' => 'failed',
            'failure_reason' => self::CONNECTION_REASON,
        ]);
    }

    public function test_the_index_publishes_the_failure_reason_and_keeps_the_provider_artifacts_private(): void
    {
        $patient = $this->patient();
        $user = $this->clinician($patient);

        $analysis = AiImageAnalysis::factory()->create([
            'patient_id' => $patient->id,
            'requested_by' => $user->id,
            'status' => 'failed',
            'raw_response' => 'Error en la API de OpenAI: {"error":{"message":"invalid api key"}}',
            'failure_reason' => self::PROVIDER_REASON,
        ]);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/ai-analysis')
            ->assertOk()
            ->assertJsonPath('data.0.id', $analysis->id)
            ->assertJsonPath('data.0.failure_reason', self::PROVIDER_REASON)
            ->assertJsonMissingPath('data.0.raw_response')
            ->assertJsonMissingPath('data.0.prompt_sent');
    }

    public function test_the_analyze_door_does_not_claim_a_result_when_the_row_failed(): void
    {
        Http::fake([
            'api.openai.com/*' => Http::response('provider unavailable', 500),
        ]);

        $patient = $this->patient();
        $user = $this->clinician($patient);
        $attachment = $this->storedAttachment($patient, $user);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/ai-analysis/analyze/'.$attachment->id, ['notice_acknowledged' => true])
            ->assertCreated()
            ->assertJsonPath('data.status', 'failed')
            ->assertJsonPath('meta.message', self::FAILURE_MESSAGE)
            ->assertJsonPath('data.failure_reason', self::PROVIDER_REASON);
    }

    public function test_the_detail_of_a_failed_analysis_publishes_its_reason(): void
    {
        $patient = $this->patient();
        $user = $this->clinician($patient);

        $analysis = AiImageAnalysis::factory()->create([
            'patient_id' => $patient->id,
            'requested_by' => $user->id,
            'status' => 'failed',
            'raw_response' => 'cURL error 28: timed out',
            'failure_reason' => self::CONNECTION_REASON,
        ]);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/ai-analysis/'.$analysis->id)
            ->assertOk()
            ->assertJsonPath('data.id', $analysis->id)
            ->assertJsonPath('data.status', 'failed')
            ->assertJsonPath('data.failure_reason', self::CONNECTION_REASON)
            ->assertJsonMissingPath('data.raw_response')
            ->assertJsonMissingPath('data.prompt_sent');
    }
}
