<?php

namespace Tests\Unit\DesignSystem;

use PHPUnit\Framework\TestCase;

/**
 * Contract guard for the specialties interface redesign (2026-10).
 *
 * The rule-level assertions (canvas token, hairlines, no `<style>` blocks,
 * legacy aliases) live in `SpecialtiesAppShellTest`. This guard pins the
 * redesign contract itself: the hero/KPI/list grammar, the deactivation-only
 * row actions, the complete transient states, the server-side filters, and the
 * shared vocabulary that owns labels, usage counts and the no-delete notice.
 *
 * The deactivation-only contract is a domain invariant, not a style choice:
 * `procedure_catalog.specialty_id` and `user_specialties` reference the row, so
 * a specialty is deactivated and never deleted. The guard pins the absence of a
 * delete action in the page AND the notice that explains it in the modal.
 */
class SpecialtiesRedesignContractTest extends TestCase
{
    private const PAGE = '/resources/js/modules/specialties/SpecialtiesPage.vue';

    private const MODAL = '/resources/js/modules/specialties/SpecialtyFormModal.vue';

    private const META = '/resources/js/modules/specialties/specialtyMeta.js';

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
            '#<header\b[^>]*data-specialties-hero[^>]*>[\s\S]*?data-hero-title[^>]*>\s*Especialidades\s*</#',
            $page,
            'The hero band MUST expose data-specialties-hero and carry the exact title "Especialidades".'
        );
    }

    public function test_page_renders_four_kpi_cards_sourced_from_the_response_meta(): void
    {
        $page = self::read(self::PAGE);

        foreach (['total', 'active', 'inactive', 'procedures'] as $kpi) {
            $this->assertMatchesRegularExpression(
                '#data-kpi-card="'.$kpi.'"#',
                $page,
                sprintf('The KPI strip MUST render the "%s" card.', $kpi)
            );
            $this->assertMatchesRegularExpression(
                '#data-kpi-value="'.$kpi.'"#',
                $page,
                sprintf('The KPI strip MUST expose a value slot for "%s".', $kpi)
            );
        }

        $this->assertMatchesRegularExpression(
            '#kpis\.value\s*=\s*response\?\.meta\?\.kpis#',
            $page,
            'The KPI values MUST come from response.meta.kpis, not from a local tally.'
        );

        $this->assertMatchesRegularExpression(
            '#kpisUnavailable\s*=\s*computed\(\(\)\s*=>\s*loading\.value\s*\|\|\s*loadError\.value\s*\|\|\s*kpis\.value\s*===\s*null\)#',
            $page,
            'KPI values MUST be unavailable while loading, after a load error, or when meta.kpis is null.'
        );

        $this->assertMatchesRegularExpression(
            "#if \(kpisUnavailable\.value\) return 'N/D'#",
            $page,
            'Unavailable KPI values MUST fall back to the literal N/D instead of a fabricated 0.'
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

        foreach (['Especialidad', 'Procedimientos', 'Profesionales', 'Estado'] as $column) {
            $this->assertStringContainsString(
                sprintf("label: '%s'", $column),
                $page,
                sprintf('The table MUST declare the "%s" column.', $column)
            );
        }
    }

    public function test_page_actions_only_edit_or_toggle(): void
    {
        $page = self::read(self::PAGE);

        $this->assertStringContainsString('data-row-action="edit"', $page);
        $this->assertStringContainsString('data-row-action="toggle"', $page);

        $this->assertSame(
            0,
            preg_match_all('#data-row-action=["\']delete["\']#', $page),
            'The page MUST NOT offer a delete action: procedure_catalog.specialty_id and '
            .'user_specialties reference specialties, so they are deactivated, never deleted.'
        );
    }

    public function test_page_covers_loading_error_retry_and_empty_states(): void
    {
        $page = self::read(self::PAGE);

        $this->assertStringContainsString('data-specialties-loading', $page);
        $this->assertStringContainsString(
            '<UiSkeleton',
            $page,
            'The loading state MUST render <UiSkeleton> rather than a bare spinner.'
        );
        $this->assertStringContainsString('data-specialties-error', $page);
        $this->assertStringContainsString(
            'role="alert"',
            $page,
            'The error state MUST announce itself with role="alert".'
        );
        $this->assertStringContainsString(
            'data-specialties-retry',
            $page,
            'The error state MUST offer a retry action.'
        );
        $this->assertStringContainsString('data-specialties-empty', $page);
        $this->assertStringContainsString(
            '<UiEmptyState',
            $page,
            'The empty state MUST render <UiEmptyState>.'
        );
    }

    public function test_page_builds_server_side_filters_from_the_shared_options(): void
    {
        $page = self::read(self::PAGE);

        $this->assertStringContainsString(
            'STATUS_FILTER_OPTIONS',
            $page,
            'The status filter MUST consume the shared STATUS_FILTER_OPTIONS vocabulary.'
        );
        $this->assertMatchesRegularExpression(
            "#params\.set\('q',\s*query\)#",
            $page,
            'The search box MUST reach the API through the q query parameter.'
        );
        $this->assertMatchesRegularExpression(
            "#params\.set\('is_active',\s*statusFilter\.value\)#",
            $page,
            'The status filter MUST reach the API through the is_active query parameter.'
        );
    }

    public function test_page_consumes_the_shared_vocabulary_and_confirmation_primitive(): void
    {
        $page = self::read(self::PAGE);

        foreach (['countProcedures', 'countProfessionals', 'getStatusLabel', 'getStatusVariant'] as $export) {
            $this->assertMatchesRegularExpression(
                '#import\s*\{[^}]*\b'.$export.'\b[^}]*\}\s*from\s*[\'"]\./specialtyMeta[\'"]#s',
                $page,
                sprintf('The page MUST consume %s from the shared specialtyMeta vocabulary.', $export)
            );
        }

        $this->assertStringContainsString(
            'useConfirm',
            $page,
            'Row state changes MUST be confirmed through useConfirm.'
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

    public function test_modal_groups_fields_into_sections_and_locks_the_code_on_edit(): void
    {
        $modal = self::read(self::MODAL);

        foreach (['identification', 'detail', 'status'] as $section) {
            $this->assertMatchesRegularExpression(
                '#data-form-section="'.$section.'"#',
                $modal,
                sprintf('The modal MUST group fields under the "%s" section.', $section)
            );
        }

        $this->assertMatchesRegularExpression(
            '#<UiInput\b[^>]*v-model="form\.code"[^>]*:disabled="isEdit \|\| saving"#',
            $modal,
            'The code input MUST be disabled on edit: procedure_catalog and the CSV import reference it.'
        );

        $this->assertMatchesRegularExpression(
            '#const buildPayload = \(\) => \{([\s\S]*?)\n\}#',
            $modal,
            'The modal MUST build its payload in one place.'
        );
        preg_match('#const buildPayload = \(\) => \{([\s\S]*?)\n\}#', $modal, $match);
        $this->assertSame(
            1,
            substr_count($match[1], 'payload.code'),
            'The payload MUST assign code in exactly one place.'
        );
        $this->assertMatchesRegularExpression(
            '#if \(!isEdit\.value\) \{\s*payload\.code\s*=#',
            $match[1],
            'The code MUST only be sent on create (immutable on edit).'
        );
    }

    public function test_modal_states_the_deactivation_contract_and_avoids_a_form_element(): void
    {
        $modal = self::read(self::MODAL);

        $this->assertMatchesRegularExpression(
            '#<p\b[^>]*data-delete-contract[^>]*>[\s\S]*?\{\{\s*DELETE_CONTRACT_NOTICE\s*\}\}#',
            $modal,
            'The status section MUST render the deactivation notice through data-delete-contract.'
        );

        $this->assertMatchesRegularExpression(
            "#const DELETE_CONTRACT_NOTICE\s*=\s*\n\s*'[^']*no se eliminan[^']*se desactivan[^']*'#",
            self::read(self::META),
            'DELETE_CONTRACT_NOTICE MUST state that specialties are deactivated, never deleted.'
        );

        $this->assertStringNotContainsString(
            '<form',
            $modal,
            'The modal body MUST remain a <div>: UiSelect buttons would trigger implicit submit inside a form.'
        );

        $submitTag = self::openingTagContaining($modal, 'data-modal-submit');
        $this->assertStringNotContainsString(
            'v-if',
            $submitTag,
            'The primary action MUST NOT be hidden on edit.'
        );
        $this->assertStringNotContainsString(
            ':disabled',
            $submitTag,
            'The primary action MUST NOT be disabled on edit.'
        );
    }

    public function test_shared_meta_exports_the_specialties_vocabulary(): void
    {
        $meta = self::read(self::META);

        foreach ([
            'STATUS_FILTER_OPTIONS',
            'STATUS_FORM_OPTIONS',
            'CODE_EDIT_HINT',
            'DELETE_CONTRACT_NOTICE',
            'countProcedures',
            'countProfessionals',
            'getStatusLabel',
            'getStatusVariant',
            'getUsageSummary',
        ] as $export) {
            $this->assertMatchesRegularExpression(
                '#export\s+(?:const|function)\s+'.$export.'\b#',
                $meta,
                sprintf(
                    'specialtyMeta MUST export %s: the shared vocabulary owns labels, usage counts and the delete contract.',
                    $export
                )
            );
        }
    }
}
