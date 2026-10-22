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
use Tests\TestCase;

/**
 * F3 (#283, S5) — voiding a transaction dated inside the accounting lock.
 *
 * Without a registered authorisation the endpoint answers 403, the stored row
 * stays `completed` and the attempt is left in `audit_logs` as
 * `accounting_lock_blocked`. With `override.accounting_lock` and a reason of at
 * least ten characters the void succeeds and the trail says who authorised it
 * and why.
 *
 * Must run against the MySQL harness (AGENTS.md §6).
 */
class AccountingLockTransactionGuardTest extends TestCase
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
    }

    private function sessionFor(User $owner, ?Branch $branch = null): CashRegisterSession
    {
        return CashRegisterSession::factory()->create([
            'user_id' => $owner->id,
            'branch_id' => ($branch ?? $this->branch)->id,
            'status' => 'open',
            'opening_amount' => 100.0,
        ]);
    }

    private function transactionAt(CashRegisterSession $session, \DateTimeInterface $date): Transaction
    {
        $transaction = Transaction::factory()->create([
            'patient_id' => Patient::factory()->create(['branch_id' => $session->branch_id])->id,
            'payment_method_id' => PaymentMethod::factory()->create()->id,
            'created_by' => $this->admin->id,
            'cash_register_session_id' => $session->id,
            'amount' => 80.0,
            'type' => 'payment',
            'status' => 'completed',
        ]);

        $transaction->forceFill(['created_at' => $date])->save();

        return $transaction->refresh();
    }

    private function lockThrough(\DateTimeInterface $date, ?Branch $branch = null): AccountingLock
    {
        return AccountingLock::create([
            'module' => AccountingLockService::CASH_TRANSACTIONS,
            'branch_id' => $branch?->id,
            'locked_through' => $date->format('Y-m-d'),
            'set_by' => $this->admin->id,
        ]);
    }

    public function test_voiding_a_transaction_inside_the_lock_is_forbidden_and_audited(): void
    {
        $branch = $this->branch;
        $session = $this->sessionFor($this->admin, $branch);
        $transaction = $this->transactionAt($session, now()->subDays(5));
        $this->lockThrough(now()->subDays(2), $branch);

        $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/transactions/{$transaction->id}/void", [
                'reason' => 'Cobro duplicado',
            ])
            ->assertForbidden();

        $this->assertSame('completed', $transaction->fresh()->status, 'The blocked void must not change the row.');
        $this->assertSame(
            0,
            CashMovement::where('reference', 'VOID-'.$transaction->transaction_number)->count(),
            'The blocked void must not write the reversal movement.',
        );

        $audit = AuditLog::where('action', AccountingLockService::ACTION_BLOCKED)->sole();
        $this->assertSame($this->admin->id, $audit->user_id);
        $this->assertSame(Transaction::class, $audit->auditable_type);
        $this->assertSame($transaction->id, $audit->auditable_id);
        $this->assertSame(AccountingLockService::CASH_TRANSACTIONS, $audit->metadata['module']);
        $this->assertSame($transaction->created_at->toDateString(), $audit->metadata['accounting_date']);
        $this->assertSame(now()->subDays(2)->toDateString(), $audit->metadata['locked_through']);
        $this->assertSame('void', $audit->metadata['action']);
        $this->assertSame($branch->id, $audit->metadata['branch_id']);
    }

    public function test_an_administrator_can_void_inside_the_lock_with_a_registered_reason(): void
    {
        $session = $this->sessionFor($this->admin);
        $transaction = $this->transactionAt($session, now()->subDays(5));
        $this->lockThrough(now()->subDays(2));

        $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/transactions/{$transaction->id}/void", [
                'reason' => 'Cobro duplicado',
                'lock_override_reason' => 'Gerencia autorizo corregir el cierre',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'voided');

        $this->assertSame('voided', $transaction->fresh()->status);

        $audit = AuditLog::where('action', AccountingLockService::ACTION_OVERRIDDEN)->sole();
        $this->assertSame('Gerencia autorizo corregir el cierre', $audit->metadata['reason']);
        $this->assertSame(AccountingLockService::CASH_TRANSACTIONS, $audit->metadata['module']);
        $this->assertSame('void', $audit->metadata['action']);
        $this->assertSame($this->admin->id, $audit->user_id);
        $this->assertSame(0, AuditLog::where('action', AccountingLockService::ACTION_BLOCKED)->count());
    }

    public function test_an_override_reason_shorter_than_ten_characters_is_rejected_and_not_audited(): void
    {
        $session = $this->sessionFor($this->admin);
        $transaction = $this->transactionAt($session, now()->subDays(5));
        $this->lockThrough(now()->subDays(2));

        $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/transactions/{$transaction->id}/void", [
                'reason' => 'Cobro duplicado',
                'lock_override_reason' => 'corto',
            ])
            ->assertStatus(422);

        $this->assertSame('completed', $transaction->fresh()->status);
        $this->assertSame(0, AuditLog::where('action', AccountingLockService::ACTION_OVERRIDDEN)->count());
        $this->assertSame(0, AuditLog::where('action', AccountingLockService::ACTION_BLOCKED)->count());
    }

    public function test_a_transaction_dated_after_the_lock_is_unaffected(): void
    {
        $session = $this->sessionFor($this->admin);
        $transaction = $this->transactionAt($session, now());
        $this->lockThrough(now()->subDay());

        $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/transactions/{$transaction->id}/void", [
                'reason' => 'Cobro duplicado',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'voided');

        $this->assertSame('voided', $transaction->fresh()->status);
        $this->assertSame(0, AuditLog::where('action', AccountingLockService::ACTION_BLOCKED)->count());
        $this->assertSame(0, AuditLog::where('action', AccountingLockService::ACTION_OVERRIDDEN)->count());
    }

    public function test_a_lock_of_another_sede_does_not_freeze_this_one(): void
    {
        $own = $this->branch;
        $other = Branch::factory()->create();
        $session = $this->sessionFor($this->admin, $own);
        $transaction = $this->transactionAt($session, now()->subDays(5));
        $this->lockThrough(now()->subDays(2), $other);

        $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/transactions/{$transaction->id}/void", [
                'reason' => 'Cobro duplicado',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'voided');

        $this->assertSame(0, AuditLog::where('action', AccountingLockService::ACTION_BLOCKED)->count());
    }
}
