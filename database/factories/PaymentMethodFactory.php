<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\PaymentMethod>
 */
class PaymentMethodFactory extends Factory
{
    protected $model = \App\Models\PaymentMethod::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->words(2, true),
            'code' => strtoupper($this->faker->bothify('PM-##')),
            'description' => $this->faker->sentence(),
            'is_active' => true,
            'is_system' => false,
            'commission_percentage' => 0,
            'gateway_type' => null,
        ];
    }
}
