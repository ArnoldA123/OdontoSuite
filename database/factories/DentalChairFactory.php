<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\DentalChair>
 */
class DentalChairFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        // `dental_chairs.name` and `code` are NOT NULL, and `code` is unique: a
        // factory that defines nothing fails on insert under strict mode with
        // "Field 'name' doesn't have a default value".
        return [
            'name' => 'Sillón '.$this->faker->unique()->numberBetween(1, 999),
            'code' => 'SILLA-'.$this->faker->unique()->numerify('####'),
            'description' => $this->faker->sentence(6),
            'status' => 'active',
            'is_active' => true,
        ];
    }
}
