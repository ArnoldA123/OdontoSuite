<?php

namespace Tests\Feature\Api;

use App\Authorization\Permissions;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * #295 (S6, read half) — the warehouse as data, not as a screen.
 *
 * `products.stock_quantity` is the only truth of current stock and
 * `stock_movements` is the ledger, so these four endpoints publish the row the
 * UI will need, narrow it with the filters the operation asks for, and keep the
 * KPI strip measuring the whole population instead of the filtered page.
 */
class InventoryReadSurfaceTest extends TestCase
{
    use RefreshDatabase;

    private function userOf(string $role): User
    {
        return User::factory()->create([
            'role' => $role,
            'is_active' => true,
        ]);
    }

    /**
     * @param array<string, mixed> $attributes
     */
    private function product(array $attributes = []): Product
    {
        return Product::factory()->create($attributes);
    }

    /**
     * @param array<string, mixed> $attributes
     */
    private function movement(Product $product, User $author, array $attributes = []): StockMovement
    {
        return StockMovement::create(array_merge([
            'product_id' => $product->id,
            'branch_id' => null,
            'created_by' => $author->id,
            'type' => 'in',
            'quantity' => 10,
            'previous_stock' => 0,
            'new_stock' => 10,
            'movement_date' => now()->toDateString(),
        ], $attributes));
    }

    public function test_the_list_publishes_real_decimals_and_the_stock_flags(): void
    {
        $category = ProductCategory::factory()->create(['name' => 'Resinas']);
        $author = $this->userOf('odontologo');

        $this->product([
            'product_category_id' => $category->id,
            'name' => 'Resina A2',
            'code' => 'RES-A2',
            'barcode' => '7751234567890',
            'unit' => 'jeringa',
            'cost_price' => 12.50,
            'sale_price' => 30.00,
            'stock_quantity' => 0.5,
            'minimum_stock' => 1,
            'expiry_date' => now()->addDays(10)->toDateString(),
        ]);

        $this->product([
            'name' => 'Anestesia Lidocaina',
            'code' => 'ANE-001',
            'stock_quantity' => 0,
            'minimum_stock' => 2,
        ]);

        $this->product([
            'name' => 'Guantes M',
            'code' => 'GUA-M',
            'stock_quantity' => 12.5,
            'minimum_stock' => 2,
            'expiry_date' => now()->addDays(90)->toDateString(),
        ]);

        $response = $this->actingAs($author)->getJson('/api/inventory/products');

        $response->assertOk();
        $response->assertJsonPath('meta.total', 3);
        $response->assertJsonPath('meta.current_page', 1);

        $rows = collect($response->json('data'))->keyBy('code');

        $resin = $rows['RES-A2'];
        $this->assertSame(0.5, $resin['stock_quantity']);
        // La cantidad es decimal: 0.5 no se redondea a entero al viajar.
        $this->assertSame(1.0, (float) $resin['minimum_stock']);
        $this->assertSame(12.5, (float) $resin['cost_price']);
        $this->assertSame(30.0, (float) $resin['sale_price']);
        $this->assertSame('jeringa', $resin['unit']);
        $this->assertSame('7751234567890', $resin['barcode']);
        $this->assertSame(['id' => $category->id, 'name' => 'Resinas'], $resin['category']);
        $this->assertSame(now()->addDays(10)->toDateString(), $resin['expiry_date']);
        $this->assertTrue($resin['low_stock']);
        $this->assertFalse($resin['out_of_stock']);
        $this->assertTrue($resin['expiring_soon']);

        $anesthesia = $rows['ANE-001'];
        $this->assertTrue($anesthesia['out_of_stock']);
        $this->assertTrue($anesthesia['low_stock']);
        $this->assertFalse($anesthesia['expiring_soon']);
        $this->assertNull($anesthesia['expiry_date']);

        $gloves = $rows['GUA-M'];
        $this->assertSame(12.5, $gloves['stock_quantity']);
        $this->assertFalse($gloves['low_stock']);
        $this->assertFalse($gloves['out_of_stock']);
        $this->assertFalse($gloves['expiring_soon']);

        // La tira mide la tabla entera: 0.5 y 0 quedan por debajo de su mínimo.
        $response->assertJsonPath('meta.kpis.products', 3);
        $response->assertJsonPath('meta.kpis.low_stock', 2);
        $response->assertJsonPath('meta.kpis.out_of_stock', 1);
        $response->assertJsonPath('meta.kpis.expiring_soon', 1);
    }

    public function test_stock_status_search_and_paging_narrow_the_list_without_moving_the_kpis(): void
    {
        $author = $this->userOf('odontologo');

        $this->product([
            'name' => 'Resina A2',
            'code' => 'RES-A2',
            'stock_quantity' => 0.5,
            'minimum_stock' => 5,
        ]);

        $this->product([
            'name' => 'Anestesia Lidocaina',
            'code' => 'ANE-001',
            'stock_quantity' => 0,
            'minimum_stock' => 2,
        ]);

        $this->product([
            'name' => 'Guantes M',
            'code' => 'GUA-M',
            'stock_quantity' => 40,
            'minimum_stock' => 5,
            'expiry_date' => now()->addDays(5)->toDateString(),
        ]);

        $low = $this->actingAs($author)->getJson('/api/inventory/products?stock_status=low');
        $low->assertOk();
        $low->assertJsonCount(2, 'data');
        $this->assertSame(['ANE-001', 'RES-A2'], $this->codes($low->json('data')));

        $out = $this->actingAs($author)->getJson('/api/inventory/products?stock_status=out');
        $out->assertOk();
        $this->assertSame(['ANE-001'], $this->codes($out->json('data')));

        $expiring = $this->actingAs($author)->getJson('/api/inventory/products?stock_status=expiring');
        $expiring->assertOk();
        $this->assertSame(['GUA-M'], $this->codes($expiring->json('data')));

        // El filtro acota la página, nunca la población que mide la tira.
        $low->assertJsonPath('meta.kpis.products', 3);
        $low->assertJsonPath('meta.kpis.out_of_stock', 1);
        $low->assertJsonPath('meta.kpis.expiring_soon', 1);

        $search = $this->actingAs($author)->getJson('/api/inventory/products?q=RES');
        $search->assertOk();
        $this->assertSame(['RES-A2'], $this->codes($search->json('data')));

        $paged = $this->actingAs($author)->getJson('/api/inventory/products?per_page=2');
        $paged->assertOk();
        $paged->assertJsonCount(2, 'data');
        $paged->assertJsonPath('meta.per_page', 2);
        $paged->assertJsonPath('meta.total', 3);
        $paged->assertJsonPath('meta.last_page', 2);
    }

    public function test_the_search_escapes_the_like_wildcards(): void
    {
        $author = $this->userOf('odontologo');

        $this->product(['name' => 'Algodon 100%', 'code' => 'ALG-100', 'stock_quantity' => 5]);
        $this->product(['name' => 'Gasas', 'code' => 'GAS-001', 'stock_quantity' => 5]);

        $response = $this->actingAs($author)->getJson('/api/inventory/products?q='.urlencode('100%'));

        $response->assertOk();
        $this->assertSame(['ALG-100'], $this->codes($response->json('data')));
    }

    public function test_alerts_separate_low_out_and_expiring_and_count_each_list(): void
    {
        $author = $this->userOf('odontologo');

        $this->product([
            'name' => 'Resina A2',
            'code' => 'RES-A2',
            'stock_quantity' => 0.5,
            'minimum_stock' => 5,
        ]);

        $this->product([
            'name' => 'Anestesia Lidocaina',
            'code' => 'ANE-001',
            'stock_quantity' => 0,
            'minimum_stock' => 2,
        ]);

        $this->product([
            'name' => 'Guantes M',
            'code' => 'GUA-M',
            'stock_quantity' => 30,
            'minimum_stock' => 5,
            'expiry_date' => now()->addDays(10)->toDateString(),
        ]);

        $this->product([
            'name' => 'Gasas',
            'code' => 'GAS-001',
            'stock_quantity' => 30,
            'minimum_stock' => 5,
            'expiry_date' => now()->addDays(90)->toDateString(),
        ]);

        $this->product([
            'name' => 'Brackets',
            'code' => 'BRA-001',
            'stock_quantity' => 30,
            'minimum_stock' => 5,
        ]);

        $response = $this->actingAs($author)->getJson('/api/inventory/alerts');

        $response->assertOk();
        $response->assertJsonPath('meta.counts.low_stock', 2);
        $response->assertJsonPath('meta.counts.out_of_stock', 1);
        $response->assertJsonPath('meta.counts.expiring', 1);
        $response->assertJsonPath('meta.expiring_days', Product::EXPIRING_SOON_DAYS);

        // El más vacío primero: la lista de alertas se lee de arriba hacia abajo.
        $this->assertSame(['ANE-001', 'RES-A2'], $this->codes($response->json('data.low_stock')));
        $this->assertSame(['ANE-001'], $this->codes($response->json('data.out_of_stock')));
        $this->assertSame(['GUA-M'], $this->codes($response->json('data.expiring')));

        $response->assertJsonStructure([
            'data' => [
                'low_stock' => [[
                    'id', 'name', 'code', 'barcode', 'unit', 'category', 'cost_price',
                    'sale_price', 'stock_quantity', 'minimum_stock', 'maximum_stock',
                    'expiry_date', 'is_active', 'low_stock', 'out_of_stock', 'expiring_soon',
                ]],
                'out_of_stock',
                'expiring',
            ],
            'meta' => ['counts' => ['low_stock', 'out_of_stock', 'expiring'], 'expiring_days'],
        ]);
    }

    public function test_movements_filter_by_type_and_date_range_and_carry_their_product(): void
    {
        $author = $this->userOf('odontologo');
        $product = $this->product([
            'name' => 'Resina A2',
            'code' => 'RES-A2',
            'unit' => 'jeringa',
            'stock_quantity' => 7.5,
        ]);

        $received = $this->movement($product, $author, [
            'type' => 'in',
            'quantity' => 10,
            'previous_stock' => 0,
            'new_stock' => 10,
            'unit_cost' => 12.5,
            'total_cost' => 125,
            'batch_number' => 'LOTE-1',
            'expiry_date' => now()->addYear()->toDateString(),
            'reference' => 'FACT-001',
            'movement_date' => now()->subDays(10)->toDateString(),
        ]);

        $consumed = $this->movement($product, $author, [
            'type' => 'out',
            'quantity' => 2.5,
            'previous_stock' => 10,
            'new_stock' => 7.5,
            'unit_cost' => 12.5,
            'total_cost' => 31.25,
            'batch_number' => 'LOTE-1',
            'reason' => 'Consumo en atención',
            'movement_date' => now()->subDays(2)->toDateString(),
        ]);

        $counted = $this->movement($product, $author, [
            'type' => 'adjustment',
            'quantity' => -5,
            'previous_stock' => 10,
            'new_stock' => 5,
            'reason' => 'Conteo fisico',
            'movement_date' => now()->subDays(20)->toDateString(),
        ]);

        $byType = $this->actingAs($author)->getJson('/api/inventory/movements?type=out');
        $byType->assertOk();
        $byType->assertJsonCount(1, 'data');

        $row = $byType->json('data.0');
        $this->assertSame($consumed->id, $row['id']);
        $this->assertSame(['id' => $product->id, 'name' => 'Resina A2', 'code' => 'RES-A2', 'unit' => 'jeringa'], $row['product']);
        $this->assertSame('out', $row['type']);
        $this->assertSame(2.5, $row['quantity']);
        $this->assertSame(10.0, (float) $row['previous_stock']);
        $this->assertSame(7.5, $row['new_stock']);
        $this->assertSame(12.5, (float) $row['unit_cost']);
        $this->assertSame(31.25, (float) $row['total_cost']);
        $this->assertSame('LOTE-1', $row['batch_number']);
        $this->assertSame('Consumo en atención', $row['reason']);
        $this->assertSame(now()->subDays(2)->toDateString(), $row['movement_date']);
        $this->assertSame(['id' => $author->id, 'name' => $author->name], $row['created_by']);
        $this->assertArrayHasKey('created_at', $row);
        $this->assertNull($row['branch_id']);
        $this->assertNull($row['clinical_encounter_id']);

        // La ventana incluye sus dos bordes.
        $range = $this->actingAs($author)->getJson(
            '/api/inventory/movements?from='.now()->subDays(10)->toDateString().'&to='.now()->subDays(2)->toDateString()
        );
        $range->assertOk();
        $this->assertSame([$consumed->id, $received->id], collect($range->json('data'))->pluck('id')->all());

        $older = $this->actingAs($author)->getJson('/api/inventory/movements?to='.now()->subDays(15)->toDateString());
        $older->assertOk();
        $this->assertSame([$counted->id], collect($older->json('data'))->pluck('id')->all());

        $byProduct = $this->actingAs($author)->getJson('/api/inventory/movements?product_id='.$product->id.'&type=in');
        $byProduct->assertOk();
        $this->assertSame([$received->id], collect($byProduct->json('data'))->pluck('id')->all());
    }

    public function test_the_product_detail_publishes_its_last_twenty_movements(): void
    {
        $author = $this->userOf('odontologo');
        $product = $this->product([
            'name' => 'Resina A2',
            'code' => 'RES-A2',
            'stock_quantity' => 21,
        ]);

        $created = [];

        for ($i = 1; $i <= 21; $i++) {
            $created[] = $this->movement($product, $author, [
                'quantity' => 1,
                'previous_stock' => $i - 1,
                'new_stock' => $i,
                'reason' => 'Movimiento '.$i,
                'movement_date' => now()->toDateString(),
            ])->id;
        }

        $response = $this->actingAs($author)->getJson('/api/inventory/products/'.$product->id);

        $response->assertOk();
        $response->assertJsonPath('data.id', $product->id);
        $response->assertJsonPath('data.code', 'RES-A2');
        $response->assertJsonPath('data.stock_quantity', 21);

        $movements = $response->json('data.movements');
        $this->assertCount(20, $movements);

        $expected = array_slice(array_reverse($created), 0, 20);
        $this->assertSame($expected, collect($movements)->pluck('id')->all());

        $this->assertSame([
            'id', 'type', 'quantity', 'previous_stock', 'new_stock', 'unit_cost', 'total_cost',
            'batch_number', 'expiry_date', 'reference', 'reason', 'movement_date',
            'clinical_encounter_id', 'created_by',
        ], array_keys($movements[0]));

        $this->assertSame(['id' => $author->id, 'name' => $author->name], $movements[0]['created_by']);
    }

    public function test_an_unknown_product_answers_404(): void
    {
        $this->actingAs($this->userOf('odontologo'))
            ->getJson('/api/inventory/products/999999')
            ->assertNotFound();
    }

    public function test_the_catalog_grants_reading_to_the_warehouse_roles_only(): void
    {
        $this->assertSame(
            ['administrador', 'odontologo', 'implantologo', 'tecnico_dental', 'asistente', 'finanzas'],
            Permissions::rolesFor(Permissions::VIEW_INVENTORY),
        );

        $this->assertSame(
            ['administrador', 'finanzas', 'asistente'],
            Permissions::rolesFor(Permissions::MANAGE_INVENTORY),
        );
    }

    public function test_reception_is_forbidden_on_every_read_endpoint(): void
    {
        $receptionist = $this->userOf('recepcionista');
        $author = $this->userOf('odontologo');
        $product = $this->product(['name' => 'Resina A2', 'code' => 'RES-A2']);
        $movement = $this->movement($product, $author);

        foreach ([
            '/api/inventory/products',
            '/api/inventory/products/'.$product->id,
            '/api/inventory/movements',
            '/api/inventory/alerts',
        ] as $uri) {
            $this->actingAs($receptionist)->getJson($uri)->assertForbidden();
        }

        $this->assertDatabaseHas('stock_movements', ['id' => $movement->id]);
    }

    public function test_a_clinical_role_and_finance_read_every_endpoint(): void
    {
        $author = $this->userOf('odontologo');
        $product = $this->product(['name' => 'Resina A2', 'code' => 'RES-A2']);
        $this->movement($product, $author);

        foreach (['odontologo', 'finanzas'] as $role) {
            $user = $role === 'odontologo' ? $author : $this->userOf('finanzas');

            foreach ([
                '/api/inventory/products',
                '/api/inventory/products/'.$product->id,
                '/api/inventory/movements',
                '/api/inventory/alerts',
            ] as $uri) {
                $this->actingAs($user)->getJson($uri)->assertOk();
            }
        }
    }

    /**
     * @param  list<array<string, mixed>> $rows
     * @return list<string>
     */
    private function codes(array $rows): array
    {
        return collect($rows)->pluck('code')->sort()->values()->all();
    }
}
