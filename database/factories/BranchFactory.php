<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Branch>
 */
class BranchFactory extends Factory
{
    protected $model = \App\Models\Branch::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->company().' Branch',
            'code' => strtoupper($this->faker->unique()->bothify('BR-###')),
            'address' => $this->faker->streetAddress(),
            // `branches.city` is NOT NULL without a default: the factory filled
            // every other required column and this one was 15 of the 71 failures
            // of the MySQL runner under strict mode.
            'city' => $this->faker->city(),
            'phone' => $this->faker->phoneNumber(),
            'is_active' => true,
        ];
    }
}
