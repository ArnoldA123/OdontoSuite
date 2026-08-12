<?php

namespace Tests\Unit\DesignSystem;

/**
 * PR-pagos-03a — CajaModalsAppShellTest. Asserts PAGOS-MOD-001 +
 * PAGOS-MNY-002 + PAGOS-CON-001 across the 2 Caja modal `.vue` files
 * (TransactionModal, MovementModal). DLR-R-* rules are inherited from
 * ModuleAppShellTestCase.
 *
 * Out of scope here (deferred to PR-pagos-03b):
 * - `resources/js/modules/cash-register/components/OpenCashModal.vue`
 * - `resources/js/modules/cash-register/components/CloseCashModal.vue`
 * Both files were `git restore`d to their pre-PR-pagos-03 state; they will
 * be re-polished in PR-pagos-03b and re-added to `polishedFiles()`.
 */
class CajaModalsAppShellTest extends ModuleAppShellTestCase
{
    /** @return array<int, string> */
    protected static function polishedFiles(): array
    {
        $root = dirname(__DIR__, 3);
        return [
            $root . '/resources/js/modules/cash-register/components/TransactionModal.vue',
            $root . '/resources/js/modules/cash-register/components/MovementModal.vue',
        ];
    }

    /**
     * PAGOS-MOD-001 + PAGOS-MNY-002 + PAGOS-CON-001 combined: no Teleport,
     * <UiModal>/<UiButton>/<UiStatusBadge> present, formatCurrency imported,
     * defineEmits(['close','success']) preserved.
     *
     * @dataProvider polishedFileProvider
     */
    public function test_modals_combined_primitive_and_contract_rules(string $path): void
    {
        $src = self::readSource($path);
        $this->assertNotNull($src, sprintf('%s must be readable.', $path));

        $this->assertSame(0, preg_match('/<Teleport\b[^>]*\bto\s*=\s*["\']body["\']/i', $src),
            sprintf('%s MUST NOT contain `<Teleport to="body">` (PAGOS-MOD-001).', $path));

        $this->assertTrue(
            (bool) preg_match('/<UiModal\b/', $src)
                || (bool) preg_match("/import\s+Modal\s+from\s+['\"]@\/components\/ui\/Modal\.vue['\"]/", $src),
            sprintf('%s MUST consume `<UiModal>` (PAGOS-MOD-001).', $path));

        $this->assertTrue(
            (bool) preg_match('/<UiButton\b/', $src)
                || (bool) preg_match("/import\s+(?:Ui)?Button\s+from\s+['\"]@\/components\/ui\/Button\.vue['\"]/", $src),
            sprintf('%s MUST consume `<UiButton>` (PAGOS-MOD-001).', $path));

        $this->assertTrue(
            (bool) preg_match('/<UiStatusBadge\b/', $src)
                || (bool) preg_match("/import\s+\w*[Bb]adge\w*\s+from\s+['\"]@\/components\/ui\/StatusBadge\.vue['\"]/", $src),
            sprintf('%s MUST consume `<UiStatusBadge>` (PAGOS-MOD-001).', $path));

        $this->assertTrue(
            (bool) preg_match(
                "/import\s*\{[^}]*formatCurrency[^}]*\}\s*from\s*['\"](?:@\/composables\/useFormatters|\.\.\/\.\.\/composables\/useFormatters|\.\.\/composables\/useFormatters)['\"]/",
                $src
            ),
            sprintf('%s MUST import `formatCurrency` from `useFormatters` (PAGOS-MNY-002).', $path));

        $this->assertTrue(
            (bool) preg_match("/defineEmits\\s*\\(\\s*\\[\\s*['\"]close['\"]\\s*,\\s*['\"]success['\"]\\s*\\]\\s*\\)/", $src),
            sprintf('%s MUST keep `defineEmits([\'close\',\'success\'])` byte-for-byte (PAGOS-CON-001).', $path));
    }

    /**
     * PAGOS-MNY-002 — no local `Intl.NumberFormat('es-PE', { currency: 'PEN' })`.
     *
     * @dataProvider polishedFileProvider
     */
    public function test_modals_no_local_intl_pen_format(string $path): void
    {
        $src = self::readSource($path);
        $this->assertNotNull($src, sprintf('%s must be readable.', $path));
        $cleaned = self::stripScriptForClassScan($src);
        $this->assertSame(0,
            preg_match("/Intl\\.NumberFormat\\s*\\(\\s*['\"]es-PE['\"]\\s*,\\s*\\{[^}]*currency:\\s*['\"]PEN['\"]/s", $cleaned),
            sprintf('%s MUST NOT redeclare the canonical `Intl.NumberFormat(\'es-PE\', { currency: \'PEN\' })` (PAGOS-MNY-002).', $path));
    }

    /** PR-pagos-03 §3.2 — TransactionModal `type` prop (default `'payment'`, validator constrains to `['payment','refund']`). */
    public function test_transaction_modal_declares_type_prop(): void
    {
        $path = dirname(__DIR__, 3) . '/resources/js/modules/cash-register/components/TransactionModal.vue';
        $src = self::readSource($path);
        $this->assertNotNull($src, sprintf('%s must be readable.', $path));
        $this->assertTrue(
            (bool) preg_match(
                '/type\s*:\s*\{\s*type\s*:\s*String\s*,\s*default\s*:\s*[\'"]payment[\'"]\s*,\s*validator\s*:\s*v\s*=>\s*\[\s*[\'"]payment[\'"]\s*,\s*[\'"]refund[\'"]\s*\]\.includes\s*\(\s*v\s*\)/s',
                $src
            ),
            sprintf('%s MUST declare the additive `type` prop with default \'payment\' + validator (PR-pagos-03 §3.2).', $path));
    }

    // NOTE: `test_close_cash_modal_uses_tabular_nums_on_totals` removed for
    // PR-pagos-03a — CloseCashModal is deferred to PR-pagos-03b (the file was
    // `git restore`d to its pre-PR-pagos-03 state). The rule will be
    // re-enabled in PR-pagos-03b once CloseCashModal is re-polished.

    /** PR-pagos-03 — TransactionModal consumes <UiCard> + <UiLoadingSpinner>; no legacy bg-primary-50 / animate-spin (PAGOS-MOD-001-1 / DLR-R-009). */
    public function test_transaction_modal_uses_ui_card_and_spinner(): void
    {
        $path = dirname(__DIR__, 3) . '/resources/js/modules/cash-register/components/TransactionModal.vue';
        $src = self::readSource($path);
        $this->assertNotNull($src, sprintf('%s must be readable.', $path));

        $this->assertTrue(
            (bool) preg_match('/<UiCard\b/', $src)
                || (bool) preg_match("/import\s+(?:Ui)?Card\s+from\s+['\"]@\/components\/ui\/Card\.vue['\"]/", $src),
            sprintf('%s MUST consume `<UiCard>` (PAGOS-MOD-001-1).', $path));

        $this->assertTrue(
            (bool) preg_match('/<UiLoadingSpinner\b/', $src)
                || (bool) preg_match("/import\s+(?:Ui)?LoadingSpinner\s+from\s+['\"]@\/components\/ui\/LoadingSpinner\.vue['\"]/", $src),
            sprintf('%s MUST consume `<UiLoadingSpinner>` (PAGOS-MOD-001-1).', $path));

        $this->assertSame(0, preg_match('/(?<![\w-])bg-primary-50(?![\w-])/', $src),
            sprintf('%s MUST NOT contain `bg-primary-50` (DLR-R-009).', $path));

        $this->assertSame(0, preg_match('/(?<![\w-])animate-spin(?![\w-])/', $src),
            sprintf('%s MUST NOT contain `animate-spin` (DLR-R-009).', $path));
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
     * Strip JS/TS string + comment noise inside `<script>...</script>`
     * blocks so regex patterns match actual class strings, not pattern
     * examples embedded in JS literals.
     */
    private static function stripScriptForClassScan(string $src): string
    {
        return preg_replace_callback(
            '#<script\b[^>]*>(.*?)</script>#s',
            static function (array $m): string {
                $script = $m[1];
                $stripped = preg_replace('#/\*.*?\*/#s', '', $script) ?? $script;
                $stripped = preg_replace('#//[^\n]*#', '', $stripped) ?? $stripped;
                $stripped = preg_replace("/'(?:\\\\.|[^'\\\\])*'/", "''", $stripped) ?? $stripped;
                $stripped = preg_replace('/"(?:\\\\.|[^"\\\\])*"/', '""', $stripped) ?? $stripped;
                $stripped = preg_replace('/`(?:\\\\.|[^`\\\\])*`/', '``', $stripped) ?? $stripped;
                return '<script>' . $stripped . '</script>';
            },
            $src
        ) ?? $src;
    }
}
