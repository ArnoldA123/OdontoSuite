<?php

namespace Tests\Unit\DesignSystem;

use PHPUnit\Framework\TestCase;

use Tests\Support\SourceGrep;

/**
 * PR3 — Dashboard + AppShell anti-requirement guards (PR3 slice scope).
 *
 * Covers the static grep / source-inspection checks the orchestrator
 * specified in the Definition of Done. Each test is RED before the PR3
 * implementation lands and GREEN after:
 *
 *   3.7.X  pnpm build + php artisan test exit code 0
 *   3.7.5  `grep -n "linear-gradient\|bg-gradient"` in DashboardPage.vue returns 0
 *   3.7.5  `grep -n "h-screen\|height: 100vh"` in AppLayout.vue returns 0
 *   3.7.5  DashboardPage.vue contains no `<style scoped>` block
 *   3.7.5  AppLayout.vue uses `surface-glass` for chrome (sidebar + topbar)
 *   3.7.5  AppLayout.vue uses `min-h-[100dvh]` not `h-screen`
 *   3.7.5  DashboardPage.vue uses `UiStatusPill` for cash status (collapsed quadruple)
 *   3.7.6  `grep -rn "images/pexels"` in resources/js/ returns 0
 *
 * These are source-inspection tests (not DOM); they are the PR3 regression
 * gate a reviewer can re-run cheaply.
 */
class DashboardAppShellTest extends TestCase
{
    private static function projectRootPath(): string { return dirname(__DIR__, 3); }

    private const DASHBOARD_FILE = '/resources/js/modules/dashboard/DashboardPage.vue';
    private const APP_LAYOUT_FILE = '/resources/js/components/layout/AppLayout.vue';
    private const FAB_FILE = '/resources/js/components/layout/FloatingActionButton.vue';

    public static function setUpBeforeClass(): void
    {
        // No bootstrap needed — these tests shell out to ripgrep on the
        // filesystem and read files directly.
    }

    private static function readFile(string $absPath): ?string
    {
        if (!is_file($absPath)) {
            return null;
        }
        $src = file_get_contents($absPath);
        return $src === false ? null : $src;
    }

    private static function grepCount(string $pattern, string $rootPath): int
    {
        return SourceGrep::count($pattern, $rootPath);
    }

    private static function grepLines(string $pattern, string $path): array
    {
        return SourceGrep::lines($pattern, $path);
    }

    /**
     * DoD #4 — DashboardPage.vue must contain zero gradients of any kind.
     */
    public function test_dashboard_page_no_linear_or_class_gradients(): void
    {
        $path = self::projectRootPath() . self::DASHBOARD_FILE;
        $this->assertFileExists($path, 'DashboardPage.vue must exist');

        $gradients = self::grepCount('linear-gradient|bg-gradient', $path);
        $this->assertSame(
            0,
            $gradients,
            "DashboardPage.vue must contain zero `linear-gradient` / `bg-gradient` declarations (DoD #4). Found: " . $gradients
        );
    }

    /**
     * DoD #4 — DashboardPage.vue must not embed `<style scoped>` blocks.
     * The 149-LOC inline style block is being deleted in PR3 and replaced
     * with primitive + Tailwind classes.
     */
    public function test_dashboard_page_no_scoped_style_block(): void
    {
        $src = self::readFile(self::projectRootPath() . self::DASHBOARD_FILE);
        $this->assertNotNull($src);
        // Look for `<style ...>` (optionally with `scoped`).
        $matches = preg_match_all('/<style\b[^>]*>/i', (string) $src);
        $this->assertSame(
            0,
            (int) $matches,
            'DashboardPage.vue must contain zero <style> blocks (DoD #4). Found: ' . $matches
        );
    }

    /**
     * DoD #4 — DashboardPage.vue must not contain any hand-written hex literals.
     * All color values must come from the token layer (tokens.js / Tailwind
     * classes / CSS custom properties).
     */
    public function test_dashboard_page_no_hex_literals(): void
    {
        $path = self::projectRootPath() . self::DASHBOARD_FILE;
        $hex = self::grepCount('#[0-9a-fA-F]{6}', $path);
        $this->assertSame(
            0,
            $hex,
            'DashboardPage.vue must contain zero hand-written #RRGGBB hex literals. Found: ' . $hex
        );
    }

    /**
     * PR3 dashboard content guard — DashboardPage.vue must render cash
     * status through a primitive that supports custom Spanish labels, and
     * must NOT pass an English status key ('open' / 'closed' /
     * 'no_session') to a primitive whose status map only knows
     * appointment / plan keys. The fix replaces the previous <UiStatusPill
     * :status="cashStatusPillStatus"> (which leaked raw 'open' to the
     * DOM) with a <UiBadge variant="..."> + Spanish label in the slot.
     */
    public function test_dashboard_collapses_cash_status_into_status_pill(): void
    {
        $path = self::projectRootPath() . self::DASHBOARD_FILE;
        $src = (string) self::readFile($path);
        $this->assertNotNull($src);

        // The cash pill renders through <UiBadge ... data-cash-pill>, never
        // <UiStatusPill> (StatusPill's STATUS_MAP doesn't know 'open'/
        // 'closed' / 'no_session' and would fall through to the raw key).
        $badgePillCount = preg_match_all('/<UiBadge\b[^>]*data-cash-pill\b/i', $src);
        $this->assertGreaterThanOrEqual(
            1,
            (int) $badgePillCount,
            'DashboardPage.vue must render cash status via a <UiBadge data-cash-pill> primitive that supports custom labels.'
        );

        $oldStatusPill = preg_match_all('/<UiStatusPill\b[^>]*\bcashStatusPillStatus\b/i', $src);
        $this->assertSame(
            0,
            (int) $oldStatusPill,
            'DashboardPage.vue must not bind cash status to <UiStatusPill> — that primitive has no entry for the cash states and would print the raw key.'
        );

        // Legacy computed quartet must be gone.
        $this->assertStringNotContainsString(
            'cashStatusClass',
            $src,
            'DashboardPage.vue must not redeclare the cashStatusClass computed.'
        );
        $this->assertStringNotContainsString(
            'cashStatusIconClass',
            $src,
            'DashboardPage.vue must not redeclare the cashStatusIconClass computed.'
        );
        $this->assertStringNotContainsString(
            'cashStatusIconColor',
            $src,
            'DashboardPage.vue must not redeclare the cashStatusIconColor computed.'
        );

        // The old (broken) computed that returned raw keys must be gone.
        $this->assertStringNotContainsString(
            'cashStatusPillStatus',
            $src,
            'DashboardPage.vue must not expose the old cashStatusPillStatus ref that returned raw English status keys.'
        );

        // Spanish labels must be present in the source.
        $this->assertStringContainsString("'Abierta'", $src);
        $this->assertStringContainsString("'Cerrada'", $src);
        $this->assertStringContainsString("'Sin sesión'", $src);
    }

    /**
     * Belt-and-braces: the raw English cash keys ('open' / 'closed' /
     * 'no_session') MUST NOT appear as string literals anywhere in the
     * SCRIPT section outside the cashStatusPillState computed, and MUST
     * NOT appear anywhere in the TEMPLATE section at all. Any
     * DOM-bound expression that returns one of those keys would print
     * English in a Spanish UI (the bug the user reported).
     *
     * The cashStatusPillState computed block (the legitimate home for
     * those keys — they ride as the data-cash-pill-state attribute only)
     * is stripped from the script body before the check runs.
     *
     * The test is intentionally strict: a future contributor who reaches
     * for one of these strings and wires it to a primitive's prop binding
     * will fail the test, even if the visible DOM hasn't yet regressed.
     */
    public function test_dashboard_no_raw_english_cash_status_keys_appear_in_source(): void
    {
        $path = self::projectRootPath() . self::DASHBOARD_FILE;
        $src = (string) self::readFile($path);
        $this->assertNotNull($src);

        // TEMPLATE check: the keys must never appear in <template>...</template>
        // (they could be bound via {{ }}, v-text, or any prop binding).
        $template = '';
        if (preg_match('/<template>([\s\S]*?)<\/template>/', $src, $m)) {
            $template = $m[1];
        }
        foreach (["'open'", "'closed'", "'no_session'"] as $needle) {
            $this->assertStringNotContainsString(
                $needle,
                $template,
                "DashboardPage.vue template must not contain the raw English cash key {$needle} (would leak English to the DOM)."
            );
        }

        // SCRIPT check: locate the <script setup> block, strip the
        // cashStatusPillState computed (the only legitimate home), then
        // assert no other code references the raw keys.
        $script = '';
        if (preg_match('/<script\s+setup>([\s\S]*?)<\/script>/', $src, $m)) {
            $script = $m[1];
        }

        // Strip the cashStatusPillState computed (the only allowed home for
        // these keys). The trailing semicolon is optional — this codebase omits
        // it, and requiring it made the strip silently match nothing, so the
        // test failed on the very block it was meant to exempt.
        $scriptStripped = preg_replace(
            '/const\s+cashStatusPillState\s*=\s*computed\(\s*\(\)\s*=>\s*\{[\s\S]*?\}\s*\)\s*;?/m',
            '',
            $script
        );
        $this->assertNotNull($scriptStripped);

        // Strip comments before asserting. The keys are named in the comments
        // that explain why they must not be passed to a primitive, and a
        // comment cannot reach the DOM — matching them punished the code for
        // documenting itself.
        $scriptStripped = preg_replace('#/\*[\s\S]*?\*/#', '', (string) $scriptStripped);
        $scriptStripped = preg_replace('#(^|\s)//[^\n]*#', '', (string) $scriptStripped);

        foreach (["'open'", "'closed'", "'no_session'"] as $needle) {
            $this->assertStringNotContainsString(
                $needle,
                $scriptStripped,
                "DashboardPage.vue script section must not contain the raw English cash key {$needle} outside the cashStatusPillState computed (would leak English to the DOM if wired to a primitive)."
            );
        }
    }

    /**
     * DoD #4 / #5 — AppLayout.vue must use the `.surface-glass` class for
     * the sidebar and top bar (chrome only). Need at least two uses (sidebar
     * + topbar).
     */
    public function test_app_layout_uses_surface_glass_for_chrome(): void
    {
        $path = self::projectRootPath() . self::APP_LAYOUT_FILE;
        $this->assertFileExists($path);

        $count = self::grepCount('surface-glass', $path);
        $this->assertGreaterThanOrEqual(
            2,
            $count,
            'AppLayout.vue must apply .surface-glass to at least two chrome surfaces (sidebar + topbar). Found: ' . $count
        );
    }

    /**
     * DoD #5 — AppLayout.vue must NOT use `h-screen` or `height: 100vh`.
     * The spec requires `min-h-[100dvh]` (dynamic viewport height) instead.
     */
    public function test_app_layout_no_h_screen_or_height_vh(): void
    {
        $path = self::projectRootPath() . self::APP_LAYOUT_FILE;
        $src = (string) self::readFile($path);
        $this->assertNotNull($src);

        $hScreen = preg_match_all('/\bh-screen\b/', $src);
        $this->assertSame(
            0,
            (int) $hScreen,
            'AppLayout.vue must not use h-screen (use min-h-[100dvh]). Found: ' . $hScreen
        );

        $vh = preg_match_all('/height\s*:\s*100vh/i', $src);
        $this->assertSame(
            0,
            (int) $vh,
            'AppLayout.vue must not use `height: 100vh`. Found: ' . $vh
        );
    }

    /**
     * DoD #5 — AppLayout.vue must use `min-h-[100dvh]` so mobile browser
     * chrome doesn't clip content.
     */
    public function test_app_layout_uses_min_dvh(): void
    {
        $path = self::projectRootPath() . self::APP_LAYOUT_FILE;
        $minDvh = self::grepCount('min-h-\\[100dvh\\]', $path);
        $this->assertGreaterThanOrEqual(
            1,
            $minDvh,
            'AppLayout.vue must use min-h-[100dvh] for full-height pages (DoD #5). Found: ' . $minDvh
        );
    }

    /**
     * DoD #2 — Quick actions must NOT be rendered in a 5-column grid at
     * any breakpoint. A 5-up grid at 1440 px gave each card only ~70 px
     * of text space, which clipped every subtitle. Quick actions are
     * actions, not a stat row, so they don't need to match the stats
     * grid. The contract: cap at 3 columns at lg+, never 5.
     *
     * The stats grid (5-col at lg+) legitimately uses a 5-up layout — this
     * assertion scopes its check to the region BETWEEN the
     * "Cargando acciones rápidas" / "Acciones Rápidas" heading and the
     * "Citas de Hoy" heading (the quick-actions + its loading skeleton),
     * not the whole file.
     */
    public function test_quick_actions_grid_capped_at_three_columns(): void
    {
        $path = self::projectRootPath() . self::DASHBOARD_FILE;
        $src = (string) self::readFile($path);
        $this->assertNotNull($src);

        // Whole file: the quick-actions grid class must include
        // lg:grid-cols-3.
        $this->assertStringContainsString(
            'lg:grid-cols-3',
            $src,
            'DashboardPage.vue quick-actions must use lg:grid-cols-3 (3 cols at lg+), not 5.'
        );

        // Scope to the two quick-action regions by their <section> aria-labels:
        // the skeleton ("Cargando acciones rápidas") and the loaded grid
        // ("Acciones rápidas"). Scoping by prose markers instead swallowed the
        // stats grid, which legitimately uses 5 columns, and failed the wrong
        // section. T3 adds a `v-if="!statsError"` binding to the loaded
        // quick-actions section, so the anchor matches the aria-label anywhere
        // inside the <section> opening tag instead of requiring the literal
        // `<section aria-label=` prefix.
        foreach (['Cargando acciones rápidas', 'Acciones rápidas'] as $label) {
            $found = preg_match(
                '/<section\b[^>]*aria-label="' . preg_quote($label, '/') . '"/',
                $src,
                $matches,
                PREG_OFFSET_CAPTURE
            );
            $start = $found === 1 ? $matches[0][1] : false;
            $this->assertNotFalse(
                $start,
                'DashboardPage.vue must contain a <section aria-label="' . $label . '">'
            );

            $end = strpos($src, '</section>', $start);
            $this->assertNotFalse($end, 'Section "' . $label . '" must be closed');
            $region = substr($src, $start, $end - $start);

            $this->assertDoesNotMatchRegularExpression(
                '/grid-cols-(4|5|6)\b/',
                $region,
                'Quick-actions section "' . $label . '" must not use a 4/5/6-column grid — '
                    . 'the Spanish labels are clipped at that width.'
            );
        }
    }


    /**
     * DoD #2 — The chevron SVG inside each quick action card consumes
     * horizontal space the label needs. Cards are whole-card clickable,
     * so the chevron is decorative and must not be present on the
     * quick-action card. The previous 5-col layout had a 16 px chevron
     * per card; on a ~70 px card that ate ~20% of the text budget.
     *
     * Scope: only the elements marked `data-action="..."` (the actual
     * quick-action cards). The "Ver calendario" / "Ver todas" CTAs in the
     * section headers legitimately use chevrons and are not in scope.
     */
    public function test_quick_action_cards_have_no_chevron_svg(): void
    {
        $path = self::projectRootPath() . self::DASHBOARD_FILE;
        $src = (string) self::readFile($path);

        // Match every `<UiCard ... data-action="..." ...> ... </UiCard>` block
        // and assert none of them contain the right-chevron path.
        preg_match_all(
            '/<UiCard[^>]*\bdata-action="[^"]+"[^>]*>[\s\S]*?<\/UiCard>/',
            $src,
            $matches
        );
        $cards = $matches[0] ?? [];
        $this->assertGreaterThanOrEqual(
            4,
            count($cards),
            'DashboardPage.vue must contain at least 4 data-action cards (the 4 verified action labels).'
        );
        foreach ($cards as $idx => $card) {
            $this->assertDoesNotMatchRegularExpression(
                '/M9 5l7 7-7 7/',
                $card,
                "Quick-action card #{$idx} must not contain a chevron SVG (it consumed space the label needed)."
            );
        }
    }

    /**
     * DoD #2 — Quick-action subtitles (the descriptive `<p>` after the
     * title, e.g. "Gestionar base de datos") must NOT have `truncate`
     * (overflow: hidden + ellipsis) because Spanish copy runs ~25%
     * longer than English. The truncated state was the clip that the
     * user reported.
     *
     * Scope: the `<p>` paragraphs inside each `data-action` card.
     */
    public function test_quick_action_subtitles_do_not_truncate(): void
    {
        $path = self::projectRootPath() . self::DASHBOARD_FILE;
        $src = (string) self::readFile($path);

        preg_match_all(
            '/<UiCard[^>]*\bdata-action="[^"]+"[^>]*>[\s\S]*?<\/UiCard>/',
            $src,
            $matches
        );
        $cards = $matches[0] ?? [];
        $this->assertGreaterThanOrEqual(
            4,
            count($cards),
            'DashboardPage.vue must contain at least 4 data-action cards.'
        );
        foreach ($cards as $idx => $card) {
            $this->assertDoesNotMatchRegularExpression(
                '/<p[^>]*\btruncate\b[^>]*>/i',
                $card,
                "Quick-action card #{$idx} must not use `truncate` on its subtitle <p>."
            );
        }
    }

    /**
     * DoD #1 — FloatingActionButton.vue (a chrome button used in the layout
     * cluster) must not contain gradient class names. Gradients are
     * decoration; this is a clinical tool.
     */
    public function test_floating_action_button_no_gradient_classes(): void
    {
        $path = self::projectRootPath() . self::FAB_FILE;
        $gradients = self::grepCount('bg-gradient-to-|bg-gradient ', $path);
        $this->assertSame(
            0,
            $gradients,
            'FloatingActionButton.vue must not use bg-gradient classes (DoD #1 - no gradients-as-decoration). Found: ' . $gradients
        );
    }

    /**
     * DoD #4 — DashboardPage.vue + AppLayout.vue must contain zero hex literals.
     * Combined: every color comes from the token layer.
     */
    public function test_dashboard_and_layout_combined_no_hex_literals(): void
    {
        $dir = self::projectRootPath() . '/resources/js/modules/dashboard/';
        $this->assertDirectoryExists($dir);

        $layoutDir = self::projectRootPath() . '/resources/js/components/layout/';
        $this->assertDirectoryExists($layoutDir);

        $hexCount = SourceGrep::count('#[0-9a-fA-F]{6}', $dir, $layoutDir);

        $this->assertSame(
            0,
            $hexCount,
            'Dashboard + layout files must contain zero #RRGGBB hex literals. Found: ' . $hexCount
        );
    }

    /**
     * DoD #1 — `images/pexels` must not appear in any JS source file.
     * Photography is reserved for the Login hero and 404 page only.
     */
    public function test_no_pexels_image_references_in_js_source(): void
    {
        $dir = self::projectRootPath() . '/resources/js/';
        $this->assertDirectoryExists($dir);

        $peels = self::grepCount('images/pexels', $dir);
        $this->assertSame(
            0,
            $peels,
            'resources/js/ must not reference images/pexels (DoD #1 - photography reserved for Login + 404). Found: ' . $peels
        );
    }

    /**
     * DoD — DashboardPage.vue must consume the PR2 card primitive
     * (`UiCard`) and the page must show 5 stat cards, all using
     * `variant="glass"` per the spec. Even when permission-gating hides
     * some, the source still emits them — the gating happens at the
     * template level via `v-if`.
     *
     * WU1 — verified stat-card labels (daily-operations strip): "Citas Hoy",
     * "Atendidas Hoy", "Por Atender", "Saldo de Caja" (D1). The historical
     * metrics (Pacientes, Citas del Mes, Ingresos) left for BI (D2) and the
     * retired stat handles must be gone.
     */
    public function test_dashboard_contains_all_four_daily_operations_stat_card_labels(): void
    {
        $path = self::projectRootPath() . self::DASHBOARD_FILE;
        $src = (string) self::readFile($path);
        $this->assertNotNull($src);

        $labels = ['Citas Hoy', 'Atendidas Hoy', 'Por Atender', 'Saldo de Caja'];
        foreach ($labels as $label) {
            $this->assertStringContainsString(
                $label,
                $src,
                "DashboardPage.vue must render the stat-card label \"{$label}\" (verified content)."
            );
        }

        $retired = [
            'data-stat-card="total-patients"',
            'data-stat-card="total-appointments-month"',
            'data-stat-card="total-income"',
            'data-stat-card="cash-status"',
        ];
        foreach ($retired as $handle) {
            $this->assertStringNotContainsString(
                $handle,
                $src,
                "DashboardPage.vue must not keep the retired KPI handle {$handle} (WU1: four daily-operations cards, D1/D2)."
            );
        }
    }

    /**
     * T3 — DashboardPage.vue must contain the four verified quick-action
     * labels after the "Nueva Cita" tile was removed (single CTA per
     * destination: the agenda header owns the primary appointment CTA).
     * T6 — the /business-intelligence tile is named after the sidebar entry
     * ("Business Intelligence"), not the retired "Reportes" alias.
     */
    public function test_dashboard_contains_all_four_verified_quick_action_labels(): void
    {
        $path = self::projectRootPath() . self::DASHBOARD_FILE;
        $src = (string) self::readFile($path);
        $this->assertNotNull($src);

        $labels = ['Pacientes', 'Profesionales', 'Ambientes', 'Business Intelligence'];
        foreach ($labels as $label) {
            // "Pacientes" appears in BOTH the stat card and the quick
            // actions; each label must be present at least once.
            $this->assertGreaterThanOrEqual(
                1,
                substr_count($src, $label),
                "DashboardPage.vue must render the quick-action label \"{$label}\" (verified content)."
            );
        }

        // The removed tile used the title-case label; the agenda header
        // keeps the sentence-case "Nueva cita" CTA. Match the rendered
        // element text so a design-record comment naming the removed tile
        // cannot fail the guard.
        $this->assertDoesNotMatchRegularExpression(
            '/>\s*Nueva Cita\s*</',
            $src,
            'DashboardPage.vue must not render the removed "Nueva Cita" quick-action tile (T3 single CTA per destination).'
        );
    }

    /**
     * T3 — "Ver calendario" must be rendered exactly once: on the Acciones
     * rápidas header. The Agenda de hoy and Próximas citas headers no
     * longer duplicate it, and every other calendar affordance is gone.
     */
    public function test_dashboard_renders_single_ver_calendario_cta(): void
    {
        $path = self::projectRootPath() . self::DASHBOARD_FILE;
        $src = (string) self::readFile($path);
        $this->assertNotNull($src);

        $this->assertSame(
            1,
            substr_count($src, 'Ver calendario'),
            'DashboardPage.vue must render exactly one "Ver calendario" CTA (T3 single CTA per destination).'
        );
        $this->assertSame(
            1,
            substr_count($src, '@click="goToCalendar"'),
            'DashboardPage.vue must bind goToCalendar to the single remaining "Ver calendario" CTA only.'
        );

        $quickActions = $this->sectionRegion($src, 'Acciones rápidas');
        $this->assertStringContainsString(
            'Ver calendario',
            $quickActions,
            'DashboardPage.vue must keep the "Ver calendario" CTA in the Acciones rápidas header.'
        );

        foreach (['Agenda de hoy', 'Próximas citas'] as $label) {
            $this->assertStringNotContainsString(
                'Ver calendario',
                $this->sectionRegion($src, $label),
                "DashboardPage.vue section \"{$label}\" must not duplicate the \"Ver calendario\" CTA (T3)."
            );
        }
    }

    /**
     * T3 — the four KPI cards are a static reference strip: none carries
     * the UiCard clickable/hover props or an @click binding. The header
     * "Ir a Caja" action remains the only cash destination CTA.
     */
    public function test_dashboard_stat_cards_have_no_click_affordance(): void
    {
        $path = self::projectRootPath() . self::DASHBOARD_FILE;
        $src = (string) self::readFile($path);
        $this->assertNotNull($src);

        preg_match_all(
            '/<UiCard[^>]*\bdata-stat-card="[^"]+"[^>]*>/',
            $src,
            $matches
        );
        $openings = $matches[0] ?? [];
        $this->assertSame(
            4,
            count($openings),
            'DashboardPage.vue must render exactly 4 stat cards for the static-strip check (D1).'
        );

        foreach ($openings as $idx => $opening) {
            $this->assertDoesNotMatchRegularExpression(
                '/\bclickable\b/',
                $opening,
                "KPI card #{$idx} must not carry the clickable prop (T3: the KPI strip is static)."
            );
            $this->assertDoesNotMatchRegularExpression(
                '/\bhover\b/',
                $opening,
                "KPI card #{$idx} must not carry the hover prop (T3: the KPI strip is static)."
            );
            $this->assertDoesNotMatchRegularExpression(
                '/@click\b/',
                $opening,
                "KPI card #{$idx} must not bind @click (T3: the KPI strip is static)."
            );
        }

        $this->assertSame(
            1,
            substr_count($src, '@click="goToCashRegister"'),
            'DashboardPage.vue must keep the header "Ir a Caja" action as the only cash destination CTA (T3).'
        );
    }

    /**
     * Region helper: the source slice of a single stat card (the UiCard
     * carrying `data-stat-card` through its closing tag).
     */
    private function statCardRegion(string $src, string $statKey): string
    {
        preg_match(
            '/<UiCard[^>]*\bdata-stat-card="' . preg_quote($statKey, '/') . '"[^>]*>[\s\S]*?<\/UiCard>/',
            $src,
            $matches
        );
        $region = $matches[0] ?? '';
        $this->assertNotEmpty(
            $region,
            "DashboardPage.vue must contain a data-stat-card=\"{$statKey}\" card."
        );

        return $region;
    }

    /**
     * Region helper: the source slice between a section's opening tag and
     * its closing tag.
     */
    private function sectionRegion(string $src, string $ariaLabel): string
    {
        $found = preg_match(
            '/<section\b[^>]*aria-label="' . preg_quote($ariaLabel, '/') . '"/',
            $src,
            $matches,
            PREG_OFFSET_CAPTURE
        );
        $this->assertSame(
            1,
            $found,
            "DashboardPage.vue must contain a <section aria-label=\"{$ariaLabel}\">"
        );
        $start = $matches[0][1];
        $end = strpos($src, '</section>', $start);
        $this->assertNotFalse($end, "Section \"{$ariaLabel}\" must be closed");

        return substr($src, $start, $end - $start);
    }

    /**
     * DoD — DashboardPage.vue must preserve the 300ms WebSocket debounce.
     * This is load-bearing per design Decision 2 + the applied progress on
     * slice 08 / FF-015. The regex is permissive (uses [\s\S]) so it can
     * span the nested parentheses in an arrow function callback.
     */
    public function test_dashboard_preserves_300ms_websocket_debounce(): void
    {
        $path = self::projectRootPath() . self::DASHBOARD_FILE;
        $src = (string) self::readFile($path);
        $this->assertNotNull($src);

        $this->assertMatchesRegularExpression(
            '/setTimeout\([\s\S]*?},\s*300\s*\)/',
            $src,
            'DashboardPage.vue must keep a 300ms trailing-edge debounce on WebSocket bursts (slice 08 / FF-015).'
        );
    }

    /**
     * T5 — DashboardPage.vue renders the today-appointments empty case
     * through the shared DashboardSectionEmpty component (extracted from the
     * previous inline block; HOTFIX-DASH-007 icon + CTA ride its slots).
     */
    public function test_dashboard_uses_empty_state_for_today_appointments(): void
    {
        $path = self::projectRootPath() . self::DASHBOARD_FILE;
        $src = (string) self::readFile($path);
        $this->assertNotNull($src);

        // The shared empty pattern lives in the dashboard module; the page
        // consumes it for the today's-appointments empty case.
        $this->assertStringContainsString(
            '<DashboardSectionEmpty',
            $src,
            "DashboardPage.vue must render <DashboardSectionEmpty /> for the today's-appointments empty case (T5)."
        );
    }

    /**
     * T5 — the three per-section inline error blocks (agenda, próximas
     * citas, pendientes) were near-duplicate copies of the same markup.
     * They now all render the shared DashboardSectionError component, the
     * page keeps no copy of the old inline block, and the page-level stats
     * error keeps its own distinct role.
     */
    public function test_dashboard_uses_shared_section_error_component(): void
    {
        $path = self::projectRootPath() . self::DASHBOARD_FILE;
        $src = (string) self::readFile($path);
        $this->assertNotNull($src);

        $this->assertSame(
            0,
            substr_count($src, 'rounded-ios p-5 bg-systemRed-50'),
            'DashboardPage.vue must not keep the duplicated inline section-error markup (T5: DashboardSectionError owns it).'
        );

        $this->assertSame(
            3,
            preg_match_all('/<DashboardSectionError\b/', $src),
            'DashboardPage.vue must render exactly three <DashboardSectionError /> blocks (agenda, próximas citas, pendientes).'
        );

        $hooks = [
            'error-appointments' => 'data-retry-appointments',
            'error-upcoming' => 'data-retry-upcoming',
            'error-pending' => 'data-retry-pending',
        ];
        foreach ($hooks as $state => $retry) {
            $this->assertMatchesRegularExpression(
                '/<DashboardSectionError\b(?=[^>]*data-state="' . $state . '")[^>]*>/s',
                $src,
                "DashboardPage.vue must keep the `{$state}` hook on its shared section-error component (T5)."
            );
            $this->assertMatchesRegularExpression(
                '/<DashboardSectionError\b(?=[^>]*data-state="' . $state . '")[^>]*' . $retry . '/s',
                $src,
                "DashboardPage.vue must keep the `{$retry}` retry hook on its shared section-error component (T5)."
            );
        }

        // The page-level stats error is a different role (it replaces the
        // whole page) and stays as its own block.
        $this->assertSame(
            1,
            substr_count($src, 'data-state="error-stats"'),
            'DashboardPage.vue must keep exactly one page-level stats error block (T5).'
        );
    }

    /**
     * T5 — the three empty states render through the shared
     * DashboardSectionEmpty component: one padding token, per-section copy
     * passed as props, and the pending empty copy keeps its per-column
     * semantics inside each data-pending-group.
     */
    public function test_dashboard_uses_shared_section_empty_component(): void
    {
        $path = self::projectRootPath() . self::DASHBOARD_FILE;
        $src = (string) self::readFile($path);
        $this->assertNotNull($src);

        $componentPath = self::projectRootPath() . '/resources/js/modules/dashboard/DashboardSectionEmpty.vue';
        $this->assertFileExists(
            $componentPath,
            'DashboardSectionEmpty.vue must exist (T5 shared empty-state pattern).'
        );
        $componentSrc = (string) self::readFile($componentPath);
        $this->assertStringContainsString(
            'data-section-empty',
            $componentSrc,
            'DashboardSectionEmpty.vue must expose the data-section-empty hook (T5).'
        );
        $this->assertStringContainsString(
            'p-10',
            $componentSrc,
            'DashboardSectionEmpty.vue must own the single padding token used by every empty state (T5).'
        );

        foreach (['empty-appointments', 'empty-upcoming'] as $state) {
            $this->assertMatchesRegularExpression(
                '/<DashboardSectionEmpty\b[^>]*data-state="' . $state . '"/s',
                $src,
                "DashboardPage.vue must render the shared <DashboardSectionEmpty /> with the `{$state}` hook (T5)."
            );
        }

        $pending = $this->sectionRegion($src, 'Pendientes');
        $this->assertMatchesRegularExpression(
            '/data-pending-group="quotations"[\s\S]*?<DashboardSectionEmpty\b/s',
            $pending,
            'Pending quotations empty column must render through DashboardSectionEmpty (T5).'
        );
        $this->assertMatchesRegularExpression(
            '/data-pending-group="treatment-plans"[\s\S]*?<DashboardSectionEmpty\b/s',
            $pending,
            'Pending treatment-plans empty column must render through DashboardSectionEmpty (T5).'
        );
        $this->assertStringContainsString(
            'title="Sin presupuestos pendientes"',
            $pending,
            'Pending quotations empty copy must keep its per-column semantics through the component prop (T5).'
        );
        $this->assertStringContainsString(
            'title="Sin planes por aceptar"',
            $pending,
            'Pending treatment-plans empty copy must keep its per-column semantics through the component prop (T5).'
        );
    }

    /**
     * T5 — pending plan rows render the backend final_cost with the same row
     * anatomy as the quotation rows, with a muted "N/D" fallback that never
     * paints an em dash.
     */
    public function test_dashboard_plan_rows_render_final_cost(): void
    {
        $path = self::projectRootPath() . self::DASHBOARD_FILE;
        $src = (string) self::readFile($path);
        $this->assertNotNull($src);

        $pending = $this->sectionRegion($src, 'Pendientes');
        $start = strpos($pending, 'data-pending-group="treatment-plans"');
        $this->assertNotFalse(
            $start,
            'DashboardPage.vue must render the treatment-plans pending group.'
        );
        $planRegion = substr($pending, $start);

        $this->assertStringContainsString(
            'formatPENLabel(item.final_cost)',
            $planRegion,
            'DashboardPage.vue plan rows must render the backend final_cost through formatPENLabel (T5).'
        );
        $this->assertStringContainsString(
            'N/D',
            $planRegion,
            'DashboardPage.vue plan rows must render a muted "N/D" fallback when final_cost is null (T5).'
        );
        $this->assertStringNotContainsString(
            "\u{2014}",
            $planRegion,
            'DashboardPage.vue plan rows must never fall back to an em dash (T5 / HOTFIX-DASH-011).'
        );
    }

    /**
     * T2a (ops IA) - the agenda is the page's protagonist, so the today
     * list renders EVERY appointment the endpoint returns. The previous
     * slice(0, 3) cap was removed by the ops-redesign IA pass; this guard
     * pins the removal and the uncapped v-for over todayAppointments.
     */
    public function test_dashboard_renders_every_today_appointment_without_cap(): void
    {
        $src = (string) self::readFile(self::projectRootPath() . self::DASHBOARD_FILE);
        $this->assertNotNull($src);

        $this->assertStringNotContainsString(
            'slice(0, 3)',
            $src,
            "DashboardPage.vue must not cap today's appointments; the agenda renders every appointment returned."
        );

        $this->assertMatchesRegularExpression(
            '/v-for="appointment in todayAppointments"/',
            $src,
            'DashboardPage.vue agenda must iterate the full todayAppointments list (no slice cap).'
        );
    }

    /**
     * DoD — DashboardPage.vue must use `UiSkeleton` for the loading state.
     */
    public function test_dashboard_uses_skeleton_for_loading(): void
    {
        $path = self::projectRootPath() . self::DASHBOARD_FILE;
        $src = (string) self::readFile($path);
        $this->assertNotNull($src);

        $this->assertMatchesRegularExpression(
            '/<UiSkeleton\b/',
            $src,
            "DashboardPage.vue must render <UiSkeleton /> placeholders in the loading state."
        );
    }

    /**
     * DoD — The 5 stat cards must be marked with `tabular-nums` so the
     * numbers don't shift when the value updates over a WebSocket burst.
     * This is the design contract for "Numbers in stat cards are data, not
     * display type — keep them sans and tabular".
     */
    public function test_dashboard_stat_card_numbers_are_tabular_nums(): void
    {
        $path = self::projectRootPath() . self::DASHBOARD_FILE;
        $src = (string) self::readFile($path);
        $this->assertNotNull($src);

        $this->assertStringContainsString(
            'tabular-nums',
            $src,
            'DashboardPage.vue stat-card numbers must use tabular-nums (font-variant-numeric: tabular-nums).'
        );
    }

    /* ============================================================ */
    /* PR4 — Dashboard exemplar polish slice                        */
    /*                                                                */
    /* These tests assert the dashboard polish tokens + grid fixed    */
    /* slots + greeting hierarchy + topbar optical weight +          */
    /* quick-action keyhint affordance + empty-state illustration    */
    /* are present in the source. They are RED before the PR4         */
    /* implementation lands and GREEN after.                          */
    /* ============================================================ */

    /**
     * WU1 — the KPI card anatomy is three lines: small uppercase eyebrow,
     * big tabular number, ONE scope caption (D3). The four-row slot grid
     * (h-4 / h-12 / min-h-6 / h-4) and the reserved chip slot are gone:
     * no blank slot survives in the strip.
     */
    public function test_dashboard_stat_cards_use_eyebrow_number_caption_anatomy(): void
    {
        $path = self::projectRootPath() . self::DASHBOARD_FILE;
        $src = (string) self::readFile($path);
        $this->assertNotNull($src);

        preg_match_all(
            '/<UiCard[^>]*\bdata-stat-card="[^"]+"[^>]*>([\s\S]*?)<\/UiCard>/',
            $src,
            $matches
        );
        $cards = $matches[0] ?? [];
        $this->assertSame(
            4,
            count($cards),
            'DashboardPage.vue must render exactly 4 stat cards carrying data-stat-card (D1).'
        );

        foreach ($cards as $idx => $card) {
            $this->assertDoesNotMatchRegularExpression(
                '/min-h-6/',
                $card,
                "Stat card #{$idx} must reserve no blank chip slot (D3: no min-h-6 reservations)."
            );
            $this->assertDoesNotMatchRegularExpression(
                '/\bh-12\b|\bh-4\b/',
                $card,
                "Stat card #{$idx} must keep no fixed four-row scaffolding (WU1 anatomy)."
            );
            $this->assertSame(
                1,
                preg_match_all('/data-kpi-caption="/', $card),
                "Stat card #{$idx} must render exactly one caption line (D3: no caption sprawl)."
            );
            $this->assertMatchesRegularExpression(
                '/class="[^"]*uppercase[^"]*"/',
                $card,
                "Stat card #{$idx} must carry the small uppercase eyebrow."
            );
            $this->assertMatchesRegularExpression(
                '/\btabular-nums\b/',
                $card,
                "Stat card #{$idx} must render its big number with tabular-nums."
            );
            $this->assertMatchesRegularExpression(
                '/\bdata-stat="[^"]+"/',
                $card,
                "Stat card #{$idx} must expose the stable data-stat hook on its value element."
            );
        }
    }

    /**
     * D3 — the reserved chip slot is gone. The backend comparison labels are
     * broken (delta_label "-100" for patients), so the strip reserves no
     * min-h-6 slot and binds no comparison field at all.
     */
    public function test_dashboard_kpi_cards_reserve_no_blank_slots(): void
    {
        $path = self::projectRootPath() . self::DASHBOARD_FILE;
        $src = (string) self::readFile($path);
        $this->assertNotNull($src);

        $this->assertSame(
            0,
            preg_match_all('/min-h-6\b/', $src),
            'DashboardPage.vue must reserve no blank chip slots (min-h-6) anywhere: the KPI anatomy is eyebrow + number + one caption (D3).'
        );

        $this->assertDoesNotMatchRegularExpression(
            '/\bdelta_label\b/',
            $src,
            'DashboardPage.vue must not bind the broken backend delta_label (D3: no comparison chips).'
        );
        $this->assertStringNotContainsString(
            'chipVariant',
            $src,
            'DashboardPage.vue must not keep the chipVariant helper (D3: no comparison chips).'
        );
    }

    /**
     * 4.1.5 — each stat card carries a `data-stat-card` attribute whose value
     * equals the stat key (`appointments-today`, `completed-today`,
     * `pending-today`, `cash-balance`) plus the `data-stat` hook on the value
     * element (WU1 / D1). These are the handles the JS smoke suite and the
     * Playwright run use to assert the numbers.
     */
    public function test_dashboard_four_stat_cards_carry_data_stat_card_attribute(): void
    {
        $path = self::projectRootPath() . self::DASHBOARD_FILE;
        $src = (string) self::readFile($path);
        $this->assertNotNull($src);

        $expectedKeys = [
            'appointments-today',
            'completed-today',
            'pending-today',
            'cash-balance',
        ];
        foreach ($expectedKeys as $key) {
            $this->assertStringContainsString(
                'data-stat-card="' . $key . '"',
                $src,
                "DashboardPage.vue must mark the \"{$key}\" card with data-stat-card=\"{$key}\"."
            );
            $this->assertStringContainsString(
                'data-stat="' . $key . '"',
                $src,
                "DashboardPage.vue must mark the \"{$key}\" value element with data-stat=\"{$key}\" (stable testability hook)."
            );
        }
    }

    /**
     * 4.2.1 — The greeting "Buenos días, Admin" must NOT be rendered as an
     * `<h1>` or `<h2>` (it competes with the topbar's `<h1>`), and its
     * size must be `text-lg font-medium` (NOT the previous `text-2xl font-semibold`).
     */
    public function test_dashboard_greeting_not_h2_or_h1_uses_text_lg_font_medium(): void
    {
        $path = self::projectRootPath() . self::DASHBOARD_FILE;
        $src = (string) self::readFile($path);
        $this->assertNotNull($src);

        // The greeting must be a <p> with text-lg font-medium text-theme-secondary.
        // We assert on the class binding the template emits.
        $this->assertMatchesRegularExpression(
            '/<p[^>]*class="[^"]*text-lg[^"]*font-medium[^"]*text-theme-secondary[^"]*"[^>]*>\s*\{\{\s*getGreeting\(\)\s*\}\}/',
            $src,
            'DashboardPage.vue greeting must be a <p class="text-lg font-medium text-theme-secondary">{{ getGreeting() }}, ...'
        );

        // The previous text-2xl font-semibold greeting size is forbidden
        // (defect 7 — two competing headings).
        $this->assertDoesNotMatchRegularExpression(
            '/text-2xl[^"]*font-semibold[^"]*text-ink-800/',
            $src,
            'DashboardPage.vue greeting must not use the previous text-2xl font-semibold text-ink-800 (would compete with topbar h1).'
        );

        // Only one h1 in the dashboard page source — the dashboard route's
        // <h1> lives in AppLayout.vue, but the page source itself must
        // not contain any other h1 to keep the page heading hierarchy
        // unambiguous.
        $h1Count = preg_match_all('/<h1\b/i', $src);
        $this->assertSame(
            0,
            (int) $h1Count,
            'DashboardPage.vue must not declare its own <h1> (the page <h1> lives in AppLayout.vue).'
        );

        // No h2 carrying the greeting either.
        $this->assertDoesNotMatchRegularExpression(
            '/<h2[^>]*>\s*\{\{\s*getGreeting\(\)\s*\}\}/',
            $src,
            'DashboardPage.vue greeting must not be wrapped in <h2>.'
        );
    }

    /**
     * 4.3.1 — The topbar (AppLayout.vue) consumes the new topbar tokens
     * for icon size and stroke weight so the WS dot, bell, and avatar all
     * share one optical weight.
     */
    public function test_app_layout_topbar_consumes_topbar_icon_size_and_weight(): void
    {
        $path = self::projectRootPath() . self::APP_LAYOUT_FILE;
        $src = (string) self::readFile($path);
        $this->assertNotNull($src);

        // The topbar must consume the new tokens via CSS variables.
        $this->assertStringContainsString(
            '--topbar-icon-size',
            $src,
            'AppLayout.vue topbar must consume the --topbar-icon-size token (G2 single optical weight).'
        );
        $this->assertStringContainsString(
            '--topbar-icon-weight',
            $src,
            'AppLayout.vue topbar must consume the --topbar-icon-weight token (G2 single optical weight).'
        );

        // The BellIcon glyph in the topbar must carry the stroke-width
        // attribute that consumes the icon-weight token.
        $this->assertMatchesRegularExpression(
            '/BellIcon[^>]*style="[^"]*stroke-width:\s*var\(--topbar-icon-weight\)/',
            $src,
            'AppLayout.vue BellIcon must declare style="stroke-width: var(--topbar-icon-weight)" on the topbar control.'
        );
    }

    /**
     * HOTFIX-DASH-006 wins over the PR5-era G4 keyhint-chip contract below.
     * The source removed the letter-key shortcut badge on purpose
     * (design-taste §9.D "no Material keyboard-shortcut reference visual"):
     * affordance is hover-lift + the whole card being clickable. This test
     * now pins the REMOVAL: ≥4 data-action cards, no chevron (PR3 contract
     * stays), and no data-keyhint / <kbd> badge anywhere in the region.
     */
    public function test_quick_action_cards_carry_keyhint_chip_no_chevron(): void
    {
        $path = self::projectRootPath() . self::DASHBOARD_FILE;
        $src = (string) self::readFile($path);
        $this->assertNotNull($src);

        // Reuse the existing chevron path ban — extend its scope to the
        // whole quick-action region (it already covers data-action cards).
        preg_match_all(
            '/<UiCard[^>]*\bdata-action="[^"]+"[^>]*>[\s\S]*?<\/UiCard>/',
            $src,
            $matches
        );
        $cards = $matches[0] ?? [];
        $this->assertGreaterThanOrEqual(
            4,
            count($cards),
            'DashboardPage.vue must contain at least 4 data-action cards (T3: 4 verified action labels).'
        );

        foreach ($cards as $idx => $card) {
            // The banned chevron path must remain absent (PR3 contract).
            $this->assertDoesNotMatchRegularExpression(
                '/M9 5l7 7-7 7/',
                $card,
                "Quick-action card #{$idx} must not contain the banned chevron path (G4 — replace with keyhint)."
            );

            // HOTFIX-DASH-006: no keyboard-shortcut badge. The data-keyhint
            // attribute and the <kbd> chip must be absent from every card.
            $this->assertDoesNotMatchRegularExpression(
                '/data-keyhint="[A-Z]"/',
                $card,
                "Quick-action card #{$idx} must not carry data-keyhint (HOTFIX-DASH-006 removed the badge)."
            );
            $this->assertDoesNotMatchRegularExpression(
                '/<kbd\b/',
                $card,
                "Quick-action card #{$idx} must not render a <kbd> keyhint chip (HOTFIX-DASH-006 removed the badge)."
            );
        }
    }

    /**
     * 4.5.1 (revised in correction round) — The today-appointments
     * `<EmptyState>` for the empty case must NOT carry a remote
     * illustration. The previous Picsum-seeded URL resolved to an
     * unrelated stock photo (a sunset over a pier), and clinical
     * products must not leak requests to third-party hosts. The
     * empty state is composed from what is already in the design
     * system: the EmptyState primitive with its default icon, a
     * one-line Spanish message, and a real call-to-action.
     *
     * The previous `illustration="https://picsum.photos/..."` binding
     * was the wrong tool here: the design-taste-frontend skill
     * explicitly scopes Picsum to landing pages and portfolios and
     * lists dashboards as OUT OF SCOPE.
     */
    public function test_dashboard_empty_state_picsum_illustration(): void
    {
        $path = self::projectRootPath() . self::DASHBOARD_FILE;
        $src = (string) self::readFile($path);
        $this->assertNotNull($src);

        // The empty state for today-appointments renders through the shared
        // DashboardSectionEmpty component (T5) and keeps the
        // data-state="empty-appointments" hook. It MUST NOT carry an
        // illustration attribute pointing to a third-party host.
        $this->assertMatchesRegularExpression(
            '/<DashboardSectionEmpty\b[^>]*data-state="empty-appointments"/s',
            $src,
            'DashboardPage.vue must render <DashboardSectionEmpty data-state="empty-appointments"> for the today-appointments empty case (T5, HOTFIX-DASH-007 icon through the icon slot).'
        );
        $this->assertDoesNotMatchRegularExpression(
            '/<EmptyState\b[^>]*\billustration="[^"]*picsum\.photos/i',
            $src,
            'DashboardPage.vue <EmptyState> must NOT carry a Picsum illustration (correction round; clinical products cannot reach out to third-party image hosts).'
        );
        $this->assertDoesNotMatchRegularExpression(
            '/<EmptyState\b[^>]*\billustration="https?:\/\//i',
            $src,
            'DashboardPage.vue <EmptyState> must NOT carry a remote illustration (correction round; air-gapped deployments would render broken images).'
        );
    }

    /**
     * Project-wide guard — NO `.vue` / `.js` / `.ts` file under
     * `resources/js/` may reference a third-party image host. Clinical
     * deployments are often air-gapped; even on connected networks,
     * a request from a patient-scheduling surface to picsum.photos /
     * unsplash / pexels is an unnecessary leak. If an illustration is
     * ever needed, it must be a committed local asset under
     * `public/images/ui/` (the same rule the not-found image follows).
     * This test catches the regression at
     * the source level so the previous Picsum bug cannot return.
     */
    public function test_no_external_image_host_anywhere_in_js_source(): void
    {
        $dir = self::projectRootPath() . '/resources/js/';
        $this->assertDirectoryExists($dir);

        // The banned hosts. Picsum is the most recent offender;
        // the others are listed explicitly so a future contributor
        // who reaches for "stock photo service X" fails the same test.
        $bannedHosts = [
            'picsum.photos',
            'unsplash.com',
            'images.unsplash.com',
            'pexels.com',
            'images.pexels.com',
        ];

        foreach ($bannedHosts as $host) {
            $count = self::grepCount($host, $dir);
            $this->assertSame(
                0,
                $count,
                "resources/js/ must not reference the external image host `{$host}` "
                    . "(clinical products cannot reach out to third-party image hosts). "
                    . "Found: {$count} occurrences."
            );
        }
    }

    /**
     * D8 / R1 — the daily appointment count lives ONLY in the KPI. The
     * Agenda de hoy header must not render `todayAppointments.length` (nor
     * its singular/plural suffix), and the KPI strip binds the very same
     * array.
     */
    public function test_dashboard_agenda_header_renders_no_appointment_count(): void
    {
        $path = self::projectRootPath() . self::DASHBOARD_FILE;
        $src = (string) self::readFile($path);
        $this->assertNotNull($src);

        $agenda = $this->sectionRegion($src, 'Agenda de hoy');

        $this->assertDoesNotMatchRegularExpression(
            '/\{\{\s*todayAppointments\.length\s*\}\}/',
            $agenda,
            'DashboardPage.vue agenda header must not render the appointment count (D8: the KPI owns it).'
        );
        $this->assertDoesNotMatchRegularExpression(
            "/\?\s*'cita'\s*:\s*'citas'/",
            $agenda,
            'DashboardPage.vue agenda header must not render the singular/plural count suffix (D8).'
        );

        $kpi = $this->sectionRegion($src, 'Resumen del día');
        $this->assertStringContainsString(
            'data-stat="appointments-today"',
            $kpi,
            'The daily count must render in the KPI strip (single source, R1).'
        );
    }

    /**
     * Eyebrow row rhythm (WU1 strip). Every one of the four KPI eyebrows
     * must use the SAME token size (`text-xs`), no tracking, and
     * `whitespace-nowrap` so the longest label ("Saldo de Caja") stays on
     * one line.
     */
    public function test_dashboard_four_eyebrows_use_uniform_text_size(): void
    {
        $path = self::projectRootPath() . self::DASHBOARD_FILE;
        $src = (string) self::readFile($path);
        $this->assertNotNull($src);

        // All four labels must be present and rendered with text-xs.
        $expectedLabels = ['Citas Hoy', 'Atendidas Hoy', 'Por Atender', 'Saldo de Caja'];
        foreach ($expectedLabels as $label) {
            // The eyebrow pattern: <p class="text-xs ... uppercase ... whitespace-nowrap">{{ label }}</p>
            $pattern = '/<p[^>]*\btext-xs\b[^>]*\buppercase\b[^>]*\bwhitespace-nowrap\b[^>]*>\s*' . preg_quote($label, '/') . '\s*<\/p>/';
            $this->assertMatchesRegularExpression(
                $pattern,
                $src,
                "DashboardPage.vue eyebrow for \"{$label}\" must use text-xs + uppercase + whitespace-nowrap (uniform eyebrow rhythm)."
            );
        }

        // No eyebrow may keep the previous arbitrary text-[11px] size.
        // Scope to the data-stat-card blocks so the assertion does
        // not catch unrelated utility uses elsewhere.
        preg_match_all(
            '/<UiCard[^>]*\bdata-stat-card="[^"]+"[^>]*>[\s\S]*?<\/UiCard>/',
            $src,
            $matches
        );
        $cards = $matches[0] ?? [];
        $this->assertSame(
            4,
            count($cards),
            'DashboardPage.vue must render exactly 4 data-stat-card elements for the eyebrow uniformity check (D1).'
        );
        foreach ($cards as $idx => $card) {
            $this->assertDoesNotMatchRegularExpression(
                '/text-\[11px\]/',
                $card,
                "KPI card #{$idx} eyebrow must not keep the arbitrary text-[11px] size (the token class is text-xs)."
            );
        }
    }

    /**
     * T4 — section headings. The KPI strip was the only section without a
     * visible title (aria-label only). It now carries the same h2 anatomy
     * as its siblings (`text-base font-semibold text-label`), placed at the
     * top of the section, and the accessible name matches the visible text.
     */
    public function test_dashboard_kpi_section_has_a_visible_heading(): void
    {
        $path = self::projectRootPath() . self::DASHBOARD_FILE;
        $src = (string) self::readFile($path);
        $this->assertNotNull($src);

        $region = $this->sectionRegion($src, 'Resumen del día');

        $this->assertStringContainsString(
            '<h2 class="text-base font-semibold text-label">Resumen del día</h2>',
            $region,
            'DashboardPage.vue KPI section must render the visible h2 "Resumen del día" with the sibling section-title anatomy (T4).'
        );

        // The heading comes first; the card grid follows it.
        $headingPos = strpos($region, '<h2');
        $gridPos = strpos($region, 'data-reveal="kpi"');
        $this->assertNotFalse($headingPos, 'KPI section must contain the h2 (T4).');
        $this->assertNotFalse($gridPos, 'KPI section must contain the card grid (data-reveal="kpi").');
        $this->assertLessThan(
            $gridPos,
            $headingPos,
            'DashboardPage.vue KPI heading must sit at the top of the section, above the card grid (T4).'
        );
    }

    /**
     * T4 — one casing convention for section titles: sentence case. The
     * "Acciones Rápidas" h2 becomes "Acciones rápidas" and agrees with its
     * own aria-label, which already read "Acciones rápidas". The sibling
     * titles keep their existing copy.
     */
    public function test_dashboard_quick_actions_heading_uses_sentence_case(): void
    {
        $path = self::projectRootPath() . self::DASHBOARD_FILE;
        $src = (string) self::readFile($path);
        $this->assertNotNull($src);

        $region = $this->sectionRegion($src, 'Acciones rápidas');
        $this->assertStringContainsString(
            '<h2 class="text-base font-semibold text-label">Acciones rápidas</h2>',
            $region,
            'DashboardPage.vue must render the sentence-case h2 "Acciones rápidas" (T4 section-title convention).'
        );

        $this->assertStringNotContainsString(
            'Acciones Rápidas',
            $src,
            'DashboardPage.vue must not keep the title-case "Acciones Rápidas" heading (T4: h2 and aria-label agree).'
        );

        foreach (['Agenda de hoy', 'Próximas citas', 'Pendientes', 'Resumen del día'] as $label) {
            $this->assertStringContainsString(
                '<h2 class="text-base font-semibold text-label">' . $label . '</h2>',
                $src,
                "DashboardPage.vue section \"{$label}\" must keep the shared section-title anatomy (T4)."
            );
        }
    }

    /**
     * D3 — no comparison chip survives in the KPI strip: the backend
     * delta_label is broken ("-100" for patients), so the strip renders no
     * pill (UiBadge or hand-rolled) and keeps no chip helper.
     */
    public function test_dashboard_kpi_cards_render_no_comparison_chips(): void
    {
        $path = self::projectRootPath() . self::DASHBOARD_FILE;
        $src = (string) self::readFile($path);
        $this->assertNotNull($src);

        $this->assertStringNotContainsString(
            'chipToneClass',
            $src,
            'DashboardPage.vue must not keep the ad-hoc chipToneClass() helper (D3: chips out).'
        );
        $this->assertStringNotContainsString(
            'text-xs font-semibold px-2 py-0.5 rounded-full whitespace-nowrap',
            $src,
            'DashboardPage.vue must not keep the hand-rolled pill span class signature (D3: chips out).'
        );

        $expectedKeys = [
            'appointments-today',
            'completed-today',
            'pending-today',
            'cash-balance',
        ];
        foreach ($expectedKeys as $key) {
            $card = $this->statCardRegion($src, $key);
            $this->assertStringNotContainsString(
                '<UiBadge',
                $card,
                "DashboardPage.vue KPI card \"{$key}\" must render no comparison pill (D3)."
            );
        }
    }

    /**
     * T4 — decorative dots out. Each KPI card carried an aria-hidden
     * accent dot top-right that read as a status indicator. The header
     * cash-status dot is the only dot on the page with meaning.
     */
    public function test_dashboard_kpi_cards_carry_no_decorative_dot(): void
    {
        $path = self::projectRootPath() . self::DASHBOARD_FILE;
        $src = (string) self::readFile($path);
        $this->assertNotNull($src);

        preg_match_all(
            '/<UiCard[^>]*\bdata-stat-card="[^"]+"[^>]*>[\s\S]*?<\/UiCard>/',
            $src,
            $matches
        );
        $cards = $matches[0] ?? [];
        $this->assertSame(
            4,
            count($cards),
            'DashboardPage.vue must render exactly 4 stat cards for the decorative-dot check (D1).'
        );

        foreach ($cards as $idx => $card) {
            $this->assertStringNotContainsString(
                '--color-accent-500',
                $card,
                "KPI card #{$idx} must not carry the decorative accent dot (T4: dots out)."
            );
            $this->assertDoesNotMatchRegularExpression(
                '/aria-hidden="true"/',
                $card,
                "KPI card #{$idx} must not carry any aria-hidden decoration (T4: dots out)."
            );
        }

        // The header cash-status dot is the page's only remaining dot.
        $this->assertSame(
            1,
            substr_count($src, 'inline-block w-1.5 h-1.5 rounded-full'),
            'DashboardPage.vue must keep exactly one dot (the header cash-status indicator) after the T4 removal.'
        );
    }

    /**
     * WU1 — caption grammar. The Citas Hoy caption states the scope of its
     * number ("Agenda del día"): the number counts the agenda array, so the
     * caption names that source instead of repeating the eyebrow or the page
     * date.
     */
    public function test_dashboard_citas_hoy_caption_states_the_scope(): void
    {
        $path = self::projectRootPath() . self::DASHBOARD_FILE;
        $src = (string) self::readFile($path);
        $this->assertNotNull($src);

        $this->assertStringNotContainsString(
            'getShortTodayDate',
            $src,
            'DashboardPage.vue must not keep getShortTodayDate(): the page header owns the date anchor.'
        );

        $card = $this->statCardRegion($src, 'appointments-today');

        $this->assertMatchesRegularExpression(
            '/data-kpi-caption="appointments-today"[\s\S]{0,200}?Agenda del día/',
            $card,
            'DashboardPage.vue Citas Hoy caption must state the scope as "Agenda del día" (WU1).'
        );
        $this->assertDoesNotMatchRegularExpression(
            '/\{\{\s*getTodayDate\(\)\s*\}\}/',
            $card,
            'DashboardPage.vue Citas Hoy caption must not bind to getTodayDate() (the page header owns the long date).'
        );
    }

    /**
     * WU1 / D1 — the three day counts derive from the SAME source the agenda
     * renders: the `todayAppointments` array, bucketed through the shared
     * status map. No second counter, no second endpoint.
     */
    public function test_dashboard_day_kpis_derive_from_the_today_appointment_list(): void
    {
        $path = self::projectRootPath() . self::DASHBOARD_FILE;
        $src = (string) self::readFile($path);
        $this->assertNotNull($src);

        $this->assertMatchesRegularExpression(
            '/const todayCounts = computed\([\s\S]*?for \(const appointment of todayAppointments\.value\)/',
            $src,
            'DashboardPage.vue must derive the day KPI counts from todayAppointments (single source, R1).'
        );

        foreach (['todayKpiDisplay', 'completedKpiDisplay', 'pendingKpiDisplay'] as $binding) {
            $this->assertStringContainsString(
                '{{ ' . $binding . ' }}',
                $src,
                "DashboardPage.vue KPI value must render through {$binding} (derived from the agenda array)."
            );
        }

        $this->assertStringNotContainsString(
            'backendStats.appointments_today',
            $src,
            'DashboardPage.vue must not feed a KPI from the backend appointments_today counter (D1: the agenda array is the only source).'
        );
    }

    /**
     * WU1 — caption grammar. Each caption states the scope of its number in
     * ONE line: Citas Hoy counts the day's agenda, Atendidas Hoy the
     * completed ones, Por Atender the ones neither completed nor cancelled.
     */
    public function test_dashboard_scope_captions_state_number_scope(): void
    {
        $path = self::projectRootPath() . self::DASHBOARD_FILE;
        $src = (string) self::readFile($path);
        $this->assertNotNull($src);

        $expectedCaptions = [
            'appointments-today' => 'Agenda del día',
            'completed-today' => 'Completadas',
            'pending-today' => 'Sin completar ni cancelar',
        ];
        foreach ($expectedCaptions as $statKey => $captionText) {
            $card = $this->statCardRegion($src, $statKey);
            $this->assertMatchesRegularExpression(
                '/data-kpi-caption="' . $statKey . '"[\s\S]{0,200}?' . preg_quote($captionText, '/') . '/',
                $card,
                "DashboardPage.vue caption on \"{$statKey}\" must state the scope of the number as \"{$captionText}\" (WU1)."
            );
        }
    }

    /**
     * Defect 1 — The dashboard page wrapper (or the AppLayout root when
     * the route is /dashboard) consumes the canvas token so the page
     * surface is `bg-canvas`. The card surfaces stay `bg-systemBackground`.
     */
    public function test_dashboard_uses_canvas_token_for_page_surface(): void
    {
        $layoutPath = self::projectRootPath() . self::APP_LAYOUT_FILE;
        $dashboardPath = self::projectRootPath() . self::DASHBOARD_FILE;

        $layoutSrc = (string) self::readFile($layoutPath);
        $dashboardSrc = (string) self::readFile($dashboardPath);
        $this->assertNotNull($layoutSrc);

        // The dashboard route must drive the canvas surface. The two
        // allowed implementations: (a) AppLayout.vue consumes bg-canvas
        // route-aware; or (b) the DashboardPage root consumes bg-canvas.
        // We assert on the AppLayout root because that is the structural
        // wrapper (the dashboard content rides a <slot/> inside it).
        $appLayoutHasCanvas = (bool) preg_match(
            '/class="[^"]*bg-canvas[^"]*"/',
            $layoutSrc
        );
        $dashboardHasCanvas = (bool) preg_match(
            '/class="[^"]*bg-canvas[^"]*"/',
            $dashboardSrc
        );
        $this->assertTrue(
            $appLayoutHasCanvas || $dashboardHasCanvas,
            'The dashboard surface must consume bg-canvas (canvas vs surface separation; PR1 token).'
        );
    }

    /**
     * Defect 2 + 3 — KPI cards consume the PR1 hairline border and the
     * PR1 elevation-2 rung for the shadow. The hairline replaces the
     * opaque `border-separator` outline; the elevation-2 rung replaces
     * the pure-black `shadow-medium`.
     */
    public function test_dashboard_kpi_cards_consume_hairline_and_elevation(): void
    {
        $path = self::projectRootPath() . self::DASHBOARD_FILE;
        $src = (string) self::readFile($path);
        $this->assertNotNull($src);

        // The 5 KPI cards must each reference the hairline token via the
        // arbitrary-value Tailwind syntax (border-color: var(--color-hairline))
        // OR via a custom CSS variable indirection. We assert on the
        // token reference (the only reliable source-level marker).
        preg_match_all(
            '/<UiCard[^>]*\bdata-stat-card="[^"]+"[^>]*>[\s\S]*?<\/UiCard>/',
            $src,
            $matches
        );
        $cards = $matches[0] ?? [];
        $this->assertSame(
            4,
            count($cards),
            'DashboardPage.vue must render exactly 4 data-stat-card elements for the KPI hairline/elevation check (D1).'
        );

        foreach ($cards as $idx => $card) {
            $hasHairline = (bool) preg_match(
                '/--color-hairline/',
                $card
            );
            $this->assertTrue(
                $hasHairline,
                "KPI card #{$idx} must consume var(--color-hairline) on its border (PR1 hairline token; defect 2)."
            );

            $hasElevation = (bool) preg_match(
                '/--elevation-2/',
                $card
            );
            $this->assertTrue(
                $hasElevation,
                "KPI card #{$idx} must consume var(--elevation-2) for its shadow (PR1 elevation ramp; defect 3)."
            );
        }
    }

    /**
     * HOTFIX-DASH-002 wins over the PR5-era one-tint icon-plate contract below.
     * The source removed the icon plates from the stat cards on purpose
     * (pinned by IconInBoxAuditTest). This test now pins the REMOVAL: the
     * 4 data-stat-card elements, no plate tint classes in any card, and the
     * T2b quick-action shape where @heroicons/vue components replaced the
     * previous inline 1.5 stroke-width SVGs.
     */
    public function test_dashboard_kpi_icon_plates_share_one_tint(): void
    {
        $path = self::projectRootPath() . self::DASHBOARD_FILE;
        $src = (string) self::readFile($path);
        $this->assertNotNull($src);

        preg_match_all(
            '/<UiCard[^>]*\bdata-stat-card="[^"]+"[^>]*>[\s\S]*?<\/UiCard>/',
            $src,
            $matches
        );
        $cards = $matches[0] ?? [];
        $this->assertSame(
            4,
            count($cards),
            'DashboardPage.vue must render exactly 4 data-stat-card elements for the icon-plate removal check (D1).'
        );

        $bannedPlateTints = [
            'bg-systemGray-100',
            'bg-systemBlue-50',
            'bg-systemBlue-100',
            'bg-success-50 text-success-600',
            'bg-warning-50 text-warning-600',
            'bg-cream-200 text-ink-500',
            'bg-systemGreen-100 text-systemGreen-600',
        ];
        foreach ($cards as $idx => $card) {
            foreach ($bannedPlateTints as $banned) {
                $this->assertStringNotContainsString(
                    $banned,
                    $card,
                    "KPI card #{$idx} must not carry the removed icon-plate tint `{$banned}` (HOTFIX-DASH-002 removed the plates)."
                );
            }
        }

        // T2b - quick-action icons moved to @heroicons/vue 24-outline
        // components, so the inline quick-action SVGs are gone (T3 dropped
        // the "Nueva Cita" tile). Pin the new shape: the heroicons import
        // exists, no inline <svg> remains inside a data-action tile, and the
        // HOTFIX-DASH-007 empty-state line-art SVG (stroke-width 1.5) is
        // still inline.
        $this->assertStringContainsString(
            '@heroicons/vue/24/outline',
            $src,
            'DashboardPage.vue must import its quick-action icons from @heroicons/vue/24/outline (T2b).'
        );

        preg_match_all(
            '/<UiCard[^>]*\bdata-action="[^"]+"[^>]*>[\s\S]*?<\/UiCard>/',
            $src,
            $actionMatches
        );
        $actionCards = $actionMatches[0] ?? [];
        $this->assertGreaterThanOrEqual(
            4,
            count($actionCards),
            'DashboardPage.vue must render at least 4 data-action cards for the heroicons check.'
        );
        foreach ($actionCards as $idx => $card) {
            $this->assertDoesNotMatchRegularExpression(
                '/<svg\b/i',
                $card,
                "Quick-action card #{$idx} must render its icon through @heroicons/vue, not an inline <svg> (T2b)."
            );
        }

        $inlineIcons = preg_match_all('/<svg\b[^>]*stroke-width="1\.5"/', $src);
        $this->assertGreaterThanOrEqual(
            1,
            (int) $inlineIcons,
            'DashboardPage.vue must keep at least 1 inline SVG with stroke-width="1.5" (the HOTFIX-DASH-007 empty-state line-art icon).'
        );
    }
    /**
     * HOTFIX-DASH-001 wins over the PR5 sidebar headers: section labels
     * were removed (hairline dividers instead), pinned by
     * SidebarEyebrowAuditTest. This test now pins the removal — zero group
     * headers and no Operaciones/Configuración labels in AppLayout.vue.
     */
    public function testPr5SidebarGroupHeadersAdded(): void
    {
        $source = (string) self::readFile(self::projectRootPath() . self::APP_LAYOUT_FILE);
        $this->assertSame(0, substr_count($source, 'class="px-6 py-2 text-[11px] uppercase tracking-[0.12em] text-systemGray-500"'));
        $this->assertDoesNotMatchRegularExpression('/>\s*Operaciones\s*<\/div>/', $source);
        $this->assertDoesNotMatchRegularExpression('/>\s*Configuración\s*<\/div>/', $source);
    }

    public function testPr5NavLabelsRemainInFrozenOrder(): void
    {
        $source = (string) self::readFile(self::projectRootPath() . self::APP_LAYOUT_FILE);
        // T6 — the payment-methods entry carries the accent every sibling
        // label already had. Route path and permission keys stay ASCII.
        $labels = ['Dashboard', 'Calendario', 'Pacientes', 'Profesionales', 'Ambientes', 'Tipos de Cita', 'Sucursales', 'Métodos de Pago', 'Catálogo de Procedimientos', 'Mis Procedimientos', 'Business Intelligence', 'Caja', 'Planes de Tratamiento', 'Presupuestos', 'Historias Clínicas', 'Especialidades', 'Análisis IA'];
        $positions = array_map(fn (string $label): int => strpos($source, "name: '{$label}'"), $labels);
        $this->assertCount(17, array_filter($positions, fn (int|false $position): bool => $position !== false));
        $sorted = $positions;
        sort($sorted);
        $this->assertSame($sorted, $positions);
    }

    /**
     * T6 — one accented name for the payment-methods destination. The sidebar
     * entry and the module heading both read "Métodos de Pago"; the ASCII
     * spelling is retired from user-visible copy (identifiers, route paths
     * and permission keys keep it by design).
     */
    public function test_t6_payment_methods_copy_carries_the_accent(): void
    {
        $layout = (string) self::readFile(self::projectRootPath() . self::APP_LAYOUT_FILE);
        $this->assertStringContainsString(
            "name: 'Métodos de Pago'",
            $layout,
            'AppLayout.vue sidebar entry must read "Métodos de Pago" (T6 naming coherence with every sibling label).'
        );
        $this->assertStringNotContainsString(
            "name: 'Metodos de Pago'",
            $layout,
            'AppLayout.vue must not keep the unaccented sidebar label (T6).'
        );

        $pagePath = self::projectRootPath()
            . '/resources/js/modules/settings/payment-methods/PaymentMethodsPage.vue';
        $this->assertFileExists($pagePath, 'PaymentMethodsPage.vue must exist (T6 naming boundary).');
        $page = (string) self::readFile($pagePath);

        $this->assertStringContainsString(
            'title="Métodos de Pago"',
            $page,
            'PaymentMethodsPage.vue heading must read "Métodos de Pago" (T6).'
        );

        // Byte-safe pin: the accented form holds a 2-byte "é", so this ASCII
        // pattern only matches the unaccented copy in any casing.
        $unaccented = preg_match_all('/[Mm]etodos de [Pp]ago/u', $page);
        $this->assertSame(
            0,
            (int) $unaccented,
            'PaymentMethodsPage.vue must render zero unaccented "metodos de pago" strings (T6, user-visible copy only).'
        );
    }

    /**
     * T6 — the quick-action tiles are real controls. Each `data-action` tile
     * keeps its UiCard surface as a presentation wrapper, but the interactive
     * element inside is a native `<button type="button">`. The card opening
     * tag must not carry the `clickable` prop or the click binding: a
     * clickable div is neither focusable nor activatable from the keyboard.
     */
    public function test_t6_quick_action_tiles_render_through_native_buttons(): void
    {
        $src = (string) self::readFile(self::projectRootPath() . self::DASHBOARD_FILE);
        $this->assertNotNull($src);

        preg_match_all(
            '/<UiCard\b[^>]*\bdata-action="[^"]+"[^>]*>[\s\S]*?<\/UiCard>/',
            $src,
            $matches
        );
        $cards = $matches[0] ?? [];
        $this->assertGreaterThanOrEqual(
            4,
            count($cards),
            'DashboardPage.vue must contain at least 4 data-action tiles for the T6 button-semantics check.'
        );

        foreach ($cards as $idx => $card) {
            $opening = substr($card, 0, (int) strpos($card, '>') + 1);

            $this->assertDoesNotMatchRegularExpression(
                '/\bclickable\b/',
                $opening,
                "Quick-action tile #{$idx} must not carry the UiCard clickable prop (T6: real button semantics)."
            );
            $this->assertDoesNotMatchRegularExpression(
                '/@click/',
                $opening,
                "Quick-action tile #{$idx} must not bind the click on the card surface (T6: the native button owns it)."
            );
            $this->assertMatchesRegularExpression(
                '/<button\b[^>]*type="button"/',
                $card,
                "Quick-action tile #{$idx} must render a native <button type=\"button\"> inside the card (T6 keyboard + AT semantics)."
            );
        }
    }

    /**
     * T6 — chevrons ride the documented 1.5 stroke baseline (apple-design
     * §16), the same value the empty-state line art already uses. The arrow
     * chevron in the "Ver calendario" CTA was the last stroke-width="2"
     * holdout on the page.
     */
    public function test_t6_dashboard_strokes_use_the_1_5_baseline(): void
    {
        $src = (string) self::readFile(self::projectRootPath() . self::DASHBOARD_FILE);
        $this->assertNotNull($src);

        $this->assertSame(
            0,
            substr_count($src, 'stroke-width="2"'),
            'DashboardPage.vue must contain zero stroke-width="2" strokes (T6: every icon rides the 1.5 baseline).'
        );
    }

}
