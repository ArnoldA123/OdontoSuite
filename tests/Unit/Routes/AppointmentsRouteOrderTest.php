<?php

namespace Tests\Unit\Routes;

use Tests\TestCase;

/**
 * Fix live-probes #25/#26 — route registration order for live probes.
 *
 * routes/api.php now registers the Billing group (which owns
 * `appointments/ready-to-bill`) BEFORE `Route::apiResource('appointments', ...)`.
 * Previously the resource was registered first, so `appointments/{appointment}`
 * shadowed the fixed segment: Laravel's route matcher hit the parameterized
 * route first, tried to resolve `ready-to-bill` as a model bound Appointment,
 * and returned 404 for every role.
 *
 * These tests pin the order and the permission protection so a future
 * reordering cannot silently reintroduce the shadowing.
 */
class AppointmentsRouteOrderTest extends TestCase
{
    private const READY_TO_BILL_URI = 'api/appointments/ready-to-bill';

    private const SHOW_URI = 'api/appointments/{appointment}';

    private const BILLING_ROLES = ['administrador', 'finanzas', 'recepcionista'];

    private static function routesFile(): string
    {
        return dirname(__DIR__, 3).'/routes/api.php';
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private static function registeredRoutes(): array
    {
        $root = dirname(__DIR__, 3);
        $command = 'cd '.escapeshellarg($root).' && '.escapeshellarg(PHP_BINARY).' artisan route:list --json';

        $lines = [];
        $status = 0;
        exec($command, $lines, $status);

        $output = implode("\n", $lines);

        if ($status !== 0) {
            self::fail('`php artisan route:list --json` exited with status '.$status.":\n".$output);
        }

        $routes = json_decode($output, true);

        if (! is_array($routes)) {
            self::fail("`php artisan route:list --json` did not return decodable JSON:\n".$output);
        }

        return $routes;
    }

    public function test_fixed_segment_routes_register_before_parameter_routes(): void
    {
        $routes = self::registeredRoutes();

        $readyToBillIndex = null;
        $showIndex = null;

        foreach ($routes as $index => $route) {
            $method = (string) ($route['method'] ?? '');
            $uri = (string) ($route['uri'] ?? '');

            if (! str_contains($method, 'GET')) {
                continue;
            }

            if ($uri === self::READY_TO_BILL_URI) {
                $readyToBillIndex = $index;
            }

            if ($uri === self::SHOW_URI) {
                $showIndex = $index;
            }
        }

        $this->assertNotNull(
            $readyToBillIndex,
            'Missing GET route `'.self::READY_TO_BILL_URI.'` in route:list. The Billing hook route must exist.'
        );

        $this->assertNotNull(
            $showIndex,
            'Missing GET route `'.self::SHOW_URI.'` in route:list. The appointments resource show route must exist.'
        );

        $this->assertLessThan(
            $showIndex,
            $readyToBillIndex,
            '`'.self::READY_TO_BILL_URI.'` must be registered BEFORE `'.self::SHOW_URI.'` '
                .'(ready-to-bill='.$readyToBillIndex.', show='.$showIndex.'). '
                .'Otherwise the fixed segment is swallowed by `{appointment}` model binding, which tries to '
                .'resolve "ready-to-bill" as an Appointment and returns 404 for every role.'
        );
    }

    public function test_billing_route_keeps_its_permission(): void
    {
        $route = collect(\Illuminate\Support\Facades\Route::getRoutes())
            ->first(fn ($route) => $route->uri() === 'api/appointments/ready-to-bill');

        $this->assertNotNull($route, 'routes/api.php must keep the ready-to-bill route.');

        $middleware = implode(',', $route->middleware());
        $this->assertStringContainsString(
            'permission:view.billing',
            $middleware,
            'ready-to-bill must be guarded by view.billing. Found: '.$middleware
        );

        // The permission matrix is the single source of truth (#280): the
        // roles of view.billing must stay exactly the billing roles.
        $this->assertSame(
            self::BILLING_ROLES,
            \App\Authorization\Permissions::rolesFor(\App\Authorization\Permissions::VIEW_BILLING),
            'view.billing must grant exactly the billing roles.'
        );
    }
}
