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
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

/**
 * #287, D3/D4/D8 — the money vocabulary separates the drawer from the ledger.
 *
 * A payment and its refund move cash and need an open drawer; a write-off and
 * an adjustment are ledger entries: no method, no drawer, no cash movement. A
 * discount is a field of the charge, so it is no longer a writable type. The
 * refund is capped by what the parent payment left and by what the patient has
 * actually paid.
 *
 * Must run against the MySQL harness (AGENTS.md §6).
 */
#[Group('mysql')]
class MoneyVocabularyTest extends TestCase
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

    private function openSession(?User $owner = null, float $opening = 100.0): CashRegisterSession
    {
        $owner ??= $this->admin;

        if ($owner->isNot($this->admin)) {
            $this->actingAs($owner, 'sanctum');
        }

        $session = app(CashRegisterService::class)->openSession([
            'branch_id' => $this->branch->id,
            'opening_amount' => $opening,
        ]);

        $this->actingAs($this->admin, 'sanctum');

        return $session;
    }

    private function method(array $attributes = []): PaymentMethod
    {
        return PaymentMethod::factory()->create($attributes);
    }

    /** @param array<string, mixed> $extra */
    private function payload(array $extra = []): array
    {
        return array_merge([
            'patient_id' => Patient::factory()->create()->id,
            'amount' => 100.0,
            'type' => 'payment',
            'description' => 'Movimiento de prueba',
        ], $extra);
    }

    private function drawer(CashRegisterSession $session): float
    {
        return (float) app(CashRegisterService::class)
            ->getSessionSummary($session->fresh())['expected_amount'];
    }

    private function dailyIncome(): float
    {
        $report = $this->getJson('/api/cash-reports/daily')->assertOk()->json('data');

        return round((float) collect($report['summary_by_payment_method'])->sum('total'), 2);
    }

    public function test_a_payment_still_needs_an_open_drawer(): void
    {
        $this->postJson('/api/transactions', $this->payload([
            'payment_method_id' => $this->method()->id,
        ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('session');

        $this->assertSame(0, Transaction::count());
    }

    public function test_a_write_off_is_created_without_a_drawer_and_without_a_method(): void
    {
        $response = $this->postJson('/api/transactions', $this->payload([
            'type' => 'write_off',
            'authorized_by' => $this->admin->id,
            'authorization_reason' => 'Paciente sin recursos',
        ]));

        $response->assertCreated();

        $transaction = Transaction::sole();

        $this->assertSame('write_off', $transaction->type);
        $this->assertNull($transaction->payment_method_id);
        $this->assertNull($transaction->cash_register_session_id);
        $this->assertSame($this->admin->id, (int) $transaction->authorized_by);
        $this->assertSame('Paciente sin recursos', $transaction->authorization_reason);
        $this->assertSame(0, CashMovement::count(), 'A write-off never writes a cash movement.');
    }

    public function test_a_write_off_adds_no_daily_income_and_never_touches_the_drawer(): void
    {
        $session = $this->openSession();

        $this->postJson('/api/transactions', $this->payload([
            'payment_method_id' => $this->method()->id,
            'amount' => 50.0,
        ]))->assertCreated();

        $drawerBefore = $this->drawer($session);
        $incomeBefore = $this->dailyIncome();

        $this->postJson('/api/transactions', $this->payload([
            'type' => 'write_off',
            'amount' => 30.0,
            'authorized_by' => $this->admin->id,
            'authorization_reason' => 'Condonación aprobada',
        ]))->assertCreated();

        $this->assertEqualsWithDelta(150.0, $drawerBefore, 0.001);
        $this->assertEqualsWithDelta($drawerBefore, $this->drawer($session), 0.001);
        $this->assertEqualsWithDelta(50.0, $incomeBefore, 0.001);
        $this->assertEqualsWithDelta($incomeBefore, $this->dailyIncome(), 0.001);
    }

    public function test_an_adjustment_keeps_its_sign_and_moves_no_cash(): void
    {
        $this->postJson('/api/transactions', $this->payload([
            'type' => 'adjustment',
            'amount' => -40.5,
            'authorized_by' => $this->admin->id,
            'authorization_reason' => 'Corrección de saldo',
        ]))->assertCreated();

        $this->postJson('/api/transactions', $this->payload([
            'type' => 'adjustment',
            'amount' => 25.0,
            'authorized_by' => $this->admin->id,
            'authorization_reason' => 'Interés por mora',
        ]))->assertCreated();

        $amounts = Transaction::orderBy('id')->pluck('amount')->map(fn ($amount) => (float) $amount);

        $this->assertEqualsWithDelta(-40.5, $amounts[0], 0.001);
        $this->assertEqualsWithDelta(25.0, $amounts[1], 0.001);
        $this->assertSame(0, CashMovement::count(), 'An adjustment is a ledger entry, not cash.');
    }

    public function test_an_adjustment_of_exactly_zero_is_refused(): void
    {
        $this->postJson('/api/transactions', $this->payload([
            'type' => 'adjustment',
            'amount' => 0,
            'authorized_by' => $this->admin->id,
            'authorization_reason' => 'Ajuste vacío',
        ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('amount');

        $this->assertSame(0, Transaction::count());
    }

    public function test_discount_is_no_longer_a_transaction_type(): void
    {
        $this->postJson('/api/transactions', $this->payload([
            'type' => 'discount',
            'amount' => 10.0,
            'payment_method_id' => $this->method()->id,
        ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('type');

        $this->assertSame(0, Transaction::count());
    }

    public function test_a_refund_without_an_administrator_authorization_is_refused(): void
    {
        $receptionist = User::factory()->create([
            'role' => 'recepcionista',
            'is_active' => true,
        ]);

        $session = $this->openSession($receptionist);

        $this->actingAs($receptionist, 'sanctum')
            ->postJson('/api/transactions', $this->payload([
                'type' => 'refund',
                'amount' => 20.0,
                'payment_method_id' => $this->method()->id,
                'authorization_reason' => 'Devolución pedida por el paciente',
            ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('authorized_by');

        $this->assertSame(0, Transaction::count());
        $this->assertEqualsWithDelta(100.0, $this->drawer($session), 0.001);
    }

    public function test_a_refund_authorized_by_a_non_administrator_is_refused(): void
    {
        $this->openSession();
        $finanzas = User::factory()->create(['role' => 'finanzas', 'is_active' => true]);

        $this->postJson('/api/transactions', $this->payload([
            'type' => 'refund',
            'amount' => 20.0,
            'payment_method_id' => $this->method()->id,
            'authorized_by' => $finanzas->id,
            'authorization_reason' => 'Autorización de finanzas',
        ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('authorized_by');

        $this->assertSame(0, Transaction::count());
    }

    public function test_a_refund_authorized_by_an_administrator_moves_the_cash_out(): void
    {
        $session = $this->openSession();
        $patient = Patient::factory()->create();

        $this->postJson('/api/transactions', $this->payload([
            'patient_id' => $patient->id,
            'payment_method_id' => $this->method()->id,
            'amount' => 80.0,
        ]))->assertCreated();

        $this->postJson('/api/transactions', $this->payload([
            'patient_id' => $patient->id,
            'type' => 'refund',
            'amount' => 30.0,
            'payment_method_id' => $this->method()->id,
            'authorized_by' => $this->admin->id,
            'authorization_reason' => 'Devolución aprobada por gerencia',
        ]))->assertCreated();

        $refund = Transaction::where('type', 'refund')->sole();
        $movement = CashMovement::where('transaction_id', $refund->id)->sole();

        $this->assertSame('expense', $movement->type);
        $this->assertEqualsWithDelta(30.0, (float) $movement->amount, 0.001);
        $this->assertEqualsWithDelta(150.0, $this->drawer($session), 0.001);
    }

    public function test_a_refund_cannot_exceed_what_its_parent_payment_left(): void
    {
        $this->openSession(null, 0.0);
        $method = $this->method();
        $patient = Patient::factory()->create();

        $parent = $this->postJson('/api/transactions', $this->payload([
            'patient_id' => $patient->id,
            'payment_method_id' => $method->id,
            'amount' => 100.0,
        ]))->assertCreated()->json('data');

        // A second payment gives the patient room globally, so only the parent
        // cap can refuse the third refund.
        $this->postJson('/api/transactions', $this->payload([
            'patient_id' => $patient->id,
            'payment_method_id' => $method->id,
            'amount' => 100.0,
        ]))->assertCreated();

        $this->postJson('/api/transactions', $this->payload([
            'patient_id' => $patient->id,
            'type' => 'refund',
            'amount' => 80.0,
            'payment_method_id' => $method->id,
            'parent_transaction_id' => $parent['id'],
            'authorized_by' => $this->admin->id,
            'authorization_reason' => 'Devolución parcial',
        ]))->assertCreated();

        $this->postJson('/api/transactions', $this->payload([
            'patient_id' => $patient->id,
            'type' => 'refund',
            'amount' => 30.0,
            'payment_method_id' => $method->id,
            'parent_transaction_id' => $parent['id'],
            'authorized_by' => $this->admin->id,
            'authorization_reason' => 'Segunda devolución sobre el mismo pago',
        ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('amount');

        $this->assertSame(1, Transaction::where('type', 'refund')->count());
    }

    public function test_a_refund_cannot_exceed_the_money_collected_for_the_patient(): void
    {
        $this->openSession(null, 0.0);
        $method = $this->method();
        $patient = Patient::factory()->create();

        $this->postJson('/api/transactions', $this->payload([
            'patient_id' => $patient->id,
            'payment_method_id' => $method->id,
            'amount' => 100.0,
        ]))->assertCreated();

        $this->postJson('/api/transactions', $this->payload([
            'patient_id' => $patient->id,
            'type' => 'refund',
            'amount' => 100.0,
            'payment_method_id' => $method->id,
            'authorized_by' => $this->admin->id,
            'authorization_reason' => 'Devolución total',
        ]))->assertCreated();

        $this->postJson('/api/transactions', $this->payload([
            'patient_id' => $patient->id,
            'type' => 'refund',
            'amount' => 1.0,
            'payment_method_id' => $method->id,
            'authorized_by' => $this->admin->id,
            'authorization_reason' => 'Devolución sin dinero cobrado',
        ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('amount');

        $this->assertSame(1, Transaction::where('type', 'refund')->count());
    }

    public function test_a_write_off_cannot_declare_a_payment_method(): void
    {
        $this->postJson('/api/transactions', $this->payload([
            'type' => 'write_off',
            'payment_method_id' => $this->method()->id,
            'authorized_by' => $this->admin->id,
            'authorization_reason' => 'Condonación aprobada',
        ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('payment_method_id');

        $this->assertSame(0, Transaction::count());
    }
}
