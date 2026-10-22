<?php

namespace Tests\Feature\Api;

use App\Models\AccountingLock;
use App\Models\Branch;
use App\Models\User;
use App\Services\AccountingLockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * F3 (#283, S4/S5) — administration of the accounting freeze date.
 *
 * One row per module and sede; the global row (branch_id null) is group scope.
 * The endpoints answer one entry per module with the lock that actually applies
 * to the sede, and the group row is never writable from a sede account.
 *
 * Must run against the MySQL harness (AGENTS.md §6).
 */
class AccountingLockManagementTest extends TestCase
{
    use RefreshDatabase;

    private function user(?Branch $branch, string $role = 'administrador'): User
    {
        return User::factory()->create([
            'role' => $role,
            'is_active' => true,
            'branch_id' => $branch?->id,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function entry(array $data, string $module): array
    {
        foreach ($data as $entry) {
            if (($entry['module'] ?? null) === $module) {
                return $entry;
            }
        }

        $this->fail("The payload has no entry for [{$module}].");
    }

    public function test_two_puts_of_the_same_global_module_leave_exactly_one_row(): void
    {
        $admin = $this->user(null);
        $first = now()->subWeek()->toDateString();
        $second = now()->subDays(2)->toDateString();

        $this->actingAs($admin, 'sanctum')
            ->putJson('/api/accounting-locks/'.AccountingLockService::CASH_TRANSACTIONS, [
                'locked_through' => $first,
            ])
            ->assertOk()
            ->assertJsonPath('data.module', AccountingLockService::CASH_TRANSACTIONS)
            ->assertJsonPath('data.locked_through', $first)
            ->assertJsonPath('data.source', AccountingLock::SOURCE_GLOBAL)
            ->assertJsonPath('data.branch_id', null)
            ->assertJsonPath('data.set_by', $admin->id);

        $this->actingAs($admin, 'sanctum')
            ->putJson('/api/accounting-locks/'.AccountingLockService::CASH_TRANSACTIONS, [
                'locked_through' => $second,
            ])
            ->assertOk()
            ->assertJsonPath('data.locked_through', $second);

        $this->assertSame(
            1,
            AccountingLock::where('module', AccountingLockService::CASH_TRANSACTIONS)->count(),
            'A second PUT of the same global module must update the row, not add another one.',
        );
        $this->assertSame(
            $second,
            AccountingLock::where('module', AccountingLockService::CASH_TRANSACTIONS)->sole()->locked_through->toDateString(),
        );
    }

    public function test_get_reports_one_entry_per_module_with_the_lock_that_applies(): void
    {
        $admin = $this->user(null);
        $lockedThrough = now()->subDay()->toDateString();

        $this->actingAs($admin, 'sanctum')
            ->putJson('/api/accounting-locks/'.AccountingLockService::QUOTATIONS, [
                'locked_through' => $lockedThrough,
            ])
            ->assertOk();

        $response = $this->actingAs($admin, 'sanctum')
            ->getJson('/api/accounting-locks')
            ->assertOk();

        $this->assertCount(count(AccountingLockService::MODULES), $response->json('data'));

        $quotations = $this->entry($response->json('data'), AccountingLockService::QUOTATIONS);
        $this->assertSame($lockedThrough, $quotations['locked_through']);
        $this->assertSame(AccountingLock::SOURCE_GLOBAL, $quotations['source']);

        $movements = $this->entry($response->json('data'), AccountingLockService::CASH_MOVEMENTS);
        $this->assertNull($movements['locked_through']);
        $this->assertSame(AccountingLock::SOURCE_NONE, $movements['source']);
        $this->assertNull($movements['branch_id']);
    }

    public function test_a_branch_row_wins_over_the_global_row_in_the_read(): void
    {
        $admin = $this->user(null);
        $branch = Branch::factory()->create();
        $globalDate = now()->subWeek()->toDateString();
        $branchDate = now()->subDays(3)->toDateString();

        $this->actingAs($admin, 'sanctum')
            ->putJson('/api/accounting-locks/'.AccountingLockService::CASH_TRANSACTIONS, [
                'locked_through' => $globalDate,
            ])
            ->assertOk();

        $this->actingAs($admin, 'sanctum')
            ->putJson('/api/accounting-locks/'.AccountingLockService::CASH_TRANSACTIONS, [
                'locked_through' => $branchDate,
                'branch_id' => $branch->id,
            ])
            ->assertOk()
            ->assertJsonPath('data.source', AccountingLock::SOURCE_BRANCH)
            ->assertJsonPath('data.branch_id', $branch->id);

        $forBranch = $this->actingAs($admin, 'sanctum')
            ->getJson("/api/accounting-locks?branch_id={$branch->id}")
            ->assertOk();

        $entry = $this->entry($forBranch->json('data'), AccountingLockService::CASH_TRANSACTIONS);
        $this->assertSame(AccountingLock::SOURCE_BRANCH, $entry['source']);
        $this->assertSame($branchDate, $entry['locked_through']);
        $this->assertSame($branch->id, $entry['branch_id']);

        $global = $this->actingAs($admin, 'sanctum')
            ->getJson('/api/accounting-locks')
            ->assertOk();

        $entry = $this->entry($global->json('data'), AccountingLockService::CASH_TRANSACTIONS);
        $this->assertSame(AccountingLock::SOURCE_GLOBAL, $entry['source']);
        $this->assertSame($globalDate, $entry['locked_through']);
    }

    public function test_a_non_administrator_cannot_manage_accounting_locks(): void
    {
        $finanzas = $this->user(null, 'finanzas');

        $this->actingAs($finanzas, 'sanctum')
            ->getJson('/api/accounting-locks')
            ->assertForbidden();

        $this->actingAs($finanzas, 'sanctum')
            ->putJson('/api/accounting-locks/'.AccountingLockService::CASH_TRANSACTIONS, [
                'locked_through' => now()->toDateString(),
            ])
            ->assertForbidden();

        $this->actingAs($finanzas, 'sanctum')
            ->deleteJson('/api/accounting-locks/'.AccountingLockService::CASH_TRANSACTIONS)
            ->assertForbidden();

        $this->assertSame(0, AccountingLock::count());
    }

    public function test_a_sede_administrator_cannot_write_the_global_row(): void
    {
        $own = Branch::factory()->create();
        $admin = $this->user($own);

        $this->actingAs($admin, 'sanctum')
            ->putJson('/api/accounting-locks/'.AccountingLockService::CASH_TRANSACTIONS, [
                'locked_through' => now()->toDateString(),
                'branch_id' => null,
            ])
            ->assertForbidden();

        $this->assertSame(0, AccountingLock::count(), 'A sede administrator must never write the group row.');
    }

    public function test_a_sede_administrator_cannot_set_or_clear_another_branch(): void
    {
        $own = Branch::factory()->create();
        $other = Branch::factory()->create();
        $admin = $this->user($own);

        $this->actingAs($admin, 'sanctum')
            ->putJson('/api/accounting-locks/'.AccountingLockService::CASH_TRANSACTIONS, [
                'locked_through' => now()->toDateString(),
                'branch_id' => $other->id,
            ])
            ->assertForbidden();

        $this->actingAs($admin, 'sanctum')
            ->deleteJson('/api/accounting-locks/'.AccountingLockService::CASH_TRANSACTIONS."?branch_id={$other->id}")
            ->assertForbidden();

        $this->assertSame(0, AccountingLock::count());
    }

    public function test_a_sede_administrator_freezes_and_clears_their_own_sede_by_default(): void
    {
        $own = Branch::factory()->create();
        $admin = $this->user($own);
        $date = now()->subDay()->toDateString();

        $this->actingAs($admin, 'sanctum')
            ->putJson('/api/accounting-locks/'.AccountingLockService::CASH_MOVEMENTS, [
                'locked_through' => $date,
            ])
            ->assertOk()
            ->assertJsonPath('data.source', AccountingLock::SOURCE_BRANCH)
            ->assertJsonPath('data.branch_id', $own->id)
            ->assertJsonPath('data.locked_through', $date);

        $this->assertSame(0, AccountingLock::whereNull('branch_id')->count(), 'A sede lock must never land on the group row.');

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/accounting-locks')
            ->assertOk()
            ->assertJsonPath('data.1.source', AccountingLock::SOURCE_BRANCH)
            ->assertJsonPath('data.1.locked_through', $date);

        $this->actingAs($admin, 'sanctum')
            ->deleteJson('/api/accounting-locks/'.AccountingLockService::CASH_MOVEMENTS)
            ->assertOk()
            ->assertJsonPath('data.removed', true)
            ->assertJsonPath('data.branch_id', $own->id);

        $this->assertSame(0, AccountingLock::count());

        $this->actingAs($admin, 'sanctum')
            ->deleteJson('/api/accounting-locks/'.AccountingLockService::CASH_MOVEMENTS)
            ->assertOk()
            ->assertJsonPath('data.removed', false);
    }

    public function test_an_unknown_module_is_rejected(): void
    {
        $admin = $this->user(null);

        $this->actingAs($admin, 'sanctum')
            ->putJson('/api/accounting-locks/mystery_module', [
                'locked_through' => now()->toDateString(),
            ])
            ->assertStatus(422);

        $this->actingAs($admin, 'sanctum')
            ->deleteJson('/api/accounting-locks/mystery_module')
            ->assertStatus(422);

        $this->assertSame(0, AccountingLock::count());
    }

    public function test_the_date_is_required_and_must_be_a_date(): void
    {
        $admin = $this->user(null);

        $this->actingAs($admin, 'sanctum')
            ->putJson('/api/accounting-locks/'.AccountingLockService::QUOTATIONS, [])
            ->assertStatus(422)
            ->assertJsonValidationErrors('locked_through');

        $this->actingAs($admin, 'sanctum')
            ->putJson('/api/accounting-locks/'.AccountingLockService::QUOTATIONS, [
                'locked_through' => 'not-a-date',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('locked_through');

        $this->assertSame(0, AccountingLock::count());
    }
}
