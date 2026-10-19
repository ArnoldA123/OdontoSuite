<?php

namespace Tests\Unit\DesignSystem;

/**
 * Design contract for the `/procedure-stats` surface after
 * procedure-stats-ui-redesign (S1 + S7, 2026-10-19).
 *
 * The page was rebuilt on the dashboard recipe (hero band -> KPI strip ->
 * filters -> error/loading/empty states -> table + specialty tiles -> charts).
 * Every assertion below pins the RULE behind a block, never the markup of the
 * moment: the hooks the redesign exposes (`data-procedure-stats-hero`,
 * `data-kpi-card`, `data-state="error"`, `data-period-empty`, `data-chart`,
 * `data-export-csv`) plus the shared primitives and tokens those hooks must
 * resolve to. Removing a hook, a token, or the primitive backing it fails
 * here even while the page still renders. Dead anatomy from the previous
 * slice (PageHeader, data-stat-card, fixed h-4/h-12/h-6/h-4 slots) is not
 * resurrected by this contract.
 *
 * Coverage map:
 *   S1  hero band + 4-card KPI strip + shared ui primitives + token ramps.
 *   S2  retryable error block, period empty state, loading skeletons.
 *   S4  currency through the shared formatter (no inline `.toFixed(2)`).
 *   S5  chart canvases expose data-chart hooks with role="img" and labels;
 *       no canvas is mounted without data.
 *   S6  CSV export reads the applied period (never the draft) off one hook.
 *   S7  router entry unchanged: /procedure-stats sits between
 *       /procedure-catalog/:id and /my-procedures and lazy-imports the page.
 *
 * The inherited DLR-R-001/002/004/021 assertions run through polishedFiles();
 * DOM behavior lives in tests/js/smoke/procedure-stats*.test.js and the nav
 * contract in tests/Unit/Documentation/CatalogStatsReachabilityTest.php.
 */
class ProcedureStatsAppShellTest extends ModuleAppShellTestCase
{
    private const PAGE_PATH = '/resources/js/modules/procedure-catalog/ProcedureStatsPage.vue';
    private const APP_JS_PATH = '/resources/js/app.js';

    /** KPI slots, left to right: catalog totals first, period usage last. */
    private const KPI_SLOTS = ['total', 'active', 'inactive', 'period-usage'];

    /** @return array<int, string> */
    protected static function polishedFiles(): array
    {
        return [dirname(__DIR__, 3) . self::PAGE_PATH];
    }

    private function readSource(string $relativePath): string
    {
        $path = dirname(__DIR__, 3) . $relativePath;
        $this->assertFileExists($path, sprintf('%s must exist.', $relativePath));

        $source = file_get_contents($path);
        $this->assertIsString($source, sprintf('%s must be readable.', $relativePath));

        return $source;
    }

    private function pageSource(): string
    {
        return $this->readSource(self::PAGE_PATH);
    }

    /**
     * The outer `<template>` region: everything before the `<script>` block.
     * Scoping to it keeps a ban from being satisfied by a script constant.
     */
    private function templateSource(string $src): string
    {
        $scriptStart = strpos($src, '<script');
        $this->assertNotFalse($scriptStart, 'ProcedureStatsPage.vue must declare a <script> block.');

        return substr($src, 0, (int) $scriptStart);
    }

    private function extractBlock(string $pattern, string $src, string $message): string
    {
        $matched = preg_match($pattern, $src, $matches);
        $this->assertSame(1, $matched, $message);

        return $matches[0];
    }

    /**
     * @return array<int, array{slot: string, html: string}>
     */
    private function kpiCards(string $src): array
    {
        preg_match_all(
            '~<UiCard\b[^>]*\bdata-kpi-card="([^"]+)"[^>]*>[\s\S]*?</UiCard>~',
            $src,
            $matches,
            PREG_SET_ORDER
        );

        return array_map(
            static fn (array $match): array => ['slot' => $match[1], 'html' => $match[0]],
            $matches
        );
    }

    private static function routeOffset(string $src, string $path): ?int
    {
        $pattern = '~path:\s*[\'"]' . preg_quote($path, '~') . '[\'"]~';
        if (preg_match($pattern, $src, $matches, PREG_OFFSET_CAPTURE) !== 1) {
            return null;
        }

        return $matches[0][1];
    }

    /**
     * S7 (EC-001 unchanged) - the stats page must stay reachable: the
     * `/procedure-stats` entry sits between `/procedure-catalog/:id` and
     * `/my-procedures`, before the 404 catch-all, and binds its component
     * through a lazy `import()`.
     */
    public function test_procedure_stats_route_block_is_lazy_and_ordered(): void
    {
        $src = $this->readSource(self::APP_JS_PATH);

        $this->assertMatchesRegularExpression(
            '~\{\s*path:\s*[\'"]/procedure-stats[\'"][\s\S]{0,300}?component:\s*\(\)\s*=>\s*import\(\s*[\'"][^\'"]*ProcedureStatsPage\.vue[\'"]~',
            $src,
            'resources/js/app.js must bind /procedure-stats to a lazy import of ProcedureStatsPage.vue (S7).'
        );

        $catalogDetail = self::routeOffset($src, '/procedure-catalog/:id');
        $stats = self::routeOffset($src, '/procedure-stats');
        $myProcedures = self::routeOffset($src, '/my-procedures');

        $this->assertNotNull($catalogDetail, 'resources/js/app.js must keep the /procedure-catalog/:id route.');
        $this->assertNotNull($stats, 'resources/js/app.js must keep the /procedure-stats route.');
        $this->assertNotNull($myProcedures, 'resources/js/app.js must keep the /my-procedures route.');

        $this->assertGreaterThan(
            $catalogDetail,
            $stats,
            '/procedure-stats must be declared AFTER /procedure-catalog/:id (route cluster ordering; S7).'
        );
        $this->assertLessThan(
            $myProcedures,
            $stats,
            '/procedure-stats must be declared BEFORE /my-procedures (route cluster ordering; S7).'
        );

        $catchAll = self::routeOffset($src, '/:pathMatch(.*)*');
        if ($catchAll !== null) {
            $this->assertLessThan(
                $catchAll,
                $stats,
                '/procedure-stats must be declared BEFORE the 404 catch-all (S7 reachability).'
            );
        }
    }

    /**
     * S1 - the hero band owns the page title and surface recipe; the old
     * `<PageHeader>` anatomy is gone. The band carries the eyebrow/title/
     * caption hooks plus the canvas + hairline + panel radius + elevation-2
     * tokens.
     */
    public function test_hero_band_replaces_page_header(): void
    {
        $src = $this->pageSource();

        $this->assertDoesNotMatchRegularExpression(
            '~<PageHeader\b~',
            $src,
            'ProcedureStatsPage.vue must not render <PageHeader>: the hero band owns the page title (S1).'
        );

        $hero = $this->extractBlock(
            '~<header\b[^>]*\bdata-procedure-stats-hero\b[^>]*>[\s\S]*?</header>~',
            $src,
            'ProcedureStatsPage.vue must render a <header> carrying data-procedure-stats-hero (S1).'
        );

        foreach (['data-hero-eyebrow', 'data-hero-title', 'data-hero-caption'] as $hook) {
            $this->assertStringContainsString(
                $hook,
                $hero,
                sprintf('The hero band must expose the `%s` hook (S1 hero anatomy).', $hook)
            );
        }

        foreach ([
            'var(--color-canvas)' => 'canvas base',
            'var(--color-hairline)' => 'hairline border',
            'var(--radius-panel)' => 'panel radius',
            'var(--elevation-2)' => 'elevation-2 rung',
        ] as $token => $label) {
            $this->assertStringContainsString(
                $token,
                $hero,
                sprintf('The hero band must consume the %s token `%s` (S1 surface recipe).', $label, $token)
            );
        }
    }

    /**
     * S1 - exactly four KPI slots, each a shared `<UiCard>` on the
     * elevation-2 + hairline recipe, each exposing a matching
     * `data-kpi-value` hook on a tabular numeral.
     */
    public function test_kpi_strip_renders_four_shared_cards_on_the_surface_recipe(): void
    {
        $src = $this->pageSource();
        $cards = $this->kpiCards($src);

        $this->assertCount(
            4,
            $cards,
            'ProcedureStatsPage.vue must render exactly four <UiCard data-kpi-card="..."> KPI slots (S1 KPI strip).'
        );

        $this->assertEqualsCanonicalizing(
            self::KPI_SLOTS,
            array_column($cards, 'slot'),
            'The KPI strip must keep the four catalog/period slots: total, active, inactive, period-usage (S1).'
        );

        foreach ($cards as $card) {
            $slot = $card['slot'];

            $this->assertStringContainsString(
                'var(--elevation-2)',
                $card['html'],
                sprintf('The `%s` KPI card must sit on the elevation-2 rung (S1 KPI recipe).', $slot)
            );
            $this->assertStringContainsString(
                'var(--color-hairline)',
                $card['html'],
                sprintf('The `%s` KPI card must carry the hairline border token (S1 KPI recipe).', $slot)
            );

            $valueTag = $this->extractBlock(
                '~<[a-z][^>]*\bdata-kpi-value="' . preg_quote($slot, '~') . '"[^>]*>~',
                $card['html'],
                sprintf('The `%s` KPI card must expose data-kpi-value="%s" (S1 KPI anatomy).', $slot, $slot)
            );

            $this->assertStringContainsString(
                'tabular-nums',
                $valueTag,
                sprintf('The `%s` KPI numeral must carry tabular-nums (S1 numerals).', $slot)
            );
        }
    }

    /**
     * S2 - the error state is a top-of-page `role="alert"` block with a retry
     * action that re-emits `loadStats()`. It must precede the data surfaces
     * and must never be rendered as an empty period.
     */
    public function test_error_state_is_retryable_and_never_renders_as_empty_data(): void
    {
        $src = $this->pageSource();

        $errorTag = $this->extractBlock(
            '~<[a-z][^>]*\bdata-state="error"[^>]*>~',
            $src,
            'ProcedureStatsPage.vue must expose a data-state="error" block (S2).'
        );

        $this->assertMatchesRegularExpression(
            '~\brole="alert"~',
            $errorTag,
            'The data-state="error" block must announce itself with role="alert" (S2).'
        );

        $this->assertMatchesRegularExpression(
            '~\bdata-retry\b(?:(?!</UiButton>)[\s\S]){0,300}?@click="loadStats"~',
            $src,
            'The error block must expose a `data-retry` action wired to loadStats(), '
            . 'so Reintentar re-emits the request and clears the error (S2).'
        );

        $errorPosition = strpos($src, 'data-state="error"');
        $firstKpiPosition = strpos($src, 'data-kpi-card=');
        $this->assertNotFalse($errorPosition, 'The error block must be present.');
        $this->assertNotFalse($firstKpiPosition, 'The KPI strip must be present.');
        $this->assertLessThan(
            $firstKpiPosition,
            $errorPosition,
            'The error block must render above the data surfaces, never as a trailing banner under empty results (S2).'
        );

        $emptyTag = $this->extractBlock(
            '~<UiCard\b[^>]*\bdata-period-empty\b[^>]*>~',
            $src,
            'ProcedureStatsPage.vue must expose a data-period-empty block (S2).'
        );

        $this->assertStringContainsString(
            '!error',
            $emptyTag,
            'The empty state must be guarded by `v-else-if="!error ..."`, '
            . 'so an error is never rendered as an empty period (S2).'
        );
    }

    /**
     * S2 - the empty period renders the shared `<UiEmptyState>` primitive,
     * not an ad-hoc paragraph.
     */
    public function test_period_empty_state_uses_the_shared_primitive(): void
    {
        $src = $this->pageSource();

        $this->assertMatchesRegularExpression(
            '~<UiCard\b[^>]*\bdata-period-empty\b[^>]*>[\s\S]{0,400}?<UiEmptyState\b~',
            $src,
            'The data-period-empty block must render the shared <UiEmptyState>, not an ad-hoc empty paragraph (S2).'
        );
    }

    /**
     * S2 - the loading branch is a dedicated section carrying `aria-busy`
     * and rendering the shared `<UiSkeleton>`.
     */
    public function test_loading_branch_renders_skeletons_with_aria_busy(): void
    {
        $src = $this->pageSource();

        $loadingBlock = $this->extractBlock(
            '~<section\b[^>]*\bv-if="loading"[^>]*>[\s\S]*?</section>~',
            $src,
            'ProcedureStatsPage.vue must render a dedicated loading section (S2).'
        );

        $this->assertStringContainsString(
            'aria-busy="true"',
            $loadingBlock,
            'The loading section must carry aria-busy="true" (S2).'
        );

        $this->assertMatchesRegularExpression(
            '~<UiSkeleton\b~',
            $loadingBlock,
            'The loading section must render the shared <UiSkeleton>, not a blank surface (S2).'
        );
    }

    /**
     * S1/S7 - the role disclosure is a badge naming the two audiences the
     * route serves.
     */
    public function test_role_disclosure_badge_names_the_two_audiences(): void
    {
        $src = $this->pageSource();

        $badge = $this->extractBlock(
            '~<UiBadge\b[^>]*\bdata-role-disclosure\b[^>]*>[\s\S]*?</UiBadge>~',
            $src,
            'ProcedureStatsPage.vue must render a <UiBadge data-role-disclosure> (S7 role disclosure).'
        );

        $this->assertMatchesRegularExpression(
            '~\bAdministrador\b~',
            $badge,
            'The role disclosure must name Administrador (S7: administrador + finanzas can reach the page).'
        );
        $this->assertMatchesRegularExpression(
            '~\bFinanzas\b~',
            $badge,
            'The role disclosure must name Finanzas (S7: administrador + finanzas can reach the page).'
        );
    }

    /**
     * S4 - every currency figure flows through the shared formatter; the
     * template never formats money inline.
     */
    public function test_currency_goes_through_the_shared_formatter(): void
    {
        $src = $this->pageSource();

        $this->assertMatchesRegularExpression(
            '~import\s*\{[^}]*\bformatCurrency\b[^}]*\}\s*from\s*[\'"][^\'"]*composables/useFormatters[\'"]~',
            $src,
            'ProcedureStatsPage.vue must import formatCurrency from composables/useFormatters (S4 currency contract).'
        );

        $template = $this->templateSource($src);

        $this->assertMatchesRegularExpression(
            '~\bformatCurrency\(\s*~',
            $template,
            'The template must render currency through formatCurrency(...) (shared formatter).'
        );
        $this->assertDoesNotMatchRegularExpression(
            '~\.toFixed\(\s*2\s*\)~',
            $template,
            'The template must not format currency inline with .toFixed(2); use formatCurrency (shared formatter).'
        );
        $this->assertDoesNotMatchRegularExpression(
            '~Intl\.NumberFormat~',
            $template,
            'The template must not construct its own Intl.NumberFormat; use formatCurrency (shared formatter).'
        );
    }

    /**
     * S1 - status colors come from the tokenised systemGreen/systemRed ramps;
     * raw Tailwind green/red ramps are banned.
     */
    public function test_status_colors_use_token_ramps_only(): void
    {
        $src = $this->pageSource();

        $this->assertDoesNotMatchRegularExpression(
            '~(?<![\w-])(?:text|bg|border|ring|from|via|to)-(?:green|red|emerald|rose)-[0-9]{2,3}(?![\w-])~',
            $src,
            'ProcedureStatsPage.vue must not use raw Tailwind green/red ramps; '
            . 'consume the systemGreen/systemRed token ramps (S1).'
        );

        $activeCard = null;
        foreach ($this->kpiCards($src) as $card) {
            if ($card['slot'] === 'active') {
                $activeCard = $card['html'];
                break;
            }
        }
        $this->assertNotNull($activeCard, 'The KPI strip must keep the `active` slot (S1).');
        $this->assertStringContainsString(
            'text-systemGreen-600',
            (string) $activeCard,
            'The `active` KPI numeral must use the tokenised `text-systemGreen-600` ramp (S1).'
        );

        $errorTag = $this->extractBlock(
            '~<[a-z][^>]*\bdata-state="error"[^>]*>~',
            $src,
            'ProcedureStatsPage.vue must expose a data-state="error" block (S2).'
        );
        $this->assertStringContainsString(
            'bg-systemRed-50',
            $errorTag,
            'The error block must paint the tokenised `bg-systemRed-50` ramp (S1/S2).'
        );
    }

    /**
     * S1 - every specialty tile keeps the shared tile vocabulary: control
     * radius plus the hairline token.
     */
    public function test_specialty_tiles_keep_the_control_radius_and_hairline(): void
    {
        $src = $this->pageSource();

        $matched = preg_match_all(
            '~<li\b[^>]*\bdata-specialty-row\b[^>]*>[\s\S]*?</li>~',
            $src,
            $tiles
        );

        $this->assertGreaterThanOrEqual(
            1,
            $matched,
            'ProcedureStatsPage.vue must render the specialty distribution as data-specialty-row tiles (S1).'
        );

        foreach ($tiles[0] as $tile) {
            $openingTag = $this->extractBlock(
                '~<li\b[^>]*>~',
                $tile,
                'Each specialty tile must keep a <li> opening tag.'
            );

            $this->assertStringContainsString(
                'rounded-[var(--radius-control)]',
                $openingTag,
                'Every specialty tile must ride the control radius (S1 tile vocabulary).'
            );
            $this->assertStringContainsString(
                'var(--color-hairline)',
                $tile,
                'Every specialty tile must consume the hairline token for its border (S1 tile vocabulary).'
            );
        }
    }

    /**
     * S5/S6 - both charts expose stable canvas hooks with accessible roles,
     * no canvas mounts without data, and the CSV action is wired to the
     * applied period (never the draft).
     */
    public function test_charts_and_csv_export_expose_stable_hooks(): void
    {
        $src = $this->pageSource();

        foreach (['usage', 'specialty'] as $chart) {
            $canvas = $this->extractBlock(
                '~<canvas\b[^>]*\bdata-chart="' . preg_quote($chart, '~') . '"[^>]*>~',
                $src,
                sprintf('ProcedureStatsPage.vue must expose a <canvas data-chart="%s"> hook (S5).', $chart)
            );

            $this->assertMatchesRegularExpression(
                '~\brole="img"~',
                $canvas,
                sprintf('The `%s` chart canvas must declare role="img" (S5).', $chart)
            );
            $this->assertMatchesRegularExpression(
                '~\baria-label="[^"]+"~',
                $canvas,
                sprintf('The `%s` chart canvas must carry an aria-label (S5).', $chart)
            );
        }

        $chartsSection = $this->extractBlock(
            '~<section\b[^>]*\bdata-charts\b[^>]*>~',
            $src,
            'ProcedureStatsPage.vue must wrap the charts in a data-charts section (S5).'
        );

        $this->assertMatchesRegularExpression(
            '~\bv-if="[^"]*\bhasData\b~',
            $chartsSection,
            'The charts section must render only when hasData is true; no canvas without data (S5).'
        );

        $this->assertMatchesRegularExpression(
            '~\bdata-export-csv\b(?:(?!</UiButton>)[\s\S]){0,300}?@click="exportCsv"~',
            $src,
            'The hero must expose a data-export-csv action wired to exportCsv() (S6).'
        );

        $exportHandler = $this->extractBlock(
            '~const exportCsv = \(\) => \{[\s\S]*?\n\}~',
            $src,
            'ProcedureStatsPage.vue must keep the exportCsv() handler (S6).'
        );

        $this->assertStringContainsString(
            'applied.value.from',
            $exportHandler,
            'The CSV filename must use the applied `from` date (S6).'
        );
        $this->assertStringContainsString(
            'applied.value.to',
            $exportHandler,
            'The CSV filename must use the applied `to` date (S6).'
        );
        $this->assertStringNotContainsString(
            'draft.value',
            $exportHandler,
            'The CSV export must read the applied period, never the draft filters (S6).'
        );
    }

    /**
     * S1 - the ranking table and specialty tiles keep tabular numerals.
     */
    public function test_table_and_specialty_numerics_carry_tabular_nums(): void
    {
        $src = $this->pageSource();

        foreach (['usage_count', 'total_quantity', 'total_revenue'] as $cell) {
            $cellBlock = $this->extractBlock(
                '~<template\b[^>]*#cell-' . preg_quote($cell, '~') . '\b[^>]*>[\s\S]*?</template>~',
                $src,
                sprintf('The ranking table must keep the `%s` cell slot (S1 table anatomy).', $cell)
            );

            $this->assertStringContainsString(
                'tabular-nums',
                $cellBlock,
                sprintf('The `%s` table numeral must carry tabular-nums (S1 numerals).', $cell)
            );
        }

        $matched = preg_match_all(
            '~<li\b[^>]*\bdata-specialty-row\b[^>]*>[\s\S]*?</li>~',
            $src,
            $tiles
        );

        $this->assertGreaterThanOrEqual(
            1,
            $matched,
            'ProcedureStatsPage.vue must render the specialty distribution as data-specialty-row tiles (S1).'
        );

        foreach ($tiles[0] as $tile) {
            $this->assertStringContainsString(
                'tabular-nums',
                $tile,
                'Every specialty tile numeral must carry tabular-nums (S1 numerals).'
            );
        }
    }
}
