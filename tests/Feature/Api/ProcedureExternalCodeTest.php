<?php

namespace Tests\Feature\Api;

use App\Models\ProcedureCatalog;
use App\Models\ProcedureExternalCode;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * #293 S7 — the catalog maps to the public nomenclature.
 *
 * A procedure carries its codes in the health-sector catalog (RM 550-2023/MINSA)
 * and in the SIS tariff, so an equivalence table exists instead of a free-text
 * note. The listing can be searched from the public code, which is the case that
 * matters when a clinic receives an external referral.
 *
 * Must run against the MySQL harness (AGENTS.md §6).
 */
class ProcedureExternalCodeTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'role' => 'administrador',
            'is_active' => true,
        ]);

        $this->actingAs($this->admin, 'sanctum');
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'code' => 'OBT-'.strtoupper(substr(md5((string) microtime(true)), 0, 6)),
            'name' => 'Obturación composite',
            'default_cost' => 120.0,
        ], $overrides);
    }

    public function test_a_procedure_carries_its_public_nomenclature_codes(): void
    {
        $created = $this->postJson('/api/procedure-catalog', $this->payload([
            'code' => 'MAP-TOOTH',
            'external_codes' => [
                ['system' => 'minsa_rm550_2023', 'code' => 'MINSA-997201', 'description' => 'Obturación con composite'],
                ['system' => 'sis', 'code' => 'SIS-2121'],
            ],
        ]))->assertCreated()->json('data');

        $this->assertCount(2, $created['external_codes']);
        $this->assertDatabaseHas('procedure_external_codes', [
            'procedure_catalog_id' => $created['id'],
            'system' => 'minsa_rm550_2023',
            'code' => 'MINSA-997201',
        ]);
        $this->assertDatabaseHas('procedure_external_codes', [
            'procedure_catalog_id' => $created['id'],
            'system' => 'sis',
            'code' => 'SIS-2121',
        ]);

        $detail = $this->getJson('/api/procedure-catalog/'.$created['id'])
            ->assertOk()
            ->json('data');

        $this->assertEqualsCanonicalizing(
            ['minsa_rm550_2023', 'sis'],
            array_column($detail['external_codes'], 'system')
        );
    }

    public function test_updating_the_procedure_replaces_the_equivalence_table(): void
    {
        $procedure = ProcedureCatalog::create([
            'code' => 'MAP-REPLACE', 'name' => 'Procedimiento', 'default_cost' => 100,
            'is_active' => true,
        ]);

        ProcedureExternalCode::create([
            'procedure_catalog_id' => $procedure->id,
            'system' => 'sis',
            'code' => 'SIS-OLD',
        ]);

        $this->putJson('/api/procedure-catalog/'.$procedure->id, [
            'external_codes' => [['system' => 'minsa_rm550_2023', 'code' => 'MINSA-NEW']],
        ])->assertOk();

        $this->assertDatabaseMissing('procedure_external_codes', ['code' => 'SIS-OLD']);
        $this->assertDatabaseHas('procedure_external_codes', ['code' => 'MINSA-NEW']);
        $this->assertSame(1, $procedure->externalCodes()->count());
    }

    public function test_an_equivalence_names_its_system_and_its_code(): void
    {
        $response = $this->postJson('/api/procedure-catalog', $this->payload([
            'code' => 'MAP-INVALID',
            'external_codes' => [['description' => 'sin sistema ni código']],
        ]))->assertStatus(422);

        $errors = $response->json('errors');
        $this->assertArrayHasKey('external_codes.0.system', $errors);
        $this->assertArrayHasKey('external_codes.0.code', $errors);
        $this->assertDatabaseMissing('procedure_catalog', ['code' => 'MAP-INVALID']);
    }

    public function test_the_same_public_code_cannot_be_declared_twice_for_one_procedure(): void
    {
        $this->postJson('/api/procedure-catalog', $this->payload([
            'code' => 'MAP-DUP',
            'external_codes' => [
                ['system' => 'sis', 'code' => 'SIS-1'],
                ['system' => 'sis', 'code' => 'SIS-1'],
            ],
        ]))->assertStatus(422)->assertJsonValidationErrors(['external_codes.1.code']);

        $this->assertDatabaseMissing('procedure_catalog', ['code' => 'MAP-DUP']);
    }

    public function test_the_listing_finds_a_procedure_by_its_public_code(): void
    {
        $procedure = ProcedureCatalog::create([
            'code' => 'MAP-FIND', 'name' => 'Obturación', 'default_cost' => 100,
            'is_active' => true,
        ]);
        ProcedureExternalCode::create([
            'procedure_catalog_id' => $procedure->id,
            'system' => 'sis',
            'code' => 'SIS-7733',
        ]);

        $codes = collect($this->getJson('/api/procedure-catalog?external_system=sis&external_code=SIS-7733')
            ->assertOk()
            ->json('data'))->pluck('code')->all();

        $this->assertSame(['MAP-FIND'], $codes);
    }

    public function test_the_listing_searches_by_the_public_code_without_the_system(): void
    {
        $procedure = ProcedureCatalog::create([
            'code' => 'MAP-BARE', 'name' => 'Obturación', 'default_cost' => 100,
            'is_active' => true,
        ]);
        ProcedureExternalCode::create([
            'procedure_catalog_id' => $procedure->id,
            'system' => 'minsa_rm550_2023',
            'code' => 'MINSA-5544',
        ]);

        $codes = collect($this->getJson('/api/procedure-catalog?external_code=MINSA-5544')
            ->assertOk()
            ->json('data'))->pluck('code')->all();

        $this->assertSame(['MAP-BARE'], $codes);
    }
}
