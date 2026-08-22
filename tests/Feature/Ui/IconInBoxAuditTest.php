<?php

namespace Tests\Feature\Ui;

use App\Models\User;
use Tests\TestCase;

/**
 * HOTFIX-DASH-002 — KPI cards have NO icon-in-rounded-gray-box.
 *
 * The KPI card surface MUST NOT contain a <div> or <span> with classes
 * combining `rounded` AND `bg-system-gray-*` (or `bg-neutral-*`) AND
 * small icon size — the Material-leak pattern.
 *
 * Pins the RULE: scan DashboardPage.vue for the Material icon-in-box
 * pattern (rounded + bg-system-gray-* on a KPI card). Assert zero
 * matches. (design-taste §9.D NO three-equal Material cards; apple-design
 * §16 icon stroke 1.5 — not icon-in-box; apple-design §12 material
 * weight encodes hierarchy.)
 */
class IconInBoxAuditTest extends TestCase
{

    private const DASHBOARD_PAGE_REL = '/resources/js/modules/dashboard/DashboardPage.vue';

    private static function dashboardPagePath(): string
    {
        return dirname(__DIR__, 3) . self::DASHBOARD_PAGE_REL;
    }

    public function test_kpi_cards_have_no_icon_in_rounded_gray_box(): void
    {
        $user = User::factory()->make();
        $response = $this->actingAs($user)->get('/dashboard');
        $response->assertStatus(200);

        $this->assertFileExists(
            self::dashboardPagePath(),
            'DashboardPage.vue must exist (HOTFIX-DASH-002 source boundary)'
        );

        $source = (string) file_get_contents(self::dashboardPagePath());

        // Pin the RULE: zero <div>/<span> on KPI cards combining
        // `rounded` AND `bg-systemGray-*` (the Material-leak: a rounded
        // icon container sitting in a KPI card body).
        $matches = preg_match_all(
            '/<(?:div|span)\b[^>]*\b(?:bg-systemGray-\d+[^>]*\brounded(?:-\w+)?|rounded(?:-\w+)?[^>]*\bbg-systemGray-\d+)\b[^>]*>/i',
            $source
        );

        $this->assertSame(
            0,
            (int) $matches,
            'DashboardPage.vue MUST NOT contain <div>/<span> with combined `rounded` + `bg-systemGray-*` icon container on KPI cards — HOTFIX-DASH-002, design-taste §9.D (NO three-equal Material cards), apple-design §16 (icon stroke 1.5 — not icon-in-box).'
        );
    }

    public function test_kpi_cards_have_no_neutral_icon_container_either(): void
    {
        $user = User::factory()->make();
        $response = $this->actingAs($user)->get('/dashboard');
        $response->assertStatus(200);

        $source = (string) file_get_contents(self::dashboardPagePath());

        // Belt-and-suspenders: zero <div>/<span> on KPI cards combining
        // `rounded` AND `bg-neutral-*` (the neutral fallback for the same
        // Material-leak pattern).
        $neutralMatches = preg_match_all(
            '/<(?:div|span)\b[^>]*\b(?:bg-neutral-\d+[^>]*\brounded(?:-\w+)?|rounded(?:-\w+)?[^>]*\bbg-neutral-\d+)\b[^>]*>/i',
            $source
        );

        $this->assertSame(
            0,
            (int) $neutralMatches,
            'DashboardPage.vue MUST NOT contain <div>/<span> with combined `rounded` + `bg-neutral-*` icon container on KPI cards — HOTFIX-DASH-002, design-taste §9.D (Material-leak pattern is forbidden under any neutral palette).'
        );
    }
}