<?php

namespace Tests\Unit\DesignSystem;

/**
 * Specialties module — per-module design-system guard
 * (specialties-interface-redesign).
 *
 * Extends `ModuleAppShellTestCase` so the five inherited DLR-R rules (canvas
 * token, no legacy `border-theme` literal, focus-ring token, no legacy
 * focus-ring aliases, no `<style scoped>` block) fire against both surfaces of
 * the redesign: the list page and the create/edit modal.
 *
 * The redesign contract itself (hero band, KPI strip, table primitive,
 * deactivation-only actions, transient states, server-side filters, shared
 * vocabulary) is pinned by the sibling `SpecialtiesRedesignContractTest`.
 */
class SpecialtiesAppShellTest extends ModuleAppShellTestCase
{
    /** List page path constant — single source of truth for the data provider. */
    private const PAGE_PATH = '/resources/js/modules/specialties/SpecialtiesPage.vue';

    /** Create/edit modal path constant. */
    private const MODAL_PATH = '/resources/js/modules/specialties/SpecialtyFormModal.vue';

    /** @return array<int, string> */
    protected static function polishedFiles(): array
    {
        return [
            dirname(__DIR__, 3).self::PAGE_PATH,
            dirname(__DIR__, 3).self::MODAL_PATH,
        ];
    }
}
