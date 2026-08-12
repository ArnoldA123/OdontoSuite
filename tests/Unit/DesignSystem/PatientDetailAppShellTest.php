<?php

namespace Tests\Unit\DesignSystem;

/**
 * PR-pacientes-03 — PatientDetailAppShellTest.
 *
 * Asserts PAC-DET-001 (`PatientDetailPage.vue` 5-tab drawer → `<UiTabs>`),
 * PAC-DEEP-001 (4 cross-category deep-links preserved byte-for-byte),
 * PAC-RT-001 (`useEcho` `patients` + 4 cross-category channel subscriptions
 * preserved byte-for-byte), and the design-level cross-cutting rules
 * (no `border-theme` on the polished sections, no `<style scoped>` block,
 * no legacy focus-ring aliases, no `border-accent text-accent` active tab
 * indicator).
 *
 * The single `PatientDetailPage.vue` file (1480 lines, 53.4 KB) carries the
 * header + 5-tab drawer + 6 tab content panels + audit tab surface that
 * PR-pacientes-03 polishes. The Edit Patient modal (line 706+) is
 * PR-pacientes-04 scope; the section-scoped assertions below cut at the
 * `<!-- Edit Patient Modal -->` marker so the modal's pre-existing
 * `border-theme` literals don't false-positive the polished-section rules.
 *
 * Rules asserted (each parameterized via `polishedFileProvider()` so a
 * failure pinpoints the exact file that regressed):
 *
 *   - PAC-DET-001   5-tab drawer MUST consume `<UiTabs v-model="activeTab">`;
 *                   inline `@click="activeTab = tab.id"` handler MUST be
 *                   absent (replaced by v-model binding); legacy
 *                   `border-accent text-accent` active indicator MUST be
 *                   absent (UiTabs owns the active state).
 *   - PAC-DET-001   Audit tab content MUST consume `<UiCard variant="glass">`
 *                   wrappers + `<UiBadge variant="info">` for the
 *                   action-type indicator (replaces raw `border border-theme
 *                   rounded-lg p-4` list items + dynamic variant badge).
 *   - PAC-DET-001   Change-diff callout MUST use `border-l-2 border-hairline`
 *                   (NOT legacy `border-l-2 border-theme`).
 *   - PAC-DET-001   `<style scoped>` block MUST be removed.
 *   - PAC-DEEP-001  4 cross-category `router.push('/<target>?patient_id=…')`
 *                   calls preserved byte-for-byte (treatment-plans,
 *                   quotations, medical-records, specialty-records).
 *   - PAC-RT-001    `useEcho().channel('patients')` + 4 cross-category
 *                   channel subscriptions (`treatment-plans`, `quotations`,
 *                   `medical-records`, `specialty-records`) preserved.
 *   - PAC-CON-001   `<script>` block stays byte-for-byte (composable
 *                   contracts + refs + watchers + function declarations +
 *                   Echo subscriptions + 4 deep-link functions).
 *
 * Inherited rules (from {@see ModuleAppShellTestCase}, applied via
 * `polishedFileProvider()`): DLR-R-001 canvas surface (overridden to
 * `<AppLayout>` reference), DLR-R-002 no `border-theme` literal
 * (overridden to scope to header/tabs/audit sections), DLR-R-004 no
 * legacy focus-ring aliases (overridden to scope to header/tabs/audit
 * sections), DLR-R-021 no `<style scoped>` block.
 */
class PatientDetailAppShellTest extends ModuleAppShellTestCase
{
    /** PatientDetailPage file path constant — single source of truth for the data provider. */
    private const PATIENT_DETAIL_PATH = '/resources/js/modules/patients/PatientDetailPage.vue';

    /**
     * Marker that demarcates the START of the first tab panel content.
     * Everything BEFORE this comment is the polished header + tab strip
     * region.
     */
    private const DATA_TAB_CONTENT_MARKER = '<!-- Datos del Paciente -->';

    /**
     * Marker that demarcates the START of the audit tab content. The
     * polished surface continues from here until the matching closing
     * `</template>` slot tag.
     */
    private const AUDIT_TAB_CONTENT_MARKER = '<!-- Historial de Auditoría -->';

    /** @return array<int, string> */
    protected static function polishedFiles(): array
    {
        return [
            dirname(__DIR__, 3) . self::PATIENT_DETAIL_PATH,
        ];
    }

    /**
     * Override the inherited `test_page_references_canvas_token` rule. The
     * page mounts inside `<AppLayout>` which provides the canvas surface
     * per DLR-CORE-001 + `canvasRoutes` (PR0 landed). The page file does
     * NOT need a direct `bg-canvas` reference. We pin the `<AppLayout>`
     * reference instead.
     *
     * @dataProvider polishedFileProvider
     */
    public function test_page_references_canvas_token(string $path): void
    {
        $src = self::readSource($path);
        $this->assertNotNull($src, sprintf('%s must exist and be readable.', $path));

        $this->assertTrue(
            (bool) preg_match('/<AppLayout\b/', $src),
            sprintf(
                '%s MUST reference `<AppLayout>` (canvas-surface wrapper per DLR-CORE-001). '
                    . 'The pacientes design acknowledges the page file does not need a direct '
                    . '`bg-canvas` reference — the AppLayout provides it.',
                $path
            )
        );
    }

    /**
     * Override the inherited `test_no_legacy_border_theme_literal` rule.
     * Scope the assertion to the PR-pacientes-03 polished sections:
     *
     *   - The header + tab strip (everything BEFORE the first
     *     `<!-- Datos del Paciente -->` comment — i.e., `<AppLayout>` +
     *     `<PageHeader>` + Patient Info Card + the new `<UiTabs>` nav).
     *   - The audit tab content (between `<!-- Historial de Auditoría -->`
     *     and its closing `</template>` slot tag).
     *
     * The OTHER tab panels (treatment-plans / quotations / medical-records
     * / specialties) intentionally KEEP their legacy `border-theme`
     * literals — those are out of scope for PR-pacientes-03 (their list
     * item wrappers stay as-is until a future polish slice migrates them
     * to `<UiCard>`). Asserting whole-file purity would RED until that
     * later slice lands.
     *
     * The Edit modal at line 706+ still carries `border-theme` literals
     * (header divider + 2 raw `<select>` chromes) and is PR-pacientes-04
     * scope.
     *
     * @dataProvider polishedFileProvider
     */
    public function test_no_legacy_border_theme_literal(string $path): void
    {
        $src = self::readSource($path);
        $this->assertNotNull($src, sprintf('%s must exist and be readable.', $path));

        $section = self::extractPolishedSection($src);

        $this->assertDoesNotMatchRegularExpression(
            '/(?<![\w-])border-theme(?![\w-])/',
            $section,
            sprintf(
                '%s polished sections (header + 5-tab drawer nav + audit tab content) '
                    . 'MUST NOT contain the legacy `border-theme` literal (PAC-DET-001 / DLR-R-002). '
                    . 'Use `border-hairline`. The other tab panels (treatment-plans / quotations '
                    . '/ medical-records / specialties) are out of scope for this PR.',
                $path
            )
        );
    }

    /**
     * Override the inherited `test_no_legacy_focus_ring_alias` rule. Scope
     * the assertion to the PR-pacientes-03 polished sections (header +
     * tab strip + audit tab content). The Edit modal at line 706+ still
     * has `focus:ring-primary-500 focus:border-transparent` on its raw
     * `<select>` chrome and is PR-pacientes-04 scope.
     *
     * @dataProvider polishedFileProvider
     */
    public function test_no_legacy_focus_ring_alias(string $path): void
    {
        $src = self::readSource($path);
        $this->assertNotNull($src, sprintf('%s must exist and be readable.', $path));

        $section = self::extractPolishedSection($src);

        $this->assertSame(
            0,
            preg_match('/(?<![\w-])focus:ring-primary-500(?![\w-])/', $section),
            sprintf(
                '%s polished sections MUST NOT contain the legacy `focus:ring-primary-500` alias '
                    . '(PAC-DET-001 / DLR-R-004). The focus ring lives in the `<UiTabs>` + `<UiCard>` primitives.',
                $path
            )
        );
    }

    /**
     * PAC-DET-001 — the 5-tab drawer's active tab indicator MUST NOT carry
     * the legacy `border-accent text-accent` class strings. `<UiTabs>`
     * owns the active state via its own `bg-systemBlue-500` indicator bar
     * + `text-systemBlue-600` text color; no legacy alias should leak
     * into the polished file.
     *
     * @dataProvider polishedFileProvider
     */
    public function test_detail_no_border_accent_active_indicator(string $path): void
    {
        $src = self::readSource($path);
        $this->assertNotNull($src, sprintf('%s must exist and be readable.', $path));

        $this->assertSame(
            0,
            preg_match('/(?<![\w-])border-accent(?![\w-])/', $src),
            sprintf(
                '%s MUST NOT contain the legacy `border-accent` literal (PAC-DET-001). '
                    . '`<UiTabs>` owns the active indicator.',
                $path
            )
        );

        // `text-accent` was also a legacy alias; pin its absence too.
        $this->assertSame(
            0,
            preg_match('/(?<![\w-])text-accent(?![\w-])/', $src),
            sprintf(
                '%s MUST NOT contain the legacy `text-accent` literal (PAC-DET-001).',
                $path
            )
        );
    }

    /**
     * PAC-DET-001 — the 5-tab drawer MUST consume `<UiTabs v-model="activeTab">`.
     * The inline `@click="activeTab = tab.id"` handler MUST be absent
     * (v-model binding is the sole contract). UiTabs uses `v-model` to
     * propagate the active tab id back to the parent; the inline click
     * handler would silently double-update the ref.
     *
     * @dataProvider polishedFileProvider
     */
    public function test_detail_uses_ui_tabs(string $path): void
    {
        $src = self::readSource($path);
        $this->assertNotNull($src, sprintf('%s must exist and be readable.', $path));

        // POSITIVE: <UiTabs> is referenced for the 5-tab drawer.
        $this->assertTrue(
            (bool) preg_match('/<UiTabs\b/', $src),
            sprintf(
                '%s MUST consume `<UiTabs>` for the 5-tab drawer (PAC-DET-001). '
                    . 'The raw `<button>` step strip is replaced by the primitive.',
                $path
            )
        );

        // POSITIVE: v-model="activeTab" wired to the tab strip.
        $this->assertTrue(
            (bool) preg_match('/<UiTabs\b[^>]*\sv-model\s*=\s*["\']activeTab["\']/', $src),
            sprintf(
                '%s MUST wire `<UiTabs v-model="activeTab">` (PAC-DET-001). '
                    . 'The v-model binding is the sole contract for tab selection.',
                $path
            )
        );

        // NEGATIVE: the inline @click="activeTab = tab.id" handler is absent.
        $this->assertSame(
            0,
            preg_match('/@click\s*=\s*["\']activeTab\s*=\s*tab\.id["\']/', $src),
            sprintf(
                '%s MUST NOT carry the inline `@click="activeTab = tab.id"` handler (PAC-DET-001). '
                    . 'The v-model binding is the sole contract — the inline handler would '
                    . 'silently double-update the ref and bypass UiTabs\'s keyboard navigation.',
                $path
            )
        );
    }

    /**
     * PAC-DEEP-001 — the 4 cross-category `router.push(...)` deep-links
     * MUST be preserved byte-for-byte. These calls are the navigation
     * contract between `PatientDetailPage` and the 4 sibling modules
     * (treatment-plans, quotations, medical-records, specialty-records);
     * a UI refactor that drops the `?patient_id=…` query param breaks the
     * contract and silently strands the user on the destination module
     * without the patient filter applied.
     *
     * The deep-links live in the `<script>` block (function declarations
     * `createTreatmentPlan`, `createQuotation`, `createMedicalRecord`,
     * `createSpecialtyRecord`). The template wires each tab's "Nuevo X"
     * button to those functions; neither side may be edited.
     *
     * @dataProvider polishedFileProvider
     */
    public function test_detail_cross_category_deep_links_preserved(string $path): void
    {
        $src = self::readSource($path);
        $this->assertNotNull($src, sprintf('%s must exist and be readable.', $path));

        $targets = [
            '/treatment-plans?patient_id=',
            '/quotations?patient_id=',
            '/medical-records?patient_id=',
            '/specialty-records?patient_id=',
        ];

        foreach ($targets as $target) {
            $this->assertTrue(
                (bool) preg_match(
                    '/router\.push\s*\(\s*[`\'"][^`\'"]*' . preg_quote($target, '/') . '/',
                    $src
                ),
                sprintf(
                    '%s MUST preserve `router.push(` to a target containing `%s…` (PAC-DEEP-001). '
                        . 'The 4 deep-link navigations are the contract between this module and the '
                        . '4 sibling modules; a UI refactor must not drop the `?patient_id=…` query param.',
                    $path,
                    $target
                )
            );
        }
    }

    /**
     * PAC-RT-001 — the `useEcho` channel subscriptions MUST stay subscribed
     * byte-for-byte. The 5 channels are: `patients` (`.patient.updated`),
     * `treatment-plans` (`.treatment-plan.{created,updated,deleted}`),
     * `quotations` (`.quotation.{created,updated,approved}`),
     * `medical-records` (`.medical-record.{created,updated}` +
     * `.clinical-evolution.created` + `.clinical-attachment.created`),
     * `specialty-records` (`.specialty-record.{created,updated}`).
     *
     * The channels are the realtime pipe that keeps the per-tab create
     * buttons in sync with cross-module events; a `<script>` edit that
     * accidentally removes a subscription silently breaks cross-tab
     * realtime updates.
     *
     * @dataProvider polishedFileProvider
     */
    public function test_detail_echo_channels_preserved(string $path): void
    {
        $src = self::readSource($path);
        $this->assertNotNull($src, sprintf('%s must exist and be readable.', $path));

        $channels = [
            'patients',
            'treatment-plans',
            'quotations',
            'medical-records',
            'specialty-records',
        ];

        foreach ($channels as $channelName) {
            $this->assertTrue(
                (bool) preg_match(
                    "/channel\\s*\\(\\s*['\"]" . preg_quote($channelName, '/') . "['\"]\\s*\\)/",
                    $src
                ),
                sprintf(
                    '%s MUST keep `channel(\'%s\')` subscription (PAC-RT-001). '
                        . 'The 5 Echo channels are the realtime pipe for cross-module patient events; '
                        . 'a `<script>` edit that accidentally removes a subscription silently breaks '
                        . 'cross-tab realtime updates.',
                    $path,
                    $channelName
                )
            );
        }
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
     * Extract the PR-pacientes-03 polished surface from the full file.
     * The polished surface is the UNION of two regions:
     *
     *   1. Header + tab strip: from `<template>` to the opening
     *      `<!-- Datos del Paciente -->` comment marker (the start of the
     *      first tab panel's content). This covers the `<AppLayout>` +
     *      `<PageHeader>` + Patient Info Card + the new `<UiTabs>` nav.
     *   2. Audit tab content: from `<!-- Historial de Auditoría -->` to
     *      the closing `</template>` slot tag of the audit slot. This
     *      covers the audit log list with `<UiCard>` wrappers.
     *
     * The OTHER tab panels (treatment-plans / quotations / medical-records
     * / specialties) are intentionally EXCLUDED — they keep their legacy
     * `border-theme` literals until a future polish slice migrates them
     * to `<UiCard>`. The Edit modal at line 706+ is also EXCLUDED (it's
     * PR-pacientes-04 scope).
     *
     * The concatenation is what the section-scoped rules scan. If either
     * region is missing, the test fails RED loudly.
     */
    private static function extractPolishedSection(string $src): string
    {
        $pieces = [];

        // Region 1: header + tab strip (everything BEFORE the first tab
        // panel content marker).
        $firstTabStart = strpos($src, self::DATA_TAB_CONTENT_MARKER);
        if ($firstTabStart !== false) {
            $pieces[] = substr($src, 0, $firstTabStart);
        }

        // Region 2: audit tab content (between the marker comment + its
        // closing </template> slot tag).
        $auditStart = strpos($src, self::AUDIT_TAB_CONTENT_MARKER);
        if ($auditStart !== false) {
            // Find the matching closing </template> AFTER the audit start.
            $auditEnd = strpos($src, '</template>', $auditStart);
            if ($auditEnd !== false) {
                $pieces[] = substr($src, $auditStart, $auditEnd - $auditStart + strlen('</template>'));
            }
        }

        return implode("\n", $pieces);
    }
}
