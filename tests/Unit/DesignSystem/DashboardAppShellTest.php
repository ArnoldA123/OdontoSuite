<?php

namespace Tests\Unit\DesignSystem;

use PHPUnit\Framework\TestCase;

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
        $cmd = sprintf(
            'rg --no-heading --count-matches --no-messages %s %s 2>&1',
            escapeshellarg($pattern),
            escapeshellarg($rootPath)
        );
        $output = (string) shell_exec($cmd);
        if ($output === '') {
            return 0;
        }
        $total = 0;
        foreach (preg_split('/\r?\n/', $output) as $line) {
            $line = trim((string) $line);
            if ($line === '') {
                continue;
            }
            // rg --count-matches prints "path:count" per file
            $parts = explode(':', $line);
            $count = (int) end($parts);
            $total += $count;
        }
        return $total;
    }

    private static function grepLines(string $pattern, string $path): array
    {
        $cmd = sprintf(
            'rg --no-heading --no-messages %s %s 2>&1',
            escapeshellarg($pattern),
            escapeshellarg($path)
        );
        $output = (string) shell_exec($cmd);
        if ($output === '') {
            return [];
        }
        return array_values(array_filter(preg_split('/\r?\n/', $output), fn($l) => $l !== ''));
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
        // section.
        foreach (['Cargando acciones rápidas', 'Acciones rápidas'] as $label) {
            $start = strpos($src, '<section aria-label="' . $label . '"');
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
            'DashboardPage.vue must contain at least 4 data-action cards (the 5 verified action labels).'
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

        $hexCount = 0;

        $cmd = sprintf(
            'rg --no-heading --count-matches --no-messages %s %s %s 2>&1',
            escapeshellarg('#[0-9a-fA-F]{6}'),
            escapeshellarg($dir),
            escapeshellarg($layoutDir)
        );
        $output = (string) shell_exec($cmd);
        if ($output !== '') {
            foreach (preg_split('/\r?\n/', $output) as $line) {
                $line = trim((string) $line);
                if ($line === '') {
                    continue;
                }
                $parts = explode(':', $line);
                $count = (int) end($parts);
                $hexCount += $count;
            }
        }

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
     * Verified labels: "Citas Hoy", "Pacientes", "Profesionales",
     * "Total Citas", "Estado de Caja".
     */
    public function test_dashboard_contains_all_five_verified_stat_card_labels(): void
    {
        $path = self::projectRootPath() . self::DASHBOARD_FILE;
        $src = (string) self::readFile($path);
        $this->assertNotNull($src);

        $labels = ['Citas Hoy', 'Pacientes', 'Profesionales', 'Total Citas', 'Estado de Caja'];
        foreach ($labels as $label) {
            $this->assertStringContainsString(
                $label,
                $src,
                "DashboardPage.vue must render the stat-card label \"{$label}\" (verified content)."
            );
        }
    }

    /**
     * DoD — DashboardPage.vue must contain all 5 verified quick-action labels.
     */
    public function test_dashboard_contains_all_five_verified_quick_action_labels(): void
    {
        $path = self::projectRootPath() . self::DASHBOARD_FILE;
        $src = (string) self::readFile($path);
        $this->assertNotNull($src);

        $labels = ['Pacientes', 'Nueva Cita', 'Profesionales', 'Ambientes', 'Reportes'];
        $counts = array_map(
            fn($label) => substr_count($src, $label),
            $labels
        );
        // "Pacientes" appears in BOTH stat cards and quick actions; just make
        // sure each label is present at least once.
        foreach ($counts as $label => $count) {
            $this->assertGreaterThanOrEqual(
                1,
                $count,
                "DashboardPage.vue must render the quick-action label \"{$label}\" (verified content)."
            );
        }
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
     * DoD — DashboardPage.vue must use `EmptyState` for the today's-appointments
     * empty case (the live state users see today due to the GET 404 bug).
     */
    public function test_dashboard_uses_empty_state_for_today_appointments(): void
    {
        $path = self::projectRootPath() . self::DASHBOARD_FILE;
        $src = (string) self::readFile($path);
        $this->assertNotNull($src);

        // The global registration in resources/js/plugins/ui-components.js
        // exposes EmptyState.vue as `<EmptyState />` (no Ui prefix).
        $this->assertStringContainsString(
            '<EmptyState',
            $src,
            "DashboardPage.vue must render <EmptyState /> for the today's-appointments empty case."
        );
    }

    /**
     * DoD — DashboardPage.vue must cap today's appointments at 3 to keep
     * the visible region deliberate.
     */
    public function test_dashboard_caps_today_appointments_at_three(): void
    {
        $path = self::projectRootPath() . self::DASHBOARD_FILE . '';
        $src = (string) self::readFile(self::projectRootPath() . self::DASHBOARD_FILE);
        $this->assertNotNull($src);

        $this->assertStringContainsString(
            'slice(0, 3)',
            $src,
            "DashboardPage.vue must cap today's appointments at 3 via slice(0, 3)."
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
}
