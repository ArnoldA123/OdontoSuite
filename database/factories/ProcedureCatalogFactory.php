<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ProcedureCatalog>
 */
class ProcedureCatalogFactory extends Factory
{
    protected $model = \App\Models\ProcedureCatalog::class;

    public function definition(): array
    {
        return [
            'code' => strtoupper($this->faker->bothify('PROC-####')),
            'name' => $this->faker->words(3, true),
            'description' => $this->faker->sentence(),
            'category' => $this->faker->randomElement([
                'restorative', 'endodontic', 'surgical', 'orthodontic', 'preventive',
            ]),
            'default_duration_minutes' => 30,
            'default_cost' => 100.00,
            'is_active' => true,
        ];
    }
}
