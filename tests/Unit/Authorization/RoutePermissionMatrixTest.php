<?php

namespace Tests\Unit\Authorization;

use App\Authorization\Permissions;
use App\Http\Controllers\Api\ConsultationController;
use App\Http\Controllers\Api\MedicalRecordController;
use App\Http\Controllers\Api\SpecialtyRecordController;
use App\Http\Controllers\Api\TreatmentPlanController;
use Illuminate\Routing\Route;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * #280 — route-level matrix guard.
 *
 * Each test reads the real route collection, so a new route cannot dodge the
 * agreement: authenticated routes must declare a permission, that permission
 * must exist in the catalog, and clinical write surfaces must not be granted
 * to reception. Role lists in routes/api.php fail the first test with a line.
 */
class RoutePermissionMatrixTest extends TestCase
{
    /** Public API routes: no session, therefore no permission. */
    private const PUBLIC_ROUTES = [
        'api/login',
        'api/auth/login',
        'api/auth/forgot-password',
        'api/auth/reset-password',
        'api/payments/webhooks/mercadopago',
    ];

    /** Session infrastructure: authenticated, but it is not a resource. */
    private const SESSION_ROUTES = [
        'api/auth/logout',
        'api/auth/me',
    ];

    /** Controller actions that write clinical data. Reception must not reach them. */
    private const CLINICAL_WRITE_ACTIONS = [
        ConsultationController::class => ['complete'],
        MedicalRecordController::class => [
            'store',
            'update',
            'destroy',
            'addEvolution',
            'updateEvolution',
            'deleteEvolution',
            'uploadAttachment',
            'deleteAttachment',
        ],
        SpecialtyRecordController::class => ['store', 'update', 'destroy'],
        TreatmentPlanController::class => [
            'store',
            'update',
            'destroy',
            'changeStatus',
            'duplicate',
            'addItem',
            'removeItem',
        ],
    ];

    /**
     * @return list<Route>
     */
    private function apiRoutes(): array
    {
        return array_values(array_filter(
            app('router')->getRoutes()->getRoutes(),
            fn (Route $route) => str_starts_with($route->uri(), 'api/'),
        ));
    }

    /**
     * @return list<string>
     */
    private function permissionsOf(Route $route): array
    {
        $permissions = [];

        foreach ($route->middleware() as $middleware) {
            if (! is_string($middleware) || ! str_starts_with($middleware, 'permission:')) {
                continue;
            }

            foreach (explode(',', substr($middleware, strlen('permission:'))) as $permission) {
                $permission = trim($permission);

                if ($permission !== '') {
                    $permissions[] = $permission;
                }
            }
        }

        return array_values(array_unique($permissions));
    }

    #[Test]
    public function no_route_declares_roles_instead_of_permissions(): void
    {
        $path = base_path('routes/api.php');

        foreach (file($path, FILE_IGNORE_NEW_LINES) as $index => $line) {
            $this->assertDoesNotMatchRegularExpression(
                '/[\'"]role:/',
                $line,
                sprintf('routes/api.php:%d declares roles; declare a permission instead.', $index + 1),
            );
        }
    }

    #[Test]
    public function every_session_route_declares_a_known_permission(): void
    {
        foreach ($this->apiRoutes() as $route) {
            if (in_array($route->uri(), self::PUBLIC_ROUTES, true)) {
                $this->assertSame([], $this->permissionsOf($route), "Public route [{$route->uri()}] declares a permission.");

                continue;
            }

            if (in_array($route->uri(), self::SESSION_ROUTES, true)) {
                $this->assertSame([], $this->permissionsOf($route), "Session route [{$route->uri()}] declares a permission.");

                continue;
            }

            $permissions = $this->permissionsOf($route);

            $this->assertNotSame(
                [],
                $permissions,
                "Route [{$route->uri()}] is not public and declares no permission.",
            );

            foreach ($permissions as $permission) {
                $this->assertTrue(
                    Permissions::exists($permission),
                    "Route [{$route->uri()}] declares unknown permission [{$permission}].",
                );
            }
        }
    }

    #[Test]
    public function every_catalog_permission_is_routed_or_frontend_only(): void
    {
        $routed = [];

        foreach ($this->apiRoutes() as $route) {
            foreach ($this->permissionsOf($route) as $permission) {
                $routed[$permission] = true;
            }
        }

        foreach (Permissions::all() as $permission) {
            $this->assertTrue(
                isset($routed[$permission]) || in_array($permission, Permissions::FRONTEND_ONLY, true),
                "Permission [{$permission}] has no route and is not declared frontend-only.",
            );
        }
    }

    #[Test]
    public function reception_is_forbidden_on_every_clinical_write_action(): void
    {
        $checked = 0;

        foreach ($this->apiRoutes() as $route) {
            $controller = $route->getControllerClass();
            $method = $route->getActionMethod();

            if (! isset(self::CLINICAL_WRITE_ACTIONS[$controller])) {
                continue;
            }

            if (! in_array($method, self::CLINICAL_WRITE_ACTIONS[$controller], true)) {
                continue;
            }

            $permissions = $this->permissionsOf($route);
            $this->assertNotSame([], $permissions, "Clinical route [{$route->uri()}] has no permission.");

            foreach ($permissions as $permission) {
                $this->assertFalse(
                    Permissions::roleHas('recepcionista', $permission),
                    "Reception reaches clinical write [{$route->uri()}] through [{$permission}].",
                );
            }

            $checked++;
        }

        $this->assertGreaterThanOrEqual(16, $checked, 'The clinical write surface shrank unexpectedly.');
    }

    #[Test]
    public function the_matrix_is_the_inverse_of_the_effective_permissions(): void
    {
        foreach (Permissions::ROLES as $role) {
            $effective = Permissions::forRole($role);

            foreach ($effective as $permission) {
                $this->assertContains($role, Permissions::rolesFor($permission));
            }

            foreach (array_diff(Permissions::all(), $effective) as $permission) {
                $this->assertNotContains($role, Permissions::rolesFor($permission));
            }
        }
    }
}
