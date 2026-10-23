<?php

namespace Tests\Feature\Domain;

use App\Models\ClinicalEncounter;
use App\Models\ProcedureCatalog;
use App\Models\ProcedureMaterial;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * #295 (S4, S5, S8) — the inventory data model.
 *
 * The procedure declares the inputs it consumes, the stock ledger keeps three
 * decimals so a half syringe or 12.5 ml is a real quantity, and a stock
 * movement can be traced to the clinical act that consumed it.
 */
class InventorySchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_recipe_table_links_a_procedure_to_the_products_it_consumes(): void
    {
        $procedure = ProcedureCatalog::factory()->create();
        $resin = Product::factory()->create();
        $anesthetic = Product::factory()->create();

        $procedure->materialRecipe()->create(['product_id' => $resin->id, 'quantity' => 1.5]);
        $procedure->materialRecipe()->create(['product_id' => $anesthetic->id, 'quantity' => 2]);

        $recipe = $procedure->materialRecipe()->with('product')->get()->keyBy('product_id');

        $this->assertCount(2, $recipe);
        $this->assertSame($anesthetic->id, $recipe[$anesthetic->id]->product_id);
        $this->assertSame($anesthetic->name, $recipe[$anesthetic->id]->product->name);
        $this->assertSame(2.0, (float) $recipe[$anesthetic->id]->quantity);
        $this->assertSame(1.5, (float) $recipe[$resin->id]->quantity);
    }

    public function test_a_procedure_declares_each_product_once(): void
    {
        $procedure = ProcedureCatalog::factory()->create();
        $product = Product::factory()->create();

        $procedure->materialRecipe()->create(['product_id' => $product->id, 'quantity' => 1]);

        $this->expectException(\Illuminate\Database\QueryException::class);

        $procedure->materialRecipe()->create(['product_id' => $product->id, 'quantity' => 2]);
    }

    public function test_stock_and_consumption_quantities_keep_three_decimals(): void
    {
        $product = Product::factory()->create([
            'stock_quantity' => 12.5,
            'minimum_stock' => 0.5,
            'maximum_stock' => 100.25,
        ]);

        $product->refresh();

        $this->assertSame(12.5, (float) $product->stock_quantity);
        $this->assertSame(0.5, (float) $product->minimum_stock);
        $this->assertSame(100.25, (float) $product->maximum_stock);
        $this->assertSame('decimal', Schema::getColumnType('products', 'stock_quantity'));

        $material = ProcedureMaterial::create([
            'product_id' => $product->id,
            'created_by' => User::factory()->create()->id,
            'quantity_used' => 0.5,
            'unit_cost' => 20,
        ]);

        $this->assertSame(0.5, (float) $material->fresh()->quantity_used);
        $this->assertSame('10.00', $material->fresh()->total_cost);
        $this->assertSame('decimal', Schema::getColumnType('procedure_materials', 'quantity_used'));

        $movement = StockMovement::create([
            'product_id' => $product->id,
            'created_by' => User::factory()->create()->id,
            'type' => 'out',
            'quantity' => 2.25,
            'previous_stock' => 12.5,
            'new_stock' => 10.25,
            'movement_date' => now()->toDateString(),
        ]);

        $movement->refresh();

        $this->assertSame(2.25, (float) $movement->quantity);
        $this->assertSame(12.5, (float) $movement->previous_stock);
        $this->assertSame(10.25, (float) $movement->new_stock);
        $this->assertSame('decimal', Schema::getColumnType('stock_movements', 'quantity'));
    }

    public function test_a_stock_movement_is_traceable_to_the_clinical_act_that_consumed_it(): void
    {
        $encounter = ClinicalEncounter::factory()->create();
        $product = Product::factory()->withStock(5)->create();

        $movement = StockMovement::create([
            'product_id' => $product->id,
            'clinical_encounter_id' => $encounter->id,
            'created_by' => User::factory()->create()->id,
            'type' => 'out',
            'quantity' => 2,
            'previous_stock' => 5,
            'new_stock' => 3,
            'movement_date' => now()->toDateString(),
        ]);

        $this->assertSame($encounter->id, $movement->fresh()->clinicalEncounter->id);
        $this->assertSame(1, StockMovement::where('clinical_encounter_id', $encounter->id)->count());
    }

    public function test_a_stock_movement_can_be_recorded_without_a_branch(): void
    {
        $product = Product::factory()->withStock(1)->create();

        $movement = StockMovement::create([
            'product_id' => $product->id,
            'created_by' => User::factory()->create(['branch_id' => null])->id,
            'type' => 'in',
            'quantity' => 1,
            'previous_stock' => 1,
            'new_stock' => 2,
            'movement_date' => now()->toDateString(),
        ]);

        $this->assertNull($movement->fresh()->branch_id);
    }
}
