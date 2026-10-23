<?php

namespace Database\Factories;

use App\Models\DiagnosisCatalog;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\DiagnosisCatalog>
 */
class DiagnosisCatalogFactory extends Factory
{
    protected $model = DiagnosisCatalog::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'cie_code' => fake()->unique()->numerify('K0#.#'),
            'name' => fake()->sentence(3),
            'description' => fake()->sentence(8),
            'category' => 'Caries dental',
            'specialty' => 'general',
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => ['is_active' => false]);
    }
}
