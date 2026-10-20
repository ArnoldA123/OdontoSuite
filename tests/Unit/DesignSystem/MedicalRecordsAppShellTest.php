<?php

namespace Tests\Unit\DesignSystem;

/**
 * Historias clínicas interface redesign — T3 page shell contract.
 *
 * Scope: the page shell only (`MedicalRecordsPage.vue`). The tab surfaces
 * (historia general, evoluciones, adjuntos, estadísticas) are rewritten by
 * T4–T7, so their files are NOT listed in `polishedFiles()`: each one still
 * carries a `<style scoped>` block and would fail the inherited DLR-R-021
 * rule today. Pending their own task:
 *
 *   - components/MedicalRecordCard.vue      (T4)
 *   - components/MedicalRecordDetail.vue    (T4)
 *   - components/MedicalRecordModal.vue     (T4)
 *   - components/EvolutionTimeline.vue      (T5)
 *   - components/EvolutionModal.vue         (T5)
 *   - components/AttachmentGallery.vue      (T6)
 *   - components/UploadAttachmentModal.vue  (T6)
 *   - components/MedicalRecordStats.vue     (T7)
 *
 * Inherited rules (ModuleAppShellTestCase): DLR-R-001 canvas surface,
 * DLR-R-002 no `border-theme`, DLR-R-004 focus-ring token, DLR-R-021 no
 * `<style scoped>`.
 *
 * Shell rules pinned here (spec S1, decisions D2/D3/D6/D7):
 *
 *   - MRC-SHELL-001  hero band: canvas/hairline/panel radius/elevation-2,
 *                    DentalMotifLayer hero motif, brand lockup, `.page-title`
 *                    as a `<p>` (the app-shell topbar owns the `<h1>`).
 *   - MRC-SHELL-002  single primary CTA (`Nueva historia`) + ghost `Volver`;
 *                    the CTA stays disabled until a patient is selected.
 *   - MRC-SHELL-003  KPI strip: four `UiCard variant="glass"` cards over the
 *                    real stats keys and the four `data-stat` hooks.
 *   - MRC-SHELL-004  tabs ride the `UiTabs` primitive, in order, with the
 *                    `v-model` contract.
 *   - MRC-SHELL-005  deep links: `route.query.patient_id` + `route.params.id`
 *                    open the workspace; `/medical-records/:id(\d+)` is
 *                    registered next to the index route with `requireAuth`.
 *   - MRC-SHELL-006  evolutions are requested by record id, never by patient
 *                    id; the four surfaces load with `Promise.allSettled`.
 *   - MRC-SHELL-007  no `can(...)` function call (usePermissions exposes an
 *                    object of computed refs — decision D7) and no global
 *                    `confirm()` (the child surfaces own their dialog).
 */
class MedicalRecordsAppShellTest extends ModuleAppShellTestCase
{
    private const PAGE_PATH = '/resources/js/modules/medical-records/MedicalRecordsPage.vue';

    private const APP_ROUTES_PATH = '/resources/js/app.js';

    /** @return array<int, string> */
    protected static function polishedFiles(): array
    {
        return [dirname(__DIR__, 3).self::PAGE_PATH];
    }

    private function pageSource(): string
    {
        $path = dirname(__DIR__, 3).self::PAGE_PATH;
        $src = file_get_contents($path);
        $this->assertNotFalse($src, sprintf('%s must be readable.', $path));

        return (string) $src;
    }

    /**
     * MRC-SHELL-001 — the hero band repeats the established login/dashboard
     * recipe verbatim (same tokens as PatientsPage).
     */
    public function test_hero_band_keeps_the_shared_surface_recipe(): void
    {
        $src = $this->pageSource();

        $this->assertStringContainsString(
            'data-medical-records-hero',
            $src,
            'The page shell must expose the `data-medical-records-hero` hook (MRC-SHELL-001).'
        );
        $this->assertStringContainsString(
            'background: var(--color-canvas)',
            $src,
            'The hero band must paint the canvas base (MRC-SHELL-001 / DLR-R-001).'
        );
        $this->assertStringContainsString(
            'border: 1px solid var(--color-hairline)',
            $src,
            'The hero band must carry the hairline vocabulary (MRC-SHELL-001).'
        );
        $this->assertStringContainsString(
            'border-radius: var(--radius-panel)',
            $src,
            'The hero band must ride the panel radius (MRC-SHELL-001).'
        );
        $this->assertStringContainsString(
            'box-shadow: var(--elevation-2)',
            $src,
            'The hero band must sit on the elevation-2 rung (MRC-SHELL-001).'
        );
        $this->assertStringContainsString(
            '<DentalMotifLayer variant="hero"',
            $src,
            'The hero band must render the shared dental motif (MRC-SHELL-001).'
        );
        $this->assertStringContainsString(
            'OdontoSuite',
            $src,
            'The hero band must carry the brand lockup (MRC-SHELL-001).'
        );
        $this->assertMatchesRegularExpression(
            '#<p class="page-title relative mt-3 text-4xl font-semibold text-label">\s*Historias clínicas\s*</p>#s',
            $src,
            'The page title must be the non-heading `.page-title` paragraph reading "Historias clínicas" (MRC-SHELL-001).'
        );
        $this->assertDoesNotMatchRegularExpression(
            '#<h1\b#',
            $src,
            'The app-shell topbar owns the page <h1>; the page shell must not render one (MRC-SHELL-001).'
        );
        $this->assertMatchesRegularExpression(
            '#<h2\b#',
            $src,
            'Section titles must stay on the <h2> tier (MRC-SHELL-001).'
        );
    }

    /**
     * MRC-SHELL-002 — one primary CTA, one back action, and the CTA stays
     * disabled until a patient is selected.
     */
    public function test_hero_actions_keep_one_primary_cta(): void
    {
        $src = $this->pageSource();

        $this->assertStringContainsString(
            'data-back-button',
            $src,
            'The hero band must expose the `Volver` action hook (MRC-SHELL-002).'
        );
        $this->assertMatchesRegularExpression(
            '#router\.back\(\)#',
            $src,
            'The `Volver` action must call `router.back()` (MRC-SHELL-002).'
        );
        $this->assertStringContainsString(
            'data-new-record-button',
            $src,
            'The hero band must expose the `Nueva historia` action hook (MRC-SHELL-002).'
        );
        $this->assertMatchesRegularExpression(
            '#data-new-record-button[\s\S]{0,400}?:disabled="!selectedPatient"#',
            $src,
            '`Nueva historia` must stay disabled until a patient is selected (MRC-SHELL-002).'
        );
        $this->assertMatchesRegularExpression(
            '#<UiButton\b[\s\S]{0,200}?variant="primary"[\s\S]{0,200}?data-new-record-button#s',
            $src,
            '`Nueva historia` must be the hero\'s single primary button (MRC-SHELL-002).'
        );
        $this->assertMatchesRegularExpression(
            '#<UiButton\b[\s\S]{0,200}?variant="ghost"[\s\S]{0,200}?data-back-button#s',
            $src,
            '`Volver` must ride the ghost variant (MRC-SHELL-002).'
        );
    }

    /**
     * MRC-SHELL-003 — the KPI strip uses the dashboard anatomy and reads the
     * four stats keys the backend actually returns.
     */
    public function test_kpi_strip_renders_the_four_real_stats(): void
    {
        $src = $this->pageSource();

        $this->assertGreaterThanOrEqual(
            4,
            preg_match_all('#<UiCard\b[\s\S]{0,120}?variant="glass"#', $src),
            'The KPI strip must render four `UiCard variant="glass"` cards (MRC-SHELL-003).'
        );

        $hooks = [
            'records' => 'total_records',
            'evolutions' => 'total_evolutions',
            'attachments' => 'total_attachments',
            'follow-ups' => 'pending_follow_ups',
        ];

        foreach ($hooks as $hook => $key) {
            $this->assertMatchesRegularExpression(
                '#data-stat="'.preg_quote($hook, '#').'"#',
                $src,
                sprintf('The KPI strip must expose `data-stat="%s"` (MRC-SHELL-003).', $hook)
            );
            $this->assertMatchesRegularExpression(
                '#stats\?\.'.preg_quote($key, '#').'#',
                $src,
                sprintf('The `%s` KPI must read the real `%s` stats key (MRC-SHELL-003).', $hook, $key)
            );
        }

        $this->assertDoesNotMatchRegularExpression(
            '#(?:from-|bg-gradient-to-)#',
            $src,
            'The KPI strip must not use gradients or colour ramps (MRC-SHELL-003 / D6).'
        );
        $this->assertMatchesRegularExpression(
            '#text-2xl font-bold text-label tabular-nums#',
            $src,
            'KPI numbers must keep the dashboard anatomy literal (MRC-SHELL-003).'
        );
    }

    /**
     * MRC-SHELL-004 — the surfaces ride the `UiTabs` primitive, in order.
     */
    public function test_tabs_use_the_ui_tabs_primitive(): void
    {
        $src = $this->pageSource();

        $this->assertMatchesRegularExpression(
            '#<UiTabs\b#',
            $src,
            'The page shell must render the `UiTabs` primitive instead of ad-hoc buttons (MRC-SHELL-004).'
        );
        $this->assertMatchesRegularExpression(
            '#<UiTabs\b[^>]*\bv-model="activeTab"#s',
            $src,
            '`UiTabs` must own the `activeTab` state through v-model (MRC-SHELL-004).'
        );

        $expected = [
            "id: 'general', label: 'Historia general'",
            "id: 'evolutions', label: 'Evoluciones'",
            "id: 'attachments', label: 'Adjuntos'",
            "id: 'stats', label: 'Estadísticas'",
        ];

        $offset = -1;
        foreach ($expected as $declaration) {
            $position = strpos($src, $declaration);
            $this->assertNotFalse(
                $position,
                sprintf('The tab contract must declare `%s` (MRC-SHELL-004).', $declaration)
            );
            $this->assertGreaterThan(
                $offset,
                $position,
                sprintf('`%s` must keep its declared order in the tab contract (MRC-SHELL-004).', $declaration)
            );
            $offset = $position;
        }
    }

    /**
     * MRC-SHELL-005 — both deep links open the workspace, and the detail
     * route is registered next to the index route behind auth.
     */
    public function test_deep_links_open_the_workspace(): void
    {
        $src = $this->pageSource();

        $this->assertStringContainsString(
            'route.query.patient_id',
            $src,
            'The shell must read the `?patient_id=` deep link (MRC-SHELL-005 / D2).'
        );
        $this->assertStringContainsString(
            'route.params.id',
            $src,
            'The shell must read the `/medical-records/:id` deep link (MRC-SHELL-005 / D2).'
        );
        $this->assertMatchesRegularExpression(
            '#getRecord\(#',
            $src,
            'The `/medical-records/:id` deep link must resolve the record (MRC-SHELL-005).'
        );
        $this->assertMatchesRegularExpression(
            '#`/api/patients/\$\{[^}]+\}`#',
            $src,
            'The `?patient_id=` deep link must fetch the patient from `GET /api/patients/{id}` (MRC-SHELL-005).'
        );
        $this->assertMatchesRegularExpression(
            '#data-clear-selection#',
            $src,
            'The workspace must offer the `Quitar selección` action (MRC-SHELL-005).'
        );
        $this->assertMatchesRegularExpression(
            '#\breset\(\)#',
            $src,
            'Clearing the selection must reset the data layer so no stale panel survives (MRC-SHELL-005 / D3).'
        );
    }

    /** MRC-SHELL-005 — the detail route lives next to the index route. */
    public function test_detail_route_is_registered_next_to_the_index_route(): void
    {
        $path = dirname(__DIR__, 3).self::APP_ROUTES_PATH;
        $src = file_get_contents($path);
        $this->assertNotFalse($src, sprintf('%s must be readable.', $path));
        $routes = (string) $src;

        $indexPosition = strpos($routes, "path: '/medical-records',");
        $this->assertNotFalse(
            $indexPosition,
            'The `/medical-records` index route must stay registered (MRC-SHELL-005).'
        );

        $this->assertMatchesRegularExpression(
            '#path:\s*\'/medical-records/:id\(\\\\\\\\d\+\)\'#',
            $routes,
            '`resources/js/app.js` must register `/medical-records/:id(\\\\d+)` (MRC-SHELL-005).'
        );
        $this->assertStringContainsString(
            "name: 'medical-record-detail'",
            $routes,
            'The detail route must be named `medical-record-detail` (MRC-SHELL-005).'
        );

        $detailPosition = strpos($routes, "name: 'medical-record-detail'");
        $this->assertNotFalse($detailPosition, 'The detail route declaration must exist (MRC-SHELL-005).');
        $this->assertGreaterThan(
            $indexPosition,
            $detailPosition,
            'The detail route must be declared after the index route (MRC-SHELL-005).'
        );

        $detailBlock = substr($routes, $detailPosition, 400);
        $this->assertStringContainsString(
            'beforeEnter: requireAuth',
            $detailBlock,
            'The detail route must be guarded by `requireAuth` (MRC-SHELL-005).'
        );
        $this->assertStringContainsString(
            'MedicalRecordsPage.vue',
            $detailBlock,
            'The detail route must reuse the medical records page component (MRC-SHELL-005).'
        );
    }

    /**
     * MRC-SHELL-006 — evolutions belong to a record, and the workspace loads
     * its surfaces with partial-failure tolerance.
     */
    public function test_evolutions_are_requested_by_record_id(): void
    {
        $src = $this->pageSource();

        $this->assertMatchesRegularExpression(
            '#getEvolutions\(\s*currentRecord\.value\.id\s*\)#',
            $src,
            'Evolutions must be requested with the selected medical record id (MRC-SHELL-006 / D3).'
        );
        $this->assertDoesNotMatchRegularExpression(
            '#getEvolutions\(\s*selectedPatient#',
            $src,
            'Evolutions must never be requested with the patient id (MRC-SHELL-006 / D3).'
        );
        $this->assertStringContainsString(
            'Promise.allSettled',
            $src,
            'The workspace must load its surfaces with `Promise.allSettled` so one failure does not blank the rest (MRC-SHELL-006 / D3).'
        );
        $this->assertMatchesRegularExpression(
            '#getAttachments\(\s*patient\.id\s*\)#',
            $src,
            'Attachments must load with the patient id and no category filter (MRC-SHELL-006 / D5).'
        );
        $this->assertMatchesRegularExpression(
            '#getStats\(\s*patient\.id\s*\)#',
            $src,
            'Stats must load with the patient id (MRC-SHELL-006).'
        );
    }

    /**
     * MRC-SHELL-007 — permissions are computed refs (D7) and confirmation is
     * the child surfaces' job until T4–T6 (D6).
     */
    public function test_page_shell_never_calls_helpers_as_wrong_shapes(): void
    {
        $src = $this->pageSource();

        $this->assertDoesNotMatchRegularExpression(
            '#(?<![\w$.])can\s*\(#',
            $src,
            '`usePermissions().can` is an object of computed refs; calling it throws (MRC-SHELL-007 / D7).'
        );
        $this->assertDoesNotMatchRegularExpression(
            '#(?<![\w$.])confirm\s*\(#',
            $src,
            'The page shell must not call the global `confirm()`; the child surfaces own their dialog until T4–T6 (MRC-SHELL-007 / D6).'
        );
    }

    /**
     * MRC-SHELL-001 — the block entrance mirrors PatientsPage: one spring per
     * block, a `data-reveal` hook each, and the reduced-motion escape hatch.
     */
    public function test_blocks_keep_the_staggered_entrance_contract(): void
    {
        $src = $this->pageSource();

        $this->assertGreaterThanOrEqual(
            3,
            preg_match_all('#\bdata-reveal="#', $src),
            'Each page block must expose its own `data-reveal` hook (MRC-SHELL-001).'
        );
        $this->assertGreaterThanOrEqual(
            3,
            preg_match_all('#\buseSpring\(\{?#', $src),
            'Each page block must ride its own `useSpring` instance (MRC-SHELL-001).'
        );
        $this->assertStringContainsString(
            'prefersReducedMotion',
            $src,
            'The entrance must honour `prefers-reduced-motion` (MRC-SHELL-001).'
        );
        $this->assertStringContainsString(
            'revealStyle(',
            $src,
            'The entrance must render through `revealStyle()` so a block without a spring paints its final state (MRC-SHELL-001).'
        );
    }
}
