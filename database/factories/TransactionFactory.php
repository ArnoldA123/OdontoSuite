<?php

namespace Database\Factories;

use App\Models\Patient;
use App\Models\PaymentMethod;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Transaction>
 */
class TransactionFactory extends Factory
{
    /**
     * Monotonic within the process. `transactions.transaction_number` is unique
     * and production formats it as `TXN-<date>-<sequence>`
     * (TransactionService::generateTransactionNumber), so a counter that never
     * repeats is the only form that cannot collide across tests.
     */
    private static int $sequence = 0;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        // `transactions` declares five columns NOT NULL without a default --
        // patient_id, payment_method_id, created_by, transaction_number and
        // amount -- and no factory existed at all, so every test that called
        // Transaction::factory() died with "Class
        // Database\Factories\TransactionFactory not found" before reaching what
        // it wanted to assert.
        return [
            'patient_id' => Patient::factory(),
            'payment_method_id' => PaymentMethod::factory(),
            'created_by' => User::factory(),
            'transaction_number' => 'TXN-'.now()->format('Ymd').'-'
                .str_pad((string) ++self::$sequence, 4, '0', STR_PAD_LEFT),
            'amount' => $this->faker->randomFloat(2, 20, 500),
            'type' => 'payment',
            'status' => 'completed',
            'processed_at' => now(),
        ];
    }
}
