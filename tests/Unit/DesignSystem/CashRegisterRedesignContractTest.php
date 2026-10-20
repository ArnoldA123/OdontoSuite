<?php

namespace Tests\Unit\DesignSystem;

use PHPUnit\Framework\TestCase;

/**
 * Contract guard for the Caja interface redesign (2026-10).
 *
 * The shared rules (canvas token, hairlines, legacy chrome, canonical money
 * formatter) live in ModuleAppShellTestCase and its subclasses. This guard
 * pins the redesign contract: the dashboard shell, the ledger-sourced KPIs,
 * the primitive-driven lists, the v-model modal contract, the real endpoints
 * behind the actions, and the removal of the controls that had no contract.
 */
class CashRegisterRedesignContractTest extends TestCase
{
    private const MODULE = '/resources/js/modules/cash-register';

    /** @var array<int, string> */
    private const PRIMITIVE_LISTS = [
        '/components/PendingPaymentsList.vue',
        '/components/TransactionList.vue',
        '/components/MovementList.vue',
        '/components/SessionList.vue',
        '/components/CashReports.vue',
    ];

    /** @var array<int, string> */
    private const V_MODEL_MODALS = [
        '/components/OpenCashModal.vue',
        '/components/CloseCashModal.vue',
        '/components/TransactionModal.vue',
        '/components/MovementModal.vue',
    ];

    private static function read(string $relativePath): string
    {
        $path = dirname(__DIR__, 3).self::MODULE.$relativePath;
        $source = file_get_contents($path);
        self::assertNotFalse($source, sprintf('%s must be readable.', $path));

        return $source;
    }

    public function test_the_page_leads_with_the_hero_band_instead_of_a_duplicated_page_header(): void
    {
        $page = self::read('/CashRegisterPage.vue');

        $this->assertStringNotContainsString(
            '<PageHeader',
            $page,
            'The Caja page MUST NOT render <PageHeader>; the hero band owns the title.'
        );
        $this->assertMatchesRegularExpression(
            '/<header\b[^>]*data-caja-hero[^>]*>[\s\S]*?data-hero-title[^>]*>\s*Caja\s*</',
            $page,
            'The hero band MUST expose data-caja-hero and the exact title "Caja".'
        );
        $this->assertMatchesRegularExpression(
            '/data-hero-state/',
            $page,
            'The session state MUST live in the hero band.'
        );
    }

    public function test_the_kpi_strip_names_what_each_figure_sums(): void
    {
        $page = self::read('/CashRegisterPage.vue');

        foreach (['opening', 'income', 'expenses', 'expected'] as $kpi) {
            $this->assertMatchesRegularExpression(
                '/data-kpi-card="'.$kpi.'"/',
                $page,
                sprintf('The KPI strip MUST render the "%s" card.', $kpi)
            );
            $this->assertMatchesRegularExpression(
                '/data-kpi-value="'.$kpi.'"/',
                $page,
                sprintf('The KPI strip MUST expose the "%s" value slot.', $kpi)
            );
        }

        $this->assertMatchesRegularExpression(
            "/summary\.value\s*\?\s*formatCurrency\([^)]*\)\s*:\s*'N\/D'/",
            $page,
            'A missing summary MUST read N/D instead of a fabricated zero.'
        );
        $this->assertMatchesRegularExpression(
            '/summary\.value\.expected_amount/',
            $page,
            'The "Saldo esperado" card MUST read expected_amount, the ledger position.'
        );
    }

    public function test_tabs_are_permission_gated_instead_of_rendering_dead_surfaces(): void
    {
        $page = self::read('/CashRegisterPage.vue');

        $this->assertMatchesRegularExpression(
            '/if\s*\(canViewPendingPayments\.value\)\s*items\.push\(\{\s*id:\s*.payments./',
            $page,
            'The Cobros tab MUST be gated: pending payments are admin/finanzas only.'
        );
        $this->assertMatchesRegularExpression(
            '/if\s*\(canViewReports\.value\)\s*items\.push\(\{\s*id:\s*.reports./',
            $page,
            'The Reportes tab MUST be gated behind viewCashReports.'
        );
    }

    /**
     * @dataProvider listProvider
     */
    public function test_every_list_is_on_the_table_primitive(string $path): void
    {
        $source = self::read($path);

        $this->assertMatchesRegularExpression(
            '/<UiDataTable\b/',
            $source,
            sprintf('%s MUST render through <UiDataTable>.', $path)
        );
        $this->assertStringNotContainsString(
            '<table',
            $source,
            sprintf('%s MUST NOT hand-write a table.', $path)
        );
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function listProvider(): array
    {
        $cases = [];
        foreach (self::PRIMITIVE_LISTS as $path) {
            $cases[$path] = [$path];
        }

        return $cases;
    }

    /**
     * @dataProvider modalProvider
     */
    public function test_every_modal_speaks_the_v_model_contract(string $path): void
    {
        $source = self::read($path);

        $this->assertMatchesRegularExpression(
            "/defineEmits\(\s*\[\s*'update:modelValue'\s*,\s*'success'\s*\]\s*\)/",
            $source,
            sprintf('%s MUST emit update:modelValue together with success.', $path)
        );
        $this->assertMatchesRegularExpression(
            "/defineProps\(/",
            $source,
            sprintf('%s MUST declare its props.', $path)
        );
        $this->assertStringContainsString(
            ':model-value="modelValue"',
            $source,
            sprintf('%s MUST feed <UiModal> from modelValue.', $path)
        );
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function modalProvider(): array
    {
        $cases = [];
        foreach (self::V_MODEL_MODALS as $path) {
            $cases[$path] = [$path];
        }

        return $cases;
    }

    public function test_no_native_select_survives_in_the_module(): void
    {
        $files = array_merge(
            ['/CashRegisterPage.vue', '/ReadyToBillPage.vue'],
            self::PRIMITIVE_LISTS,
            self::V_MODEL_MODALS,
            ['/components/PaymentModal.vue', '/components/MercadoPagoCheckout.vue']
        );

        foreach ($files as $path) {
            $this->assertStringNotContainsString(
                '<select',
                self::read($path),
                sprintf('%s MUST use <UiSelect> instead of a native select.', $path)
            );
        }
    }

    public function test_the_payment_flows_use_the_endpoints_that_own_the_balance(): void
    {
        $paymentModal = self::read('/components/PaymentModal.vue');
        $transactionList = self::read('/components/TransactionList.vue');
        $transactionModal = self::read('/components/TransactionModal.vue');

        $this->assertMatchesRegularExpression(
            '/`\/api\/pending-payments\/\$\{[^}]+\}\/pay`/',
            $paymentModal,
            'A payment opened from a pending appointment MUST post to the pay endpoint that updates the appointment balance.'
        );
        $this->assertStringContainsString(
            '/api/payment-methods/active',
            $paymentModal,
            'The payment modal MUST read the public active catalog.'
        );
        $this->assertStringContainsString(
            '/api/payment-methods/active',
            $transactionList,
            'The transaction filters MUST read the public active catalog.'
        );
        $this->assertStringContainsString(
            '/api/payment-methods/active',
            $transactionModal,
            'The transaction modal MUST read the public active catalog.'
        );
    }

    public function test_dead_controls_are_gone(): void
    {
        $transactions = self::read('/components/TransactionList.vue');
        $movements = self::read('/components/MovementList.vue');
        $sessions = self::read('/components/SessionList.vue');
        $reports = self::read('/components/CashReports.vue');

        $this->assertStringNotContainsString(
            'window.confirm',
            $transactions,
            'Anular MUST go through useConfirm, not the native dialog.'
        );
        $this->assertStringNotContainsString(
            'Reabrir',
            $sessions,
            'There is no reopen endpoint; the control MUST NOT render.'
        );
        $this->assertStringNotContainsString(
            'exportToExcel',
            $movements,
            'The stub exports MUST NOT survive the redesign.'
        );
        $this->assertStringNotContainsString(
            'window.print',
            $reports,
            'The reports page MUST NOT print the whole application shell.'
        );
        $this->assertStringNotContainsString(
            'localStorage',
            $reports,
            'Exports MUST go through useApi().download, not a hand-built fetch.'
        );
        $this->assertStringContainsString(
            'download(',
            $reports,
            'The reports page MUST download through useApi().download.'
        );
    }

    public function test_the_failed_pending_payments_load_is_visible(): void
    {
        $pending = self::read('/components/PendingPaymentsList.vue');

        $this->assertMatchesRegularExpression(
            '/role="alert"/',
            $pending,
            'A failed load MUST render an alert instead of an empty list.'
        );
        $this->assertMatchesRegularExpression(
            '/data-pending-payments-retry/',
            $pending,
            'A failed load MUST offer a retry action.'
        );
    }

    public function test_the_movement_form_sends_the_required_concept(): void
    {
        $modal = self::read('/components/MovementModal.vue');

        $this->assertMatchesRegularExpression(
            '/concept/',
            $modal,
            'The movement form MUST send the whitelisted concept the request requires.'
        );
        $this->assertMatchesRegularExpression(
            '/branch_id/',
            $modal,
            'The movement form MUST send the session branch when it is known.'
        );
    }

    public function test_ready_to_bill_runs_inside_the_application_shell(): void
    {
        $page = self::read('/ReadyToBillPage.vue');

        $this->assertMatchesRegularExpression('/<AppLayout\b/', $page);
        $this->assertMatchesRegularExpression('/data-rtb-hero/', $page);
        $this->assertMatchesRegularExpression('/<UiDataTable\b/', $page);
        $this->assertStringNotContainsString('<table', $page);
        $this->assertMatchesRegularExpression(
            '/role="alert"/',
            $page,
            'A failed load MUST render an alert with retry instead of stale rows.'
        );
    }
}
