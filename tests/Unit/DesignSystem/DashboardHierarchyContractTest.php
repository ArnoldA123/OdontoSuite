<?php

namespace Tests\Unit\DesignSystem;

use PHPUnit\Framework\TestCase;

/**
 * Dashboard information architecture after U2.
 *
 * Source-inspection guards for the two-block reading order (Hoy -> Dónde
 * seguir), the hero band carrying the login's visual language, the compact
 * destination cards and the topbar subtitle that must stop framing the page as
 * a "Resumen". DOM behavior is covered by tests/js/smoke/dashboard-*.test.js;
 * these rules are the cheap re-run gate for the source anatomy.
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
        $path = self::projectRootPath().$rel;

        return is_file($path) ? (string) file_get_contents($path) : '';
    }

    private function dashboardSource(): string
    {
        $src = self::read(self::DASHBOARD_FILE);
        $this->assertNotSame('', $src, 'DashboardPage.vue must exist');

        return $src;
    }

    /**
     * U2 - the page renders two blocks in reading order: "Hoy" and "Dónde
     * seguir". The three appointment blocks are retired.
     */
    public function test_dashboard_renders_the_two_blocks_in_reading_order(): void
    {
        $src = $this->dashboardSource();

        $hoy = strpos($src, 'data-dashboard-block="hoy"');
        $destinations = strpos($src, 'data-dashboard-block="destinations"');

        $this->assertNotFalse($hoy, 'DashboardPage.vue must render block "hoy".');
        $this->assertNotFalse($destinations, 'DashboardPage.vue must render block "destinations".');
        $this->assertLessThan($destinations, $hoy, 'Block "Hoy" must render before "Dónde seguir".');

        foreach (['attention', 'upcoming'] as $retired) {
            $this->assertStringNotContainsString(
                'data-dashboard-block="'.$retired.'"',
                $src,
                "\"{$retired}\" is not a dashboard block anymore: its content moved out of the page (U2)."
            );
        }

        $this->assertSame(
            0,
            preg_match_all('/aria-label="Resumen del día"/', $src),
            'DashboardPage.vue must render zero "Resumen del día" regions.'
        );
        $this->assertStringNotContainsString(
            'aria-label="Agenda de hoy"',
            $src,
            'DashboardPage.vue must no longer list today\'s appointments (U2).'
        );
        $this->assertStringNotContainsString(
            'aria-label="Requiere tu atención"',
            $src,
            'DashboardPage.vue must retire the "Requiere tu atención" block: the answers live in Pendientes (U1/U2).'
        );
        $this->assertStringNotContainsString(
            'aria-label="Próximos días"',
            $src,
            'DashboardPage.vue must retire the week preview (U2).'
        );
    }

    /**
     * U2 - block "Hoy" leads with the KPI strip and owns no list anymore.
     */
    public function test_kpi_strip_is_the_only_content_of_block_hoy(): void
    {
        $src = $this->dashboardSource();

        $blockStart = strpos($src, 'data-dashboard-block="hoy"');
        $heading = strpos($src, '<h2 class="text-2xl font-semibold text-label">Hoy</h2>');
        $strip = strpos($src, 'data-reveal="kpi"');
        $destinations = strpos($src, 'data-dashboard-block="destinations"');

        foreach ([$heading, $strip, $destinations] as $pos) {
            $this->assertNotFalse($pos);
            $this->assertGreaterThan($blockStart, $pos, 'Block "Hoy" must own its heading and strip.');
        }
        $this->assertLessThan($strip, $heading, 'The block heading must lead the KPI strip.');
        $this->assertLessThan($destinations, $strip, 'The strip must close block "Hoy" before the destinations.');

        $this->assertStringNotContainsString(
            'data-appointment-row',
            $src,
            'DashboardPage.vue must keep zero appointment rows (U2).'
        );
        $this->assertStringNotContainsString(
            'todayAppointments',
            $src,
            'DashboardPage.vue must not keep the today list that fed the retired agenda.'
        );
    }

    /**
     * U2 - the destinations are three link cards, each with one line of what it
     * resolves and no invented counter.
     */
    public function test_destinations_block_is_compact_and_counter_free(): void
    {
        $src = $this->dashboardSource();

        $block = strpos($src, 'data-dashboard-block="destinations"');
        $this->assertNotFalse($block);

        foreach (["to: '/calendar'", "to: '/my-day'", "to: '/continuity'"] as $route) {
            $this->assertStringContainsString(
                $route,
                $src,
                "The destinations block must link to {$route}."
            );
        }

        foreach (['Agenda', 'Mi jornada', 'Pendientes'] as $title) {
            $this->assertStringContainsString(
                "title: '{$title}'",
                $src,
                "The destinations block must title the \"{$title}\" card."
            );
        }

        $this->assertStringContainsString(
            'v-for="destination in destinations"',
            $src,
            'The destinations block must render its cards from one declared list.'
        );
        $this->assertStringContainsString(
            'description:',
            $src,
            'Every destination card must declare the line of what it resolves.'
        );

        // The cards carry no counter and no data read.
        $this->assertStringContainsString(
            '<RouterLink',
            $src,
            'Every destination card must be a real link, not a clickable surface.'
        );

        $this->assertStringNotContainsString(
            'count:',
            $src,
            'The destinations must not carry invented counters.'
        );
    }

    /**
     * The page header is the shared component: one anatomy for the nine
     * surfaces, mounted with the greeting as its title. The brand lockup and
     * the dental motif live outside the header.
     */
    public function test_page_header_owns_the_greeting_band(): void
    {
        $src = $this->dashboardSource();

        $this->assertStringContainsString(
            '<PageHeader',
            $src,
            'DashboardPage.vue must mount the shared <PageHeader>.'
        );
        $this->assertMatchesRegularExpression(
            '/<PageHeader\b[\s\S]{0,400}?kicker="HOY"/',
            $src,
            'The dashboard header must declare the HOY kicker.'
        );
        $this->assertMatchesRegularExpression(
            '/:title="`\$\{getGreeting\(\)\}, \$\{firstName\}`"/',
            $src,
            'The greeting must travel to <PageHeader :title>.'
        );
        $this->assertMatchesRegularExpression(
            '/:subtitle="getTodayDate\(\)"/',
            $src,
            'The date must travel to <PageHeader :subtitle>.'
        );

        // The surface recipe moved to the component; the page no longer
        // repeats it, and it carries no brand lockup or motif.
        $this->assertStringNotContainsString(
            'data-dashboard-hero',
            $src,
            'DashboardPage.vue must not keep the retired hero hook.'
        );
        $this->assertStringNotContainsString(
            'OdontoSuite',
            $src,
            'The dashboard header must not paint the brand wordmark.'
        );
        $this->assertStringNotContainsString(
            'DentalMotifLayer',
            $src,
            'The dashboard header must not mount the dental motif.'
        );
    }

    /**
     * D10 - the dashboard topbar description must stop framing the page as a
     * "Resumen"; only the dashboard entry of the per-page map moves.
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
     * HOTFIX-DASH-011 - the em-dash ban holds file-wide on the restructured
     * template (block comments included).
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
