<?php

namespace Tests\Unit\DesignSystem;

use PHPUnit\Framework\TestCase;

/**
 * Contract guard for the payment-methods interface redesign (2026-10).
 *
 * The rule-level assertions (canvas token, hairlines, no <style> blocks,
 * legacy aliases, gateway redaction) live in CajaPagesAppShellTest. This
 * guard pins the redesign contract itself: the hero/KPI/list grammar, the
 * truthful row actions, the complete transient states, and the rule that the
 * shared vocabulary — not the page — owns labels and deletion eligibility.
 */
class PaymentMethodsRedesignContractTest extends TestCase
{
    private const PAGE = '/resources/js/modules/settings/payment-methods/PaymentMethodsPage.vue';

    private const MODAL = '/resources/js/modules/settings/payment-methods/PaymentMethodFormModal.vue';

    private static function read(string $path): string
    {
        $source = file_get_contents(dirname(__DIR__, 3).$path);
        self::assertNotFalse($source, sprintf('%s must be readable.', $path));

        return $source;
    }

    private static function openingTagContaining(string $source, string $needle): string
    {
        $position = strpos($source, $needle);
        self::assertNotFalse($position, sprintf('Expected to find `%s`.', $needle));
        $start = strrpos(substr($source, 0, $position), '<');
        self::assertNotFalse($start, sprintf('Expected `%s` to belong to an opening tag.', $needle));
        $end = strpos($source, '>', $position);
        self::assertNotFalse($end, sprintf('Expected the tag holding `%s` to close.', $needle));

        return substr($source, $start, $end - $start + 1);
    }

    public function test_page_leads_with_the_hero_band_instead_of_a_duplicated_page_header(): void
    {
        $page = self::read(self::PAGE);

        $this->assertStringNotContainsString(
            '<PageHeader',
            $page,
            'The redesigned page MUST NOT render <PageHeader>; the hero band owns the title.'
        );
        $this->assertMatchesRegularExpression(
            '/<header\b[^>]*data-pm-hero[^>]*>[\s\S]*?data-hero-title[^>]*>\s*Métodos de Pago\s*</',
            $page,
            'The hero band MUST expose data-pm-hero and the exact title "Métodos de Pago".'
        );
    }

    public function test_page_renders_four_kpi_cards_with_truthful_placeholders(): void
    {
        $page = self::read(self::PAGE);

        foreach (['total', 'active', 'inactive', 'system'] as $kpi) {
            $this->assertMatchesRegularExpression(
                '/data-kpi-card="'.$kpi.'"/',
                $page,
                sprintf('The KPI strip MUST render the "%s" card.', $kpi)
            );
            $this->assertMatchesRegularExpression(
                '/data-kpi-value="'.$kpi.'"/',
                $page,
                sprintf('The KPI strip MUST expose a value slot for "%s".', $kpi)
            );
        }

        $this->assertMatchesRegularExpression(
            '/kpisUnavailable\s*=\s*computed\(\s*\(\)\s*=>[^;]*loading\.value[^;]*loadError\.value/s',
            $page,
            'KPI values MUST read N/D while loading or after a load error instead of a fabricated 0.'
        );
        // #445: the strip describes the directory, not the filtered page, so
        // the four counters read the unfiltered population read.
        $this->assertStringContainsString(
            'populationMethods.value.length',
            $page,
            'The KPI total MUST count the unfiltered population instead of the filtered rows.'
        );
        $this->assertSame(
            3,
            substr_count($page, 'populationMethods.value.filter'),
            'The active/inactive/system counters MUST read the unfiltered population.'
        );
        $this->assertSame(
            4,
            substr_count($page, "'N/D'"),
            'Every KPI MUST fall back to the N/D placeholder (4 KPIs).'
        );
    }

    public function test_page_renders_the_list_through_the_shared_table_primitive(): void
    {
        $page = self::read(self::PAGE);

        $this->assertStringContainsString(
            '<UiDataTable',
            $page,
            'The list MUST consume <UiDataTable> instead of a hand-rolled table.'
        );
        $this->assertStringNotContainsString(
            '<table',
            $page,
            'The redesigned page MUST NOT hand-roll a <table>.'
        );

        foreach (['Método', 'Pasarela', 'Comisión', 'Tipo', 'Estado'] as $column) {
            $this->assertStringContainsString(
                sprintf("label: '%s'", $column),
                $page,
                sprintf('The table MUST declare the "%s" column.', $column)
            );
        }
    }

    public function test_page_actions_follow_the_backend_deletion_contract(): void
    {
        $page = self::read(self::PAGE);

        $this->assertStringContainsString('data-row-action="edit"', $page);
        $this->assertStringContainsString('data-row-action="toggle"', $page);

        $toggleTag = self::openingTagContaining($page, 'data-row-action="toggle"');
        // #439: the only condition allowed on the toggle is the session gate;
        // it MUST stay available for every method the session may write,
        // system methods included.
        $this->assertStringContainsString(
            'v-if="can.managePaymentMethods?.value"',
            $toggleTag,
            'Activar/Desactivar MUST follow the session permission gate.'
        );
        $this->assertStringNotContainsString(
            'canDelete',
            $toggleTag,
            'Activar/Desactivar MUST be available for every method, system methods included.'
        );
        $this->assertStringNotContainsString(
            'v-if="row',
            $toggleTag,
            'Activar/Desactivar MUST NOT be restricted per row state; the label already switches.'
        );

        $this->assertMatchesRegularExpression(
            '/<UiButton\b[^>]*v-if="[^"]*canDelete\(row\)[^"]*"[^>]*data-row-action="delete"|'
                .'<UiButton\b[^>]*data-row-action="delete"[^>]*v-if="[^"]*canDelete\(row\)[^"]*"/s',
            $page,
            'Eliminar MUST only render when canDelete(row) allows it (PAGOS/F7 contract).'
        );
    }

    public function test_page_covers_loading_error_and_empty_states_with_retry(): void
    {
        $page = self::read(self::PAGE);

        $this->assertStringContainsString('data-pm-loading', $page);
        $this->assertStringContainsString('<UiSkeleton', $page);
        $this->assertStringContainsString('data-pm-error', $page);
        $this->assertStringContainsString('role="alert"', $page);
        $this->assertStringContainsString('data-pm-retry', $page);
        $this->assertStringContainsString('data-pm-empty', $page);
        $this->assertStringContainsString('<UiEmptyState', $page);
    }

    public function test_page_uses_the_shared_vocabulary_and_confirmation_primitive(): void
    {
        $page = self::read(self::PAGE);

        foreach ([
            'canDelete',
            'formatCommission',
            'getGatewayLabel',
            'getScopeLabel',
            'getScopeVariant',
            'getStatusLabel',
            'getStatusVariant',
        ] as $export) {
            $this->assertMatchesRegularExpression(
                '/import\s*\{[^}]*\b'.$export.'\b[^}]*\}\s*from\s*[\'"]\.\/paymentMethodMeta[\'"]/s',
                $page,
                sprintf('The page MUST consume %s() from the shared paymentMethodMeta vocabulary.', $export)
            );
        }

        $this->assertStringContainsString(
            'useConfirm',
            $page,
            'Row confirmations MUST go through useConfirm.'
        );
        $this->assertSame(
            0,
            preg_match('/\{\{\s*(?:row|method)\.gateway_type\s*\}\}/', $page),
            'The page MUST NOT interpolate the raw gateway_type; it renders the vocabulary label.'
        );
        $this->assertStringNotContainsString(
            '<select',
            $page,
            'The page MUST use <UiSelect> instead of a native <select>.'
        );
        $this->assertStringNotContainsString(
            '<svg',
            $page,
            'Icons MUST come from @heroicons/vue, not inline SVG.'
        );
    }

    public function test_page_builds_the_status_filter_query_from_the_shared_options(): void
    {
        $page = self::read(self::PAGE);

        $this->assertStringContainsString('STATUS_FILTER_OPTIONS', $page);
        $this->assertMatchesRegularExpression(
            "/params\.set\('is_active',\s*statusFilter\.value\)/",
            $page,
            'The status filter MUST reach the API through the is_active query parameter.'
        );
        $this->assertMatchesRegularExpression(
            "/params\.set\('q',\s*query\)/",
            $page,
            'The search box MUST reach the API through the q query parameter.'
        );
    }

    public function test_modal_groups_the_form_into_sections_and_uses_the_shared_selects(): void
    {
        $modal = self::read(self::MODAL);

        foreach (['identification', 'billing', 'gateway', 'description'] as $section) {
            $this->assertMatchesRegularExpression(
                '/data-form-section="'.$section.'"/',
                $modal,
                sprintf('The modal MUST group fields under the "%s" section.', $section)
            );
        }

        $this->assertGreaterThanOrEqual(
            2,
            substr_count($modal, '<UiSelect'),
            'Pasarela and Estado MUST be rendered through <UiSelect>.'
        );
        $this->assertStringContainsString('GATEWAY_TYPE_OPTIONS', $modal);
        $this->assertStringContainsString('STATUS_FORM_OPTIONS', $modal);
        $this->assertStringContainsString(
            'v-model="form.allows_change"',
            $modal,
            'The already-modeled allows_change flag MUST be editable.'
        );
        $this->assertStringNotContainsString(
            '<form',
            $modal,
            'The modal body MUST remain a <div>: UiSelect buttons would trigger implicit submit inside a form.'
        );
    }

    public function test_modal_keeps_system_methods_editable_without_a_dead_end_footer(): void
    {
        $modal = self::read(self::MODAL);

        $this->assertSame(
            0,
            preg_match('/:disabled="[^"]*isSystem[^"]*"/', $modal),
            'System methods MUST stay editable; only the code and is_system are immutable.'
        );

        $submitTag = self::openingTagContaining($modal, 'data-modal-submit');
        $this->assertStringNotContainsString(
            'v-if',
            $submitTag,
            'The primary action MUST NOT be hidden for system methods.'
        );
        $this->assertStringNotContainsString(
            ':disabled',
            $submitTag,
            'The primary action MUST NOT be disabled for system methods.'
        );
    }

    public function test_modal_payload_never_sends_is_system_and_omits_code_on_edit(): void
    {
        $modal = self::read(self::MODAL);

        $this->assertMatchesRegularExpression(
            '/const buildPayload = \(\) => \{([\s\S]*?)\n\}/',
            $modal,
            'The modal MUST build its payload in one place.'
        );
        preg_match('/const buildPayload = \(\) => \{([\s\S]*?)\n\}/', $modal, $match);
        $this->assertArrayNotHasKey(
            'is_system',
            array_flip(preg_split('/\W+/', $match[1])),
            'The payload MUST NOT include is_system; the server owns that invariant.'
        );
        $this->assertMatchesRegularExpression(
            '/if \(!isEdit\.value\) \{\s*payload\.code\s*=/',
            $match[1],
            'The code MUST only be sent on create (immutable on edit).'
        );
    }

    public function test_modal_requires_an_access_token_before_sending_credentials(): void
    {
        $modal = self::read(self::MODAL);

        $this->assertMatchesRegularExpression(
            "/gatewayConfig\.access_token\.trim\(\)/",
            $modal,
            'Credentials MUST only be sent when an access token is provided.'
        );
        $this->assertStringContainsString(
            'Ingresa el Access Token para actualizar las credenciales.',
            $modal,
            'A public key without an access token MUST be blocked with an explicit field error.'
        );
    }
}
