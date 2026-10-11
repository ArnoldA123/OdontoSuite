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
 *     bg-error-100) — pills come from <UiBadge>
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
}
