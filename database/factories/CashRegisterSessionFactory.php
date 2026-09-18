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
            // `session_code` used to be set here and no migration ever declared
            // that column, so every insert failed with "Unknown column
            // 'session_code' in 'field list'" -- 15 of the 71 failures of the
            // MySQL runner. The schema is the truth; a code for the session is a
            // product decision, not something a fixture may invent.
        ];
    }
}
