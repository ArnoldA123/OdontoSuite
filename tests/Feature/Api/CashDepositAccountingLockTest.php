<?php

namespace Tests\Feature\Api;

use App\Models\AccountingLock;
use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\CashDeposit;
use App\Models\CashMovement;
use App\Models\CashRegisterSession;
use App\Models\User;
use App\Services\AccountingLockService;
use App\Services\CashRegisterService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

/**
 * #323 (S8-S9) — a deposit dated inside an accounting lock.
 *
 * The bank date of a deposit is the accounting date that reconciles the day, so
 * a deposit dated in a closed period mutates a reconciliation that was already
 * reported. Without a registered authorization it answers 403 and leaves the
 * attempt in `audit_logs` as `accounting_lock_blocked`; with
 * `override.accounting_lock` and a stated reason it is accepted and the trail
 * says who authorized it and why. A deposit dated after the lock is untouched.
 *
 * Must run against the MySQL harness (AGENTS.md §6).
 */
#[Group('mysql')]
class CashDepositAccountingLockTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Branch $branch;

    private CashRegisterSession $session;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'role' => 'administrador',
            'is_active' => true,
        ]);
        $this->branch = Branch::factory()->create();

        $this->actingAs($this->admin, 'sanctum');

        $this->session = app(CashRegisterService::class)->openSession([
            'branch_id' => $this->branch->id,
            'opening_amount' => 100.0,
        ]);
    }

    private function lockThrough(string $date): AccountingLock
    {
        return AccountingLock::create([
            'module' => AccountingLockService::CASH_MOVEMENTS,
            'branch_id' => $this->branch->id,
            'locked_through' => $date,
            'set_by' => $this->admin->id,
        ]);
    }

    /**
     * @param array<string, mixed> $extra
     */
    private function deposit(array $extra = []): \Illuminate\Testing\TestResponse
    {
        return $this->postJson('/api/cash-register/deposits', [
            'cash_register_session_id' => $this->session->id,
            'amount' => 50.0,
            'reference' => 'DEP-0001',
            ...$extra,
        ]);
    }

    public function test_a_deposit_dated_inside_the_lock_is_forbidden_and_audited(): void
    {
        $this->lockThrough(now()->subDay()->toDateString());
        $movements = CashMovement::count();

        $this->deposit(['deposited_at' => now()->subDays(2)->toDateString()])
            ->assertForbidden();

        $this->assertSame(0, CashDeposit::count(), 'The blocked deposit must not be written.');
        $this->assertSame($movements, CashMovement::count(), 'The blocked deposit must not move the drawer.');
        $this->assertSame(
            100.0,
            (float) app(CashRegisterService::class)->getSessionSummary($this->session->fresh())['expected_amount'],
            'The drawer must keep the cash it held.',
        );

        $audit = AuditLog::where('action', AccountingLockService::ACTION_BLOCKED)->sole();
        $this->assertSame($this->admin->id, $audit->user_id);
        $this->assertSame(CashRegisterSession::class, $audit->auditable_type);
        $this->assertSame($this->session->id, $audit->auditable_id);
        $this->assertSame(AccountingLockService::CASH_MOVEMENTS, $audit->metadata['module']);
        $this->assertSame(now()->subDays(2)->toDateString(), $audit->metadata['accounting_date']);
        $this->assertSame(now()->subDay()->toDateString(), $audit->metadata['locked_through']);
        $this->assertSame('deposit', $audit->metadata['action']);
        $this->assertSame($this->branch->id, $audit->metadata['branch_id']);
    }

    public function test_an_administrator_registers_a_deposit_inside_the_lock_with_a_reason(): void
    {
        $this->lockThrough(now()->subDay()->toDateString());
        $reason = 'Depósito del cierre pendiente de registrar';
        $depositedAt = now()->subDays(2)->toDateString();

        $this->deposit([
            'deposited_at' => $depositedAt,
            'lock_override_reason' => $reason,
        ])->assertCreated();

        $deposit = CashDeposit::sole();
        $this->assertSame($depositedAt, $deposit->deposited_at->toDateString());
        $this->assertSame(50.0, (float) $deposit->amount);
        $this->assertNotNull($deposit->cash_movement_id, 'The deposit must still write its withdrawal.');

        $audit = AuditLog::where('action', AccountingLockService::ACTION_OVERRIDDEN)->sole();
        $this->assertSame($this->admin->id, $audit->user_id);
        $this->assertSame($reason, $audit->metadata['reason']);
        $this->assertSame(AccountingLockService::CASH_MOVEMENTS, $audit->metadata['module']);
        $this->assertSame($depositedAt, $audit->metadata['accounting_date']);
        $this->assertSame('deposit', $audit->metadata['action']);
    }

    public function test_an_override_without_enough_reason_is_refused_and_writes_nothing(): void
    {
        $this->lockThrough(now()->subDay()->toDateString());

        $this->deposit([
            'deposited_at' => now()->subDays(2)->toDateString(),
            'lock_override_reason' => 'corto',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('lock_override_reason');

        $this->assertSame(0, CashDeposit::count());
        $this->assertSame(0, AuditLog::count(), 'A refused override is not an authorization attempt.');
    }

    public function test_a_deposit_after_the_lock_is_allowed_without_a_reason(): void
    {
        $this->lockThrough(now()->subDay()->toDateString());

        $this->deposit()->assertCreated();

        $this->assertSame(1, CashDeposit::count());
        $this->assertSame(now()->toDateString(), CashDeposit::sole()->deposited_at->toDateString());
        $this->assertSame(0, AuditLog::count());
    }

    public function test_against_a_lock_of_another_branch_the_deposit_is_not_blocked(): void
    {
        $otherBranch = Branch::factory()->create();

        AccountingLock::create([
            'module' => AccountingLockService::CASH_MOVEMENTS,
            'branch_id' => $otherBranch->id,
            'locked_through' => now()->subDay()->toDateString(),
            'set_by' => $this->admin->id,
        ]);

        $this->deposit(['deposited_at' => now()->subDays(2)->toDateString()])->assertCreated();

        $this->assertSame(1, CashDeposit::count());
        $this->assertSame(0, AuditLog::count());
    }
}
