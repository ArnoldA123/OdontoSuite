<?php

namespace Tests\Unit\DesignSystem;

/**
 * PR-ambientes-01 + PR-ambientes-02 — EnvironmentsAppShellTest.
 *
 * PR-ambientes-01 asserts the AMB-01-002..008 rules for the `environments`
 * module list page (`EnvironmentsPage.vue`).
 *
 * PR-ambientes-02 EXTENDS `polishedFiles()` to also cover the detail page
 * (`EnvironmentDetailPage.vue`) and adds 6 PR-02-only rule assertions:
 * AMB-02-003 (tabs → `<UiTabs>`), AMB-02-004 (header avatar flat systemBlue,
 * no gradients), AMB-02-005 (audit empty state → `<UiEmptyState>`),
 * AMB-02-006 (audit log card → `<UiCard variant="glass">` + change-diff
 * callout hairline), AMB-02-007 (modal chrome — covered in sibling
 * `EnvironmentsModalChromeTest`, not here), AMB-02-008 (change-diff
 * `text-red-500`/`text-green-500` → `text-systemRed-600`/`text-systemGreen-600`).
 *
 * Also asserted in PR-02: the `<script>` helper `getAuditActionVariant`
 * MUST return `'neutral'` for the default case (NOT the illegal `<UiBadge>`
 * variant `'secondary'`) — DLR-AMB-005 EXCEPTION #2.
 *
 * The base class `ModuleAppShellTestCase` enforces the 5 inherited DLR-R
 * rules (canvas token, no `border-theme`, focus ring, no `<style scoped>`,
 * no legacy focus-ring aliases) via `polishedFileProvider()`. This subclass
 * adds 6 PR-ambientes-01-only + 6 PR-ambientes-02-only rule assertions.
 *
 * Implementation note: regex delimiters are `#` (NOT `/`) because the path
 * constants contain forward slashes; using `/` as delimiter would force
 * every `/` to be escaped `\/`, which is brittle and error-prone.
 */
class EnvironmentsAppShellTest extends ModuleAppShellTestCase
{
    /** List page path constant — single source of truth for the data provider. */
    private const LIST_PAGE_PATH = '/resources/js/modules/environments/EnvironmentsPage.vue';

    /** Detail page path constant — PR-ambientes-02 extension. */
    private const DETAIL_PAGE_PATH = '/resources/js/modules/environments/EnvironmentDetailPage.vue';

    /** @return array<int, string> */
    protected static function polishedFiles(): array
    {
        return [
            dirname(__DIR__, 3) . self::LIST_PAGE_PATH,
            // PR-ambientes-02 — detail page added to the polished file set so
            // the 5 inherited DLR-R rules (canvas token, no border-theme,
            // focus ring, no <style scoped>, no legacy focus-ring aliases)
            // also fire against the detail page.
            dirname(__DIR__, 3) . self::DETAIL_PAGE_PATH,
        ];
    }

    /**
     * AMB-01-002 — the status filter (`statusFilter` reactive ref) MUST
     * consume the canonical `<UiSelect>` primitive (NOT a raw `<select>`
     * with legacy focus chrome). All 4 options (Todos / Activos / Inactivos
     * / Mantenimiento) MUST keep their `value` attributes byte-for-byte.
     *
     * POSITIVE rule: `<UiSelect` reference present in the file.
     *
     * NEGATIVE rule: zero raw `<select ... class="...border-theme...">` controls.
     */
    public function test_status_filter_uses_ui_select(): void
    {
        $path = dirname(__DIR__, 3) . self::LIST_PAGE_PATH;
        $src = self::readSource($path);
        $this->assertNotNull($src, sprintf('%s must be readable.', $path));

        // POSITIVE: `<UiSelect` reference present (the status filter was
        // bound to a raw `<select>` with `border-theme` legacy chrome).
        $this->assertTrue(
            (bool) preg_match('#<UiSelect\b#', $src)
                || (bool) preg_match(
                    '#import\s+\w*[Ss]elect\w*\s+from\s+[\'"][^\'"]*components/ui/Select\.vue[\'"]#',
                    $src
                ),
            sprintf(
                '%s MUST consume <UiSelect> for the status filter (AMB-01-002). '
                . 'Raw `<select class="border-theme">` controls are deprecated.',
                $path
            )
        );

        // NEGATIVE: zero raw `<select ... class="...border-theme...">` controls
        // anywhere in the file. The hand-rolled status filter on line 67
        // carried the legacy `border border-theme rounded-lg` chrome.
        $this->assertSame(
            0,
            preg_match('#<select\b[^>]*\bborder-theme(?![\w-])#', $src),
            sprintf(
                '%s MUST NOT keep a raw `<select class="border-theme">` status '
                . 'filter (AMB-01-002 / DLR-R-002). All selects MUST consume <UiSelect>.',
                $path
            )
        );
    }

    /**
     * AMB-01-003 — the environments table dividers (lines 104 + 134) MUST
     * consume the `var(--color-hairline)` token instead of the legacy
     * `divide-theme` literal.
     *
     * POSITIVE rule: `divide-[color:var(--color-hairline)]` reference present.
     *
     * NEGATIVE rule: zero `divide-theme` literal matches anywhere in the file.
     */
    public function test_table_dividers_use_hairline(): void
    {
        $path = dirname(__DIR__, 3) . self::LIST_PAGE_PATH;
        $src = self::readSource($path);
        $this->assertNotNull($src, sprintf('%s must be readable.', $path));

        // POSITIVE: at least one `divide-[color:var(--color-hairline)]` reference
        // (apply phase replaces both `divide-theme` instances on the <thead>
        // and <tbody> wrappers).
        $this->assertTrue(
            (bool) preg_match(
                '#divide-\[color:var\(--color-hairline\)\]#',
                $src
            ),
            sprintf(
                '%s MUST consume `divide-[color:var(--color-hairline)]` on the table dividers '
                . '(AMB-01-003). Replace `divide-theme` on lines 104 + 134.',
                $path
            )
        );

        // NEGATIVE: zero `divide-theme` literal matches anywhere in the file.
        $divideThemeCount = preg_match_all('#(?<![\w-])divide-theme(?![\w-])#', $src);
        $this->assertSame(
            0,
            $divideThemeCount,
            sprintf(
                '%s MUST NOT keep the legacy `divide-theme` literal (AMB-01-003 / '
                . 'global guard rail — hairline token). Found %d `divide-theme` match(es).',
                $path,
                $divideThemeCount
            )
        );
    }

    /**
     * AMB-01-004 — the row avatar background and text colours MUST consume
     * the tokenised systemBlue ramps (`bg-systemBlue-50` + `text-systemBlue-700`)
     * instead of the legacy `bg-primary-100` + `text-accent` aliases.
     *
     * POSITIVE rule: `bg-systemBlue-50` reference present + `text-systemBlue-700`
     * reference present.
     *
     * NEGATIVE rule: zero `bg-primary-100` matches + zero `text-accent` matches.
     */
    public function test_row_avatar_uses_system_blue(): void
    {
        $path = dirname(__DIR__, 3) . self::LIST_PAGE_PATH;
        $src = self::readSource($path);
        $this->assertNotNull($src, sprintf('%s must be readable.', $path));

        // POSITIVE: `bg-systemBlue-50` reference present on the row avatar
        // background (replaces `bg-primary-100`).
        $this->assertTrue(
            (bool) preg_match('#(?<![\w-])bg-systemBlue-50(?![\w-])#', $src),
            sprintf(
                '%s MUST consume `bg-systemBlue-50` on the row avatar background '
                . '(AMB-01-004). Replace `bg-primary-100`.',
                $path
            )
        );

        // POSITIVE: `text-systemBlue-700` reference present on the row avatar
        // initial text (replaces `text-accent`).
        $this->assertTrue(
            (bool) preg_match('#(?<![\w-])text-systemBlue-700(?![\w-])#', $src),
            sprintf(
                '%s MUST consume `text-systemBlue-700` on the row avatar initial '
                . '(AMB-01-004). Replace `text-accent`.',
                $path
            )
        );

        // NEGATIVE: zero `bg-primary-100` matches anywhere in the file.
        $this->assertSame(
            0,
            preg_match('#(?<![\w-])bg-primary-100(?![\w-])#', $src),
            sprintf(
                '%s MUST NOT keep the legacy `bg-primary-100` row-avatar alias '
                . '(AMB-01-004 / DLR-R-009). Found a match.',
                $path
            )
        );

        // NEGATIVE: zero `text-accent` matches anywhere in the file.
        $this->assertSame(
            0,
            preg_match('#(?<![\w-])text-accent(?![\w-])#', $src),
            sprintf(
                '%s MUST NOT keep the legacy `text-accent` row-avatar alias '
                . '(AMB-01-004 / DLR-R-009). Found a match.',
                $path
            )
        );
    }

    /**
     * AMB-01-005 — the three action buttons (Ver Detalle / Editar / Eliminar)
     * MUST consume `<UiButton variant="link">` (Ver / Editar) and
     * `<UiButton variant="ghost">` (Eliminar) instead of `<UiButton
     * variant="ghost">` with legacy `text-accent hover:text-accent-hover` /
     * `text-accent hover:text-primary-800` / `text-red-600 hover:text-red-900`
     * raw Tailwind classes.
     *
     * POSITIVE rule: at least 2 `variant="link"` references + at least 1
     * `variant="ghost"` reference + at least 1 `text-systemRed-700` reference.
     *
     * NEGATIVE rule: zero `text-accent` + zero `hover:text-accent-hover` +
     * zero `text-red-600` + zero `hover:text-red-900` matches.
     */
    public function test_action_buttons_use_ui_button_variants(): void
    {
        $path = dirname(__DIR__, 3) . self::LIST_PAGE_PATH;
        $src = self::readSource($path);
        $this->assertNotNull($src, sprintf('%s must be readable.', $path));

        // POSITIVE: ≥2 `variant="link"` references (Ver Detalle + Editar).
        $linkCount = preg_match_all('#variant=["\']link["\']#', $src);
        $this->assertGreaterThanOrEqual(
            2,
            $linkCount,
            sprintf(
                '%s MUST consume `<UiButton variant="link">` for Ver Detalle + Editar '
                . '(AMB-01-005). Found %d `variant="link"` reference(s); expected at least 2.',
                $path,
                $linkCount
            )
        );

        // POSITIVE: ≥1 `variant="ghost"` reference on Eliminar.
        $ghostCount = preg_match_all('#variant=["\']ghost["\']#', $src);
        $this->assertGreaterThanOrEqual(
            1,
            $ghostCount,
            sprintf(
                '%s MUST consume `<UiButton variant="ghost">` for Eliminar '
                . '(AMB-01-005). Found %d `variant="ghost"` reference(s); expected at least 1.',
                $path,
                $ghostCount
            )
        );

        // POSITIVE: ≥1 `text-systemRed-700` reference on the Eliminar button body.
        $this->assertTrue(
            (bool) preg_match('#(?<![\w-])text-systemRed-700(?![\w-])#', $src),
            sprintf(
                '%s MUST consume `text-systemRed-700` on the Eliminar button body '
                . '(AMB-01-005). Replace `text-red-600`.',
                $path
            )
        );

        // NEGATIVE: zero legacy `text-accent` matches (covered already by
        // test_row_avatar_uses_system_blue; repeated here for paired error
        // context specific to the action-button row).
        $this->assertSame(
            0,
            preg_match('#(?<![\w-])text-accent(?![\w-])#', $src),
            sprintf(
                '%s MUST NOT keep legacy `text-accent` action-button classes '
                . '(AMB-01-005 / DLR-R-009). Found a match.',
                $path
            )
        );

        // NEGATIVE: zero `hover:text-accent-hover` legacy alias matches.
        $this->assertSame(
            0,
            preg_match('#(?<![\w-])hover:text-accent-hover(?![\w-])#', $src),
            sprintf(
                '%s MUST NOT keep the legacy `hover:text-accent-hover` action-button alias '
                . '(AMB-01-005). Found a match.',
                $path
            )
        );

        // NEGATIVE: zero `text-red-600` legacy Tailwind matches.
        $this->assertSame(
            0,
            preg_match('#(?<![\w-])text-red-600(?![\w-])#', $src),
            sprintf(
                '%s MUST NOT keep the legacy `text-red-600` Tailwind ramp '
                . '(AMB-01-005). Found a match.',
                $path
            )
        );

        // NEGATIVE: zero `hover:text-red-900` legacy Tailwind matches.
        $this->assertSame(
            0,
            preg_match('#(?<![\w-])hover:text-red-900(?![\w-])#', $src),
            sprintf(
                '%s MUST NOT keep the legacy `hover:text-red-900` Tailwind ramp '
                . '(AMB-01-005). Found a match.',
                $path
            )
        );
    }

    /**
     * AMB-01-006 — the hand-rolled spinner (line 82) carrying
     * `inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-accent`
     * MUST migrate to `<UiLoadingSpinner size="md" />`. The `border-accent`
     * legacy alias MUST be removed.
     *
     * POSITIVE rule: ≥1 `<UiLoadingSpinner` reference in the file.
     *
     * NEGATIVE rule: zero `border-accent` legacy alias matches anywhere in
     * the file.
     */
    public function test_loading_spinner_uses_ui_component(): void
    {
        $path = dirname(__DIR__, 3) . self::LIST_PAGE_PATH;
        $src = self::readSource($path);
        $this->assertNotNull($src, sprintf('%s must be readable.', $path));

        // POSITIVE: ≥1 `<UiLoadingSpinner` reference (the list loading
        // state on line 82 must consume the canonical primitive).
        $this->assertTrue(
            (bool) preg_match('#<UiLoadingSpinner\b#', $src)
                || (bool) preg_match(
                    '#import\s+UiLoadingSpinner\s+from\s+[\'"][^\'"]*components/ui/LoadingSpinner\.vue[\'"]#',
                    $src
                ),
            sprintf(
                '%s MUST consume `<UiLoadingSpinner />` for the list loading state '
                . '(AMB-01-006). Replace the hand-rolled spinner on line 82.',
                $path
            )
        );

        // NEGATIVE: zero `border-accent` legacy alias matches anywhere in
        // the file. The hand-rolled spinner carried `border-b-2 border-accent`;
        // the canonical primitive does not use any `border-accent` class.
        $borderAccentCount = preg_match_all('#(?<![\w-])border-accent(?![\w-])#', $src);
        $this->assertSame(
            0,
            $borderAccentCount,
            sprintf(
                '%s MUST NOT keep the legacy `border-accent` spinner alias '
                . '(AMB-01-006 / DLR-R-009). Found %d `border-accent` match(es).',
                $path,
                $borderAccentCount
            )
        );
    }

    /**
     * AMB-01-007 — the hand-rolled empty state on lines 86-101 (custom SVG
     * icon + `<p>No se encontraron ambientes</p>` paragraph) MUST migrate
     * to `<UiEmptyState title="No se encontraron ambientes" description="..." />`.
     *
     * POSITIVE rule: ≥1 `<UiEmptyState` reference in the file.
     *
     * NEGATIVE rule: zero hand-rolled `<svg class="mx-auto h-12 w-12 text-theme-secondary">`
     * empty-state icon matches + zero `<p class="mt-2 text-theme-secondary">No se encontraron ambientes</p>`
     * literal matches.
     */
    public function test_empty_state_uses_ui_component(): void
    {
        $path = dirname(__DIR__, 3) . self::LIST_PAGE_PATH;
        $src = self::readSource($path);
        $this->assertNotNull($src, sprintf('%s must be readable.', $path));

        // POSITIVE: ≥1 `<UiEmptyState` reference. The unused import in the
        // file (line 368 per the explore inventory) MUST now be consumed.
        $uiEmptyStateCount = preg_match_all('#<UiEmptyState\b#', $src);
        $this->assertGreaterThanOrEqual(
            1,
            $uiEmptyStateCount,
            sprintf(
                '%s MUST consume `<UiEmptyState title="..." description="..." />` for '
                . 'the list empty state (AMB-01-007). Found %d <UiEmptyState> reference(s); '
                . 'the unused import at the top of the file must be wired up.',
                $path,
                $uiEmptyStateCount
            )
        );

        // NEGATIVE: zero hand-rolled custom-SVG empty-state icon container.
        // The hand-rolled empty state carried
        // `<svg class="mx-auto h-12 w-12 text-theme-secondary">` paired with
        // a `<p class="mt-2 text-theme-secondary">No se encontraron ambientes</p>`
        // paragraph.
        $this->assertDoesNotMatchRegularExpression(
            '#<svg\b[^>]*\bclass=["\'][^"\']*\bmx-auto\b[^"\']*\bh-12\b[^"\']*\btext-theme-secondary\b#',
            $src,
            sprintf(
                '%s MUST NOT keep the hand-rolled `<svg class="mx-auto h-12 text-theme-secondary">` '
                . 'empty-state icon (AMB-01-007). Replace with <UiEmptyState title="..." description="..." />.',
                $path
            )
        );

        // Companion NEGATIVE: the literal empty-state title text
        // `No se encontraron ambientes` MUST NOT remain inside a raw `<p>` element.
        $this->assertDoesNotMatchRegularExpression(
            '#<p\b[^>]*>\s*No se encontraron ambientes\s*</p>#',
            $src,
            sprintf(
                '%s MUST NOT keep the hand-rolled `<p>No se encontraron ambientes</p>` '
                . 'empty-state paragraph (AMB-01-007). The title MUST live on the '
                . '<UiEmptyState title="..."> prop.',
                $path
            )
        );
    }

    /**
     * Local helper — read the file source, or null if unreadable.
     * Mirrors `AppointmentTypesAppShellTest::readSource()` so this test is
     * self-contained without extending the existing class (we extend
     * `ModuleAppShellTestCase` directly per `PatientsListAppShellTest`
     * precedent).
     */
    private static function readSource(string $path): ?string
    {
        if (!is_file($path)) {
            return null;
        }
        $src = file_get_contents($path);
        return $src === false ? null : $src;
    }

    // ========================================================================
    // PR-ambientes-02 — Detail page (`EnvironmentDetailPage.vue`) assertions.
    // Extends `polishedFiles()` coverage + adds 6 PR-02-only rules.
    // ========================================================================

    /**
     * AMB-02-003 — the 2-tab drawer (line 70 raw tab strip + per-button
     * `border-accent text-accent` / `border-transparent text-theme-secondary
     * hover:text-theme-primary hover:border-theme` active/inactive styles)
     * MUST migrate to `<UiTabs v-model="activeTab" :tabs="tabs">`. The
     * 2 tab labels (`Datos` / `Historial de auditoría`) MUST keep their
     * labels byte-for-byte.
     *
     * POSITIVE rule: ≥1 `<UiTabs` reference present in the detail page.
     *
     * NEGATIVE rule: zero `border-accent text-accent` legacy active-indicator
     * matches anywhere in the file.
     */
    public function test_tabs_use_ui_tabs(): void
    {
        $path = dirname(__DIR__, 3) . self::DETAIL_PAGE_PATH;
        $src = self::readSource($path);
        $this->assertNotNull($src, sprintf('%s must be readable.', $path));

        // POSITIVE: ≥1 `<UiTabs` reference (the 2-tab drawer must consume
        // the canonical primitive, not a hand-rolled nav element).
        $this->assertTrue(
            (bool) preg_match('#<UiTabs\b#', $src)
                || (bool) preg_match(
                    '#import\s+UiTabs\s+from\s+[\'"][^\'"]*components/ui/Tabs\.vue[\'"]#',
                    $src
                ),
            sprintf(
                '%s MUST consume `<UiTabs v-model="activeTab" :tabs="tabs">` for the '
                . '2-tab drawer (AMB-02-003). Replace the raw `<nav class="flex space-x-8 border-b border-theme">` '
                . 'on line 70 with the canonical primitive.',
                $path
            )
        );

        // NEGATIVE: zero `border-accent text-accent` literal matches anywhere
        // in the file. The hand-rolled tab strip carried the legacy active
        // indicator class `border-accent text-accent` on the active button.
        $borderAccentCount = preg_match_all(
            '#(?<![\w-])border-accent\s+text-accent(?![\w-])#',
            $src
        );
        $this->assertSame(
            0,
            $borderAccentCount,
            sprintf(
                '%s MUST NOT keep the legacy `border-accent text-accent` active-tab '
                . 'indicator (AMB-02-003 / DLR-R-009). Found %d match(es).',
                $path,
                $borderAccentCount
            )
        );
    }

    /**
     * AMB-02-004 — the header avatar on line 30 carries `bg-gradient-accent`
     * which is forbidden by global §11 (no gradients). The avatar MUST be
     * tokenised to flat `bg-systemBlue-50` + `rounded-[var(--radius-card-lg)]`,
     * mirroring the precedent from `PatientsPage` row avatars.
     *
     * POSITIVE rule: `bg-systemBlue-50` reference present + `rounded-[var(--radius-card-lg)]`
     * reference present on the header avatar.
     *
     * NEGATIVE rule: zero `bg-gradient-*` legacy matches anywhere in the file.
     */
    public function test_no_gradient_class(): void
    {
        $path = dirname(__DIR__, 3) . self::DETAIL_PAGE_PATH;
        $src = self::readSource($path);
        $this->assertNotNull($src, sprintf('%s must be readable.', $path));

        // POSITIVE: `bg-systemBlue-50` reference present on the header avatar
        // background (replaces `bg-gradient-accent`).
        $this->assertTrue(
            (bool) preg_match('#(?<![\w-])bg-systemBlue-50(?![\w-])#', $src),
            sprintf(
                '%s MUST consume `bg-systemBlue-50` on the header avatar '
                . '(AMB-02-004). Replace `bg-gradient-accent` with a flat ramp.',
                $path
            )
        );

        // NEGATIVE: zero `bg-gradient-*` legacy matches anywhere in the file.
        // The header avatar (line 30) + the audit empty-state icon
        // (lines 145-167 in the original) both carried `bg-gradient-*` classes.
        $gradientCount = preg_match_all('#(?<![\w-])bg-gradient-#', $src);
        $this->assertSame(
            0,
            $gradientCount,
            sprintf(
                '%s MUST NOT keep any `bg-gradient-*` class (AMB-02-004 / '
                . 'global §11 — no gradients). Found %d `bg-gradient-*` match(es).',
                $path,
                $gradientCount
            )
        );
    }

    /**
     * AMB-02-005 — the hand-rolled audit-log empty state (lines 145-167 with
     * `bg-gradient-to-br from-theme-surface to-theme-surface-elevated`
     * icon + custom `<h3>` + `<p>` paragraphs) MUST migrate to
     * `<UiEmptyState title="No hay historial de auditoría" description="..." />`.
     *
     * POSITIVE rule: ≥1 `<UiEmptyState` reference in the detail page.
     *
     * NEGATIVE rule: zero `bg-gradient-to-br` legacy alias matches.
     */
    public function test_audit_empty_state_uses_ui_component(): void
    {
        $path = dirname(__DIR__, 3) . self::DETAIL_PAGE_PATH;
        $src = self::readSource($path);
        $this->assertNotNull($src, sprintf('%s must be readable.', $path));

        // POSITIVE: ≥1 `<UiEmptyState` reference (the audit-log empty state
        // must consume the canonical primitive, not a hand-rolled gradient
        // SVG + paragraph).
        $this->assertTrue(
            (bool) preg_match('#<UiEmptyState\b#', $src)
                || (bool) preg_match(
                    '#import\s+UiEmptyState\s+from\s+[\'"][^\'"]*components/ui/EmptyState\.vue[\'"]#',
                    $src
                ),
            sprintf(
                '%s MUST consume `<UiEmptyState title="No hay historial de auditoría" '
                . 'description="...">` for the audit-log empty state (AMB-02-005). '
                . 'Replace the hand-rolled gradient SVG + paragraphs on lines 145-167.',
                $path
            )
        );

        // NEGATIVE: zero `bg-gradient-to-br` legacy alias matches. The
        // audit empty state icon container carried `bg-gradient-to-br
        // from-theme-surface to-theme-surface-elevated`.
        $this->assertSame(
            0,
            preg_match('#(?<![\w-])bg-gradient-to-br(?![\w-])#', $src),
            sprintf(
                '%s MUST NOT keep the legacy `bg-gradient-to-br` audit empty-state '
                . 'icon alias (AMB-02-005 / global §11). Found a match.',
                $path
            )
        );
    }

    /**
     * AMB-02-006 — the audit log item wrapper on line 172
     * (`border border-theme rounded-lg p-4 hover:bg-theme-surface
     * transition-colors`) MUST migrate to `<UiCard variant="glass">`.
     *
     * POSITIVE rule: ≥1 `<UiCard\b[^>]*\bvariant=["\']glass["\']` reference.
     *
     * NEGATIVE rule: zero `border border-theme rounded-lg p-4` legacy
     * audit-item wrapper class string matches.
     */
    public function test_audit_log_uses_ui_card(): void
    {
        $path = dirname(__DIR__, 3) . self::DETAIL_PAGE_PATH;
        $src = self::readSource($path);
        $this->assertNotNull($src, sprintf('%s must be readable.', $path));

        // POSITIVE: ≥1 `<UiCard variant="glass">` reference. The audit log
        // card wrapper must consume the canonical UiCard primitive.
        $this->assertTrue(
            (bool) preg_match(
                '#<UiCard\b[^>]*\bvariant=["\']glass["\']#',
                $src
            ),
            sprintf(
                '%s MUST consume `<UiCard variant="glass">` for the audit log item wrapper '
                . '(AMB-02-006). Replace the hand-rolled `border border-theme rounded-lg p-4` '
                . 'wrapper on line 172.',
                $path
            )
        );

        // NEGATIVE: zero legacy audit-item wrapper class string matches.
        $this->assertDoesNotMatchRegularExpression(
            '#<div\b[^>]*\bclass=["\'][^"\']*\bborder\s+border-theme\s+rounded-lg\s+p-4\b#',
            $src,
            sprintf(
                '%s MUST NOT keep the legacy `border border-theme rounded-lg p-4` '
                . 'audit log item wrapper (AMB-02-006). Replace with `<UiCard variant="glass">`.',
                $path
            )
        );
    }

    /**
     * AMB-02-006 (companion) — the change-diff callout border on line 198
     * `border-l-2 border-theme` MUST consume the canonical hairline token
     * `border-[color:var(--color-hairline)]`.
     *
     * POSITIVE rule: `border-l-2 border-[color:var(--color-hairline)]`
     * reference present.
     *
     * NEGATIVE rule: zero `border-l-2 border-theme` literal matches.
     */
    public function test_change_diff_callout_uses_hairline(): void
    {
        $path = dirname(__DIR__, 3) . self::DETAIL_PAGE_PATH;
        $src = self::readSource($path);
        $this->assertNotNull($src, sprintf('%s must be readable.', $path));

        // POSITIVE: `border-l-2 border-[color:var(--color-hairline)]` reference
        // present (the change-diff callout border must consume the hairline
        // token, NOT `border-theme`).
        $this->assertTrue(
            (bool) preg_match(
                '#(?<![\w-])border-l-2\s+border-\[color:var\(--color-hairline\)\](?![\w-])#',
                $src
            ),
            sprintf(
                '%s MUST consume `border-l-2 border-[color:var(--color-hairline)]` on the '
                . 'change-diff callout (AMB-02-006). Replace `border-l-2 border-theme` '
                . 'on line 198.',
                $path
            )
        );

        // NEGATIVE: zero `border-l-2 border-theme` literal matches.
        $this->assertSame(
            0,
            preg_match('#(?<![\w-])border-l-2\s+border-theme(?![\w-])#', $src),
            sprintf(
                '%s MUST NOT keep the legacy `border-l-2 border-theme` change-diff callout '
                . 'border (AMB-02-006 / DLR-R-002). Found a match.',
                $path
            )
        );
    }

    /**
     * AMB-02-002 + DLR-AMB-005 EXCEPTION #2 — the `<script>` helper
     * `getAuditActionVariant` MUST return `'neutral'` for the default
     * case (NOT `'secondary'`). `'secondary'` is not a legal `<UiBadge>`
     * variant per `StatusBadge.vue:27` (`[success, warning, error, info,
     * neutral]`) and would render blank.
     *
     * POSITIVE rule: `getAuditActionVariant` helper present + maps the
     * unknown-action default to `'neutral'`.
     *
     * NEGATIVE rule: zero `'secondary'` literal matches inside the
     * `getAuditActionVariant` helper body (the rest of the file is
     * tolerated — `var(--motion-duration-secondary)` and similar tokens
     * are token-side artifacts and excluded by the regex below).
     */
    public function test_audit_action_badge_uses_legal_variant(): void
    {
        $path = dirname(__DIR__, 3) . self::DETAIL_PAGE_PATH;
        $src = self::readSource($path);
        $this->assertNotNull($src, sprintf('%s must be readable.', $path));

        // POSITIVE: `getAuditActionVariant` reference present (the helper
        // exists and is consumed by the audit action badge).
        $this->assertTrue(
            (bool) preg_match('#\bgetAuditActionVariant\b#', $src),
            sprintf(
                '%s MUST declare the `getAuditActionVariant` helper (AMB-02-002 / '
                . 'DLR-AMB-005 EXCEPTION #2). The audit action badge uses it to '
                . 'pick the <UiBadge> variant.',
                $path
            )
        );

        // NEGATIVE: zero `'secondary'` literal matches inside the helper body.
        // The legacy `return 'secondary'` for the default-case fallback is
        // an illegal <UiBadge> variant — it must be replaced with
        // `return 'neutral'`.
        $this->assertSame(
            0,
            preg_match(
                '#getAuditActionVariant[^}]*[\'"]secondary[\'"]#s',
                $src
            ),
            sprintf(
                '%s MUST NOT return the illegal <UiBadge> variant `\'secondary\'` from '
                . '`getAuditActionVariant` (AMB-02-002 / DLR-AMB-005 EXCEPTION #2). '
                . 'Replace `return \'secondary\'` with `return \'neutral\'`.',
                $path
            )
        );

        // POSITIVE: the helper's default-case fallback returns `'neutral'`.
        // Pattern: `return 'neutral'` literal appears inside the
        // `getAuditActionVariant` helper body.
        $this->assertTrue(
            (bool) preg_match(
                '#getAuditActionVariant[^}]*return\s+[\'"]neutral[\'"]#s',
                $src
            ),
            sprintf(
                '%s MUST map the `getAuditActionVariant` default case to '
                . '`return \'neutral\'` (AMB-02-002 / DLR-AMB-005 EXCEPTION #2).',
                $path
            )
        );
    }
}
