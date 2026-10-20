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
use Tests\TestCase;

/**
 * Contract guard for the cash ledger (2026-10, caja interface redesign).
 *
 * The movement ledger is the only source of physical cash flows:
 *
 *   cash_in  = income + deposit rows
 *   cash_out = expense + withdrawal rows
 *   expected = opening_amount + cash_in - cash_out
 *
 * The opening row (`OPEN-…`) and the closing row (`CLOSE-…`) that
 * CashRegisterService writes are snapshots of the drawer, not flows, so they
 * must never enter the equation. `adjustment` rows stay out of it as well:
 * the closing snapshot is the only writer today and a manual adjustment has no
 * defined sign.
 *
 * Sales keep their own pair (`total_income`/`total_expenses`, completed
 * transactions only) so a deposit does not inflate the sales KPI.
 *
 * Must run against the MySQL harness (AGENTS.md §6).
 */
class CashRegisterSummaryTest extends TestCase
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

    private function payment(float $amount, ?PaymentMethod $method = null): Transaction
    {
        return app(TransactionService::class)->createTransaction([
            'patient_id' => Patient::factory()->create()->id,
            'payment_method_id' => ($method ?? PaymentMethod::factory()->create())->id,
            'amount' => $amount,
            'type' => 'payment',
            'description' => 'Cobro de prueba',
        ]);
    }

    private function manualMovement(
        CashRegisterSession $session,
        string $type,
        string $concept,
        float $amount
    ): CashMovement {
        return CashMovement::create([
            'cash_register_session_id' => $session->id,
            'created_by' => $this->admin->id,
            'type' => $type,
            'concept' => $concept,
            'amount' => $amount,
            'description' => 'Movimiento manual de prueba',
        ]);
    }

    /** @return array<string, mixed> */
    private function summary(CashRegisterSession $session): array
    {
        return app(CashRegisterService::class)->getSessionSummary($session->fresh());
    }

    public function test_cash_flow_totals_read_the_movement_ledger_once(): void
    {
        $session = $this->openSession(100.0);
        $this->payment(50.0);
        $this->manualMovement($session, 'deposit', 'deposit', 20.0);
        $this->manualMovement($session, 'income', 'sale_refund', 5.0);
        $this->manualMovement($session, 'withdrawal', 'withdrawal', 15.0);

        $summary = $this->summary($session);

        $this->assertEqualsWithDelta(50.0, $summary['total_income'], 0.001);
        $this->assertEqualsWithDelta(0.0, $summary['total_expenses'], 0.001);
        $this->assertEqualsWithDelta(75.0, $summary['cash_in'], 0.001);
        $this->assertEqualsWithDelta(15.0, $summary['cash_out'], 0.001);
        $this->assertEqualsWithDelta(60.0, $summary['net_flow'], 0.001);
        $this->assertEqualsWithDelta(160.0, $summary['expected_amount'], 0.001);

        $this->assertArrayNotHasKey(
            'total_movements',
            $summary,
            'total_movements summed the opening and closing snapshots; it must not survive.'
        );
    }

    public function test_the_opening_and_closing_snapshots_never_enter_the_equation(): void
    {
        $session = $this->openSession(100.0);
        $this->manualMovement($session, 'deposit', 'deposit', 20.0);

        app(CashRegisterService::class)->closeSession($session->id, [
            'closing_amount' => 120.0,
        ]);

        $summary = $this->summary($session);

        $this->assertEqualsWithDelta(20.0, $summary['cash_in'], 0.001);
        $this->assertEqualsWithDelta(0.0, $summary['cash_out'], 0.001);
        $this->assertEqualsWithDelta(120.0, $summary['expected_amount'], 0.001);
        $this->assertEqualsWithDelta(
            0.0,
            (float) $session->fresh()->difference_amount,
            0.001,
            'The arqueo difference must compare the count against the same ledger position.'
        );
    }

    public function test_a_voided_payment_reverses_the_cash_flow_but_not_the_sales_total(): void
    {
        $session = $this->openSession(100.0);
        $transaction = $this->payment(50.0);

        app(TransactionService::class)->voidTransaction($transaction->id, 'Prueba');

        $summary = $this->summary($session);

        $this->assertEqualsWithDelta(
            0.0,
            $summary['total_income'],
            0.001,
            'A voided transaction is not a sale.'
        );
        $this->assertEqualsWithDelta(50.0, $summary['cash_in'], 0.001);
        $this->assertEqualsWithDelta(50.0, $summary['cash_out'], 0.001);
        $this->assertEqualsWithDelta(100.0, $summary['expected_amount'], 0.001);
    }

    public function test_payment_methods_and_hours_are_labelled_lists(): void
    {
        $session = $this->openSession(100.0);
        $cash = PaymentMethod::factory()->create(['name' => 'Efectivo']);
        $this->payment(50.0, $cash);

        $summary = $this->summary($session);

        $this->assertTrue(array_is_list($summary['by_payment_method']));
        $this->assertSame('Efectivo', $summary['by_payment_method'][0]['payment_method']);
        $this->assertSame(1, $summary['by_payment_method'][0]['count']);
        $this->assertEqualsWithDelta(50.0, $summary['by_payment_method'][0]['total'], 0.001);

        $this->assertTrue(array_is_list($summary['by_hour']));
        $this->assertSame(now()->format('H'), $summary['by_hour'][0]['hour']);
        $this->assertSame(1, $summary['by_hour'][0]['count']);
        $this->assertEqualsWithDelta(50.0, $summary['by_hour'][0]['total'], 0.001);
    }

    public function test_the_current_session_payload_exposes_the_cash_flow_keys(): void
    {
        $this->openSession(100.0);
        $this->payment(50.0);

        $this->getJson('/api/cash-register/current')
            ->assertOk()
            ->assertJsonPath('data.summary.cash_in', 50)
            ->assertJsonPath('data.summary.cash_out', 0)
            ->assertJsonPath('data.summary.expected_amount', 150)
            ->assertJsonMissingPath('data.summary.total_movements');
    }
}
