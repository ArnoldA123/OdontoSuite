<?php

namespace Tests\Feature\Api;

use App\Models\ProcedureCatalog;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * #295 (S4) — the procedure declares the inputs it consumes.
 *
 * `material_recipe` is the catalogue-side declaration: product plus estimated
 * quantity. The act copies it as a starting point, so this endpoint only owns
 * the recipe itself. The legacy `materials` string list keeps validating
 * untouched (S9).
 */
class ProcedureCatalogMaterialRecipeTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'administrador', 'is_active' => true]);
    }

    /**
     * @param  array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'code' => 'OBT-COMP',
            'name' => 'Obturación composite',
            'default_cost' => 180,
            'default_duration_minutes' => 45,
        ], $overrides);
    }

    public function test_a_procedure_records_the_inputs_it_consumes_and_publishes_them(): void
    {
        $admin = $this->admin();
        $resin = Product::factory()->create(['name' => 'Resina A2', 'unit' => 'jeringa', 'cost_price' => 20]);
        $anesthetic = Product::factory()->create(['name' => 'Anestesia lidocaína', 'unit' => 'cartucho', 'cost_price' => 3.5]);

        $response = $this->actingAs($admin, 'sanctum')->postJson('/api/procedure-catalog', $this->payload([
            'material_recipe' => [
                ['product_id' => $resin->id, 'quantity' => 1.5],
                ['product_id' => $anesthetic->id, 'quantity' => 2],
            ],
        ]));

        $response->assertCreated()
            ->assertJsonPath('data.material_recipe.0.product_id', $resin->id)
            ->assertJsonPath('data.material_recipe.0.quantity', 1.5)
            ->assertJsonPath('data.material_recipe.0.product_name', 'Resina A2')
            ->assertJsonPath('data.material_recipe.0.unit', 'jeringa')
            ->assertJsonPath('data.material_recipe.1.product_id', $anesthetic->id)
            ->assertJsonPath('data.material_recipe.1.quantity', 2);

        $procedure = ProcedureCatalog::where('code', 'OBT-COMP')->sole();
        $this->assertSame(2, $procedure->materialRecipe()->count());

        // The detail endpoint publishes the same recipe after a fresh read.
        $this->actingAs($admin, 'sanctum')
            ->getJson("/api/procedure-catalog/{$procedure->id}")
            ->assertOk()
            ->assertJsonPath('data.material_recipe.1.product_name', 'Anestesia lidocaína')
            ->assertJsonPath('data.material_recipe.1.cost_price', 3.5);
    }

    public function test_updating_the_procedure_replaces_the_recipe_when_it_is_sent(): void
    {
        $admin = $this->admin();
        $resin = Product::factory()->create();
        $anesthetic = Product::factory()->create();
        $procedure = ProcedureCatalog::factory()->create();
        $procedure->materialRecipe()->create(['product_id' => $resin->id, 'quantity' => 1]);

        $this->actingAs($admin, 'sanctum')
            ->putJson("/api/procedure-catalog/{$procedure->id}", [
                'material_recipe' => [['product_id' => $anesthetic->id, 'quantity' => 4]],
            ])
            ->assertOk()
            ->assertJsonCount(1, 'data.material_recipe');

        $this->assertSame([$anesthetic->id], $procedure->materialRecipe()->pluck('product_id')->all());
        $this->assertSame(4.0, (float) $procedure->materialRecipe()->sole()->quantity);
    }

    public function test_updating_without_the_recipe_leaves_it_untouched(): void
    {
        $admin = $this->admin();
        $product = Product::factory()->create();
        $procedure = ProcedureCatalog::factory()->create();
        $procedure->materialRecipe()->create(['product_id' => $product->id, 'quantity' => 3]);

        $this->actingAs($admin, 'sanctum')
            ->putJson("/api/procedure-catalog/{$procedure->id}", ['name' => 'Obturación composite'])
            ->assertOk();

        $this->assertSame(3.0, (float) $procedure->materialRecipe()->sole()->quantity);
    }

    public function test_an_empty_recipe_clears_the_inputs_of_the_procedure(): void
    {
        $admin = $this->admin();
        $product = Product::factory()->create();
        $procedure = ProcedureCatalog::factory()->create();
        $procedure->materialRecipe()->create(['product_id' => $product->id, 'quantity' => 3]);

        $this->actingAs($admin, 'sanctum')
            ->putJson("/api/procedure-catalog/{$procedure->id}", ['material_recipe' => []])
            ->assertOk()
            ->assertJsonCount(0, 'data.material_recipe');

        $this->assertSame(0, $procedure->materialRecipe()->count());
        $this->assertFalse($procedure->fresh()->hasMaterialRecipe());
    }

    public function test_the_recipe_refuses_an_unknown_product(): void
    {
        $this->actingAs($this->admin(), 'sanctum')
            ->postJson('/api/procedure-catalog', $this->payload([
                'material_recipe' => [['product_id' => 999999, 'quantity' => 1]],
            ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['material_recipe.0.product_id']);
    }

    public function test_the_recipe_refuses_a_quantity_that_is_not_positive(): void
    {
        $product = Product::factory()->create();

        $this->actingAs($this->admin(), 'sanctum')
            ->postJson('/api/procedure-catalog', $this->payload([
                'material_recipe' => [['product_id' => $product->id, 'quantity' => 0]],
            ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['material_recipe.0.quantity']);
    }

    public function test_the_recipe_refuses_the_same_product_twice(): void
    {
        $product = Product::factory()->create();

        $this->actingAs($this->admin(), 'sanctum')
            ->postJson('/api/procedure-catalog', $this->payload([
                'material_recipe' => [
                    ['product_id' => $product->id, 'quantity' => 1],
                    ['product_id' => $product->id, 'quantity' => 2],
                ],
            ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['material_recipe.1.product_id']);
    }

    public function test_the_legacy_materials_string_list_still_validates(): void
    {
        $this->actingAs($this->admin(), 'sanctum')
            ->postJson('/api/procedure-catalog', $this->payload([
                'materials' => ['Resina compuesta', 'Grabador ácido'],
            ]))
            ->assertCreated();
    }
}
