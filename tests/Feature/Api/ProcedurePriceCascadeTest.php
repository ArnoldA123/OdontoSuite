<?php

namespace Tests\Feature\Api;

use App\Models\Branch;
use App\Models\Payer;
use App\Models\ProcedureCatalog;
use App\Models\ProcedurePrice;
use App\Models\User;
use App\Services\ProcedurePriceResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * #293 S3/S5 — one tariff table, resolved by specificity and validity.
 *
 * A row is a price agreed for a procedure with a sede, a convenio, both, or
 * neither. Resolution picks the most specific row in force on the reference
 * date: convenio+sede, convenio only, sede only, then the catalog list price.
 * The result names its source and the row that applied, and a page resolves in
 * one query so a picker never degrades into N lookups.
 *
 * Must run against the MySQL harness (AGENTS.md §6).
 */
class ProcedurePriceCascadeTest extends TestCase
{
    use RefreshDatabase;

    private function user(?Branch $branch, string $role = 'odontologo'): User
    {
        return User::factory()->create([
            'role' => $role,
            'is_active' => true,
            'branch_id' => $branch?->id,
        ]);
    }

    private function payer(string $code = 'rimac'): Payer
    {
        return Payer::create([
            'code' => $code,
            'name' => 'Convenio '.strtoupper($code),
            'is_active' => true,
        ]);
    }

    private function procedure(float $cost = 100.0): ProcedureCatalog
    {
        return ProcedureCatalog::factory()->create([
            'code' => 'PROC-'.uniqid(),
            'name' => 'Limpieza '.uniqid(),
            'default_cost' => $cost,
            'is_active' => true,
        ]);
    }

    /**
     * @param array<string, mixed> $dimensions
     */
    private function priceRow(ProcedureCatalog $procedure, float $price, array $dimensions = []): ProcedurePrice
    {
        return ProcedurePrice::create(array_merge([
            'procedure_catalog_id' => $procedure->id,
            'price' => $price,
        ], $dimensions));
    }

    public function test_the_convenio_plus_sede_row_beats_every_other_dimension(): void
    {
        $branch = Branch::factory()->create();
        $payer = $this->payer();
        $procedure = $this->procedure(100.0);

        $this->priceRow($procedure, 90.0, ['branch_id' => $branch->id]);
        $this->priceRow($procedure, 70.0, ['payer_id' => $payer->id]);
        $this->priceRow($procedure, 60.0, ['payer_id' => $payer->id, 'branch_id' => $branch->id]);

        $resolved = app(ProcedurePriceResolver::class)
            ->resolve($procedure, $branch->id, null, $payer->id);

        $this->assertSame(60.0, $resolved['price']);
        $this->assertSame('payer_branch', $resolved['source']);
        $this->assertNotNull($resolved['price_id']);
    }

    public function test_a_convenio_only_row_applies_in_every_sede(): void
    {
        $payer = $this->payer();
        $procedure = $this->procedure(100.0);
        $this->priceRow($procedure, 70.0, ['payer_id' => $payer->id]);

        foreach ([Branch::factory()->create(), Branch::factory()->create()] as $branch) {
            $resolved = app(ProcedurePriceResolver::class)
                ->resolve($procedure, $branch->id, null, $payer->id);

            $this->assertSame(70.0, $resolved['price']);
            $this->assertSame('payer', $resolved['source']);
        }
    }

    public function test_the_sede_row_applies_when_the_convenio_has_no_row(): void
    {
        $branch = Branch::factory()->create();
        $payer = $this->payer();
        $procedure = $this->procedure(100.0);
        $this->priceRow($procedure, 65.0, ['branch_id' => $branch->id]);

        $resolved = app(ProcedurePriceResolver::class)
            ->resolve($procedure, $branch->id, null, $payer->id);

        $this->assertSame(65.0, $resolved['price']);
        $this->assertSame('branch', $resolved['source']);
    }

    public function test_the_list_price_is_the_last_fallback(): void
    {
        $branch = Branch::factory()->create();
        $payer = $this->payer();
        $procedure = $this->procedure(100.0);

        $resolved = app(ProcedurePriceResolver::class)
            ->resolve($procedure, $branch->id, null, $payer->id);

        $this->assertSame(100.0, $resolved['price']);
        $this->assertSame('catalog', $resolved['source']);
        $this->assertNull($resolved['price_id']);
        $this->assertNull($resolved['valid_from']);
        $this->assertNull($resolved['valid_to']);
    }

    public function test_a_convenio_row_does_not_leak_to_another_convenio(): void
    {
        $branch = Branch::factory()->create();
        $agreed = $this->payer('rimac');
        $other = $this->payer('pacifico');
        $procedure = $this->procedure(100.0);

        $this->priceRow($procedure, 80.0, ['branch_id' => $branch->id]);
        $this->priceRow($procedure, 40.0, ['payer_id' => $agreed->id]);

        $resolved = app(ProcedurePriceResolver::class)
            ->resolve($procedure, $branch->id, null, $other->id);

        $this->assertSame(80.0, $resolved['price']);
        $this->assertSame('branch', $resolved['source']);
    }

    public function test_only_the_row_in_force_on_the_reference_date_applies(): void
    {
        $branch = Branch::factory()->create();
        $procedure = $this->procedure(100.0);

        $this->priceRow($procedure, 60.0, [
            'branch_id' => $branch->id,
            'valid_from' => now()->subMonths(3)->toDateString(),
            'valid_to' => now()->subMonths(2)->toDateString(),
        ]);
        $this->priceRow($procedure, 40.0, [
            'branch_id' => $branch->id,
            'valid_from' => now()->addMonth()->toDateString(),
        ]);
        $this->priceRow($procedure, 80.0, [
            'branch_id' => $branch->id,
            'valid_from' => now()->subDay()->toDateString(),
        ]);

        $resolver = app(ProcedurePriceResolver::class);

        $this->assertSame(80.0, $resolver->resolve($procedure, $branch->id)['price']);
        $this->assertSame(
            60.0,
            $resolver->resolve($procedure, $branch->id, Carbon::now()->subMonths(3)->addMonth())['price'],
        );
        $this->assertSame(
            40.0,
            $resolver->resolve($procedure, $branch->id, Carbon::now()->addMonths(2))['price'],
        );
    }

    public function test_the_latest_start_wins_within_a_specificity_and_an_open_start_sorts_last(): void
    {
        $branch = Branch::factory()->create();
        $payer = $this->payer();
        $procedure = $this->procedure(100.0);

        $this->priceRow($procedure, 50.0, ['payer_id' => $payer->id]);
        $this->priceRow($procedure, 70.0, [
            'payer_id' => $payer->id,
            'valid_from' => now()->subDays(10)->toDateString(),
        ]);

        $resolver = app(ProcedurePriceResolver::class);
        $this->assertSame(70.0, $resolver->resolve($procedure, $branch->id, null, $payer->id)['price']);

        $latest = $this->priceRow($procedure, 75.0, [
            'payer_id' => $payer->id,
            'valid_from' => now()->subDays(2)->toDateString(),
        ]);

        $resolved = $resolver->resolve($procedure, $branch->id, null, $payer->id);

        $this->assertSame(75.0, $resolved['price']);
        $this->assertSame($latest->id, $resolved['price_id']);
    }

    public function test_a_page_of_prices_resolves_in_one_query(): void
    {
        $branch = Branch::factory()->create();
        $payer = $this->payer();

        $page = collect(range(1, 5))->map(function () use ($branch, $payer) {
            $procedure = $this->procedure();
            $this->priceRow($procedure, 90.0, ['branch_id' => $branch->id]);
            $this->priceRow($procedure, 70.0, ['payer_id' => $payer->id, 'branch_id' => $branch->id]);

            return $procedure;
        });

        DB::flushQueryLog();
        DB::enableQueryLog();

        $resolved = app(ProcedurePriceResolver::class)
            ->resolveMany($page, $branch->id, null, $payer->id);

        $priceQueries = collect(DB::getQueryLog())
            ->pluck('query')
            ->filter(fn (string $query): bool => str_contains($query, 'procedure_prices')
                && str_starts_with(ltrim($query), 'select'));

        DB::disableQueryLog();

        $this->assertCount(1, $priceQueries);
        $this->assertCount(5, $resolved);

        foreach ($page as $procedure) {
            $this->assertSame(70.0, $resolved[$procedure->id]['price']);
            $this->assertSame('payer_branch', $resolved[$procedure->id]['source']);
        }
    }

    public function test_resolving_without_a_sede_or_a_convenio_answers_the_catalog_without_querying(): void
    {
        $page = collect(range(1, 3))->map(fn () => $this->procedure(120.0));

        DB::flushQueryLog();
        DB::enableQueryLog();

        $resolved = app(ProcedurePriceResolver::class)->resolveMany($page, null, null, null);

        $priceQueries = collect(DB::getQueryLog())
            ->pluck('query')
            ->filter(fn (string $query): bool => str_contains($query, 'procedure_prices'));

        DB::disableQueryLog();

        $this->assertCount(0, $priceQueries);

        foreach ($page as $procedure) {
            $this->assertSame(120.0, $resolved[$procedure->id]['price']);
            $this->assertSame('catalog', $resolved[$procedure->id]['source']);
        }
    }

    public function test_the_convenio_door_writes_group_rows_and_the_branch_door_writes_payer_less_rows(): void
    {
        $actor = $this->user(null, 'administrador');
        $payer = $this->payer();
        $branch = Branch::factory()->create();
        $groupProcedure = $this->procedure(100.0);
        $branchProcedure = $this->procedure(100.0);

        $this->actingAs($actor, 'sanctum')
            ->putJson("/api/payers/{$payer->id}/procedure-prices", [
                'items' => [
                    ['procedure_catalog_id' => $groupProcedure->id, 'price' => 70.0],
                    [
                        'procedure_catalog_id' => $branchProcedure->id,
                        'price' => 75.0,
                        'branch_id' => $branch->id,
                    ],
                ],
            ])
            ->assertOk();

        $this->assertDatabaseHas('procedure_prices', [
            'procedure_catalog_id' => $groupProcedure->id,
            'payer_id' => $payer->id,
            'branch_id' => null,
            'price' => 70.0,
        ]);
        $this->assertDatabaseHas('procedure_prices', [
            'procedure_catalog_id' => $branchProcedure->id,
            'payer_id' => $payer->id,
            'branch_id' => $branch->id,
            'price' => 75.0,
        ]);

        $list = $this->actingAs($actor, 'sanctum')
            ->getJson("/api/payers/{$payer->id}/procedure-prices")
            ->assertOk()
            ->json();

        $this->assertSame($payer->id, $list['data'][0]['payer_id']);
        $this->assertArrayHasKey('branch_name', $list['data'][0]);

        $this->actingAs($actor, 'sanctum')
            ->putJson("/api/branches/{$branch->id}/procedure-prices", [
                'items' => [['procedure_catalog_id' => $groupProcedure->id, 'price' => 65.0]],
            ])
            ->assertOk();

        $this->assertDatabaseHas('procedure_prices', [
            'procedure_catalog_id' => $groupProcedure->id,
            'branch_id' => $branch->id,
            'payer_id' => null,
            'price' => 65.0,
        ]);

        $branchList = $this->actingAs($actor, 'sanctum')
            ->getJson("/api/branches/{$branch->id}/procedure-prices")
            ->assertOk()
            ->json('data.0');

        $this->assertNull($branchList['payer_id']);
    }

    public function test_the_convenio_door_requires_group_scope(): void
    {
        $own = Branch::factory()->create();
        $actor = $this->user($own, 'administrador');
        $payer = $this->payer();
        $procedure = $this->procedure(100.0);

        $this->actingAs($actor, 'sanctum')
            ->putJson("/api/payers/{$payer->id}/procedure-prices", [
                'items' => [['procedure_catalog_id' => $procedure->id, 'price' => 50.0]],
            ])
            ->assertForbidden();

        $this->actingAs($actor, 'sanctum')
            ->getJson("/api/payers/{$payer->id}/procedure-prices")
            ->assertForbidden();

        $this->assertDatabaseCount('procedure_prices', 0);
    }

    public function test_finance_and_clinical_roles_cannot_manage_either_door(): void
    {
        $branch = Branch::factory()->create();
        $payer = $this->payer();
        $procedure = $this->procedure(100.0);
        $payload = ['items' => [['procedure_catalog_id' => $procedure->id, 'price' => 10.0]]];

        foreach (['finanzas', 'odontologo'] as $role) {
            $actor = $this->user($branch, $role);

            $this->actingAs($actor, 'sanctum')
                ->putJson("/api/branches/{$branch->id}/procedure-prices", $payload)
                ->assertForbidden();

            $this->actingAs($actor, 'sanctum')
                ->putJson("/api/payers/{$payer->id}/procedure-prices", $payload)
                ->assertForbidden();
        }

        $this->assertDatabaseCount('procedure_prices', 0);
    }

    public function test_the_convenio_door_rejects_an_inverted_window(): void
    {
        $actor = $this->user(null, 'administrador');
        $payer = $this->payer();
        $procedure = $this->procedure(100.0);

        $this->actingAs($actor, 'sanctum')
            ->putJson("/api/payers/{$payer->id}/procedure-prices", [
                'items' => [[
                    'procedure_catalog_id' => $procedure->id,
                    'price' => 50.0,
                    'valid_from' => now()->toDateString(),
                    'valid_to' => now()->subDay()->toDateString(),
                ]],
            ])
            ->assertUnprocessable()
            ->assertJsonPath('errors.items', ['La vigencia final no puede ser anterior al inicio.']);

        $this->assertDatabaseCount('procedure_prices', 0);
    }

    public function test_the_convenio_door_only_deletes_its_own_rows(): void
    {
        $actor = $this->user(null, 'administrador');
        $payer = $this->payer();
        $other = $this->payer('pacifico');
        $procedure = $this->procedure(100.0);

        $own = $this->priceRow($procedure, 70.0, ['payer_id' => $payer->id]);
        $foreign = $this->priceRow($procedure, 80.0, ['payer_id' => $other->id]);

        $this->actingAs($actor, 'sanctum')
            ->deleteJson("/api/payers/{$payer->id}/procedure-prices/{$foreign->id}")
            ->assertNotFound();

        $this->actingAs($actor, 'sanctum')
            ->deleteJson("/api/payers/{$payer->id}/procedure-prices/{$own->id}")
            ->assertOk();

        $this->assertDatabaseMissing('procedure_prices', ['id' => $own->id]);
        $this->assertDatabaseHas('procedure_prices', ['id' => $foreign->id]);
    }
}
