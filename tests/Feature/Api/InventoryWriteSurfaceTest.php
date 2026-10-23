<?php

namespace Tests\Feature\Api;

use App\Models\Branch;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * #295 (S6, write half) — the warehouse receiving a purchase and counting a
 * shelf.
 *
 * Both doors write two facts in the same breath: the movement in the ledger
 * (`stock_movements`) and the new balance in `products.stock_quantity`, which
 * stays the only truth of current stock. A receipt adds what arrived and never
 * reprices the catalog; a count fixes the balance at what was counted, and the
 * ledger keeps the magnitude positive because the direction is already told by
 * `previous_stock` and `new_stock`.
 */
class InventoryWriteSurfaceTest extends TestCase
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
     * @param  array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private function receiptPayload(Product $product, array $overrides = []): array
    {
        return array_merge([
            'product_id' => $product->id,
            'quantity' => 1,
        ], $overrides);
    }

    /**
     * @param  array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private function adjustmentPayload(Product $product, array $overrides = []): array
    {
        return array_merge([
            'product_id' => $product->id,
            'counted_quantity' => 1,
            'reason' => 'Conteo físico mensual',
        ], $overrides);
    }

    public function test_a_receipt_adds_the_stock_and_writes_the_in_movement(): void
    {
        $warehouse = $this->userOf('asistente');
        $product = $this->product([
            'name' => 'Resina A2',
            'code' => 'RES-A2',
            'unit' => 'jeringa',
            'cost_price' => 12.50,
            'stock_quantity' => 0.5,
        ]);

        $expiry = now()->addMonths(6)->toDateString();

        $response = $this->actingAs($warehouse)->postJson('/api/inventory/receipts', $this->receiptPayload($product, [
            'quantity' => 2.25,
            'unit_cost' => 11.80,
            'batch_number' => 'LOTE-77',
            'expiry_date' => $expiry,
            'reference' => 'FACT-0007',
            'notes' => 'Compra del mes',
        ]));

        $response->assertCreated();
        $response->assertJsonPath('meta.message', 'Recepción registrada.');
        $this->assertSame(2.75, (float) $response->json('meta.stock_quantity'));
        $this->assertSame(2.25, (float) $response->json('meta.delta'));

        // El producto publicado es la misma fila del listado, ya con el saldo
        // nuevo y el costo de catálogo intacto.
        $response->assertJsonPath('data.product.id', $product->id);
        $response->assertJsonPath('data.product.code', 'RES-A2');
        $this->assertSame(2.75, (float) $response->json('data.product.stock_quantity'));
        $this->assertSame(12.5, (float) $response->json('data.product.cost_price'));

        $this->assertSame(2.75, (float) $product->fresh()->stock_quantity);
        $this->assertSame(12.5, (float) $product->fresh()->cost_price);

        $movement = StockMovement::query()->sole();

        $this->assertSame($product->id, (int) $movement->product_id);
        $this->assertSame('in', $movement->type);
        $this->assertSame(2.25, (float) $movement->quantity);
        $this->assertSame(0.5, (float) $movement->previous_stock);
        $this->assertSame(2.75, (float) $movement->new_stock);
        $this->assertSame(11.8, (float) $movement->unit_cost);
        $this->assertSame(26.55, (float) $movement->total_cost);
        $this->assertSame('LOTE-77', $movement->batch_number);
        $this->assertSame($expiry, $movement->expiry_date->toDateString());
        $this->assertSame('FACT-0007', $movement->reference);
        $this->assertSame('Compra del mes', $movement->notes);
        $this->assertSame('Recepción por compra', $movement->reason);
        $this->assertSame(now()->toDateString(), $movement->movement_date->toDateString());
        $this->assertSame($warehouse->id, (int) $movement->created_by);
        $this->assertNull($movement->branch_id);
        $this->assertNull($movement->clinical_encounter_id);

        // El movimiento viaja con el mismo mapper del libro.
        $row = $response->json('data.movement');
        $this->assertSame($movement->id, $row['id']);
        $this->assertSame('in', $row['type']);
        $this->assertSame(2.25, (float) $row['quantity']);
        $this->assertSame(0.5, (float) $row['previous_stock']);
        $this->assertSame(2.75, (float) $row['new_stock']);
        $this->assertSame('LOTE-77', $row['batch_number']);
        $this->assertSame('FACT-0007', $row['reference']);
        $this->assertSame(['id' => $product->id, 'name' => 'Resina A2', 'code' => 'RES-A2', 'unit' => 'jeringa'], $row['product']);
        $this->assertSame(['id' => $warehouse->id, 'name' => $warehouse->name], $row['created_by']);
    }

    public function test_a_receipt_without_unit_cost_prices_the_batch_at_the_catalog_cost(): void
    {
        $warehouse = $this->userOf('finanzas');
        $product = $this->product([
            'name' => 'Guantes M',
            'code' => 'GUA-M',
            'cost_price' => 8.40,
            'stock_quantity' => 0,
        ]);

        $response = $this->actingAs($warehouse)->postJson(
            '/api/inventory/receipts',
            $this->receiptPayload($product, ['quantity' => 12, 'movement_date' => now()->subDay()->toDateString()]),
        );

        $response->assertCreated();
        $this->assertSame(12.0, (float) $response->json('meta.stock_quantity'));

        $movement = StockMovement::query()->sole();
        $this->assertSame(8.4, (float) $movement->unit_cost);
        $this->assertSame(100.8, (float) $movement->total_cost);
        $this->assertSame(now()->subDay()->toDateString(), $movement->movement_date->toDateString());
    }

    public function test_a_receipt_of_nothing_or_of_an_expired_batch_is_rejected(): void
    {
        $warehouse = $this->userOf('asistente');
        $product = $this->product(['name' => 'Gasas', 'code' => 'GAS-001']);

        $this->actingAs($warehouse)
            ->postJson('/api/inventory/receipts', $this->receiptPayload($product, [
                'quantity' => 0,
                'expiry_date' => now()->subDay()->toDateString(),
            ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['quantity', 'expiry_date']);

        $this->actingAs($warehouse)
            ->postJson('/api/inventory/receipts', $this->receiptPayload($product, [
                'product_id' => 999999,
                'quantity' => 1,
            ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['product_id']);

        $this->assertDatabaseCount('stock_movements', 0);
        $this->assertSame(0.0, (float) $product->fresh()->stock_quantity);
    }

    public function test_a_count_below_the_ledger_fixes_the_stock_at_the_counted_quantity(): void
    {
        $warehouse = $this->userOf('asistente');
        $product = $this->product([
            'name' => 'Anestesia Lidocaina',
            'code' => 'ANE-001',
            'cost_price' => 4.00,
            'stock_quantity' => 12.5,
        ]);

        $response = $this->actingAs($warehouse)->postJson('/api/inventory/adjustments', $this->adjustmentPayload($product, [
            'counted_quantity' => 10.25,
            'notes' => 'Faltante de dos unidades',
            'movement_date' => now()->subDays(2)->toDateString(),
        ]));

        $response->assertCreated();
        $response->assertJsonPath('meta.message', 'Ajuste registrado.');
        $this->assertSame(10.25, (float) $response->json('meta.stock_quantity'));
        $this->assertSame(-2.25, (float) $response->json('meta.delta'));
        $this->assertSame(10.25, (float) $response->json('data.product.stock_quantity'));

        $this->assertSame(10.25, (float) $product->fresh()->stock_quantity);

        $movement = StockMovement::query()->sole();
        $this->assertSame('adjustment', $movement->type);
        // La magnitud va siempre positiva: la dirección la dicen los saldos.
        $this->assertSame(2.25, (float) $movement->quantity);
        $this->assertSame(12.5, (float) $movement->previous_stock);
        $this->assertSame(10.25, (float) $movement->new_stock);
        $this->assertSame(4.0, (float) $movement->unit_cost);
        $this->assertSame(9.0, (float) $movement->total_cost);
        $this->assertSame('Conteo físico mensual', $movement->reason);
        $this->assertSame('Faltante de dos unidades', $movement->notes);
        $this->assertSame(now()->subDays(2)->toDateString(), $movement->movement_date->toDateString());
        $this->assertSame($warehouse->id, (int) $movement->created_by);
        $this->assertNull($movement->branch_id);
        $this->assertNull($movement->batch_number);

        $row = $response->json('data.movement');
        $this->assertSame($movement->id, $row['id']);
        $this->assertSame('adjustment', $row['type']);
        $this->assertSame(2.25, (float) $row['quantity']);
        $this->assertSame(12.5, (float) $row['previous_stock']);
        $this->assertSame(10.25, (float) $row['new_stock']);
    }

    public function test_a_count_above_the_ledger_records_the_surplus(): void
    {
        $warehouse = $this->userOf('administrador');
        $product = $this->product([
            'name' => 'Brackets',
            'code' => 'BRA-001',
            'cost_price' => 2.00,
            'stock_quantity' => 2,
        ]);

        $response = $this->actingAs($warehouse)->postJson('/api/inventory/adjustments', $this->adjustmentPayload($product, [
            'counted_quantity' => 7.5,
        ]));

        $response->assertCreated();
        $this->assertSame(7.5, (float) $response->json('meta.stock_quantity'));
        $this->assertSame(5.5, (float) $response->json('meta.delta'));
        $this->assertSame(7.5, (float) $product->fresh()->stock_quantity);

        $movement = StockMovement::query()->sole();
        $this->assertSame('adjustment', $movement->type);
        $this->assertSame(5.5, (float) $movement->quantity);
        $this->assertSame(2.0, (float) $movement->previous_stock);
        $this->assertSame(7.5, (float) $movement->new_stock);
        $this->assertSame(11.0, (float) $movement->total_cost);
    }

    public function test_a_count_that_matches_the_ledger_is_rejected_and_writes_nothing(): void
    {
        $warehouse = $this->userOf('asistente');
        $product = $this->product([
            'name' => 'Resina A2',
            'code' => 'RES-A2',
            'stock_quantity' => 4,
        ]);

        $response = $this->actingAs($warehouse)->postJson('/api/inventory/adjustments', $this->adjustmentPayload($product, [
            'counted_quantity' => 4,
        ]));

        $response->assertStatus(422);
        $response->assertJsonPath(
            'error.message',
            'El conteo coincide con el stock registrado (4): no hay ajuste que registrar.',
        );

        $this->assertDatabaseCount('stock_movements', 0);
        $this->assertSame(4.0, (float) $product->fresh()->stock_quantity);
    }

    public function test_a_count_needs_a_reason_that_explains_itself(): void
    {
        $warehouse = $this->userOf('asistente');
        $product = $this->product([
            'name' => 'Resina A2',
            'code' => 'RES-A2',
            'stock_quantity' => 4,
        ]);

        $this->actingAs($warehouse)
            ->postJson('/api/inventory/adjustments', $this->adjustmentPayload($product, [
                'counted_quantity' => 3,
                'reason' => 'Conteo',
            ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['reason']);

        $this->assertDatabaseCount('stock_movements', 0);
    }

    public function test_only_the_warehouse_roles_write_and_clinical_work_only_reads(): void
    {
        $author = $this->userOf('odontologo');
        $product = $this->product(['name' => 'Resina A2', 'code' => 'RES-A2', 'stock_quantity' => 10]);

        foreach (['odontologo', 'recepcionista'] as $role) {
            $user = $role === 'odontologo' ? $author : $this->userOf($role);

            $this->actingAs($user)
                ->postJson('/api/inventory/receipts', $this->receiptPayload($product, ['quantity' => 1]))
                ->assertForbidden();

            $this->actingAs($user)
                ->postJson('/api/inventory/adjustments', $this->adjustmentPayload($product, ['counted_quantity' => 9]))
                ->assertForbidden();
        }

        $this->assertDatabaseCount('stock_movements', 0);
        $this->assertSame(10.0, (float) $product->fresh()->stock_quantity);

        foreach (['asistente', 'finanzas', 'administrador'] as $role) {
            $user = $this->userOf($role);

            $this->actingAs($user)
                ->postJson('/api/inventory/receipts', $this->receiptPayload($product, ['quantity' => 1]))
                ->assertCreated();

            $this->actingAs($user)
                ->postJson('/api/inventory/adjustments', $this->adjustmentPayload($product, [
                    'counted_quantity' => (float) $product->fresh()->stock_quantity - 0.5,
                ]))
                ->assertCreated();
        }

        $this->assertDatabaseCount('stock_movements', 6);
        $this->assertSame(11.5, (float) $product->fresh()->stock_quantity);
    }

    public function test_a_receipt_from_an_affiliated_user_lands_in_their_sede(): void
    {
        $branch = Branch::factory()->create();
        $assistant = User::factory()->create([
            'role' => 'asistente',
            'is_active' => true,
            'branch_id' => $branch->id,
        ]);
        $product = $this->product(['stock_quantity' => 0]);

        $this->actingAs($assistant, 'sanctum')
            ->postJson('/api/inventory/receipts', $this->receiptPayload($product, ['quantity' => 2]))
            ->assertCreated()
            ->assertJsonPath('data.movement.branch_id', $branch->id);

        // La sede del movimiento sigue al usuario afiliado; sin sede el libro
        // registra alcance de grupo (ADR-0010).
        $this->assertSame($branch->id, StockMovement::sole()->branch_id);
    }
}
