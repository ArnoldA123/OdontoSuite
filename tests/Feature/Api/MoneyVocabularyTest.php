<?php

namespace Tests\Feature\Api;

use App\Models\Branch;
use App\Models\CashMovement;
use App\Models\CashRegisterSession;
use App\Models\Patient;
use App\Models\PatientCharge;
use App\Models\PaymentMethod;
use App\Models\Transaction;
use App\Models\User;
use App\Services\CashRegisterService;
use App\Services\TransactionService;
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

    /**
     * Debt the patient owes, so a write-off has something to forgive (#287, T3).
     */
    private function charge(Patient $patient, float $amount = 200.0): PatientCharge
    {
        return PatientCharge::create([
            'patient_id' => $patient->id,
            'source' => PatientCharge::SOURCE_MANUAL,
            'description' => 'Cargo de prueba',
            'amount' => $amount,
            'charged_at' => now(),
        ]);
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
        $patient = Patient::factory()->create();
        $this->charge($patient);

        $response = $this->postJson('/api/transactions', $this->payload([
            'patient_id' => $patient->id,
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
        $patient = Patient::factory()->create();
        $this->charge($patient);

        $this->postJson('/api/transactions', $this->payload([
            'patient_id' => $patient->id,
            'payment_method_id' => $this->method()->id,
            'amount' => 50.0,
        ]))->assertCreated();

        $drawerBefore = $this->drawer($session);
        $incomeBefore = $this->dailyIncome();

        $this->postJson('/api/transactions', $this->payload([
            'patient_id' => $patient->id,
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

    public function test_a_write_off_cannot_forgive_more_than_the_patient_owes(): void
    {
        $patient = Patient::factory()->create();
        $this->charge($patient, 40.0);

        $this->postJson('/api/transactions', $this->payload([
            'patient_id' => $patient->id,
            'type' => 'write_off',
            'amount' => 40.5,
            'authorized_by' => $this->admin->id,
            'authorization_reason' => 'Condonación que inventa deuda',
        ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('amount');

        $this->assertSame(0, Transaction::count());
        $this->assertSame(0, CashMovement::count());
    }

    public function test_a_write_off_within_the_outstanding_balance_is_created(): void
    {
        $patient = Patient::factory()->create();
        $this->charge($patient, 200.0);

        $this->postJson('/api/transactions', $this->payload([
            'patient_id' => $patient->id,
            'type' => 'write_off',
            'amount' => 200.0,
            'authorized_by' => $this->admin->id,
            'authorization_reason' => 'Condonación total del saldo',
        ]))->assertCreated();

        $transaction = Transaction::sole();

        $this->assertSame('write_off', $transaction->type);
        $this->assertEqualsWithDelta(200.0, (float) $transaction->amount, 0.001);
        $this->assertSame(0, CashMovement::count());

        // The forgiven debt left the balance at zero, so nothing else is forgivable.
        $this->postJson('/api/transactions', $this->payload([
            'patient_id' => $patient->id,
            'type' => 'write_off',
            'amount' => 1.0,
            'authorized_by' => $this->admin->id,
            'authorization_reason' => 'Condonación sobre un saldo ya saldado',
        ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('amount');

        $this->assertSame(1, Transaction::count());
    }

    public function test_a_refund_parent_must_be_a_completed_payment_of_the_same_patient(): void
    {
        $this->openSession();
        $method = $this->method();
        $payer = Patient::factory()->create();
        $other = Patient::factory()->create();

        $payment = $this->postJson('/api/transactions', $this->payload([
            'patient_id' => $payer->id,
            'payment_method_id' => $method->id,
            'amount' => 100.0,
        ]))->assertCreated()->json('data');

        $this->postJson('/api/transactions', $this->payload([
            'patient_id' => $other->id,
            'type' => 'refund',
            'amount' => 10.0,
            'payment_method_id' => $method->id,
            'parent_transaction_id' => $payment['id'],
            'authorized_by' => $this->admin->id,
            'authorization_reason' => 'Devolución de otro paciente',
        ]))->assertStatus(422)->assertJsonValidationErrors('parent_transaction_id');

        $refund = $this->postJson('/api/transactions', $this->payload([
            'patient_id' => $payer->id,
            'type' => 'refund',
            'amount' => 10.0,
            'payment_method_id' => $method->id,
            'parent_transaction_id' => $payment['id'],
            'authorized_by' => $this->admin->id,
            'authorization_reason' => 'Devolución válida',
        ]))->assertCreated()->json('data');

        $this->postJson('/api/transactions', $this->payload([
            'patient_id' => $payer->id,
            'type' => 'refund',
            'amount' => 5.0,
            'payment_method_id' => $method->id,
            'parent_transaction_id' => $refund['id'],
            'authorized_by' => $this->admin->id,
            'authorization_reason' => 'Devolución de una devolución',
        ]))->assertStatus(422)->assertJsonValidationErrors('parent_transaction_id');

        app(TransactionService::class)->voidTransaction((int) $payment['id'], 'Prueba');

        $this->postJson('/api/transactions', $this->payload([
            'patient_id' => $payer->id,
            'type' => 'refund',
            'amount' => 5.0,
            'payment_method_id' => $method->id,
            'parent_transaction_id' => $payment['id'],
            'authorized_by' => $this->admin->id,
            'authorization_reason' => 'Devolución de un pago anulado',
        ]))->assertStatus(422)->assertJsonValidationErrors('parent_transaction_id');

        $this->assertSame(1, Transaction::where('type', 'refund')->count());
    }

    public function test_a_write_off_authorized_by_a_non_administrator_is_refused(): void
    {
        $receptionist = User::factory()->create(['role' => 'recepcionista', 'is_active' => true]);
        $patient = Patient::factory()->create();
        $this->charge($patient, 100.0);

        $this->postJson('/api/transactions', $this->payload([
            'patient_id' => $patient->id,
            'type' => 'write_off',
            'amount' => 10.0,
            'authorized_by' => $receptionist->id,
            'authorization_reason' => 'Condonación de recepción',
        ]))->assertStatus(422)->assertJsonValidationErrors('authorized_by');

        $this->assertSame(0, Transaction::count());
    }

    public function test_an_adjustment_authorized_by_a_non_administrator_is_refused(): void
    {
        $receptionist = User::factory()->create(['role' => 'recepcionista', 'is_active' => true]);

        $this->postJson('/api/transactions', $this->payload([
            'type' => 'adjustment',
            'amount' => 10.0,
            'authorized_by' => $receptionist->id,
            'authorization_reason' => 'Ajuste de recepción',
        ]))->assertStatus(422)->assertJsonValidationErrors('authorized_by');

        $this->assertSame(0, Transaction::count());
    }

    public function test_a_non_payment_cannot_declare_a_discount(): void
    {
        $this->postJson('/api/transactions', $this->payload([
            'type' => 'adjustment',
            'amount' => 10.0,
            'discount_amount' => 5.0,
            'authorized_by' => $this->admin->id,
            'authorization_reason' => 'Ajuste con descuento',
        ]))->assertStatus(422)->assertJsonValidationErrors('discount_amount');

        $this->assertSame(0, Transaction::count());
    }
}
