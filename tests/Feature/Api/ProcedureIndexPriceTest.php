<?php

namespace Tests\Feature\Api;

use App\Models\Branch;
use App\Models\ProcedureCatalog;
use App\Models\ProcedurePrice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * #431 — el índice del catálogo resuelve el tarifario de la página.
 *
 * `search()` y `activeList()` ya respondían el precio de la sede desde #290/#293;
 * `paginate()` quedó fuera y publicaba la lista, así que el picker compartido
 * mostraba un importe distinto del que la API resuelve para el mismo actor.
 *
 * Must run against the MySQL harness (AGENTS.md §6).
 */
class ProcedureIndexPriceTest extends TestCase
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

    private function procedure(float $cost = 100.0): ProcedureCatalog
    {
        return ProcedureCatalog::factory()->create([
            'code' => 'PROC-'.uniqid(),
            'name' => 'Limpieza '.uniqid(),
            'default_cost' => $cost,
            'is_active' => true,
        ]);
    }

    private function priceRow(ProcedureCatalog $procedure, Branch $branch, float $price): ProcedurePrice
    {
        return ProcedurePrice::create([
            'procedure_catalog_id' => $procedure->id,
            'branch_id' => $branch->id,
            'payer_id' => null,
            'price' => $price,
            'valid_from' => now()->subWeek()->toDateString(),
            'valid_to' => now()->addWeek()->toDateString(),
        ]);
    }

    public function test_the_index_resolves_the_sede_price_and_names_its_source(): void
    {
        $branch = Branch::factory()->create();
        $procedure = $this->procedure(500.0);
        $this->priceRow($procedure, $branch, 375.0);

        $response = $this->actingAs($this->user($branch), 'sanctum')
            ->getJson('/api/procedure-catalog?q='.urlencode($procedure->name));

        $response->assertOk();
        $row = collect($response->json('data'))->firstWhere('id', $procedure->id);

        $this->assertNotNull($row);
        $this->assertSame(375.0, (float) $row['default_cost']);
        $this->assertSame(500.0, (float) $row['list_cost']);
        $this->assertSame('branch', $row['price_source']);
    }

    public function test_the_index_publishes_the_list_price_without_a_branch_row(): void
    {
        $branch = Branch::factory()->create();
        $procedure = $this->procedure(500.0);

        $response = $this->actingAs($this->user($branch), 'sanctum')
            ->getJson('/api/procedure-catalog?q='.urlencode($procedure->name));

        $response->assertOk();
        $row = collect($response->json('data'))->firstWhere('id', $procedure->id);

        $this->assertNotNull($row);
        $this->assertSame(500.0, (float) $row['default_cost']);
        $this->assertSame(500.0, (float) $row['list_cost']);
        $this->assertSame('catalog', $row['price_source']);
    }

    public function test_the_page_is_resolved_with_a_single_price_query(): void
    {
        $branch = Branch::factory()->create();
        $procedures = collect(range(1, 5))->map(fn () => $this->procedure(100.0));
        $procedures->each(fn (ProcedureCatalog $procedure) => $this->priceRow($procedure, $branch, 80.0));

        $priceQueries = 0;
        DB::listen(function ($query) use (&$priceQueries) {
            if (str_contains($query->sql, 'procedure_prices')) {
                $priceQueries++;
            }
        });

        $response = $this->actingAs($this->user($branch), 'sanctum')
            ->getJson('/api/procedure-catalog?per_page=10');

        $response->assertOk();
        $this->assertCount(5, $response->json('data'));
        $this->assertSame(1, $priceQueries);
    }

    /**
     * S2: `show()` queda fuera a propósito; su bloque «Costo por defecto» es la
     * lista y el tab Tarifario publica la fila de la sede.
     */
    public function test_show_keeps_publishing_the_list_price(): void
    {
        $branch = Branch::factory()->create();
        $procedure = $this->procedure(500.0);
        $this->priceRow($procedure, $branch, 375.0);

        $response = $this->actingAs($this->user($branch), 'sanctum')
            ->getJson('/api/procedure-catalog/'.$procedure->id);

        $response->assertOk();
        $row = $response->json('data');

        $this->assertSame(500.0, (float) $row['default_cost']);
        $this->assertArrayNotHasKey('price_source', $row);
    }
}
