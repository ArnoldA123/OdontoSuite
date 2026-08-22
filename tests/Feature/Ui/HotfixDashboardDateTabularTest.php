<?php

namespace Tests\Feature\Ui;

use App\Models\User;
use Tests\TestCase;

/**
 * HOTFIX-DASH-008 — Greeting date uses tabular-nums.
 *
 * The date string in the greeting block MUST compute font-feature-settings
 * containing `"tnum" 1`. Pin the RULE: greeting date element MUST declare
 * font-feature-settings with literal `"tnum"` substring. (apple-design §15
 * typography — tabular nums pin number alignment across changing values.)
 *
 * The greeting date is the <p> tag below the welcome line that renders
 * getTodayDate(). The current source declares neither `font-feature-settings`
 * nor `font-variant-numeric: tabular-nums` on that element.
 */
class HotfixDashboardDateTabularTest extends TestCase
{

    private const DASHBOARD_PAGE_REL = '/resources/js/modules/dashboard/DashboardPage.vue';

    private static function dashboardPagePath(): string
    {
        return dirname(__DIR__, 3) . self::DASHBOARD_PAGE_REL;
    }

    public function test_greeting_date_element_declares_literal_tnum_font_feature_settings(): void
    {
        $user = User::factory()->make();
        $response = $this->actingAs($user)->get('/dashboard');
        $response->assertStatus(200);

        $this->assertFileExists(
            self::dashboardPagePath(),
            'DashboardPage.vue must exist (HOTFIX-DASH-008 source boundary)'
        );

        $source = (string) file_get_contents(self::dashboardPagePath());

        // Pin the RULE: there MUST be at least one element in the source
        // whose font-feature-settings declaration contains the literal
        // `"tnum"` substring AND whose body is the greeting-date
        // interpolation ({{ getTodayDate() }}).
        //
        // We anchor the search on getTodayDate() so the test catches the
        // greeting block specifically and not just any KPI card. The
        // surrounding markup (the <p> rendered by getTodayDate()) MUST
        // carry a `font-feature-settings` declaration with literal "tnum".
        $matches = preg_match_all(
            '/getTodayDate\s*\(\s*\)[\s\S]{0,400}?font-feature-settings\s*:\s*["\'][^"\']*\btnum\b[^"\']*["\']/is',
            $source
        );

        $this->assertGreaterThan(
            0,
            (int) $matches,
            'DashboardPage.vue greeting-date block (around getTodayDate() interpolation) MUST declare font-feature-settings with literal `"tnum"` substring — HOTFIX-DASH-008, apple-design §15 (tabular nums for numbers, including date components).'
        );
    }
}