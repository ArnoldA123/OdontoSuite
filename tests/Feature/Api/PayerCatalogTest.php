<?php

namespace Tests\Feature\Api;

use App\Models\Branch;
use App\Models\Payer;
use App\Models\ProcedureCatalog;
use App\Models\ProcedurePrice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * #293 S3 — the convenio catalog.
 *
 * `payers` names the agreements a procedure can be priced against. Quotation
 * roles read it (the estimate chooses a convenio), only the administrator
 * creates or edits it, and the index publishes how many tariff rows each
 * convenio carries so the admin surface never shows a fabricated zero.
 *
 * Must run against the MySQL harness (AGENTS.md §6).
 */
class PayerCatalogTest extends TestCase
{
    use RefreshDatabase;

    private function user(?Branch $branch, string $role = 'administrador'): User
    {
        return User::factory()->create([
            'role' => $role,
            'is_active' => true,
            'branch_id' => $branch?->id,
        ]);
    }

    private function payer(string $code, string $name, bool $active = true): Payer
    {
        return Payer::create([
            'code' => $code,
            'name' => $name,
            'is_active' => $active,
        ]);
    }

    public function test_active_lists_only_active_convenios_for_a_quotation_role(): void
    {
        $this->payer('rimac', 'Rímac EPS');
        $this->payer('pacifico', 'Pacífico Seguros', false);

        $response = $this->actingAs($this->user(null, 'finanzas'), 'sanctum')
            ->getJson('/api/payers/active')
            ->assertOk();

        $codes = collect($response->json('data'))->pluck('code')->all();

        $this->assertSame(['rimac'], $codes);
        $this->assertSame(
            ['id', 'code', 'name'],
            array_keys($response->json('data.0')),
        );
    }

    public function test_an_administrator_creates_a_convenio_with_a_slug_code(): void
    {
        $response = $this->actingAs($this->user(null, 'administrador'), 'sanctum')
            ->postJson('/api/payers', [
                'code' => 'rimac_eps',
                'name' => 'Rímac EPS',
            ]);

        $response->assertCreated();
        $response->assertJsonPath('data.code', 'rimac_eps');
        $response->assertJsonPath('data.is_active', true);

        $this->assertDatabaseHas('payers', [
            'code' => 'rimac_eps',
            'name' => 'Rímac EPS',
            'is_active' => true,
        ]);
    }

    public function test_an_administrator_updates_a_convenio(): void
    {
        $payer = $this->payer('rimac', 'Rímac EPS');

        $this->actingAs($this->user(null, 'administrador'), 'sanctum')
            ->putJson("/api/payers/{$payer->id}", [
                'code' => 'rimac',
                'name' => 'Rímac Seguros',
                'is_active' => false,
            ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Rímac Seguros')
            ->assertJsonPath('data.is_active', false);
    }

    public function test_a_duplicate_code_is_rejected(): void
    {
        $this->payer('rimac', 'Rímac EPS');

        $this->actingAs($this->user(null, 'administrador'), 'sanctum')
            ->postJson('/api/payers', ['code' => 'rimac', 'name' => 'Otro convenio'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('code');
    }

    public function test_an_invalid_code_is_rejected(): void
    {
        $this->actingAs($this->user(null, 'administrador'), 'sanctum')
            ->postJson('/api/payers', ['code' => 'Rímac EPS!', 'name' => 'Rímac EPS'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('code');
    }

    public function test_a_non_administrator_cannot_create_a_convenio(): void
    {
        $this->actingAs($this->user(null, 'finanzas'), 'sanctum')
            ->postJson('/api/payers', ['code' => 'rimac', 'name' => 'Rímac EPS'])
            ->assertForbidden();

        $this->assertDatabaseCount('payers', 0);
    }

    public function test_the_index_counts_the_tariff_rows_of_each_convenio(): void
    {
        $procedure = ProcedureCatalog::factory()->create();
        $withPrice = $this->payer('rimac', 'Rímac EPS');
        $withoutPrice = $this->payer('pacifico', 'Pacífico Seguros');

        ProcedurePrice::create([
            'procedure_catalog_id' => $procedure->id,
            'payer_id' => $withPrice->id,
            'price' => 70.0,
        ]);

        $response = $this->actingAs($this->user(null, 'administrador'), 'sanctum')
            ->getJson('/api/payers')
            ->assertOk();

        $payers = collect($response->json('data'))->keyBy('code');

        $this->assertSame(['pacifico', 'rimac'], collect($response->json('data'))->pluck('code')->all());
        $this->assertSame(0, $payers['pacifico']['prices_count']);
        $this->assertSame(1, $payers['rimac']['prices_count']);
        $this->assertSame($withoutPrice->id, $payers['pacifico']['id']);
    }
}
