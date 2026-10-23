<?php

namespace Tests\Feature\Api;

use App\Models\ProcedureCatalog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

/**
 * #293 S1 — the catalog says what surface a procedure is performed on.
 *
 * Five values, `none` by default so the existing catalog keeps its contract:
 * a procedure either applies to a tooth, a tooth surface, a quadrant, an arch,
 * or to the patient as a whole. The declaration is what later lets a plan or an
 * estimate demand the site.
 *
 * Must run against the MySQL harness (AGENTS.md §6).
 */
class ProcedureCatalogApplicabilityTest extends TestCase
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
            'code' => 'END-'.strtoupper(substr(md5((string) microtime(true)), 0, 6)),
            'name' => 'Endodoncia unirradicular',
            'default_cost' => 180.0,
            'default_duration_minutes' => 45,
        ], $overrides);
    }

    public function test_a_procedure_declares_the_site_it_applies_to(): void
    {
        $created = $this->postJson('/api/procedure-catalog', $this->payload([
            'code' => 'OBT-COMP',
            'name' => 'Obturación composite',
            'site_type' => 'tooth',
        ]))->assertCreated()->json('data');

        $this->assertSame('tooth', $created['site_type']);
        $this->assertDatabaseHas('procedure_catalog', [
            'id' => $created['id'],
            'site_type' => 'tooth',
        ]);

        $detail = $this->getJson('/api/procedure-catalog/'.$created['id'])
            ->assertOk()
            ->json('data');

        $this->assertSame('tooth', $detail['site_type']);
    }

    public function test_a_procedure_without_a_declared_site_applies_to_the_patient(): void
    {
        $created = $this->postJson('/api/procedure-catalog', $this->payload([
            'code' => 'LIMP-DENTAL',
            'name' => 'Limpieza dental',
        ]))->assertCreated()->json('data');

        $this->assertSame('none', $created['site_type']);
        $this->assertSame(
            'none',
            ProcedureCatalog::findOrFail($created['id'])->site_type
        );
    }

    public function test_an_unknown_site_type_is_rejected(): void
    {
        $response = $this->postJson('/api/procedure-catalog', $this->payload([
            'code' => 'SITIO-INVALIDO',
            'site_type' => 'molar',
        ]))->assertStatus(422);

        $this->assertArrayHasKey('site_type', $response->json('errors'));
        $this->assertDatabaseMissing('procedure_catalog', ['code' => 'SITIO-INVALIDO']);
    }

    public function test_the_four_declared_sites_plus_the_whole_patient_are_accepted(): void
    {
        foreach (['none', 'tooth', 'surface', 'quadrant', 'arch'] as $index => $site) {
            $this->postJson('/api/procedure-catalog', $this->payload([
                'code' => 'SITE-'.$index,
                'site_type' => $site,
            ]))->assertCreated();

            $this->assertDatabaseHas('procedure_catalog', [
                'code' => 'SITE-'.$index,
                'site_type' => $site,
            ]);
        }
    }

    public function test_the_listing_filters_by_the_declared_site(): void
    {
        ProcedureCatalog::create([
            'code' => 'F-TOTH', 'name' => 'Obturación', 'site_type' => 'tooth',
            'default_cost' => 90, 'is_active' => true,
        ]);
        ProcedureCatalog::create([
            'code' => 'F-NONE', 'name' => 'Consulta', 'site_type' => 'none',
            'default_cost' => 50, 'is_active' => true,
        ]);

        $codes = collect($this->getJson('/api/procedure-catalog?site_type=tooth')
            ->assertOk()
            ->json('data'))->pluck('code')->all();

        $this->assertSame(['F-TOTH'], $codes);
    }

    public function test_updating_the_procedure_moves_its_site(): void
    {
        $procedure = ProcedureCatalog::create([
            'code' => 'UPD-SITE', 'name' => 'Procedimiento', 'site_type' => 'none',
            'default_cost' => 100, 'is_active' => true,
        ]);

        $this->putJson('/api/procedure-catalog/'.$procedure->id, ['site_type' => 'quadrant'])
            ->assertOk();

        $this->assertSame('quadrant', $procedure->fresh()->site_type);
    }

    public function test_the_csv_importer_reads_the_site_type_column(): void
    {
        $csv = implode("\n", [
            'code,name,default_cost,site_type',
            'CSV-TOOTH,Obturación en pieza,90,tooth',
            'CSV-NONE,Consulta general,50,',
        ]);

        $this->postJson('/api/admin/procedure-catalog/import', [
            'file' => UploadedFile::fake()->createWithContent('procedures.csv', $csv),
        ])->assertOk()->assertJsonPath('data.inserted', 2);

        $this->assertSame('tooth', ProcedureCatalog::where('code', 'CSV-TOOTH')->value('site_type'));
        $this->assertSame('none', ProcedureCatalog::where('code', 'CSV-NONE')->value('site_type'));
    }
}
