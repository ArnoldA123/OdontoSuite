<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Patient>
 */
class PatientFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        // `patients.first_name` and `last_name` are NOT NULL without a default, so
        // a factory that defines nothing fails on insert under strict mode with
        // "Field 'first_name' doesn't have a default value". That single gap was
        // 28 of the 71 failures of the MySQL runner, which is why this file is no
        // longer empty.
        return [
            'first_name' => $this->faker->firstName(),
            'last_name' => $this->faker->lastName(),
            'email' => $this->faker->unique()->safeEmail(),
            'phone' => $this->faker->numerify('9########'),
            'birth_date' => $this->faker->dateTimeBetween('-80 years', '-18 years')->format('Y-m-d'),
            'gender' => $this->faker->randomElement(['male', 'female', 'other']),
            'address' => $this->faker->streetAddress(),
            'is_active' => true,
        ];
    }
}
