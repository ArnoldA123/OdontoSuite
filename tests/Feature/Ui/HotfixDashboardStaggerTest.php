<?php

namespace Tests\Feature\Ui;

use App\Models\User;
use Tests\TestCase;

/**
 * HOTFIX-DASH-009 — Per-section staggered springs (4 sections, 60ms stagger).
 *
 * Dashboard MUST attach useSpring({ damping: 1.0, response: 0.35 }) to each
 * of: greeting block, KPI grid, Quick Actions, Citas de Hoy empty state.
 * Sections MUST enter with stagger delays of 0ms, 60ms, 120ms, 180ms (via
 * setTimeout post-mount). Pin the RULE: 4 distinct useSpring instances on
 * Dashboard page sections. (apple-design §4 springs for entrance — default
 * to damping 1.0 critically damped; apple-design §8 intermediate frames
 * telegraph direction — stagger tells the eye where the eye should land.)
 */
class HotfixDashboardStaggerTest extends TestCase
{

    private const DASHBOARD_PAGE_REL = '/resources/js/modules/dashboard/DashboardPage.vue';

    private static function dashboardPagePath(): string
    {
        return dirname(__DIR__, 3) . self::DASHBOARD_PAGE_REL;
    }

    /**
     * Extract every useSpring({ ... }) options literal from the file.
     *
     * @return string[]
     */
    private static function extractUseSpringOptions(string $source): array
    {
        $opts = [];
        $pattern = '/useSpring\s*\(\s*\{([^{}]*(?:\{[^{}]*\}[^{}]*)*)\}\s*\)/s';
        if (!preg_match_all($pattern, $source, $matches)) {
            return $opts;
        }
        foreach ($matches[1] as $body) {
            $opts[] = $body;
        }
        return $opts;
    }

    public function test_dashboard_attaches_at_least_four_distinct_use_spring_instances(): void
    {
        $user = User::factory()->make();
        $response = $this->actingAs($user)->get('/dashboard');
        $response->assertStatus(200);

        $this->assertFileExists(
            self::dashboardPagePath(),
            'DashboardPage.vue must exist (HOTFIX-DASH-009 source boundary)'
        );

        $source = (string) file_get_contents(self::dashboardPagePath());
        $opts = self::extractUseSpringOptions($source);

        $this->assertGreaterThanOrEqual(
            4,
            count($opts),
            'DashboardPage.vue MUST attach at least FOUR distinct useSpring instances (greeting, KPI grid, Quick Actions, Citas de Hoy empty state) — HOTFIX-DASH-009, apple-design §4 (springs for entrance), §8 (intermediate frames telegraph direction).'
        );

        // Each spring MUST declare a distinct cssVar so the four entrance
        // animations cannot collide on the same CSS custom property.
        $cssVars = [];
        foreach ($opts as $body) {
            if (preg_match('/cssVar\s*:\s*[\'"]([^\'"]+)[\'"]/i', $body, $vm)) {
                $cssVars[] = $vm[1];
            }
        }
        $uniqueCssVars = array_values(array_unique($cssVars));
        $this->assertGreaterThanOrEqual(
            4,
            count($uniqueCssVars),
            'DashboardPage.vue MUST declare at least FOUR distinct cssVar tokens across useSpring calls — HOTFIX-DASH-009 (greeting + KPI grid + Quick Actions + Citas de Hoy each need their own cssVar).'
        );
    }
}