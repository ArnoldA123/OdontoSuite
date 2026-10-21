<?php

namespace Tests\Feature\Api;

use App\Models\CashRegisterSession;
use App\Models\Patient;
use App\Models\PaymentMethod;
use App\Models\Transaction;
use App\Models\User;
use App\Services\TransactionService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Issue #192, decision B — voiding a transaction is administrator-only.
 *
 * The UI always hid the button from `finanzas` and `recepcionista`, but the
 * route admitted all three cash roles and TransactionService never checked the
 * role. The contract is now one-directional: the route answers 403 before the
 * controller runs, and the service refuses a non-administrator on its own so a
 * future caller cannot bypass the route.
 *
 * Must run against the MySQL harness (AGENTS.md §6).
 */
class TransactionVoidRoleTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role): User
    {
        return User::factory()->create([
            'role' => $role,
            'is_active' => true,
        ]);
    }

    private function openSessionFor(User $owner): CashRegisterSession
    {
        return CashRegisterSession::factory()->create([
            'user_id' => $owner->id,
            'status' => 'open',
            'opening_amount' => 100.0,
        ]);
    }

    private function transactionFor(User $creator, CashRegisterSession $session): Transaction
    {
        return Transaction::factory()->create([
            'patient_id' => Patient::factory()->create()->id,
            'payment_method_id' => PaymentMethod::factory()->create()->id,
            'created_by' => $creator->id,
            'cash_register_session_id' => $session->id,
            'amount' => 80.0,
            'type' => 'payment',
            'status' => 'completed',
        ]);
    }

    public function test_an_administrador_can_void_a_transaction(): void
    {
        $admin = $this->user('administrador');
        $session = $this->openSessionFor($admin);
        $transaction = $this->transactionFor($admin, $session);

        $response = $this->actingAs($admin, 'sanctum')
            ->postJson("/api/transactions/{$transaction->id}/void", [
                'reason' => 'Cobro duplicado',
            ]);

        $response->assertOk();
        $this->assertSame('voided', $response->json('data.status'));

        $this->actingAs($admin, 'sanctum')
            ->getJson("/api/transactions/{$transaction->id}")
            ->assertOk()
            ->assertJsonPath('data.status', 'voided');
    }

    public function test_finanzas_cannot_void_a_transaction(): void
    {
        $finanzas = $this->user('finanzas');
        $session = $this->openSessionFor($finanzas);
        $transaction = $this->transactionFor($finanzas, $session);

        $this->actingAs($finanzas, 'sanctum')
            ->postJson("/api/transactions/{$transaction->id}/void", [
                'reason' => 'Intento de finanzas',
            ])
            ->assertForbidden();

        $this->actingAs($finanzas, 'sanctum')
            ->getJson("/api/transactions/{$transaction->id}")
            ->assertOk()
            ->assertJsonPath('data.status', 'completed');
    }

    public function test_recepcionista_cannot_void_a_transaction(): void
    {
        $recepcionista = $this->user('recepcionista');
        $session = $this->openSessionFor($recepcionista);
        $transaction = $this->transactionFor($recepcionista, $session);

        $this->actingAs($recepcionista, 'sanctum')
            ->postJson("/api/transactions/{$transaction->id}/void", [
                'reason' => 'Intento de recepción',
            ])
            ->assertForbidden();

        $this->actingAs($recepcionista, 'sanctum')
            ->getJson("/api/transactions/{$transaction->id}")
            ->assertOk()
            ->assertJsonPath('data.status', 'completed');
    }

    public function test_the_service_refuses_a_non_administrator_without_the_route(): void
    {
        $finanzas = $this->user('finanzas');
        $session = $this->openSessionFor($finanzas);
        $transaction = $this->transactionFor($finanzas, $session);

        $this->actingAs($finanzas, 'sanctum');

        try {
            app(TransactionService::class)->voidTransaction($transaction->id, 'Sin ruta');
            $this->fail('The service must refuse a non-administrator.');
        } catch (AuthorizationException) {
            // Expected: the guard is the service contract, not the route.
        }

        $this->assertSame('completed', $transaction->fresh()->status);
    }
}
