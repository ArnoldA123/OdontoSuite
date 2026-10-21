<?php

namespace Tests\Unit\DesignSystem;

use PHPUnit\Framework\TestCase;

/**
 * Historias clínicas — multi-history workspace contract (issue #228).
 *
 * A patient can own several `medical_records` rows (the database does not
 * forbid it). The workspace used to load `per_page=50` and bind Evoluciones
 * and the evolution modal to the first active record, so the rest of the
 * histories were unreachable from the interface.
 *
 * Rules pinned here:
 *
 *   - MRC-SCOPE-001  the workspace walks the index pages (`getAllRecords`,
 *                    which iterates `meta.last_page`) instead of silently
 *                    truncating at the 50-row cap.
 *   - MRC-SCOPE-002  when `records.length > 1` the page renders a history
 *                    selector; choosing one swaps `currentRecord` and reloads
 *                    only the evolutions that belong to it.
 *   - MRC-SCOPE-003  the active history is visible in the tab card header and
 *                    the timeline names the record it is showing.
 *   - MRC-SCOPE-004  attachments and stats are patient-scoped and say so on
 *                    their surfaces instead of pretending to follow a record.
 *
 * DOM behavior for the two-history case lives in
 * `tests/js/smoke/medical-records.test.js`; this file pins the contract so a
 * silent rename fails without a browser.
 */
class MedicalRecordsRecordScopeTest extends TestCase
{
    private const PAGE_PATH = '/resources/js/modules/medical-records/MedicalRecordsPage.vue';

    private const TIMELINE_PATH = '/resources/js/modules/medical-records/components/EvolutionTimeline.vue';

    private const GALLERY_PATH = '/resources/js/modules/medical-records/components/AttachmentGallery.vue';

    private function source(string $relativePath): string
    {
        $path = dirname(__DIR__, 3).$relativePath;
        $this->assertFileExists($path, sprintf('%s must exist.', $relativePath));

        $source = file_get_contents($path);
        $this->assertIsString($source, sprintf('%s must be readable.', $relativePath));

        return $source;
    }

    /**
     * MRC-SCOPE-001 — the workspace paginates; the landing keeps its own
     * paginated index call.
     */
    public function test_the_workspace_loads_every_history_page(): void
    {
        $source = $this->source(self::PAGE_PATH);

        $this->assertStringContainsString('const {', $source);
        $this->assertStringContainsString('getAllRecords,', $source);
        $this->assertStringContainsString(
            "getAllRecords({ patient_id: patient.id, status: 'all' })",
            $source,
            'The workspace must load every history of the patient (MRC-SCOPE-001).'
        );
        $this->assertStringNotContainsString(
            "getRecords({ patient_id: patient.id, status: 'all', per_page: 50 })",
            $source,
            'The silent 50-row cap must not come back (MRC-SCOPE-001).'
        );

        $store = $this->source('/resources/js/composables/useMedicalRecords.js');
        $this->assertStringContainsString('const getAllRecords', $store);
        $this->assertStringContainsString('first.meta?.last_page', $store);
        $this->assertStringContainsString('MAX_RECORD_PAGES', $store);
        $this->assertStringContainsString('getAllRecords,', $store);
    }

    /**
     * MRC-SCOPE-002 — the selector exists only when it has something to pick,
     * and changing it reloads the evolutions of the chosen record.
     */
    public function test_the_page_offers_a_history_selector_when_there_are_several(): void
    {
        $source = $this->source(self::PAGE_PATH);

        $this->assertStringContainsString('v-if="records.length > 1"', $source);
        $this->assertStringContainsString('data-record-selector', $source);
        $this->assertStringContainsString(':options="recordOptions"', $source);
        $this->assertStringContainsString('@update:model-value="selectRecord"', $source);
        $this->assertStringContainsString('const selectRecord = async recordId =>', $source);
        $this->assertStringContainsString('currentRecord.value = record', $source);
        $this->assertStringContainsString('await loadEvolutions()', $source);
    }

    /**
     * MRC-SCOPE-003 — the active history is visible in the tab card and in the
     * timeline header.
     */
    public function test_the_active_history_is_visible_on_the_tab_surfaces(): void
    {
        $page = $this->source(self::PAGE_PATH);

        $this->assertStringContainsString('data-active-record-header', $page);
        $this->assertStringContainsString('data-active-record-label', $page);
        $this->assertStringContainsString('currentRecord?.record_number', $page);
        $this->assertStringContainsString(':record="currentRecord"', $page);

        $timeline = $this->source(self::TIMELINE_PATH);
        $this->assertMatchesRegularExpression(
            '/record:\s*\{[^}]*type:\s*Object[^}]*default:\s*null[^}]*\}/s',
            $timeline,
            'The timeline declares the nullable `record` prop (MRC-SCOPE-003).'
        );
        $this->assertStringContainsString('data-evolution-record', $timeline);
        $this->assertStringContainsString('record.record_number', $timeline);
    }

    /**
     * MRC-SCOPE-004 — patient-scoped surfaces declare their scope.
     */
    public function test_patient_scoped_surfaces_declare_their_scope(): void
    {
        $page = $this->source(self::PAGE_PATH);
        $gallery = $this->source(self::GALLERY_PATH);

        $this->assertStringContainsString('data-stats-scope', $page);
        $this->assertStringContainsString('todo el expediente del paciente', $page);

        $this->assertStringContainsString('data-attachments-scope', $gallery);
        $this->assertStringContainsString('expediente completo del paciente', $gallery);
        $this->assertMatchesRegularExpression(
            '/record:\s*\{[^}]*type:\s*Object[^}]*default:\s*null[^}]*\}/s',
            $gallery,
            'The gallery accepts the active record it names in its scope note.'
        );
    }
}
