<?php

namespace Tests\Feature\Modules;

use App\Models\DentalPiece;
use App\Models\ImplantologyRecord;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * full-user-browser-audit-2026-08-05 / PR6 / Phase 4b — Specialty Records Round-Trip.
 *
 * Validation-first Feature test for the specialty records POST + GET
 * round-trip contract. Exercises `POST /api/specialty-records` and
 * `GET /api/specialty-records/{id}` (auth:sanctum +
 * role:administrador,odontologo,implantologo,tecnico_dental).
 * Must run against MySQL harness (AGENTS.md §6).
 */
class SpecialtyRecordsRoundTripTest extends TestCase
{
    use RefreshDatabase;

    private function implantologo(): User
    {
        return User::create([
            'name' => 'Implantologo Test',
            'email' => 'implantologo.test@example.com',
            'username' => 'implantologo_test',
            'password' => bcrypt('password'),
            'role' => 'implantologo',
            'is_active' => true,
        ]);
    }

    private function recepcionista(): User
    {
        return User::create([
            'name' => 'Recep Test',
            'email' => 'recep.test@example.com',
            'username' => 'recep_test',
            'password' => bcrypt('password'),
            'role' => 'recepcionista',
            'is_active' => true,
        ]);
    }

    private function patient(): Patient
    {
        return Patient::create([
            'first_name' => 'Implant',
            'last_name' => 'Patient',
            'email' => 'implant.patient.' . uniqid('', true) . '@example.com',
            'phone' => '+51 9' . str_pad((string) random_int(10000000, 99999999), 8, '0', STR_PAD_LEFT),
            'is_active' => true,
        ]);
    }

    private function dentalPiece(): DentalPiece
    {
        return DentalPiece::create([
            'fdi_number' => '16',
            'name' => 'Upper right first molar',
            'type' => 'molar',
            'quadrant' => 'superior_derecho',
            'position' => 6,
            'is_permanent' => true,
            'is_active' => true,
        ]);
    }

    private function validPayload(Patient $patient, DentalPiece $piece): array
    {
        return [
            'specialty' => 'implantologia',
            'patient_id' => $patient->id,
            'dental_piece_id' => $piece->id,
            'implant_brand' => 'NobelBiocare',
            'implant_model' => 'TiUltra',
            'implant_diameter' => '4.3',
            'implant_length' => '11.5',
            'batch_number' => 'BATCH-' . uniqid(),
            'serial_number' => 'SN-' . uniqid(),
            'placement_date' => now()->subDay()->toDateString(),
            'status' => 'placed',
            'surgical_notes' => 'Sin complicaciones',
            'torque_value' => 35.00,
        ];
    }

    public function test_post_then_get_round_trip_preserves_implantology_record(): void
    {
        $user = $this->implantologo();
        $patient = $this->patient();
        $piece = $this->dentalPiece();
        $payload = $this->validPayload($patient, $piece);

        $postResponse = $this->actingAs($user, 'sanctum')
            ->postJson('/api/specialty-records', $payload);

        $postResponse->assertStatus(201)
            ->assertJsonStructure(['data' => ['id', 'patient_id', 'implant_brand', 'implant_model']]);

        $newId = $postResponse->json('data.id');
        $this->assertIsInt($newId);
        $this->assertSame($payload['implant_brand'], $postResponse->json('data.implant_brand'));
        $this->assertSame($payload['implant_model'], $postResponse->json('data.implant_model'));
        $this->assertSame($payload['batch_number'], $postResponse->json('data.batch_number'));

        $getResponse = $this->actingAs($user, 'sanctum')
            ->getJson("/api/specialty-records/{$newId}");

        $getResponse->assertOk()
            ->assertJsonStructure(['data' => ['id', 'patient_id', 'implant_brand', 'implant_model', 'batch_number']]);

        $row = $getResponse->json('data');
        $this->assertSame($newId, $row['id']);
        $this->assertSame($payload['patient_id'], $row['patient_id']);
        $this->assertSame($payload['implant_brand'], $row['implant_brand']);
        $this->assertSame($payload['implant_model'], $row['implant_model']);
        $this->assertSame($payload['implant_diameter'], $row['implant_diameter']);
        $this->assertSame($payload['implant_length'], $row['implant_length']);
        $this->assertSame($payload['batch_number'], $row['batch_number']);
        $this->assertSame($payload['placement_date'], substr((string) $row['placement_date'], 0, 10));
        $this->assertSame($payload['status'], $row['status']);
        $this->assertSame($payload['surgical_notes'], $row['surgical_notes']);

        $this->assertDatabaseHas('implantology_records', [
            'id' => $newId,
            'patient_id' => $patient->id,
            'implant_brand' => $payload['implant_brand'],
        ]);
    }

    public function test_invalid_payload_missing_patient_id_returns_422(): void
    {
        $user = $this->implantologo();
        $piece = $this->dentalPiece();
        $payload = $this->validPayload($this->patient(), $piece);
        unset($payload['patient_id']);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/specialty-records', $payload);

        $response->assertStatus(422)
            ->assertJsonStructure(['message', 'errors' => ['patient_id']]);

        // StoreSpecialtyRecordRequest throws ValidationException before the
        // controller body executes, so Laravel's global renderer wins
        // (canonical "Los datos proporcionados no son válidos.").
        $this->assertSame('Los datos proporcionados no son válidos.', $response->json('message'));
        $this->assertNotEmpty($response->json('errors.patient_id'), '422 envelope MUST include the patient_id error.');

        $this->assertSame(0, ImplantologyRecord::count(), 'Failed 422 MUST NOT persist an ImplantologyRecord row.');
    }

    public function test_recepcionista_role_is_rejected_with_403(): void
    {
        $user = $this->recepcionista();
        $patient = $this->patient();
        $piece = $this->dentalPiece();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/specialty-records', $this->validPayload($patient, $piece));

        $response->assertStatus(403)->assertJsonStructure(['message']);
        $this->assertSame('No tienes permisos para acceder a este recurso.', $response->json('message'));
    }

    public function test_unauthenticated_request_is_rejected_with_401_envelope(): void
    {
        $previousDebug = (bool) config('app.debug');
        config(['app.debug' => false]);

        try {
            $response = $this->postJson('/api/specialty-records', ['specialty' => 'implantologia']);

            $response->assertStatus(401)
                ->assertJsonStructure(['message'])
                ->assertHeader('WWW-Authenticate', 'Bearer realm="api"');

            $this->assertSame('No autenticado.', $response->json('message'));
            $this->assertNull($response->json('data'), 'No specialty-record payload may leak in the unauthenticated envelope.');
        } finally {
            config(['app.debug' => $previousDebug]);
        }
    }
}