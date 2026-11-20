<?php

namespace Tests\Unit\Routes;

use PHPUnit\Framework\TestCase;

/**
 * Dashboard route surface after U2.
 *
 * The dashboard keeps the summary and the commercial pending answers. The two
 * appointment lists it used to publish (`dashboard/appointments-today` and
 * `dashboard/upcoming`) are gone with their page blocks: the agenda and the
 * professional desk own that data now.
 */
class ApiDashboardRoutesTest extends TestCase
{
    private static function routesFile(): string
    {
        return dirname(__DIR__, 3).'/routes/api.php';
    }

    private static function controllerFile(): string
    {
        return dirname(__DIR__, 3).'/app/Http/Controllers/Api/DashboardController.php';
    }

    public function test_dashboard_appointment_list_routes_are_retired(): void
    {
        $source = file_get_contents(self::routesFile());
        $this->assertNotFalse($source);

        // The daily alias, the canonical today route and the week preview all
        // left with the blocks they fed (U2).
        foreach ([
            "Route::get('dashboard/today'",
            "Route::get('dashboard/appointments-today'",
            "Route::get('dashboard/upcoming'",
        ] as $retired) {
            $this->assertStringNotContainsString(
                $retired,
                $source,
                "The dashboard must not publish {$retired}: the appointment lists live in the agenda."
            );
        }
    }

    public function test_dashboard_stats_and_pending_routes_are_preserved(): void
    {
        $source = file_get_contents(self::routesFile());

        $this->assertStringContainsString(
            "Route::get('dashboard/stats', [DashboardController::class, 'stats'])",
            $source,
            'dashboard/stats must remain registered: the dashboard publishes the day summary from it.'
        );

        $this->assertStringContainsString(
            "Route::get('dashboard/pending', [DashboardController::class, 'pending'])",
            $source,
            'dashboard/pending must stay registered inside the authenticated group: Pendientes consumes it.'
        );
    }

    public function test_dashboard_controller_drops_the_retired_actions(): void
    {
        $source = file_get_contents(self::controllerFile());
        $this->assertNotFalse($source);

        foreach (['function today(', 'function upcoming('] as $retired) {
            $this->assertStringNotContainsString(
                $retired,
                $source,
                "DashboardController must not keep {$retired}: its page block was retired (U2)."
            );
        }

        $this->assertStringContainsString(
            'public function stats(Request $request): JsonResponse',
            $source,
            'DashboardController@stats must stay untouched.'
        );
        $this->assertStringContainsString(
            'public function pending(Request $request): JsonResponse',
            $source,
            'DashboardController@pending must stay: Pendientes now consumes it.'
        );
        $this->assertStringNotContainsString(
            'AppointmentResource',
            $source,
            'DashboardController must not keep the AppointmentResource import once its two list actions are gone.'
        );
    }
}
