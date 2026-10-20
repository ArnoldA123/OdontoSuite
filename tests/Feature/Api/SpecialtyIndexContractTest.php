<?php

namespace Tests\Feature\Api;

use App\Models\ProcedureCatalog;
use App\Models\Specialty;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Contract for the specialties master catalog consumed by the admin
 * interface (/specialties): server-side search and status filters, the usage
 * counts rendered per row, and the KPI population that must not shrink when
 * the list is filtered.
 */
class SpecialtyIndexContractTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create([
            'role' => 'administrador',
            'is_active' => true,
        ]);
    }

    /**
     * @param array<string, mixed> $attributes
     */
    private function specialty(array $attributes = []): Specialty
    {
        return Specialty::create(array_merge([
            'code' => 'general',
            'name' => 'Odontología general',
            'description' => 'Atención integral, preventiva y diagnóstico general.',
            'is_active' => true,
        ], $attributes));
    }

    private function professional(Specialty $specialty, bool $primary = true): User
    {
        $user = User::factory()->create([
            'role' => 'odontologo',
            'is_active' => true,
        ]);

        $specialty->users()->attach($user->id, ['is_primary' => $primary]);

        return $user;
    }

    public function test_index_exposes_the_usage_counts_each_row_renders(): void
    {
        $specialty = $this->specialty(['code' => 'endodoncia', 'name' => 'Endodoncia']);
        ProcedureCatalog::factory()->count(2)->create(['specialty_id' => $specialty->id]);
        ProcedureCatalog::factory()->create(['specialty_id' => null]);
        $this->professional($specialty);
        $this->professional($specialty, primary: false);

        $response = $this->actingAs($this->admin(), 'sanctum')
            ->getJson('/api/specialties');

        $response->assertOk();
        $row = collect($response->json('data'))->firstWhere('code', 'endodoncia');

        $this->assertNotNull($row);
        $this->assertSame(2, $row['procedures_count']);
        $this->assertSame(2, $row['professionals_count']);
    }

    public function test_status_filter_does_not_rewrite_the_kpi_population(): void
    {
        $active = $this->specialty(['code' => 'general', 'name' => 'Odontología general']);
        $this->specialty([
            'code' => 'ortodoncia',
            'name' => 'Ortodoncia',
            'is_active' => false,
        ]);

        ProcedureCatalog::factory()->count(3)->create(['specialty_id' => $active->id]);
        ProcedureCatalog::factory()->create(['specialty_id' => null]);

        $response = $this->actingAs($this->admin(), 'sanctum')
            ->getJson('/api/specialties?is_active=true');

        $response->assertOk();
        $this->assertSame(['general'], collect($response->json('data'))->pluck('code')->all());
        $response->assertJsonPath('meta.total', 1);
        $response->assertJsonPath('meta.kpis.total', 2);
        $response->assertJsonPath('meta.kpis.active', 1);
        $response->assertJsonPath('meta.kpis.inactive', 1);
        $response->assertJsonPath('meta.kpis.procedures', 3);

        $inactiveResponse = $this->actingAs($this->admin(), 'sanctum')
            ->getJson('/api/specialties?is_active=false');

        $inactiveResponse->assertOk();
        $this->assertSame(
            ['ortodoncia'],
            collect($inactiveResponse->json('data'))->pluck('code')->all()
        );
    }

    public function test_search_covers_name_code_and_description(): void
    {
        $this->specialty([
            'code' => 'ortodoncia',
            'name' => 'Ortodoncia',
            'description' => 'Brackets, alineadores y retención.',
        ]);
        $this->specialty([
            'code' => 'endodoncia',
            'name' => 'Endodoncia',
            'description' => 'Tratamientos de conducto y retratamientos.',
        ]);

        $byDescription = $this->actingAs($this->admin(), 'sanctum')
            ->getJson('/api/specialties?q=alineadores');
        $byDescription->assertOk();
        $this->assertSame(
            ['ortodoncia'],
            collect($byDescription->json('data'))->pluck('code')->all()
        );

        $byCode = $this->actingAs($this->admin(), 'sanctum')
            ->getJson('/api/specialties?q=endodoncia');
        $byCode->assertOk();
        $this->assertSame(['endodoncia'], collect($byCode->json('data'))->pluck('code')->all());

        $noMatches = $this->actingAs($this->admin(), 'sanctum')
            ->getJson('/api/specialties?q=periodoncia');

        $noMatches->assertOk();
        $this->assertSame([], $noMatches->json('data'));
        $noMatches->assertJsonPath('meta.total', 0);
        $noMatches->assertJsonPath('meta.kpis.total', 2);
    }

    public function test_legacy_active_only_parameter_keeps_working(): void
    {
        $this->specialty(['code' => 'general', 'name' => 'Odontología general']);
        $this->specialty([
            'code' => 'cirugia_oral',
            'name' => 'Cirugía oral',
            'is_active' => false,
        ]);

        $response = $this->actingAs($this->admin(), 'sanctum')
            ->getJson('/api/specialties?active_only=1');

        $response->assertOk();
        $this->assertSame(['general'], collect($response->json('data'))->pluck('code')->all());
    }

    public function test_show_exposes_usage_counts_and_returns_404_for_unknown_ids(): void
    {
        $specialty = $this->specialty(['code' => 'periodoncia', 'name' => 'Periodoncia']);
        ProcedureCatalog::factory()->create(['specialty_id' => $specialty->id]);
        $this->professional($specialty);

        $response = $this->actingAs($this->admin(), 'sanctum')
            ->getJson("/api/specialties/{$specialty->id}");

        $response->assertOk();
        $response->assertJsonPath('data.procedures_count', 1);
        $response->assertJsonPath('data.professionals_count', 1);

        $this->actingAs($this->admin(), 'sanctum')
            ->getJson('/api/specialties/999999')
            ->assertNotFound();
    }

    public function test_write_contract_keeps_the_admin_permissions_and_validation(): void
    {
        $admin = $this->admin();

        $created = $this->actingAs($admin, 'sanctum')
            ->postJson('/api/specialties', [
                'code' => 'estetica',
                'name' => 'Estética dental',
                'description' => 'Blanqueamiento, carillas y diseño de sonrisa.',
            ]);

        $created->assertCreated();
        $created->assertJsonPath('data.code', 'estetica');

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/specialties', [
                'code' => 'estetica',
                'name' => 'Duplicada',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('code');

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/specialties', ['code' => 'sin_nombre'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('name');

        $receptionist = User::factory()->create([
            'role' => 'recepcionista',
            'is_active' => true,
        ]);

        $this->actingAs($receptionist, 'sanctum')
            ->postJson('/api/specialties', ['code' => 'nueva', 'name' => 'Nueva'])
            ->assertForbidden();

        $updated = $this->actingAs($admin, 'sanctum')
            ->putJson("/api/specialties/{$created->json('data.id')}", ['is_active' => false]);

        $updated->assertOk();
        $this->assertDatabaseHas('specialties', [
            'code' => 'estetica',
            'is_active' => false,
        ]);
    }
}
