<?php

namespace Tests\Unit\DesignSystem;

use PHPUnit\Framework\TestCase;

/**
 * Appointment-types redesign contract (2026-10-10, commits bacec66 / 6ed3e4f).
 *
 * Source-inspection guards for the list + detail pages of the
 * `appointment-types` module: the hero band surface recipe, the KPI strip
 * anatomy, the `default_duration_minutes` duration fix, the removed View
 * modal, the detail skeleton + audit diff ramps, the file-hygiene bans and
 * the `?edit=<id>` deep-link pair. DOM behavior is covered by
 * tests/js/smoke/appointment-types.test.js and
 * tests/js/smoke/appointment-types-detail.test.js; these rules are the
 * cheap re-run gate for the source anatomy.
 */
class AppointmentTypesRedesignContractTest extends TestCase
{
    private static function projectRootPath(): string
    {
        return dirname(__DIR__, 3);
    }

    private const LIST_PAGE = '/resources/js/modules/appointment-types/AppointmentTypesPage.vue';
    private const DETAIL_PAGE = '/resources/js/modules/appointment-types/AppointmentTypeDetailPage.vue';

    private static function read(string $rel): string
    {
        $path = self::projectRootPath() . $rel;
        return is_file($path) ? (string) file_get_contents($path) : '';
    }

    private function listSource(): string
    {
        $src = self::read(self::LIST_PAGE);
        $this->assertNotSame('', $src, 'AppointmentTypesPage.vue must exist');
        return $src;
    }

    private function detailSource(): string
    {
        $src = self::read(self::DETAIL_PAGE);
        $this->assertNotSame('', $src, 'AppointmentTypeDetailPage.vue must exist');
        return $src;
    }

    /**
     * Hero band recipe - the list hero paints the canvas base under a
     * hairline border, the panel radius and the elevation-2 rung.
     */
    public function test_list_page_keeps_hero_band_recipe(): void
    {
        $src = $this->listSource();

        $this->assertStringContainsString(
            'background: var(--color-canvas)',
            $src,
            'AppointmentTypesPage.vue must paint the hero band on the canvas base (redesign 2026-10-10).'
        );
        $this->assertStringContainsString(
            'border: 1px solid var(--color-hairline)',
            $src,
            'The list hero band must carry the hairline vocabulary (redesign 2026-10-10).'
        );
        $this->assertStringContainsString(
            'border-radius: var(--radius-panel)',
            $src,
            'The list hero band must ride the panel radius (redesign 2026-10-10).'
        );
        $this->assertStringContainsString(
            'box-shadow: var(--elevation-2)',
            $src,
            'The list hero band must sit on the elevation-2 rung (redesign 2026-10-10).'
        );
    }

    /**
     * Hero band recipe - the detail hero repeats the same surface recipe
     * (canvas base + hairline + panel radius + elevation-2).
     */
    public function test_detail_page_keeps_hero_band_recipe(): void
    {
        $src = $this->detailSource();

        $this->assertStringContainsString(
            'background: var(--color-canvas)',
            $src,
            'AppointmentTypeDetailPage.vue must paint the hero band on the canvas base (redesign 2026-10-10).'
        );
        $this->assertStringContainsString(
            'border: 1px solid var(--color-hairline)',
            $src,
            'The detail hero band must carry the hairline vocabulary (redesign 2026-10-10).'
        );
        $this->assertStringContainsString(
            'border-radius: var(--radius-panel)',
            $src,
            'The detail hero band must ride the panel radius (redesign 2026-10-10).'
        );
        $this->assertStringContainsString(
            'box-shadow: var(--elevation-2)',
            $src,
            'The detail hero band must sit on the elevation-2 rung (redesign 2026-10-10).'
        );
    }

    /**
     * KPI strip contract - exactly four KPI slots (total / active /
     * inactive / avg-duration) anchored on `data-kpi-card` +
     * `data-kpi-value`, derived from the same loaded catalog the table
     * renders.
     */
    public function test_list_page_kpi_strip_has_exactly_four_kpi_slots(): void
    {
        $src = $this->listSource();

        $this->assertSame(
            4,
            preg_match_all('#data-kpi-value="#', $src),
            'AppointmentTypesPage.vue must render exactly four data-kpi-value slots (redesign 2026-10-10).'
        );
        $this->assertSame(
            4,
            preg_match_all('#data-kpi-card="#', $src),
            'AppointmentTypesPage.vue must render exactly four data-kpi-card slots (redesign 2026-10-10).'
        );

        foreach (['total', 'active', 'inactive', 'avg-duration'] as $slot) {
            $this->assertStringContainsString(
                sprintf('data-kpi-value="%s"', $slot),
                $src,
                sprintf('The KPI strip must keep the `%s` slot (redesign 2026-10-10).', $slot)
            );
        }
    }

    /**
     * Section heading grammar - the list section keeps the label heading
     * tier `text-2xl font-semibold text-label`.
     */
    public function test_list_page_keeps_the_section_heading_grammar(): void
    {
        $src = $this->listSource();

        $this->assertMatchesRegularExpression(
            '#<h2 class="text-2xl font-semibold text-label">#',
            $src,
            'AppointmentTypesPage.vue must keep the section heading grammar '
            . '`<h2 class="text-2xl font-semibold text-label">` (redesign 2026-10-10).'
        );
    }

    /**
     * Duration field fix - the table cell and the KPI average READ the API
     * field `default_duration_minutes`; the legacy read
     * `type.duration_minutes` is gone.
     */
    public function test_list_page_reads_default_duration_minutes_for_the_duration_cell(): void
    {
        $src = $this->listSource();

        $this->assertGreaterThanOrEqual(
            2,
            preg_match_all('#type\.default_duration_minutes#', $src),
            'AppointmentTypesPage.vue must read `type.default_duration_minutes` for the duration '
            . 'cell AND the KPI average (the 2026-10-10 duration fix).'
        );
        $this->assertSame(
            0,
            preg_match('#(?<![\w-])type\.duration_minutes(?![\w-])#', $src),
            'AppointmentTypesPage.vue must NOT read the legacy `type.duration_minutes` field '
            . '(the 2026-10-10 duration fix: reads use `type.default_duration_minutes`).'
        );
    }

    /**
     * Duration field fix (write path) - the API write contract validates
     * `duration_minutes` (`AppointmentTypeController::storeRules()`) and
     * stores it as `default_duration_minutes`, so the ONLY tolerated
     * non-`default_` duration references are the create/edit form payload
     * (`newType.duration_minutes`, `editingType.duration_minutes`, and the
     * `duration_minutes:` form-state initializer). Any other bare
     * `duration_minutes` reference is a regression of the fix.
     */
    public function test_list_page_confines_bare_duration_minutes_to_the_write_payload(): void
    {
        $src = $this->listSource();

        $stripped = str_replace('default_duration_minutes', '', $src);
        $stripped = preg_replace('#(?:newType|editingType)\.duration_minutes\b#', '', $stripped);
        $stripped = preg_replace('#\bduration_minutes\s*:#', '', $stripped);

        $this->assertIsString($stripped);
        $this->assertStringNotContainsString(
            'duration_minutes',
            $stripped,
            'Every non-`default_` `duration_minutes` reference in AppointmentTypesPage.vue must be the '
            . 'create/edit write-payload field (`newType.duration_minutes` / `editingType.duration_minutes` / '
            . '`duration_minutes:` initializer) mandated by AppointmentTypeController::storeRules(). '
            . 'Anything else re-opens the pre-redesign duration bug.'
        );
    }

    /**
     * Removed View modal - the `viewingType` / `viewType` state and the
     * `showViewTypeModal` flag are gone for good (Ver Detalle navigates to
     * the detail page instead).
     */
    public function test_list_page_keeps_the_view_modal_removed(): void
    {
        $src = $this->listSource();

        foreach (['viewingType', 'viewType', 'showViewTypeModal'] as $anchor) {
            $this->assertSame(
                0,
                preg_match('#(?<![\w-])' . preg_quote($anchor, '#') . '(?![\w-])#i', $src),
                sprintf(
                    'AppointmentTypesPage.vue must NOT resurrect the removed View modal anchor `%s` '
                    . '(redesign 2026-10-10: Ver Detalle navigates to the detail page).',
                    $anchor
                )
            );
        }
    }

    /**
     * Detail skeleton anchor - the loading state keeps its
     * `data-detail-skeleton` hook.
     */
    public function test_detail_page_keeps_the_skeleton_anchor(): void
    {
        $src = $this->detailSource();

        $this->assertStringContainsString(
            'data-detail-skeleton',
            $src,
            'AppointmentTypeDetailPage.vue must keep the `data-detail-skeleton` loading anchor '
            . '(redesign 2026-10-10).'
        );
    }

    /**
     * Audit diff ramps - the change diff keeps the token-aligned system
     * ramps for the De:/A: spans.
     */
    public function test_detail_page_keeps_the_audit_diff_ramps(): void
    {
        $src = $this->detailSource();

        $this->assertStringContainsString(
            'text-systemRed-600',
            $src,
            'AppointmentTypeDetailPage.vue must keep `text-systemRed-600` on the audit diff old-value span '
            . '(TIPOS-02-005).'
        );
        $this->assertStringContainsString(
            'text-systemGreen-600',
            $src,
            'AppointmentTypeDetailPage.vue must keep `text-systemGreen-600` on the audit diff new-value span '
            . '(TIPOS-02-005).'
        );
    }

    /**
     * HOTFIX em-dash ban - zero U+2014 characters file-wide on both pages
     * (block comments included, same scope as DashboardHierarchyContractTest).
     */
    public function test_pages_keep_the_em_dash_ban(): void
    {
        foreach ([self::LIST_PAGE, self::DETAIL_PAGE] as $rel) {
            $src = self::read($rel);
            $this->assertNotSame('', $src, sprintf('%s must exist', $rel));

            $this->assertSame(
                0,
                preg_match_all('/\x{2014}/u', $src),
                sprintf('%s must contain zero U+2014 em-dash characters (HOTFIX em-dash ban).', $rel)
            );
        }
    }

    /**
     * No gradient - zero `bg-gradient` classes file-wide on both pages
     * (global guard rail #10).
     */
    public function test_pages_keep_the_no_gradient_ban(): void
    {
        foreach ([self::LIST_PAGE, self::DETAIL_PAGE] as $rel) {
            $src = self::read($rel);
            $this->assertNotSame('', $src, sprintf('%s must exist', $rel));

            $this->assertSame(
                0,
                preg_match_all('#(?<![\w-])bg-gradient\b#', $src),
                sprintf('%s must contain zero `bg-gradient` classes (global guard rail #10).', $rel)
            );
        }
    }

    /**
     * No scoped style block - both pages render on Tailwind utilities +
     * global token CSS (DLR-R-021).
     */
    public function test_pages_keep_the_no_scoped_style_ban(): void
    {
        foreach ([self::LIST_PAGE, self::DETAIL_PAGE] as $rel) {
            $src = self::read($rel);
            $this->assertNotSame('', $src, sprintf('%s must exist', $rel));

            $this->assertSame(
                0,
                preg_match('#<style\s+scoped\s*>#', $src),
                sprintf('%s must contain zero `<style scoped>` blocks (DLR-R-021).', $rel)
            );
        }
    }

    /**
     * No hand-written hex colors - zero hex color literals file-wide; the
     * design tokens own every colour value.
     */
    public function test_pages_keep_the_no_hex_literal_ban(): void
    {
        foreach ([self::LIST_PAGE, self::DETAIL_PAGE] as $rel) {
            $src = self::read($rel);
            $this->assertNotSame('', $src, sprintf('%s must exist', $rel));

            $this->assertSame(
                0,
                preg_match_all('~#[0-9a-fA-F]{3,8}~', $src),
                sprintf(
                    '%s must contain zero hand-written hex color literals `#[0-9a-fA-F]{3,8}`; '
                    . 'design tokens own every colour value.',
                    $rel
                )
            );
        }
    }

    /**
     * Deep-link contract (detail side) - the `Editar` hero action
     * deep-links into the shared edit modal through the `edit` query
     * parameter via `goEdit`.
     */
    public function test_detail_page_keeps_the_edit_query_navigation(): void
    {
        $src = $this->detailSource();

        $this->assertStringContainsString(
            'const goEdit',
            $src,
            'AppointmentTypeDetailPage.vue must keep the `goEdit` handler (redesign 2026-10-10 deep-link contract).'
        );
        $this->assertMatchesRegularExpression(
            '#query\s*:\s*\{\s*edit\s*:\s*String\(#',
            $src,
            'AppointmentTypeDetailPage.vue must deep-link with `query: { edit: String(id) }` '
            . '(redesign 2026-10-10 deep-link contract).'
        );
    }

    /**
     * Deep-link contract (list side) - the list page consumes the `edit`
     * query parameter through `openEditFromQuery` and clears it.
     */
    public function test_list_page_keeps_the_open_edit_from_query_handler(): void
    {
        $src = $this->listSource();

        $this->assertStringContainsString(
            'const openEditFromQuery',
            $src,
            'AppointmentTypesPage.vue must keep the `openEditFromQuery` handler (redesign 2026-10-10 deep-link contract).'
        );
        $this->assertMatchesRegularExpression(
            '#openEditFromQuery\(\)#',
            $src,
            'AppointmentTypesPage.vue must invoke `openEditFromQuery()` after loading the catalog '
            . '(redesign 2026-10-10 deep-link contract).'
        );
        $this->assertMatchesRegularExpression(
            '#route\.query\.edit#',
            $src,
            'AppointmentTypesPage.vue must read `route.query.edit` (redesign 2026-10-10 deep-link contract).'
        );
    }
}
