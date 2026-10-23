<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\ProductCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Product>
 */
class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition(): array
    {
        return [
            'product_category_id' => ProductCategory::factory(),
            'name' => $this->faker->unique()->words(2, true),
            'code' => strtoupper($this->faker->unique()->bothify('PRD-###??')),
            'unit' => 'unit',
            'cost_price' => 20.00,
            'sale_price' => 35.00,
            'stock_quantity' => 0,
            'minimum_stock' => 0,
            'is_active' => true,
        ];
    }

    /**
     * A product with stock already received in the warehouse.
     */
    public function withStock(float $quantity): static
    {
        return $this->state(fn (): array => ['stock_quantity' => $quantity]);
    }
}
