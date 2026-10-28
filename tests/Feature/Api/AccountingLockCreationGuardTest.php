<?php

namespace Tests\Feature\Api;

use App\Models\AccountingLock;
use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\CashMovement;
use App\Models\CashRegisterSession;
use App\Models\Patient;
use App\Models\PaymentMethod;
use App\Models\Transaction;
use App\Models\User;
use App\Services\AccountingLockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * #410 (S8/S10) — the accounting freeze also covers money created today.
 *
 * The guard used to run only on the mutation of an existing row, so a payment
 * or a manual cash movement booked while the module was frozen through today
 * was written as if the lock did not exist. Both creation doors guard before
 * the write, on the branch of the drawer, and the override keeps its contract:
 * administrator only, at least ten characters, audited as
 * `accounting_lock_overridden`; a blocked attempt writes nothing and leaves one
 * `accounting_lock_blocked` entry.
 *
 * Must run against the MySQL harness (AGENTS.md §6).
 */
class AccountingLockCreationGuardTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;

    private Patient $patient;

    private PaymentMethod $cashMethod;

    protected function setUp(): void
    {
        parent::setUp();

        $this->branch = Branch::factory()->create();
        $this->patient = Patient::factory()->create(['branch_id' => $this->branch->id]);
        $this->cashMethod = PaymentMethod::factory()->create([
            'name' => 'Efectivo',
            'type' => PaymentMethod::TYPE_CASH,
            'requires_reference' => false,
        ]);
    }

    private function actor(string $role): User
    {
        $user = User::factory()->create([
            'role' => $role,
            'is_active' => true,
            'branch_id' => null,
        ]);

        $this->actingAs($user, 'sanctum');

        return $user;
    }

    private function openSessionFor(User $owner): CashRegisterSession
    {
        return CashRegisterSession::factory()->create([
            'user_id' => $owner->id,
            'branch_id' => $this->branch->id,
            'status' => 'open',
            'opening_amount' => 100.0,
        ]);
    }

    private function lockThrough(string $module, \DateTimeInterface $date, ?User $by = null): AccountingLock
    {
        return AccountingLock::create([
            'module' => $module,
            'branch_id' => $this->branch->id,
            'locked_through' => $date->format('Y-m-d'),
            'set_by' => $by?->id,
        ]);
    }

    /**
     * @param array<string, mixed> $extra
     */
    private function charge(array $extra = []): TestResponse
    {
        return $this->postJson('/api/transactions', array_merge([
            'patient_id' => $this->patient->id,
            'payment_method_id' => $this->cashMethod->id,
            'amount' => 50.0,
            'type' => 'payment',
            'description' => 'Cobro de prueba',
        ], $extra));
    }

    /**
     * @param array<string, mixed> $extra
     */
    private function movement(CashRegisterSession $session, array $extra = []): TestResponse
    {
        return $this->postJson('/api/cash-movements', array_merge([
            'cash_register_session_id' => $session->id,
            'concept' => 'deposit',
            'type' => 'income',
            'amount' => 25.0,
            'description' => 'Deposito de prueba',
        ], $extra));
    }

    private function blockedMessage(string $module, string $lockedThrough, string $action = 'create'): string
    {
        return sprintf(
            'El módulo [%s] está bloqueado contablemente hasta el %s: la operación [%s] sobre un registro de esa fecha requiere autorización registrada.',
            $module,
            $lockedThrough,
            $action,
        );
    }

    public function test_a_payment_inside_the_lock_is_forbidden_and_writes_nothing(): void
    {
        $admin = $this->actor('administrador');
        $session = $this->openSessionFor($admin);
        $this->lockThrough(AccountingLockService::CASH_TRANSACTIONS, now(), $admin);

        $response = $this->charge();

        $response->assertForbidden();
        $response->assertJsonPath('code', 'accounting_lock_blocked');
        $response->assertJsonPath(
            'message',
            $this->blockedMessage(AccountingLockService::CASH_TRANSACTIONS, now()->toDateString()),
        );

        $this->assertSame(0, Transaction::count(), 'The blocked charge must not write the transaction.');
        $this->assertSame(0, CashMovement::count(), 'The blocked charge must not move the drawer.');

        $audit = AuditLog::where('action', AccountingLockService::ACTION_BLOCKED)->sole();
        $this->assertSame($admin->id, $audit->user_id);
        $this->assertSame(CashRegisterSession::class, $audit->auditable_type);
        $this->assertSame($session->id, $audit->auditable_id);
        $this->assertSame(AccountingLockService::CASH_TRANSACTIONS, $audit->metadata['module']);
        $this->assertSame(now()->toDateString(), $audit->metadata['accounting_date']);
        $this->assertSame('create', $audit->metadata['action']);
        $this->assertSame($this->branch->id, $audit->metadata['branch_id']);
    }

    public function test_an_administrator_charges_inside_the_lock_with_a_registered_reason(): void
    {
        $admin = $this->actor('administrador');
        $session = $this->openSessionFor($admin);
        $this->lockThrough(AccountingLockService::CASH_TRANSACTIONS, now(), $admin);

        $this->charge(['lock_override_reason' => 'Gerencia autorizo el cobro de hoy'])
            ->assertCreated();

        $transaction = Transaction::sole();
        $this->assertSame('payment', $transaction->type);
        $this->assertSame(1, CashMovement::where('transaction_id', $transaction->id)->count());

        $audit = AuditLog::where('action', AccountingLockService::ACTION_OVERRIDDEN)->sole();
        $this->assertSame($admin->id, $audit->user_id);
        $this->assertSame(CashRegisterSession::class, $audit->auditable_type);
        $this->assertSame($session->id, $audit->auditable_id);
        $this->assertSame('Gerencia autorizo el cobro de hoy', $audit->metadata['reason']);
        $this->assertSame(AccountingLockService::CASH_TRANSACTIONS, $audit->metadata['module']);
        $this->assertSame('create', $audit->metadata['action']);
        $this->assertSame(0, AuditLog::where('action', AccountingLockService::ACTION_BLOCKED)->count());
    }

    public function test_finanzas_cannot_override_the_lock_even_with_a_reason(): void
    {
        $finance = $this->actor('finanzas');
        $this->openSessionFor($finance);
        $this->lockThrough(AccountingLockService::CASH_TRANSACTIONS, now(), $finance);

        $this->charge(['lock_override_reason' => 'Gerencia autorizo el cobro de hoy'])
            ->assertForbidden()
            ->assertJsonPath('code', 'accounting_lock_blocked');

        $this->assertSame(0, Transaction::count());
        $this->assertSame(0, CashMovement::count());
        $this->assertSame(1, AuditLog::where('action', AccountingLockService::ACTION_BLOCKED)->count());
        $this->assertSame(0, AuditLog::where('action', AccountingLockService::ACTION_OVERRIDDEN)->count());
    }

    public function test_finanzas_without_a_reason_is_forbidden_inside_the_lock(): void
    {
        $finance = $this->actor('finanzas');
        $this->openSessionFor($finance);
        $this->lockThrough(AccountingLockService::CASH_TRANSACTIONS, now(), $finance);

        $this->charge()->assertForbidden()->assertJsonPath('code', 'accounting_lock_blocked');

        $this->assertSame(0, Transaction::count());
        $this->assertSame(0, CashMovement::count());
    }

    public function test_a_short_override_reason_keeps_the_422_contract_and_writes_nothing(): void
    {
        $admin = $this->actor('administrador');
        $this->openSessionFor($admin);
        $this->lockThrough(AccountingLockService::CASH_TRANSACTIONS, now(), $admin);

        $response = $this->charge(['lock_override_reason' => 'corto']);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['lock_override_reason']);
        $response->assertJsonMissingPath('code');

        $this->assertSame(0, Transaction::count());
        $this->assertSame(0, CashMovement::count());
        $this->assertSame(0, AuditLog::count());
    }

    public function test_a_manual_cash_movement_inside_the_lock_is_forbidden(): void
    {
        $finance = $this->actor('finanzas');
        $session = $this->openSessionFor($finance);
        $this->lockThrough(AccountingLockService::CASH_MOVEMENTS, now(), $finance);

        $response = $this->movement($session);

        $response->assertForbidden();
        $response->assertJsonPath('code', 'accounting_lock_blocked');
        $response->assertJsonPath(
            'message',
            $this->blockedMessage(AccountingLockService::CASH_MOVEMENTS, now()->toDateString()),
        );

        $this->assertSame(0, CashMovement::count(), 'The blocked movement must not reach the ledger.');

        $audit = AuditLog::where('action', AccountingLockService::ACTION_BLOCKED)->sole();
        $this->assertSame(CashRegisterSession::class, $audit->auditable_type);
        $this->assertSame($session->id, $audit->auditable_id);
        $this->assertSame(AccountingLockService::CASH_MOVEMENTS, $audit->metadata['module']);
        $this->assertSame('create', $audit->metadata['action']);
        $this->assertSame($this->branch->id, $audit->metadata['branch_id']);
    }

    public function test_an_administrator_records_a_movement_inside_the_lock_with_a_registered_reason(): void
    {
        $admin = $this->actor('administrador');
        $session = $this->openSessionFor($admin);
        $this->lockThrough(AccountingLockService::CASH_MOVEMENTS, now(), $admin);

        $this->movement($session, ['lock_override_reason' => 'Gerencia autorizo el deposito'])
            ->assertCreated()
            ->assertJsonPath('data.description', 'Deposito de prueba');

        $movement = CashMovement::sole();
        $this->assertSame('Deposito de prueba', $movement->description);
        $this->assertArrayNotHasKey(
            'lock_override_reason',
            $movement->getAttributes(),
            'The override reason is an authorization, never a column of the ledger.',
        );

        $audit = AuditLog::where('action', AccountingLockService::ACTION_OVERRIDDEN)->sole();
        $this->assertSame('Gerencia autorizo el deposito', $audit->metadata['reason']);
        $this->assertSame(AccountingLockService::CASH_MOVEMENTS, $audit->metadata['module']);
        $this->assertSame('create', $audit->metadata['action']);
        $this->assertSame(0, AuditLog::where('action', AccountingLockService::ACTION_BLOCKED)->count());
    }

    public function test_a_lock_through_yesterday_does_not_block_todays_charge(): void
    {
        $admin = $this->actor('administrador');
        $this->openSessionFor($admin);
        $this->lockThrough(AccountingLockService::CASH_TRANSACTIONS, now()->subDay(), $admin);

        $this->charge()->assertCreated();

        $this->assertSame(1, Transaction::count());
        $this->assertSame(0, AuditLog::count());
    }
}
