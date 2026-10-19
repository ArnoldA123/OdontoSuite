<?php

namespace Tests\Feature\Modules;

use App\Models\ProcedureCatalog;
use App\Models\Specialty;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Contract tests for the procedure-catalog summary and version-history
 * endpoints, plus the extended clinical fields the catalog form already
 * submits (materials_needed, requires_radiographs, post_procedure_care,
 * requirements).
 *
 * Runs against MySQL: `php artisan test --configuration=phpunit.mysql.xml`
 * over an already-migrated database (the CI backend job migrates first).
 */
class ProcedureCatalogSummaryVersionsTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        // MigrateFreshPortabilityTest seeds the catalog on its own connection;
        // keep this class's totals absolute.
        DB::table('procedure_catalog_versions')->delete();
        DB::table('procedure_catalog')->delete();
        DB::table('audit_logs')->delete();
    }

    private function admin(): User
    {
        return User::create([
            'name' => 'Catalog Admin',
            'email' => 'catalog.admin@example.com',
            'username' => 'catalog_admin',
            'password' => bcrypt('password'),
            'role' => 'administrador',
            'is_active' => true,
        ]);
    }

    private function makeSpecialty(string $code = 'endodoncia', string $name = 'Endodoncia'): Specialty
    {
        return Specialty::create([
            'code' => $code,
            'name' => $name,
            'description' => "Procedures for {$name}",
            'is_active' => true,
        ]);
    }

    private function makeProcedure(array $overrides = []): ProcedureCatalog
    {
        return ProcedureCatalog::create(array_merge([
            'code' => 'ENDO-001',
            'name' => 'Endodoncia unirradicular',
            'description' => 'Tratamiento de conducto',
            'default_cost' => 100.00,
            'default_duration_minutes' => 30,
            'is_active' => true,
        ], $overrides));
    }

    public function test_summary_reports_exact_catalog_totals_and_averages(): void
    {
        $admin = $this->admin();
        $specialty = $this->makeSpecialty();

        $this->makeProcedure([
            'code' => 'ENDO-A',
            'specialty_id' => $specialty->id,
            'default_cost' => 100.00,
            'default_duration_minutes' => 30,
        ]);
        $this->makeProcedure([
            'code' => 'ENDO-B',
            'specialty_id' => $specialty->id,
            'default_cost' => 200.00,
            'default_duration_minutes' => 60,
        ]);
        $this->makeProcedure([
            'code' => 'ENDO-C',
            'specialty_id' => $specialty->id,
            'default_cost' => 300.00,
            'default_duration_minutes' => 90,
            'is_active' => false,
        ]);

        $response = $this->actingAs($admin, 'sanctum')
            ->getJson('/api/procedure-catalog/summary');

        $response->assertOk()
            ->assertJsonPath('data.total', 3)
            ->assertJsonPath('data.active', 2)
            ->assertJsonPath('data.inactive', 1)
            ->assertJsonPath('data.by_specialty.0.specialty_name', 'Endodoncia')
            ->assertJsonPath('data.by_specialty.0.count', 3);

        $this->assertEquals(60.0, $response->json('data.avg_duration_minutes'));
        $this->assertEquals(200.0, $response->json('data.avg_cost'));
    }

    public function test_summary_requires_authentication(): void
    {
        $this->getJson('/api/procedure-catalog/summary')->assertStatus(401);
    }

    public function test_versions_returns_snapshots_for_the_procedure_only(): void
    {
        $admin = $this->admin();
        $procedure = $this->makeProcedure(['code' => 'ENDO-V1']);
        $other = $this->makeProcedure(['code' => 'ENDO-V2', 'name' => 'Otro procedimiento']);

        $this->actingAs($admin, 'sanctum')
            ->putJson("/api/procedure-catalog/{$procedure->id}", [
                'name' => 'Endodoncia actualizada',
                'default_cost' => 150.00,
            ])
            ->assertOk();

        $this->actingAs($admin, 'sanctum')
            ->putJson("/api/procedure-catalog/{$other->id}", [
                'name' => 'Otro actualizado',
            ])
            ->assertOk();

        $response = $this->actingAs($admin, 'sanctum')
            ->getJson("/api/procedure-catalog/{$procedure->id}/versions");

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [[
                    'id', 'change_type', 'changed_fields', 'default_cost',
                    'name', 'code', 'is_active', 'changed_at', 'changed_by',
                ]],
                'meta' => ['total'],
            ])
            // The creation snapshot plus the update snapshot of THIS procedure.
            ->assertJsonPath('meta.total', 2)
            ->assertJsonPath('data.0.change_type', 'updated')
            ->assertJsonPath('data.0.changed_by.name', 'Catalog Admin');

        $this->assertSame(
            'Endodoncia actualizada',
            $response->json('data.0.changed_fields.name'),
            'The version snapshot must belong to the requested procedure.'
        );
        $this->assertNotContains(
            'Otro actualizado',
            array_column($response->json('data'), 'name'),
            'Versions of a different procedure must not leak into the response.'
        );
    }

    public function test_versions_returns_404_for_unknown_procedure(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/procedure-catalog/99999/versions')
            ->assertStatus(404)
            ->assertJsonPath('message', 'Procedimiento no encontrado');
    }

    public function test_update_persists_extended_clinical_fields(): void
    {
        $admin = $this->admin();
        $procedure = $this->makeProcedure(['code' => 'ENDO-FIELDS']);

        $payload = [
            'materials_needed' => 'Lima K, irrigante, gutapercha',
            'requirements' => 'Radiografía periapical previa',
            'requires_radiographs' => true,
            'post_procedure_care' => 'Evitar masticar por 24 horas',
            'contraindications' => 'Infección aguda no controlada',
        ];

        $this->actingAs($admin, 'sanctum')
            ->putJson("/api/procedure-catalog/{$procedure->id}", $payload)
            ->assertOk();

        $this->actingAs($admin, 'sanctum')
            ->getJson("/api/procedure-catalog/{$procedure->id}")
            ->assertOk()
            ->assertJsonPath('data.materials_needed', $payload['materials_needed'])
            ->assertJsonPath('data.requires_radiographs', true)
            ->assertJsonPath('data.post_procedure_care', $payload['post_procedure_care'])
            ->assertJsonPath('data.contraindications', $payload['contraindications']);
    }

    public function test_update_can_reactivate_a_procedure(): void
    {
        $admin = $this->admin();
        $procedure = $this->makeProcedure(['code' => 'ENDO-OFF', 'is_active' => false]);

        $this->actingAs($admin, 'sanctum')
            ->putJson("/api/procedure-catalog/{$procedure->id}", ['is_active' => true])
            ->assertOk()
            ->assertJsonPath('data.is_active', true);

        $this->assertTrue($procedure->refresh()->is_active);
    }
}
