<?php

namespace Tests\Unit\DesignSystem;

use PHPUnit\Framework\TestCase;

/**
 * T1 — Professionals module design-system guards
 * (odd/tasks/professionals-interface-redesign.md).
 *
 * Source-inspection guards over resources/js/modules/professionals/*.vue.
 * Each rule is RED before the T1 vocabulary-normalization edit lands and
 * GREEN after:
 *
 *   - no legacy alias classes (border-theme, focus:ring-primary-500,
 *     focus:border-accent, bg-theme-surface-elevated, text-theme-primary)
 *   - no hand-built pill/badge backgrounds (bg-primary-100, bg-success-100,
 *     bg-error-100) nor their token/var form bg-[color:var(--color-*-100)]
 *     (T4, RELIABILITY-004) — pills come from <UiBadge>
 *   - no raw <select> elements — selects come from <UiSelect>
 *   - no native confirm() leak — every confirm() call site must be an awaited
 *     consumption gating the destructive action
 *     (`const ok = await confirm({...})` + `if (ok)`) (T6, RELIABILITY-001)
 *   - no U+2014 em-dash in module sources
 *   - both page files import the shared vocabulary from
 *     ./professionals.constants (kills the duplicated specialty map)
 *
 * T6 (RELIABILITY-003): import/export assertions match quote-style
 * independently (single or double quotes), the './professionals.constants'
 * import accepts an optional '.js' suffix, and the constants contract accepts
 * both `export const NAME =` and `export { NAME }` forms. The contracts
 * (which module is imported, which names are exported, which copy is frozen)
 * are unchanged; only the quoting noise is tolerated.
 */
class ProfessionalsAppShellTest extends TestCase
{
    private const MODULE_DIR = '/resources/js/modules/professionals';

    private const PAGE_FILES = [
        '/resources/js/modules/professionals/ProfessionalsPage.vue',
        '/resources/js/modules/professionals/ProfessionalDetailPage.vue',
    ];

    private const BANNED_LEGACY_ALIASES = [
        'border-theme',
        'focus:ring-primary-500',
        'focus:border-accent',
        'bg-theme-surface-elevated',
        'text-theme-primary',
    ];

    private const BANNED_HAND_BUILT_BADGES = [
        'bg-primary-100',
        'bg-success-100',
        'bg-error-100',
        'bg-[color:var(--color-primary-100)]',
        'bg-[color:var(--color-success-100)]',
        'bg-[color:var(--color-error-100)]',
    ];

    private static function projectRootPath(): string
    {
        return dirname(__DIR__, 3);
    }

    private static function readSource(string $absPath): string
    {
        self::assertFileExists($absPath, "Source file must exist: {$absPath}");
        $src = file_get_contents($absPath);
        self::assertNotFalse($src, "Source file must be readable: {$absPath}");

        return (string) $src;
    }

    /**
     * @return array<string, string> absolute path => source
     */
    private static function moduleVueSources(): array
    {
        $files = glob(self::projectRootPath().self::MODULE_DIR.'/*.vue');
        self::assertIsArray($files);
        self::assertNotEmpty($files, 'resources/js/modules/professionals/ must contain .vue sources');
        sort($files);

        $sources = [];
        foreach ($files as $file) {
            $sources[$file] = self::readSource($file);
        }

        return $sources;
    }

    private static function shortName(string $absPath): string
    {
        return basename($absPath);
    }

    public function test_module_vues_ban_legacy_alias_classes(): void
    {
        foreach (self::moduleVueSources() as $path => $src) {
            foreach (self::BANNED_LEGACY_ALIASES as $alias) {
                self::assertStringNotContainsString(
                    $alias,
                    $src,
                    self::shortName($path)." must not use the legacy alias class '{$alias}' (T1: token utilities only)."
                );
            }
        }
    }

    public function test_module_vues_ban_hand_built_badge_backgrounds(): void
    {
        foreach (self::moduleVueSources() as $path => $src) {
            foreach (self::BANNED_HAND_BUILT_BADGES as $badgeClass) {
                self::assertStringNotContainsString(
                    $badgeClass,
                    $src,
                    self::shortName($path)." must not build pills by hand with '{$badgeClass}' (D6: <UiBadge> is the single pill system)."
                );
            }
        }
    }

    public function test_module_vues_use_select_primitive_not_raw_select(): void
    {
        foreach (self::moduleVueSources() as $path => $src) {
            self::assertStringNotContainsString(
                '<select',
                $src,
                self::shortName($path).' must not render raw <select> elements (D8: <UiSelect> primitive).'
            );
        }
    }

    public function test_module_vues_ban_native_confirm(): void
    {
        $callSiteTotal = 0;
        foreach (self::moduleVueSources() as $path => $src) {
            self::assertConfirmCallSitesAreAwaitedAndGuarded($src, self::shortName($path));
            preg_match_all('/(?<![\w.$])confirm\s*\(/', $src, $matches);
            $callSiteTotal += count($matches[0]);
        }

        self::assertGreaterThan(
            0,
            $callSiteTotal,
            'The professionals module must route its destructive actions through confirm() from useConfirm (D12; zero confirm call sites in the whole module would make this rule vacuous).'
        );
    }

    /**
     * T6 (RELIABILITY-001) — hardened D12 rule. Merely mentioning useConfirm
     * is not enough: every confirm( call site must be an awaited consumption
     * (`const ok = await confirm({...})`) whose result gates the destructive
     * action (`if (ok)`). An unawaited confirm resolves to a Promise, which is
     * always truthy, so a bare/unchecked call would silently delete; the old
     * substring rule let that slip. Files without call sites pass here (the
     * detail page has no destructive action); the test method anchors at
     * least one confirm call site across the whole module so the rule can
     * never pass vacuously.
     */
    private static function assertConfirmCallSitesAreAwaitedAndGuarded(string $src, string $shortName): void
    {
        self::assertStringNotContainsString(
            'window.confirm',
            $src,
            "{$shortName} must not call window.confirm (D12: useConfirm composable)."
        );

        preg_match_all('/(?<![\w.$])confirm\s*\(/', $src, $matches, PREG_OFFSET_CAPTURE);
        $callSites = $matches[0];
        if ($callSites === []) {
            return;
        }

        self::assertStringContainsString(
            'useConfirm',
            $src,
            "{$shortName} calls confirm() without importing useConfirm (native confirm leak, D12)."
        );

        foreach ($callSites as [$call, $offset]) {
            $before = substr($src, 0, $offset);
            $tail = substr($before, max(0, strlen($before) - 120));

            self::assertMatchesRegularExpression(
                '/\b(\w+)\s*=\s*await\s*$/',
                $tail,
                "{$shortName}: the confirm() call site '{$call}...' must be an awaited consumption of the form `const ok = await confirm({...})` (D12/RELIABILITY-001: an unawaited confirm returns a Promise, which is always truthy)."
            );

            preg_match('/\b(\w+)\s*=\s*await\s*$/', $tail, $assigned);
            $resultVar = $assigned[1];
            self::assertMatchesRegularExpression(
                '/\bif\s*\(\s*!?\s*'.preg_quote($resultVar, '/').'\b/',
                substr($src, $offset),
                "{$shortName}: the awaited confirm() result '{$resultVar}' must gate the action with `if ({$resultVar})` (D12/RELIABILITY-001: an unchecked confirmation always proceeds)."
            );
        }
    }

    public function test_module_vues_ban_em_dash_character(): void
    {
        foreach (self::moduleVueSources() as $path => $src) {
            self::assertFalse(
                strpos($src, "\u{2014}") !== false,
                self::shortName($path).' must not contain the U+2014 em-dash character (use "N/D" or plain text).'
            );
        }
    }

    public function test_page_files_import_shared_professionals_constants(): void
    {
        foreach (self::PAGE_FILES as $file) {
            $src = self::readSource(self::projectRootPath().$file);
            self::assertImportsSharedConstants($src, basename($file));
        }
    }

    /**
     * T6 (RELIABILITY-003) — quote-style independent import rule: single or
     * double quotes and an optional '.js' module suffix are noise; the
     * contract is which module is imported (D1: one shared specialty map).
     */
    private static function assertImportsSharedConstants(string $src, string $shortName): void
    {
        self::assertMatchesRegularExpression(
            "/from\\s*['\"]\\.\\/professionals\\.constants(?:\\.js)?['\"]/",
            $src,
            "{$shortName} must import the shared vocabulary from './professionals.constants' (D1: one specialty map; quote style and an optional .js suffix are irrelevant)."
        );
    }

    public function test_professionals_constants_export_frozen_vocabulary(): void
    {
        $path = self::projectRootPath().self::MODULE_DIR.'/professionals.constants.js';
        self::assertConstantsExportVocabulary(self::readSource($path));
    }

    /**
     * T6 (RELIABILITY-003) — the constants contract tolerates either export
     * form (`export const NAME =` or `export { NAME }`) and either quote
     * style for the frozen labels; the exported names and the exact copy
     * remain pinned, including the key-to-label direction (no inversion).
     */
    private static function assertConstantsExportVocabulary(string $src): void
    {
        foreach (['SPECIALTY_LABELS', 'SPECIALTY_OPTIONS', 'CLINICAL_ROLES', 'ROLE_LABELS'] as $export) {
            self::assertMatchesRegularExpression(
                "/export\\s+const\\s+{$export}\\b|export\\s*\\{[^}]*\\b{$export}\\b[^}]*\\}/",
                $src,
                "professionals.constants.js must export {$export} via `export const {$export} =` or `export { {$export} }` (D1 shared vocabulary; RELIABILITY-003 accepts either form)."
            );
        }

        $frozenSpecialtyLabels = [
            'general' => 'Odontología General',
            'orthodontics' => 'Ortodoncia',
            'endodontics' => 'Endodoncia',
            'periodontics' => 'Periodoncia',
            'oral_surgery' => 'Cirugía Oral',
            'pediatric' => 'Odontopediatría',
            'prosthodontics' => 'Prótesis Dental',
            'cosmetic' => 'Odontología Estética',
        ];

        foreach ($frozenSpecialtyLabels as $key => $label) {
            self::assertMatchesRegularExpression(
                '/\b'.preg_quote($key, '/').'\s*:\s*[\x27\x22]'.preg_quote($label, '/').'[\x27\x22]/',
                $src,
                "SPECIALTY_LABELS must keep the frozen label '{$label}' for '{$key}' with either quote style (vocabulary normalization must not change copy, RELIABILITY-003)."
            );
        }
    }

    /**
     * T3 (D4) — hero band: data-professionals-hero hook, neutral canvas
     * surface, no mint/green wash anywhere in the page.
     */
    public function test_hero_band_hook_uses_canvas_surface_without_mint(): void
    {
        $src = self::readSource(self::projectRootPath().self::PAGE_FILES[0]);

        self::assertStringContainsString(
            'data-professionals-hero',
            $src,
            'ProfessionalsPage.vue must expose the data-professionals-hero hook (D4 hero band).'
        );
        self::assertStringContainsString(
            'var(--color-canvas)',
            $src,
            'ProfessionalsPage.vue hero must sit on var(--color-canvas) (D4: neutral surface, user direction: no mint tint).'
        );
        self::assertStringNotContainsString(
            'bg-accent-50',
            $src,
            'ProfessionalsPage.vue must not use bg-accent-50 (D4: neutral canvas, no mint surfaces).'
        );
        self::assertStringNotContainsString(
            'mint',
            $src,
            'ProfessionalsPage.vue must not introduce mint surfaces (D4: neutral canvas only).'
        );
    }

    /**
     * T3 (D4) — no gradients anywhere in the page.
     */
    public function test_page_bans_gradient_surfaces(): void
    {
        $src = self::readSource(self::projectRootPath().self::PAGE_FILES[0]);

        foreach (['bg-gradient', 'linear-gradient'] as $gradient) {
            self::assertStringNotContainsString(
                $gradient,
                $src,
                "ProfessionalsPage.vue must not use '{$gradient}' (D4: flat token surfaces only)."
            );
        }
    }

    /**
     * T3 (D3) — team KPI strip: exactly four data-stat-card occurrences with
     * the four team data-stat values, on the wrapping data-team-kpis block.
     */
    public function test_team_kpi_strip_has_exactly_four_stat_cards(): void
    {
        $src = self::readSource(self::projectRootPath().self::PAGE_FILES[0]);

        self::assertStringContainsString(
            'data-team-kpis',
            $src,
            'ProfessionalsPage.vue must expose the data-team-kpis wrapping block (D3 KPI strip).'
        );

        self::assertSame(
            4,
            substr_count($src, 'data-stat-card'),
            'ProfessionalsPage.vue must contain exactly four data-stat-card occurrences (D3: four team KPIs).'
        );

        foreach (['team-total', 'team-active', 'team-inactive', 'team-specialties'] as $stat) {
            self::assertStringContainsString(
                "data-stat-card=\"{$stat}\"",
                $src,
                "ProfessionalsPage.vue must anchor a data-stat-card with the value '{$stat}' (D3)."
            );
            self::assertStringContainsString(
                "data-stat=\"{$stat}\"",
                $src,
                "ProfessionalsPage.vue must anchor the KPI number with data-stat='{$stat}' (D3)."
            );
        }
    }

    /**
     * T3 (D2/D11) — single data source: the broken /api/users/search call is
     * gone and the role query is derived from CLINICAL_ROLES, never hardcoded.
     */
    public function test_search_endpoint_gone_and_role_query_derived_from_clinical_roles(): void
    {
        $src = self::readSource(self::projectRootPath().self::PAGE_FILES[0]);

        self::assertStringNotContainsString(
            '/api/users/search',
            $src,
            'ProfessionalsPage.vue must not call the dead /api/users/search endpoint (D2: client-side filter over the loaded collection).'
        );
        self::assertStringNotContainsString(
            'role=odontologo',
            $src,
            'ProfessionalsPage.vue must not hardcode a role=odontologo query string (D2: the team query lists every clinical role).'
        );
        self::assertMatchesRegularExpression(
            "/import\s*\{[^}]*CLINICAL_ROLES[^}]*\}\s*from\s*['\"]\.\/professionals\.constants['\"]/",
            $src,
            'ProfessionalsPage.vue must import CLINICAL_ROLES from ./professionals.constants (D1/D2).'
        );
        self::assertMatchesRegularExpression(
            '/users\?role=\$\{/',
            $src,
            'ProfessionalsPage.vue must build the role query by interpolation (D2: one collection load for the whole clinical team).'
        );
    }

    /**
     * T4 (D6 + RELIABILITY-004) — the token/var form of hand-built pill fills
     * must not slip past the guard: any bg-[color:var(--color-*-100)] fill is
     * a hand-built badge surface.
     */
    public function test_module_vues_ban_token_var_hand_built_badge_fills(): void
    {
        foreach (self::moduleVueSources() as $path => $src) {
            self::assertDoesNotMatchRegularExpression(
                '/bg-\[color:var\(--color-\w+-100\)\]/',
                $src,
                self::shortName($path).' must not build pill fills with bg-[color:var(--color-*-100)] (D6: <UiBadge> is the single pill system, RELIABILITY-004).'
            );
        }
    }

    /**
     * T4 (D12) — the dead "Ver" modal and its unreachable state are gone.
     */
    public function test_professionals_page_removes_dead_view_modal(): void
    {
        $src = self::readSource(self::projectRootPath().self::PAGE_FILES[0]);

        foreach (['viewProfessional', 'showViewProfessionalModal', 'viewingProfessional', 'Ver Profesional'] as $remnant) {
            self::assertStringNotContainsString(
                $remnant,
                $src,
                "ProfessionalsPage.vue must not keep the dead 'Ver' modal remnant '{$remnant}' (D12: unreachable surface removed)."
            );
        }
    }

    /**
     * T4 (D12 + product decision 4) — the role selector is fed from the shared
     * vocabulary; no hardcoded role option lists in the template.
     */
    public function test_professionals_page_role_selector_fed_from_shared_vocabulary(): void
    {
        $src = self::readSource(self::projectRootPath().self::PAGE_FILES[0]);

        self::assertMatchesRegularExpression(
            "/import\s*\{[^}]*ROLE_LABELS[^}]*\}\s*from\s*['\"]\.\/professionals\.constants['\"]/",
            $src,
            'ProfessionalsPage.vue must import ROLE_LABELS from ./professionals.constants (D12: role selector vocabulary).'
        );
        self::assertStringContainsString(
            'CLINICAL_ROLES.map(',
            $src,
            'ProfessionalsPage.vue must build the role options from CLINICAL_ROLES (D12: no hardcoded role option list).'
        );
        self::assertGreaterThanOrEqual(
            2,
            substr_count($src, ':options="roleOptions"'),
            'Create and edit modals must feed their role select with :options="roleOptions" (D12 product decision 4).'
        );

        foreach (["value: 'odontologo'", "value: 'implantologo'", "value: 'tecnico_dental'", "value: 'asistente'"] as $hardcoded) {
            self::assertStringNotContainsString(
                $hardcoded,
                $src,
                "ProfessionalsPage.vue must not hardcode the role option {$hardcoded} (D12: CLINICAL_ROLES/ROLE_LABELS feed the selector)."
            );
        }
    }

    /**
     * T4 (D7) — one empty-state pattern: UiSectionEmpty imported and
     * used; the bare inline-SVG empty state is gone.
     */
    public function test_professionals_page_uses_dashboard_section_empty(): void
    {
        $src = self::readSource(self::projectRootPath().self::PAGE_FILES[0]);

        self::assertMatchesRegularExpression(
            "/from\\s*['\"]\\.\\.\\/\\.\\.\\/components\\/ui\\/UiSectionEmpty\\.vue['\"]/",
            $src,
            'ProfessionalsPage.vue must import UiSectionEmpty (D7: one empty-state pattern, reuse by import only; quote style is irrelevant).'
        );
        self::assertStringContainsString(
            '<UiSectionEmpty',
            $src,
            'ProfessionalsPage.vue must render UiSectionEmpty for its empty states (D7).'
        );
        self::assertStringNotContainsString(
            'M16 7a4 4 0 11-8 0',
            $src,
            'ProfessionalsPage.vue must drop the bare inline-SVG empty state (D7: UiSectionEmpty owns the dental motif).'
        );
    }

    /**
     * T4 (D5) — list hierarchy data hooks: block, filter bar and rows.
     */
    public function test_professionals_page_exposes_team_list_data_hooks(): void
    {
        $src = self::readSource(self::projectRootPath().self::PAGE_FILES[0]);

        foreach (['data-team-list', 'data-team-filters', 'data-team-row'] as $hook) {
            self::assertStringContainsString(
                $hook,
                $src,
                "ProfessionalsPage.vue must expose the {$hook} hook (D5 list hierarchy)."
            );
        }
    }

    /**
     * T5 (D10) — detail page visual parity: flat token surfaces only, no
     * gradients anywhere in the page.
     */
    public function test_detail_page_bans_gradient_surfaces(): void
    {
        $src = self::readSource(self::projectRootPath().self::PAGE_FILES[1]);

        foreach (['bg-gradient', 'linear-gradient'] as $gradient) {
            self::assertStringNotContainsString(
                $gradient,
                $src,
                "ProfessionalDetailPage.vue must not use '{$gradient}' (D10: flat token surfaces only, the accent-gradient avatar must go)."
            );
        }
    }

    /**
     * T5 (product decision 2) — commission_rate is finance-sensitive and must
     * never be rendered on the detail page.
     */
    public function test_detail_page_never_renders_commission_rate(): void
    {
        $src = self::readSource(self::projectRootPath().self::PAGE_FILES[1]);

        self::assertStringNotContainsString(
            'commission_rate',
            $src,
            'ProfessionalDetailPage.vue must not render commission_rate (product decision 2: the commission figure stays out of the UI, the guard pins it).'
        );
    }

    /**
     * T5 (D6/RELIABILITY-004) — rol, especialidad and estado pills come from
     * <UiBadge>; no hand-built fills in plain or token/var form.
     */
    public function test_detail_page_bans_hand_built_badge_fills(): void
    {
        $src = self::readSource(self::projectRootPath().self::PAGE_FILES[1]);

        foreach (self::BANNED_HAND_BUILT_BADGES as $badgeClass) {
            self::assertStringNotContainsString(
                $badgeClass,
                $src,
                "ProfessionalDetailPage.vue must not build pills by hand with '{$badgeClass}' (D6: <UiBadge> is the single pill system)."
            );
        }

        self::assertDoesNotMatchRegularExpression(
            '/bg-\[color:var\(--color-\w+-100\)\]/',
            $src,
            'ProfessionalDetailPage.vue must not build pill fills with bg-[color:var(--color-*-100)] (D6: <UiBadge>, RELIABILITY-004).'
        );
    }

    /**
     * T5 (D7/D10) — the audit empty state uses the shared UiSectionEmpty
     * pattern; the hand-built gradient-box empty state is gone.
     */
    public function test_detail_page_uses_dashboard_section_empty(): void
    {
        $src = self::readSource(self::projectRootPath().self::PAGE_FILES[1]);

        self::assertMatchesRegularExpression(
            "/from\\s*['\"]\\.\\.\\/\\.\\.\\/components\\/ui\\/UiSectionEmpty\\.vue['\"]/",
            $src,
            'ProfessionalDetailPage.vue must import UiSectionEmpty (D7/D10: one empty-state pattern, reuse by import only; quote style is irrelevant).'
        );
        self::assertStringContainsString(
            '<UiSectionEmpty',
            $src,
            'ProfessionalDetailPage.vue must render UiSectionEmpty for the empty audit history (D10).'
        );
    }

    /**
     * T5 (D1) — the shared vocabulary feeds the facts block: ROLE_LABELS and
     * SPECIALTY_LABELS imported from ./professionals.constants, no hardcoded
     * label maps left behind.
     */
    public function test_detail_page_imports_shared_professionals_constants(): void
    {
        $src = self::readSource(self::projectRootPath().self::PAGE_FILES[1]);

        foreach (['ROLE_LABELS', 'SPECIALTY_LABELS'] as $export) {
            self::assertMatchesRegularExpression(
                "/import\\s*\\{[^}]*{$export}[^}]*\\}\\s*from\\s*['\"]\\.\\/professionals\\.constants['\"]/",
                $src,
                "ProfessionalDetailPage.vue must import {$export} from ./professionals.constants (D1: one shared vocabulary)."
            );
        }

        foreach (["odontologo: 'Odontólogo'", "general: 'Odontología General'"] as $hardcoded) {
            self::assertStringNotContainsString(
                $hardcoded,
                $src,
                "ProfessionalDetailPage.vue must not hardcode {$hardcoded} (D1: the label maps live in professionals.constants)."
            );
        }
    }

    /**
     * T5 (D10) — the facts block data hook anchors the professional data rows.
     */
    public function test_detail_page_exposes_facts_block_hook(): void
    {
        $src = self::readSource(self::projectRootPath().self::PAGE_FILES[1]);

        self::assertStringContainsString(
            'data-professional-facts',
            $src,
            'ProfessionalDetailPage.vue must expose the data-professional-facts hook (D10 facts block).'
        );
    }

    /**
     * T5 (D10) — hero parity with the list page: canvas surface tokens and the
     * Breadcrumbs primitive own list > detail wayfinding; the redundant back
     * button is gone.
     */
    public function test_detail_page_hero_matches_list_surface_and_drops_back_button(): void
    {
        $src = self::readSource(self::projectRootPath().self::PAGE_FILES[1]);

        self::assertMatchesRegularExpression(
            "/from\\s*['\"]\\.\\.\\/\\.\\.\\/components\\/ui\\/Breadcrumbs\\.vue['\"]/",
            $src,
            'ProfessionalDetailPage.vue must import the Breadcrumbs primitive (D10: list > detail wayfinding; quote style is irrelevant).'
        );
        foreach (['var(--color-canvas)', 'var(--color-hairline)', 'var(--radius-panel)', 'var(--elevation-2)'] as $token) {
            self::assertStringContainsString(
                $token,
                $src,
                "ProfessionalDetailPage.vue hero must use the {$token} surface token (D10: same hero surface as the list page)."
            );
        }
        foreach (['Volver', 'goBack'] as $backRemnant) {
            self::assertStringNotContainsString(
                $backRemnant,
                $src,
                "ProfessionalDetailPage.vue must not keep the redundant '{$backRemnant}' back action (D10: the breadcrumb owns the return to the list)."
            );
        }
    }

    /**
     * T5 (D10 style restrictions) — no U+2014 in the redesigned detail page.
     */
    public function test_detail_page_bans_em_dash_character(): void
    {
        $src = self::readSource(self::projectRootPath().self::PAGE_FILES[1]);

        self::assertFalse(
            strpos($src, "\u{2014}") !== false,
            'ProfessionalDetailPage.vue must not contain the U+2014 em-dash character (use "N/D" or plain text).'
        );
    }

    /**
     * T4 (RELIABILITY-005) — required fields are enforced in the submit path
     * (forwarded `required` lands on the wrapper div and native validation is
     * silently dropped) and surface inline validation messages.
     */
    public function test_professionals_page_enforces_required_fields_in_submit_path(): void
    {
        $src = self::readSource(self::projectRootPath().self::PAGE_FILES[0]);

        self::assertStringContainsString(
            'validateProfessionalForm',
            $src,
            'ProfessionalsPage.vue must validate required fields in the submit path (RELIABILITY-005: forwarded `required` is silently dropped).'
        );
        self::assertGreaterThanOrEqual(
            4,
            substr_count($src, ':error="createErrors') + substr_count($src, ':error="editErrors'),
            'Create and edit modals must surface inline validation messages through the primitives (RELIABILITY-005).'
        );
    }
}
