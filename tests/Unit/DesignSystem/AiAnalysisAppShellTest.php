<?php

namespace Tests\Unit\DesignSystem;

/**
 * Issue #218 — `AiAnalysisButton` leaves the scoped stylesheet and the legacy
 * colour aliases behind.
 *
 * Scope:
 *
 *   - `AiAnalysisPage.vue` (page shell) inherits the full base contract,
 *     including DLR-R-001 canvas surface.
 *   - `components/AiAnalysisButton.vue` (embedded component) inherits the
 *     style-discipline rules only: no `border-theme`, tokenised focus ring,
 *     no legacy focus aliases, no `<style scoped>`. A button never paints the
 *     page canvas, so DLR-R-001 is intentionally page-only.
 *
 * Button rules pinned here:
 *
 *   - AIB-SHELL-001  no `<style>` block at all; the component speaks utility
 *                    classes and design tokens.
 *   - AIB-SHELL-002  no legacy colour aliases (`bg-primary-*`,
 *                    `*-badge`, `bg-theme-surface*`) and no custom
 *                    `status-*` / `review-*` classes.
 *   - AIB-SHELL-003  the action rides `UiButton` and the review decision rides
 *                    `UiStatusPill`; no raw `<button>`.
 *   - AIB-SHELL-004  module labels still come from `getStatusLabel()` and
 *                    `getReviewDecisionLabel()`.
 *   - AIB-SHELL-005  the permission gate stays
 *                    `can['ai-analysis.analyze']?.value` (object of computed
 *                    refs, never `can(...)`) and the category gate stays
 *                    `radiografia` + `file_type === 'image'`.
 */
class AiAnalysisAppShellTest extends ModuleAppShellTestCase
{
    private const PAGE_PATH = '/resources/js/modules/ai-analysis/AiAnalysisPage.vue';

    private const BUTTON_PATH = '/resources/js/modules/ai-analysis/components/AiAnalysisButton.vue';

    /** @return array<int, string> */
    protected static function polishedFiles(): array
    {
        return [dirname(__DIR__, 3).self::PAGE_PATH];
    }

    /** @return array<int, string> */
    protected static function embeddedFiles(): array
    {
        return [dirname(__DIR__, 3).self::BUTTON_PATH];
    }

    private function buttonSource(): string
    {
        $path = dirname(__DIR__, 3).self::BUTTON_PATH;
        $src = file_get_contents($path);
        $this->assertNotFalse($src, sprintf('%s must be readable.', $path));

        return (string) $src;
    }

    /** AIB-SHELL-001 — the embedded action has no stylesheet of its own. */
    public function test_button_has_no_style_block(): void
    {
        $src = $this->buttonSource();

        $this->assertDoesNotMatchRegularExpression(
            '#<style\b#',
            $src,
            'AiAnalysisButton.vue must not declare a `<style>` block (AIB-SHELL-001).'
        );
    }

    /** AIB-SHELL-002 — the issue's legacy aliases are gone for good. */
    public function test_button_drops_the_legacy_color_aliases(): void
    {
        $src = $this->buttonSource();

        $legacyAliases = [
            'bg-primary-600',
            'hover:bg-primary-700',
            'bg-warning-badge',
            'bg-primary-50',
            'text-primary-700',
            'bg-success-badge',
            'bg-danger-badge',
            'bg-theme-surface',
            'bg-theme-surface-elevated',
        ];

        foreach ($legacyAliases as $alias) {
            $this->assertStringNotContainsString(
                $alias,
                $src,
                sprintf(
                    'AiAnalysisButton.vue must not contain the legacy `%s` alias (AIB-SHELL-002).',
                    $alias
                )
            );
        }

        $this->assertDoesNotMatchRegularExpression(
            '#\bstatus-(?:pending|processing|completed|failed|unknown)\b#',
            $src,
            'AiAnalysisButton.vue must not hand-build `status-*` classes; the pill primitive owns them (AIB-SHELL-002).'
        );
        $this->assertDoesNotMatchRegularExpression(
            '#\breview-(?:accepted|rejected|partial|unknown)\b#',
            $src,
            'AiAnalysisButton.vue must not hand-build `review-*` classes; the pill primitive owns them (AIB-SHELL-002).'
        );
    }

    /** AIB-SHELL-003 — the primitives carry the construction. */
    public function test_button_rides_the_ui_primitives_and_tokens(): void
    {
        $src = $this->buttonSource();

        $this->assertMatchesRegularExpression(
            '#<UiButton\b#',
            $src,
            'The action must ride `UiButton` (AIB-SHELL-003).'
        );
        $this->assertMatchesRegularExpression(
            '#<UiStatusPill\b#',
            $src,
            'The review decision must ride `UiStatusPill` (AIB-SHELL-003).'
        );
        $this->assertDoesNotMatchRegularExpression(
            '#<button\b#',
            $src,
            'The embedded action must not hand-build a raw `<button>` (AIB-SHELL-003).'
        );

        $this->assertStringContainsString(
            'text-label',
            $src,
            'The status label must consume the `text-label` token utility (AIB-SHELL-003).'
        );
        $this->assertStringContainsString(
            'text-theme-secondary',
            $src,
            'Secondary metadata must consume `text-theme-secondary` (AIB-SHELL-003).'
        );
        $this->assertMatchesRegularExpression(
            '#(?:rounded-ios|var\(--radius-control\))#',
            $src,
            'The component must ride the control radius token (`rounded-ios` / `--radius-control`) (AIB-SHELL-003).'
        );
        $this->assertMatchesRegularExpression(
            '#<UiButton\b[\s\S]{0,300}?variant="primary"#',
            $src,
            'The create action must ride the primary variant, whose accent construction lives in `UiButton` (AIB-SHELL-003).'
        );
    }

    /** AIB-SHELL-004 — labels keep coming from the module composable. */
    public function test_button_keeps_the_module_state_labels(): void
    {
        $src = $this->buttonSource();

        $this->assertStringContainsString(
            'getStatusLabel(analysis.status)',
            $src,
            'The status label must come from `getStatusLabel()` (AIB-SHELL-004).'
        );
        $this->assertStringContainsString(
            'getReviewDecisionLabel(analysis.review_decision)',
            $src,
            'The review label must come from `getReviewDecisionLabel()` (AIB-SHELL-004).'
        );
        $this->assertStringContainsString(
            'Analizar con IA',
            $src,
            'The idle action must keep the `Analizar con IA` label (AIB-SHELL-004).'
        );
        $this->assertStringContainsString(
            'Analizando...',
            $src,
            'The in-flight action must keep the `Analizando...` label (AIB-SHELL-004).'
        );
        $this->assertStringContainsString(
            'analysis.reviewed',
            $src,
            'The reviewed state must render the review decision (AIB-SHELL-004).'
        );
    }

    /** AIB-SHELL-005 — permission and category gates stay byte-for-byte. */
    public function test_button_keeps_the_permission_and_category_gate(): void
    {
        $src = $this->buttonSource();

        $this->assertStringContainsString(
            "can['ai-analysis.analyze']?.value",
            $src,
            'The permission gate must stay `can[\'ai-analysis.analyze\']?.value` (AIB-SHELL-005).'
        );
        $this->assertDoesNotMatchRegularExpression(
            '#(?<![\w$.])can\s*\(#',
            $src,
            '`usePermissions().can` is an object of computed refs; calling it throws (AIB-SHELL-005).'
        );
        $this->assertStringContainsString(
            "attachment.category === 'radiografia'",
            $src,
            'The category gate must stay `attachment.category === \'radiografia\'` (AIB-SHELL-005).'
        );
        $this->assertStringContainsString(
            "attachment.file_type === 'image'",
            $src,
            'The category gate must stay `attachment.file_type === \'image\'` (AIB-SHELL-005).'
        );
        $this->assertStringContainsString(
            'getAnalysisByAttachment(props.attachmentId)',
            $src,
            'Mount must load the existing analysis through `getAnalysisByAttachment` (AIB-SHELL-005).'
        );
        $this->assertStringContainsString(
            'analyzeImage(props.attachmentId)',
            $src,
            'The action must call `analyzeImage` (AIB-SHELL-005).'
        );
        $this->assertStringContainsString(
            "emit('analysisCompleted'",
            $src,
            'The component must keep the `analysisCompleted` emit (AIB-SHELL-005).'
        );
        $this->assertStringContainsString(
            "emit('viewAnalysis'",
            $src,
            'The component must keep the `viewAnalysis` emit (AIB-SHELL-005).'
        );
    }
}
