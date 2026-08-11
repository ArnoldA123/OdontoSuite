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
     * 4.1.1 — Each of the 5 stat cards carries the four-row fixed-slot
     * grid (h-4 / h-12 / h-6 / h-4) plus a `data-stat-card` attribute
     * so Playwright can verify the row baseline.
     */
    public function test_dashboard_stat_cards_use_fixed_slot_grid(): void
    {
        $path = self::projectRootPath() . self::DASHBOARD_FILE;
        $src = (string) self::readFile($path);
        $this->assertNotNull($src);

        // Every stat card must declare the fixed-slot four-row grid via
        // the explicit Tailwind row-heights, in this exact order:
        //   eyebrow (h-4) / number (h-12) / chip (h-6) / caption (h-4)
        preg_match_all(
            '/<UiCard[^>]*\bdata-stat-card="[^"]+"[^>]*>([\s\S]*?)<\/UiCard>/',
            $src,
            $matches
        );
        $cards = $matches[0] ?? [];
        $this->assertGreaterThanOrEqual(
            5,
            count($cards),
            'DashboardPage.vue must render at least 5 stat cards carrying data-stat-card.'
        );

        foreach ($cards as $idx => $card) {
            // The slot order matters: h-4 must precede h-12, h-12 must
            // precede h-6, h-6 must precede h-4. Strict strpos checks
            // enforce the slot order; they do NOT enforce equal margins.
            $eyebrowPos = strpos($card, 'h-4');
            $numberPos  = strpos($card, 'h-12');
            $chipPos    = strpos($card, 'h-6');
            $captionPos = strpos($card, 'h-4', $chipPos === false ? 0 : $chipPos);

            $this->assertNotFalse(
                $eyebrowPos,
                "Stat card #{$idx} must reserve an eyebrow slot (h-4)."
            );
            $this->assertNotFalse(
                $numberPos,
                "Stat card #{$idx} must reserve a number slot (h-12)."
            );
            $this->assertNotFalse(
                $chipPos,
                "Stat card #{$idx} must reserve a chip slot (h-6) — even when empty, the slot must exist."
            );
            $this->assertNotFalse(
                $captionPos,
                "Stat card #{$idx} must reserve a caption slot (h-4)."
            );
            $this->assertLessThan(
                $numberPos,
                $eyebrowPos,
                "Stat card #{$idx} eyebrow slot must come before the number slot."
            );
            $this->assertLessThan(
                $chipPos,
                $numberPos,
                "Stat card #{$idx} number slot must come before the chip slot."
            );
            $this->assertLessThan(
                $captionPos,
                $chipPos,
                "Stat card #{$idx} chip slot must come before the caption slot."
            );
        }
    }

    /**
     * 4.1.3 — Each of the 5 stat cards renders the chip slot as an
     * empty `<div class="h-6">` (no chip) when `comparisons[statKey]
     * .delta_label` is null. Only cards with a non-null delta_label
     * render a `<span>` chip.
     *
     * Source-level assertion: the chip-slot element renders a Tailwind
     * `h-6 min-h-[24px]` (or equivalent fixed-height) container so the
     * reserved slot does not collapse.
     */
    public function test_dashboard_chip_slot_is_reserved_height(): void
    {
        $path = self::projectRootPath() . self::DASHBOARD_FILE;
        $src = (string) self::readFile($path);
        $this->assertNotNull($src);

        // At least 5 cards must carry a chip slot class binding.
        $chipSlotCount = preg_match_all('/h-6\s+min-h-\[24px\]/', $src);
        $this->assertGreaterThanOrEqual(
            5,
            (int) $chipSlotCount,
            'DashboardPage.vue must reserve the chip slot for each of the 5 stat cards (h-6 min-h-[24px]).'
        );

        // The chip span (when delta_label is non-null) is rendered
        // conditionally; the source must contain at least one template
        // expression binding to comparisons[statKey].delta_label.
        $this->assertMatchesRegularExpression(
            '/comparisons\[[^\]]+\]\.delta_label/',
            $src,
            'DashboardPage.vue must bind the chip to comparisons[statKey].delta_label.'
        );
    }

    /**
     * 4.1.5 — Each of the 5 stat cards carries a `data-stat-card` attribute
     * whose value equals the stat key (`appointments-today`, `total-patients`,
     * `total-professionals`, `total-appointments-month`, `cash-status`).
     * This is the test handle the Playwright run uses to assert row baseline.
     */
    public function test_dashboard_five_stat_cards_carry_data_stat_card_attribute(): void
    {
        $path = self::projectRootPath() . self::DASHBOARD_FILE;
        $src = (string) self::readFile($path);
        $this->assertNotNull($src);

        $expectedKeys = [
            'appointments-today',
            'total-patients',
            'total-professionals',
            'total-appointments-month',
            'cash-status',
        ];
        foreach ($expectedKeys as $key) {
            $this->assertStringContainsString(
                'data-stat-card="' . $key . '"',
                $src,
                "DashboardPage.vue must mark the \"{$key}\" card with data-stat-card=\"{$key}\"."
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
     * 4.4.2 — Each quick-action card carries a `data-keyhint` attribute
     * and renders a `<kbd>` element with a keyhint class binding in the
     * top-right corner. The keyhint is the non-chevron affordance device
     * (G4 — banned SVG path `M9 5l7 7-7 7`).
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
            5,
            count($cards),
            'DashboardPage.vue must contain at least 5 data-action cards (5 verified action labels).'
        );

        foreach ($cards as $idx => $card) {
            // The banned chevron path must remain absent (PR3 contract).
            $this->assertDoesNotMatchRegularExpression(
                '/M9 5l7 7-7 7/',
                $card,
                "Quick-action card #{$idx} must not contain the banned chevron path (G4 — replace with keyhint)."
            );

            // Each card must carry a data-keyhint attribute that names
            // the keyboard shortcut for the action (G4 device).
            $this->assertMatchesRegularExpression(
                '/data-keyhint="[A-Z]"/',
                $card,
                "Quick-action card #{$idx} must carry data-keyhint=\"<single uppercase letter>\" (G4)."
            );

            // The card body must render a <kbd> chip element with the
            // keyhint letter visible to the user (not just as data).
            $this->assertMatchesRegularExpression(
                '/<kbd\b[^>]*class="[^"]*rounded[^"]*"[^>]*>[^<]+<\/kbd>/',
                $card,
                "Quick-action card #{$idx} must render a visible <kbd> keyhint chip in the top-right corner."
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

        // The EmptyState for today-appointments must still be present
        // (the no-CTA bug from PR3 stays fixed), but it MUST NOT carry
        // an illustration attribute pointing to a third-party host.
        $this->assertMatchesRegularExpression(
            '/<EmptyState[^>]*data-state="empty-appointments"[^>]*>/',
            $src,
            'DashboardPage.vue must render <EmptyState data-state="empty-appointments"> for the today-appointments empty case.'
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
     * `public/images/ui/` (the same rule the login-hero and
     * not-found images follow). This test catches the regression at
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
     * Defect 2 — chip layout. The comparison pill MUST contain only
     * the delta value. The `period_label` is a separate muted caption
     * SIBLING of the pill, NOT a child. The previous anatomy nested
     * both inside the pill, which overflowed the reserved h-6 slot
     * and collided with the caption row.
     */
    public function test_dashboard_chip_period_label_is_outside_the_pill(): void
    {
        $path = self::projectRootPath() . self::DASHBOARD_FILE;
        $src = (string) self::readFile($path);
        $this->assertNotNull($src);

        // For each stat card that carries a chip, the chip slot must
        // wrap a <span> pill + a <span> muted text in a flex container,
        // NOT nest the muted text inside the pill.
        $chipStats = [
            'appointments_today',
            'total_patients',
            'total_appointments_this_month',
        ];

        foreach ($chipStats as $statKey) {
            // Find the chip slot block for this stat key. The `v-if` guard
            // uses optional chaining (`comparisons?.appointments_today?.…`),
            // so every dot here is optionally preceded by `?`. The window to
            // the closing </div> must clear the pill <span> plus the sibling
            // caption <span>, which together run to roughly 550 characters.
            $pattern = '/comparisons\??\.' . preg_quote($statKey, '/')
                . '\??\.delta_label[\s\S]{0,80}?class="h-6 min-h-\[24px\] flex items-center gap-1\.5"[\s\S]{0,900}?<\/div>/';
            $this->assertMatchesRegularExpression(
                $pattern,
                $src,
                "DashboardPage.vue chip slot for `{$statKey}` must be a flex row with gap (defect 2 fix)."
            );

            // The chip slot must contain the pill <span> AND the
            // muted caption <span> as siblings, not nested. The
            // pill <span> carries `rounded-full`; the muted <span>
            // carries `truncate` (no rounded-full).
            $pillWithNestedCaption = '/<span[^>]*rounded-full[\s\S]*?<span[^>]*\bperiod_label\b[\s\S]*?<\/span>\s*<\/span>/';
            $this->assertDoesNotMatchRegularExpression(
                $pillWithNestedCaption,
                $src,
                "DashboardPage.vue chip pill must NOT nest the period_label inside it (defect 2)."
            );
        }
    }

    /**
     * Defect 4 — eyebrow row rhythm. Every one of the five KPI
     * eyebrows must use the SAME text size so the row baseline is
     * uniform. The previous `text-xs ... tracking-wide` wrapped the
     * longest label ("Estado de Caja") onto two lines while the
     * shorter labels sat on one, breaking the rhythm. PR4 reduces
     * every eyebrow to `text-[11px]` with no tracking and adds
     * `whitespace-nowrap` so all five labels fit on a single line.
     */
    public function test_dashboard_five_eyebrows_use_uniform_text_size(): void
    {
        $path = self::projectRootPath() . self::DASHBOARD_FILE;
        $src = (string) self::readFile($path);
        $this->assertNotNull($src);

        // All five labels must be present and rendered with text-[11px].
        $expectedLabels = ['Citas Hoy', 'Pacientes', 'Profesionales', 'Total Citas', 'Estado de Caja'];
        foreach ($expectedLabels as $label) {
            // The eyebrow pattern: <p class="text-[11px] ... uppercase ... {{ label }} </p>
            $pattern = '/<p[^>]*\btext-\[11px\][^>]*\buppercase\b[^>]*\bwhitespace-nowrap\b[^>]*>\s*' . preg_quote($label, '/') . '\s*<\/p>/';
            $this->assertMatchesRegularExpression(
                $pattern,
                $src,
                "DashboardPage.vue eyebrow for \"{$label}\" must use text-[11px] + uppercase + whitespace-nowrap (defect 4 row-rhythm fix)."
            );
        }

        // No eyebrow may use the previous text-xs (12 px) class.
        // Scope to the data-stat-card blocks so the assertion does
        // not catch unrelated text-xs utility uses elsewhere.
        preg_match_all(
            '/<UiCard[^>]*\bdata-stat-card="[^"]+"[^>]*>[\s\S]*?<\/UiCard>/',
            $src,
            $matches
        );
        $cards = $matches[0] ?? [];
        $this->assertGreaterThanOrEqual(
            5,
            count($cards),
            'DashboardPage.vue must render at least 5 data-stat-card elements for the eyebrow uniformity check.'
        );
        foreach ($cards as $idx => $card) {
            $this->assertDoesNotMatchRegularExpression(
                '/<p[^>]*\btext-xs\b[^>]*\buppercase\b[^>]*\btracking-wide\b[^>]*>/',
                $card,
                "KPI card #{$idx} eyebrow must NOT use the previous text-xs + tracking-wide (would wrap \"Estado de Caja\")."
            );
        }
    }

    /**
     * Defect 3 — date caption truncation. The Citas Hoy caption slot
     * must use the short `11 de ago` format via `getShortTodayDate()`,
     * NOT the full `martes, 11 de agosto de 2026` format via
     * `getTodayDate()`. The full format overflowed the KPI card's
     * caption slot at 5-up width and `truncate` clipped it mid-word.
     */
    public function test_dashboard_citas_hoy_caption_uses_short_date(): void
    {
        $path = self::projectRootPath() . self::DASHBOARD_FILE;
        $src = (string) self::readFile($path);
        $this->assertNotNull($src);

        // The Citas Hoy card's caption slot must bind to
        // getShortTodayDate(), not getTodayDate().
        $this->assertStringContainsString(
            'getShortTodayDate',
            $src,
            'DashboardPage.vue must define and consume getShortTodayDate() for the Citas Hoy caption (defect 3).'
        );

        // The Citas Hoy card must NOT render the long getTodayDate()
        // binding in its caption slot (it is reserved for the
        // topbar page description under AppLayout).
        $citasHoyCard = '';
        if (preg_match(
            '/<UiCard[^>]*\bdata-stat-card="appointments-today"[^>]*>[\s\S]*?<\/UiCard>/',
            $src,
            $m
        )) {
            $citasHoyCard = $m[0];
        }
        $this->assertNotEmpty(
            $citasHoyCard,
            'DashboardPage.vue must contain a data-stat-card="appointments-today" card.'
        );
        $this->assertDoesNotMatchRegularExpression(
            '/\{\{\s*getTodayDate\(\)\s*\}\}/',
            $citasHoyCard,
            'DashboardPage.vue Citas Hoy caption must not bind to getTodayDate() (full format overflows the slot at 5-up; defect 3).'
        );
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
        $this->assertGreaterThanOrEqual(
            5,
            count($cards),
            'DashboardPage.vue must render at least 5 data-stat-card elements for the KPI hairline/elevation check.'
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
     * Defect 6 — The five icon plates (the small square containing the
     * card icon) MUST share one coherent tint. The previous mix
     * (systemBlue-100 / success-50 / warning-50 / cream-200 / systemGreen-100)
     * was random colour noise. The fix: every plate uses the same tint.
     *
     * Source-level: every plate <div> emits the same bg-* and text-*
     * class pair. We allow the chosen pair to be either
     * (bg-systemGray-100 + text-systemGray-600) — iOS Settings — or
     * (bg-systemBlue-50 + text-systemBlue-600) — accent treatment.
     */
    public function test_dashboard_kpi_icon_plates_share_one_tint(): void
    {
        $path = self::projectRootPath() . self::DASHBOARD_FILE;
        $src = (string) self::readFile($path);
        $this->assertNotNull($src);

        // Scope: the icon-plate <div> lives inside each data-stat-card.
        preg_match_all(
            '/<UiCard[^>]*\bdata-stat-card="[^"]+"[^>]*>[\s\S]*?<\/UiCard>/',
            $src,
            $matches
        );
        $cards = $matches[0] ?? [];
        $this->assertGreaterThanOrEqual(
            5,
            count($cards),
            'DashboardPage.vue must render at least 5 data-stat-card elements for the icon-plate tint check.'
        );

        // Each plate must carry exactly ONE pair (the "tint class") so
        // a future contributor who adds a coloured tint to a single
        // card fails the test. We assert the tint pair is present and
        // that the legacy multi-tint strings are gone from the
        // icon-plate regions.
        $tintPairs = [
            ['bg-systemGray-100', 'text-systemGray-600'],
            ['bg-systemBlue-50',  'text-systemBlue-600'],
        ];
        $foundTint = null;
        foreach ($cards as $idx => $card) {
            $cardTint = null;
            foreach ($tintPairs as $pair) {
                if (
                    str_contains($card, $pair[0]) &&
                    str_contains($card, $pair[1])
                ) {
                    $cardTint = $pair;
                    break;
                }
            }
            $this->assertNotNull(
                $cardTint,
                "KPI card #{$idx} icon plate must use a unified tint "
                    . "(bg-systemGray-100 + text-systemGray-600, or bg-systemBlue-50 + text-systemBlue-600)."
            );
            if ($foundTint === null) {
                $foundTint = $cardTint;
            } else {
                $this->assertSame(
                    $foundTint,
                    $cardTint,
                    "KPI card #{$idx} icon plate must use the SAME tint as the other four cards (defect 6 — coherent treatment)."
                );
            }
        }

        // Belt-and-braces: the legacy multi-tint classes must be absent
        // from the data-stat-card regions. They were the noise the fix
        // removes.
        $legacyTints = [
            'bg-success-50 text-success-600',   // Pacientes old green
            'bg-warning-50 text-warning-600',   // Profesionales old yellow
            'bg-cream-200 text-ink-500',        // Total Citas old cream
            'bg-systemGreen-100 text-systemGreen-600', // Caja old green-bordered
        ];
        foreach ($cards as $idx => $card) {
            foreach ($legacyTints as $legacy) {
                $this->assertStringNotContainsString(
                    $legacy,
                    $card,
                    "KPI card #{$idx} icon plate must not use the legacy tint `{$legacy}` (defect 6 fix)."
                );
            }
        }
    }
    public function testPr5SidebarGroupHeadersAdded(): void
    {
        $source = (string) self::readFile(self::projectRootPath() . self::APP_LAYOUT_FILE);
        $this->assertSame(2, substr_count($source, 'class="px-6 py-2 text-[11px] uppercase tracking-[0.12em] text-systemGray-500"'));
        $this->assertMatchesRegularExpression('/>\s*Operaciones\s*<\/div>/', $source);
        $this->assertMatchesRegularExpression('/>\s*Configuración\s*<\/div>/', $source);
    }

    public function testPr5NavLabelsRemainInFrozenOrder(): void
    {
        $source = (string) self::readFile(self::projectRootPath() . self::APP_LAYOUT_FILE);
        $labels = ['Dashboard', 'Calendario', 'Pacientes', 'Profesionales', 'Ambientes', 'Tipos de Cita', 'Sucursales', 'Metodos de Pago', 'Catálogo de Procedimientos', 'Mis Procedimientos', 'Business Intelligence', 'Caja', 'Planes de Tratamiento', 'Presupuestos', 'Historias Clínicas', 'Especialidades', 'Análisis IA'];
        $positions = array_map(fn (string $label): int => strpos($source, "name: '{$label}'"), $labels);
        $this->assertCount(17, array_filter($positions, fn (int|false $position): bool => $position !== false));
        $sorted = $positions;
        sort($sorted);
        $this->assertSame($sorted, $positions);
    }

}
