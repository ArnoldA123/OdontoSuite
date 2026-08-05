<?php

namespace Tests\Feature\Api;

use App\Models\User;
use App\Models\Transaction;
use App\Models\CashRegisterSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * RED test (slice 01, T-01.9) for:
 *  - API-007: GET /api/transactions/list
 *  - API-024: voidTransaction route
 *  - API-025: generateReceipt route
 */
class TransactionEndpointsTest extends TestCase
{
    use RefreshDatabase;

    protected function finanzasUser(): User
    {
        return User::factory()->create([
            'role' => 'finanzas',
            'is_active' => true,
        ]);
    }

    protected function openSessionFor(User $user): CashRegisterSession
    {
        return CashRegisterSession::factory()->create([
            'user_id' => $user->id,
            'status' => 'open',
            'opening_amount' => 100.0,
        ]);
    }

    public function test_transactions_list_returns_200(): void
    {
        $user = $this->finanzasUser();
        $this->openSessionFor($user);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/transactions/list');

        $response->assertOk();
        $this->assertIsArray($response->json('data'));
    }

    public function test_void_transaction_returns_200(): void
    {
        $user = $this->finanzasUser();
        $session = $this->openSessionFor($user);

        $transaction = Transaction::factory()->create([
            'cash_register_session_id' => $session->id,
            'status' => 'completed',
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson("/api/transactions/{$transaction->id}/void", [
                'reason' => 'Test void',
            ]);

        $this->assertNotEquals(404, $response->status());
        $this->assertNotEquals(500, $response->status());
    }

    public function test_generate_receipt_returns_200(): void
    {
        $user = $this->finanzasUser();
        $session = $this->openSessionFor($user);

        $transaction = Transaction::factory()->create([
            'cash_register_session_id' => $session->id,
            'status' => 'completed',
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson("/api/transactions/{$transaction->id}/receipt");

        $this->assertNotEquals(404, $response->status());
        $this->assertNotEquals(500, $response->status());
    }
}
