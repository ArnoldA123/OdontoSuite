<?php

namespace Tests\Feature\Api;

use App\Models\Branch;
use App\Models\CashDeposit;
use App\Models\CashMovement;
use App\Models\CashRegisterSession;
use App\Models\Patient;
use App\Models\PaymentMethod;
use App\Models\User;
use App\Services\CashRegisterService;
use App\Services\TransactionService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * #287 (T4, S5/S7) — the deposit closes the drawer against the bank.
 *
 * A deposit is money leaving the drawer: it is registered against an open
 * session, it can never exceed the cash the drawer holds, and it writes the
 * cash movement that keeps `expected_amount` true. The list is paginated and
 * scoped to the effective sede, and the daily report reconciles what the drawer
 * held with what reached the bank.
 *
 * Must run against the MySQL harness (AGENTS.md §6).
 */
class CashDepositTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Branch $branch;

    private CashRegisterSession $session;

    private PaymentMethod $cashMethod;

    private Patient $patient;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'role' => 'administrador',
            'is_active' => true,
        ]);
        $this->branch = Branch::factory()->create();

        $this->actingAs($this->admin, 'sanctum');

        $this->cashMethod = PaymentMethod::factory()->create(['name' => 'Efectivo', 'type' => 'cash']);
        $this->patient = Patient::factory()->create();

        $this->session = app(CashRegisterService::class)->openSession([
            'branch_id' => $this->branch->id,
            'opening_amount' => 100.0,
        ]);

        // The drawer holds 200: 100 of opening cash plus a 100 cash sale.
        $this->collect(100.0);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function collect(float $amount): void
    {
        app(TransactionService::class)->createTransaction([
            'patient_id' => $this->patient->id,
            'payment_method_id' => $this->cashMethod->id,
            'amount' => $amount,
            'type' => 'payment',
            'description' => 'Cobro de prueba',
        ]);
    }

    private function available(): float
    {
        return (float) app(CashRegisterService::class)
            ->getSessionSummary($this->session->fresh())['expected_amount'];
    }

    /**
     * @param  array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'cash_register_session_id' => $this->session->id,
            'amount' => 80,
            'reference' => 'BCP-000123',
            'notes' => 'Depósito del día',
        ], $overrides);
    }

    /**
     * @return array<string, mixed>
     */
    private function dailyReport(?int $branchId = null): array
    {
        $query = http_build_query(array_filter(['branch_id' => $branchId]));

        return $this->getJson('/api/cash-reports/daily?date='.now()->toDateString().'&'.$query)
            ->assertOk()
            ->json('data');
    }

    public function test_the_drawer_registers_a_deposit_with_its_withdrawal_movement(): void
    {
        $this->assertEqualsWithDelta(200.0, $this->available(), 0.001);

        $this->postJson('/api/cash-register/deposits', $this->payload())
            ->assertCreated()
            ->assertJsonPath('data.amount', '80.00')
            ->assertJsonPath('data.reference', 'BCP-000123')
            ->assertJsonPath('data.branch_id', $this->branch->id)
            ->assertJsonPath('meta.message', 'Depósito registrado exitosamente');

        $deposit = CashDeposit::sole();

        $this->assertSame($this->session->id, $deposit->cash_register_session_id);
        $this->assertSame($this->branch->id, $deposit->branch_id);
        $this->assertSame($this->admin->id, $deposit->created_by);
        $this->assertNotNull($deposit->cash_movement_id);

        $movement = CashMovement::findOrFail($deposit->cash_movement_id);

        $this->assertSame('withdrawal', $movement->type);
        $this->assertSame('deposit', $movement->concept);
        $this->assertSame('BCP-000123', $movement->reference);
        $this->assertSame($this->session->id, $movement->cash_register_session_id);
        $this->assertSame($this->branch->id, $movement->branch_id);
        $this->assertSame($this->admin->id, $movement->created_by);
        $this->assertEqualsWithDelta(80.0, (float) $movement->amount, 0.001);

        $this->assertEqualsWithDelta(
            120.0,
            $this->available(),
            0.001,
            'The deposit is a withdrawal: the drawer must expect 80 less.'
        );
    }

    public function test_a_deposit_over_the_available_cash_is_refused_and_writes_nothing(): void
    {
        $response = $this->postJson('/api/cash-register/deposits', $this->payload(['amount' => 200.01]));

        $response->assertUnprocessable()->assertJsonValidationErrors('amount');
        $this->assertStringContainsString('200.00', (string) $response->json('errors.amount.0'));

        $this->assertSame(0, CashDeposit::count());
        $this->assertSame(0, CashMovement::where('type', 'withdrawal')->count());
        $this->assertEqualsWithDelta(200.0, $this->available(), 0.001);
    }

    public function test_a_closed_drawer_refuses_a_deposit(): void
    {
        app(CashRegisterService::class)->closeSession($this->session->id, [
            'closing_amount' => 200.0,
        ]);

        $this->postJson('/api/cash-register/deposits', $this->payload())
            ->assertUnprocessable()
            ->assertJsonValidationErrors('cash_register_session_id');

        $this->assertSame(0, CashDeposit::count());
    }

    public function test_a_future_deposit_date_is_refused(): void
    {
        $this->postJson('/api/cash-register/deposits', $this->payload([
            'deposited_at' => now()->addDay()->toDateString(),
        ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('deposited_at');

        $this->assertSame(0, CashDeposit::count());
    }

    public function test_a_clinical_role_cannot_register_a_deposit(): void
    {
        $dentist = User::factory()->create(['role' => 'odontologo', 'is_active' => true]);

        $this->actingAs($dentist, 'sanctum')
            ->postJson('/api/cash-register/deposits', $this->payload())
            ->assertForbidden();

        $this->assertSame(0, CashDeposit::count());
    }

    public function test_the_deposit_list_paginates_and_filters_by_date(): void
    {
        $yesterday = now()->copy()->subDay()->toDateString();

        Carbon::setTestNow(Carbon::parse($yesterday.' 10:00:00'));
        $this->postJson('/api/cash-register/deposits', $this->payload([
            'amount' => 40,
            'reference' => 'BCP-AYER',
        ]))->assertCreated();

        Carbon::setTestNow();

        $this->postJson('/api/cash-register/deposits', $this->payload([
            'amount' => 50,
            'reference' => 'BCP-HOY-1',
        ]))->assertCreated();
        $this->postJson('/api/cash-register/deposits', $this->payload([
            'amount' => 30,
            'reference' => 'BCP-HOY-2',
        ]))->assertCreated();

        $this->getJson('/api/cash-register/deposits?per_page=2')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.total', 3)
            ->assertJsonPath('meta.per_page', 2)
            ->assertJsonPath('meta.current_page', 1)
            ->assertJsonPath('meta.last_page', 2)
            ->assertJsonPath('data.0.reference', 'BCP-HOY-2');

        $this->getJson('/api/cash-register/deposits?date_from='.$yesterday.'&date_to='.$yesterday)
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.reference', 'BCP-AYER');

        $this->getJson('/api/cash-register/deposits?per_page=500')
            ->assertOk()
            ->assertJsonPath('meta.per_page', 100);
    }

    public function test_the_deposit_list_is_scoped_to_the_effective_branch(): void
    {
        $this->postJson('/api/cash-register/deposits', $this->payload())->assertCreated();

        $own = Branch::factory()->create();
        $finance = User::factory()->create([
            'role' => 'finanzas',
            'is_active' => true,
            'branch_id' => $own->id,
        ]);

        $ownSession = CashRegisterSession::factory()->create([
            'user_id' => $finance->id,
            'branch_id' => $own->id,
            'status' => 'open',
            'opened_at' => now(),
        ]);

        $this->actingAs($finance, 'sanctum');

        $this->postJson('/api/cash-register/deposits', [
            'cash_register_session_id' => $ownSession->id,
            'amount' => 20,
            'reference' => 'BCP-PROPIA',
        ])->assertCreated();

        $this->getJson('/api/cash-register/deposits')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.reference', 'BCP-PROPIA');

        $this->getJson('/api/cash-register/deposits?branch_id='.$this->branch->id)
            ->assertForbidden();
    }

    public function test_a_restricted_user_cannot_deposit_into_another_branch(): void
    {
        $own = Branch::factory()->create();
        $finance = User::factory()->create([
            'role' => 'finanzas',
            'is_active' => true,
            'branch_id' => $own->id,
        ]);

        $this->actingAs($finance, 'sanctum')
            ->postJson('/api/cash-register/deposits', $this->payload())
            ->assertForbidden();

        $this->assertSame(0, CashDeposit::count());
    }

    public function test_the_daily_report_reconciles_the_deposits_with_the_drawer_cash(): void
    {
        $this->postJson('/api/cash-register/deposits', $this->payload(['amount' => 60]))->assertCreated();

        $otherBranch = Branch::factory()->create();
        $otherSession = CashRegisterSession::factory()->create([
            'user_id' => $this->admin->id,
            'branch_id' => $otherBranch->id,
            'status' => 'open',
            'opened_at' => now(),
        ]);

        CashDeposit::create([
            'branch_id' => $otherBranch->id,
            'cash_register_session_id' => $otherSession->id,
            'created_by' => $this->admin->id,
            'amount' => 25,
            'deposited_at' => now(),
            'reference' => 'BCP-OTRA-SEDE',
        ]);

        $reconciliation = $this->dailyReport($this->branch->id)['reconciliation'];

        $this->assertEqualsWithDelta(100.0, $reconciliation['cash']['total'], 0.001);
        $this->assertSame(1, $reconciliation['deposits']['count']);
        $this->assertEqualsWithDelta(60.0, $reconciliation['deposits']['total'], 0.001);
        $this->assertEqualsWithDelta(40.0, $reconciliation['pending_deposit'], 0.001);

        $wholeGroup = $this->dailyReport()['reconciliation'];

        $this->assertSame(2, $wholeGroup['deposits']['count']);
        $this->assertEqualsWithDelta(85.0, $wholeGroup['deposits']['total'], 0.001);
    }

    public function test_a_deposit_of_earlier_cash_turns_the_pending_deposit_negative(): void
    {
        $this->postJson('/api/cash-register/deposits', $this->payload(['amount' => 150]))->assertCreated();

        $reconciliation = $this->dailyReport()['reconciliation'];

        $this->assertEqualsWithDelta(100.0, $reconciliation['cash']['total'], 0.001);
        $this->assertEqualsWithDelta(150.0, $reconciliation['deposits']['total'], 0.001);
        $this->assertEqualsWithDelta(
            -50.0,
            $reconciliation['pending_deposit'],
            0.001,
            'A deposit larger than the sales of the day carried cash from earlier days.'
        );
    }

    public function test_a_card_sale_is_collected_but_never_enters_the_drawer(): void
    {
        $card = PaymentMethod::factory()->create([
            'name' => 'Tarjeta',
            'type' => PaymentMethod::TYPE_CARD,
            'requires_reference' => false,
        ]);

        $transaction = app(TransactionService::class)->createTransaction([
            'patient_id' => $this->patient->id,
            'payment_method_id' => $card->id,
            'amount' => 120.0,
            'type' => 'payment',
            'description' => 'Cobro con tarjeta',
        ]);

        $this->assertSame(
            0,
            CashMovement::where('transaction_id', $transaction->id)->count(),
            'A card sale is collected for the patient but it never enters the drawer.'
        );
        $this->assertEqualsWithDelta(200.0, $this->available(), 0.001);

        $summary = app(CashRegisterService::class)->getSessionSummary($this->session->fresh());
        $this->assertEqualsWithDelta(220.0, $summary['total_income'], 0.001);
        $this->assertEqualsWithDelta(100.0, $summary['cash_in'], 0.001);

        $this->postJson('/api/cash-register/deposits', $this->payload(['amount' => 200.01]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('amount');

        $this->postJson('/api/cash-register/deposits', $this->payload(['amount' => 200]))->assertCreated();
    }

    public function test_voiding_a_card_sale_does_not_move_the_drawer(): void
    {
        $card = PaymentMethod::factory()->create([
            'name' => 'Tarjeta',
            'type' => PaymentMethod::TYPE_CARD,
            'requires_reference' => false,
        ]);

        $transaction = app(TransactionService::class)->createTransaction([
            'patient_id' => $this->patient->id,
            'payment_method_id' => $card->id,
            'amount' => 120.0,
            'type' => 'payment',
            'description' => 'Cobro con tarjeta',
        ]);

        app(TransactionService::class)->voidTransaction($transaction->id, 'Prueba');

        $summary = app(CashRegisterService::class)->getSessionSummary($this->session->fresh());

        $this->assertEqualsWithDelta(100.0, $summary['total_income'], 0.001);
        $this->assertEqualsWithDelta(100.0, $summary['cash_in'], 0.001);
        $this->assertEqualsWithDelta(0.0, $summary['cash_out'], 0.001);
        $this->assertEqualsWithDelta(200.0, $summary['expected_amount'], 0.001);
    }

    public function test_a_card_refund_does_not_move_the_drawer_either(): void
    {
        $card = PaymentMethod::factory()->create([
            'name' => 'Tarjeta',
            'type' => PaymentMethod::TYPE_CARD,
            'requires_reference' => false,
        ]);

        $refund = app(TransactionService::class)->createTransaction([
            'patient_id' => $this->patient->id,
            'payment_method_id' => $card->id,
            'amount' => 40.0,
            'type' => 'refund',
            'description' => 'Devolución con tarjeta',
            'authorized_by' => $this->admin->id,
            'authorization_reason' => 'Devolución de una venta con tarjeta',
        ]);

        $this->assertSame(
            0,
            CashMovement::where('transaction_id', $refund->id)->count(),
            'A card refund is money returned to the card, not cash leaving the drawer.'
        );

        $summary = app(CashRegisterService::class)->getSessionSummary($this->session->fresh());

        $this->assertEqualsWithDelta(0.0, $summary['cash_out'], 0.001);
        $this->assertEqualsWithDelta(200.0, $summary['expected_amount'], 0.001);
    }
}
