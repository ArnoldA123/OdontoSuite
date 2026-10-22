<?php

namespace Tests\Feature\Api;

use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\CashRegisterSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * #283 S2/S6 — the remaining administrative surface of the sede scope: a sede
 * administrator manages its own sede and nothing else, group scope manages the
 * catalog of sedes, moving a user between sedes leaves an audit trail (L2), and
 * no report or dashboard widens to another sede by omission.
 *
 * Must run against the MySQL harness (AGENTS.md §6).
 */
class BranchScopeAdministrationTest extends TestCase
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
    private function branchPayload(string $code): array
    {
        return [
            'code' => $code,
            'name' => 'Sede '.$code,
            'city' => 'Lima',
        ];
    }

    public function test_a_sede_administrator_cannot_create_a_branch(): void
    {
        $own = Branch::factory()->create();
        $actor = $this->user($own);

        $this->actingAs($actor, 'sanctum')
            ->postJson('/api/branches', $this->branchPayload('SC-NEW-01'))
            ->assertForbidden();

        $this->assertSame(1, Branch::count());
    }

    public function test_a_sede_administrator_cannot_rename_another_branch(): void
    {
        $own = Branch::factory()->create();
        $other = Branch::factory()->create(['name' => 'Sede Norte']);

        $this->actingAs($this->user($own), 'sanctum')
            ->putJson("/api/branches/{$other->id}", ['name' => 'Renombrada', 'city' => 'Lima'])
            ->assertForbidden();

        $this->assertSame('Sede Norte', $other->fresh()->name);
    }

    public function test_a_sede_administrator_cannot_delete_another_branch(): void
    {
        $own = Branch::factory()->create();
        $other = Branch::factory()->create();

        $this->actingAs($this->user($own), 'sanctum')
            ->deleteJson("/api/branches/{$other->id}")
            ->assertForbidden();

        $this->assertNotNull($other->fresh());
    }

    public function test_group_scope_creates_a_branch(): void
    {
        $this->actingAs($this->user(null), 'sanctum')
            ->postJson('/api/branches', $this->branchPayload('SC-NEW-02'))
            ->assertCreated();

        $this->assertDatabaseHas('branches', ['code' => 'SC-NEW-02']);
    }

    public function test_moving_a_user_between_branches_leaves_an_audit_trail(): void
    {
        $own = Branch::factory()->create();
        $other = Branch::factory()->create();
        $actor = $this->user(null);
        $target = $this->user($own, 'odontologo');

        $this->actingAs($actor, 'sanctum')
            ->putJson("/api/users/{$target->id}", [
                'name' => $target->name,
                'email' => $target->email,
                'username' => $target->username,
                'role' => 'odontologo',
                'branch_id' => $other->id,
            ])
            ->assertOk();

        $audit = AuditLog::where('action', 'user_branch_assigned')
            ->where('auditable_id', $target->id)
            ->sole();

        $this->assertSame($own->id, $audit->old_values['branch_id']);
        $this->assertSame($other->id, $audit->new_values['branch_id']);
    }

    public function test_the_cash_summary_never_widens_to_another_branch(): void
    {
        $own = Branch::factory()->create();
        $other = Branch::factory()->create();
        $actor = $this->user($own, 'finanzas');

        $peer = $this->user($other, 'finanzas');

        $mine = CashRegisterSession::factory()->create([
            'user_id' => $this->user($own, 'recepcionista')->id,
            'branch_id' => $own->id,
            'status' => 'open',
            'opened_at' => now(),
        ]);
        CashRegisterSession::factory()->create([
            'user_id' => $peer->id,
            'branch_id' => $other->id,
            'status' => 'open',
            'opened_at' => now(),
        ]);

        $this->actingAs($actor, 'sanctum')
            ->getJson('/api/cash-register/summary')
            ->assertOk()
            ->assertJsonPath('data.session.id', $mine->id)
            ->assertJsonPath('data.session.branch_id', $own->id);
    }

    public function test_naming_another_branch_in_the_cash_report_is_forbidden(): void
    {
        $own = Branch::factory()->create();
        $other = Branch::factory()->create();
        $actor = $this->user($own, 'finanzas');

        $this->actingAs($actor, 'sanctum')
            ->getJson('/api/cash-reports/period?start_date='
                .now()->subDays(3)->toDateString().'&end_date='
                .now()->toDateString()."&branch_id={$other->id}")
            ->assertForbidden();
    }

    public function test_naming_another_branch_in_the_dashboard_is_forbidden(): void
    {
        $own = Branch::factory()->create();
        $other = Branch::factory()->create();
        $actor = $this->user($own, 'recepcionista');

        $this->actingAs($actor, 'sanctum')
            ->getJson("/api/dashboard/appointments-today?branch_id={$other->id}")
            ->assertForbidden();
    }
}
