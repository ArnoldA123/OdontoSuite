<?php

namespace Tests\Feature\Api;

use App\Models\Branch;
use App\Models\CashRegisterSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Cash follow-ups S1/S2 — the cash-register-sessions resource published more
 * routes than the controller implements.
 *
 *  - S1: `POST /cash-register-sessions/{id}/close` ignored its `{id}` and
 *    delegated to the generic close, the exact twin of the `{id}/open` route
 *    removed in #194.
 *  - S2: `Route::apiResource(...)` published `store`, `update` and `destroy`,
 *    none of which CashRegisterController implements, so those requests died
 *    with a 500 "method does not exist".
 *
 * Must run against the MySQL harness (AGENTS.md §6).
 */
class CashRegisterPhantomRoutesTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role): User
    {
        return User::factory()->create([
            'role' => $role,
            'is_active' => true,
        ]);
    }

    private function openSession(User $owner): CashRegisterSession
    {
        return CashRegisterSession::factory()->create([
            'user_id' => $owner->id,
            'branch_id' => Branch::factory()->create()->id,
            'status' => 'open',
            'opening_amount' => 100.0,
        ]);
    }

    public function test_the_close_route_with_an_id_no_longer_exists(): void
    {
        $admin = $this->user('administrador');
        $session = $this->openSession($admin);

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/cash-register-sessions/{$session->id}/close", [
                'session_id' => $session->id,
                'closing_amount' => 150.0,
            ])
            ->assertNotFound();

        $this->assertSame(
            'open',
            $session->fresh()->status,
            'The removed {id}/close route MUST NOT reach the generic close.'
        );
    }

    public function test_the_generic_close_with_a_session_id_still_closes_the_session(): void
    {
        $admin = $this->user('administrador');
        $session = $this->openSession($admin);

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/cash-register/close', [
                'session_id' => $session->id,
                'closing_amount' => 150.0,
            ])
            ->assertOk()
            ->assertJsonPath('data.id', $session->id)
            ->assertJsonPath('data.status', 'closed');
    }

    public function test_the_phantom_resource_methods_are_not_allowed(): void
    {
        $admin = $this->user('administrador');
        $session = $this->openSession($admin);

        // The URIs still answer GET, so the method is not allowed rather than
        // the resource being gone.
        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/cash-register-sessions', [
                'branch_id' => $session->branch_id,
                'opening_amount' => 100.0,
            ])
            ->assertStatus(405);

        $this->actingAs($admin, 'sanctum')
            ->putJson("/api/cash-register-sessions/{$session->id}", ['status' => 'closed'])
            ->assertStatus(405);

        $this->actingAs($admin, 'sanctum')
            ->deleteJson("/api/cash-register-sessions/{$session->id}")
            ->assertStatus(405);
    }

    public function test_the_two_real_resource_reads_survive(): void
    {
        $owner = $this->user('recepcionista');
        $session = $this->openSession($owner);

        $this->actingAs($owner, 'sanctum')
            ->getJson('/api/cash-register-sessions')
            ->assertOk()
            ->assertJsonPath('meta.current_page', 1);

        $this->actingAs($owner, 'sanctum')
            ->getJson("/api/cash-register-sessions/{$session->id}")
            ->assertOk()
            ->assertJsonPath('data.session.id', $session->id);
    }
}
