<?php

namespace Tests\Unit\DesignSystem;

/**
 * Branches UI redesign (2026-10-19) — app-shell rules for the
 * `settings/branches` module.
 *
 * The base class enforces the inherited DLR-R rules (canvas surface,
 * hairline vocabulary, focus ring, no `<style scoped>`) against the two
 * pages that carry the dashboard hero band: the directory list and the new
 * branch detail. The modal is covered by BranchesRedesignContractTest's
 * modal-chrome assertions instead, because it has no canvas surface.
 */
class BranchesAppShellTest extends ModuleAppShellTestCase
{
    private const LIST_PAGE_PATH = '/resources/js/modules/settings/branches/BranchesPage.vue';

    private const DETAIL_PAGE_PATH = '/resources/js/modules/settings/branches/BranchDetailPage.vue';

    /** @return array<int, string> */
    protected static function polishedFiles(): array
    {
        return [
            dirname(__DIR__, 3).self::LIST_PAGE_PATH,
            dirname(__DIR__, 3).self::DETAIL_PAGE_PATH,
        ];
    }
}
