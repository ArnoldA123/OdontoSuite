<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\AppointmentType>
 */
class AppointmentTypeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        // `appointment_types.name` and `default_duration_minutes` are NOT NULL
        // without a default, and this factory defined nothing: under strict mode
        // the insert failed with "Field 'name' doesn't have a default value".
        // Filling the three columns that the schema requires is what those tests
        // were waiting for.
        return [
            'name' => $this->faker->randomElement([
                'Consulta general',
                'Limpieza dental',
                'Endodoncia',
                'Ortodoncia',
                'Extracción',
                'Control',
            ]).' '.$this->faker->unique()->numberBetween(1, 9999),
            'description' => $this->faker->sentence(8),
            'default_duration_minutes' => $this->faker->randomElement([15, 30, 45, 60]),
            'is_active' => true,
        ];
    }
}
