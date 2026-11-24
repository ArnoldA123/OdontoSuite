<?php

namespace Tests\Unit\DesignSystem;

use PHPUnit\Framework\TestCase;

/**
 * S15 — the continuity desk becomes "Pendientes".
 *
 * The desk used to paint one table per source, so the same patient could show up
 * four times with the actions scattered between the rows. The contract that
 * replaced it is one section and one row per patient: the queue (overdue
 * controls and continuity requests) plus the commercial answers merge by
 * `patientKey`, and every pending keeps its own chip and its own action.
 *
 * Source-inspection guards for the single section, the grouped row model, the
 * chip vocabulary, the per-item actions, the patient KPI, the tolerant load and
 * the navigation copy. DOM behavior is covered by
 * tests/js/smoke/continuity.test.js.
 */
class ContinuityPendingSectionTest extends TestCase
{
    private const PAGE_FILE = '/resources/js/modules/continuity/ContinuityPage.vue';

    private const APP_LAYOUT_FILE = '/resources/js/components/layout/AppLayout.vue';

    private static function read(string $rel): string
    {
        $path = dirname(__DIR__, 3).$rel;

        return is_file($path) ? (string) file_get_contents($path) : '';
    }

    private function pageSource(): string
    {
        $src = self::read(self::PAGE_FILE);
        $this->assertNotSame('', $src, 'ContinuityPage.vue must exist');

        return $src;
    }

    /** Every `data-continuity-section` key the page declares, in order. */
    private function sectionKeys(string $src): array
    {
        preg_match_all('/data-continuity-section="([^"]+)"/', $src, $matches);

        return $matches[1];
    }

    public function test_header_is_named_pendientes_and_states_its_three_populations(): void
    {
        $src = $this->pageSource();

        $this->assertStringContainsString(
            '<PageHeader',
            $src,
            'The page must mount the shared PageHeader.'
        );
        $this->assertMatchesRegularExpression(
            '/<PageHeader\b[^>]*kicker="Pendientes"[^>]*title="Pendientes"/u',
            $src,
            'The shared header must name the desk "Pendientes" in kicker and title.'
        );

        $this->assertStringContainsString('Recalls vencidos', $src);
        $this->assertStringContainsString('solicitudes de continuidad', $src);
        $this->assertStringContainsString('esperando', $src);
        $this->assertStringNotContainsString('Cola de continuidad', $src);
    }

    public function test_the_desk_publishes_one_section_and_the_queue_sections_are_gone(): void
    {
        $src = $this->pageSource();

        $this->assertSame(
            ['pending'],
            $this->sectionKeys($src),
            'Pendientes paints one section named "pending"; the per-source sections are retired.'
        );

        foreach ([
            'data-continuity',
            'data-continuity-refresh',
            'data-continuity-kpis',
            'data-continuity-error',
            'data-continuity-error-message',
            'data-continuity-retry',
            'data-continuity-loading',
            'data-continuity-section="pending"',
            'data-continuity-table="pending"',
            'data-continuity-empty="pending"',
            'data-continuity-pending-state',
            'data-continuity-pending-loading',
            'data-continuity-pending-error',
            'data-continuity-pending-error-message',
            'data-continuity-pending-retry',
            'data-continuity-pending-view',
        ] as $hook) {
            $this->assertStringContainsString(
                $hook,
                $src,
                "ContinuityPage.vue must keep the {$hook} hook."
            );
        }

        // A returning `recalls`/`requests` table would paint the patient twice.
        $this->assertStringNotContainsString('data-continuity-section="recalls"', $src);
        $this->assertStringNotContainsString('data-continuity-section="requests"', $src);
        $this->assertStringNotContainsString('data-continuity-table="recalls"', $src);
        $this->assertStringNotContainsString('data-continuity-table="requests"', $src);
        $this->assertStringNotContainsString('data-continuity-empty="recalls"', $src);
        $this->assertStringNotContainsString('data-continuity-empty="requests"', $src);
    }

    public function test_the_grouped_table_reads_pending_rows_with_the_patient_as_its_key(): void
    {
        $src = $this->pageSource();

        $this->assertMatchesRegularExpression(
            '/<UiDataTable[\s\S]*?:data="pendingRows"[\s\S]*?row-key="key"[\s\S]*?:show-header="false"[\s\S]*?data-continuity-table="pending"/',
            $src,
            'The desk renders the grouped rows through UiDataTable, keyed by the patient row.'
        );

        foreach ([
            "label: 'Paciente'",
            "label: 'Pendientes'",
            "label: 'Detalle'",
        ] as $column) {
            $this->assertStringContainsString(
                $column,
                $src,
                "The grouped table declares its {$column} column."
            );
        }

        $this->assertStringContainsString(
            'Una fila por paciente con sus controles, solicitudes y respuestas pendientes',
            $src,
            'The section states the grouping in its own copy.'
        );
    }

    public function test_rows_merge_by_patient_so_no_row_repeats_the_same_patient(): void
    {
        $src = $this->pageSource();

        // One map keyed by the patient: a second pending joins the first row
        // instead of pushing a new one.
        $this->assertStringContainsString('const groups = new Map()', $src);
        $this->assertStringContainsString('const group = groups.get(item.patientKey) || {', $src);
        $this->assertStringContainsString('groups.set(item.patientKey, group)', $src);

        $this->assertSame(
            1,
            substr_count($src, 'patientRows.push('),
            'The row list is filled once per group, never once per pending.'
        );
        $this->assertMatchesRegularExpression(
            '/for \(const group of groups\.values\(\)\) \{[\s\S]*?patientRows\.push\(\{/',
            $src,
            'The push lives inside the loop over the patient groups, not inside the items loop.'
        );
        $this->assertStringContainsString('key: `patient-${item.patientKey}`', $src);
        $this->assertStringContainsString(
            'const pendingRows = computed(() => buildPendingRows())',
            $src,
            'The table reads the grouped derivation, not the raw queues.'
        );

        foreach ([
            'queue.value.recalls',
            'queue.value.requests',
            'pendingQuotations.value?.items',
            'pendingTreatmentPlans.value?.items',
        ] as $source) {
            $this->assertStringContainsString(
                $source,
                $src,
                "The grouping feeds from {$source}."
            );
        }
    }

    public function test_every_pending_keeps_its_chip_and_its_own_action(): void
    {
        $src = $this->pageSource();

        foreach ([
            "recall: { label: 'Control'",
            "request: { label: 'Solicitud'",
            "quotation: { label: 'Presupuesto'",
            "plan: { label: 'Plan'",
        ] as $source) {
            $this->assertStringContainsString(
                $source,
                $src,
                "The grouped row carries the {$source} chip vocabulary."
            );
        }

        $this->assertStringContainsString('v-for="label in row.labels"', $src);
        $this->assertStringContainsString(':data-continuity-label="label.key"', $src);
        $this->assertStringContainsString('v-for="row in entry.items"', $src);

        // One action per pending: a patient with a control and a quotation keeps
        // both doors, and the commercial answers stay read-only.
        $this->assertStringContainsString('data-continuity-schedule="recall"', $src);
        $this->assertStringContainsString('data-continuity-schedule="request"', $src);
        $this->assertStringContainsString('data-continuity-dismiss', $src);
        $this->assertStringContainsString('@click.stop="openSchedule(\'recall\', row.payload)"', $src);
        $this->assertStringContainsString('@click.stop="openSchedule(\'request\', row.payload)"', $src);
        $this->assertStringContainsString('@click.stop="openPending(row)"', $src);

        $this->assertStringContainsString("route: '/quotations'", $src);
        $this->assertStringContainsString("route: '/treatment-plans'", $src);
    }

    public function test_the_kpi_strip_counts_patients_and_keeps_the_queue_numbers(): void
    {
        $src = $this->pageSource();

        $this->assertStringContainsString("key: 'recalls'", $src);
        $this->assertStringContainsString("key: 'requests'", $src);
        $this->assertStringContainsString("key: 'compliance'", $src);
        $this->assertStringContainsString("key: 'patients'", $src);
        $this->assertStringContainsString(
            "label: 'Pacientes con pendientes'",
            $src,
            'The patient KPI is named for the population the table groups.'
        );
        $this->assertStringContainsString(
            'value: formatNumber(pendingRows.value.length)',
            $src,
            'The patient KPI counts grouped rows, so one patient with two pendings counts once.'
        );
        $this->assertStringContainsString("caption: 'Una fila por paciente'", $src);
    }

    public function test_pending_section_reads_its_own_door_with_a_tolerant_retry(): void
    {
        $src = $this->pageSource();

        $this->assertStringContainsString(
            "get('/api/dashboard/pending')",
            $src,
            'The pending section must read the endpoint the dashboard used to consume.'
        );
        $this->assertStringContainsString(
            'const loadPending = async ()',
            $src,
            'The section owns its load, separate from the queue loader.'
        );
        $this->assertMatchesRegularExpression(
            '/onMounted\(\(\) => \{\s*refreshAll\(\)\s*\}\)/',
            $src,
            'On mount the page refreshes both halves through refreshAll().'
        );
        $this->assertMatchesRegularExpression(
            '/const refreshAll = \(\) => \{\s*load\(\)\s*loadPending\(\)\s*\}/',
            $src,
            'refreshAll() must reload the queue (a no-op without view.continuity) and the commercial answers.'
        );
        $this->assertStringContainsString(
            'pendingError.value = pendingMessage(thrown)',
            $src,
            'A failed pending load records its own error instead of breaking the queue.'
        );
        $this->assertStringContainsString(
            'data-continuity-pending-retry',
            $src,
            'The pending error block offers its own retry.'
        );
    }

    public function test_pending_payload_key_is_the_visibility_contract(): void
    {
        $src = $this->pageSource();

        $this->assertStringContainsString(
            'const pendingQuotations = computed(() => pending.value?.quotations || null)',
            $src,
            'Quotations must be read from their own payload key.'
        );
        $this->assertStringContainsString(
            'const pendingTreatmentPlans = computed(() => pending.value?.treatment_plans || null)',
            $src,
            'Treatment plans must be read from their own payload key.'
        );
        $this->assertStringContainsString(
            'Boolean(pendingQuotations.value || pendingTreatmentPlans.value)',
            $src,
            'The commercial half must stay hidden while no readable subset exists.'
        );
        $this->assertStringContainsString(
            'v-if="pendingLoading || hasPendingGroups || pendingError"',
            $src,
            'The commercial state renders only while loading, with a readable subset, or on its own error.'
        );
    }

    public function test_pending_section_uses_the_shared_table_and_empty_patterns(): void
    {
        $src = $this->pageSource();

        $this->assertMatchesRegularExpression(
            '/<UiSectionEmpty\b[\s\S]*?data-continuity-empty="pending"/',
            $src,
            'The empty case must render through the shared UiSectionEmpty primitive.'
        );
        $this->assertMatchesRegularExpression(
            '/<UiSectionEmpty\b[\s\S]*?title="Sin pendientes por paciente"/',
            $src,
            'The empty case names the grouped population, not a single source.'
        );
        $this->assertStringContainsString('data-continuity-pending-state', $src);
    }

    public function test_navigation_and_topbar_copy_describe_the_new_contents(): void
    {
        $layout = self::read(self::APP_LAYOUT_FILE);
        $this->assertNotSame('', $layout, 'AppLayout.vue must exist');

        $this->assertStringContainsString(
            "name: 'Pendientes'",
            $layout,
            'The sidebar entry must read "Pendientes".'
        );
        $this->assertStringNotContainsString(
            "name: 'Continuidad'",
            $layout,
            'The retired "Continuidad" label must not survive in the sidebar.'
        );
        $this->assertStringContainsString(
            "to: '/continuity'",
            $layout,
            'The entry keeps its route.'
        );

        // The topbar line is shared by every role that can open the desk, and
        // finance reads only the commercial half: it must stay role-neutral.
        // The detailed copy lives in the page hero, which adapts by permission.
        $this->assertMatchesRegularExpression(
            "/'\\/continuity'\\s*:\\s*'[^']*espera respuesta[^']*'/i",
            $layout,
            'The topbar description must describe the widened desk without naming a subset.'
        );
        $this->assertStringContainsString(
            'presupuestos o planes',
            self::read(self::PAGE_FILE),
            'The page hero carries the detailed copy.'
        );
    }
}
