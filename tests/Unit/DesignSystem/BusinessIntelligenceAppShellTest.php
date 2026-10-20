<?php

namespace Tests\Unit\DesignSystem;

/**
 * Design contract for the `/business-intelligence` surface after
 * business-intelligence-ui-redesign (T7-T10, 2026-10-20).
 *
 * The page was rebuilt on the dashboard / procedure-stats recipe: hero band ->
 * filter card -> KPI strip -> charts -> table, with one definition of money and
 * an export that reads the applied period. Every assertion pins the rule behind
 * a block, never the markup of the moment: the hooks the redesign exposes
 * (`data-bi-hero`, `data-kpi-card`, `data-state="error"`, `data-report-empty`,
 * `data-chart`, `data-export-*`) plus the shared primitives and tokens those
 * hooks must resolve to.
 *
 * Coverage map:
 *   T7  hero band, four-card KPI strip, filters on shared primitives, complete
 *       load states.
 *   T8  chart canvases expose data-chart hooks with role="img" and labels;
 *       Chart.js loads dynamically and the palette comes from tokens.js.
 *   T9  export rides useApi().download with the applied period; no raw fetch
 *       and no localStorage token read.
 *   D11 the page no longer subscribes to the public `dashboard-updates`
 *       channel.
 *
 * The inherited DLR-R-001/002/004/021 assertions run through polishedFiles();
 * DOM behavior lives in tests/js/smoke/business-intelligence*.test.js.
 */
class BusinessIntelligenceAppShellTest extends ModuleAppShellTestCase
{
    private const PAGE_PATH = '/resources/js/modules/business-intelligence/BusinessIntelligencePage.vue';

    private const USE_API_PATH = '/resources/js/composables/useApi.js';

    /** KPI slots, left to right: activity first, money and capacity after. */
    private const KPI_SLOTS = ['appointments', 'patients', 'revenue', 'occupancy'];

    /** @var array<int, string> */
    private const EXPORT_FORMATS = ['excel', 'csv', 'pdf'];

    /** @return array<int, string> */
    protected static function polishedFiles(): array
    {
        return [dirname(__DIR__, 3).self::PAGE_PATH];
    }

    private function readSource(string $relativePath): string
    {
        $path = dirname(__DIR__, 3).$relativePath;
        $this->assertFileExists($path, sprintf('%s must exist.', $relativePath));

        $source = file_get_contents($path);
        $this->assertIsString($source, sprintf('%s must be readable.', $relativePath));

        return $source;
    }

    private function pageSource(): string
    {
        return $this->readSource(self::PAGE_PATH);
    }

    private function templateSource(string $src): string
    {
        $scriptStart = strpos($src, '<script');
        $this->assertNotFalse(
            $scriptStart,
            'BusinessIntelligencePage.vue must declare a <script> block.'
        );

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

    /**
     * T7 - the hero band owns the page title and the surface recipe; the old
     * `<PageHeader>` anatomy is gone. The band carries the eyebrow/title/
     * caption hooks, the applied period, the refresh action and the three
     * export actions.
     */
    public function test_hero_band_replaces_page_header_and_owns_the_actions(): void
    {
        $src = $this->pageSource();

        $this->assertDoesNotMatchRegularExpression(
            '~<PageHeader\b~',
            $src,
            'BusinessIntelligencePage.vue must not render <PageHeader>: the hero band owns the page title (T7).'
        );

        $hero = $this->extractBlock(
            '~<header\b[^>]*\bdata-bi-hero\b[^>]*>[\s\S]*?</header>~',
            $src,
            'BusinessIntelligencePage.vue must render a <header> carrying data-bi-hero (T7).'
        );

        foreach (['data-hero-eyebrow', 'data-hero-title', 'data-hero-caption'] as $hook) {
            $this->assertStringContainsString(
                $hook,
                $hero,
                sprintf('The hero band must expose the `%s` hook (T7 hero anatomy).', $hook)
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
                sprintf('The hero band must consume the %s token `%s` (T7 surface recipe).', $label, $token)
            );
        }

        $this->assertStringContainsString(
            'data-period-applied',
            $hero,
            'The hero band must render the applied period, not the draft filters (T7).'
        );

        $this->assertMatchesRegularExpression(
            '~\bdata-hero-refresh\b(?:(?!</UiButton>)[\s\S]){0,300}?@click="loadReport"~',
            $hero,
            'The hero must expose a refresh action wired to loadReport() (T7).'
        );

        foreach (self::EXPORT_FORMATS as $format) {
            $this->assertMatchesRegularExpression(
                '~\bdata-export-'.preg_quote($format, '~').'\b(?:(?!</UiButton>)[\s\S]){0,300}?@click="exportReport\(\s*\''
                .preg_quote($format, '~').'\'\s*\)~',
                $hero,
                sprintf('The hero must expose a data-export-%s action wired to exportReport(\'%s\') (T9).', $format, $format)
            );
        }
    }

    /**
     * T7 - exactly four KPI slots, each a shared `<UiCard>` on the
     * glass/elevation-2/hairline recipe, each exposing a matching
     * `data-kpi-value` hook on a tabular numeral.
     */
    public function test_kpi_strip_renders_four_shared_cards_on_the_surface_recipe(): void
    {
        $src = $this->pageSource();
        $cards = $this->kpiCards($src);

        $this->assertCount(
            4,
            $cards,
            'BusinessIntelligencePage.vue must render exactly four <UiCard data-kpi-card="..."> KPI slots (T7 KPI strip).'
        );

        $this->assertEqualsCanonicalizing(
            self::KPI_SLOTS,
            array_column($cards, 'slot'),
            'The KPI strip must keep the four report slots: appointments, patients, revenue, occupancy (T7).'
        );

        foreach ($cards as $card) {
            $slot = $card['slot'];

            $this->assertStringContainsString(
                'variant="glass"',
                $card['html'],
                sprintf('The `%s` KPI card must use the shared glass card variant (T7 KPI recipe).', $slot)
            );
            $this->assertStringContainsString(
                'padding="sm"',
                $card['html'],
                sprintf('The `%s` KPI card must use the compact card padding (T7 KPI recipe).', $slot)
            );
            $this->assertStringContainsString(
                'var(--elevation-2)',
                $card['html'],
                sprintf('The `%s` KPI card must sit on the elevation-2 rung (T7 KPI recipe).', $slot)
            );
            $this->assertStringContainsString(
                'var(--color-hairline)',
                $card['html'],
                sprintf('The `%s` KPI card must carry the hairline border token (T7 KPI recipe).', $slot)
            );

            $valueTag = $this->extractBlock(
                '~<[a-z][^>]*\bdata-kpi-value="'.preg_quote($slot, '~').'"[^>]*>~',
                $card['html'],
                sprintf('The `%s` KPI card must expose data-kpi-value="%s" (T7 KPI anatomy).', $slot, $slot)
            );

            $this->assertStringContainsString(
                'tabular-nums',
                $valueTag,
                sprintf('The `%s` KPI numeral must carry tabular-nums (T7 numerals).', $slot)
            );
        }
    }

    /**
     * T7 - the error state is a top-of-page `role="alert"` block with a retry
     * action that re-emits `loadReport()`. It must precede the data surfaces
     * and the empty state must live behind the `!error` branch, so an error is
     * never rendered as an empty period.
     */
    public function test_error_state_is_retryable_and_never_renders_as_empty_data(): void
    {
        $src = $this->pageSource();

        $errorTag = $this->extractBlock(
            '~<DashboardSectionError\b[^>]*\bdata-state="error"[^>]*>~',
            $src,
            'BusinessIntelligencePage.vue must expose a data-state="error" block through DashboardSectionError (T7).'
        );

        $this->assertMatchesRegularExpression(
            '~\brole="alert"~',
            $errorTag,
            'The data-state="error" block must announce itself with role="alert" (T7).'
        );

        $this->assertMatchesRegularExpression(
            '~\bdata-retry\b~',
            $src,
            'The error block must expose a data-retry action (T7).'
        );

        $this->assertMatchesRegularExpression(
            '~<DashboardSectionError\b[\s\S]{0,400}?@retry="loadReport"~',
            $src,
            'The error block must wire its retry event to loadReport(), so Reintentar re-emits the request (T7).'
        );

        $errorPosition = strpos($src, 'data-state="error"');
        $firstKpiPosition = strpos($src, 'data-kpi-card=');
        $emptyPosition = strpos($src, 'data-report-empty');
        $noErrorBranch = strpos($src, 'v-else-if="!error"');

        $this->assertNotFalse($errorPosition, 'The error block must be present.');
        $this->assertNotFalse($firstKpiPosition, 'The KPI strip must be present.');
        $this->assertNotFalse($emptyPosition, 'The empty block must be present.');
        $this->assertNotFalse($noErrorBranch, 'The data surfaces must live behind a `v-else-if="!error"` branch.');

        $this->assertLessThan(
            $firstKpiPosition,
            $errorPosition,
            'The error block must render above the data surfaces, never as a trailing banner under empty results (T7).'
        );
        $this->assertGreaterThan(
            $noErrorBranch,
            $emptyPosition,
            'The empty block must sit behind the `!error` branch so an error is never rendered as an empty period (T7).'
        );
    }

    /**
     * T7 - the empty period renders the shared `<UiEmptyState>` primitive,
     * not an ad-hoc paragraph.
     */
    public function test_empty_state_uses_the_shared_primitive(): void
    {
        $src = $this->pageSource();

        $this->assertMatchesRegularExpression(
            '~<UiCard\b[^>]*\bdata-report-empty\b[^>]*>[\s\S]{0,500}?<UiEmptyState\b~',
            $src,
            'The data-report-empty block must render the shared <UiEmptyState>, not an ad-hoc empty paragraph (T7).'
        );
    }

    /**
     * T7 - the loading branch is a dedicated section carrying `aria-busy`
     * and rendering the shared `<UiSkeleton>`; the page root also reports
     * `aria-busy` while the applied period reloads (D11).
     */
    public function test_loading_branch_renders_skeletons_with_aria_busy(): void
    {
        $src = $this->pageSource();

        $loadingBlock = $this->extractBlock(
            '~<section\b[^>]*\bv-if="loading"[^>]*>[\s\S]*?</section>~',
            $src,
            'BusinessIntelligencePage.vue must render a dedicated loading section (T7).'
        );

        $this->assertStringContainsString(
            'aria-busy="true"',
            $loadingBlock,
            'The loading section must carry aria-busy="true" (T7).'
        );

        $this->assertMatchesRegularExpression(
            '~<UiSkeleton\b~',
            $loadingBlock,
            'The loading section must render the shared <UiSkeleton>, not a blank surface (T7).'
        );

        $this->assertMatchesRegularExpression(
            '~<div\b[^>]*\s:aria-busy="loading"[^>]*>~',
            $src,
            'The page root must report aria-busy while the applied period reloads (D11).'
        );
    }

    /**
     * T7 - the filters use the shared form controls: the raw `<select>` and
     * the `Debug:` diagnostics from the previous surface are gone.
     */
    public function test_filters_use_shared_controls_and_drop_debug_noise(): void
    {
        $src = $this->pageSource();
        $template = $this->templateSource($src);

        $this->assertDoesNotMatchRegularExpression(
            '~<select\b~',
            $template,
            'BusinessIntelligencePage.vue must not render a raw <select>: use <UiSelect> (T7).'
        );

        $this->assertStringNotContainsString(
            'Debug:',
            $src,
            'BusinessIntelligencePage.vue must not render `Debug:` diagnostics (T7).'
        );

        $this->assertMatchesRegularExpression(
            '~<UiSelect\b[^>]*\bdata-filters-professional\b~',
            $template,
            'The professional filter must be a <UiSelect> (T7).'
        );
        $this->assertMatchesRegularExpression(
            '~<UiSelect\b[^>]*\bdata-filters-environment\b~',
            $template,
            'The environment filter must be a <UiSelect> (T7).'
        );
        $this->assertMatchesRegularExpression(
            '~<UiSelect\b[^>]*\bdata-filters-report\b~',
            $template,
            'The report selector must be a <UiSelect> (T7).'
        );

        $this->assertMatchesRegularExpression(
            '~<UiButton\b[^>]*\s:data-preset="preset\.id"~',
            $template,
            'The period preset group must render one <UiButton> per preset through :data-preset (T7).'
        );

        foreach (['7d' => 7, '30d' => 30, '90d' => 90] as $preset => $days) {
            $this->assertStringContainsString(
                sprintf("{ id: '%s', label: ", $preset),
                $src,
                sprintf('The filter card must expose the %s period preset (T7).', $preset)
            );
            $this->assertStringContainsString(
                sprintf('days: %d }', $days),
                $src,
                sprintf('The %s period preset must cover %d days (T7).', $preset, $days)
            );
        }

        $this->assertMatchesRegularExpression(
            '~<UiCard\b[^>]*\bvariant="glass"[^>]*\bdata-bi-filters\b~',
            $template,
            'The filter card must reuse <UiCard variant="glass"> (T7).'
        );
    }

    /**
     * T7/T9 - every currency figure flows through the shared formatter; the
     * page never formats money with local Intl or `.toFixed`.
     */
    public function test_currency_goes_through_the_shared_formatter(): void
    {
        $src = $this->pageSource();
        $template = $this->templateSource($src);

        $this->assertMatchesRegularExpression(
            '~import\s*\{[^}]*\bformatCurrency\b[^}]*\}\s*from\s*[\'"][^\'"]*composables/useFormatters[\'"]~',
            $src,
            'BusinessIntelligencePage.vue must import formatCurrency from composables/useFormatters (T7/T9 currency contract).'
        );

        $this->assertMatchesRegularExpression(
            '~\bformatCurrency\(\s*~',
            $template,
            'The template must render currency through formatCurrency(...) (shared formatter).'
        );

        $revenueKpi = $this->extractBlock(
            '~<UiCard\b[^>]*\bdata-kpi-card="revenue"[^>]*>[\s\S]*?</UiCard>~',
            $src,
            'The KPI strip must keep the `revenue` slot (T7).'
        );
        $this->assertStringContainsString(
            'formatCurrency(dashboard.totalRevenue)',
            $revenueKpi,
            'The revenue KPI must read the backend `totalRevenue` through formatCurrency (T7 money contract).'
        );

        $this->assertStringContainsString(
            'formatCurrency(revenueSummary.total_revenue)',
            $src,
            'The revenue summary must read `meta.summary.total_revenue` through formatCurrency (T7 money contract).'
        );

        $this->assertMatchesRegularExpression(
            '~\bMONEY_KEYS\b[\s\S]{0,300}?formatCurrency\(value\)~',
            $src,
            'Money table columns must format through formatCurrency(value) (T7 money contract).'
        );

        $this->assertDoesNotMatchRegularExpression(
            '~\.toFixed\(\s*2\s*\)~',
            $src,
            'BusinessIntelligencePage.vue must not format money inline with .toFixed(2) (shared formatter).'
        );
        $this->assertDoesNotMatchRegularExpression(
            '~new Intl\.NumberFormat~',
            $src,
            'BusinessIntelligencePage.vue must not construct its own Intl.NumberFormat (shared formatter).'
        );
    }

    /**
     * T7 - status colors come from the tokenised systemGreen/systemRed ramps;
     * raw Tailwind green/red ramps are banned.
     */
    public function test_status_colors_use_token_ramps_only(): void
    {
        $src = $this->pageSource();

        $this->assertDoesNotMatchRegularExpression(
            '~(?<![\w-])(?:text|bg|border|ring|from|via|to)-(?:green|red|emerald|rose)-[0-9]{2,3}(?![\w-])~',
            $src,
            'BusinessIntelligencePage.vue must not use raw Tailwind green/red ramps; '
            .'consume the systemGreen/systemRed token ramps (T7).'
        );

        $revenueKpi = $this->extractBlock(
            '~<UiCard\b[^>]*\bdata-kpi-card="revenue"[^>]*>[\s\S]*?</UiCard>~',
            $src,
            'The KPI strip must keep the `revenue` slot (T7).'
        );
        $this->assertStringContainsString(
            'text-systemGreen-600',
            $revenueKpi,
            'The revenue KPI numeral must use the tokenised `text-systemGreen-600` ramp (T7).'
        );

        $this->assertStringContainsString(
            'DashboardSectionError',
            $src,
            'The retry block must reuse the dashboard section-error primitive, which paints the systemRed ramp (T7).'
        );
    }

    /**
     * T7/D11 - the page no longer subscribes to the public `dashboard-updates`
     * channel: refreshes are on demand for the applied period.
     */
    public function test_page_does_not_consume_the_public_dashboard_channel(): void
    {
        $src = $this->pageSource();

        $this->assertDoesNotMatchRegularExpression(
            '~\buseEcho\b~',
            $src,
            'BusinessIntelligencePage.vue must not import or call useEcho (D11).'
        );
        $this->assertStringNotContainsString(
            'dashboard-updates',
            $src,
            'BusinessIntelligencePage.vue must not subscribe to the public dashboard-updates channel (D11).'
        );
    }

    /**
     * T8 - both dashboard charts and the revenue cut expose stable canvas
     * hooks with accessible roles, and no canvas mounts without data.
     */
    public function test_chart_canvases_expose_stable_accessible_hooks(): void
    {
        $src = $this->pageSource();

        foreach (['appointments-by-day', 'revenue-by-month', 'revenue-by-day'] as $chart) {
            $canvas = $this->extractBlock(
                '~<canvas\b[^>]*\bdata-chart="'.preg_quote($chart, '~').'"[^>]*>~',
                $src,
                sprintf('BusinessIntelligencePage.vue must expose a <canvas data-chart="%s"> hook (T8).', $chart)
            );

            $this->assertMatchesRegularExpression(
                '~\brole="img"~',
                $canvas,
                sprintf('The `%s` chart canvas must declare role="img" (T8).', $chart)
            );
            $this->assertMatchesRegularExpression(
                '~\baria-label="[^"]+"~',
                $canvas,
                sprintf('The `%s` chart canvas must carry an aria-label (T8).', $chart)
            );
        }

        $dashboardCharts = $this->extractBlock(
            '~<section\b[^>]*\bdata-bi-charts\b[^>]*>~',
            $src,
            'BusinessIntelligencePage.vue must wrap the dashboard charts in a data-bi-charts section (T8).'
        );
        $this->assertMatchesRegularExpression(
            '~\bv-if="[^"]*\bhasDashboardCharts\b~',
            $dashboardCharts,
            'The dashboard charts section must render only when hasDashboardCharts is true; no canvas without data (T8).'
        );

        $this->assertMatchesRegularExpression(
            '~\bv-if="selectedReport === \'revenue\' && porDiaRows\.length > 0"~',
            $src,
            'The revenue chart must render only when the `Por Día` cut has rows; no canvas without data (T8).'
        );

        $this->assertMatchesRegularExpression(
            '~v-if="dashboard\.appointmentsByDay\.length > 0"~',
            $src,
            'The appointments canvas must render only when appointmentsByDay has data (T8).'
        );
        $this->assertMatchesRegularExpression(
            '~v-if="dashboard\.revenueByMonth\.length > 0"~',
            $src,
            'The revenue-by-month canvas must render only when revenueByMonth has data (T8).'
        );
    }

    /**
     * T8 - Chart.js loads through a dynamic import inside the render function,
     * the palette comes from tokens.js, every instance is responsive and the
     * previous instances are destroyed before a new render.
     */
    public function test_charts_follow_the_shared_chart_recipe(): void
    {
        $src = $this->pageSource();

        $this->assertMatchesRegularExpression(
            '~import\s*\{[^}]*\bcolors\b[^}]*\}\s*from\s*[\'"][^\'"]*design-system/tokens\.js[\'"]~',
            $src,
            'BusinessIntelligencePage.vue must read the chart palette from design-system/tokens.js (T8).'
        );

        $renderCharts = $this->extractBlock(
            '~const renderCharts = async \(\) => \{[\s\S]*?\n\}~',
            $src,
            'BusinessIntelligencePage.vue must keep the renderCharts() function (T8).'
        );

        $this->assertStringContainsString(
            "await import('chart.js')",
            $renderCharts,
            'Chart.js must be loaded with a dynamic import inside the render function (T8).'
        );
        $this->assertStringContainsString(
            'chartRenderToken',
            $renderCharts,
            'A render token must guard chart creation against stale loads (T8).'
        );
        $this->assertStringContainsString(
            'destroyCharts()',
            $renderCharts,
            'A re-render must destroy the previous chart instances (T8).'
        );

        $this->assertMatchesRegularExpression(
            '~const appointmentsChartConfig[\s\S]{0,800}?colors\.systemIndigo\[500\]~',
            $src,
            'The appointments chart must use the tokenised systemIndigo ramp (T8).'
        );
        $this->assertMatchesRegularExpression(
            '~const revenueByMonthChartConfig[\s\S]{0,800}?colors\.systemGreen\[500\]~',
            $src,
            'The revenue-by-month chart must use the tokenised systemGreen ramp (T8).'
        );
        $this->assertMatchesRegularExpression(
            '~const revenueByDayChartConfig[\s\S]{0,800}?colors\.accent\[500\]~',
            $src,
            'The revenue-by-day chart must use the tokenised accent ramp (T8).'
        );

        $this->assertSame(
            3,
            preg_match_all('~responsive: true~', $src),
            'Every chart config must set responsive: true (T8).'
        );
        $this->assertSame(
            3,
            preg_match_all('~maintainAspectRatio: false~', $src),
            'Every chart config must set maintainAspectRatio: false (T8).'
        );
        $this->assertSame(
            3,
            preg_match_all('~<div class="h-64">~', $src),
            'Every chart canvas must sit in an h-64 wrapper (T8).'
        );
    }

    /**
     * T9 - export rides useApi().download with the applied period; the page
     * never builds a raw fetch nor reads the token from localStorage.
     */
    public function test_export_uses_the_api_client_and_the_applied_period(): void
    {
        $src = $this->pageSource();

        $this->assertMatchesRegularExpression(
            '~const\s*\{[^}]*\bdownload\b[^}]*\}\s*=\s*useApi\(\)~',
            $src,
            'BusinessIntelligencePage.vue must take `download` from useApi() (T9).'
        );

        $exportHandler = $this->extractBlock(
            '~const exportReport = async format => \{[\s\S]*?\n\}~',
            $src,
            'BusinessIntelligencePage.vue must keep the exportReport() handler (T9).'
        );

        $this->assertStringContainsString(
            'download(',
            $exportHandler,
            'The export handler must fetch the file through useApi().download (T9).'
        );
        $this->assertStringContainsString(
            'appliedParams()',
            $exportHandler,
            'The export handler must send the applied period (T9).'
        );
        $this->assertStringNotContainsString(
            'draft.value',
            $exportHandler,
            'The export must read the applied period, never the draft filters (T9).'
        );
        $this->assertStringNotContainsString(
            'fetch(',
            $src,
            'BusinessIntelligencePage.vue must not build a raw fetch; useAi().download owns the auth headers (T9).'
        );
        $this->assertStringNotContainsString(
            "localStorage.getItem('auth_token')",
            $src,
            'BusinessIntelligencePage.vue must not read the auth token from localStorage (T9).'
        );

        $appliedParams = $this->extractBlock(
            '~const appliedParams = \(\) => \(\{[\s\S]*?\}\)~',
            $src,
            'BusinessIntelligencePage.vue must keep the appliedParams() builder (T9).'
        );
        $this->assertStringContainsString(
            'applied.value.from',
            $appliedParams,
            'The export period must use the applied `from` date (T9).'
        );
        $this->assertStringContainsString(
            'applied.value.to',
            $appliedParams,
            'The export period must use the applied `to` date (T9).'
        );
        $this->assertStringNotContainsString(
            'draft.value',
            $appliedParams,
            'The export period must never read the draft filters (T9).'
        );

        $api = $this->readSource(self::USE_API_PATH);
        $this->assertMatchesRegularExpression(
            '~const download = async \(url, options = \{\}\) => \{~',
            $api,
            'useApi must expose the additive download(url, options) method (T9).'
        );
        $this->assertStringContainsString(
            'getHeaders()',
            $api,
            'useApi.download must reuse the existing auth headers (T9).'
        );
        $this->assertStringContainsString(
            'content-disposition',
            strtolower($api),
            'useApi.download must read the filename from Content-Disposition (T9).'
        );
    }
}
