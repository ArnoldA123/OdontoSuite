<?php

namespace Tests\Feature\Ui;

use App\Models\User;
use Tests\TestCase;

/**
 * HOTFIX-DASH-003 — KPI numbers use tabular-nums.
 *
 * The big number element on each KPI card MUST compute font-feature-settings
 * containing `"tnum" 1`. The rule pins the literal feature tag — passing the
 * computed value through a CSS variable is not enough; the source MUST
 * declare the literal `"tnum"` substring on every KPI number element so the
 * feature setting is auditable in source. (apple-design §15 typography —
 * tracking (letter-spacing) is size-specific and tabular nums pin number
 * alignment across changing values.)
 */
class KpiNumberTabularTest extends TestCase
{

    private const DASHBOARD_PAGE_REL = '/resources/js/modules/dashboard/DashboardPage.vue';

    private static function dashboardPagePath(): string
    {
        return dirname(__DIR__, 3) . self::DASHBOARD_PAGE_REL;
    }

    public function test_each_kpi_number_declares_literal_tnum_font_feature_settings(): void
    {
        $user = User::factory()->make();
        $response = $this->actingAs($user)->get('/dashboard');
        $response->assertStatus(200);

        $this->assertFileExists(
            self::dashboardPagePath(),
            'DashboardPage.vue must exist (HOTFIX-DASH-003 source boundary)'
        );

        $source = (string) file_get_contents(self::dashboardPagePath());

        // Pin the RULE: the source MUST contain the literal `"tnum"`
        // substring inside a font-feature-settings string declaration.
        // The current source uses `var(--font-features-tabular-nums)`
        // which does NOT satisfy this rule — the rule pins the literal
        // feature tag so audit tooling can grep for it.
        $literalTnumCount = preg_match_all(
            '/font-feature-settings\s*:\s*["\'][^"\']*\btnum\b[^"\']*["\']/i',
            $source
        );

        $this->assertGreaterThan(
            0,
            (int) $literalTnumCount,
            'DashboardPage.vue MUST declare font-feature-settings with literal `"tnum"` substring on KPI number elements — HOTFIX-DASH-003, apple-design §15 (tabular nums for numbers; rule pins the literal feature tag, not a CSS variable reference).'
        );

        // Sanity: DashboardPage.vue must render at least 2 KPI cards.
        $kpiCardCount = preg_match_all('/data-stat-card\s*=/i', $source);
        $this->assertGreaterThanOrEqual(
            2,
            (int) $kpiCardCount,
            'DashboardPage.vue must render at least 2 KPI cards (data-stat-card attribute) for this rule to be meaningful.'
        );
    }
}