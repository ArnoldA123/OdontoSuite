<?php

namespace Tests\Unit\DesignSystem;

/**
 * PR-pagos-04 — PaymentModalAppShellTest.
 *
 * Per-module structure test for the 2 payment modal `.vue` files in
 * `resources/js/modules/cash-register/components/`:
 *
 *   - PaymentModal.vue       — manual + Mercado Pago tabs; patient/concept/amount
 *   - MercadoPagoCheckout.vue — Bricks container; success/error/processing states
 *
 * Inherited rules (from {@see ModuleAppShellTestCase}): DLR-R-001 canvas
 * surface, DLR-R-002 no `border-theme`, DLR-R-004 no legacy focus-ring
 * aliases, DLR-R-021 no `<style scoped>`.
 *
 * PR-pagos-04-only rules asserted here:
 *
 *   - PAGOS-MOD-001   UiModal + UiTabs + UiButton + UiStatusBadge primitives
 *                     (the PaymentModal swap covers tab strip + status pills;
 *                     MercadoPagoCheckout covers processing-state badge).
 *   - PAGOS-MNY-002   PaymentModal's local `formatCurrency` helper replaced
 *                     by the canonical helper from `useFormatters`.
 *   - PAGOS-MOD-002   PaymentModal's MercadoPago `<UiTabs>` tab is `:disabled`
 *                     when the amount is zero (per design §3.1 disabled-when-
 *                     amount-zero rule).
 *   - PAGOS-CON-001   `<script>` block of PaymentModal.vue MUST remain
 *                     byte-for-byte unchanged (the 401 redirect code path is
 *                     the regression guard for UXF-021). Verified indirectly
 *                     by `PaymentModal401RedirectTest`; this test only checks
 *                     the NO `<Teleport to="body">` + tab-strip primitives.
 *
 * The 401 redirect is pinned by `tests/Unit/Composables/PaymentModal401RedirectTest.php`
 * (UXF-021). This test stays focused on the surface-level primitives + chrome
 * tokens.
 */
class PaymentModalAppShellTest extends ModuleAppShellTestCase
{
    /**
     * PR-pagos-04 scope: 2 payment modal files.
     *
     * @return array<int, string>
     */
    protected static function polishedFiles(): array
    {
        $root = dirname(__DIR__, 3);

        return [
            $root . '/resources/js/modules/cash-register/components/PaymentModal.vue',
            $root . '/resources/js/modules/cash-register/components/MercadoPagoCheckout.vue',
        ];
    }

    /**
     * PAGOS-MOD-001 + PAGOS-MNY-002 combined: Ui primitives adopted; no
     * local `formatCurrency` helper (must import from `useFormatters`).
     *
     * The PR-pagos-04 scope includes BOTH PaymentModal and MercadoPagoCheckout.
     * PaymentModal is the heavy file (Manual + Mercado Pago tab strip +
     * submit; status badge for the gateway pill). MercadoPagoCheckout adopts
     * UiButton (already present) + UiStatusBadge variant="info" for the
     * processing state (per design §3.1).
     *
     * @dataProvider polishedFileProvider
     */
    public function test_payment_modal_combined_primitive_and_format_rules(string $path): void
    {
        $src = self::readSource($path);
        $this->assertNotNull($src, sprintf('%s must be readable.', $path));

        // No hand-built <Teleport to="body"> in either file (PaymentModal
        // uses the canonical <Modal> primitive; MercadoPagoCheckout is a
        // sibling component consumed by PaymentModal — it MUST also stay
        // teleport-free per PAGOS-MOD-001).
        $this->assertSame(
            0,
            preg_match('/<Teleport\b[^>]*\bto\s*=\s*["\']body["\']/i', $src),
            sprintf('%s MUST NOT contain `<Teleport to="body">` (PAGOS-MOD-001).', $path)
        );

        // <UiButton> OR `import UiButton` (covers both primitive-as-tag and
        // import-only reference forms).
        $this->assertTrue(
            (bool) preg_match('/<UiButton\b/', $src)
                || (bool) preg_match(
                    "/import\s+(?:Ui)?Button\s+from\s+['\"](?:@\/|\.\.\/\.\.\/)?(?:components\/ui\/)?(?:Ui)?Button\.vue['\"]/",
                    $src
                ),
            sprintf('%s MUST consume `<UiButton>` (PAGOS-MOD-001).', $path)
        );

        // <UiStatusBadge> OR `import UiStatusBadge` (the MercadoPagoCheckout
        // adopts <UiStatusBadge variant="info"> for the processing state;
        // PaymentModal adopts it for the MercadoPago-tab gateway pill).
        $this->assertTrue(
            (bool) preg_match('/<UiStatusBadge\b/', $src)
                || (bool) preg_match(
                    "/import\s+(?:Ui)?StatusBadge\s+from\s+['\"](?:@\/|\.\.\/\.\.\/)?(?:components\/ui\/)?(?:Ui)?StatusBadge\.vue['\"]/",
                    $src
                ),
            sprintf('%s MUST consume `<UiStatusBadge>` (PAGOS-MOD-001).', $path)
        );

        // formatCurrency MUST be imported from useFormatters (PAGOS-MNY-002).
        // Some components may use `formatPENLabel` instead (the backwards-
        // compatible alias from PR-pagos-01). At minimum the canonical
        // helper must be referenced.
        $hasFormatCurrency = (bool) preg_match(
            "/(?:formatCurrency|formatPENLabel)\b/",
            $src
        );
        $this->assertTrue(
            $hasFormatCurrency,
            sprintf('%s MUST reference the canonical `formatCurrency` / `formatPENLabel` helper from `useFormatters` (PAGOS-MNY-002).', $path)
        );

        $hasFormatCurrencyImport = (bool) preg_match(
            "/import\s*\{[^}]*(?:formatCurrency|formatPENLabel)[^}]*\}\s*from\s*['\"](?:@\/composables\/useFormatters|\.\.\/\.\.\/composables\/useFormatters|\.\.\/composables\/useFormatters|\.\.\/\.\.\/\.\.\/composables\/useFormatters)['\"]/",
            $src
        );
        $this->assertTrue(
            $hasFormatCurrencyImport,
            sprintf('%s MUST import `formatCurrency` (or its `formatPENLabel` alias) from `useFormatters` (PAGOS-MNY-002).', $path)
        );
    }

    /**
     * PAGOS-MOD-001 (PaymentModal only): the tab strip MUST use the
     * `<UiTabs>` primitive. There MUST NOT be a raw `<button class="...">`
     * for the manual/MP toggle (the legacy raw `<button>` tab strip is
     * forbidden by design §3.1).
     *
     * MercadoPagoCheckout is NOT a tab strip — it renders the Bricks
     * container + state pills inside an already-selected tab. It is
     * exempted from this assertion.
     */
    public function test_payment_modal_uses_ui_tabs_for_tab_strip(): void
    {
        $path = dirname(__DIR__, 3) . '/resources/js/modules/cash-register/components/PaymentModal.vue';
        $src = self::readSource($path);
        $this->assertNotNull($src, sprintf('%s must be readable.', $path));

        // Positive: UiTabs primitive OR its import.
        $this->assertTrue(
            (bool) preg_match('/<UiTabs\b/', $src)
                || (bool) preg_match(
                    "/import\s+(?:Ui)?Tabs\s+from\s+['\"](?:@\/components\/ui\/Tabs\.vue|\.\.\/\.\.\/components\/ui\/Tabs\.vue)/",
                    $src
                ),
            sprintf('%s MUST consume `<UiTabs>` for the Manual / Mercado Pago tab strip (PAGOS-MOD-001).', $path)
        );

        // Negative: no raw `<button class="...">` for the tab strip. We
        // tolerate other `<button>` elements (Cancel/Submit buttons) by
        // scoping the regex to the buttons that carry the legacy
        // text-accent + border-accent active-state marker OR the
        // border-b border-theme marker (the legacy tab strip pattern).
        $hasLegacyTabButton = preg_match(
            '/<button\b[^>]*\bclass\s*=[^>]*\b(?:border-b-2|border-theme)[^>]*>/i',
            $src
        );
        $this->assertSame(
            0,
            $hasLegacyTabButton,
            sprintf('%s MUST NOT contain a raw `<button class="...border-b-2... border-theme...">` legacy tab strip (PAGOS-MOD-001).', $path)
        );
    }

    /**
     * Design §3.1 / SPEC PAGOS-MOD-002: the MercadoPago `<UiTabs>` tab is
     * `:disabled` when `amount <= 0` (the "Ingrese monto" hint badge
     * appears below the tab strip in that state). We assert the disabled
     * prop binding covers the amount zero case.
     */
    public function test_payment_modal_mercadopago_tab_disabled_when_amount_zero(): void
    {
        $path = dirname(__DIR__, 3) . '/resources/js/modules/cash-register/components/PaymentModal.vue';
        $src = self::readSource($path);
        $this->assertNotNull($src, sprintf('%s must be readable.', $path));

        // POSITIVE: either the MercadoPago tab has `:disabled="..."` that
        // covers the amount-zero case, OR the tab definition's computed
        // `disabled` flag references `amount`. We accept either the `<UiTabs
        // :tabs="manualTabs">` shape with a computed array OR an inline ternary
        // `:disabled="amount <= 0 || ..."`.
        $hasDisabledBinding = (bool) preg_match(
            '/(?::disabled\s*=\s*["\'][^"\']*amount[^"\']*<=?\s*0[^"\']*["\']|\bdisabled\s*:\s*(?:\([^)]*formData[^)]*\)|formData\.value\.amount|amount)\s*<=?\s*0|amount\s*<=\s*0\s*\?)/s',
            $src
        );
        $this->assertTrue(
            $hasDisabledBinding,
            sprintf(
                '%s MUST declare the MercadoPago tab as `:disabled="amount <= 0 ..."` (or a computed flag referencing amount) per design §3.1 (PR-pagos-04).',
                $path
            )
        );

        // POSITIVE: the "Ingrese monto" hint badge appears somewhere in
        // the template (the visible cue that the tab is gated).
        $hasHintBadge = (bool) preg_match(
            '/Ingrese\s+monto/i',
            $src
        );
        $this->assertTrue(
            $hasHintBadge,
            sprintf(
                '%s MUST show the "Ingrese monto" hint badge when the MercadoPago tab is disabled (PR-pagos-04 §T-04.2).',
                $path
            )
        );
    }

    /**
     * DLR-R-009 (PaymentModal): no legacy `bg-success/warning/error-100`
     * status-pill class (replaced by `<UiStatusBadge>`). MercadoPagoCheckout
     * is exempted because its success state uses a one-off icon background
     * (the legacy `bg-success-100` circle) — that is a decorative icon
     * surface, not a status pill (and remains unchanged because the
     * design §8 risk #1 keeps the visual motion minimal).
     */
    public function test_payment_modal_no_legacy_status_pill_classes(): void
    {
        $path = dirname(__DIR__, 3) . '/resources/js/modules/cash-register/components/PaymentModal.vue';
        $src = self::readSource($path);
        $this->assertNotNull($src, sprintf('%s must be readable.', $path));

        $cleaned = self::stripScriptForClassScan($src);
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

    /**
     * DLR-R-002 + DLR-R-004 (PaymentModal): no `border-theme` literal AND no
     * `focus:ring-primary-500` / `focus:border-accent` focus-ring aliases.
     * Inherited from {@see ModuleAppShellTestCase::test_no_legacy_border_theme_literal}
     * + `test_no_legacy_focus_ring_alias`; this is the same rule, parametrised
     * for the 2-file PaymentModal scope (a focused re-assertion that fires
     * quickly and pinpoints the file if a regression sneaks in).
     *
     * @dataProvider polishedFileProvider
     */
    public function test_payment_modal_files_no_legacy_chrome(string $path): void
    {
        $src = self::readSource($path);
        $this->assertNotNull($src, sprintf('%s must be readable.', $path));

        $cleaned = self::stripScriptForClassScan($src);

        // No legacy border-theme literal (DLR-R-002).
        $this->assertDoesNotMatchRegularExpression(
            '/(?<![\w-])border-theme(?![\w-])/',
            $cleaned,
            sprintf(
                '%s MUST NOT contain the legacy `border-theme` literal (DLR-R-002). '
                    . 'Use the `border-hairline` / `--color-hairline` token instead.',
                $path
            )
        );

        // No legacy focus-ring aliases (DLR-R-004).
        $this->assertDoesNotMatchRegularExpression(
            '/(?<![\w-])focus:ring-primary-500(?![\w-])|(?<![\w-])focus:border-accent(?![\w-])/',
            $cleaned,
            sprintf(
                '%s MUST NOT contain legacy focus-ring aliases (`focus:ring-primary-500` or `focus:border-accent`) per DLR-R-004.',
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
