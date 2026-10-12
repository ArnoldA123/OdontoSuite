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
 *   - no native confirm() — confirmations come from useConfirm()
 *   - no U+2014 em-dash in module sources
 *   - both page files import the shared vocabulary from
 *     ./professionals.constants (kills the duplicated specialty map)
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
        $files = glob(self::projectRootPath() . self::MODULE_DIR . '/*.vue');
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
                    self::shortName($path) . " must not use the legacy alias class '{$alias}' (T1: token utilities only)."
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
                    self::shortName($path) . " must not build pills by hand with '{$badgeClass}' (D6: <UiBadge> is the single pill system)."
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
                self::shortName($path) . ' must not render raw <select> elements (D8: <UiSelect> primitive).'
            );
        }
    }

    public function test_module_vues_ban_native_confirm(): void
    {
        foreach (self::moduleVueSources() as $path => $src) {
            self::assertStringNotContainsString(
                'window.confirm',
                $src,
                self::shortName($path) . ' must not call window.confirm (D12: useConfirm composable).'
            );

            $callsConfirm = preg_match('/(?<![\w.$])confirm\s*\(/', $src) === 1;
            if ($callsConfirm) {
                self::assertStringContainsString(
                    'useConfirm',
                    $src,
                    self::shortName($path) . ' calls confirm() without importing useConfirm (native confirm leak, D12).'
                );
            }
        }
    }

    public function test_module_vues_ban_em_dash_character(): void
    {
        foreach (self::moduleVueSources() as $path => $src) {
            self::assertFalse(
                strpos($src, "\u{2014}") !== false,
                self::shortName($path) . ' must not contain the U+2014 em-dash character (use "N/D" or plain text).'
            );
        }
    }

    public function test_page_files_import_shared_professionals_constants(): void
    {
        foreach (self::PAGE_FILES as $file) {
            $src = self::readSource(self::projectRootPath() . $file);
            self::assertStringContainsString(
                "from './professionals.constants'",
                $src,
                basename($file) . ' must import the shared vocabulary from ./professionals.constants (D1: one specialty map).'
            );
        }
    }

    public function test_professionals_constants_export_frozen_vocabulary(): void
    {
        $path = self::projectRootPath() . self::MODULE_DIR . '/professionals.constants.js';
        $src = self::readSource($path);

        foreach (['SPECIALTY_LABELS', 'SPECIALTY_OPTIONS', 'CLINICAL_ROLES', 'ROLE_LABELS'] as $export) {
            self::assertStringContainsString(
                "export const {$export}",
                $src,
                "professionals.constants.js must export {$export} (D1 shared vocabulary)."
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
            self::assertStringContainsString(
                "{$key}: '{$label}'",
                $src,
                "SPECIALTY_LABELS must keep the frozen label '{$label}' for '{$key}' (vocabulary normalization must not change copy)."
            );
        }
    }

    /**
     * T3 (D4) — hero band: data-professionals-hero hook, neutral canvas
     * surface, no mint/green wash anywhere in the page.
     */
    public function test_hero_band_hook_uses_canvas_surface_without_mint(): void
    {
        $src = self::readSource(self::projectRootPath() . self::PAGE_FILES[0]);

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
        $src = self::readSource(self::projectRootPath() . self::PAGE_FILES[0]);

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
        $src = self::readSource(self::projectRootPath() . self::PAGE_FILES[0]);

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
        $src = self::readSource(self::projectRootPath() . self::PAGE_FILES[0]);

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
                self::shortName($path) . ' must not build pill fills with bg-[color:var(--color-*-100)] (D6: <UiBadge> is the single pill system, RELIABILITY-004).'
            );
        }
    }

    /**
     * T4 (D12) — the dead "Ver" modal and its unreachable state are gone.
     */
    public function test_professionals_page_removes_dead_view_modal(): void
    {
        $src = self::readSource(self::projectRootPath() . self::PAGE_FILES[0]);

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
        $src = self::readSource(self::projectRootPath() . self::PAGE_FILES[0]);

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
     * T4 (D7) — one empty-state pattern: DashboardSectionEmpty imported and
     * used; the bare inline-SVG empty state is gone.
     */
    public function test_professionals_page_uses_dashboard_section_empty(): void
    {
        $src = self::readSource(self::projectRootPath() . self::PAGE_FILES[0]);

        self::assertStringContainsString(
            "from '../dashboard/DashboardSectionEmpty.vue'",
            $src,
            'ProfessionalsPage.vue must import DashboardSectionEmpty (D7: one empty-state pattern, reuse by import only).'
        );
        self::assertStringContainsString(
            '<DashboardSectionEmpty',
            $src,
            'ProfessionalsPage.vue must render DashboardSectionEmpty for its empty states (D7).'
        );
        self::assertStringNotContainsString(
            'M16 7a4 4 0 11-8 0',
            $src,
            'ProfessionalsPage.vue must drop the bare inline-SVG empty state (D7: DashboardSectionEmpty owns the dental motif).'
        );
    }

    /**
     * T4 (D5) — list hierarchy data hooks: block, filter bar and rows.
     */
    public function test_professionals_page_exposes_team_list_data_hooks(): void
    {
        $src = self::readSource(self::projectRootPath() . self::PAGE_FILES[0]);

        foreach (['data-team-list', 'data-team-filters', 'data-team-row'] as $hook) {
            self::assertStringContainsString(
                $hook,
                $src,
                "ProfessionalsPage.vue must expose the {$hook} hook (D5 list hierarchy)."
            );
        }
    }

    /**
     * T4 (RELIABILITY-005) — required fields are enforced in the submit path
     * (forwarded `required` lands on the wrapper div and native validation is
     * silently dropped) and surface inline validation messages.
     */
    public function test_professionals_page_enforces_required_fields_in_submit_path(): void
    {
        $src = self::readSource(self::projectRootPath() . self::PAGE_FILES[0]);

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
