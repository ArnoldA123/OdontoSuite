<?php

namespace Tests\Unit\Services;

use App\Models\AccountingLock;
use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\TreatmentPlan;
use App\Models\User;
use App\Services\AccountingLockService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * F3 (#283, S4/S5) — resolution and guard of the accounting lock.
 *
 * Resolution: the sede row wins over the group row, the group row applies when
 * the sede has none, and no row means no lock. The guard only freezes a
 * mutation dated at or before `locked_through`; a mutation after it is always
 * allowed, because today's money is not history yet.
 *
 * Must run against the MySQL harness (AGENTS.md §6).
 */
class AccountingLockServiceTest extends TestCase
{
    use RefreshDatabase;

    private AccountingLockService $locks;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->locks = app(AccountingLockService::class);
        $this->admin = User::factory()->create([
            'role' => 'administrador',
            'is_active' => true,
        ]);
    }

    private function lock(?Branch $branch, string $module, string $date): AccountingLock
    {
        return $this->locks->setLock($module, $date, $branch?->id, $this->admin);
    }

    private function auditable(): TreatmentPlan
    {
        return TreatmentPlan::factory()->create();
    }

    public function test_a_branch_row_wins_over_the_global_row(): void
    {
        $branch = Branch::factory()->create();
        $this->lock(null, AccountingLockService::QUOTATIONS, now()->subDays(10)->toDateString());
        $this->lock($branch, AccountingLockService::QUOTATIONS, now()->subDays(2)->toDateString());

        $lock = $this->locks->effectiveLock(AccountingLockService::QUOTATIONS, $branch->id);

        $this->assertNotNull($lock);
        $this->assertSame($branch->id, $lock->branch_id);
        $this->assertSame(now()->subDays(2)->toDateString(), $lock->locked_through->toDateString());
    }

    public function test_the_global_row_applies_when_the_branch_has_none(): void
    {
        $branch = Branch::factory()->create();
        $this->lock(null, AccountingLockService::QUOTATIONS, now()->subDays(10)->toDateString());

        $lock = $this->locks->effectiveLock(AccountingLockService::QUOTATIONS, $branch->id);

        $this->assertNotNull($lock);
        $this->assertNull($lock->branch_id);
    }

    public function test_without_a_row_there_is_no_lock(): void
    {
        $branch = Branch::factory()->create();

        $this->assertNull($this->locks->effectiveLock(AccountingLockService::TREATMENT_PLANS, null));
        $this->assertNull($this->locks->effectiveLock(AccountingLockService::TREATMENT_PLANS, $branch->id));
    }

    public function test_an_unknown_module_is_rejected(): void
    {
        foreach ([
            fn () => $this->locks->effectiveLock('mystery_module', null),
            fn () => $this->locks->setLock('mystery_module', now()->toDateString(), null, $this->admin),
            fn () => $this->locks->clearLock('mystery_module', null),
        ] as $call) {
            try {
                $call();
                $this->fail('An unknown module must be rejected.');
            } catch (ValidationException $e) {
                $this->assertArrayHasKey('module', $e->errors());
            }
        }

        $this->assertSame(0, AccountingLock::count());
    }

    public function test_set_lock_keeps_one_row_per_module_and_branch(): void
    {
        $branch = Branch::factory()->create();

        $this->lock(null, AccountingLockService::CASH_TRANSACTIONS, now()->subDays(9)->toDateString());
        $this->lock(null, AccountingLockService::CASH_TRANSACTIONS, now()->subDays(4)->toDateString());
        $this->lock($branch, AccountingLockService::CASH_TRANSACTIONS, now()->subDays(4)->toDateString());

        $this->assertSame(2, AccountingLock::count());
        $this->assertSame(
            1,
            AccountingLock::where('module', AccountingLockService::CASH_TRANSACTIONS)->whereNull('branch_id')->count(),
        );
        $this->assertSame(
            now()->subDays(4)->toDateString(),
            $this->locks->effectiveLock(AccountingLockService::CASH_TRANSACTIONS, null)->locked_through->toDateString(),
        );
    }

    public function test_clear_lock_reports_whether_a_row_existed(): void
    {
        $this->lock(null, AccountingLockService::CASH_MOVEMENTS, now()->toDateString());

        $this->assertTrue($this->locks->clearLock(AccountingLockService::CASH_MOVEMENTS, null));
        $this->assertFalse($this->locks->clearLock(AccountingLockService::CASH_MOVEMENTS, null));
        $this->assertSame(0, AccountingLock::count());
    }

    public function test_the_guard_passes_when_the_module_has_no_lock(): void
    {
        $this->actingAs($this->admin, 'sanctum');

        $this->locks->guard(
            AccountingLockService::QUOTATIONS,
            now()->subMonth(),
            null,
            $this->auditable(),
            'update',
        );

        $this->assertSame(0, AuditLog::count());
    }

    public function test_the_guard_passes_when_the_accounting_date_is_after_the_lock(): void
    {
        $this->actingAs($this->admin, 'sanctum');
        $this->lock(null, AccountingLockService::QUOTATIONS, now()->subDays(2)->toDateString());

        $this->locks->guard(
            AccountingLockService::QUOTATIONS,
            now(),
            null,
            $this->auditable(),
            'update',
        );

        $this->assertSame(0, AuditLog::count());
    }

    public function test_the_guard_blocks_and_audits_a_date_inside_the_lock(): void
    {
        $this->actingAs($this->admin, 'sanctum');
        $this->lock(null, AccountingLockService::QUOTATIONS, now()->subDays(2)->toDateString());
        $plan = $this->auditable();

        try {
            $this->locks->guard(
                AccountingLockService::QUOTATIONS,
                now()->subDays(5),
                null,
                $plan,
                'update',
            );

            $this->fail('A date inside the lock must be refused.');
        } catch (AuthorizationException $e) {
            $this->assertStringContainsString(AccountingLockService::QUOTATIONS, $e->getMessage());
            $this->assertStringContainsString(now()->subDays(2)->toDateString(), $e->getMessage());
        }

        $audit = AuditLog::where('action', AccountingLockService::ACTION_BLOCKED)->sole();
        $this->assertSame($plan->id, $audit->auditable_id);
        $this->assertSame(AccountingLockService::QUOTATIONS, $audit->metadata['module']);
        $this->assertSame(now()->subDays(5)->toDateString(), $audit->metadata['accounting_date']);
        $this->assertSame(now()->subDays(2)->toDateString(), $audit->metadata['locked_through']);
        $this->assertSame('update', $audit->metadata['action']);
        $this->assertSame([], $audit->new_values);
        $this->assertSame([], $audit->old_values);
    }

    public function test_the_guard_without_a_registered_reason_blocks_instead_of_overriding(): void
    {
        $this->actingAs($this->admin, 'sanctum');
        $this->lock(null, AccountingLockService::TREATMENT_PLANS, now()->subDays(2)->toDateString());
        $plan = $this->auditable();

        try {
            $this->locks->guard(
                AccountingLockService::TREATMENT_PLANS,
                now()->subDays(5),
                null,
                $plan,
                'add_item',
            );

            $this->fail('A frozen plan must not be written without an override.');
        } catch (AuthorizationException) {
            // Expected: no reason was stated, so this is a plain blocked mutation.
        }

        $this->assertSame(0, AuditLog::where('action', AccountingLockService::ACTION_OVERRIDDEN)->count());
        $this->assertSame(1, AuditLog::where('action', AccountingLockService::ACTION_BLOCKED)->count());
    }

    public function test_the_guard_requires_a_reason_of_at_least_ten_characters_to_override(): void
    {
        $this->actingAs($this->admin, 'sanctum');
        $this->lock(null, AccountingLockService::TREATMENT_PLANS, now()->subDays(2)->toDateString());
        $plan = $this->auditable();

        foreach (['', '   ', 'corta'] as $reason) {
            try {
                $this->locks->guard(
                    AccountingLockService::TREATMENT_PLANS,
                    now()->subDays(5),
                    null,
                    $plan,
                    'add_item',
                    $reason,
                );

                $this->fail('An override without a useful reason must be refused.');
            } catch (ValidationException $e) {
                $this->assertArrayHasKey('lock_override_reason', $e->errors());
            }
        }

        $this->assertSame(0, AuditLog::count(), 'A refused override must not write the overridden audit entry.');
    }

    public function test_the_guard_audits_a_valid_override(): void
    {
        $this->actingAs($this->admin, 'sanctum');
        $this->lock(null, AccountingLockService::TREATMENT_PLANS, now()->subDays(2)->toDateString());
        $plan = $this->auditable();

        $this->locks->guard(
            AccountingLockService::TREATMENT_PLANS,
            now()->subDays(5),
            null,
            $plan,
            'remove_item',
            'Gerencia autorizo el ajuste',
        );

        $audit = AuditLog::where('action', AccountingLockService::ACTION_OVERRIDDEN)->sole();
        $this->assertSame('Gerencia autorizo el ajuste', $audit->metadata['reason']);
        $this->assertSame(AccountingLockService::TREATMENT_PLANS, $audit->metadata['module']);
        $this->assertSame('remove_item', $audit->metadata['action']);
        $this->assertSame($this->admin->id, $audit->user_id);
        $this->assertSame(0, AuditLog::where('action', AccountingLockService::ACTION_BLOCKED)->count());
    }
}
