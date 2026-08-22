<?php

namespace Tests\Unit\DesignSystem;

/**
 * PR-ambientes-01 — EnvironmentsStatusBadgeTest.
 *
 * Asserts the AMB-01-008 rule: the status pill on the list rows + the
 * View modal MUST consume `<UiStatusBadge>` (or `<UiBadge>`) with a
 * variant token, NOT the legacy colour-class strings
 * (`bg-success-100 text-success-700`, `bg-warning-100 text-warning-700`,
 * `bg-theme-surface text-theme-primary`).
 *
 * Companion assertion: the `<script>` helper `getStatusColor` MUST be
 * renamed to `getStatusVariant` (DLR-AMB-005 EXCEPTION #1) and its return
 * values MUST change from colour-class strings to variant tokens
 * (`success | neutral | warning`). This is a documented exception to the
 * global `<script>`-never-touched rule; the rename is 1-line additive.
 *
 * The canonical UiStatusBadge variant set is
 * `[success, warning, error, info, neutral]` per `StatusBadge.vue:27`.
 * The dental-chair status enum is `active | inactive | maintenance`,
 * which maps to:
 *
 *   - active       → success
 *   - inactive     → neutral
 *   - maintenance  → warning
 *
 * Implementation note: regex delimiters are `#` (NOT `/`) because the
 * file paths contain forward slashes; using `/` would force every `/` to
 * be escaped `\/`, which is brittle and error-prone.
 */
class EnvironmentsStatusBadgeTest extends \PHPUnit\Framework\TestCase
{
    /** List page path constant — single source of truth for the data provider. */
    private const LIST_PAGE_PATH = '/resources/js/modules/environments/EnvironmentsPage.vue';

    private static function projectRoot(): string
    {
        return dirname(__DIR__, 3);
    }

    private static function listPagePath(): string
    {
        return self::projectRoot() . self::LIST_PAGE_PATH;
    }

    private static function readSource(string $path): ?string
    {
        if (!is_file($path)) {
            return null;
        }
        $src = file_get_contents($path);
        return $src === false ? null : $src;
    }

    /**
     * AMB-01-008 — the list page status pill (line 170) and the View modal
     * status pill (line 340) MUST consume `<UiStatusBadge>` (or `<UiBadge>`)
     * with a `:variant="..."` prop bound to a token string, NOT the legacy
     * colour-class strings.
     *
     * POSITIVE rule: ≥2 `<UiStatusBadge` (or `<UiBadge`) references in the
     * file — one on the list rows + one on the View modal status pill.
     *
     * POSITIVE rule: at least 1 `<UiBadge\b[^>]*\bvariant=` reference (the
     * list row pill must pass the variant explicitly via prop).
     *
     * NEGATIVE rule: zero `bg-success-100` + `bg-warning-100` +
     * `bg-theme-surface text-theme-primary` literal class strings.
     */
    public function test_status_pill_uses_ui_status_badge(): void
    {
        $path = self::listPagePath();
        $src = self::readSource($path);
        $this->assertNotNull($src, sprintf('%s must be readable.', $path));

        // POSITIVE: ≥2 `<UiStatusBadge` or `<UiBadge` references — one on
        // the list rows, one on the View modal status pill.
        $badgeRefs = preg_match_all(
            '#<Ui(?:Status)?Badge\b#',
            $src
        );
        $this->assertGreaterThanOrEqual(
            2,
            $badgeRefs,
            sprintf(
                '%s MUST consume `<UiStatusBadge>` for the list-row + View-modal '
                . 'status pills (AMB-01-008). Found %d badge reference(s); '
                . 'expected at least 2 (list row + View modal).',
                $path,
                $badgeRefs
            )
        );

        // POSITIVE: ≥1 `<UiBadge ... variant="..."` reference (the list
        // row pill binds the variant via prop).
        $variantPropRefs = preg_match_all(
            '#<Ui(?:Status)?Badge\b[^>]*\bvariant=["\']#',
            $src
        );
        $this->assertGreaterThanOrEqual(
            1,
            $variantPropRefs,
            sprintf(
                '%s MUST bind the status-badge variant via `<UiBadge :variant="...">` '
                . '(AMB-01-008). Found %d variant-bound reference(s); expected at least 1.',
                $path,
                $variantPropRefs
            )
        );

        // NEGATIVE: zero `bg-success-100` legacy alias matches.
        $this->assertSame(
            0,
            preg_match('#(?<![\w-])bg-success-100(?![\w-])#', $src),
            sprintf(
                '%s MUST NOT keep the legacy `bg-success-100` status-pill alias '
                . '(AMB-01-008 / DLR-R-009). Found a match.',
                $path
            )
        );

        // NEGATIVE: zero `bg-warning-100` legacy alias matches.
        $this->assertSame(
            0,
            preg_match('#(?<![\w-])bg-warning-100(?![\w-])#', $src),
            sprintf(
                '%s MUST NOT keep the legacy `bg-warning-100` status-pill alias '
                . '(AMB-01-008 / DLR-R-009). Found a match.',
                $path
            )
        );

        // NEGATIVE: zero `bg-theme-surface text-theme-primary` literal
        // (the `inactive` status returned this string in the legacy
        // `getStatusColor` helper).
        $this->assertSame(
            0,
            preg_match('#(?<![\w-])bg-theme-surface\s+text-theme-primary(?![\w-])#', $src),
            sprintf(
                '%s MUST NOT keep the legacy `bg-theme-surface text-theme-primary` '
                . 'status-pill alias (AMB-01-008 / DLR-R-009). Found a match.',
                $path
            )
        );

        // NEGATIVE: zero `text-success-700` legacy alias matches.
        $this->assertSame(
            0,
            preg_match('#(?<![\w-])text-success-700(?![\w-])#', $src),
            sprintf(
                '%s MUST NOT keep the legacy `text-success-700` status-pill alias '
                . '(AMB-01-008 / DLR-R-009). Found a match.',
                $path
            )
        );

        // NEGATIVE: zero `text-warning-700` legacy alias matches.
        $this->assertSame(
            0,
            preg_match('#(?<![\w-])text-warning-700(?![\w-])#', $src),
            sprintf(
                '%s MUST NOT keep the legacy `text-warning-700` status-pill alias '
                . '(AMB-01-008 / DLR-R-009). Found a match.',
                $path
            )
        );
    }

    /**
     * DLR-AMB-005 EXCEPTION #1 + AMB-02-001 — the `<script>` helper
     * `getStatusColor` MUST be renamed to `getStatusVariant` and its body
     * MUST return variant tokens (`success | neutral | warning`) instead of
     * legacy colour-class strings.
     *
     * POSITIVE rule: `getStatusVariant` reference present in the `<script>`
     * block (the rename is in place).
     *
     * NEGATIVE rule: `getStatusColor` reference absent (the legacy name is
     * gone).
     *
     * POSITIVE rule: the helper body returns `'success'`, `'neutral'`, and
     * `'warning'` tokens for the dental-chair status enum
     * (`active | inactive | maintenance`).
     */
    public function test_get_status_variant_returns_tokens(): void
    {
        $path = self::listPagePath();
        $src = self::readSource($path);
        $this->assertNotNull($src, sprintf('%s must be readable.', $path));

        // POSITIVE: `getStatusVariant` reference present in the file.
        $this->assertTrue(
            (bool) preg_match('#\bgetStatusVariant\b#', $src),
            sprintf(
                '%s MUST declare the `getStatusVariant` helper (DLR-AMB-005 EXCEPTION #1 / '
                . 'AMB-02-001). The legacy `getStatusColor` returning colour-class strings '
                . 'MUST be renamed to `getStatusVariant` returning variant tokens.',
                $path
            )
        );

        // NEGATIVE: zero `getStatusColor` references (the legacy name is gone).
        $this->assertSame(
            0,
            preg_match('#\bgetStatusColor\b#', $src),
            sprintf(
                '%s MUST NOT keep the legacy `getStatusColor` helper name '
                . '(DLR-AMB-005 EXCEPTION #1 / AMB-02-001). Rename to `getStatusVariant`.',
                $path
            )
        );

        // POSITIVE: the helper maps `active` to `'success'` and `inactive`
        // to `'neutral'` and `maintenance` to `'warning'`. We assert each
        // canonical mapping is present in the helper body by looking for
        // the pattern `active: 'success'` / `inactive: 'neutral'` /
        // `maintenance: 'warning'`. Tolerant of whitespace and arrow-fn
        // placement.
        $this->assertTrue(
            (bool) preg_match(
                '#active\s*:\s*[\'"]success[\'"]#',
                $src
            ),
            sprintf(
                '%s MUST map `active` → `success` in the `getStatusVariant` helper body '
                . '(AMB-01-008 / DLR-AMB-005 EXCEPTION #1).',
                $path
            )
        );

        $this->assertTrue(
            (bool) preg_match(
                '#inactive\s*:\s*[\'"]neutral[\'"]#',
                $src
            ),
            sprintf(
                '%s MUST map `inactive` → `neutral` in the `getStatusVariant` helper body '
                . '(AMB-01-008 / DLR-AMB-005 EXCEPTION #1).',
                $path
            )
        );

        $this->assertTrue(
            (bool) preg_match(
                '#maintenance\s*:\s*[\'"]warning[\'"]#',
                $src
            ),
            sprintf(
                '%s MUST map `maintenance` → `warning` in the `getStatusVariant` helper body '
                . '(AMB-01-008 / DLR-AMB-005 EXCEPTION #1).',
                $path
            )
        );
    }
}
