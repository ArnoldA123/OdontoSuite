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
            'name' => $this->faker->company() . ' Branch',
            'code' => strtoupper($this->faker->bothify('BR-###')),
            'address' => $this->faker->streetAddress(),
            'phone' => $this->faker->phoneNumber(),
            'is_active' => true,
        ];
    }
}
