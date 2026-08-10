<?php

namespace Tests\Feature\Modules;

use App\Models\ProcedureCatalog;
use App\Models\Specialty;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * full-user-browser-audit-2026-08-05 / PR5 / Phase 4a — Catalog Filter.
 *
 * Validation-first Feature test for the Catálogo de Procedimientos filter
 * contract documented in specs/module-validation-tests. The slice exercises
 * the EXISTING `GET /api/procedure-catalog` endpoint and its `specialty`
 * query parameter (the production code's actual contract — the spec uses
 * "category" as a generic label for the specialty filter; the controller
 * resolves it through `ProcedureCatalogService::paginate` which filters by
 * `specialty.code` via the `specialty_id` FK).
 *
 * Spec scenarios under test:
 *   - Filter by specialty narrows the list to only matching procedures
 *   - Empty filter returns the full paginated list
 *   - Filter by an unknown specialty returns 200 with empty data array
 *   - Unauthenticated request is rejected with 401
 *
 * Why Service + Resource + Controller chain (not a Unit test): the contract
 * the frontend consumes is the JSON envelope `{data:[...], meta:{...}}` and
 * the FK-based filter, which only surface through the controller path.
 *
 * Rollback boundary: delete this file. No production code is touched.
 *
 * Known runtime caveat: the local SQLite test runner hits the pre-existing
 * `transactions.type` DROP COLUMN tech debt (AGENTS.md §6). The bounded
 * runtime fallback is the dev MySQL harness (`DB_CONNECTION=mysql`). This
 * test must be run against MySQL to exercise the controller contract; the
 * SQLite path will fail at the RefreshDatabase bootstrap before any
 * assertion runs.
 */
class CatalogFilterTest extends TestCase
{
    use RefreshDatabase;

    private function odontologo(): User
    {
        // Direct create: User::factory() is blocked against MySQL because the
        // default factory does not emit `username` (MySQL users.username is
        // NOT NULL without default). Bypassing the factory is the safest
        // available path for Feature tests until the factory is fixed.
        return User::create([
            'name' => 'Test Odontologo',
            'email' => 'odontologo.catalog.test@example.com',
            'username' => 'odonto_catalog',
            'password' => bcrypt('password'),
            'role' => 'odontologo',
            'is_active' => true,
        ]);
    }

    private function makeSpecialty(string $code, string $name): Specialty
    {
        return Specialty::create([
            'code' => $code,
            'name' => $name,
            'description' => "Procedures for {$name}",
            'is_active' => true,
        ]);
    }

    private function makeProcedure(Specialty $specialty, string $code, string $name): ProcedureCatalog
    {
        return ProcedureCatalog::create([
            'code' => $code,
            'name' => $name,
            'description' => "{$name} description",
            'specialty_id' => $specialty->id,
            'default_cost' => 100.00,
            'default_duration_minutes' => 30,
            'is_active' => true,
        ]);
    }

    public function test_filter_by_specialty_returns_scoped_results(): void
    {
        $user = $this->odontologo();
        $restorative = $this->makeSpecialty('restorative', 'Restorative');
        $endodontic = $this->makeSpecialty('endodontic', 'Endodontic');

        $this->makeProcedure($restorative, 'REST-001', 'Composite filling');
        $this->makeProcedure($restorative, 'REST-002', 'Amalgam filling');
        $this->makeProcedure($endodontic, 'ENDO-001', 'Root canal');

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/procedure-catalog?specialty=restorative');

        $response->assertOk()
            ->assertJsonStructure(['data' => [['id', 'code', 'name', 'specialty']], 'meta' => ['total', 'per_page', 'current_page', 'last_page']]);

        $rows = $response->json('data');
        $codes = collect($rows)->pluck('code')->all();
        $this->assertContains('REST-001', $codes);
        $this->assertContains('REST-002', $codes);
        $this->assertNotContains('ENDO-001', $codes, 'Filter by specialty=restorative MUST exclude endodontic procedures.');
    }

    public function test_empty_filter_returns_full_paginated_list(): void
    {
        $user = $this->odontologo();
        $restorative = $this->makeSpecialty('restorative', 'Restorative');
        $this->makeProcedure($restorative, 'REST-A', 'Procedure A');
        $this->makeProcedure($restorative, 'REST-B', 'Procedure B');
        $this->makeProcedure($restorative, 'REST-C', 'Procedure C');

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/procedure-catalog');

        $response->assertOk();
        $this->assertGreaterThanOrEqual(3, count($response->json('data')));
        $this->assertSame(3, $response->json('meta.total'));
    }

    public function test_filter_by_unknown_specialty_returns_empty_data_with_200(): void
    {
        $user = $this->odontologo();
        $restorative = $this->makeSpecialty('restorative', 'Restorative');
        $this->makeProcedure($restorative, 'REST-001', 'Composite filling');

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/procedure-catalog?specialty=nonexistent-specialty-code');

        $response->assertOk()
            ->assertJsonPath('data', [])
            ->assertJsonPath('meta.total', 0);
    }

    public function test_unauthenticated_request_is_rejected_with_401(): void
    {
        // Slice 07b tightens this assertion. Pre-PR7b the bootstrap Throwable
        // catch-all returned 500 against MySQL; the explicit
        // AuthenticationException renderer now returns the canonical 401
        // envelope. No procedure data may leak.
        $previousDebug = (bool) config('app.debug');
        config(['app.debug' => false]);

        try {
            $response = $this->getJson('/api/procedure-catalog');

            $response->assertStatus(401)
                ->assertJsonStructure(['message'])
                ->assertHeader('WWW-Authenticate', 'Bearer realm="api"');

            $this->assertSame('No autenticado.', $response->json('message'));
            $this->assertNull(
                $response->json('data'),
                'No procedure data may leak in the unauthenticated response envelope.'
            );
        } finally {
            config(['app.debug' => $previousDebug]);
        }
    }
}
