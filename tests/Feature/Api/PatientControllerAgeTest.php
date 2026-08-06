<?php

namespace Tests\Feature\Api;

use App\Models\Patient;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * full-user-browser-audit-2026-08-05 / PR2 follow-up / Phase 2b — controller wire-up.
 *
 * RED test for the bounded follow-up identified by verify-report #337:
 * PatientController::index / show / store / update returned raw Eloquent models
 * and never invoked PatientResource, so the `age` key never reached the JSON
 * contract consumed by PatientsPage and PatientSelector.
 *
 * This Feature test exercises the real controller path via Sanctum acting-as,
 * which is the only level at which the bug surfaces. The Unit test
 * (PatientResourceAgeTest) already passes; that one is intentionally retained
 * as the source-of-truth on the resource class in isolation.
 *
 * Spec scenarios under test:
 *   - GET /api/patients returns data[0].age as a JSON integer (adult, 1990-04-15 → 36 against 2026-08-05)
 *   - GET /api/patients/{id} returns data.age as a JSON integer
 *   - GET /api/patients returns data[i].age: null for a patient with null birth_date
 *   - POST /api/patients returns data.age as a JSON integer derived from the posted birth_date
 *   - PUT /api/patients/{id} returns data.age as a JSON integer derived from the updated birth_date
 *   - Pagination meta (current_page, last_page, per_page, total) is preserved
 *   - Existing permissions/auth envelope is preserved (unauthenticated → 401)
 *
 * Rollback boundary: delete this file + revert the four PatientController
 * methods (index, show, store, update) to return raw `$patient` and
 * `$patients->items()`. No data impact, no migration, no model change.
 */
class PatientControllerAgeTest extends TestCase
{
    use RefreshDatabase;

    /** Pin "today" so the integer age assertion stays deterministic. */
    private const TODAY_ISO = '2026-08-05 12:00:00';

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse(self::TODAY_ISO, 'UTC'));
        date_default_timezone_set('UTC');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        date_default_timezone_set(@date_default_timezone_get());
        parent::tearDown();
    }

    private function admin(): User
    {
        return User::factory()->create([
            'role' => 'administrador',
            'is_active' => true,
        ]);
    }

    private function seedAdultPatient(string $firstName = 'Juan'): Patient
    {
        return Patient::create([
            'first_name' => $firstName,
            'last_name' => 'Pérez',
            'email' => strtolower($firstName) . '.perez@example.com',
            // uniqid() suffix prevents patients_phone_unique collisions when this
            // helper is invoked repeatedly (e.g. test_index_preserves_pagination_meta_envelope
            // bulk-seeds 20 rows). Format stays a valid phone string, length well
            // under varchar(255). Slice 07d (PR7d) — same defect class as PR7c.
            'phone' => '+51 987 654 321-' . uniqid(),
            'birth_date' => '1990-04-15',
            'gender' => 'male',
            'is_active' => true,
        ]);
    }

    private function seedPatientWithoutBirthDate(string $firstName = 'Sin'): Patient
    {
        return Patient::create([
            'first_name' => $firstName,
            'last_name' => 'Fecha',
            'email' => strtolower($firstName) . '.sinfecha@example.com',
            'phone' => '+51 987 111 222-' . uniqid(),
            'birth_date' => null,
            'gender' => 'other',
            'is_active' => true,
        ]);
    }

    // -------------------------------------------------------------------
    // GET /api/patients — list endpoint must include age on every row
    // -------------------------------------------------------------------

    public function test_index_returns_age_integer_for_seeded_patient(): void
    {
        $admin = $this->admin();
        $patient = $this->seedAdultPatient();

        $response = $this->actingAs($admin, 'sanctum')
            ->getJson('/api/patients');

        $response->assertOk();

        $rows = $response->json('data');
        $this->assertIsArray($rows);
        $this->assertNotEmpty($rows, 'GET /api/patients MUST return at least one row.');

        $matching = collect($rows)->firstWhere('id', $patient->id);
        $this->assertNotNull(
            $matching,
            'GET /api/patients MUST include the seeded patient (id=' . $patient->id . ').'
        );
        $this->assertArrayHasKey(
            'age',
            $matching,
            'GET /api/patients data MUST include the `age` key (verify #337 CRITICAL: controller bypasses PatientResource).'
        );
        $this->assertIsInt(
            $matching['age'],
            'GET /api/patients data[i].age MUST be a JSON integer, not float, string, or null.'
        );
        $this->assertSame(
            36,
            $matching['age'],
            'GET /api/patients data[i].age for 1990-04-15 against 2026-08-05 MUST equal 36.'
        );
    }

    public function test_index_returns_age_null_for_patient_with_null_birth_date(): void
    {
        $admin = $this->admin();
        $patient = $this->seedPatientWithoutBirthDate();

        $response = $this->actingAs($admin, 'sanctum')
            ->getJson('/api/patients');

        $response->assertOk();

        $rows = $response->json('data');
        $matching = collect($rows)->firstWhere('id', $patient->id);

        $this->assertNotNull($matching);
        $this->assertArrayHasKey(
            'age',
            $matching,
            'GET /api/patients data MUST include the `age` key (even when null) so the frontend fallback renders.'
        );
        $this->assertNull(
            $matching['age'],
            'GET /api/patients data[i].age MUST be JSON null when birth_date is null.'
        );
    }

    public function test_index_preserves_pagination_meta_envelope(): void
    {
        $admin = $this->admin();
        // Seed more than per_page so pagination is exercised
        for ($i = 0; $i < 20; $i++) {
            $this->seedAdultPatient('Bulk' . $i);
        }

        $response = $this->actingAs($admin, 'sanctum')
            ->getJson('/api/patients');

        $response->assertOk();

        $this->assertArrayHasKey('meta', $response->json());
        $this->assertArrayHasKey('current_page', $response->json('meta'));
        $this->assertArrayHasKey('last_page', $response->json('meta'));
        $this->assertArrayHasKey('per_page', $response->json('meta'));
        $this->assertArrayHasKey('total', $response->json('meta'));
        $this->assertArrayHasKey('active_count', $response->json('meta'));
        $this->assertArrayHasKey('inactive_count', $response->json('meta'));
        $this->assertSame(20, $response->json('meta.total'));
    }

    // -------------------------------------------------------------------
    // GET /api/patients/{id} — detail endpoint must include age
    // -------------------------------------------------------------------

    public function test_show_returns_age_integer_for_seeded_patient(): void
    {
        $admin = $this->admin();
        $patient = $this->seedAdultPatient('Maria');

        $response = $this->actingAs($admin, 'sanctum')
            ->getJson('/api/patients/' . $patient->id);

        $response->assertOk();

        $data = $response->json('data');
        $this->assertIsArray($data);
        $this->assertArrayHasKey(
            'age',
            $data,
            'GET /api/patients/{id} data MUST include the `age` key (verify #337 CRITICAL).'
        );
        $this->assertIsInt($data['age']);
        $this->assertSame(36, $data['age']);
    }

    public function test_show_returns_age_null_for_patient_with_null_birth_date(): void
    {
        $admin = $this->admin();
        $patient = $this->seedPatientWithoutBirthDate();

        $response = $this->actingAs($admin, 'sanctum')
            ->getJson('/api/patients/' . $patient->id);

        $response->assertOk();
        $this->assertArrayHasKey('age', $response->json('data'));
        $this->assertNull($response->json('data.age'));
    }

    // -------------------------------------------------------------------
    // POST /api/patients — create endpoint must include computed age in 201
    // -------------------------------------------------------------------

    public function test_store_returns_age_integer_in_201_envelope(): void
    {
        $admin = $this->admin();

        $response = $this->actingAs($admin, 'sanctum')
            ->postJson('/api/patients', [
                'first_name' => 'Nuevo',
                'last_name' => 'Paciente',
                'email' => 'nuevo.paciente@example.com',
                'phone' => '+51 987 333 444',
                'birth_date' => '1990-04-15',
                'gender' => 'female',
                'is_active' => true,
            ]);

        $response->assertStatus(201);

        $data = $response->json('data');
        $this->assertIsArray($data);
        $this->assertArrayHasKey(
            'age',
            $data,
            'POST /api/patients 201 data MUST include the `age` key (verify #337 CRITICAL).'
        );
        $this->assertIsInt($data['age']);
        $this->assertSame(36, $data['age']);
    }

    // -------------------------------------------------------------------
    // PUT /api/patients/{id} — update endpoint must include computed age in 200
    // -------------------------------------------------------------------

    public function test_update_returns_age_integer_in_200_envelope(): void
    {
        $admin = $this->admin();
        $patient = $this->seedAdultPatient('Antes');

        $response = $this->actingAs($admin, 'sanctum')
            ->putJson('/api/patients/' . $patient->id, [
                'first_name' => 'Despues',
                'last_name' => 'Paciente',
                'email' => 'despues.paciente@example.com',
                'phone' => '+51 987 555 666',
                'birth_date' => '1990-04-15',
                'gender' => 'male',
                'is_active' => true,
            ]);

        $response->assertOk();

        $data = $response->json('data');
        $this->assertIsArray($data);
        $this->assertArrayHasKey(
            'age',
            $data,
            'PUT /api/patients/{id} 200 data MUST include the `age` key (verify #337 CRITICAL).'
        );
        $this->assertIsInt($data['age']);
        $this->assertSame(36, $data['age']);
    }

    // -------------------------------------------------------------------
    // Auth envelope: unauthenticated request is rejected (existing permission preserved)
    // -------------------------------------------------------------------

    public function test_index_rejects_unauthenticated_with_401(): void
    {
        $previousDebug = (bool) config('app.debug');
        config(['app.debug' => false]);

        try {
            $response = $this->getJson('/api/patients');

            $response->assertStatus(401)
                ->assertJsonStructure(['message'])
                ->assertHeader('WWW-Authenticate', 'Bearer realm="api"');

            $this->assertSame('No autenticado.', $response->json('message'));
        } finally {
            config(['app.debug' => $previousDebug]);
        }
    }
}
