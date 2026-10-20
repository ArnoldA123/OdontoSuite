<?php

namespace Tests\Feature\Api;

use App\Models\Branch;
use App\Models\CashMovement;
use App\Models\CashRegisterSession;
use App\Models\Patient;
use App\Models\PaymentMethod;
use App\Models\Transaction;
use App\Models\User;
use App\Services\CashRegisterService;
use App\Services\TransactionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Integrity rules that keep the cash ledger reversible (2026-10).
 *
 * The ledger is the single source of the drawer position, so three invariants
 * have to hold or the position lies:
 *
 *   - a void reverses the transaction's own flow exactly once, whatever the
 *     transaction type maps to;
 *   - the opening and closing snapshots cannot be edited or deleted, because
 *     `opening_amount` already counts the first and `closing_amount` records
 *     the second;
 *   - a discount can never exceed the amount it discounts, or the ledger row
 *     turns negative.
 *
 * Must run against the MySQL harness (AGENTS.md §6).
 */
class CashLedgerIntegrityTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Branch $branch;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'role' => 'administrador',
            'is_active' => true,
        ]);
        $this->branch = Branch::factory()->create();

        $this->actingAs($this->admin, 'sanctum');
    }

    private function openSession(float $opening = 100.0): CashRegisterSession
    {
        return app(CashRegisterService::class)->openSession([
            'branch_id' => $this->branch->id,
            'opening_amount' => $opening,
        ]);
    }

    private function transaction(string $type, float $amount, array $extra = []): Transaction
    {
        return app(TransactionService::class)->createTransaction([
            'patient_id' => Patient::factory()->create()->id,
            'payment_method_id' => PaymentMethod::factory()->create()->id,
            'amount' => $amount,
            'type' => $type,
            'description' => 'Transacción de prueba',
            ...$extra,
        ]);
    }

    /** @return array<string, mixed> */
    private function summary(CashRegisterSession $session): array
    {
        return app(CashRegisterService::class)->getSessionSummary($session->fresh());
    }

    public function test_voiding_a_discount_transaction_reverses_its_expense_once(): void
    {
        $session = $this->openSession(100.0);
        $transaction = $this->transaction('discount', 30.0);

        $this->assertEqualsWithDelta(70.0, $this->summary($session)['expected_amount'], 0.001);

        app(TransactionService::class)->voidTransaction($transaction->id, 'Prueba');

        $summary = $this->summary($session);

        $this->assertEqualsWithDelta(30.0, $summary['cash_out'], 0.001);
        $this->assertEqualsWithDelta(30.0, $summary['cash_in'], 0.001);
        $this->assertEqualsWithDelta(
            100.0,
            $summary['expected_amount'],
            0.001,
            'A voided discount must restore the drawer position, not double the expense.'
        );
    }

    public function test_voiding_a_refund_returns_the_cash_once(): void
    {
        $session = $this->openSession(100.0);
        $transaction = $this->transaction('refund', 20.0);

        $this->assertEqualsWithDelta(80.0, $this->summary($session)['expected_amount'], 0.001);

        app(TransactionService::class)->voidTransaction($transaction->id, 'Prueba');

        $this->assertEqualsWithDelta(100.0, $this->summary($session)['expected_amount'], 0.001);
    }

    public function test_the_opening_snapshot_cannot_be_edited(): void
    {
        $session = $this->openSession(100.0);
        $opening = CashMovement::where('cash_register_session_id', $session->id)
            ->where('reference', 'OPEN-'.str_pad((string) $session->id, 6, '0', STR_PAD_LEFT))
            ->firstOrFail();

        $this->putJson("/api/cash-movements/{$opening->id}", ['reference' => null])
            ->assertStatus(422);

        $this->assertEqualsWithDelta(100.0, $this->summary($session)['expected_amount'], 0.001);
    }

    public function test_the_opening_snapshot_cannot_be_deleted(): void
    {
        $session = $this->openSession(100.0);
        $opening = CashMovement::where('cash_register_session_id', $session->id)
            ->where('type', 'deposit')
            ->firstOrFail();

        $this->deleteJson("/api/cash-movements/{$opening->id}")->assertStatus(422);

        $this->assertNotNull($opening->fresh());
        $this->assertEqualsWithDelta(100.0, $this->summary($session)['expected_amount'], 0.001);
    }

    public function test_a_discount_cannot_exceed_the_amount(): void
    {
        $this->openSession(100.0);
        $method = PaymentMethod::factory()->create();

        $this->postJson('/api/transactions', [
            'patient_id' => Patient::factory()->create()->id,
            'payment_method_id' => $method->id,
            'amount' => 100.0,
            'discount_amount' => 150.0,
            'discount_authorized_by' => $this->admin->id,
            'type' => 'payment',
            'description' => 'Cobro con descuento inválido',
        ])->assertStatus(422)->assertJsonValidationErrors('discount_amount');

        $this->expectException(ValidationException::class);
        app(TransactionService::class)->createTransaction([
            'patient_id' => Patient::factory()->create()->id,
            'payment_method_id' => $method->id,
            'amount' => 100.0,
            'discount_amount' => 150.0,
            'discount_authorized_by' => $this->admin->id,
            'type' => 'payment',
            'description' => 'Cobro con descuento inválido',
        ]);
    }
}
