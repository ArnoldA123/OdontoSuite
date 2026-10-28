<?php

namespace Tests\Unit\DesignSystem;

/**
 * Design contract for the treatment-plans module after
 * treatment-plans-ui-redesign (S1, S2, S5, 2026-10-20).
 *
 * The module was rebuilt on the dashboard recipe (hero band -> KPI strip ->
 * filters -> error/loading/empty states -> list or kanban surface) across its
 * four surfaces: page, plan detail, create/edit modal and the card / kanban /
 * status-badge pieces. Every assertion below pins the RULE behind a block, not
 * the markup of the moment: the hooks the redesign exposes
 * (`data-treatment-plans-hero`, `data-kpi-card`, `data-state="error"`,
 * `data-state="empty"`, `data-quick-filter`, `data-plan-card`,
 * `data-kanban-column`, `data-plan-detail`, `data-plan-modal`) plus the shared
 * primitives and tokens those hooks must resolve to. Removing a hook, a token
 * or the primitive backing it fails here even while the page still renders.
 *
 * The inherited DLR-R-001/002/004/021 assertions run through polishedFiles() on
 * the page only (a badge cannot reference the canvas token). Scoped-style
 * removal on the other five surfaces is asserted here directly.
 *
 * DOM behaviour lives in tests/js/smoke/treatment-plans*.test.js.
 */
class TreatmentPlansAppShellTest extends ModuleAppShellTestCase
{
    private const PAGE_PATH = '/resources/js/modules/treatment-plans/TreatmentPlansPage.vue';

    private const MODAL_PATH = '/resources/js/modules/treatment-plans/components/TreatmentPlanModal.vue';

    private const DETAIL_PATH = '/resources/js/modules/treatment-plans/components/TreatmentPlanDetail.vue';

    private const CARD_PATH = '/resources/js/modules/treatment-plans/components/TreatmentPlanCard.vue';

    private const KANBAN_PATH = '/resources/js/modules/treatment-plans/components/KanbanColumn.vue';

    private const BADGE_PATH = '/resources/js/modules/treatment-plans/components/PlanStatusBadge.vue';

    private const INLINE_PATIENT_PATH = '/resources/js/modules/treatment-plans/components/CreatePatientInline.vue';

    /** KPI slots, left to right: open volume first, money last. */
    private const KPI_SLOTS = ['active', 'pending-approval', 'overdue', 'pending-cost'];

    /** Quick-filter presets, each mapped to a real API filter (see L5). */
    private const QUICK_FILTERS = ['all', 'active', 'mine', 'overdue', 'completed'];

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

    /**
     * The outer `<template>` region: everything before the `<script>` block.
     * Scoping to it keeps a ban from being satisfied by a script constant.
     */
    private function templateSource(string $src): string
    {
        $scriptStart = strpos($src, '<script');
        $this->assertNotFalse($scriptStart, 'TreatmentPlansPage.vue must declare a <script> block.');

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
     * S2 - the four surfaces of the module stay present and edited together.
     */
    public function test_the_four_module_surfaces_stay_present(): void
    {
        foreach ([self::PAGE_PATH, self::MODAL_PATH, self::DETAIL_PATH, self::CARD_PATH, self::KANBAN_PATH, self::BADGE_PATH] as $path) {
            $this->readSource($path);
        }
    }

    /**
     * #242 - the detail dialog rides `UiModal`, so it must not keep a hand-rolled
     * overlay, backdrop, dialog role or document-level Escape listener of its
     * own: those belong to the primitive and its shared modal stack.
     */
    public function test_plan_detail_rides_the_modal_primitive(): void
    {
        $src = $this->readSource(self::DETAIL_PATH);

        $this->assertStringContainsString(
            '<UiModal',
            $src,
            'TreatmentPlanDetail.vue must mount the dialog through `UiModal` (#242).'
        );

        foreach (['fixed inset-0', 'backdrop-blur', 'role="dialog"', "document.addEventListener('keydown'"] as $ownContract) {
            $this->assertStringNotContainsString(
                $ownContract,
                $src,
                sprintf(
                    'TreatmentPlanDetail.vue must not hand-build `%s`; UiModal owns the dialog contract (#242).',
                    $ownContract
                )
            );
        }
    }

    /**
     * S1/S5 - no touched surface keeps a `<style scoped>` block: the dashboard
     * recipe rides utility classes plus the global token CSS.
     */
    public function test_no_surface_keeps_a_scoped_style_block(): void
    {
        foreach ([self::PAGE_PATH, self::MODAL_PATH, self::DETAIL_PATH, self::CARD_PATH, self::KANBAN_PATH, self::BADGE_PATH, self::INLINE_PATIENT_PATH] as $path) {
            $this->assertDoesNotMatchRegularExpression(
                '~<style\s+scoped\s*>~',
                $this->readSource($path),
                sprintf('%s must not contain a `<style scoped>` block (DLR-R-021): the module borrows the dashboard recipe.', $path)
            );
        }
    }

    /**
     * S1 - the hero band owns the page title and surface recipe; the old
     * `<PageHeader>` anatomy is gone. The band carries the eyebrow/title/
     * caption hooks plus the canvas + hairline + panel radius + elevation rung.
     */
    public function test_hero_band_replaces_page_header(): void
    {
        $src = $this->pageSource();

        $this->assertDoesNotMatchRegularExpression(
            '~<PageHeader\b~',
            $src,
            'TreatmentPlansPage.vue must not render <PageHeader>: the hero band owns the page title (S1).'
        );

        $hero = $this->extractBlock(
            '~<header\b[^>]*\bdata-treatment-plans-hero\b[^>]*>[\s\S]*?</header>~',
            $src,
            'TreatmentPlansPage.vue must render a <header> carrying data-treatment-plans-hero (S1).'
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

        // The smoke test mounts the page and asserts this exact string; it is
        // also the user-facing page title.
        $this->assertStringContainsString(
            'Planes de Tratamiento',
            $hero,
            'The hero band must keep the page title "Planes de Tratamiento" (smoke contract).'
        );
    }

    /**
     * S1/S3 - exactly four KPI slots backed by the summary endpoint, each a
     * shared `<UiCard>` on the elevation-2 + hairline recipe, each exposing a
     * matching `data-kpi-value` hook on a tabular numeral.
     */
    public function test_kpi_strip_renders_four_shared_cards_from_the_summary(): void
    {
        $src = $this->pageSource();
        $cards = $this->kpiCards($src);

        $this->assertCount(
            4,
            $cards,
            'TreatmentPlansPage.vue must render exactly four <UiCard data-kpi-card="..."> KPI slots (S1 KPI strip).'
        );

        $this->assertEqualsCanonicalizing(
            self::KPI_SLOTS,
            array_column($cards, 'slot'),
            'The KPI strip must keep the four slots: active, pending-approval, overdue, pending-cost (S1/S3).'
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
                '~<[a-z][^>]*\bdata-kpi-value="'.preg_quote($slot, '~').'"[^>]*>~',
                $card['html'],
                sprintf('The `%s` KPI card must expose data-kpi-value="%s" (S1 KPI anatomy).', $slot, $slot)
            );

            $this->assertStringContainsString(
                'tabular-nums',
                $valueTag,
                sprintf('The `%s` KPI numeral must carry tabular-nums (S1 numerals).', $slot)
            );
        }

        $this->assertStringContainsString(
            'getSummary',
            $src,
            'The KPI strip must be fed by the composable summary loader, never by the visible page (S3).'
        );
        $this->assertStringContainsString(
            '/api/treatment-plans/summary',
            $this->readSource('/resources/js/composables/useTreatmentPlans.js'),
            'useTreatmentPlans must request the module summary endpoint (S3).'
        );
    }

    /**
     * S1 - the error state is a top-of-page `role="alert"` block with a retry
     * action that re-emits the load. It must precede the data surfaces and must
     * never be rendered as empty results.
     */
    public function test_error_state_is_retryable_and_precedes_data(): void
    {
        $src = $this->pageSource();

        $errorTag = $this->extractBlock(
            '~<[a-z][^>]*\bdata-state="error"[^>]*>~',
            $src,
            'TreatmentPlansPage.vue must expose a data-state="error" block (S1).'
        );

        $this->assertMatchesRegularExpression(
            '~\brole="alert"~',
            $errorTag,
            'The data-state="error" block must announce itself with role="alert" (S1).'
        );

        $this->assertMatchesRegularExpression(
            '~\bdata-retry\b(?:(?!</UiButton>)[\s\S]){0,320}?@click="loadPlans~',
            $src,
            'The error block must expose a `data-retry` action wired to loadPlans(), so retry re-emits the request (S1).'
        );

        $errorPosition = strpos($src, 'data-state="error"');
        $firstKpiPosition = strpos($src, 'data-kpi-card=');
        $this->assertNotFalse($errorPosition, 'The error block must be present.');
        $this->assertNotFalse($firstKpiPosition, 'The KPI strip must be present.');
        $this->assertLessThan(
            $firstKpiPosition,
            $errorPosition,
            'The error block must render above the data surfaces, never as a trailing banner under empty results (S1).'
        );
    }

    /**
     * S1 - loading and empty are explicit states: skeletons behind `aria-busy`
     * and the shared empty primitive, never a blank surface.
     */
    public function test_loading_and_empty_states_use_shared_primitives(): void
    {
        $src = $this->pageSource();

        $loadingBlock = $this->extractBlock(
            '~<section\b[^>]*\bv-if="[^"]*\bloading\b[^"]*"[^>]*>[\s\S]*?</section>~',
            $src,
            'TreatmentPlansPage.vue must render a dedicated loading section (S1).'
        );

        $this->assertStringContainsString(
            'aria-busy="true"',
            $loadingBlock,
            'The loading section must carry aria-busy="true" (S1).'
        );

        $this->assertMatchesRegularExpression(
            '~<UiSkeleton\b~',
            $loadingBlock,
            'The loading section must render the shared <UiSkeleton>, not a blank surface (S1).'
        );

        $emptyCard = $this->extractBlock(
            '~<UiCard\b[^>]*\bdata-state="empty"[^>]*>~',
            $src,
            'TreatmentPlansPage.vue must expose a data-state="empty" surface (S1).'
        );

        $this->assertMatchesRegularExpression(
            '~\b(?:v-else-if|v-if)="[^"]*!loading~',
            $emptyCard,
            'The empty state must be guarded against the loading branch, so it never flashes over skeletons (S1).'
        );

        $this->assertMatchesRegularExpression(
            '~<UiCard\b[^>]*\bdata-state="empty"[^>]*>[\s\S]{0,600}?<UiEmptyState\b~',
            $src,
            'The data-state="empty" surface must render the shared <UiEmptyState>, not an ad-hoc paragraph (S1).'
        );
    }

    /**
     * S1/S4 - the filter bar keeps every existing filter and exposes the five
     * quick presets plus the list/kanban toggle through stable hooks.
     */
    public function test_filters_expose_presets_and_view_toggle_hooks(): void
    {
        $src = $this->pageSource();

        $filters = $this->extractBlock(
            '~<UiFilterBar\b[^>]*\bdata-filters\b[^>]*>[\s\S]*?</UiFilterBar>~',
            $src,
            'TreatmentPlansPage.vue must wrap its filters in the shared <UiFilterBar data-filters> (S1/S4 filter bar).'
        );

        foreach (['patient_name', 'status', 'date_from', 'date_to'] as $filter) {
            $this->assertStringContainsString(
                $filter,
                $filters,
                sprintf('The filter card must keep the `%s` filter (S4 preserved behavior).', $filter)
            );
        }

        $this->assertStringContainsString(
            ':data-quick-filter=',
            $filters,
            'The filter card must bind data-quick-filter on each preset pill (S4 presets).'
        );

        foreach (self::QUICK_FILTERS as $preset) {
            $this->assertMatchesRegularExpression(
                '~value:\s*[\'"]'.preg_quote($preset, '~').'[\'"]~',
                $src,
                sprintf('The quick-filter list must keep the `%s` preset (S4 presets).', $preset)
            );
        }

        $this->assertMatchesRegularExpression(
            '~data-view-toggle\b~',
            $filters,
            'The filter card must expose the list/kanban toggle through data-view-toggle (S4).'
        );

        foreach (['list', 'kanban'] as $view) {
            $this->assertMatchesRegularExpression(
                '~data-view="'.preg_quote($view, '~').'"~',
                $filters,
                sprintf('The view toggle must expose data-view="%s" (S4).', $view)
            );
        }

        $this->assertMatchesRegularExpression(
            '~data-create-plan\b~',
            $src,
            'The page must expose the primary create action through data-create-plan (S4 keyboard/CTA contract).'
        );
    }

    /**
     * L5/S4 - the presets must resolve to real API filters. `Activos` sends a
     * status list, `Vencidos` sends the overdue flag, `Míos` sends the author,
     * and the bare `status=active|mine|overdue` defect never returns.
     */
    public function test_quick_filters_map_to_real_api_filters(): void
    {
        $src = $this->pageSource();

        $this->assertDoesNotMatchRegularExpression(
            '~filters\.value\.status\s*=\s*qf\.value~',
            $src,
            'applyQuickFilter must not assign the preset value to the exact `status` filter: that is the defect behind dead pills (L5).'
        );

        foreach ([
            'statuses' => 'the active preset sends `statuses`',
            'overdue' => 'the overdue preset sends `overdue`',
            'created_by' => 'the mine preset sends `created_by`',
        ] as $param => $reason) {
            $this->assertStringContainsString(
                $param,
                $src,
                sprintf('The quick filters must map to the `%s` API filter: %s.', $param, $reason)
            );
        }
    }

    /**
     * S4 - pagination is driven by the response meta; the required `total` and
     * `perPage` props are passed instead of relying on defaults.
     */
    public function test_pagination_receives_total_and_per_page(): void
    {
        $src = $this->pageSource();

        $pagination = $this->extractBlock(
            '~<Pagination\b[^>]*>~',
            $src,
            'TreatmentPlansPage.vue must keep the shared <Pagination> (S4).'
        );

        foreach ([':total=', ':per-page='] as $prop) {
            $this->assertStringContainsString(
                $prop,
                $pagination,
                sprintf('Pagination must receive %s from the response meta (S4).', $prop)
            );
        }
    }

    /**
     * S2/S5 - status colours come from the shared StatusBadge primitive and its
     * token ramps, not from module-local alias classes.
     */
    public function test_status_badge_delegates_to_the_shared_primitive(): void
    {
        $src = $this->readSource(self::BADGE_PATH);

        $this->assertMatchesRegularExpression(
            '~import\s+StatusBadge\s+from\s+[\'"][^\'"]*components/ui/StatusBadge\.vue[\'"]~',
            $src,
            'PlanStatusBadge must delegate to the shared StatusBadge primitive (S2/S5).'
        );

        $this->assertMatchesRegularExpression(
            '~<StatusBadge\b~',
            $src,
            'PlanStatusBadge must render <StatusBadge>, keeping one status vocabulary (S2).'
        );
    }

    /**
     * S2 - each redesigned surface exposes one stable root hook, so the contract
     * survives markup rewrites.
     */
    public function test_each_surface_exposes_a_stable_root_hook(): void
    {
        $expected = [
            self::CARD_PATH => 'data-plan-card',
            self::KANBAN_PATH => 'data-kanban-column',
            self::DETAIL_PATH => 'data-plan-detail',
            self::MODAL_PATH => 'data-plan-modal',
        ];

        foreach ($expected as $path => $hook) {
            $this->assertStringContainsString(
                $hook,
                $this->readSource($path),
                sprintf('%s must expose its root hook `%s` (S2).', $path, $hook)
            );
        }
    }

    /**
     * S2/S4 - the kanban keeps its drag-and-drop contract: cards stay
     * draggable and columns keep the drop handler that emits the status change.
     */
    public function test_kanban_keeps_its_drag_and_drop_contract(): void
    {
        $src = $this->readSource(self::KANBAN_PATH);

        $this->assertStringContainsString(
            'draggable="true"',
            $src,
            'Kanban cards must stay draggable (S4 preserved behavior).'
        );
        $this->assertMatchesRegularExpression(
            '~@drop\.prevent="onDrop"~',
            $src,
            'Kanban columns must keep the @drop.prevent="onDrop" handler (S4).'
        );
        $this->assertStringContainsString(
            "emit('dropPlan'",
            $src,
            'The drop handler must keep emitting dropPlan for the page to call change-status (S4).'
        );
    }

    /**
     * S5 - the module consumes token ramps only; raw purple/amber/emerald/rose
     * ramps are banned across every touched surface.
     */
    public function test_no_raw_palette_ramps_in_touched_surfaces(): void
    {
        $pattern = '~(?<![\w-])(?:text|bg|border|ring|from|via|to)-(?:purple|violet|amber|emerald|teal|lime|rose|pink|indigo|fuchsia)-[0-9]{2,3}(?![\w-])~';

        foreach ([self::PAGE_PATH, self::MODAL_PATH, self::DETAIL_PATH, self::CARD_PATH, self::KANBAN_PATH, self::BADGE_PATH, self::INLINE_PATIENT_PATH] as $path) {
            $this->assertDoesNotMatchRegularExpression(
                $pattern,
                $this->readSource($path),
                sprintf('%s must consume token ramps instead of raw Tailwind palette classes (S5).', $path)
            );
        }
    }
}
