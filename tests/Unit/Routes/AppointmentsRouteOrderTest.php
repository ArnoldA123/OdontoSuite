<?php

namespace Tests\Unit\Routes;

use PHPUnit\Framework\TestCase;

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
 * These tests pin the order and the role protection so a future reordering
 * cannot silently reintroduce the shadowing.
 */
class AppointmentsRouteOrderTest extends TestCase
{
    private const READY_TO_BILL_URI = 'api/appointments/ready-to-bill';
    private const SHOW_URI = 'api/appointments/{appointment}';
    private const BILLING_ROLES = ['administrador', 'finanzas', 'recepcionista'];

    private static function routesFile(): string
    {
        return dirname(__DIR__, 3) . '/routes/api.php';
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private static function registeredRoutes(): array
    {
        $root = dirname(__DIR__, 3);
        $command = 'cd ' . escapeshellarg($root) . ' && ' . escapeshellarg(PHP_BINARY) . ' artisan route:list --json';

        $lines = [];
        $status = 0;
        exec($command, $lines, $status);

        $output = implode("\n", $lines);

        if ($status !== 0) {
            self::fail('`php artisan route:list --json` exited with status ' . $status . ":\n" . $output);
        }

        $routes = json_decode($output, true);

        if (!is_array($routes)) {
            self::fail("`php artisan route:list --json` did not return decodable JSON:\n" . $output);
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

            if (!str_contains($method, 'GET')) {
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
            'Missing GET route `' . self::READY_TO_BILL_URI . '` in route:list. The Billing hook route must exist.'
        );

        $this->assertNotNull(
            $showIndex,
            'Missing GET route `' . self::SHOW_URI . '` in route:list. The appointments resource show route must exist.'
        );

        $this->assertLessThan(
            $showIndex,
            $readyToBillIndex,
            '`' . self::READY_TO_BILL_URI . '` must be registered BEFORE `' . self::SHOW_URI . '` '
                . '(ready-to-bill=' . $readyToBillIndex . ', show=' . $showIndex . '). '
                . 'Otherwise the fixed segment is swallowed by `{appointment}` model binding, which tries to '
                . 'resolve "ready-to-bill" as an Appointment and returns 404 for every role.'
        );
    }

    public function test_billing_group_keeps_its_roles(): void
    {
        $source = (string) file_get_contents(self::routesFile());

        // Match every top-level `Route::middleware('role:...')->group(function () { ... });`
        // block. Nested groups are indented deeper, so the non-greedy body stops at the
        // block's own 4-space-indented closing brace.
        $pattern = "/Route::middleware\\('role:([^']+)'\\)->group\\(function \\(\\) \\{(.*?)\\n    \\}\\);/s";
        preg_match_all($pattern, $source, $matches, PREG_SET_ORDER);

        $billingBlocks = array_values(array_filter(
            $matches,
            static fn (array $match): bool => str_contains($match[2], "'appointments/ready-to-bill'")
        ));

        $this->assertCount(
            1,
            $billingBlocks,
            'Expected exactly one `role:` group in routes/api.php to contain `appointments/ready-to-bill`.'
        );

        $roles = array_map('trim', explode(',', $billingBlocks[0][1]));

        foreach (self::BILLING_ROLES as $role) {
            $this->assertContains(
                $role,
                $roles,
                'The Billing group containing `appointments/ready-to-bill` must keep role `' . $role . '`. '
                    . 'Found roles: ' . implode(',', $roles) . '. A reorder must not change this protection.'
            );
        }
    }
}
