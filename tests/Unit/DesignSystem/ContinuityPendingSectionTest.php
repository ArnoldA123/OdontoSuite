<?php

namespace Tests\Unit\DesignSystem;

use PHPUnit\Framework\TestCase;

/**
 * S15 — the continuity desk becomes "Pendientes" and absorbs the commercial
 * pending answers the dashboard used to publish.
 *
 * Source-inspection guards for the renamed hero, the third section, its merged
 * row model, the tolerant load/retry and the navigation copy. DOM behavior is
 * covered by tests/js/smoke/continuity.test.js.
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

    public function test_the_three_sections_and_their_hooks_survive(): void
    {
        $src = $this->pageSource();

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
            'data-continuity-pending-error',
            'data-continuity-pending-retry',
            'data-continuity-pending-view',
        ] as $hook) {
            $this->assertStringContainsString(
                $hook,
                $src,
                "ContinuityPage.vue must keep the {$hook} hook."
            );
        }

        // The two queue sections keep their own table and row actions.
        $this->assertStringContainsString('data-continuity-schedule="recall"', $src);
        $this->assertStringContainsString('data-continuity-schedule="request"', $src);
        $this->assertStringContainsString('data-continuity-dismiss', $src);
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
            'The section must stay hidden while no readable subset exists.'
        );
        $this->assertStringContainsString(
            'v-if="pendingLoading || hasPendingGroups || pendingError"',
            $src,
            'The section renders only while loading, with a readable subset, or on its own error.'
        );
    }

    public function test_pending_rows_merge_both_types_with_amount_status_and_one_action(): void
    {
        $src = $this->pageSource();

        foreach (["typeLabel: 'Presupuesto'", "typeLabel: 'Plan de tratamiento'"] as $label) {
            $this->assertStringContainsString(
                $label,
                $src,
                "The merged rows must carry the type copy {$label}."
            );
        }

        $this->assertStringContainsString('readAmount: item => item?.total_amount', $src);
        $this->assertStringContainsString('readAmount: item => item?.final_cost', $src);
        $this->assertStringContainsString('formatPENLabel(amount)', $src);
        $this->assertStringContainsString("'N/D'", $src);
        $this->assertStringContainsString('rows.sort((a, b) => a.sortTime - b.sortTime)', $src);

        foreach (["sent: 'Enviado'", "viewed: 'Visto'", "proposed: 'Propuesto'"] as $status) {
            $this->assertStringContainsString(
                $status,
                $src,
                "The merged rows must label the {$status} status."
            );
        }

        // One row-level action per answer, routed to its module list.
        $this->assertStringContainsString('@click.stop="openPending(row)"', $src);
        $this->assertStringContainsString("route: '/quotations'", $src);
        $this->assertStringContainsString("route: '/treatment-plans'", $src);
        $this->assertMatchesRegularExpression(
            '/const openPending = row => \{[\s\S]*?router\.push\(destination\)/',
            $src,
            'The row action must navigate with router.push (no invented deep links).'
        );
    }

    public function test_pending_section_uses_the_shared_table_and_empty_patterns(): void
    {
        $src = $this->pageSource();

        $this->assertMatchesRegularExpression(
            '/<UiDataTable[\s\S]*?:data="pendingRows"[\s\S]*?:show-header="false"/',
            $src,
            'The pending answers must render through UiDataTable with the header bar off, like the queue sections.'
        );
        $this->assertMatchesRegularExpression(
            '/<UiSectionEmpty\b[\s\S]*?data-continuity-empty="pending"/',
            $src,
            'The empty case must render through the shared UiSectionEmpty primitive.'
        );
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
