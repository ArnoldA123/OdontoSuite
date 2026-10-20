<?php

namespace Tests\Feature\Api;

use App\Models\Branch;
use App\Models\CashRegisterSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Cash follow-up S3 — CashRegisterService::getCurrentSession() guarded the
 * user scope with `! userCanManageCashRegister()`, but that helper returns
 * true for the three cash roles, so the guard never applied: a recepcionista
 * was answered the latest open session of the branch, including another
 * user's drawer (which #193 already answers 403 for).
 *
 * The rule is owner-or-administrador, the same one written in
 * CashRegisterPolicy::view. `userCanManageCashRegister()` keeps admitting the
 * three cash roles because it is the permission gate of openSession().
 *
 * Must run against the MySQL harness (AGENTS.md §6).
 */
class CashRegisterCurrentSessionScopeTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role, ?Branch $branch = null): User
    {
        return User::factory()->create([
            'role' => $role,
            'is_active' => true,
            'branch_id' => $branch?->id,
        ]);
    }

    private function openSession(User $owner, Branch $branch): CashRegisterSession
    {
        return CashRegisterSession::factory()->create([
            'user_id' => $owner->id,
            'branch_id' => $branch->id,
            'status' => 'open',
            'opening_amount' => 100.0,
        ]);
    }

    public function test_a_recepcionista_without_an_open_session_is_never_answered_another_users_drawer(): void
    {
        $branch = Branch::factory()->create();
        $owner = $this->user('recepcionista', $branch);
        $peer = $this->user('recepcionista', $branch);
        $this->openSession($owner, $branch);

        $this->actingAs($peer, 'sanctum')
            ->getJson('/api/cash-register/current')
            ->assertOk()
            ->assertJsonPath('data', null);
    }

    public function test_the_owner_is_answered_their_own_session(): void
    {
        $branch = Branch::factory()->create();
        $owner = $this->user('recepcionista', $branch);
        $this->openSession($owner, $branch);

        $this->actingAs($owner, 'sanctum')
            ->getJson('/api/cash-register/current')
            ->assertOk()
            ->assertJsonPath('data.session.user_id', $owner->id);
    }

    public function test_an_administrator_is_still_answered_the_latest_session_of_the_branch(): void
    {
        $branch = Branch::factory()->create();
        $owner = $this->user('recepcionista', $branch);
        $admin = $this->user('administrador', $branch);
        $this->openSession($owner, $branch);

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/cash-register/current')
            ->assertOk()
            ->assertJsonPath('data.session.user_id', $owner->id);
    }

    public function test_a_second_session_for_the_same_user_and_branch_names_the_real_rule(): void
    {
        $branch = Branch::factory()->create();
        $recepcionista = $this->user('recepcionista', $branch);
        $payload = [
            'branch_id' => $branch->id,
            'opening_amount' => 100.0,
        ];

        $this->actingAs($recepcionista, 'sanctum')
            ->postJson('/api/cash-register/open', $payload)
            ->assertCreated();

        $this->actingAs($recepcionista, 'sanctum')
            ->postJson('/api/cash-register/open', $payload)
            ->assertStatus(422)
            ->assertJsonPath(
                'errors.session.0',
                'Ya existe una sesión de caja abierta para este usuario y sucursal.'
            );
    }
}
