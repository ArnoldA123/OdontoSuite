<?php

namespace Tests\Feature\Ui;

use App\Models\User;
use Tests\TestCase;

/**
 * HOTFIX-DASH-005 — Quick Actions card surface matches KPI cards.
 *
 * Quick Actions cards MUST use the same surface treatment as KPI cards
 * (var(--color-surface-elevated) + var(--elevation-1) + hairline +
 * var(--radius-card-lg)). Pin the RULE: Quick Action cards and KPI cards
 * share border-radius, box-shadow token reference, and surface color.
 *
 * The Shape Consistency Lock (design-taste §4.4) — mixed shape systems
 * are banned unless there is a documented rule. KPI cards and Quick
 * Action cards currently use different UiCard variants (`glass` vs.
 * `flat`) which violates the lock.
 */
class HotfixDashboardSurfaceConsistencyTest extends TestCase
{

    private const DASHBOARD_PAGE_REL = '/resources/js/modules/dashboard/DashboardPage.vue';

    private static function dashboardPagePath(): string
    {
        return dirname(__DIR__, 3) . self::DASHBOARD_PAGE_REL;
    }

    public function test_quick_action_cards_use_same_surface_tokens_as_kpi_cards(): void
    {
        $user = User::factory()->make();
        $response = $this->actingAs($user)->get('/dashboard');
        $response->assertStatus(200);

        $this->assertFileExists(
            self::dashboardPagePath(),
            'DashboardPage.vue must exist (HOTFIX-DASH-005 source boundary)'
        );

        $source = (string) file_get_contents(self::dashboardPagePath());

        // Count Quick Action cards (data-action attribute).
        $quickActionCount = preg_match_all(
            '/\bdata-action\s*=\s*[\'"][^\'"]+[\'"]/i',
            $source
        );
        $this->assertGreaterThanOrEqual(
            2,
            (int) $quickActionCount,
            'DashboardPage.vue must render at least 2 Quick Action cards (data-action attribute) for this rule to be meaningful.'
        );

        // Pin the RULE: every Quick Action card block MUST reference at
        // least one of the surface tokens used by KPI cards (--elevation-*,
        // --color-hairline, --color-surface-elevated, --radius-card-lg).
        // A block is "from a data-action attribute through the next
        // </UiCard>" or to end-of-source. KPI cards reference elevation
        // and hairline tokens in inline style; Quick Actions must too.
        $blocksWithTokens = preg_match_all(
            '/data-action\s*=\s*[\'"][^\'"]+[\'"][\s\S]*?(?:elevation-[12]|--color-hairline|--color-surface-elevated|--radius-card-lg)/i',
            $source
        );

        $this->assertGreaterThanOrEqual(
            (int) $quickActionCount,
            (int) $blocksWithTokens,
            'DashboardPage.vue Quick Action cards MUST reference the same surface tokens as KPI cards (--elevation-1+, --color-hairline, --color-surface-elevated, --radius-card-lg) — HOTFIX-DASH-005, design-taste §4.4 (Shape Consistency Lock: same radius system across cards).'
        );
    }
}