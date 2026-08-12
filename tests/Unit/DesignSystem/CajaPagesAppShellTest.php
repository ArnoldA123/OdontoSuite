<?php

namespace Tests\Unit\DesignSystem;

/**
 * PR-pagos-05a/b — CajaPagesAppShellTest. Pins the Apple-language surface for
 * the 5 PAGOS pages (Caja hub + ready-to-bill + quotations + admin CRUD list)
 * plus the payment-method admin form modal:
 *
 *   - `cash-register/CashRegisterPage.vue`                              (Caja hub)
 *   - `cash-register/ReadyToBillPage.vue`                              (PR-pagos-05b)
 *   - `quotations/QuotationsPage.vue`                                  (PR-pagos-05b)
 *   - `settings/payment-methods/PaymentMethodsPage.vue`                (admin CRUD list)
 *   - `settings/payment-methods/PaymentMethodFormModal.vue`            (admin CRUD form)
 *
 * DLR-R-001/002/004/021 are inherited from ModuleAppShellTestCase. The rules
 * below add the PAGOS-specific deltas: legacy-chrome removal (DLR-R-009),
 * canonical money formatter (PAGOS-MNY-002), hub primitive adoption
 * (PAGOS-MOD-001), admin-CRUD table semantics (PAGOS-A11Y-001), the
 * `gateway_config` redaction rule (PAGOS-RED-001), the ReadyToBillPage
 * modal migration (PAGOS-MOD-001 / PAGOS-CON-001-1), and the QuotationsPage
 * form-primitive adoption (PAGOS-MOD-001).
 */
class CajaPagesAppShellTest extends ModuleAppShellTestCase
{
    private const CASH_REGISTER_PAGE = '/resources/js/modules/cash-register/CashRegisterPage.vue';
    private const READY_TO_BILL_PAGE = '/resources/js/modules/cash-register/ReadyToBillPage.vue';
    private const QUOTATIONS_PAGE = '/resources/js/modules/quotations/QuotationsPage.vue';
    private const PAYMENT_METHODS_PAGE = '/resources/js/modules/settings/payment-methods/PaymentMethodsPage.vue';
    private const PAYMENT_METHOD_FORM_MODAL = '/resources/js/modules/settings/payment-methods/PaymentMethodFormModal.vue';

    /** @return array<int, string> */
    protected static function polishedFiles(): array
    {
        $root = dirname(__DIR__, 3);

        return [
            $root . self::CASH_REGISTER_PAGE,
            $root . self::READY_TO_BILL_PAGE,
            $root . self::QUOTATIONS_PAGE,
            $root . self::PAYMENT_METHODS_PAGE,
            $root . self::PAYMENT_METHOD_FORM_MODAL,
        ];
    }

    /**
     * DLR-R-009 — the legacy chrome of the pre-rollout surface is gone:
     * `hover-lift`, gradient washes, the legacy success/error/accent aliases,
     * raw Tailwind reds, and every `<style>` block (which is where the
     * `hover-lift` keyframes and the global `* { transition }` rule lived).
     * `<UiButton>` is the only button primitive left.
     *
     * @dataProvider polishedFileProvider
     */
    public function test_pages_apple_language_surface(string $path): void
    {
        $src = self::readSource($path);
        $this->assertNotNull($src, sprintf('%s must be readable.', $path));

        $this->assertSame(0, preg_match('/(?<![\w-])hover-lift(?![\w-])/', $src),
            sprintf('%s MUST NOT contain the legacy `hover-lift` class (DLR-R-009).', $path));

        $this->assertSame(0, preg_match('/(?<![\w-])bg-gradient-[\w-]+/', $src),
            sprintf('%s MUST NOT contain a legacy gradient wash (DLR-R-009).', $path));

        $this->assertSame(0, preg_match('/<style\b/', $src),
            sprintf('%s MUST NOT contain a `<style>` block — the global `* { transition }` '
                . 'rule and the `hover-lift` keyframes belong to the token CSS (DLR-R-021).', $path));

        foreach (self::forbiddenLegacyAliases() as $alias) {
            $this->assertSame(0, preg_match('/(?<![\w-])' . preg_quote($alias, '/') . '(?![\w-])/', $src),
                sprintf('%s MUST NOT contain the legacy alias `%s` — use the systemBlue / systemGreen / '
                    . 'systemRed / systemYellow ramp instead (DLR-R-009).', $path, $alias));
        }

        $this->assertTrue(
            (bool) preg_match('/<UiButton\b/', $src),
            sprintf('%s MUST consume `<UiButton>` as its only button primitive (PAGOS-MOD-001).', $path));
    }

    /**
     * PAGOS-MNY-002 — no file redeclares the canonical PEN formatter, and any
     * file that renders `formatCurrency(...)` imports it from `useFormatters`.
     *
     * @dataProvider polishedFileProvider
     */
    public function test_pages_no_local_intl_pen_format(string $path): void
    {
        $src = self::readSource($path);
        $this->assertNotNull($src, sprintf('%s must be readable.', $path));

        $cleaned = self::stripScriptForClassScan($src);
        $this->assertSame(0,
            preg_match("/Intl\\.NumberFormat\\s*\\(\\s*['\"]es-PE['\"]\\s*,\\s*\\{[^}]*currency:\\s*['\"]PEN['\"]/s", $cleaned),
            sprintf('%s MUST NOT redeclare `Intl.NumberFormat(\'es-PE\', { currency: \'PEN\' })` (PAGOS-MNY-002).', $path));

        if (preg_match('/formatCurrency\s*\(/', $src) === 1) {
            $this->assertTrue(
                (bool) preg_match(
                    "/import\\s*\\{[^}]*formatCurrency[^}]*\\}\\s*from\\s*['\"](?:@\\/composables\\/useFormatters"
                        . "|(?:\\.\\.\\/)+composables\\/useFormatters)['\"]/",
                    $src
                ),
                sprintf('%s renders `formatCurrency(...)` and MUST import it from the canonical '
                    . '`useFormatters` module (PAGOS-MNY-002).', $path));
        } else {
            $this->assertTrue(true, sprintf('%s renders no money — nothing to assert.', $path));
        }
    }

    /**
     * PAGOS-MOD-001 — the Caja hub renders its tab strip through `<UiTabs>`,
     * its real-time totals through `<UiCard>`, and its session state through
     * `<UiStatusBadge>` (replacing the hardcoded green/red dot).
     */
    public function test_cash_register_page_hub_primitives(): void
    {
        $path = dirname(__DIR__, 3) . self::CASH_REGISTER_PAGE;
        $src = self::readSource($path);
        $this->assertNotNull($src, sprintf('%s must be readable.', $path));

        foreach (['UiTabs', 'UiCard', 'UiStatusBadge'] as $primitive) {
            $this->assertTrue(
                (bool) preg_match('/<' . $primitive . '\b/', $src),
                sprintf('%s MUST consume `<%s>` on the Caja hub (PAGOS-MOD-001).', $path, $primitive));
        }

        $this->assertSame(0, preg_match('/(?<![\w-])bg-(?:green|red)-500(?![\w-])/', $src),
            sprintf('%s MUST NOT hardcode the green/red session dot — `<UiStatusBadge>` owns '
                . 'the session state (PAGOS-MOD-001).', $path));

        $this->assertTrue(
            (bool) preg_match('/tabular-nums/', $src),
            sprintf('%s MUST render its real-time totals with `tabular-nums` (DLR-R-007).', $path));
    }

    /**
     * PAGOS-A11Y-001 + PAGOS-MOD-001 — the admin CRUD list is on the Apple
     * surface: `<UiStatusBadge>` replaces the legacy `<UiBadge>` pills, every
     * `<th>` carries `scope="col"`, and every rule/divider is a hairline.
     */
    public function test_payment_methods_page_admin_crud_surface(): void
    {
        $path = dirname(__DIR__, 3) . self::PAYMENT_METHODS_PAGE;
        $src = self::readSource($path);
        $this->assertNotNull($src, sprintf('%s must be readable.', $path));

        $this->assertTrue(
            (bool) preg_match('/<UiStatusBadge\b/', $src),
            sprintf('%s MUST consume `<UiStatusBadge>` for the Sistema/Custom + Activo/Inactivo '
                . 'pills (PAGOS-MOD-001).', $path));

        $this->assertSame(0, preg_match('/<UiBadge\b/', $src),
            sprintf('%s MUST NOT keep the legacy `<UiBadge>` pill (PAGOS-MOD-001).', $path));

        $headers = preg_match_all('/<th\b[^>]*>/', $src, $matches);
        $this->assertGreaterThan(0, $headers, sprintf('%s must render a table header.', $path));
        foreach ($matches[0] as $th) {
            $this->assertMatchesRegularExpression('/\bscope\s*=\s*"col"/', $th,
                sprintf('%s: every `<th>` MUST carry `scope="col"` (PAGOS-A11Y-001). Offender: %s', $path, $th));
        }

        $this->assertTrue(
            (bool) preg_match('/border-hairline/', $src),
            sprintf('%s MUST draw its table rules with the `border-hairline` token (DLR-R-002).', $path));
    }

    /**
     * PAGOS-RED-001 — the admin form marks the `gateway_config` credentials
     * block as redacted and never echoes the encrypted-at-rest blob into a
     * rendered text node.
     */
    public function test_gateway_config_redacted(): void
    {
        $path = dirname(__DIR__, 3) . self::PAYMENT_METHOD_FORM_MODAL;
        $src = self::readSource($path);
        $this->assertNotNull($src, sprintf('%s must be readable.', $path));

        $this->assertTrue(
            (bool) preg_match('/<div\b[^>]*\bdata-redacted\s*=\s*"true"[^>]*>/s', $src),
            sprintf('%s MUST mark the `gateway_config` wrapper with `data-redacted="true"` (PAGOS-RED-001).', $path));

        $this->assertSame(0, preg_match('/\{\{[^}]*gateway_config[^}]*\}\}/s', $src),
            sprintf('%s MUST NOT interpolate `gateway_config` into a rendered text node — the '
                . 'encrypted blob never reaches the DOM (PAGOS-RED-001).', $path));

        $this->assertSame(0, preg_match('/\bv-html\b/', $src),
            sprintf('%s MUST NOT use `v-html` on the redacted form (PAGOS-RED-001).', $path));

        $this->assertTrue(
            (bool) preg_match('/v-model\s*=\s*"gatewayConfig\.access_token"[^>]*\n?[^>]*type\s*=\s*"password"'
                . '|type\s*=\s*"password"[^>]*\n?[^>]*v-model\s*=\s*"gatewayConfig\.access_token"/s', $src),
            sprintf('%s MUST keep the access-token field masked (`type="password"`) (PAGOS-RED-001).', $path));
    }

    /**
     * PAGOS-MOD-001 — the ReadyToBillPage desglose modal is on the canonical
     * Apple-language surface: `<UiModal>` replaces the hand-built
     * `<Teleport to="body">` + `bg-black bg-opacity-60` wrapper, status pills
     * are `<UiStatusBadge>`s, and the action buttons run through `<UiButton>`.
     * The `previewOpen` / `closePreview` open/close contract is preserved
     * byte-for-byte (PAGOS-CON-001-1). The 401 redirect is owned by `useApi`
     * (per design §3.3), so the migration does not touch the redirect path.
     */
    public function test_ready_to_bill_modal_uses_ui_modal(): void
    {
        $path = dirname(__DIR__, 3) . self::READY_TO_BILL_PAGE;
        $src = self::readSource($path);
        $this->assertNotNull($src, sprintf('%s must be readable.', $path));

        $this->assertSame(0, preg_match('/<Teleport\b[^>]*\bto\s*=\s*["\']body["\']/', $src),
            sprintf('%s MUST NOT keep a hand-built `<Teleport to="body">` modal — '
                . 'the desglose modal MUST consume `<UiModal>` (PAGOS-MOD-001).', $path));

        $this->assertSame(0, preg_match('/(?<![\w-])bg-black\b(?:\s+bg-opacity-\d+)?/', $src),
            sprintf('%s MUST NOT keep the legacy `bg-black bg-opacity-*` backdrop — '
                . '`<UiModal>` owns the backdrop (PAGOS-MOD-001).', $path));

        $this->assertTrue(
            (bool) preg_match('/<UiModal\b/', $src),
            sprintf('%s MUST consume `<UiModal>` for the desglose modal (PAGOS-MOD-001).', $path));

        $this->assertTrue(
            (bool) preg_match('/<UiStatusBadge\b/', $src),
            sprintf('%s MUST consume `<UiStatusBadge>` for the quotation Sí/No pills '
                . '(PAGOS-MOD-001).', $path));

        $this->assertTrue(
            (bool) preg_match('/<UiButton\b/', $src),
            sprintf('%s MUST consume `<UiButton>` for the action buttons (PAGOS-MOD-001).', $path));

        $this->assertTrue(
            (bool) preg_match('/\bpreviewOpen\b/', $src),
            sprintf('%s MUST keep the `previewOpen` reactive ref that drives the modal '
                . 'open/close contract (PAGOS-CON-001-1).', $path));

        $this->assertTrue(
            (bool) preg_match('/\bclosePreview\s*=\s*\(\s*\)\s*=>\s*\{/', $src),
            sprintf('%s MUST keep the `closePreview` handler invoked by the modal close '
                . 'event (PAGOS-CON-001-1).', $path));
    }

    /**
     * PAGOS-MOD-001 — the QuotationsPage form controls are on the Ui
     * primitive surface: `<UiInput>` replaces the raw `<input>` for the four
     * text/date filters, `<UiSelect>` replaces the raw `<select>` for the
     * status filter, the legacy `focus:ring-2 focus:ring-primary-500 focus:
     * border-transparent` chrome is gone, and the custom `animate-spin`
     * spinner is replaced by `<UiLoadingSpinner>` (PAGOS-MOD-001). The
     * `<style scoped>` block is deleted (DLR-R-021). The WebSocket
     * subscription on the `quotations` channel is preserved byte-for-byte
     * (PAGOS-RT-001).
     */
    public function test_quotations_page_uses_ui_form_primitives(): void
    {
        $path = dirname(__DIR__, 3) . self::QUOTATIONS_PAGE;
        $src = self::readSource($path);
        $this->assertNotNull($src, sprintf('%s must be readable.', $path));

        $this->assertTrue(
            (bool) preg_match('/<UiInput\b/', $src),
            sprintf('%s MUST consume `<UiInput>` for the Paciente / Fecha desde / Fecha hasta '
                . 'filters (PAGOS-MOD-001).', $path));

        $this->assertTrue(
            (bool) preg_match('/<UiSelect\b/', $src),
            sprintf('%s MUST consume `<UiSelect>` for the Estado filter (PAGOS-MOD-001).', $path));

        $this->assertSame(0, preg_match('/\bfocus:ring-2\b/', $src),
            sprintf('%s MUST NOT keep the legacy `focus:ring-2 focus:ring-primary-500` '
                . 'chrome — `<UiInput>` / `<UiSelect>` own the focus ring (PAGOS-MOD-001).', $path));

        $this->assertSame(0, preg_match('/\bfocus:border-transparent\b/', $src),
            sprintf('%s MUST NOT keep the legacy `focus:border-transparent` chrome '
                . '(PAGOS-MOD-001).', $path));

        $this->assertSame(0, preg_match('/\banimate-spin\b/', $src),
            sprintf('%s MUST NOT keep the legacy `animate-spin` spinner — `<UiLoadingSpinner>` '
                . 'owns the spinner (PAGOS-MOD-001).', $path));

        $this->assertMatchesRegularExpression('/<UiButton\b/', $src,
            sprintf('%s MUST consume `<UiButton>` for at least one action button '
                . '(PAGOS-MOD-001).', $path));

        $this->assertSame(0, preg_match('/\bbtn\s+btn-(?:secondary|outline|primary)\b/', $src),
            sprintf('%s MUST NOT keep the legacy `btn btn-*` class strings — `<UiButton>` '
                . 'owns the action chrome (PAGOS-MOD-001).', $path));

        $this->assertMatchesRegularExpression(
            "/quotationsChannel\\s*=\\s*channel\\(\\s*['\"]quotations['\"]/",
            $src,
            sprintf('%s MUST keep the named `quotations` Echo channel subscription '
                . '(PAGOS-RT-001).', $path));
    }

    /** @return array<int, string> */
    private static function forbiddenLegacyAliases(): array
    {
        return [
            'text-accent',
            'text-success-600',
            'text-success-800',
            'text-error-600',
            'text-red-500',
            'text-red-600',
            'text-red-900',
            'text-amber-600',
            'hover:text-primary-800',
        ];
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
     * Strip JS/TS string + comment noise inside `<script>...</script>` blocks
     * so regex patterns match actual class strings, not pattern examples
     * embedded in JS literals.
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
