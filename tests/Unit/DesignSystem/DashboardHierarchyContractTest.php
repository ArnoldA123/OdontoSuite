<?php

namespace Tests\Unit\DesignSystem;

use PHPUnit\Framework\TestCase;

/**
 * WU4 - dashboard hierarchy contract (D7 / D6 / D10).
 *
 * Source-inspection guards for the three-block reading hierarchy, the
 * hero band carrying the login's visual language, and the topbar subtitle
 * that must stop framing the page as a "Resumen". DOM behavior is covered
 * by tests/js/smoke/dashboard-hierarchy.test.js; these rules are the
 * cheap re-run gate for the source anatomy.
 */
class DashboardHierarchyContractTest extends TestCase
{
    private static function projectRootPath(): string
    {
        return dirname(__DIR__, 3);
    }

    private const DASHBOARD_FILE = '/resources/js/modules/dashboard/DashboardPage.vue';
    private const APP_LAYOUT_FILE = '/resources/js/components/layout/AppLayout.vue';

    private static function read(string $rel): string
    {
        $path = self::projectRootPath() . $rel;
        return is_file($path) ? (string) file_get_contents($path) : '';
    }

    private function dashboardSource(): string
    {
        $src = self::read(self::DASHBOARD_FILE);
        $this->assertNotSame('', $src, 'DashboardPage.vue must exist');
        return $src;
    }

    /**
     * D7 - the three blocks render in reading order: "Hoy", then
     * "Requiere tu atención", then the secondary "Próximos días".
     */
    public function test_dashboard_renders_the_three_blocks_in_reading_order(): void
    {
        $src = $this->dashboardSource();

        $hoy = strpos($src, 'data-dashboard-block="hoy"');
        $attention = strpos($src, 'data-dashboard-block="attention"');
        $upcoming = strpos($src, 'data-dashboard-block="upcoming"');

        $this->assertNotFalse($hoy, 'DashboardPage.vue must render block "hoy" (WU4 / D7).');
        $this->assertNotFalse($attention, 'DashboardPage.vue must render block "attention" (WU4 / D7).');
        $this->assertNotFalse($upcoming, 'DashboardPage.vue must render block "upcoming" (WU4 / D7).');
        $this->assertLessThan($attention, $hoy, 'Block "Hoy" must render before "Requiere tu atención" (D7).');
        $this->assertLessThan($upcoming, $attention, 'Block "Requiere tu atención" must render before "Próximos días" (D7).');

        $this->assertSame(
            0,
            preg_match_all('/aria-label="Resumen del día"/', $src),
            'DashboardPage.vue must render zero "Resumen del día" regions (WU4 / D7: its content lives in block "Hoy").'
        );
        $this->assertStringNotContainsString(
            'aria-label="Pendientes"',
            $src,
            'DashboardPage.vue must retire the flat "Pendientes" region name for the block heading (WU4 / D7).'
        );
        $this->assertStringNotContainsString(
            'aria-label="Próximas citas"',
            $src,
            'DashboardPage.vue must retire the flat "Próximas citas" region name for the block heading (WU4 / D7).'
        );
    }

    /**
     * D7 - block "Hoy" leads with the KPI strip and follows it with the
     * agenda list; both sit under the block heading.
     */
    public function test_kpi_strip_leads_the_agenda_inside_block_hoy(): void
    {
        $src = $this->dashboardSource();

        $blockStart = strpos($src, 'data-dashboard-block="hoy"');
        $heading = strpos($src, '<h2 class="text-2xl font-semibold text-label">Hoy</h2>');
        $strip = strpos($src, 'data-reveal="kpi"');
        $agenda = strpos($src, 'aria-label="Agenda de hoy"');

        foreach ([$heading, $strip, $agenda] as $pos) {
            $this->assertNotFalse($pos);
            $this->assertGreaterThan($blockStart, $pos, 'Block "Hoy" must own its heading, strip and agenda.');
        }
        $this->assertLessThan($strip, $heading, 'The block heading must lead the KPI strip (D7).');
        $this->assertLessThan($agenda, $strip, 'The KPI strip must lead the agenda (D7).');
    }

    /**
     * D6 - the hero band carries the login's surface vocabulary (mint
     * tint, hairline, panel radius, elevation rung) and its display
     * greeting; the brand lockup glyph rides accent-500.
     */
    public function test_hero_band_carries_the_login_surface_vocabulary(): void
    {
        $src = $this->dashboardSource();

        $this->assertStringContainsString(
            'data-dashboard-hero',
            $src,
            'DashboardPage.vue must render the hero band hook (WU4 / D6).'
        );
        $this->assertStringContainsString(
            'background: var(--color-canvas)',
            $src,
            'The hero band must paint the login backdrop base (WU4 / D6; user direction: no mint tint).'
        );
        $this->assertStringContainsString(
            'border: 1px solid var(--color-hairline)',
            $src,
            'The hero band must carry the hairline vocabulary (WU4 / D6).'
        );
        $this->assertStringContainsString(
            'border-radius: var(--radius-panel)',
            $src,
            'The hero band must ride the panel radius (WU4 / D6).'
        );
        $this->assertMatchesRegularExpression(
            '/text-4xl font-semibold[^>]*>\s*\{\{ getGreeting\(\) \}\}/',
            $src,
            'The hero greeting must use the login display step (WU4 / D6).'
        );
        $this->assertStringContainsString(
            'OdontoSuite',
            $src,
            'The hero band must carry the login brand lockup wordmark (WU4 / D6).'
        );
    }

    /**
     * D7 - "Próximos días" is visually secondary: the quietest heading
     * tier and a reduced-prominence surface around its rows.
     */
    public function test_upcoming_block_stays_visually_secondary(): void
    {
        $src = $this->dashboardSource();

        $this->assertStringContainsString(
            '<h2 class="text-base font-medium text-theme-secondary">Próximos días</h2>',
            $src,
            'The upcoming block must render the quietest heading tier (WU4 / D7).'
        );
        $this->assertStringContainsString(
            'data-upcoming-surface',
            $src,
            'The upcoming rows must sit on their secondary surface hook (WU4 / D7).'
        );
        $this->assertMatchesRegularExpression(
            '/data-upcoming-surface[\s\S]{0,400}?var\(--color-system-gray-100\)/',
            $src,
            'The secondary surface must use the quiet system-gray tint (WU4 / D7).'
        );
    }

    /**
     * D10 - the dashboard topbar description must stop framing the page
     * as a "Resumen"; only the dashboard entry of the per-page map moves.
     */
    public function test_dashboard_topbar_description_drops_the_resumen_frame(): void
    {
        $src = self::read(self::APP_LAYOUT_FILE);
        $this->assertNotSame('', $src, 'AppLayout.vue must exist');

        $this->assertMatchesRegularExpression(
            "/'\\/dashboard'\\s*:\\s*'[^']*',/",
            $src,
            'AppLayout.vue must keep the per-page description map entry for /dashboard (D10).'
        );
        $this->assertDoesNotMatchRegularExpression(
            "/'\\/dashboard'\\s*:\\s*'[^']*Resumen[^']*'/",
            $src,
            'The /dashboard topbar description must not use the word "Resumen" (D10).'
        );
    }

    /**
     * HOTFIX-DASH-011 / WU4 - the em-dash ban holds file-wide on the
     * restructured template (block comments included).
     */
    public function test_dashboard_page_keeps_the_em_dash_ban(): void
    {
        $src = $this->dashboardSource();

        $this->assertSame(
            0,
            preg_match_all('/\x{2014}/u', $src),
            'DashboardPage.vue must contain zero U+2014 em-dash characters (HOTFIX-DASH-011).'
        );
    }
}
