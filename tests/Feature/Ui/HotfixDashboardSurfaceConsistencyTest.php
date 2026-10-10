<?php

namespace Tests\Feature\Ui;

use App\Models\User;
use Tests\TestCase;

/**
 * HOTFIX-DASH-005 / WU3 — Quick Actions surface consistency, retired with
 * the tiles.
 *
 * The rule compared Quick Action card surfaces against KPI cards
 * (design-taste §4.4 Shape Consistency Lock). WU3 / D5 removed the whole
 * quick-actions block, so the rule is pinned as a REMOVAL: the dashboard
 * renders zero data-action navigation cards, and no card surface anywhere
 * on the page drifts to the retired --elevation-1 rung while the KPI
 * strip consumes --elevation-2.
 */
class HotfixDashboardSurfaceConsistencyTest extends TestCase
{

    private const DASHBOARD_PAGE_REL = '/resources/js/modules/dashboard/DashboardPage.vue';

    private static function dashboardPagePath(): string
    {
        return dirname(__DIR__, 3) . self::DASHBOARD_PAGE_REL;
    }

    public function test_quick_action_cards_are_removed_with_the_block(): void
    {
        $user = User::factory()->make();
        $response = $this->actingAs($user)->get('/dashboard');
        $response->assertStatus(200);

        $this->assertFileExists(
            self::dashboardPagePath(),
            'DashboardPage.vue must exist (HOTFIX-DASH-005 source boundary)'
        );

        $source = (string) file_get_contents(self::dashboardPagePath());

        $quickActionCount = preg_match_all(
            '/\bdata-action\s*=\s*[\'"][^\'"]+[\'"]/i',
            $source
        );

        $this->assertSame(
            0,
            (int) $quickActionCount,
            'DashboardPage.vue must render zero Quick Action cards (data-action attribute) — WU3 / D5 (the tiles re-listed sidebar navigation).'
        );
    }

    /**
     * T4 elevation alignment (follow-up from T2b). The HOTFIX-DASH-005
     * comment already declared --elevation-2 as the shared rung. Pin the
     * rung on the surfaces that remain so no card can drift back to
     * --elevation-1.
     */
    public function test_remaining_cards_share_the_kpi_elevation_rung(): void
    {
        $source = (string) file_get_contents(self::dashboardPagePath());

        $this->assertDoesNotMatchRegularExpression(
            '/--elevation-1/',
            $source,
            'DashboardPage.vue MUST NOT keep the old var(--elevation-1) rung on any card surface (T4 elevation alignment, HOTFIX-DASH-005).'
        );
        $this->assertMatchesRegularExpression(
            '/--elevation-2/',
            $source,
            'DashboardPage.vue MUST keep var(--elevation-2) as the shared card rung (T4 elevation alignment, HOTFIX-DASH-005).'
        );
    }
}
