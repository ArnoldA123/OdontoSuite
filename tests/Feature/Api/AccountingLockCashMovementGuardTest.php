<?php

namespace Tests\Feature\Api;

use App\Models\AccountingLock;
use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\CashMovement;
use App\Models\CashRegisterSession;
use App\Models\User;
use App\Services\AccountingLockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * F3 (#283, S5) — editing or deleting a cash movement dated inside the lock.
 *
 * The ledger is money that already happened: a movement at or before
 * `locked_through` cannot be rewritten without a registered authorisation, and
 * the refusal leaves the row untouched plus one `accounting_lock_blocked` entry.
 *
 * Must run against the MySQL harness (AGENTS.md §6).
 */
class AccountingLockCashMovementGuardTest extends TestCase
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

        $this->session = CashRegisterSession::factory()->create([
            'user_id' => $this->admin->id,
            'branch_id' => $this->branch->id,
            'status' => 'open',
            'opening_amount' => 100.0,
        ]);
    }

    private function movementAt(\DateTimeInterface $date, string $description = 'Deposito del dia'): CashMovement
    {
        $movement = CashMovement::create([
            'cash_register_session_id' => $this->session->id,
            'created_by' => $this->admin->id,
            'type' => 'income',
            'amount' => 25.5,
            'description' => $description,
            'branch_id' => $this->branch->id,
            'concept' => 'deposit',
        ]);

        $movement->forceFill(['created_at' => $date])->save();

        return $movement->refresh();
    }

    private function lockThrough(\DateTimeInterface $date): AccountingLock
    {
        return AccountingLock::create([
            'module' => AccountingLockService::CASH_MOVEMENTS,
            'branch_id' => $this->branch->id,
            'locked_through' => $date->format('Y-m-d'),
            'set_by' => $this->admin->id,
        ]);
    }

    public function test_updating_a_movement_inside_the_lock_is_forbidden_and_audited(): void
    {
        $movement = $this->movementAt(now()->subDays(5));
        $this->lockThrough(now()->subDays(2));

        $this->actingAs($this->admin, 'sanctum')
            ->putJson("/api/cash-movements/{$movement->id}", [
                'description' => 'Descripcion reescrita',
            ])
            ->assertForbidden();

        $this->assertSame('Deposito del dia', $movement->fresh()->description, 'The blocked update must not change the row.');

        $audit = AuditLog::where('action', AccountingLockService::ACTION_BLOCKED)->sole();
        $this->assertSame(CashMovement::class, $audit->auditable_type);
        $this->assertSame($movement->id, $audit->auditable_id);
        $this->assertSame(AccountingLockService::CASH_MOVEMENTS, $audit->metadata['module']);
        $this->assertSame('update', $audit->metadata['action']);
        $this->assertSame($this->branch->id, $audit->metadata['branch_id']);
        $this->assertSame($movement->created_at->toDateString(), $audit->metadata['accounting_date']);
    }

    public function test_deleting_a_movement_inside_the_lock_is_forbidden_and_audited(): void
    {
        $movement = $this->movementAt(now()->subDays(5));
        $this->lockThrough(now()->subDays(2));

        $this->actingAs($this->admin, 'sanctum')
            ->deleteJson("/api/cash-movements/{$movement->id}")
            ->assertForbidden();

        $this->assertNull($movement->fresh()->deleted_at, 'The blocked delete must not archive the row.');

        $audit = AuditLog::where('action', AccountingLockService::ACTION_BLOCKED)->sole();
        $this->assertSame($movement->id, $audit->auditable_id);
        $this->assertSame('delete', $audit->metadata['action']);
    }

    public function test_updating_a_movement_inside_the_lock_with_a_registered_reason_succeeds(): void
    {
        $movement = $this->movementAt(now()->subDays(5));
        $this->lockThrough(now()->subDays(2));

        $this->actingAs($this->admin, 'sanctum')
            ->putJson("/api/cash-movements/{$movement->id}", [
                'description' => 'Descripcion corregida',
                'lock_override_reason' => 'Gerencia autorizo la correccion',
            ])
            ->assertOk()
            ->assertJsonPath('data.description', 'Descripcion corregida');

        $this->assertSame('Descripcion corregida', $movement->fresh()->description);

        $audit = AuditLog::where('action', AccountingLockService::ACTION_OVERRIDDEN)->sole();
        $this->assertSame('Gerencia autorizo la correccion', $audit->metadata['reason']);
        $this->assertSame('update', $audit->metadata['action']);
        $this->assertSame(0, AuditLog::where('action', AccountingLockService::ACTION_BLOCKED)->count());
    }

    public function test_a_movement_dated_after_the_lock_is_unaffected(): void
    {
        $movement = $this->movementAt(now());
        $this->lockThrough(now()->subDay());

        $this->actingAs($this->admin, 'sanctum')
            ->putJson("/api/cash-movements/{$movement->id}", [
                'description' => 'Descripcion corregida',
            ])
            ->assertOk()
            ->assertJsonPath('data.description', 'Descripcion corregida');

        $this->assertSame('Descripcion corregida', $movement->fresh()->description);
        $this->assertSame(0, AuditLog::where('action', AccountingLockService::ACTION_BLOCKED)->count());
        $this->assertSame(0, AuditLog::where('action', AccountingLockService::ACTION_OVERRIDDEN)->count());
    }
}
