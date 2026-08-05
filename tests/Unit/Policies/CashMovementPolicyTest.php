<?php

namespace Tests\Unit\Policies;

use App\Models\CashMovement;
use App\Models\User;
use PHPUnit\Framework\TestCase;

/**
 * Bugfix-2026-08 slice 09 — RED test for the CashMovementPolicy (FF-001 defense
 * in depth).
 *
 * Before this slice, `app/Policies/CashMovementPolicy.php` did NOT exist. The
 * only authorization gate on POST /api/cash-movements was the route middleware
 * `role:administrador,finanzas`. This slice introduces a Policy class so that
 * controller-side `$user->can('create', CashMovement::class)` checks and future
 * programmatic authorization have a single source of truth.
 *
 * Acceptance:
 *  - The class exists and has a public `create(User $user)` method.
 *  - Returns true for `administrador` and `finanzas`.
 *  - Returns false for every other role.
 */
class CashMovementPolicyTest extends TestCase
{
    public static function policyClass(): string
    {
        return 'App\\Policies\\CashMovementPolicy';
    }

    /**
     * Instantiate the policy via reflection so the test fails loudly when the
     * class itself is missing (rather than throwing a runtime exception).
     */
    private function policy(): \App\Policies\CashMovementPolicy
    {
        $class = self::policyClass();
        $this->assertTrue(
            class_exists($class),
            "Policy class {$class} must exist (FF-001)"
        );
        return new $class();
    }

    private function userWithRole(string $role): User
    {
        $user = new User();
        $user->role = $role;
        return $user;
    }

    /** @test FF-001 */
    public function policy_class_exists_with_create_method(): void
    {
        $class = self::policyClass();
        $this->assertTrue(
            class_exists($class),
            "CashMovementPolicy class must exist at app/Policies/CashMovementPolicy.php"
        );
        $this->assertTrue(
            method_exists($class, 'create'),
            'CashMovementPolicy must declare a public create(User $user) method'
        );
    }

    /** @test FF-001 */
    public function create_returns_true_for_administrador(): void
    {
        $policy = $this->policy();
        $this->assertTrue(
            $policy->create($this->userWithRole('administrador')),
            'administrador must be allowed to create cash movements'
        );
    }

    /** @test FF-001 */
    public function create_returns_true_for_finanzas(): void
    {
        $policy = $this->policy();
        $this->assertTrue(
            $policy->create($this->userWithRole('finanzas')),
            'finanzas must be allowed to create cash movements'
        );
    }

    /**
     * @test FF-001
     * @dataProvider nonAllowedRoles
     */
    public function create_returns_false_for_non_allowed_roles(string $role): void
    {
        $policy = $this->policy();
        $this->assertFalse(
            $policy->create($this->userWithRole($role)),
            "Role {$role} must NOT be allowed to create cash movements"
        );
    }

    public static function nonAllowedRoles(): array
    {
        return [
            'odontologo' => ['odontologo'],
            'implantologo' => ['implantologo'],
            'tecnico_dental' => ['tecnico_dental'],
            'asistente' => ['asistente'],
            'recepcionista' => ['recepcionista'],
        ];
    }

    /** @test FF-001 */
    public function policy_is_aligned_with_backend_route_middleware(): void
    {
        // The Policy and the route middleware MUST agree on the allowed roles.
        // routes/api.php:345 wraps cash-movements in
        // `role:administrador,finanzas`. We assert the policy's allowed roles
        // are exactly that set.
        $source = file_get_contents(__DIR__ . '/../../../routes/api.php');
        $this->assertNotFalse($source);

        // Find the line that defines cash-movements registration.
        $this->assertMatchesRegularExpression(
            "/apiResource\\(\\s*['\"]cash-movements['\"]/",
            $source,
            'routes/api.php must still register the cash-movements apiResource'
        );

        // Confirm the role middleware wrapping.
        $this->assertStringContainsString(
            "Route::middleware('role:administrador,finanzas')",
            $source,
            'cash-movements resource must remain wrapped in role:administrador,finanzas middleware'
        );

        // And the policy itself must use the same role set (no drift).
        $policySource = file_get_contents(__DIR__ . '/../../../app/Policies/CashMovementPolicy.php');
        $this->assertNotFalse($policySource);
        $this->assertStringContainsString(
            "['administrador', 'finanzas']",
            $policySource,
            'CashMovementPolicy::create must allow exactly the same roles as the route middleware'
        );
    }
}