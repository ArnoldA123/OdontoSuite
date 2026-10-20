<?php

namespace Tests\Feature\Api;

use App\Http\Controllers\Api\CashRegisterController;
use App\Models\CashRegisterSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Issue #194 — three contract warts on the same resource:
 *  - GET /api/cash-register/summary used to pick the latest session of the
 *    day without a user scope, so a recepcionista could be answered with
 *    somebody else's drawer;
 *  - POST /api/cash-register-sessions/{id}/open ignored `{id}` and delegated
 *    to the generic open;
 *  - currentView() had no route at all.
 *
 * Must run against the MySQL harness (AGENTS.md §6).
 */
class CashRegisterContractWartsTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role): User
    {
        return User::factory()->create([
            'role' => $role,
            'is_active' => true,
        ]);
    }

    private function sessionAt(User $owner, string $time, float $opening): CashRegisterSession
    {
        return CashRegisterSession::factory()->create([
            'user_id' => $owner->id,
            'status' => 'open',
            'opening_amount' => $opening,
            'opened_at' => now()->setTime((int) substr($time, 0, 2), (int) substr($time, 3, 2)),
        ]);
    }

    public function test_a_recepcionista_only_sees_their_own_summary_of_the_day(): void
    {
        $first = $this->user('recepcionista');
        $second = $this->user('recepcionista');
        $this->sessionAt($first, '09:00', 100.0);
        $this->sessionAt($second, '10:00', 500.0);

        $this->actingAs($second, 'sanctum')
            ->getJson('/api/cash-register/summary')
            ->assertOk()
            ->assertJsonPath('data.session.user_id', $second->id)
            ->assertJsonPath('data.opening_amount', '500.00');

        $this->actingAs($first, 'sanctum')
            ->getJson("/api/cash-register/summary?user_id={$second->id}")
            ->assertOk()
            ->assertJsonPath('data.session.user_id', $first->id)
            ->assertJsonPath('data.opening_amount', '100.00');
    }

    public function test_a_cash_manager_can_summarize_another_users_session(): void
    {
        $owner = $this->user('recepcionista');
        $manager = $this->user('administrador');
        $this->sessionAt($owner, '09:00', 100.0);

        $this->actingAs($manager, 'sanctum')
            ->getJson("/api/cash-register/summary?user_id={$owner->id}")
            ->assertOk()
            ->assertJsonPath('data.session.user_id', $owner->id)
            ->assertJsonPath('data.opening_amount', '100.00');
    }

    public function test_the_summary_without_a_session_answers_an_empty_summary(): void
    {
        $user = $this->user('recepcionista');

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/cash-register/summary')
            ->assertOk()
            ->assertJsonPath('data.session', null);
    }

    public function test_the_open_route_with_an_id_no_longer_exists(): void
    {
        $manager = $this->user('administrador');
        $session = $this->sessionAt($manager, '09:00', 100.0);

        $this->actingAs($manager, 'sanctum')
            ->postJson("/api/cash-register-sessions/{$session->id}/open", [
                'branch_id' => $session->branch_id,
                'opening_amount' => 100.0,
            ])
            ->assertNotFound();
    }

    public function test_the_dead_current_view_method_is_gone(): void
    {
        $this->assertFalse(method_exists(CashRegisterController::class, 'currentView'));
    }
}
