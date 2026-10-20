<?php

namespace Tests\Feature\Api;

use App\Models\CashRegisterSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Issue #193 — the session detail, its movement list and its closure report
 * were readable by every role admitted into the cash group, while
 * CashMovementPolicy already restricted the same resource to the session owner
 * (or an administrador). This pins the ownership rule on every session read
 * surface, including the `/cash-register/sessions/{id}` aliases.
 *
 * Must run against the MySQL harness (AGENTS.md §6).
 */
class CashSessionOwnershipTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role): User
    {
        return User::factory()->create([
            'role' => $role,
            'is_active' => true,
        ]);
    }

    private function sessionFor(User $owner): CashRegisterSession
    {
        return CashRegisterSession::factory()->create([
            'user_id' => $owner->id,
            'status' => 'open',
            'opening_amount' => 100.0,
        ]);
    }

    public static function sessionReadSurfaceProvider(): array
    {
        return [
            'resource show' => ['/api/cash-register-sessions/%d', 'json'],
            'alias show' => ['/api/cash-register/sessions/%d', 'json'],
            'resource movements alias' => ['/api/cash-register/sessions/%d/movements', 'json'],
            'closure report' => ['/api/cash-register-sessions/%d/closure-report', 'pdf'],
        ];
    }

    /**
     * @dataProvider sessionReadSurfaceProvider
     */
    public function test_a_peer_cannot_read_another_users_session(string $uri, string $kind): void
    {
        $owner = $this->user('recepcionista');
        $peer = $this->user('recepcionista');
        $session = $this->sessionFor($owner);

        $response = $this->actingAs($peer, 'sanctum')
            ->get(sprintf($uri, $session->id));

        $response->assertForbidden();

        if ($kind === 'json') {
            $response->assertJsonPath('message', 'No tienes permisos para ver esta sesión de caja');
        }
    }

    public function test_finanzas_reads_only_its_own_session(): void
    {
        $owner = $this->user('recepcionista');
        $finanzas = $this->user('finanzas');
        $session = $this->sessionFor($owner);

        $this->actingAs($finanzas, 'sanctum')
            ->getJson("/api/cash-register-sessions/{$session->id}")
            ->assertForbidden();
    }

    public function test_the_owner_still_reads_their_own_session_and_movements(): void
    {
        $owner = $this->user('recepcionista');
        $session = $this->sessionFor($owner);

        $this->actingAs($owner, 'sanctum')
            ->getJson("/api/cash-register-sessions/{$session->id}")
            ->assertOk()
            ->assertJsonPath('data.session.id', $session->id);

        $this->actingAs($owner, 'sanctum')
            ->getJson("/api/cash-register/sessions/{$session->id}/movements")
            ->assertOk()
            ->assertJsonPath('meta.current_page', 1);
    }

    public function test_an_administrator_reads_any_session(): void
    {
        $owner = $this->user('recepcionista');
        $admin = $this->user('administrador');
        $session = $this->sessionFor($owner);

        $this->actingAs($admin, 'sanctum')
            ->getJson("/api/cash-register-sessions/{$session->id}")
            ->assertOk();

        $this->actingAs($admin, 'sanctum')
            ->get("/api/cash-register-sessions/{$session->id}/closure-report")
            ->assertOk();
    }

    public function test_a_missing_session_is_still_not_found(): void
    {
        $recepcionista = $this->user('recepcionista');

        $this->actingAs($recepcionista, 'sanctum')
            ->getJson('/api/cash-register-sessions/999999')
            ->assertNotFound();
    }
}
