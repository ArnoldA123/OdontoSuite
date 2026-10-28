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
 * #407 — the administrator exemption had the same defect: an administrator
 * with no drawer of their own was answered somebody else's open session and
 * the screen offered close and deposit over it. `/cash-register/current` is
 * now always the actor's own drawer: the open one, or their last one when it
 * is closed. Reading a foreign drawer stays on
 * `GET /api/cash-register/sessions/{id}`, whose owner-or-administrador rule
 * (#193) is the labelled read.
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

    public function test_an_administrator_without_a_session_of_their_own_is_never_answered_another_users_drawer(): void
    {
        $branch = Branch::factory()->create();
        $owner = $this->user('recepcionista', $branch);
        $admin = $this->user('administrador', $branch);
        $this->openSession($owner, $branch);

        // Never the foreign session id: the payload is the empty state.
        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/cash-register/current')
            ->assertOk()
            ->assertJsonPath('data', null);
    }

    public function test_an_administrator_is_answered_their_own_open_session(): void
    {
        $branch = Branch::factory()->create();
        $peer = $this->user('recepcionista', $branch);
        $admin = $this->user('administrador', $branch);
        $own = $this->openSession($admin, $branch);
        // A newer foreign drawer must not win the ordering.
        $this->openSession($peer, $branch);

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/cash-register/current')
            ->assertOk()
            ->assertJsonPath('data.session.id', $own->id)
            ->assertJsonPath('data.session.user_id', $admin->id);
    }

    public function test_an_administrator_whose_last_session_is_closed_is_answered_that_session_with_its_closing_counts(): void
    {
        $branch = Branch::factory()->create();
        $admin = $this->user('administrador', $branch);
        $closed = CashRegisterSession::factory()->create([
            'user_id' => $admin->id,
            'branch_id' => $branch->id,
            'status' => 'closed',
            'opening_amount' => 100.0,
            'closing_amount' => 180.5,
            'expected_amount' => 170.0,
            'difference_amount' => 10.5,
            'closed_at' => now(),
            'closing_notes' => 'Cierre del turno',
        ]);

        $response = $this->actingAs($admin, 'sanctum')
            ->getJson('/api/cash-register/current')
            ->assertOk()
            ->assertJsonPath('data.session.id', $closed->id)
            ->assertJsonPath('data.session.status', 'closed')
            ->assertJsonPath('data.closing.closing_amount', 180.5)
            ->assertJsonPath('data.closing.difference_amount', 10.5)
            ->assertJsonPath('data.closing.closing_notes', 'Cierre del turno')
            ->assertJsonMissingPath('data.summary');

        // JSON drops the trailing zero of a whole number, so the amount is
        // compared numerically instead of by scalar type.
        $this->assertSame(170.0, (float) $response->json('data.closing.expected_amount'));
        $this->assertNotNull($response->json('data.closing.closed_at'));
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
