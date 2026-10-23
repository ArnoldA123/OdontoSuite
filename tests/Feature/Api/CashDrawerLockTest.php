<?php

namespace Tests\Feature\Api;

use App\Models\Branch;
use App\Models\CashMovement;
use App\Models\CashRegisterSession;
use App\Models\Patient;
use App\Models\PaymentMethod;
use App\Models\User;
use App\Services\CashRegisterService;
use App\Services\TransactionService;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

/**
 * #323 (S7) — the drawer is one lock for every writer.
 *
 * The deposit already locked the session before reading the cash available. A
 * refund did not, so a refund returning cash and a deposit taking it out could
 * both read the same expected amount. The lock is the serialization: payment,
 * refund, deposit and closure take the same `cash_register_sessions` row before
 * they read or write the drawer.
 *
 * Two processes cannot be run inside one PHPUnit test, so the concurrency is
 * pinned the way the issue allows: the lock is observed on the query log and it
 * must happen before the first write of the drawer.
 *
 * Must run against the MySQL harness (AGENTS.md §6).
 */
#[Group('mysql')]
class CashDrawerLockTest extends TestCase
{
    use RefreshDatabase;

    private const DRAWER_LOCK = '/from `cash_register_sessions`.*for update/i';

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
    }

    /**
     * The SQL of the queries issued by the action, in order.
     *
     * @return list<string>
     */
    private function queriesDuring(callable $action): array
    {
        $statements = [];

        DB::listen(function (QueryExecuted $query) use (&$statements): void {
            $statements[] = $query->sql;
        });

        $action();

        return $statements;
    }

    /**
     * @param list<string> $queries
     */
    private function assertTheDrawerWasLockedBefore(array $queries, string $firstWrite): void
    {
        $lock = null;
        $write = null;

        foreach ($queries as $index => $sql) {
            if ($lock === null && preg_match(self::DRAWER_LOCK, $sql) === 1) {
                $lock = $index;
            }

            if ($write === null && preg_match($firstWrite, $sql) === 1) {
                $write = $index;
            }
        }

        $this->assertNotNull($lock, 'The drawer row was never locked with FOR UPDATE.');
        $this->assertNotNull($write, "The expected write ({$firstWrite}) never happened.");
        $this->assertLessThan($write, $lock, 'The drawer must be locked before the first write.');
    }

    private function payment(float $amount, string $description = 'Cobro de prueba'): \App\Models\Transaction
    {
        return app(TransactionService::class)->createTransaction([
            'patient_id' => $this->patient->id,
            'payment_method_id' => $this->cashMethod->id,
            'type' => 'payment',
            'amount' => $amount,
            'description' => $description,
        ]);
    }

    public function test_a_payment_locks_the_drawer_before_it_writes_the_movement(): void
    {
        $queries = $this->queriesDuring(fn () => $this->payment(50.0));

        $this->assertTheDrawerWasLockedBefore($queries, '/^insert into `cash_movements`/');
        $this->assertSame(1, CashMovement::where('type', 'income')->whereNotNull('transaction_id')->count());
    }

    public function test_a_cash_refund_locks_the_same_drawer_row_before_it_returns_the_money(): void
    {
        $payment = app(TransactionService::class)->createTransaction([
            'patient_id' => $this->patient->id,
            'payment_method_id' => $this->cashMethod->id,
            'type' => 'payment',
            'amount' => 100.0,
            'description' => 'Cobro que se devuelve',
        ]);

        $queries = $this->queriesDuring(fn () => app(TransactionService::class)->createTransaction([
            'patient_id' => $this->patient->id,
            'payment_method_id' => $this->cashMethod->id,
            'type' => 'refund',
            'parent_transaction_id' => $payment->id,
            'amount' => 40.0,
            'description' => 'Devolución parcial',
            'authorized_by' => $this->admin->id,
            'authorization_reason' => 'Paciente no recibió el tratamiento',
        ]));

        $this->assertTheDrawerWasLockedBefore($queries, '/^insert into `cash_movements`/');
    }

    public function test_a_deposit_locks_the_drawer_before_it_writes_the_deposit(): void
    {
        $queries = $this->queriesDuring(fn () => $this->postJson('/api/cash-register/deposits', [
            'cash_register_session_id' => $this->session->id,
            'amount' => 50.0,
            'reference' => 'DEP-0001',
        ])->assertCreated());

        $this->assertTheDrawerWasLockedBefore($queries, '/^insert into `cash_deposits`/');
    }

    public function test_a_void_locks_the_drawer_before_it_writes_the_reversal(): void
    {
        $payment = $this->payment(50.0);

        $queries = $this->queriesDuring(fn () => app(TransactionService::class)->voidTransaction(
            $payment->id,
            'Cobro duplicado',
        ));

        $this->assertTheDrawerWasLockedBefore($queries, '/^insert into `cash_movements`/');
        $this->assertSame('voided', $payment->fresh()->status);
    }

    public function test_closing_the_drawer_locks_the_session_before_it_reads_the_expected_amount(): void
    {
        $queries = $this->queriesDuring(fn () => app(CashRegisterService::class)->closeSession(
            $this->session->id,
            ['closing_amount' => 100.0],
        ));

        $this->assertTheDrawerWasLockedBefore($queries, '/^update `cash_register_sessions`/');
        $this->assertSame('closed', $this->session->fresh()->status);
    }
}
