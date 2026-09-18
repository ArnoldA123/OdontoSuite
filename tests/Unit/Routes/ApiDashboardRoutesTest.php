<?php

namespace Tests\Unit\Routes;

use PHPUnit\Framework\TestCase;

/**
 * Bugfix-2026-08 slice 11 — BF-026 dashboard route dedupe RED test.
 *
 * routes/api.php currently registers BOTH
 *   Route::get('dashboard/today', [DashboardController::class, 'today']);
 *   Route::get('dashboard/appointments-today', [DashboardController::class, 'today']);
 * Both routes hit the same controller method, which is dead duplication.
 * The fix removes one. Frontend already uses /dashboard/appointments-today.
 */
class ApiDashboardRoutesTest extends TestCase
{
private static function routesFile(): string { return dirname(__DIR__, 3) . '/routes/api.php'; }

    public function test_dashboard_today_alias_route_is_removed(): void
    {
        $source = file_get_contents(self::routesFile());
        $this->assertNotFalse($source);

        // The duplicate "dashboard/today" route must be removed; only
        // "dashboard/appointments-today" should remain (canonical name).
        $this->assertStringNotContainsString(
            "Route::get('dashboard/today'",
            $source,
            'BF-026: dashboard/today is a duplicate of dashboard/appointments-today and must be removed'
        );

        // The canonical /dashboard/appointments-today route MUST remain.
        $this->assertStringContainsString(
            "Route::get('dashboard/appointments-today'",
            $source,
            'BF-026: dashboard/appointments-today must remain as the canonical today-route'
        );
    }

    public function test_dashboard_stats_route_is_preserved(): void
    {
        $source = file_get_contents(self::routesFile());

        $this->assertStringContainsString(
            "Route::get('dashboard/stats'",
            $source,
            'dashboard/stats route must remain untouched'
        );

        $this->assertStringContainsString(
            "Route::get('dashboard/upcoming'",
            $source,
            'dashboard/upcoming route must remain untouched'
        );
    }
}
