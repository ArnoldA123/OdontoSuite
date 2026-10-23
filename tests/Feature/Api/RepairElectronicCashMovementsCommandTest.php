<?php

namespace Tests\Feature\Api;

use App\Console\Commands\RepairElectronicCashMovements;
use App\Models\AuditLog;
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
 * #322 (T2, S5/S6) — `cash:repair-electronic-movements`.
 *
 * Before ADR-0015 every payment wrote a drawer row, so the sessions closed with
 * the old rule keep electronic money inside their cash flow. The repair marks
 * those rows — audit evidence plus soft delete — and leaves the session alone:
 * its stored `expected_amount` and `arqueo_data` stay as the historical record
 * while the live expected amount drops the electronic money.
 *
 * The session carries a card and a cash sale; the movement whose method cannot
 * be proven lives in its own session, because it keeps counting as a drawer flow
 * and would blur the arithmetic of the repaired one.
 *
 * Every fixture is written straight to the models, because `TransactionService`
 * no longer writes a movement for a method that does not move the drawer.
 *
 * Must run against the MySQL harness (AGENTS.md §6).
 */
#[Group('mysql')]
class RepairElectronicCashMovementsCommandTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Branch $branch;

    private CashRegisterSession $session;

    private CashRegisterSession $unknownMethodSession;

    private Transaction $cardTransaction;

    private CashMovement $cardMovement;

    private CashMovement $cashMovement;

    private CashMovement $unknownMethodMovement;

    private CashMovement $openingMovement;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'role' => 'administrador',
            'is_active' => true,
        ]);
        $this->branch = Branch::factory()->create();

        $this->session = $this->closedSession(100.0, 170.0, [
            'denominations' => ['100' => 1, '50' => 1, '20' => 1],
        ]);
        $this->openingMovement = $this->opening($this->session, 100.0);

        $this->unknownMethodSession = $this->closedSession(50.0, 65.0);
        $this->opening($this->unknownMethodSession, 50.0);

        $card = PaymentMethod::factory()->create([
            'name' => 'Tarjeta de crédito',
            'type' => PaymentMethod::TYPE_CARD,
        ]);
        $cash = PaymentMethod::factory()->create([
            'name' => 'Efectivo',
            'type' => PaymentMethod::TYPE_CASH,
        ]);
        $unknown = PaymentMethod::factory()->create([
            'name' => 'Cheque',
            'type' => 'cheque',
        ]);

        $this->cardTransaction = $this->payment($this->session, $card, 40.0, 'TXN-20261022-0001');
        $this->cardMovement = $this->income($this->cardTransaction);

        $this->cashMovement = $this->income($this->payment($this->session, $cash, 30.0, 'TXN-20261022-0002'));

        $this->unknownMethodMovement = $this->income(
            $this->payment($this->unknownMethodSession, $unknown, 15.0, 'TXN-20261022-0003')
        );
    }

    private function closedSession(float $opening, float $expected, ?array $arqueo = null): CashRegisterSession
    {
        return CashRegisterSession::create([
            'user_id' => $this->admin->id,
            'branch_id' => $this->branch->id,
            'opening_amount' => $opening,
            'closing_amount' => $expected,
            'expected_amount' => $expected,
            'difference_amount' => 0.0,
            'opened_at' => now()->subHours(6),
            'closed_at' => now()->subHour(),
            'status' => 'closed',
            'arqueo_data' => $arqueo,
        ]);
    }

    private function opening(CashRegisterSession $session, float $amount): CashMovement
    {
        return CashMovement::create([
            'cash_register_session_id' => $session->id,
            'created_by' => $this->admin->id,
            'type' => 'deposit',
            'amount' => $amount,
            'description' => 'Apertura de caja',
            'reference' => CashMovement::openingReference($session->id),
        ]);
    }

    private function payment(CashRegisterSession $session, PaymentMethod $method, float $amount, string $number): Transaction
    {
        return Transaction::create([
            'patient_id' => Patient::factory()->create()->id,
            'payment_method_id' => $method->id,
            'created_by' => $this->admin->id,
            'cash_register_session_id' => $session->id,
            'transaction_number' => $number,
            'type' => 'payment',
            'amount' => $amount,
            'description' => 'Cobro de prueba',
            'status' => 'completed',
            'processed_at' => now()->subHours(2),
        ]);
    }

    private function income(Transaction $transaction): CashMovement
    {
        return CashMovement::create([
            'cash_register_session_id' => $transaction->cash_register_session_id,
            'transaction_id' => $transaction->id,
            'created_by' => $this->admin->id,
            'type' => 'income',
            'amount' => $transaction->amount,
            'description' => $transaction->description,
            'reference' => $transaction->transaction_number,
        ]);
    }

    private function assertSessionColumnsUntouched(): void
    {
        $this->session->refresh();

        $this->assertSame('170.00', $this->session->expected_amount);
        $this->assertSame('170.00', $this->session->closing_amount);
        $this->assertSame('0.00', $this->session->difference_amount);
        $this->assertSame('closed', $this->session->status);
        $this->assertSame(
            ['denominations' => ['100' => 1, '50' => 1, '20' => 1]],
            $this->session->arqueo_data,
        );
    }

    public function test_it_marks_the_electronic_movement_and_keeps_the_rest(): void
    {
        $this->artisan('cash:repair-electronic-movements')
            ->expectsOutput('Movimientos con transacción revisados: 3')
            ->expectsOutput('Movimientos electrónicos reparados: 1')
            ->expectsOutput('Movimientos de efectivo conservados: 1')
            ->expectsOutput('Movimientos sin método comprobable: 1')
            ->expectsOutput('Idempotente: una segunda corrida repara 0 movimientos.')
            ->assertSuccessful();

        $repaired = CashMovement::withTrashed()->findOrFail($this->cardMovement->id);

        $this->assertNotNull($repaired->deleted_at, 'El movimiento electrónico queda marcado con el soft delete.');
        $this->assertSame('income', $repaired->type);
        $this->assertSame('40.00', $repaired->amount);
        $this->assertSame($this->cardTransaction->transaction_number, $repaired->reference);
        $this->assertSame($this->session->id, $repaired->cash_register_session_id);
        $this->assertSame($this->cardTransaction->id, $repaired->transaction_id);

        $this->assertNull(CashMovement::withTrashed()->findOrFail($this->cashMovement->id)->deleted_at);
        $this->assertNull(CashMovement::withTrashed()->findOrFail($this->unknownMethodMovement->id)->deleted_at);
        $this->assertNull(CashMovement::withTrashed()->findOrFail($this->openingMovement->id)->deleted_at);

        $this->assertSame(5, CashMovement::withTrashed()->count(), 'La fila sobrevive: la reparación no borra en duro.');
        $this->assertSame(4, CashMovement::count());

        $this->assertSessionColumnsUntouched();
    }

    public function test_it_writes_one_audit_row_per_repaired_movement(): void
    {
        $this->artisan('cash:repair-electronic-movements')->assertSuccessful();

        $audit = AuditLog::where('action', RepairElectronicCashMovements::ACTION_REPAIRED)->sole();

        $this->assertNull($audit->user_id, 'No hay actor autenticado en consola.');
        $this->assertSame(CashMovement::class, $audit->auditable_type);
        $this->assertSame($this->cardMovement->id, $audit->auditable_id);
        $this->assertSame('cash:repair-electronic-movements', $audit->metadata['command']);
        $this->assertSame(RepairElectronicCashMovements::REASON, $audit->metadata['reason']);
        $this->assertSame($this->session->id, $audit->metadata['cash_register_session_id']);
        $this->assertSame($this->cardTransaction->id, $audit->metadata['transaction_id']);
        $this->assertSame(PaymentMethod::TYPE_CARD, $audit->metadata['payment_method_type']);
        $this->assertSame('income', $audit->metadata['movement_type']);
        $this->assertEqualsWithDelta(40.0, $audit->metadata['amount'], 0.001);

        $this->assertSame(1, AuditLog::count());
    }

    public function test_the_live_expected_amount_drops_the_repaired_row_while_the_session_keeps_its_arqueo(): void
    {
        $service = app(CashRegisterService::class);

        $this->assertEqualsWithDelta(
            170.0,
            $service->getSessionSummary($this->session->fresh())['expected_amount'],
            0.001,
            'La gaveta histórica contaba el cobro con tarjeta.'
        );

        $this->artisan('cash:repair-electronic-movements')->assertSuccessful();

        $summary = $service->getSessionSummary($this->session->fresh());

        $this->assertEqualsWithDelta(30.0, $summary['cash_in'], 0.001);
        $this->assertEqualsWithDelta(130.0, $summary['expected_amount'], 0.001);

        $unknownSummary = $service->getSessionSummary($this->unknownMethodSession->fresh());

        $this->assertEqualsWithDelta(
            65.0,
            $unknownSummary['expected_amount'],
            0.001,
            'Un método que no se puede comprobar sigue contando en la gaveta.'
        );

        $this->assertSessionColumnsUntouched();
    }

    public function test_a_second_run_repairs_nothing(): void
    {
        $this->artisan('cash:repair-electronic-movements')
            ->expectsOutput('Movimientos electrónicos reparados: 1')
            ->assertSuccessful();

        $this->artisan('cash:repair-electronic-movements')
            ->expectsOutput('Movimientos con transacción revisados: 2')
            ->expectsOutput('Movimientos electrónicos reparados: 0')
            ->expectsOutput('Movimientos de efectivo conservados: 1')
            ->expectsOutput('Movimientos sin método comprobable: 1')
            ->expectsOutput('Idempotente: una segunda corrida repara 0 movimientos.')
            ->assertSuccessful();

        $this->assertSame(1, AuditLog::where('action', RepairElectronicCashMovements::ACTION_REPAIRED)->count());
        $this->assertSame(4, CashMovement::count());
    }

    public function test_the_movement_without_a_comprobable_method_is_kept_and_counted(): void
    {
        $this->artisan('cash:repair-electronic-movements')
            ->expectsOutput('Movimientos sin método comprobable: 1')
            ->assertSuccessful();

        $kept = CashMovement::findOrFail($this->unknownMethodMovement->id);

        $this->assertSame('income', $kept->type);
        $this->assertSame('15.00', $kept->amount);
        $this->assertSame(
            0,
            AuditLog::where('auditable_id', $kept->id)
                ->where('auditable_type', CashMovement::class)
                ->count(),
            'Un método que no se puede comprobar no se repara a ciegas.'
        );
    }

    public function test_a_dry_run_reports_the_candidate_and_writes_nothing(): void
    {
        $this->artisan('cash:repair-electronic-movements', ['--dry-run' => true])
            ->expectsOutput('Movimientos con transacción revisados: 3')
            ->expectsOutput('Movimientos electrónicos que se repararían: 1')
            ->expectsOutput('Movimientos de efectivo conservados: 1')
            ->expectsOutput('Movimientos sin método comprobable: 1')
            ->expectsOutput('Idempotente: una segunda corrida repara 0 movimientos.')
            ->expectsOutput('dry-run: no se escribió nada.')
            ->assertSuccessful();

        $this->assertNull(CashMovement::withTrashed()->findOrFail($this->cardMovement->id)->deleted_at);
        $this->assertSame(5, CashMovement::withTrashed()->count());
        $this->assertSame(0, AuditLog::count());
        $this->assertSessionColumnsUntouched();
    }
}
