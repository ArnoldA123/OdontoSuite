<?php

namespace Tests\Unit\DesignSystem;

/**
 * PR-pagos-02b — CashRegisterAppShellTest.
 *
 * Per-module structure test for the Caja (cash-register) module. PR-pagos-02b
 * ships with the 5 list + report `.vue` files in scope:
 *
 *   - TransactionList.vue, MovementList.vue, SessionList.vue (list views, PR-pagos-02a)
 *   - CashReports.vue, PendingPaymentsList.vue (report views, PR-pagos-02b)
 *
 * Modal files will be appended to `polishedFiles()` in PR-pagos-03/04.
 *
 * Inherited rules (from {@see ModuleAppShellTestCase}): DLR-R-001 canvas
 * surface, DLR-R-002 no `border-theme`, DLR-R-004 no legacy focus-ring
 * aliases, DLR-R-021 no `<style scoped>`.
 *
 * PR-pagos-02-only rules asserted here:
 *   - PAGOS-A11Y-001 numeric columns expose `scope="col"` + currency
 *     `aria-label`, plus `tabular-nums`
 *   - PAGOS-MNY-001   no raw `<input v-model="amount*">` outside CurrencyInput
 *   - PAGOS-MOD-001   no legacy `bg-success/warning/error-100` status pills
 *
 * formatCurrency canonicalization (PAGOS-MNY-002) for CashReports.vue is
 * asserted in `FormatPENLabelTest::test_format_currency_exists_at_exactly_one_location`
 * (the helper path constant `PR_PAGOS_01_SCOPE_REL_PATHS` includes
 * CashReports.vue; the previous PR-pagos-02a regression is closed by
 * PR-pagos-02b re-adding the canonical import).
 */
class CashRegisterAppShellTest extends ModuleAppShellTestCase
{
    /**
     * PR-pagos-02b scope: 5 list + report files. PR-pagos-03/04 append the
     * 6 modal files (PaymentModal, MercadoPagoCheckout, TransactionModal,
     * MovementModal, OpenCashModal, CloseCashModal).
     *
     * @return array<int, string>
     */
    protected static function polishedFiles(): array
    {
        $root = dirname(__DIR__, 3);

        return [
            $root . '/resources/js/modules/cash-register/components/TransactionList.vue',
            $root . '/resources/js/modules/cash-register/components/MovementList.vue',
            $root . '/resources/js/modules/cash-register/components/SessionList.vue',
            $root . '/resources/js/modules/cash-register/components/CashReports.vue',
            $root . '/resources/js/modules/cash-register/components/PendingPaymentsList.vue',
        ];
    }

    /**
     * @dataProvider polishedFileProvider
     */
    public function test_list_files_no_local_intl_pen_format(string $path): void
    {
        $source = self::readSource($path);
        $this->assertNotNull($source, sprintf('%s must exist and be readable.', $path));

        $cleaned = self::stripScriptForClassScan($source);
        $violates = preg_match(
            "/Intl\\.NumberFormat\\s*\\(\\s*['\"]es-PE['\"]\\s*,\\s*\\{[^}]*currency:\\s*['\"]PEN['\"]/s",
            $cleaned
        );

        $this->assertSame(
            0,
            $violates,
            sprintf(
                '%s MUST NOT redeclare `Intl.NumberFormat(\'es-PE\', { currency: \'PEN\' })` '
                    . '(PAGOS-MNY-002). Import `formatCurrency` from `useFormatters`.',
                $path
            )
        );
    }

    /**
     * @dataProvider polishedFileProvider
     */
    public function test_list_files_no_raw_money_input(string $path): void
    {
        $source = self::readSource($path);
        $this->assertNotNull($source, sprintf('%s must exist and be readable.', $path));

        $pattern = '/<input\b[^>]*\bv-model\s*=\s*["\'][^"\']*amount[^"\']*["\']/i';
        $violates = preg_match($pattern, $source);

        $this->assertSame(
            0,
            $violates,
            sprintf(
                '%s MUST NOT contain raw `<input v-model="amount*">` (PAGOS-MNY-001). '
                    . 'Use `<CurrencyInput>` for money capture.',
                $path
            )
        );
    }

    /**
     * @dataProvider polishedFileProvider
     */
    public function test_list_files_tabular_nums_scope_and_aria(string $path): void
    {
        $source = self::readSource($path);
        $this->assertNotNull($source, sprintf('%s must exist and be readable.', $path));

        $hasTable = (bool) preg_match('/<table\b/', $source);
        if (!$hasTable) {
            $this->assertTrue(true, sprintf('%s has no <table> — nothing to assert.', $path));
            return;
        }

        $hasTabularNums = (bool) preg_match(
            '/(?<![\w-])(?:tabular-nums|font-feature-settings\s*:\s*var\(--font-features-tabular-nums\))(?![\w-])/',
            $source
        );
        $this->assertTrue(
            $hasTabularNums,
            sprintf(
                '%s renders a <table> and MUST apply `tabular-nums` on numeric columns (DLR-R-007).',
                $path
            )
        );

        $hasScope = (bool) preg_match('/<th\b[^>]*\bscope\s*=\s*["\']col["\']/', $source);
        $this->assertTrue(
            $hasScope,
            sprintf(
                '%s renders a <table> and MUST declare `scope="col"` on at least one <th> (PAGOS-A11Y-001).',
                $path
            )
        );

        $hasAriaLabel = (bool) preg_match('/<td\b[^>]*aria-label[^>]*soles/is', $source);
        $this->assertTrue(
            $hasAriaLabel,
            sprintf(
                '%s renders a <table> and MUST declare `aria-label="... soles ..."` on numeric '
                    . '<td> cells (PAGOS-A11Y-001).',
                $path
            )
        );
    }

    /**
     * @dataProvider polishedFileProvider
     */
    public function test_list_files_no_legacy_status_pill_classes(string $path): void
    {
        $source = self::readSource($path);
        $this->assertNotNull($source, sprintf('%s must exist and be readable.', $path));

        $cleaned = self::stripScriptForClassScan($source);
        $violates = preg_match_all(
            '/(?<![\w-])(?:bg-success-100|bg-warning-100|bg-error-100)(?![\w-])/',
            $cleaned
        );

        $this->assertSame(
            0,
            $violates,
            sprintf(
                '%s MUST NOT contain legacy status-pill classes (PAGOS-MOD-001). '
                    . 'Use `<UiStatusBadge variant="success|warning|error|neutral">` instead.',
                $path
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

    /**
     * Strip <script> blocks' string + comment noise so class regex patterns
     * match against actual class strings, not pattern examples embedded in
     * JS literals.
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