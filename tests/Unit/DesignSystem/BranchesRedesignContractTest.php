<?php

namespace Tests\Unit\DesignSystem;

use PHPUnit\Framework\TestCase;

/**
 * Branches UI redesign (2026-10-19) — source contracts.
 *
 * The directory list and the branch detail ride the dashboard surface recipe
 * (canvas base + hairline + panel radius + elevation-2) and the shared state
 * ladder; the list owns exactly four KPI slots derived from the loaded
 * directory, filters client-side through `<UiSelect>` (never a raw
 * `<select>`), renders through `<UiDataTable>` and has no server pagination
 * path left. The detail exposes the three definition groups the API already
 * returns, and the edit modal keeps `code` immutable while supporting the
 * API's postal_code/timezone fields. DOM behaviour is pinned by
 * tests/js/smoke/branches*.test.js; these rules are the cheap source gate.
 */
class BranchesRedesignContractTest extends TestCase
{
    private const LIST_PAGE = '/resources/js/modules/settings/branches/BranchesPage.vue';

    private const DETAIL_PAGE = '/resources/js/modules/settings/branches/BranchDetailPage.vue';

    private const FORM_MODAL = '/resources/js/modules/settings/branches/BranchFormModal.vue';

    private const PRESENTATION = '/resources/js/modules/settings/branches/branchPresentation.js';

    private const APP_JS = '/resources/js/app.js';

    private static function projectRootPath(): string
    {
        return dirname(__DIR__, 3);
    }

    private static function read(string $rel): string
    {
        $path = self::projectRootPath().$rel;

        return is_file($path) ? (string) file_get_contents($path) : '';
    }

    private function source(string $rel): string
    {
        $src = self::read($rel);
        $this->assertNotSame('', $src, "{$rel} must exist");

        return $src;
    }

    /**
     * The list and the detail repeat the same hero band recipe the dashboard
     * and its sibling modules use.
     */
    public function test_pages_keep_the_dashboard_hero_band_recipe(): void
    {
        foreach ([self::LIST_PAGE, self::DETAIL_PAGE] as $page) {
            $src = $this->source($page);

            $this->assertStringContainsString('background: var(--color-canvas)', $src, "{$page} hero must paint the canvas base");
            $this->assertStringContainsString('border: 1px solid var(--color-hairline)', $src, "{$page} hero must carry the hairline");
            $this->assertStringContainsString('border-radius: var(--radius-panel)', $src, "{$page} hero must ride the panel radius");
            $this->assertStringContainsString('box-shadow: var(--elevation-2)', $src, "{$page} hero must sit on elevation-2");
        }
    }

    /**
     * Exactly four KPI slots, anchored on data-kpi-card + data-kpi-value and
     * derived from the same loaded directory the table renders.
     */
    public function test_list_kpi_strip_has_exactly_four_derived_slots(): void
    {
        $src = $this->source(self::LIST_PAGE);

        $this->assertSame(4, substr_count($src, 'data-kpi-card='), 'The branches list must expose exactly four KPI cards');

        foreach (['total', 'active', 'inactive', 'cities'] as $slot) {
            $this->assertStringContainsString(
                'data-kpi-value="'.$slot.'"',
                $src,
                "The branches KPI strip must expose the {$slot} slot"
            );
        }

        $this->assertStringContainsString("loadError.value ? 'N/D'", $src, 'The KPI strip must degrade to N/D when the load fails');
    }

    /**
     * The status filter consumes the canonical `<UiSelect>`; no raw
     * `<select>` element and no dead Pagination path remain.
     */
    public function test_list_uses_design_system_filters_and_no_dead_pagination(): void
    {
        $src = $this->source(self::LIST_PAGE);

        $this->assertStringContainsString('<UiSelect', $src, 'The status filter must use the canonical UiSelect');
        $this->assertStringContainsString('data-status-filter', $src, 'The status filter must expose its data hook');
        $this->assertStringContainsString('data-branch-search', $src, 'The search field must expose its data hook');
        $this->assertStringContainsString('data-filter-count', $src, 'The filter count must expose its data hook');

        $this->assertDoesNotMatchRegularExpression('/<select\b/', $src, 'The branches list must not render a raw <select>');
        $this->assertStringNotContainsString('components/ui/Pagination.vue', $src, 'The dead server-side pagination must be gone');
        $this->assertStringNotContainsString('<Pagination', $src, 'The dead server-side pagination must be gone');
    }

    /**
     * The list renders through the shared table primitive and ships the full
     * state ladder (skeleton, inline error + retry, contextual empty state).
     */
    public function test_list_uses_the_table_primitive_and_the_full_state_ladder(): void
    {
        $src = $this->source(self::LIST_PAGE);

        foreach (['UiDataTable', 'UiSkeleton', 'UiEmptyState', 'UiStatusBadge'] as $primitive) {
            $this->assertStringContainsString($primitive, $src, "The branches list must consume {$primitive}");
        }

        foreach (['data-branches-loading', 'data-branches-error', 'data-retry-branches', 'data-branches-empty', 'data-branches-table'] as $hook) {
            $this->assertStringContainsString($hook, $src, "The branches list must expose {$hook}");
        }

        foreach (['detail', 'edit', 'toggle'] as $action) {
            $this->assertStringContainsString(
                'data-row-action="'.$action.'"',
                $src,
                "The branches list must expose the {$action} row action"
            );
        }
    }

    /**
     * The detail page repeats the state ladder and exposes the definition
     * groups the API already returns, with an edit handoff to the list modal.
     */
    public function test_detail_exposes_the_definition_groups_and_edit_handoff(): void
    {
        $src = $this->source(self::DETAIL_PAGE);

        foreach (['data-branch-detail-hero', 'data-branch-detail-loading', 'data-branch-detail-error', 'data-retry-branch-detail', 'data-branch-summary', 'data-branch-detail-edit'] as $hook) {
            $this->assertStringContainsString($hook, $src, "The branch detail must expose {$hook}");
        }

        foreach (['address', 'city', 'state', 'country', 'postal_code', 'phone', 'email', 'timezone', 'coordinates', 'description', 'created_at'] as $field) {
            $this->assertStringContainsString(
                'data-detail-field="'.$field.'"',
                $src,
                "The branch detail must expose the {$field} definition row"
            );
        }

        $this->assertStringContainsString('UiStatusBadge', $src, 'The branch detail must consume the shared status badge');
    }

    /**
     * The detail route is registered and the detail page is lazily imported.
     */
    public function test_detail_route_is_registered(): void
    {
        $src = $this->source(self::APP_JS);

        $this->assertStringContainsString("path: '/settings/branches/:id'", $src);
        $this->assertStringContainsString('BranchDetailPage.vue', $src);
        $this->assertStringContainsString('settings-branches-detail', $src);
    }

    /**
     * The create/edit modal groups its fields, covers the API's
     * postal_code/timezone columns and keeps the code immutable on edit.
     */
    public function test_form_modal_groups_fields_and_keeps_code_immutable(): void
    {
        $src = $this->source(self::FORM_MODAL);

        foreach (['code', 'name', 'address', 'city', 'state', 'country', 'postal_code', 'phone', 'email', 'timezone', 'is_active', 'description'] as $field) {
            $this->assertStringContainsString(
                'data-field="'.$field.'"',
                $src,
                "The branch form must expose the {$field} field"
            );
        }

        $this->assertStringContainsString(':disabled="isEdit"', $src, 'The code input must be immutable on edit');
        $this->assertStringContainsString('data-modal-submit', $src);
        $this->assertStringContainsString('data-modal-cancel', $src);
        $this->assertStringContainsString('<UiSelect', $src, 'The status field must use the canonical UiSelect');
        $this->assertDoesNotMatchRegularExpression('/<select\b/', $src, 'The branch form must not render a raw <select>');
    }

    /**
     * The modal chrome stays inside the design-system vocabulary even though
     * it is not part of the canvas-surface file set.
     */
    public function test_form_modal_keeps_the_design_system_chrome(): void
    {
        $src = $this->source(self::FORM_MODAL);

        $this->assertStringNotContainsString('border-theme', $src);
        $this->assertStringNotContainsString('focus:ring-primary-500', $src);
        $this->assertStringNotContainsString('focus:border-accent', $src);
        $this->assertStringNotContainsString('<style scoped>', $src);
    }

    /**
     * One shared presentation vocabulary keeps the list and the detail from
     * drifting on status labels/variants and location formatting.
     */
    public function test_list_and_detail_share_the_presentation_helpers(): void
    {
        $this->assertNotSame('', $this->source(self::PRESENTATION), 'branchPresentation.js must exist');
        $this->assertStringContainsString('branchPresentation', $this->source(self::LIST_PAGE));
        $this->assertStringContainsString('branchPresentation', $this->source(self::DETAIL_PAGE));
    }
}
