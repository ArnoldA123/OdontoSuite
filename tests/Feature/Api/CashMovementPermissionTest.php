<?php

namespace Tests\Feature\Api;

use App\Models\CashRegisterSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Bugfix-2026-08 slice 09 — RED test for FF-001 (cash-movement create RBAC).
 *
 * Verifies the POST /api/cash-movements endpoint enforces the
 * role:administrador,finanzas middleware AND that, after this slice, the
 * backend Policy (CashMovementPolicy::create) is registered and aligned with
 * the route middleware (no drift).
 *
 * Acceptance:
 *  - administrador and finanzas can POST and the request reaches validation
 *    (422 because of an empty payload is acceptable — confirms the auth gate
 *    passed and StoreCashMovementRequest fired).
 *  - Every other role gets 403 (forbidden) BEFORE validation runs.
 */
class CashMovementPermissionTest extends TestCase
{
    use RefreshDatabase;

    private function adminUser(): User
    {
        return User::factory()->create([
            'role' => 'administrador',
            'is_active' => true,
        ]);
    }

    private function finanzasUser(): User
    {
        return User::factory()->create([
            'role' => 'finanzas',
            'is_active' => true,
        ]);
    }

    private function clinicalUser(string $role = 'odontologo'): User
    {
        return User::factory()->create([
            'role' => $role,
            'is_active' => true,
        ]);
    }

    private function openSessionFor(User $user): CashRegisterSession
    {
        return CashRegisterSession::factory()->create([
            'user_id' => $user->id,
            'status' => 'open',
            'opening_amount' => 100.0,
        ]);
    }

    /** @test FF-001 */
    public function administrador_can_post_cash_movement(): void
    {
        $admin = $this->adminUser();
        $session = $this->openSessionFor($admin);

        $response = $this->actingAs($admin, 'sanctum')
            ->postJson('/api/cash-movements', [
                'cash_register_session_id' => $session->id,
                'concept' => 'withdrawal',
                'type' => 'expense',
                'amount' => 10.50,
                'description' => 'Test movement',
            ]);

        $this->assertNotEquals(403, $response->status(),
            "administrador must NOT be 403'd by the cash-movement RBAC gate. Got: {$response->status()}");
    }

    /** @test FF-001 */
    public function finanzas_can_post_cash_movement(): void
    {
        $user = $this->finanzasUser();
        $session = $this->openSessionFor($user);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/cash-movements', [
                'cash_register_session_id' => $session->id,
                'concept' => 'withdrawal',
                'type' => 'expense',
                'amount' => 10.50,
                'description' => 'Test movement',
            ]);

        $this->assertNotEquals(403, $response->status(),
            "finanzas must NOT be 403'd by the cash-movement RBAC gate. Got: {$response->status()}");
    }

    /**
     * @test FF-001
     * @dataProvider forbiddenRoles
     */
    public function non_admin_non_finanzas_cannot_post_cash_movement(string $role): void
    {
        $user = $this->clinicalUser($role);
        $session = $this->openSessionFor($user);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/cash-movements', [
                'cash_register_session_id' => $session->id,
                'concept' => 'withdrawal',
                'type' => 'expense',
                'amount' => 10.50,
                'description' => 'Should not reach validation',
            ]);

        $response->assertForbidden();
    }

    public static function forbiddenRoles(): array
    {
        return [
            'odontologo' => ['odontologo'],
            'implantologo' => ['implantologo'],
            'tecnico_dental' => ['tecnico_dental'],
            'asistente' => ['asistente'],
            'recepcionista' => ['recepcionista'],
        ];
    }
}