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
            // `PM-##` only offered 90 combinations and two fixture rows in one
            // test already collided on the unique `code` index (CI flake). A
            // letters+digits tail keeps the shape readable while making a
            // collision negligible for the few rows the suite creates.
            'code' => 'PM-'.strtoupper($this->faker->bothify('???###')),
            'type' => \App\Models\PaymentMethod::TYPE_CASH,
            'requires_reference' => false,
            'description' => $this->faker->sentence(),
            'is_active' => true,
            'is_system' => false,
            'commission_percentage' => 0,
            'gateway_type' => null,
        ];
    }
}
