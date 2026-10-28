<?php

namespace Tests\Unit\DesignSystem;

use PHPUnit\Framework\TestCase;

/**
 * Contract guard for the presupuestos interface redesign (2026-10).
 *
 * The rule-level assertions (canvas token, hairlines, no <style> blocks,
 * legacy aliases, canonical money formatter) live in CajaPagesAppShellTest.
 * This guard pins the redesign contract itself: hero/KPI/list grammar, the
 * complete transient states, the truthful row actions, the dialog-based
 * detail, the write payloads the API actually accepts, and the rule that one
 * vocabulary module owns statuses, dates, items and permissions.
 */
class QuotationsRedesignContractTest extends TestCase
{
    private const PAGE = '/resources/js/modules/quotations/QuotationsPage.vue';

    private const DETAIL = '/resources/js/modules/quotations/components/QuotationDetail.vue';

    private const FORM = '/resources/js/modules/quotations/components/QuotationModal.vue';

    private const APPROVAL = '/resources/js/modules/quotations/components/QuotationApprovalModal.vue';

    private const REJECT = '/resources/js/modules/quotations/components/QuotationRejectModal.vue';

    private const VOCABULARY = '/resources/js/modules/quotations/quotationMeta.js';

    private const BADGE = '/resources/js/modules/quotations/components/QuotationStatusBadge.vue';

    private static function read(string $path): string
    {
        $source = file_get_contents(dirname(__DIR__, 3).$path);
        self::assertNotFalse($source, sprintf('%s must be readable.', $path));

        return $source;
    }

    /** @return array<int, string> */
    private static function moduleFiles(): array
    {
        return [self::PAGE, self::DETAIL, self::FORM, self::APPROVAL, self::REJECT];
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
            '/<header\b[^>]*data-quotations-hero[^>]*>[\s\S]*?data-hero-title[^>]*>\s*Presupuestos\s*</',
            $page,
            'The hero band MUST expose data-quotations-hero and the exact title "Presupuestos".'
        );
        $this->assertMatchesRegularExpression(
            '/data-hero-caption/',
            $page,
            'The hero band MUST carry a caption slot.'
        );
        $this->assertMatchesRegularExpression(
            '/data-hero-action="create"/',
            $page,
            'The hero band MUST own the primary "Nuevo presupuesto" action.'
        );
    }

    public function test_kpi_strip_reads_the_summary_endpoint_and_never_invents_zeros(): void
    {
        $page = self::read(self::PAGE);

        foreach (['total', 'pending', 'approved', 'approved-total'] as $kpi) {
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
            '#/api/quotations/summary#',
            $page,
            'The KPI strip MUST read the server aggregate instead of counting the paginated page.'
        );
        $this->assertMatchesRegularExpression(
            '/kpisUnavailable\s*=\s*computed\(\(\)\s*=>\s*summaryLoading\.value\s*\|\|\s*summaryError\.value\)/',
            $page,
            'KPI values MUST read N/D while the aggregate is loading or after a failure.'
        );
        $this->assertMatchesRegularExpression(
            "/'N\\/D'/",
            $page,
            'KPI values MUST render the N/D placeholder.'
        );
    }

    public function test_page_renders_the_list_on_the_shared_table_with_truthful_row_actions(): void
    {
        $page = self::read(self::PAGE);

        $this->assertMatchesRegularExpression(
            '/<UiDataTable\b/',
            $page,
            'The list MUST use <UiDataTable> instead of cards or a hand-rolled table.'
        );

        foreach (['view', 'edit', 'approve', 'reject', 'pdf', 'delete'] as $action) {
            $this->assertMatchesRegularExpression(
                '/data-row-action="'.$action.'"/',
                $page,
                sprintf('The row actions MUST expose "%s".', $action)
            );
        }

        foreach ([
            'canEditQuotation',
            'canApproveQuotation',
            'canRejectQuotation',
            'canDeleteQuotation',
            'canDownloadQuotation',
        ] as $rule) {
            $this->assertMatchesRegularExpression(
                '/'.$rule.'\(/',
                $page,
                sprintf('Row actions MUST be gated by the shared vocabulary rule %s().', $rule)
            );
        }

        $this->assertMatchesRegularExpression(
            '/import\s*\{\s*usePermissions\s*\}\s*from\s*[\'"`]@\/composables\/usePermissions[\'"`]/',
            $page,
            'The page MUST import usePermissions: the SPA derives its gates from the permission list the API publishes, so the matrix lives in the backend and never in the page.'
        );

        $this->assertMatchesRegularExpression(
            '/const\s*\{\s*can\s*\}\s*=\s*usePermissions\s*\(\s*\)/s',
            $page,
            'The page MUST gate its actions with `const { can } = usePermissions()` (#280/#420): the permission list the API publishes is the single source of the SPA gates, and the authorization matrix lives in the backend and never in the page.'
        );
    }

    public function test_page_covers_skeleton_error_and_empty_states(): void
    {
        $page = self::read(self::PAGE);

        $this->assertMatchesRegularExpression(
            '/data-quotations-loading[\s\S]*?<UiSkeleton\b/',
            $page,
            'The loading state MUST be a <UiSkeleton>, not a bare spinner.'
        );
        $this->assertMatchesRegularExpression(
            '/<div\b(?=[^>]*data-quotations-error)(?=[^>]*role="alert")[^>]*>/',
            $page,
            'The error state MUST be announced with role="alert".'
        );
        $this->assertMatchesRegularExpression(
            '/data-quotations-retry/',
            $page,
            'The error state MUST offer a retry action.'
        );
        $this->assertMatchesRegularExpression(
            '/data-quotations-empty[\s\S]*?<UiEmptyState\b/',
            $page,
            'The empty state MUST use <UiEmptyState>.'
        );
        $this->assertMatchesRegularExpression(
            '/data-quotations-empty[\s\S]*?Limpiar filtros/',
            $page,
            'The empty state MUST distinguish filtered results from a truly empty module.'
        );
    }

    public function test_detail_is_a_dialog_that_reads_only_persisted_fields(): void
    {
        $detail = self::read(self::DETAIL);

        $this->assertMatchesRegularExpression(
            '/<UiModal\b/',
            $detail,
            'The detail MUST render inside <UiModal>.'
        );
        $this->assertStringNotContainsString(
            'Teleport',
            $detail,
            'The detail MUST NOT build its own overlay.'
        );

        foreach (['patient', 'validity', 'items', 'costs'] as $section) {
            $this->assertMatchesRegularExpression(
                '/data-detail-section="'.$section.'"/',
                $detail,
                sprintf('The detail MUST render the "%s" section.', $section)
            );
        }

        foreach (['close', 'pdf', 'edit', 'approve', 'reject'] as $action) {
            $this->assertMatchesRegularExpression(
                '/data-detail-action="'.$action.'"/',
                $detail,
                sprintf('The detail footer MUST expose "%s".', $action)
            );
        }

        $this->assertMatchesRegularExpression(
            '/resolveItemTitle\(/',
            $detail,
            'Item titles MUST come from the vocabulary mapping of item_name.'
        );
        $this->assertStringNotContainsString(
            'item.description',
            $detail,
            'The detail MUST NOT read item.description; the column is item_name/item_description.'
        );
        $this->assertStringNotContainsString(
            'item.category',
            $detail,
            'The detail MUST NOT read item.category; the column is specialty.'
        );
    }

    public function test_form_sends_only_fields_the_api_validates(): void
    {
        $form = self::read(self::FORM);

        $this->assertMatchesRegularExpression(
            '/:?data-quotation-form="[^"]*create[^"]*"/',
            $form,
            'The form MUST expose data-quotation-form with the create/edit mode.'
        );

        foreach (['patient', 'items', 'adjustments', 'terms'] as $section) {
            $this->assertMatchesRegularExpression(
                '/data-form-section="'.$section.'"/',
                $form,
                sprintf('The form MUST group fields under the "%s" section.', $section)
            );
        }

        $this->assertMatchesRegularExpression(
            '/patient_id:\s*patient(?:\.value)?\.id/',
            $form,
            'The create payload MUST send patient_id from the selected patient.'
        );
        $this->assertSame(
            0,
            preg_match('/(?<![\w])tax_amount\s*:/', $form),
            'The form MUST NOT submit tax_amount: the server recalculates it.'
        );
        $this->assertSame(
            0,
            preg_match('/(?<![\w])total_amount\s*:/', $form),
            'The form MUST NOT submit total_amount: the server recalculates it.'
        );
        $this->assertMatchesRegularExpression(
            '/item_name/',
            $form,
            'The edit initialization MUST map persisted items through item_name.'
        );
    }

    public function test_approval_and_rejection_send_the_right_validated_keys(): void
    {
        $approval = self::read(self::APPROVAL);
        $reject = self::read(self::REJECT);

        $this->assertMatchesRegularExpression(
            '/notes/',
            $approval,
            'The approval flow MUST send notes (the only stored free-text key).'
        );
        foreach (['approved_by', 'approved_at', 'comments', 'conditions'] as $deadField) {
            $this->assertStringNotContainsString(
                $deadField,
                $approval,
                sprintf('The approval flow MUST NOT send "%s": the API never stores it.', $deadField)
            );
        }

        $this->assertMatchesRegularExpression(
            '/reason/',
            $reject,
            'The rejection flow MUST send the required reason.'
        );
        $this->assertMatchesRegularExpression(
            '/maxlength="500"/',
            $reject,
            'The rejection reason MUST be capped at the API limit of 500 characters.'
        );
        $this->assertMatchesRegularExpression(
            '/data-modal-field="reason"/',
            $reject,
            'The rejection reason field MUST expose a stable hook for the guard.'
        );
    }

    public function test_no_module_file_reads_dead_api_fields_or_legacy_chrome(): void
    {
        foreach (self::moduleFiles() as $path) {
            $source = self::read($path);

            $deadFields = ['quotation.title', 'quotation.description', 'item.category'];
            if ($path !== self::FORM) {
                // The form owns a local, editable `description` per row; only
                // read-only surfaces must never treat API items that way.
                $deadFields[] = 'item.description';
            }

            foreach ($deadFields as $deadField) {
                $this->assertStringNotContainsString(
                    $deadField,
                    $source,
                    sprintf('%s MUST NOT read the dead field "%s".', $path, $deadField)
                );
            }

            $this->assertStringNotContainsString(
                '<style',
                $source,
                sprintf('%s MUST NOT declare a <style> block (DLR-R-021).', $path)
            );
            $this->assertStringNotContainsString(
                'border-theme',
                $source,
                sprintf('%s MUST NOT use the legacy `border-theme` token (DLR-R-002).', $path)
            );
            $this->assertSame(
                0,
                preg_match('/\bbtn\s+btn-/', $source),
                sprintf('%s MUST NOT keep legacy `btn btn-*` chrome (PAGOS-MOD-001).', $path)
            );
            $this->assertSame(
                0,
                preg_match('/focus:ring-primary-500|focus:border-transparent/', $source),
                sprintf('%s MUST NOT reintroduce the legacy focus chrome (PAGOS-MOD-001).', $path)
            );
        }
    }

    public function test_status_badge_reads_the_shared_vocabulary(): void
    {
        $badge = self::read(self::BADGE);

        $this->assertMatchesRegularExpression(
            '/import\s*\{\s*getStatusMeta\s*\}\s*from\s*[\'"]\.\.\/quotationMeta[\'"]/',
            $badge,
            'The badge MUST resolve labels and variants through the vocabulary.'
        );
        $this->assertMatchesRegularExpression(
            '/statusMeta\.label/',
            $badge,
            'The badge MUST render the vocabulary label.'
        );
        $this->assertStringNotContainsString(
            'Rechazado',
            $badge,
            'The badge MUST NOT keep a local label map: the vocabulary owns it.'
        );
        $this->assertStringNotContainsString(
            "'viewed'",
            $badge,
            'The badge MUST NOT enumerate statuses locally.'
        );
    }

    public function test_vocabulary_is_the_single_source_of_truth(): void
    {
        $vocabulary = self::read(self::VOCABULARY);

        foreach (['draft', 'sent', 'viewed', 'approved', 'rejected', 'expired'] as $status) {
            $this->assertMatchesRegularExpression(
                "/{$status}:\s*\{\s*label:/",
                $vocabulary,
                sprintf('The vocabulary MUST map the "%s" status of the DB enum.', $status)
            );
        }

        foreach ([
            'getStatusMeta',
            'formatQuotationDate',
            'expiryBadge',
            'resolveItemTitle',
            'resolveItemDescription',
            'canEditQuotation',
            'canApproveQuotation',
            'canRejectQuotation',
            'canDeleteQuotation',
            'canDownloadQuotation',
        ] as $export) {
            $this->assertMatchesRegularExpression(
                '/export function '.$export.'\(/',
                $vocabulary,
                sprintf('The vocabulary MUST export %s().', $export)
            );
        }

        $this->assertStringNotContainsString(
            'Intl.NumberFormat',
            $vocabulary,
            'Money formatting belongs to the canonical useFormatters helper (PAGOS-MNY-002).'
        );
    }
}
