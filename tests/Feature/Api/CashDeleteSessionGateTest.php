<?php

namespace Tests\Feature\Api;

use App\Models\CashMovement;
use App\Models\CashRegisterSession;
use App\Models\Patient;
use App\Models\PaymentMethod;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Issue #58 — cash.session no cubría DELETE.
 *
 * El middleware filtraba solo POST/PUT/PATCH mientras routes/api.php mete
 * los resources completos (incluido DELETE) dentro del grupo cash.session.
 * Anular movimientos sin sesión abierta puenteaba el control de caja.
 *
 * @group mysql
 */
class CashDeleteSessionGateTest extends TestCase
{
    use RefreshDatabase;

    private static int $sequence = 0;

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

    protected function closedSessionFor(User $user): CashRegisterSession
    {
        return CashRegisterSession::factory()->create([
            'user_id' => $user->id,
            'status' => 'closed',
            'opening_amount' => 100.0,
            'closed_at' => now(),
        ]);
    }

    protected function completedTransaction(CashRegisterSession $session): Transaction
    {
        return Transaction::create([
            'patient_id' => Patient::factory()->create()->id,
            'payment_method_id' => PaymentMethod::factory()->create()->id,
            'cash_register_session_id' => $session->id,
            'created_by' => $session->user_id,
            'transaction_number' => 'TXN-'.now()->format('Ymd').'-DEL'.(++self::$sequence),
            'type' => 'payment',
            'amount' => 150.00,
            'subtotal' => 150.00,
            'discount_amount' => 0,
            'commission_amount' => 0,
            'description' => 'Gate test transaction',
            'status' => 'completed',
            'processed_at' => now(),
        ]);
    }

    protected function expenseMovement(CashRegisterSession $session): CashMovement
    {
        return CashMovement::create([
            'cash_register_session_id' => $session->id,
            'created_by' => $session->user_id,
            'type' => 'expense',
            'amount' => 25.00,
            'description' => 'Gate test movement',
            'reference' => 'GATE-'.$session->id.'-'.(++self::$sequence),
        ]);
    }

    public function test_delete_transaction_with_closed_session_returns_422(): void
    {
        $user = $this->finanzasUser();
        $session = $this->closedSessionFor($user);
        $transaction = $this->completedTransaction($session);

        $response = $this->actingAs($user, 'sanctum')
            ->deleteJson("/api/transactions/{$transaction->id}");

        $response->assertStatus(422);
        $response->assertJsonPath('error', 'NO_ACTIVE_SESSION');
    }

    public function test_delete_transaction_with_open_session_succeeds(): void
    {
        $user = $this->finanzasUser();
        $session = $this->openSessionFor($user);
        $transaction = $this->completedTransaction($session);

        $response = $this->actingAs($user, 'sanctum')
            ->deleteJson("/api/transactions/{$transaction->id}");

        $response->assertOk();
        $response->assertJsonPath('meta.message', 'Transacción eliminada exitosamente');
    }

    public function test_delete_cash_movement_with_closed_session_returns_422(): void
    {
        $user = $this->finanzasUser();
        $session = $this->closedSessionFor($user);
        $movement = $this->expenseMovement($session);

        $response = $this->actingAs($user, 'sanctum')
            ->deleteJson("/api/cash-movements/{$movement->id}");

        $response->assertStatus(422);
        $response->assertJsonPath('error', 'NO_ACTIVE_SESSION');
    }

    public function test_delete_cash_movement_with_open_session_succeeds(): void
    {
        $user = $this->finanzasUser();
        $session = $this->openSessionFor($user);
        $movement = $this->expenseMovement($session);

        $response = $this->actingAs($user, 'sanctum')
            ->deleteJson("/api/cash-movements/{$movement->id}");

        $response->assertOk();
        $response->assertJsonPath('meta.message', 'Movimiento de caja eliminado exitosamente');
    }
}
