<?php

namespace Tests\Unit\DesignSystem;

use PHPUnit\Framework\TestCase;

/**
 * Contract guard for the `/specialty-records` redesign (#212).
 *
 * The rule-level assertions (canvas token, hairlines, focus ring, no
 * `<style scoped>`) live in `SpecialtyRecordsAppShellTest`. This guard pins the
 * redesign contract itself:
 *
 *   - SRR-001  hero band owns the title; no <PageHeader> duplication.
 *   - SRR-002  four KPI cards (total / in_progress / completed / last) fed by
 *              the meta vocabulary, with `last` reading the newest `created_at`.
 *   - SRR-003  the specialty selector rides `UiTabs` with per-specialty count
 *              badges.
 *   - SRR-004  every transient state exists: loading, error + retry, empty,
 *              no patient, and a role with no specialties.
 *   - SRR-005  the page reads the Spanish keyed `/all` payload through
 *              `getAllRecords` and confirms deletes through `useConfirm`.
 *   - SRR-006  the form modal writes only real columns: its payload iterates
 *              `definition.formFields`, so invented fields cannot come back.
 *   - SRR-007  card / detail / modal render meta-driven fields only; no legacy
 *              English specialty keys and no retired field names.
 */
class SpecialtyRecordsRedesignContractTest extends TestCase
{
    private const PAGE = '/resources/js/modules/specialty-records/SpecialtyRecordsPage.vue';

    private const CARD = '/resources/js/modules/specialty-records/components/SpecialtyRecordCard.vue';

    private const DETAIL = '/resources/js/modules/specialty-records/components/SpecialtyRecordDetail.vue';

    private const MODAL = '/resources/js/modules/specialty-records/components/SpecialtyRecordModal.vue';

    private const META = '/resources/js/modules/specialty-records/specialtyRecordMeta.js';

    private static function read(string $path): string
    {
        $source = file_get_contents(dirname(__DIR__, 3).$path);
        self::assertNotFalse($source, sprintf('%s must be readable.', $path));

        return (string) $source;
    }

    public function test_page_leads_with_the_hero_band_and_the_exact_title(): void
    {
        $page = self::read(self::PAGE);

        $this->assertStringNotContainsString(
            '<PageHeader',
            $page,
            'The redesigned page MUST NOT render <PageHeader>; the hero band owns the title (SRR-001).'
        );
        $this->assertMatchesRegularExpression(
            '#<header\b[^>]*data-specialty-records-hero[^>]*>[\s\S]*?<h2\b[^>]*data-hero-title[^>]*>\s*Registros de Especialidades\s*</#',
            $page,
            'The hero band MUST expose data-specialty-records-hero and carry the exact <h2> title "Registros de Especialidades" (SRR-001).'
        );
        $this->assertStringContainsString(
            'data-hero-eyebrow',
            $page,
            'The hero band MUST carry the eyebrow (SRR-001).'
        );
        $this->assertStringContainsString(
            'data-hero-caption',
            $page,
            'The hero band MUST carry the caption (SRR-001).'
        );
        $this->assertStringContainsString(
            'data-new-record',
            $page,
            'The hero band MUST expose the "Nuevo registro" action (SRR-001).'
        );
        $this->assertMatchesRegularExpression(
            '#data-new-record[\s\S]{0,300}?:disabled="!selectedPatient#',
            $page,
            '"Nuevo registro" MUST stay disabled until a patient is selected (SRR-001).'
        );
    }

    public function test_page_renders_the_four_kpi_cards_from_the_shared_vocabulary(): void
    {
        $page = self::read(self::PAGE);

        foreach (['total', 'in_progress', 'completed', 'last'] as $kpi) {
            $this->assertMatchesRegularExpression(
                '#data-kpi-card="'.$kpi.'"#',
                $page,
                sprintf('The KPI strip MUST render the "%s" card (SRR-002).', $kpi)
            );
            $this->assertMatchesRegularExpression(
                '#data-kpi-value="'.$kpi.'"#',
                $page,
                sprintf('The KPI strip MUST expose a value slot for "%s" (SRR-002).', $kpi)
            );
        }

        $this->assertMatchesRegularExpression(
            '#countInGroup\(activeRecords\.value\)#',
            $page,
            'The total KPI MUST count the active specialty group (SRR-002).'
        );
        $this->assertMatchesRegularExpression(
            '#countByStatuses\(activeDefinition\.value\?\.progressStatuses\)#',
            $page,
            'The in-progress KPI MUST read progressStatuses of the active specialty (SRR-002).'
        );
        $this->assertMatchesRegularExpression(
            '#countByStatuses\(activeDefinition\.value\?\.completedStatuses\)#',
            $page,
            'The completed KPI MUST read completedStatuses of the active specialty (SRR-002).'
        );
        $this->assertMatchesRegularExpression(
            '#kpiLast = computed\(\(\) => formatRecordDate\(latestRecordDate\(activeRecords\.value\)\)\)#',
            $page,
            'The "last" KPI MUST format the newest created_at of the active group (SRR-002).'
        );
    }

    public function test_page_builds_the_specialty_selector_from_ui_tabs_with_count_badges(): void
    {
        $page = self::read(self::PAGE);

        $this->assertMatchesRegularExpression(
            '#<UiTabs\b[^>]*\bv-model="activeSpecialty"#s',
            $page,
            'The specialty selector MUST ride <UiTabs> with v-model on activeSpecialty (SRR-003).'
        );
        $this->assertMatchesRegularExpression(
            '#<UiTabs\b[^>]*:tabs="tabs"#s',
            $page,
            'The tab contract MUST come from the computed `tabs` list (SRR-003).'
        );
        $this->assertMatchesRegularExpression(
            '#badge: String\(countInGroup\(recordsFor\(definition\.key\)\)\)#',
            $page,
            'Each tab MUST carry the per-specialty record count as a badge (SRR-003).'
        );
        $this->assertMatchesRegularExpression(
            '#definitionsForRole\(user\.value\?\.role\)#',
            $page,
            'The visible specialties MUST come from the role matrix in the shared vocabulary (SRR-003).'
        );
        $this->assertDoesNotMatchRegularExpression(
            '#(?<![\w-])implantologo(?![\w-])#',
            $page,
            'Role checks MUST NOT be hardcoded in the page; they belong to the vocabulary (SRR-003).'
        );
    }

    public function test_page_covers_every_transient_state(): void
    {
        $page = self::read(self::PAGE);

        $this->assertStringContainsString('data-specialty-records-loading', $page);
        $this->assertStringContainsString(
            '<UiSkeleton',
            $page,
            'The loading state MUST render <UiSkeleton> instead of a bare spinner (SRR-004).'
        );

        $this->assertStringContainsString('data-specialty-records-error', $page);
        $this->assertStringContainsString(
            'role="alert"',
            $page,
            'The error state MUST announce itself with role="alert" (SRR-004).'
        );
        $this->assertStringContainsString(
            'data-specialty-records-retry',
            $page,
            'The error state MUST offer a retry action (SRR-004).'
        );

        $this->assertStringContainsString('data-specialty-records-empty', $page);
        $this->assertStringContainsString('data-specialty-records-no-patient', $page);
        $this->assertStringContainsString('data-specialty-records-no-specialties', $page);

        $this->assertGreaterThanOrEqual(
            3,
            preg_match_all('#<UiEmptyState\b#', $page),
            'Empty, no-patient and no-specialties states MUST each render <UiEmptyState> (SRR-004).'
        );
    }

    public function test_page_reads_the_keyed_payload_and_confirms_deletes(): void
    {
        $page = self::read(self::PAGE);

        $this->assertMatchesRegularExpression(
            '#const \{[^}]*\ballRecords\b[^}]*\} = useSpecialtyRecords\(\)#s',
            $page,
            'The page MUST read the keyed groups from `allRecords` (SRR-005).'
        );
        $this->assertMatchesRegularExpression(
            '#allRecords\.value\?\.\[specialty\]#',
            $page,
            'Records MUST be read by Spanish specialty key (SRR-005).'
        );
        $this->assertStringContainsString(
            'getAllRecords(selectedPatient.value.id)',
            $page,
            'The page MUST load through getAllRecords(patientId) (SRR-005).'
        );
        $this->assertStringContainsString(
            'deleteRecord(record.id, specialty)',
            $page,
            'Deletes MUST send the specialty alongside the id (SRR-005).'
        );
        $this->assertStringContainsString(
            'useConfirm',
            $page,
            'Deleting MUST be confirmed through useConfirm (SRR-005).'
        );
        $this->assertStringContainsString(
            'useErrorHandler',
            $page,
            'Failures MUST report through useErrorHandler (SRR-005).'
        );
        $this->assertStringContainsString(
            'useToast',
            $page,
            'Outcomes MUST be announced through useToast (SRR-005).'
        );
        $this->assertStringContainsString(
            "privateChannel('specialty-records')",
            $page,
            'The private WebSocket subscription to the specialty-records channel MUST survive the redesign (SRR-005).'
        );
        $this->assertStringContainsString(
            "'.specialty-record.created'",
            $page,
            'Created events MUST reload the selected patient group (SRR-005).'
        );
        $this->assertStringContainsString(
            'event?.record?.patient_id !== selectedPatient.value.id',
            $page,
            'Remote events MUST only reload when they belong to the selected patient (SRR-005).'
        );
    }

    public function test_form_modal_writes_only_real_columns(): void
    {
        $modal = self::read(self::MODAL);

        $this->assertStringContainsString(
            '<UiModal',
            $modal,
            'The form MUST ride the <UiModal> primitive (SRR-006).'
        );
        foreach (['patient-specialty', 'clinical', 'notes'] as $section) {
            $this->assertMatchesRegularExpression(
                '#data-form-section="'.$section.'"#',
                $modal,
                sprintf('The form MUST group fields under the "%s" section (SRR-006).', $section)
            );
        }

        $this->assertMatchesRegularExpression(
            '#v-for="field in clinicalFields"#',
            $modal,
            'Clinical fields MUST render from the vocabulary list (SRR-006).'
        );
        $this->assertMatchesRegularExpression(
            '#v-for="field in noteFields"#',
            $modal,
            'Notes MUST render from the vocabulary list (SRR-006).'
        );
        $this->assertMatchesRegularExpression(
            '#for \(const field of definition\.value\?\.formFields \?\? \[\]\)#',
            $modal,
            'The payload MUST iterate definition.formFields so no invented field can be sent (SRR-006).'
        );
        $this->assertMatchesRegularExpression(
            "#if \(!props\.isEdit\) payload\.patient_id = patientId\.value#",
            $modal,
            'patient_id belongs to the create contract only (SRR-006).'
        );

        foreach (['<UiInput', '<UiSelect', '<UiTextarea'] as $primitive) {
            $this->assertStringContainsString(
                $primitive,
                $modal,
                sprintf('The form MUST use the %s primitive (SRR-006).', $primitive)
            );
        }

        $this->assertStringContainsString(
            'applyServerErrors',
            $modal,
            'Server 422 errors MUST map into field errors (SRR-006).'
        );
        $this->assertMatchesRegularExpression(
            '#:disabled="saving"#',
            $modal,
            'The submit action MUST disable itself while saving (SRR-006).'
        );
    }

    public function test_components_render_only_meta_driven_fields(): void
    {
        $files = [
            self::CARD => self::read(self::CARD),
            self::DETAIL => self::read(self::DETAIL),
            self::MODAL => self::read(self::MODAL),
        ];

        $forbidden = [
            'title',
            'description',
            'procedure_date',
            'professional_name',
            'observations',
            'next_appointment',
            'implant_count',
            'implant_type',
            'position',
            'estimated_duration',
            'main_problem',
            'involved_teeth',
            'surgery_type',
            'anesthesia',
            'obturation_material',
        ];

        foreach ($files as $path => $source) {
            foreach ($forbidden as $field) {
                $this->assertDoesNotMatchRegularExpression(
                    '#\brecord\??\.'.$field.'\b#',
                    $source,
                    sprintf('%s MUST NOT read the retired field `record.%s` (SRR-007).', $path, $field)
                );
            }

            foreach (['implantology', 'orthodontics', 'endodontics', 'rehabilitation', 'oral_surgery'] as $legacy) {
                $this->assertDoesNotMatchRegularExpression(
                    '#(?<![\w-])'.$legacy.'(?![\w-])#',
                    $source,
                    sprintf('%s MUST NOT use the English specialty key "%s" (SRR-007).', $path, $legacy)
                );
            }
        }

        $this->assertMatchesRegularExpression(
            '#summaryFields\(props\.definition, props\.record\)#',
            $files[self::CARD],
            'The card MUST render its body from summaryFields (SRR-007).'
        );
        $this->assertStringContainsString(
            '<UiStatusBadge',
            $files[self::CARD],
            'The card MUST show the status through <UiStatusBadge> (SRR-007).'
        );
        $this->assertMatchesRegularExpression(
            '#detailSections\(definition\.value, props\.record\)#',
            $files[self::DETAIL],
            'The detail MUST render its sections from detailSections (SRR-007).'
        );
        $this->assertMatchesRegularExpression(
            '#v-for="section in sections"#',
            $files[self::DETAIL],
            'The detail MUST iterate the meta sections (SRR-007).'
        );
    }

    public function test_components_avoid_legacy_markup_tokens(): void
    {
        foreach ([self::PAGE, self::CARD, self::DETAIL, self::MODAL] as $path) {
            $source = self::read($path);

            $this->assertDoesNotMatchRegularExpression(
                '#<style\s+scoped\s*>#',
                $source,
                sprintf('%s MUST NOT contain a <style scoped> block (SRR-007).', $path)
            );
            $this->assertDoesNotMatchRegularExpression(
                '#(?<![\w-])btn(?![\w-])#',
                $source,
                sprintf('%s MUST NOT use the legacy `btn` class (SRR-007).', $path)
            );
            $this->assertStringNotContainsString(
                '<select',
                $source,
                sprintf('%s MUST use <UiSelect> instead of a native <select> (SRR-007).', $path)
            );
            $this->assertStringNotContainsString(
                '<svg',
                $source,
                sprintf('%s MUST take icons from @heroicons/vue, not inline SVG (SRR-007).', $path)
            );
        }
    }

    public function test_page_consumes_the_shared_vocabulary(): void
    {
        $page = self::read(self::PAGE);

        foreach (['countInGroup', 'definitionsForRole', 'formatRecordDate', 'getSpecialtyDefinition', 'latestRecordDate'] as $export) {
            $this->assertMatchesRegularExpression(
                '#import\s*\{[^}]*\b'.$export.'\b[^}]*\}\s*from\s*[\'"]\./specialtyRecordMeta[\'"]#s',
                $page,
                sprintf('The page MUST consume %s from the shared vocabulary (SRR-003).', $export)
            );
        }
    }

    public function test_shared_meta_exports_the_specialty_records_vocabulary(): void
    {
        $meta = self::read(self::META);

        foreach ([
            'SPECIALTY_RECORD_DEFINITIONS',
            'getSpecialtyDefinition',
            'definitionsForRole',
            'statusLabel',
            'statusVariant',
            'countInGroup',
            'latestRecordDate',
            'formatRecordDate',
            'formatRecordDateTime',
            'summaryFields',
            'detailSections',
            'formFields',
        ] as $export) {
            $this->assertMatchesRegularExpression(
                '#export\s+(?:const|function)\s+'.$export.'\b#',
                $meta,
                sprintf('specialtyRecordMeta MUST export %s (SRR-003).', $export)
            );
        }

        foreach (['implantologia', 'ortodoncia', 'endodoncia', 'rehabilitacion', 'cirugia_oral'] as $key) {
            $this->assertMatchesRegularExpression(
                "#key: '".$key."'#",
                $meta,
                sprintf('The vocabulary MUST declare the Spanish key "%s" (SRR-003).', $key)
            );
        }
    }
}
