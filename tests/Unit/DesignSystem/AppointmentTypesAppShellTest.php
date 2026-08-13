<?php

namespace Tests\Unit\DesignSystem;

/**
 * PR-citas-04 — AppointmentTypesAppShellTest.
 *
 * Asserts CITAS-AT-001 (admin CRUD triplet uses Ui primitives + canonical
 * formatCurrency) for the two `appointment-types` module pages:
 *
 *   - `AppointmentTypesPage.vue` (list) — filter bar, table, status pills,
 *     price column.
 *   - `AppointmentTypeDetailPage.vue` (detail) — header card, info card,
 *     audit log, status badge, price summary.
 *
 * The base class `ModuleAppShellTestCase` enforces the 5 inherited DLR-R
 * rules (canvas token, no `border-theme`, focus ring, no `<style scoped>`,
 * no legacy focus-ring aliases) via `polishedFileProvider()`. This subclass
 * adds the 4 PR-citas-04-only rule assertions below.
 *
 * Per CITAS-CON-001, the `<script>` blocks of both files are preserved
 * byte-for-byte except for the additive `formatCurrency` import (mandated
 * by PR-pagos-05 / CITAS-AT-001). The reactivity (`useApi` ownership of
 * the 401 redirect path, `useToast`, `useConfirm`, `useAuditLogs`, the
 * `loadTypes` / `loadAppointmentType` / `createType` / `updateType` /
 * `deleteType` flows, the `useRouter` `goBack` handler) stays untouched.
 *
 * Implementation note: regex delimiters are `#` (NOT `/`) because the path
 * patterns contain forward slashes; using `/` as delimiter would force
 * every `/` in the path to be escaped `\/`, which is brittle and error-prone.
 */
class AppointmentTypesAppShellTest extends ModuleAppShellTestCase
{
    /** List page path constant. */
    private const LIST_PAGE_PATH = '/resources/js/modules/appointment-types/AppointmentTypesPage.vue';

    /** Detail page path constant. */
    private const DETAIL_PAGE_PATH = '/resources/js/modules/appointment-types/AppointmentTypeDetailPage.vue';

    /** @return array<int, string> */
    protected static function polishedFiles(): array
    {
        return [
            dirname(__DIR__, 3) . self::LIST_PAGE_PATH,
            dirname(__DIR__, 3) . self::DETAIL_PAGE_PATH,
        ];
    }

    /**
     * CITAS-AT-001 — the filter bar MUST consume `<UiSelect>` (NOT raw
     * `<select>`). The list page has a `statusFilter` reactive ref bound
     * to a `<select>` that filters by active/inactive; the migration
     * replaces the raw `<select class="border-theme">` with
     * `<UiSelect :options="statusFilterOptions" v-model="statusFilter" />`.
     *
     * Zero raw `<select>` controls may remain in the list page's filter bar.
     */
    public function test_pages_use_ui_select_for_filter_bar(): void
    {
        $listPath = dirname(__DIR__, 3) . self::LIST_PAGE_PATH;
        $listSrc = self::readSource($listPath);
        $this->assertNotNull($listSrc, sprintf('%s must be readable.', $listPath));

        // 1) The list page consumes a `<UiSelect>` primitive (either as a
        //    JSX-style tag or as a named import from `components/ui/Select.vue`).
        $this->assertTrue(
            (bool) preg_match('#<UiSelect\b#', $listSrc)
                || (bool) preg_match(
                    '#import\s+\w*[Ss]elect\w*\s+from\s+[\'"][^\'"]*components/ui/Select\.vue[\'"]#',
                    $listSrc
                ),
            sprintf(
                '%s MUST consume <UiSelect> for the filter bar (CITAS-AT-001). '
                . 'Raw `<select class="border-theme">` controls are deprecated.',
                $listPath
            )
        );

        // 2) Zero raw `<select ... class="...border-theme...">` controls may
        //    remain in the list page (the filter bar + the edit modal's
        //    is_active toggle must both consume UiSelect).
        $this->assertSame(
            0,
            preg_match(
                '#<select\b[^>]*\bborder-theme(?![\w-])#',
                $listSrc
            ),
            sprintf(
                '%s MUST NOT keep a raw `<select class="border-theme">` filter '
                . 'or toggle control (CITAS-AT-001 / DLR-R-002). '
                . 'All selects MUST consume <UiSelect>.',
                $listPath
            )
        );
    }

    /**
     * CITAS-AT-001 — the `price` field on both pages MUST consume the
     * canonical `formatCurrency` from `useFormatters.js`. Zero local
     * `Intl.NumberFormat` declarations may remain in either file.
     *
     * The canonical import is either:
     *   - `import { formatCurrency } from '<path>/composables/useFormatters'`
     *   - `import { formatCurrency } from '@/composables/useFormatters'`
     *
     * The legacy `formatPENLabel` alias is also accepted (backwards-compatible
     * alias from PR-pagos-01).
     */
    public function test_pages_use_format_currency_for_price(): void
    {
        $listPath = dirname(__DIR__, 3) . self::LIST_PAGE_PATH;
        $listSrc = self::readSource($listPath);
        $this->assertNotNull($listSrc, sprintf('%s must be readable.', $listPath));

        $detailPath = dirname(__DIR__, 3) . self::DETAIL_PAGE_PATH;
        $detailSrc = self::readSource($detailPath);
        $this->assertNotNull($detailSrc, sprintf('%s must be readable.', $detailPath));

        // 1) The list page imports `formatCurrency` (or `formatPENLabel` alias)
        //    from the canonical composable location.
        $this->assertTrue(
            (bool) preg_match(
                '#import\s*\{[^}]*?\bformatCurrency\b[^}]*?\}\s*from\s*[\'"][^\'"]*composables/useFormatters[\'"]#',
                $listSrc
            ),
            sprintf(
                '%s MUST import `formatCurrency` from the canonical `useFormatters.js` '
                . 'location for the price field (CITAS-AT-001 / PAGOS-MNY-002).',
                $listPath
            )
        );

        // 2) The detail page also imports `formatCurrency` (or uses the alias).
        $hasFormatCurrencyImport = (bool) preg_match(
            '#import\s*\{[^}]*?\bformatCurrency\b[^}]*?\}\s*from\s*[\'"][^\'"]*composables/useFormatters[\'"]#',
            $detailSrc
        );
        $hasFormatPenLabelImport = (bool) preg_match(
            '#import\s*\{[^}]*?\bformatPENLabel\b[^}]*?\}\s*from\s*[\'"][^\'"]*composables/useFormatters[\'"]#',
            $detailSrc
        );
        $this->assertTrue(
            $hasFormatCurrencyImport || $hasFormatPenLabelImport,
            sprintf(
                '%s MUST import `formatCurrency` (or `formatPENLabel` alias) from the canonical '
                . '`useFormatters.js` location for the price summary (CITAS-AT-001 / PAGOS-MNY-002).',
                $detailPath
            )
        );

        // 3) Neither file may declare a local `Intl.NumberFormat` for PEN
        //    rendering (zero `Intl.NumberFormat('es-PE', { currency: 'PEN' })`).
        $this->assertSame(
            0,
            preg_match(
                '#(?<![\w-])Intl\.NumberFormat\s*\(\s*[\'"]es-PE[\'"]\s*,\s*\{\s*[^{}]*?currency\s*:\s*[\'"]PEN[\'"]#',
                $listSrc
            ),
            sprintf(
                '%s MUST NOT redeclare a local `Intl.NumberFormat(\'es-PE\', { currency: \'PEN\' })` '
                . '(PR-pagos-01 / CITAS-AT-001). The canonical `formatCurrency` from `useFormatters.js` '
                . 'is the sole money formatter.',
                $listPath
            )
        );

        $this->assertSame(
            0,
            preg_match(
                '#(?<![\w-])Intl\.NumberFormat\s*\(\s*[\'"]es-PE[\'"]\s*,\s*\{\s*[^{}]*?currency\s*:\s*[\'"]PEN[\'"]#',
                $detailSrc
            ),
            sprintf(
                '%s MUST NOT redeclare a local `Intl.NumberFormat(\'es-PE\', { currency: \'PEN\' })` '
                . '(PR-pagos-01 / CITAS-AT-001). The canonical `formatCurrency` from `useFormatters.js` '
                . 'is the sole money formatter.',
                $detailPath
            )
        );

        // 4) Neither file may contain the literal `S/ ${...}` template pattern
        //    (it was the legacy inline pattern that PR-pagos-01 eliminated).
        $this->assertSame(
            0,
            preg_match(
                '#S/\s*\$\{[^}]+\}#',
                $listSrc
            ),
            sprintf(
                '%s MUST NOT contain the legacy `S/ ${...}` literal pattern (PR-pagos-01). '
                . 'Use `formatCurrency(price)` from the canonical helper.',
                $listPath
            )
        );

        $this->assertSame(
            0,
            preg_match(
                '#S/\s*\$\{[^}]+\}#',
                $detailSrc
            ),
            sprintf(
                '%s MUST NOT contain the legacy `S/ ${...}` literal pattern (PR-pagos-01). '
                . 'Use `formatCurrency(price)` from the canonical helper.',
                $detailPath
            )
        );
    }

    /**
     * CITAS-AT-001 — neither page may carry the legacy status-pill colour
     * classes (`bg-success-100`, `bg-error-100`, `bg-warning-100`,
     * `text-success-700`, `text-error-700`, `text-warning-700`). The
     * token-aligned form is `<UiStatusBadge variant="success|neutral|error>">`.
     *
     * The list page is the heaviest offender (the `is_active` status pill);
     * the detail page has the `is_active` badge on the header card.
     */
    public function test_pages_no_legacy_status_pills(): void
    {
        $forbiddenAliases = [
            'bg-success-100',
            'bg-error-100',
            'bg-warning-100',
            'text-success-700',
            'text-error-700',
            'text-warning-700',
        ];

        foreach (self::polishedFiles() as $path) {
            $src = self::readSource($path);
            $this->assertNotNull($src, sprintf('%s must be readable.', $path));

            foreach ($forbiddenAliases as $alias) {
                $this->assertSame(
                    0,
                    preg_match('#(?<![\w-])' . preg_quote($alias, '#') . '(?![\w-])#', $src),
                    sprintf(
                        '%s MUST NOT keep the legacy status-pill class `%s` (CITAS-AT-001 / DLR-R-009). '
                        . 'Replace it with `<UiStatusBadge variant="success|neutral|error">`.',
                        $path,
                        $alias
                    )
                );
            }
        }
    }

    /**
     * CITAS-AT-001 — neither page may carry the legacy focus-ring aliases
     * (`focus:ring-primary-500` or `focus:border-accent`). The token-aligned
     * form is the global `var(--focus-ring-default)` (composed by the Ui
     * primitives) or the Apple-language `focus:ring-systemBlue-500` ramp.
     *
     * The list page has 6 raw text inputs (name / description / duration /
     * price / color in the create modal + edit modal) and the edit modal's
     * state `<select>`; the detail page has none but the rule is paired
     * for symmetry.
     */
    public function test_pages_no_legacy_focus_ring(): void
    {
        foreach (self::polishedFiles() as $path) {
            $src = self::readSource($path);
            $this->assertNotNull($src, sprintf('%s must be readable.', $path));

            $this->assertSame(
                0,
                preg_match(
                    '#(?<![\w-])focus:ring-primary-500(?![\w-])#',
                    $src
                ),
                sprintf(
                    '%s MUST NOT contain the legacy `focus:ring-primary-500` focus-ring alias (DLR-R-004 / CITAS-AT-001). '
                    . 'Rely on the global `var(--focus-ring-default)` (composed by the Ui primitives).',
                    $path
                )
            );

            $this->assertSame(
                0,
                preg_match(
                    '#(?<![\w-])focus:border-accent(?![\w-])#',
                    $src
                ),
                sprintf(
                    '%s MUST NOT contain the legacy `focus:border-accent` focus-ring alias (DLR-R-004). '
                    . 'The global token owns the focus border colour.',
                    $path
                )
            );
        }
    }

    /**
     * DLR-R-007 — the `price` column / `price` summary in both pages MUST
     * be rendered with `tabular-nums` so digit columns align. The token form
     * is `font-feature-settings: var(--font-features-tabular-nums)` OR the
     * Tailwind `tabular-nums` utility class (which is the canonical exposure).
     *
     * This is the load-bearing accessibility rule for the price field per
     * DLR-R-007; the rule pins the form, not the literal.
     */
    public function test_pages_price_column_uses_tabular_nums(): void
    {
        $listPath = dirname(__DIR__, 3) . self::LIST_PAGE_PATH;
        $listSrc = self::readSource($listPath);
        $this->assertNotNull($listSrc, sprintf('%s must be readable.', $listPath));

        $detailPath = dirname(__DIR__, 3) . self::DETAIL_PAGE_PATH;
        $detailSrc = self::readSource($detailPath);
        $this->assertNotNull($detailSrc, sprintf('%s must be readable.', $detailPath));

        // The list page's price column (or the wrapping <td>) applies the
        // `tabular-nums` utility. The check accepts either the Tailwind class
        // OR the token form (`font-feature-settings: var(--font-features-tabular-nums)`).
        $this->assertTrue(
            (bool) preg_match('#tabular-nums#', $listSrc)
                || (bool) preg_match(
                    '#font-feature-settings\s*:\s*var\(--font-features-tabular-nums\)#',
                    $listSrc
                ),
            sprintf(
                '%s MUST apply `tabular-nums` (or the token form) on the price column (DLR-R-007). '
                . 'Use `tabular-nums` Tailwind utility or `font-feature-settings: var(--font-features-tabular-nums)`.',
                $listPath
            )
        );

        // The detail page renders the price summary inside the info card;
        // the test accepts either the Tailwind class on the wrapping element
        // OR the token form on the rendered span.
        $this->assertTrue(
            (bool) preg_match('#tabular-nums#', $detailSrc)
                || (bool) preg_match(
                    '#font-feature-settings\s*:\s*var\(--font-features-tabular-nums\)#',
                    $detailSrc
                ),
            sprintf(
                '%s MUST apply `tabular-nums` (or the token form) on the price summary (DLR-R-007). '
                . 'Use `tabular-nums` Tailwind utility or `font-feature-settings: var(--font-features-tabular-nums)`.',
                $detailPath
            )
        );
    }

    /**
     * DLR-R-021 — neither page may contain a `<style scoped>` block. The
     * redesign drops the legacy scoped CSS in favor of Tailwind utility
     * classes + the global token CSS. The base class `ModuleAppShellTestCase`
     * already pins this rule via `polishedFileProvider()`; this method
     * provides a per-PR error message and is paired with the other
     * PR-citas-04 rules.
     */
    public function test_pages_no_style_scoped(): void
    {
        foreach (self::polishedFiles() as $path) {
            $src = self::readSource($path);
            $this->assertNotNull($src, sprintf('%s must be readable.', $path));

            $this->assertSame(
                0,
                preg_match('#<style\s+scoped\s*>#', $src),
                sprintf(
                    '%s MUST NOT contain a `<style scoped>` block (DLR-R-021 / CITAS-AT-001). '
                    . 'Tailwind utility classes + global token CSS own the visual surface.',
                    $path
                )
            );
        }
    }

    /**
     * CITAS-CON-001 — the list page's `useApi` import (the 401 redirect
     * owner per UXF-021) MUST be preserved. The migration is template-only
     * for the script block; the `useApi` binding stays byte-for-byte.
     */
    public function test_list_page_use_api_ownership_preserved(): void
    {
        $listPath = dirname(__DIR__, 3) . self::LIST_PAGE_PATH;
        $listSrc = self::readSource($listPath);
        $this->assertNotNull($listSrc, sprintf('%s must be readable.', $listPath));

        // The list page imports `useApi` from the canonical composable path.
        $this->assertTrue(
            (bool) preg_match(
                '#import\s*\{[^}]*?useApi[^}]*?\}\s*from\s*[\'"]\.\./\.\./composables/useApi[\'"]#',
                $listSrc
            )
            || (bool) preg_match(
                '#import\s*\{[^}]*?useApi[^}]*?\}\s*from\s*[\'"]@/composables/useApi[\'"]#',
                $listSrc
            ),
            sprintf(
                '%s MUST keep the `useApi` import (CITAS-CON-001). '
                . '`useApi` owns the 401 redirect contract; the page MUST NOT bypass it.',
                $listPath
            )
        );
    }

    private static function readSource(string $path): ?string
    {
        if (!is_file($path)) {
            return null;
        }
        $src = file_get_contents($path);
        return $src === false ? null : $src;
    }
}
