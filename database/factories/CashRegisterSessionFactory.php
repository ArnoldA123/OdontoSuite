<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\CashRegisterSession>
 */
class CashRegisterSessionFactory extends Factory
{
    protected $model = \App\Models\CashRegisterSession::class;

    public function definition(): array
    {
        return [
            'user_id' => \App\Models\User::factory(),
            'branch_id' => \App\Models\Branch::factory(),
            'opening_amount' => 100.00,
            'status' => 'open',
            'opened_at' => now(),
            'session_code' => 'CS-' . $this->faker->bothify('####-####'),
        ];
    }
}
