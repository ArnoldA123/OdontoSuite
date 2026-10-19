<?php

namespace Tests\Unit\DesignSystem;

/**
 * EC-* tokenization contract for the estadisticas-catalogo category slice
 * (ui-rollout-all-modules-2026-08 PR1: `pr1-procedure-stats-tokenise-and-wire-up`).
 *
 * Covers the 9 EC-* MUST rows from
 * `openspec/changes/archive/2026-08-21-ui-estadisticas-catalogo/spec.md`:
 *
 *   EC-001  router registration for /procedure-stats (covered by AppLayoutCanvasRoutesTest
 *           + the additive app.js entry; the source-level smoke test asserts the route
 *           block sits between /procedure-catalog/:id and /my-procedures).
 *   EC-002  KPI anatomy (data-stat-card + hairline + elevation-2 + fixed-slot grid).
 *   EC-003  <PageHeader> adoption, page-level <h1> removed.
 *   EC-004  <UiEmptyState> for 2 empty blocks + <UiSkeleton> loading block.
 *   EC-005  formatPENLabel swap (no inline `.toFixed(2)` on currency cells).
 *   EC-006  text-systemGreen-600 / bg-systemRed-50 etc. (no raw green/red ramps).
 *   EC-007  hairline borders on table + specialty tile, radius-control on tile.
 *   EC-008  inline role disclosure block.
 *   EC-009  tabular-nums + font-feature-settings paired on 8 numerics.
 *
 * The 5 inherited DLR-R-001/002/004/004/021 assertions run via the
 * `ModuleAppShellTestCase::polishedFileProvider` data provider.
 */
class ProcedureStatsAppShellTest extends ModuleAppShellTestCase
{
    private const PAGE_PATH = '/resources/js/modules/procedure-catalog/ProcedureStatsPage.vue';
    private const APP_JS_PATH = '/resources/js/app.js';

    /** @return array<int, string> */
    protected static function polishedFiles(): array
    {
        return [dirname(__DIR__, 3) . self::PAGE_PATH];
    }

    private static function readSource(string $path): ?string
    {
        $src = @file_get_contents($path);
        return $src === false ? null : $src;
    }

    /**
     * EC-001 — `/procedure-stats` MUST be registered in `resources/js/app.js`
     * between the `/procedure-catalog/:id` route block and the `/my-procedures`
     * route block, with `beforeEnter: requireAuth`. Without this entry the
     * polished page falls into the 404 catch-all (OQ-EC-1 CRITICAL).
     */
    public function test_procedure_stats_route_registered_in_app_js(): void
    {
        $src = self::readSource(dirname(__DIR__, 3) . self::APP_JS_PATH);
        $this->assertNotNull($src, 'resources/js/app.js must be readable.');

        // The route block must reference the page component.
        $this->assertMatchesRegularExpression(
            "/path:\\s*['\"]\\/procedure-stats['\"]/",
            $src,
            'resources/js/app.js must declare a route with path: \'/procedure-stats\' (EC-001).'
        );

        // The route must mount the ProcedureStatsPage component (lazy import).
        $this->assertMatchesRegularExpression(
            "/ProcedureStatsPage\\.vue/",
            $src,
            'resources/js/app.js must lazy-import ./modules/procedure-catalog/ProcedureStatsPage.vue for /procedure-stats (EC-001).'
        );

        // The route must sit BEFORE /my-procedures and AFTER /procedure-catalog/:id
        // so the route table stays ordered by module cluster (matches sibling pattern).
        $catalogDetailPos = strpos($src, "'/procedure-catalog/:id'");
        $procStatsPos = strpos($src, "'/procedure-stats'");
        $myProcPos = strpos($src, "'/my-procedures'");

        $this->assertNotFalse($catalogDetailPos, '/procedure-catalog/:id block must precede /procedure-stats.');
        $this->assertNotFalse($procStatsPos, '/procedure-stats block must exist.');
        $this->assertNotFalse($myProcPos, '/my-procedures block must exist.');
        $this->assertGreaterThan(
            $catalogDetailPos,
            $procStatsPos,
            '/procedure-stats must be declared AFTER /procedure-catalog/:id (route cluster ordering; EC-001).'
        );
        $this->assertLessThan(
            $myProcPos,
            $procStatsPos,
            '/procedure-stats must be declared BEFORE /my-procedures (route cluster ordering; EC-001).'
        );

        // The route MUST NOT be placed inside the 404 catch-all block — assert
        // it sits BEFORE the /:pathMatch(.*)* catch-all.
        $catchAllPos = strpos($src, "':pathMatch(.*)*'");
        if ($catchAllPos !== false) {
            $this->assertLessThan(
                $catchAllPos,
                $procStatsPos,
                '/procedure-stats must be declared BEFORE the 404 catch-all block (EC-001).'
            );
        }
    }

    /**
     * EC-002 — The three KPI cards keep the dashboard anatomy: data-stat-card,
     * inline hairline border, elevation-2 shadow, and the eyebrow / value /
     * caption hierarchy (the redesign replaced the old fixed h-4/h-12/h-6/h-4
     * slot grid with the current dashboard KPI recipe).
     */
    public function test_kpi_anatomy_matches_dashboard(): void
    {
        $src = self::readSource(dirname(__DIR__, 3) . self::PAGE_PATH);
        $this->assertNotNull($src, 'ProcedureStatsPage.vue must be readable.');

        preg_match_all(
            '/<UiCard[^>]*\bdata-stat-card="[^"]+"[^>]*>[\s\S]*?<\/UiCard>/',
            $src,
            $matches
        );
        $cards = $matches[0] ?? [];
        $this->assertGreaterThanOrEqual(
            3,
            count($cards),
            'ProcedureStatsPage.vue must render at least 3 KPI <UiCard> blocks carrying data-stat-card (EC-002).'
        );

        $expectedKeys = ['total-procedures', 'active', 'inactive'];
        foreach ($expectedKeys as $key) {
            $this->assertStringContainsString(
                'data-stat-card="' . $key . '"',
                $src,
                "ProcedureStatsPage.vue must mark the \"{$key}\" KPI with data-stat-card=\"{$key}\" (EC-002)."
            );
        }

        foreach ($cards as $idx => $card) {
            $this->assertMatchesRegularExpression(
                '/--color-hairline/',
                $card,
                "KPI card #{$idx} must consume var(--color-hairline) for its border (EC-002)."
            );
            $this->assertMatchesRegularExpression(
                '/--elevation-2/',
                $card,
                "KPI card #{$idx} must consume var(--elevation-2) for its shadow (EC-002)."
            );
            $this->assertMatchesRegularExpression(
                '/text-xs font-medium uppercase/',
                $card,
                "KPI card #{$idx} must render the eyebrow with the dashboard label recipe (EC-002)."
            );
            $this->assertMatchesRegularExpression(
                '/text-3xl font-bold/',
                $card,
                "KPI card #{$idx} must render the counter with the dashboard value recipe (EC-002)."
            );
            $this->assertMatchesRegularExpression(
                '/mt-1 text-xs/',
                $card,
                "KPI card #{$idx} must render a caption under the counter (EC-002)."
            );
        }
    }

    /**
     * EC-003 — The page hero follows the dashboard hierarchy: the hero band
     * carries the eyebrow, the single display <h1> and the caption. The legacy
     * PageHeader block is gone (the redesigned catalog pages share the hero
     * band recipe).
     */
    public function test_hero_band_replaces_page_header(): void
    {
        $src = self::readSource(dirname(__DIR__, 3) . self::PAGE_PATH);
        $this->assertNotNull($src, 'ProcedureStatsPage.vue must be readable.');

        $this->assertMatchesRegularExpression(
            '/<header\b[^>]*data-stats-hero/s',
            $src,
            'ProcedureStatsPage.vue must render the hero band with data-stats-hero (EC-003).'
        );
        $this->assertMatchesRegularExpression(
            '/<h1\b[^>]*data-hero-title/s',
            $src,
            'ProcedureStatsPage.vue must render its single display <h1> inside the hero (EC-003).'
        );
        $this->assertDoesNotMatchRegularExpression(
            '/<PageHeader\b/',
            $src,
            'ProcedureStatsPage.vue must not fall back to the legacy PageHeader (EC-003).'
        );
        $this->assertSame(
            1,
            preg_match_all('/<h1[\s>]/i', $src),
            'ProcedureStatsPage.vue must declare exactly one page <h1> (EC-003).'
        );
    }

    /**
     * EC-004 — Loading branch MUST render ≥3 `<UiSkeleton variant="card">` +
     * ≥6 `<UiSkeleton variant="list">`, wrapped in a container carrying
     * `aria-busy="true"` AND `aria-live="polite"`. Empty blocks MUST consume
     * `<UiEmptyState>` (≥2 instances).
     */
    public function test_loading_branch_renders_skeletons(): void
    {
        $src = self::readSource(dirname(__DIR__, 3) . self::PAGE_PATH);
        $this->assertNotNull($src, 'ProcedureStatsPage.vue must be readable.');

        $cardSkeletons = preg_match_all('/<UiSkeleton\b[^>]*\bvariant="card"/', $src);
        $listSkeletons = preg_match_all('/<UiSkeleton\b[^>]*\bvariant="list"/', $src);

        $this->assertGreaterThanOrEqual(
            3,
            (int) $cardSkeletons,
            'ProcedureStatsPage.vue must render ≥3 <UiSkeleton variant="card"> for KPI counters (EC-004).'
        );
        $this->assertGreaterThanOrEqual(
            6,
            (int) $listSkeletons,
            'ProcedureStatsPage.vue must render ≥6 <UiSkeleton variant="list"> for table + specialty rows (EC-004).'
        );

        // The loading wrapper must carry both aria-busy="true" AND aria-live="polite".
        $this->assertMatchesRegularExpression(
            '/aria-busy="true"/',
            $src,
            'ProcedureStatsPage.vue loading wrapper must carry aria-busy="true" (EC-004).'
        );
        $this->assertMatchesRegularExpression(
            '/aria-live="polite"/',
            $src,
            'ProcedureStatsPage.vue loading wrapper must carry aria-live="polite" (EC-004).'
        );

        // Empty states consume <UiEmptyState> (≥2).
        $this->assertGreaterThanOrEqual(
            2,
            preg_match_all('/<UiEmptyState\b/', $src),
            'ProcedureStatsPage.vue must render ≥2 <UiEmptyState> blocks (EC-004).'
        );
    }

    /**
     * EC-005 — The `<script setup>` block MUST import `formatPENLabel` from
     * `@/composables/useFormatters`. No inline `.toFixed(2)` MUST remain on
     * currency cells.
     */
    public function test_format_pen_label_consumed(): void
    {
        $src = self::readSource(dirname(__DIR__, 3) . self::PAGE_PATH);
        $this->assertNotNull($src, 'ProcedureStatsPage.vue must be readable.');

        $this->assertMatchesRegularExpression(
            "/import\\s*\\{\\s*formatPENLabel\\s*\\}\\s*from\\s*['\"]@\\/composables\\/useFormatters['\"]/",
            $src,
            'ProcedureStatsPage.vue <script setup> MUST import formatPENLabel from @/composables/useFormatters (EC-005).'
        );

        $this->assertDoesNotMatchRegularExpression(
            '/\.toFixed\(2\)/',
            $src,
            'ProcedureStatsPage.vue must not contain inline `.toFixed(2)` literals on currency cells (EC-005).'
        );
    }

    /**
     * EC-006 — Raw Tailwind green/red ramps MUST be replaced with the tokenised
     * `systemGreen-600` and `systemRed-50/200/700` ramps.
     */
    public function test_no_raw_green_or_red_ramps(): void
    {
        $src = self::readSource(dirname(__DIR__, 3) . self::PAGE_PATH);
        $this->assertNotNull($src, 'ProcedureStatsPage.vue must be readable.');

        $this->assertDoesNotMatchRegularExpression(
            '/(?<![\w-])text-green-600(?![\w-])/',
            $src,
            'ProcedureStatsPage.vue must not contain the raw `text-green-600` Tailwind class (EC-006).'
        );
        $this->assertDoesNotMatchRegularExpression(
            '/(?<![\w-])bg-red-50(?![\w-])/',
            $src,
            'ProcedureStatsPage.vue must not contain the raw `bg-red-50` Tailwind class (EC-006).'
        );
        $this->assertDoesNotMatchRegularExpression(
            '/(?<![\w-])border-red-200(?![\w-])/',
            $src,
            'ProcedureStatsPage.vue must not contain the raw `border-red-200` Tailwind class (EC-006).'
        );
        $this->assertDoesNotMatchRegularExpression(
            '/(?<![\w-])text-red-700(?![\w-])/',
            $src,
            'ProcedureStatsPage.vue must not contain the raw `text-red-700` Tailwind class (EC-006).'
        );

        $this->assertMatchesRegularExpression(
            '/text-systemGreen-600/',
            $src,
            'ProcedureStatsPage.vue must render the "Activos" KPI in `text-systemGreen-600` (EC-006).'
        );
        $this->assertMatchesRegularExpression(
            '/bg-systemRed-50[^"]*border-systemRed-200[^"]*text-systemRed-700/',
            $src,
            'ProcedureStatsPage.vue error banner must consume the tokenised `bg-systemRed-50 border-systemRed-200 text-systemRed-700` ramp (EC-006).'
        );
    }

    /**
     * EC-007 — Hairline borders on table + specialty tile. Specialty tile
     * wrapper carries `rounded-[var(--radius-control)]` AND
     * `border-[color:var(--color-hairline)]`.
     */
    public function test_specialty_tile_uses_hairline_and_radius_control(): void
    {
        $src = self::readSource(dirname(__DIR__, 3) . self::PAGE_PATH);
        $this->assertNotNull($src, 'ProcedureStatsPage.vue must be readable.');

        $this->assertDoesNotMatchRegularExpression(
            '/(?<![\w-])border-theme(?![\w-])/',
            $src,
            'ProcedureStatsPage.vue must not contain the legacy `border-theme` literal (EC-007).'
        );

        // Specialty tile wrapper carries the radius-control + hairline border pair.
        $this->assertMatchesRegularExpression(
            '/rounded-\[var\(--radius-control\)\]/',
            $src,
            'ProcedureStatsPage.vue specialty tile must carry `rounded-[var(--radius-control)]` (EC-007).'
        );

        // Count border-[color:var(--color-hairline)] occurrences — at least 1
        // for the specialty tile (the table also uses hairline via border-b or
        // similar, but the explicit `border-[color:var(--color-hairline)]`
        // arbitrary-value form is the canonical specialty-tile marker).
        $hairlineCount = preg_match_all(
            '/border-\[color:var\(--color-hairline\)\]/',
            $src
        );
        $this->assertGreaterThanOrEqual(
            1,
            (int) $hairlineCount,
            'ProcedureStatsPage.vue specialty tile (and other consumers) must use `border-[color:var(--color-hairline)]` (EC-007).'
        );
    }

    /**
     * EC-008 — Inline role disclosure at page top: "Visible para: Administrador,
     * Finanzas" inside an element carrying `text-xs text-theme-secondary`.
     */
    public function test_role_disclosure_present(): void
    {
        $src = self::readSource(dirname(__DIR__, 3) . self::PAGE_PATH);
        $this->assertNotNull($src, 'ProcedureStatsPage.vue must be readable.');

        // Case-insensitive Spanish literal.
        $this->assertMatchesRegularExpression(
            '/Visible\s+para:\s*Administrador,\s*Finanzas/i',
            $src,
            'ProcedureStatsPage.vue must render the role disclosure "Visible para: Administrador, Finanzas" (EC-008).'
        );

        // The disclosure lives inside an element carrying text-xs text-theme-secondary.
        $this->assertMatchesRegularExpression(
            '/text-xs\s+text-theme-secondary[^"]*"[^>]*>\s*Visible\s+para:\s*Administrador/i',
            $src,
            'ProcedureStatsPage.vue role disclosure must live inside an element carrying `text-xs text-theme-secondary` (EC-008).'
        );
    }

    /**
     * EC-009 — Every numeric element (3 KPI counters + 3 table numerics + 2
     * specialty numerics) MUST carry BOTH `tabular-nums` AND
     * `style="font-feature-settings: var(--font-features-tabular-nums)"`.
     * Either alone is a contract violation (paired-only rule).
     */
    public function test_tabular_nums_on_all_numerics(): void
    {
        $src = self::readSource(dirname(__DIR__, 3) . self::PAGE_PATH);
        $this->assertNotNull($src, 'ProcedureStatsPage.vue must be readable.');

        // Count elements that carry BOTH markers. The simplest reliable proxy:
        // a substring `tabular-nums ... font-feature-settings: var(--font-features-tabular-nums)`
        // appearing ≥8 times (3 KPI + 3 table + 2 specialty).
        $pairedCount = preg_match_all(
            '/tabular-nums[\s\S]{0,200}?font-feature-settings:\s*var\(--font-features-tabular-nums\)/',
            $src
        );
        $this->assertGreaterThanOrEqual(
            8,
            (int) $pairedCount,
            'ProcedureStatsPage.vue must apply the paired `tabular-nums` + `style="font-feature-settings: var(--font-features-tabular-nums)"` contract to ≥8 numerics (3 KPI + 3 table + 2 specialty; EC-009 paired-only rule).'
        );

        // Belt-and-braces: `tabular-nums` literal must be present (otherwise
        // a hardcoded "0 paired" above would be the same as "0 features" — the
        // regex returns 0 either way).
        $tabularCount = preg_match_all('/tabular-nums/', $src);
        $this->assertGreaterThanOrEqual(
            8,
            (int) $tabularCount,
            'ProcedureStatsPage.vue must carry `tabular-nums` on ≥8 numeric elements (EC-009).'
        );
    }
}
