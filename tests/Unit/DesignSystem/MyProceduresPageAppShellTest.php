<?php

namespace Tests\Unit\DesignSystem;

/**
 * MIS-* tokenization contract for the my-procedures favourites + catalog
 * page (`/my-procedures`). Extends ModuleAppShellTestCase (DLR-R-001/002/004/021)
 * and adds the per-category MIS-* rules. Per MIS-013 the `<script setup>`
 * block is preserved except for the additive `formatCurrency` import.
 *
 * Implementation note: regex delimiters are `#` (NOT `/`) because the path
 * patterns contain forward slashes.
 */
class MyProceduresPageAppShellTest extends ModuleAppShellTestCase
{
    private const PAGE_PATH = '/resources/js/modules/my-procedures/MyProceduresPage.vue';

    /** @return array<int, string> */
    protected static function polishedFiles(): array
    {
        return [dirname(__DIR__, 3) . self::PAGE_PATH];
    }

    private static function readSource(string $path): ?string
    {
        $src = file_get_contents($path);
        return $src === false ? null : $src;
    }

    /** MIS-001 (DLR-R-002) — no `border-theme` / `divide-theme` literals remain. */
    public function test_no_border_theme_literal(): void
    {
        $path = dirname(__DIR__, 3) . self::PAGE_PATH;
        $src = self::readSource($path);
        $this->assertNotNull($src, sprintf('%s must be readable.', $path));

        $borderCount = preg_match_all('#(?<![\w-])border-theme(?![\w-])#', $src);
        $this->assertSame(0, $borderCount, sprintf('%s MUST NOT contain `border-theme` (MIS-001).', $path));

        $divideCount = preg_match_all('#(?<![\w-])divide-theme(?![\w-])#', $src);
        $this->assertSame(0, $divideCount, sprintf('%s MUST NOT contain `divide-theme` (MIS-001).', $path));
    }

    /** MIS-002 — search field consumes `<UiInput v-model="search">`; raw `<input>` is gone. */
    public function test_search_uses_ui_input(): void
    {
        $path = dirname(__DIR__, 3) . self::PAGE_PATH;
        $src = self::readSource($path);
        $this->assertNotNull($src, sprintf('%s must be readable.', $path));

        $this->assertMatchesRegularExpression(
            '#<UiInput\b[^>]*v-model\s*=\s*["\']search["\']#s',
            $src,
            sprintf('%s MUST consume `<UiInput v-model="search">` (MIS-002).', $path)
        );

        $this->assertDoesNotMatchRegularExpression(
            '#<input\b[^>]*focus:ring-primary-500#s',
            $src,
            sprintf('%s MUST NOT keep a raw `<input>` with focus-ring alias (MIS-002).', $path)
        );

        $this->assertMatchesRegularExpression(
            '#<div\s+class="[^"]*\brelative\b[^"]*"[^>]*>\s*<UiInput\b#s',
            $src,
            sprintf('%s MUST preserve the `<div class="relative ...">` wrapper around `<UiInput>` (MIS-002).', $path)
        );
    }

    /** MIS-003 — both PEN literals consume `formatCurrency(...)`; no local `Intl.NumberFormat`. */
    public function test_format_currency_used_for_pen_values(): void
    {
        $path = dirname(__DIR__, 3) . self::PAGE_PATH;
        $src = self::readSource($path);
        $this->assertNotNull($src, sprintf('%s must be readable.', $path));

        $formatCurrencyCalls = preg_match_all('#\bformatCurrency\s*\(#', $src);
        $this->assertGreaterThanOrEqual(
            2,
            $formatCurrencyCalls,
            sprintf('%s MUST call `formatCurrency(...)` at least twice (MIS-003 / PAGOS-MNY-002).', $path)
        );

        $this->assertDoesNotMatchRegularExpression(
            '#S/\s*\{\{\s*Number\([^}]+\)\.toFixed\(2\)#s',
            $src,
            sprintf('%s MUST NOT keep the legacy `S/ {{ Number(...).toFixed(2) }}` literal (MIS-003).', $path)
        );

        $this->assertSame(
            0,
            preg_match(
                '#(?<![\w-])Intl\.NumberFormat\s*\(\s*[\'"]es-PE[\'"]\s*,\s*\{\s*[^{}]*?currency\s*:\s*[\'"]PEN[\'"]#',
                $src
            ),
            sprintf(
                '%s MUST NOT redeclare `Intl.NumberFormat(\'es-PE\', { currency: \'PEN\' })` (MIS-003 / PAGOS-MNY-002).',
                $path
            )
        );
    }

    /** MIS-004 — at least 6 `tabular-nums` references on numeric spans. */
    public function test_tabular_nums_on_numeric_cells(): void
    {
        $path = dirname(__DIR__, 3) . self::PAGE_PATH;
        $src = self::readSource($path);
        $this->assertNotNull($src, sprintf('%s must be readable.', $path));

        $tabularNumsCount = preg_match_all('#\btabular-nums\b#', $src);
        $this->assertGreaterThanOrEqual(
            6,
            $tabularNumsCount,
            sprintf('%s MUST carry `tabular-nums` at least 6 times (MIS-004). Found %d.', $path, $tabularNumsCount)
        );
    }

    /** MIS-005 — `LoadingSpinner` import + tags renamed to `UiLoadingSpinner`. */
    public function test_loading_spinner_import_is_ui_prefixed(): void
    {
        $path = dirname(__DIR__, 3) . self::PAGE_PATH;
        $src = self::readSource($path);
        $this->assertNotNull($src, sprintf('%s must be readable.', $path));

        $this->assertMatchesRegularExpression(
            '#import\s+UiLoadingSpinner\b#',
            $src,
            sprintf('%s MUST import the spinner as `UiLoadingSpinner` (MIS-005).', $path)
        );

        $this->assertDoesNotMatchRegularExpression(
            '#import\s+LoadingSpinner\b#',
            $src,
            sprintf('%s MUST NOT keep the bare `import LoadingSpinner` (MIS-005).', $path)
        );

        $this->assertDoesNotMatchRegularExpression(
            '#<LoadingSpinner\b#',
            $src,
            sprintf('%s MUST NOT use the bare `<LoadingSpinner>` tag (MIS-005).', $path)
        );
    }

    /** MIS-006 — `disabled:opacity-30` → `disabled:opacity-40` for iOS parity with `<UiButton>`. */
    public function test_disabled_opacity_uses_ios_parity(): void
    {
        $path = dirname(__DIR__, 3) . self::PAGE_PATH;
        $src = self::readSource($path);
        $this->assertNotNull($src, sprintf('%s must be readable.', $path));

        $fortyCount = preg_match_all('#\bdisabled:opacity-40\b#', $src);
        $this->assertGreaterThanOrEqual(
            2,
            $fortyCount,
            sprintf('%s MUST apply `disabled:opacity-40` at least twice (MIS-006). Found %d.', $path, $fortyCount)
        );

        $thirtyCount = preg_match_all('#\bdisabled:opacity-30\b#', $src);
        $this->assertSame(0, $thirtyCount, sprintf('%s MUST NOT keep `disabled:opacity-30` (MIS-006).', $path));
    }

    /** MIS-007 — code badges drop `font-mono`; system sans + `tabular-nums`. */
    public function test_code_badges_use_system_sans_with_tabular_nums(): void
    {
        $path = dirname(__DIR__, 3) . self::PAGE_PATH;
        $src = self::readSource($path);
        $this->assertNotNull($src, sprintf('%s must be readable.', $path));

        $monoCount = preg_match_all('#(?<![\w-])font-mono(?![\w-])#', $src);
        $this->assertSame(
            0,
            $monoCount,
            sprintf('%s MUST NOT contain `font-mono` (MIS-007). Replace with system sans + `tabular-nums`.', $path)
        );

        $this->assertMatchesRegularExpression(
            '#\btabular-nums\b#',
            $src,
            sprintf('%s MUST apply `tabular-nums` on both code badges (MIS-007).', $path)
        );
    }

    /** MIS-008 — both empty states consume `<UiEmptyState>`. */
    public function test_empty_states_consume_ui_empty_state(): void
    {
        $path = dirname(__DIR__, 3) . self::PAGE_PATH;
        $src = self::readSource($path);
        $this->assertNotNull($src, sprintf('%s must be readable.', $path));

        $emptyStateCount = preg_match_all('#<UiEmptyState\b#', $src);
        $this->assertGreaterThanOrEqual(
            2,
            $emptyStateCount,
            sprintf('%s MUST consume `<UiEmptyState>` at least twice (MIS-008). Found %d.', $path, $emptyStateCount)
        );

        $this->assertDoesNotMatchRegularExpression(
            '#border-2\s+border-dashed\s+border-theme#s',
            $src,
            sprintf('%s MUST NOT keep `border-2 border-dashed border-theme` (MIS-008).', $path)
        );
    }

    /** MIS-009 — every raw icon `<button>` consumes `var(--focus-ring-default)`; no legacy focus aliases. */
    public function test_raw_icon_buttons_consume_focus_ring_token(): void
    {
        $path = dirname(__DIR__, 3) . self::PAGE_PATH;
        $src = self::readSource($path);
        $this->assertNotNull($src, sprintf('%s must be readable.', $path));

        $focusRingCount = preg_match_all('#var\(--focus-ring-default\)#', $src);
        $this->assertGreaterThanOrEqual(
            3,
            $focusRingCount,
            sprintf(
                '%s MUST consume `var(--focus-ring-default)` at least 3 times (MIS-009 / DLR-R-004). Found %d.',
                $path,
                $focusRingCount
            )
        );

        $this->assertDoesNotMatchRegularExpression(
            '#(?<![\w-])focus:ring-primary-500(?![\w-])#',
            $src,
            sprintf('%s MUST NOT contain legacy `focus:ring-primary-500` (MIS-009 / DLR-R-004).', $path)
        );
    }

    /**
     * MIS-010 — every status ramp uses the proven system ramp:
     * rank pill `bg-systemBlue-50 text-systemBlue-700`; yellow accent
     * `text-systemYellow-500`; remove-favourite `text-systemRed-500 hover:text-systemRed-700`.
     */
    public function test_status_ramps_use_tokenized_system_colors(): void
    {
        $path = dirname(__DIR__, 3) . self::PAGE_PATH;
        $src = self::readSource($path);
        $this->assertNotNull($src, sprintf('%s must be readable.', $path));

        // Blue rank pill.
        $this->assertMatchesRegularExpression('#\bbg-systemBlue-50\b#', $src, sprintf('%s MUST use `bg-systemBlue-50` (MIS-010).', $path));
        $this->assertMatchesRegularExpression('#\btext-systemBlue-700\b#', $src, sprintf('%s MUST use `text-systemBlue-700` (MIS-010).', $path));
        $this->assertDoesNotMatchRegularExpression('#(?<![\w-])bg-primary-50(?![\w-])#', $src, sprintf('%s MUST NOT keep `bg-primary-50` (MIS-010).', $path));
        $this->assertDoesNotMatchRegularExpression('#(?<![\w-])text-primary-700(?![\w-])#', $src, sprintf('%s MUST NOT keep `text-primary-700` (MIS-010).', $path));

        // Yellow accent (star + label).
        $this->assertMatchesRegularExpression('#\btext-systemYellow-500\b#', $src, sprintf('%s MUST use `text-systemYellow-500` (MIS-010).', $path));
        $this->assertDoesNotMatchRegularExpression('#(?<![\w-])text-yellow-500(?![\w-])#', $src, sprintf('%s MUST NOT keep `text-yellow-500` (MIS-010).', $path));

        // Red Quitar icon.
        $this->assertMatchesRegularExpression('#\btext-systemRed-500\b#', $src, sprintf('%s MUST use `text-systemRed-500` (MIS-010).', $path));
        $this->assertMatchesRegularExpression('#\bhover:text-systemRed-700\b#', $src, sprintf('%s MUST use `hover:text-systemRed-700` (MIS-010).', $path));
        $this->assertDoesNotMatchRegularExpression('#(?<![\w-])text-red-500(?![\w-])#', $src, sprintf('%s MUST NOT keep `text-red-500` (MIS-010).', $path));
        $this->assertDoesNotMatchRegularExpression('#(?<![\w-])hover:text-red-700(?![\w-])#', $src, sprintf('%s MUST NOT keep `hover:text-red-700` (MIS-010).', $path));
    }

    /** MIS-011 — `hover:bg-theme-surface` → `hover:bg-canvas`; semantic alias `bg-theme-surface-elevated` retained. */
    public function test_hover_surface_uses_canvas_token(): void
    {
        $path = dirname(__DIR__, 3) . self::PAGE_PATH;
        $src = self::readSource($path);
        $this->assertNotNull($src, sprintf('%s must be readable.', $path));

        $this->assertMatchesRegularExpression('#\bhover:bg-canvas\b#', $src, sprintf('%s MUST use `hover:bg-canvas` (MIS-011).', $path));
        $this->assertDoesNotMatchRegularExpression('#\bhover:bg-theme-surface\b#', $src, sprintf('%s MUST NOT keep `hover:bg-theme-surface` (MIS-011).', $path));
        $this->assertMatchesRegularExpression(
            '#(?<![\w-])bg-theme-surface-elevated(?![\w-])#',
            $src,
            sprintf('%s MUST retain the semantic alias `bg-theme-surface-elevated` (MIS-011).', $path)
        );
    }

    /** MIS-012 — every `rounded-lg` replaced with contextual radius token; bare `rounded-lg` count == 0. */
    public function test_radius_uses_contextual_tokens(): void
    {
        $path = dirname(__DIR__, 3) . self::PAGE_PATH;
        $src = self::readSource($path);
        $this->assertNotNull($src, sprintf('%s must be readable.', $path));

        $controlCount = preg_match_all('#var\(--radius-control\)#', $src);
        $iosCount = preg_match_all('#var\(--radius-ios\)#', $src);
        $cardLgCount = preg_match_all('#var\(--radius-card-lg\)#', $src);
        $total = $controlCount + $iosCount + $cardLgCount;
        $this->assertGreaterThanOrEqual(
            4,
            $total,
            sprintf(
                '%s MUST consume contextual radius tokens at least 4 times (MIS-012). Found control=%d, ios=%d, card-lg=%d.',
                $path,
                $controlCount,
                $iosCount,
                $cardLgCount
            )
        );

        $bareRoundedLgCount = preg_match_all('#(?<![\w-])rounded-lg(?![\w-])#', $src);
        $this->assertSame(
            0,
            $bareRoundedLgCount,
            sprintf('%s MUST NOT keep the bare `rounded-lg` literal (MIS-012). Found %d.', $path, $bareRoundedLgCount)
        );
    }

    /**
     * MIS-013 — `<script setup>` block preserves the composable contract
     * (`useProcedureFavorites`, `useToast`, `useRouter().push('/dashboard')`).
     * The additive `formatCurrency` import is the one allowed script-block edit.
     */
    public function test_script_setup_unchanged(): void
    {
        $path = dirname(__DIR__, 3) . self::PAGE_PATH;
        $src = self::readSource($path);
        $this->assertNotNull($src, sprintf('%s must be readable.', $path));

        $this->assertMatchesRegularExpression(
            '#import\s+\{[^}]*useProcedureFavorites[^}]*\}\s+from\s+[\'"][^\'"]*composables/useProcedureFavorites[\'"]#s',
            $src,
            sprintf('%s MUST keep the `useProcedureFavorites` composable import (MIS-013).', $path)
        );

        $this->assertMatchesRegularExpression(
            '#import\s+\{[^}]*useToast[^}]*\}\s+from\s+[\'"][^\'"]*composables/useToast[\'"]#s',
            $src,
            sprintf('%s MUST keep the `useToast` composable import (MIS-013).', $path)
        );

        $this->assertMatchesRegularExpression(
            '#import\s+\{\s*useRouter\s*\}\s+from\s+[\'"]vue-router[\'"]#s',
            $src,
            sprintf('%s MUST keep the `useRouter` import from `vue-router` (MIS-013).', $path)
        );

        $requiredDestructured = ['favorites', 'forMe', 'loading', 'getFavorites', 'getForMe', 'addFavorite', 'removeFavorite', 'reorderFavorites'];
        foreach ($requiredDestructured as $binding) {
            $this->assertMatchesRegularExpression(
                '#\b' . preg_quote($binding, '#') . '\b#',
                $src,
                sprintf('%s MUST keep the `%s` binding from `useProcedureFavorites` (MIS-013).', $path, $binding)
            );
        }

        $this->assertMatchesRegularExpression(
            '#router\.push\(\s*[\'"]/dashboard[\'"]\s*\)#',
            $src,
            sprintf('%s MUST keep the `router.push(\'/dashboard\')` redirect in `goBack` (MIS-013).', $path)
        );

        $this->assertMatchesRegularExpression(
            '#onMounted\(\s*load\s*\)#',
            $src,
            sprintf('%s MUST keep the `onMounted(load)` hook (MIS-013).', $path)
        );

        $this->assertMatchesRegularExpression(
            '#per_page:\s*100#',
            $src,
            sprintf('%s MUST keep the `per_page: 100` parameter on `getForMe` (MIS-013).', $path)
        );

        // MIS-003 single additive edit.
        $this->assertMatchesRegularExpression(
            '#import\s+\{[^}]*\bformatCurrency\b[^}]*\}\s+from\s+[\'"][^\'"]*composables/useFormatters[\'"]#s',
            $src,
            sprintf('%s MUST add `formatCurrency` from `useFormatters` (MIS-003 additive script edit).', $path)
        );
    }
}
