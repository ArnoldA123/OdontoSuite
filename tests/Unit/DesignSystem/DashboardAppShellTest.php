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
    private static function projectRootPath(): string
    {
        return dirname(__DIR__, 3);
    }

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
        if (! is_file($absPath)) {
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
        $path = self::projectRootPath().self::DASHBOARD_FILE;
        $this->assertFileExists($path, 'DashboardPage.vue must exist');

        $gradients = self::grepCount('linear-gradient|bg-gradient', $path);
        $this->assertSame(
            0,
            $gradients,
            'DashboardPage.vue must contain zero `linear-gradient` / `bg-gradient` declarations (DoD #4). Found: '.$gradients
        );
    }

    /**
     * DoD #4 — DashboardPage.vue must not embed `<style scoped>` blocks.
     * The 149-LOC inline style block is being deleted in PR3 and replaced
     * with primitive + Tailwind classes.
     */
    public function test_dashboard_page_no_scoped_style_block(): void
    {
        $src = self::readFile(self::projectRootPath().self::DASHBOARD_FILE);
        $this->assertNotNull($src);
        // Look for `<style ...>` (optionally with `scoped`).
        $matches = preg_match_all('/<style\b[^>]*>/i', (string) $src);
        $this->assertSame(
            0,
            (int) $matches,
            'DashboardPage.vue must contain zero <style> blocks (DoD #4). Found: '.$matches
        );
    }

    /**
     * DoD #4 — DashboardPage.vue must not contain any hand-written hex literals.
     * All color values must come from the token layer (tokens.js / Tailwind
     * classes / CSS custom properties).
     */
    public function test_dashboard_page_no_hex_literals(): void
    {
        $path = self::projectRootPath().self::DASHBOARD_FILE;
        $hex = self::grepCount('#[0-9a-fA-F]{6}', $path);
        $this->assertSame(
            0,
            $hex,
            'DashboardPage.vue must contain zero hand-written #RRGGBB hex literals. Found: '.$hex
        );
    }

    /**
     * WU2 / D4 single-surface guard. Cash state lives on exactly ONE
     * surface: the "Saldo de Caja" card. The header pill and its state hook
     * are gone; the state line renders through data-cash-state (Spanish
     * label in the slot, raw English keys only as the hook value).
     */
    public function test_dashboard_cash_state_lives_on_the_single_cash_card(): void
    {
        $path = self::projectRootPath().self::DASHBOARD_FILE;
        $src = (string) self::readFile($path);
        $this->assertNotNull($src);

        // The retired pill and its hooks must not survive anywhere.
        $this->assertStringNotContainsString(
            'data-cash-pill',
            $src,
            'DashboardPage.vue must not keep the retired cash pill hooks (D4: one cash surface).'
        );
        $this->assertStringNotContainsString(
            'cashStatusPillState',
            $src,
            'DashboardPage.vue must not keep the pill state computed (D4: the state key feeds data-cash-state on the card).'
        );

        // The state line renders on the cash card through the hook binding.
        $this->assertStringContainsString(
            ':data-cash-state="cashStateKey"',
            $src,
            'DashboardPage.vue must render the cash state line through the data-cash-state hook binding.'
        );
        $this->assertStringContainsString(
            'data-cash-state',
            $this->statCardRegion($src, 'cash-balance'),
            'The data-cash-state state line must live inside the cash-balance card (the single cash surface).'
        );

        $oldStatusPill = preg_match_all('/<UiStatusPill\b[^>]*\bcashStatusPillStatus\b/i', $src);
        $this->assertSame(
            0,
            (int) $oldStatusPill,
            'DashboardPage.vue must not bind cash status to <UiStatusPill> - that primitive has no entry for the cash states and would print the raw key.'
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
     * TEMPLATE section at all, and in the SCRIPT section only inside the
     * cashStateKey computed. Any DOM-bound expression that returns one of
     * those keys would print English in a Spanish UI (the bug the user
     * reported).
     *
     * The cashStateKey computed block (the legitimate home for those keys
     * - they ride as the data-cash-state attribute only) is stripped from
     * the script body before the check runs.
     *
     * The test is intentionally strict: a future contributor who reaches
     * for one of these strings and wires it to a primitive's prop binding
     * will fail the test, even if the visible DOM hasn't yet regressed.
     */
    public function test_dashboard_no_raw_english_cash_status_keys_appear_in_source(): void
    {
        $path = self::projectRootPath().self::DASHBOARD_FILE;
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
        // cashStateKey computed (the only legitimate home), then
        // assert no other code references the raw keys.
        $script = '';
        if (preg_match('/<script\s+setup>([\s\S]*?)<\/script>/', $src, $m)) {
            $script = $m[1];
        }

        // Strip the cashStateKey computed (the only allowed home for
        // these keys). The trailing semicolon is optional — this codebase omits
        // it, and requiring it made the strip silently match nothing, so the
        // test failed on the very block it was meant to exempt.
        $scriptStripped = preg_replace(
            '/const\s+cashStateKey\s*=\s*computed\(\s*\(\)\s*=>\s*\{[\s\S]*?\}\s*\)\s*;?/m',
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
                "DashboardPage.vue script section must not contain the raw English cash key {$needle} outside the cashStateKey computed (would leak English to the DOM if wired to a primitive)."
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
        $path = self::projectRootPath().self::APP_LAYOUT_FILE;
        $this->assertFileExists($path);

        $count = self::grepCount('surface-glass', $path);
        $this->assertGreaterThanOrEqual(
            2,
            $count,
            'AppLayout.vue must apply .surface-glass to at least two chrome surfaces (sidebar + topbar). Found: '.$count
        );
    }

    /**
     * DoD #5 — AppLayout.vue must NOT use `h-screen` or `height: 100vh`.
     * The spec requires `min-h-[100dvh]` (dynamic viewport height) instead.
     */
    public function test_app_layout_no_h_screen_or_height_vh(): void
    {
        $path = self::projectRootPath().self::APP_LAYOUT_FILE;
        $src = (string) self::readFile($path);
        $this->assertNotNull($src);

        $hScreen = preg_match_all('/\bh-screen\b/', $src);
        $this->assertSame(
            0,
            (int) $hScreen,
            'AppLayout.vue must not use h-screen (use min-h-[100dvh]). Found: '.$hScreen
        );

        $vh = preg_match_all('/height\s*:\s*100vh/i', $src);
        $this->assertSame(
            0,
            (int) $vh,
            'AppLayout.vue must not use `height: 100vh`. Found: '.$vh
        );
    }

    /**
     * DoD #5 — AppLayout.vue must use `min-h-[100dvh]` so mobile browser
     * chrome doesn't clip content.
     */
    public function test_app_layout_uses_min_dvh(): void
    {
        $path = self::projectRootPath().self::APP_LAYOUT_FILE;
        $minDvh = self::grepCount('min-h-\\[100dvh\\]', $path);
        $this->assertGreaterThanOrEqual(
            1,
            $minDvh,
            'AppLayout.vue must use min-h-[100dvh] for full-height pages (DoD #5). Found: '.$minDvh
        );
    }

    /**
     * WU3 / D5 — the "Acciones rápidas" block re-listed sidebar navigation
     * (/patients, /professionals, /environments, /business-intelligence)
     * and is removed whole: no loaded section, no loading skeleton, no
     * destination tiles, and no now-dead navigation handlers. The sidebar
     * owns those destinations.
     */
    public function test_dashboard_renders_no_quick_actions_block(): void
    {
        $path = self::projectRootPath().self::DASHBOARD_FILE;
        $src = (string) self::readFile($path);
        $this->assertNotNull($src);

        foreach (['Cargando acciones rápidas', 'Acciones rápidas'] as $label) {
            $this->assertDoesNotMatchRegularExpression(
                '/<section\b[^>]*aria-label="'.preg_quote($label, '/').'"/',
                $src,
                'DashboardPage.vue must not render the quick-actions section "'.$label.'" (WU3 / D5: the dashboard never re-lists sidebar navigation).'
            );
        }

        $this->assertSame(
            0,
            preg_match_all('/\bdata-action\s*=\s*[\'\"][^\'\"]+[\'\"]/', $src),
            'DashboardPage.vue must render zero data-action destination tiles (WU3 / D5).'
        );

        foreach (
            [
                'goToCalendar',
                'goToPatients',
                'goToProfessionals',
                'goToEnvironments',
                'goToBusinessIntelligence',
            ] as $handler
        ) {
            $this->assertStringNotContainsString(
                $handler,
                $src,
                "DashboardPage.vue must drop the now-dead {$handler} handler with the quick-actions block (WU3 / D5)."
            );
        }
    }

    /**
     * U1 / U2 — the commercial pending answers moved to Pendientes. The
     * dashboard keeps neither the merged row model nor the type badges; the
     * receiving contract lives in ContinuityPendingSectionTest.
     */
    public function test_dashboard_no_longer_owns_the_pending_block(): void
    {
        $path = self::projectRootPath().self::DASHBOARD_FILE;
        $src = (string) self::readFile($path);
        $this->assertNotNull($src);

        foreach (['pendingRows', 'data-pending-row', "typeLabel: 'Presupuesto'"] as $retired) {
            $this->assertStringNotContainsString(
                $retired,
                $src,
                "DashboardPage.vue must not keep \"{$retired}\": the pending answers live in Pendientes (U1/U2)."
            );
        }

        $this->assertStringNotContainsString(
            '/api/dashboard/pending',
            $src,
            'DashboardPage.vue must not request the pending answers anymore (U1/U2).'
        );
        $this->assertStringContainsString(
            "to: '/continuity'",
            $src,
            'The dashboard points at Pendientes instead of publishing its answers.'
        );
    }

    /**
     * DoD #1 — FloatingActionButton.vue (a chrome button used in the layout
     * cluster) must not contain gradient class names. Gradients are
     * decoration; this is a clinical tool.
     */
    public function test_floating_action_button_no_gradient_classes(): void
    {
        $path = self::projectRootPath().self::FAB_FILE;
        $gradients = self::grepCount('bg-gradient-to-|bg-gradient ', $path);
        $this->assertSame(
            0,
            $gradients,
            'FloatingActionButton.vue must not use bg-gradient classes (DoD #1 - no gradients-as-decoration). Found: '.$gradients
        );
    }

    /**
     * DoD #4 — DashboardPage.vue + AppLayout.vue must contain zero hex literals.
     * Combined: every color comes from the token layer.
     */
    public function test_dashboard_and_layout_combined_no_hex_literals(): void
    {
        $dir = self::projectRootPath().'/resources/js/modules/dashboard/';
        $this->assertDirectoryExists($dir);

        $layoutDir = self::projectRootPath().'/resources/js/components/layout/';
        $this->assertDirectoryExists($layoutDir);

        $hexCount = SourceGrep::count('#[0-9a-fA-F]{6}', $dir, $layoutDir);

        $this->assertSame(
            0,
            $hexCount,
            'Dashboard + layout files must contain zero #RRGGBB hex literals. Found: '.$hexCount
        );
    }

    /**
     * DoD #1 — `images/pexels` must not appear in any JS source file.
     * Photography is reserved for the Login hero and 404 page only.
     */
    public function test_no_pexels_image_references_in_js_source(): void
    {
        $dir = self::projectRootPath().'/resources/js/';
        $this->assertDirectoryExists($dir);

        $peels = self::grepCount('images/pexels', $dir);
        $this->assertSame(
            0,
            $peels,
            'resources/js/ must not reference images/pexels (DoD #1 - photography reserved for Login + 404). Found: '.$peels
        );
    }

    /**
     * U2 — the strip keeps the two numbers it can source honestly: the day's
     * appointment count (from the summary) and the drawer balance. The three
     * day buckets that read the retired list, and the historical metrics that
     * live in BI (D2), are gone.
     */
    public function test_dashboard_contains_the_two_sourceable_stat_card_labels(): void
    {
        $path = self::projectRootPath().self::DASHBOARD_FILE;
        $src = (string) self::readFile($path);
        $this->assertNotNull($src);

        $labels = ['Citas Hoy', 'Saldo de Caja'];
        foreach ($labels as $label) {
            $this->assertStringContainsString(
                $label,
                $src,
                "DashboardPage.vue must render the stat-card label \"{$label}\" (verified content)."
            );
        }

        $retired = [
            'data-stat-card="completed-today"',
            'data-stat-card="pending-today"',
            'data-stat-card="total-patients"',
            'data-stat-card="total-appointments-month"',
            'data-stat-card="total-income"',
            'data-stat-card="cash-status"',
        ];
        foreach ($retired as $handle) {
            $this->assertStringNotContainsString(
                $handle,
                $src,
                "DashboardPage.vue must not keep the retired KPI handle {$handle} (U2: two sourceable cards)."
            );
        }
    }

    /**
     * WU3 / D5 — the quick-action tile copy left with the block: the
     * destination titles and their subtitles are gone as rendered element
     * text. The sidebar owns those destinations and their names.
     */
    public function test_dashboard_quick_action_tile_copy_is_removed(): void
    {
        $path = self::projectRootPath().self::DASHBOARD_FILE;
        $src = (string) self::readFile($path);
        $this->assertNotNull($src);

        foreach (
            [
                'Gestionar base de datos',
                'Gestionar equipo',
                'Configurar espacios',
                'Análisis y estadísticas',
            ] as $subtitle
        ) {
            $this->assertStringNotContainsString(
                $subtitle,
                $src,
                "DashboardPage.vue must drop the quick-action tile subtitle \"{$subtitle}\" (WU3 / D5)."
            );
        }

        // The removed tile used the title-case label; the agenda header
        // keeps the sentence-case "Nueva cita" CTA. Match the rendered
        // element text so a design-record comment naming the removed tile
        // cannot fail the guard.
        $this->assertDoesNotMatchRegularExpression(
            '/>\s*Nueva Cita\s*</',
            $src,
            'DashboardPage.vue must not render the removed "Nueva Cita" quick-action tile (single CTA per destination).'
        );
    }

    /**
     * U2 — the retired blocks leave no navigation handler or overflow link
     * behind. The only page-level navigation is the three destination links.
     */
    public function test_dashboard_renders_no_ver_calendario_or_ver_todos_ctas(): void
    {
        $path = self::projectRootPath().self::DASHBOARD_FILE;
        $src = (string) self::readFile($path);
        $this->assertNotNull($src);

        $this->assertSame(
            0,
            substr_count($src, 'Ver calendario'),
            'DashboardPage.vue must render zero "Ver calendario" CTAs: the calendar card is the only way in.'
        );
        $this->assertSame(
            0,
            substr_count($src, 'Ver todos'),
            'DashboardPage.vue must render zero "Ver todos" overflow links.'
        );
        $this->assertStringNotContainsString(
            'goToCalendar',
            $src,
            'DashboardPage.vue must not keep a calendar navigation handler: the destination link owns it.'
        );

        foreach (['Agenda de hoy', 'Próximos días', 'Requiere tu atención'] as $label) {
            $this->assertStringNotContainsString(
                'aria-label="'.$label.'"',
                $src,
                "DashboardPage.vue must not render the retired section \"{$label}\" (U2)."
            );
        }
    }

    /**
     * T3 / U2 — the KPI cards are a static reference strip: none carries the
     * UiCard clickable/hover props or an @click binding. The cash card
     * "Ir a Caja" action (WU2 / D4) remains the only cash destination CTA.
     */
    public function test_dashboard_stat_cards_have_no_click_affordance(): void
    {
        $path = self::projectRootPath().self::DASHBOARD_FILE;
        $src = (string) self::readFile($path);
        $this->assertNotNull($src);

        preg_match_all(
            '/<UiCard[^>]*\bdata-stat-card="[^"]+"[^>]*>/',
            $src,
            $matches
        );
        $openings = $matches[0] ?? [];
        $this->assertSame(
            2,
            count($openings),
            'DashboardPage.vue must render exactly 2 stat cards for the static-strip check.'
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
            'DashboardPage.vue must keep the cash card "Ir a Caja" action as the only cash destination CTA (D4).'
        );
        $this->assertStringContainsString(
            'goToCashRegister',
            $this->statCardRegion($src, 'cash-balance'),
            'The single "Ir a Caja" CTA must live inside the cash-balance card (D4: contextual action on the single cash surface).'
        );
    }

    /**
     * Region helper: the source slice of a single stat card (the UiCard
     * carrying `data-stat-card` through its closing tag).
     */
    private function statCardRegion(string $src, string $statKey): string
    {
        preg_match(
            '/<UiCard[^>]*\bdata-stat-card="'.preg_quote($statKey, '/').'"[^>]*>[\s\S]*?<\/UiCard>/',
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
            '/<section\b[^>]*aria-label="'.preg_quote($ariaLabel, '/').'"/',
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
        $path = self::projectRootPath().self::DASHBOARD_FILE;
        $src = (string) self::readFile($path);
        $this->assertNotNull($src);

        $this->assertMatchesRegularExpression(
            '/setTimeout\([\s\S]*?},\s*300\s*\)/',
            $src,
            'DashboardPage.vue must keep a 300ms trailing-edge debounce on WebSocket bursts (slice 08 / FF-015).'
        );
    }

    /**
     * U2 — the page keeps no shared empty state: the three blocks that owned
     * one are retired and the destinations are static links.
     */
    public function test_dashboard_renders_no_shared_empty_state(): void
    {
        $path = self::projectRootPath().self::DASHBOARD_FILE;
        $src = (string) self::readFile($path);
        $this->assertNotNull($src);

        $this->assertStringNotContainsString(
            '<DashboardSectionEmpty',
            $src,
            'DashboardPage.vue must render no shared empty state: its lists are gone (U2).'
        );
        $this->assertStringNotContainsString(
            'data-state="empty-',
            $src,
            'DashboardPage.vue must render no empty-state hook (U2).'
        );
    }

    /**
     * T5 / U2 — the shared per-section error component left with the three
     * blocks. The summary error is the only error surface of the page, and it
     * keeps its own distinct role.
     */
    public function test_dashboard_uses_shared_section_error_component(): void
    {
        $path = self::projectRootPath().self::DASHBOARD_FILE;
        $src = (string) self::readFile($path);
        $this->assertNotNull($src);

        $this->assertStringNotContainsString(
            '<DashboardSectionError',
            $src,
            'DashboardPage.vue must not render the shared section error anymore (U2).'
        );
        foreach (['data-state="error-appointments"', 'data-state="error-upcoming"', 'data-state="error-pending"'] as $retired) {
            $this->assertStringNotContainsString(
                $retired,
                $src,
                "DashboardPage.vue must not keep the retired hook {$retired} (U2)."
            );
        }

        // The page-level stats error reports only the summary in its own
        // space (#388): a failure there never unmounts the destinations.
        $this->assertSame(
            1,
            substr_count($src, 'data-state="error-stats"'),
            'DashboardPage.vue must keep exactly one page-level stats error block.'
        );
    }

    /**
     * WU3 / D9 + U2 — the shared compact empty pattern survives in the
     * primitive, but the dashboard no longer renders it: its lists are gone
     * and the destination cards are static links.
     */
    public function test_dashboard_uses_shared_section_empty_component(): void
    {
        $path = self::projectRootPath().self::DASHBOARD_FILE;
        $src = (string) self::readFile($path);
        $this->assertNotNull($src);

        $primitivePath = self::projectRootPath().'/resources/js/components/ui/UiSectionEmpty.vue';
        $this->assertFileExists(
            $primitivePath,
            'UiSectionEmpty.vue must exist (shared empty-state primitive).'
        );
        $primitiveSrc = (string) self::readFile($primitivePath);

        $this->assertStringContainsString(
            'data-section-empty',
            $primitiveSrc,
            'UiSectionEmpty.vue must expose the data-section-empty hook (T5).'
        );
        $this->assertStringContainsString(
            'p-6',
            $primitiveSrc,
            'UiSectionEmpty.vue must own the compact padding token used by every empty state (WU3 / D9).'
        );
        $this->assertStringNotContainsString(
            'p-10',
            $primitiveSrc,
            'UiSectionEmpty.vue must drop the retired generous p-10 padding (WU3 / D9: compact pattern).'
        );
        $this->assertMatchesRegularExpression(
            '/h-8 w-8/',
            $primitiveSrc,
            'UiSectionEmpty.vue must render the smaller h-8 w-8 icon (WU3 / D9).'
        );

        $this->assertSame(
            0,
            preg_match_all('/<DashboardSectionEmpty\b/', $src),
            'DashboardPage.vue must render zero shared empty states: its lists are retired (U2).'
        );
    }

    /**
     * U2 — "Dónde seguir" is the replacement contract: three link cards, one
     * line of what each resolves, each gated by the permission its own screen
     * demands and carrying no counter.
     */
    public function test_dashboard_destinations_are_gated_links(): void
    {
        $path = self::projectRootPath().self::DASHBOARD_FILE;
        $src = (string) self::readFile($path);
        $this->assertNotNull($src);

        $this->assertStringContainsString(
            'data-dashboard-block="destinations"',
            $src,
            'DashboardPage.vue must render the destinations block.'
        );
        $this->assertStringContainsString(
            '<RouterLink',
            $src,
            'The destination cards must be real links (RouterLink), never clickable divs.'
        );

        foreach (["'viewCalendar'", "'viewMyDay'"] as $permission) {
            $this->assertStringContainsString(
                'permission: '.$permission,
                $src,
                "Each single-grant destination must declare the read permission of its screen ({$permission})."
            );
        }

        // S15: Pendientes answers two grants. The queue desks reach it through
        // `view.continuity`; finance reaches its commercial answers through
        // `view.quotation`.
        $this->assertStringContainsString(
            "anyPermissions: ['viewContinuity', 'viewQuotation']",
            $src,
            'The Pendientes destination must accept either grant.'
        );

        $this->assertStringContainsString(
            'DESTINATIONS.filter',
            $src,
            'The destinations must be filtered by the permissions the session holds.'
        );
        $this->assertStringContainsString(
            '{{ destination.title }}',
            $src,
            'Each destination card renders its title from the declared list.'
        );
        $this->assertStringContainsString(
            '{{ destination.description }}',
            $src,
            'Each destination card renders the line of what it resolves.'
        );
    }

    /**
     * DoD — DashboardPage.vue must use `UiSkeleton` for the loading state.
     */
    public function test_dashboard_uses_skeleton_for_loading(): void
    {
        $path = self::projectRootPath().self::DASHBOARD_FILE;
        $src = (string) self::readFile($path);
        $this->assertNotNull($src);

        $this->assertMatchesRegularExpression(
            '/<UiSkeleton\b/',
            $src,
            'DashboardPage.vue must render <UiSkeleton /> placeholders in the loading state.'
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
        $path = self::projectRootPath().self::DASHBOARD_FILE;
        $src = (string) self::readFile($path);
        $this->assertNotNull($src);

        $this->assertStringContainsString(
            'tabular-nums',
            $src,
            'DashboardPage.vue stat-card numbers must use tabular-nums (font-variant-numeric: tabular-nums).'
        );
    }

    /* ============================================================ */
    /* PR4 — Dashboard exemplar polish slice */
    /* */
    /* These tests assert the dashboard polish tokens + grid fixed */
    /* slots + greeting hierarchy + topbar optical weight + */
    /* quick-action keyhint affordance + empty-state illustration */
    /* are present in the source. They are RED before the PR4 */
    /* implementation lands and GREEN after. */
    /* ============================================================ */

    /**
     * WU1 — the KPI card anatomy is three lines: small uppercase eyebrow,
     * big tabular number, ONE scope caption (D3). The four-row slot grid
     * (h-4 / h-12 / min-h-6 / h-4) and the reserved chip slot are gone:
     * no blank slot survives in the strip.
     */
    public function test_dashboard_stat_cards_use_eyebrow_number_caption_anatomy(): void
    {
        $path = self::projectRootPath().self::DASHBOARD_FILE;
        $src = (string) self::readFile($path);
        $this->assertNotNull($src);

        preg_match_all(
            '/<UiCard[^>]*\bdata-stat-card="[^"]+"[^>]*>([\s\S]*?)<\/UiCard>/',
            $src,
            $matches
        );
        $cards = $matches[0] ?? [];
        $this->assertSame(
            2,
            count($cards),
            'DashboardPage.vue must render exactly 2 stat cards carrying data-stat-card (U2).'
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
        $path = self::projectRootPath().self::DASHBOARD_FILE;
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
     * 4.1.5 / U2 — each stat card carries a `data-stat-card` attribute whose
     * value equals the stat key (`appointments-today`, `cash-balance`) plus the
     * `data-stat` hook on the value element. These are the handles the JS smoke
     * suite uses to assert the numbers.
     */
    public function test_dashboard_stat_cards_carry_data_stat_card_attribute(): void
    {
        $path = self::projectRootPath().self::DASHBOARD_FILE;
        $src = (string) self::readFile($path);
        $this->assertNotNull($src);

        $expectedKeys = ['appointments-today', 'cash-balance'];
        foreach ($expectedKeys as $key) {
            $this->assertStringContainsString(
                'data-stat-card="'.$key.'"',
                $src,
                "DashboardPage.vue must mark the \"{$key}\" card with data-stat-card=\"{$key}\"."
            );
            $this->assertStringContainsString(
                'data-stat="'.$key.'"',
                $src,
                "DashboardPage.vue must mark the \"{$key}\" value element with data-stat=\"{$key}\" (stable testability hook)."
            );
        }
    }

    /**
     * 4.2.1 / WU4 — The greeting "Buenos días, Admin" must NOT be rendered
     * as an `<h1>` or `<h2>` (it competes with the topbar's `<h1>`), and it
     * rides the login's display step (text-4xl font-semibold) as the hero
     * band's headline (WU4 / D6). The topbar keeps owning the heading
     * hierarchy; the hero band stays typographic.
     */
    public function test_dashboard_greeting_is_the_page_header_title(): void
    {
        $path = self::projectRootPath().self::DASHBOARD_FILE;
        $src = (string) self::readFile($path);
        $this->assertNotNull($src);

        // The greeting is the shared header's title: the anatomy (36px/600 on
        // the label token) lives in PageHeader, so the page only feeds it.
        $this->assertMatchesRegularExpression(
            '/<PageHeader\b[\s\S]{0,400}?:title="`\$\{getGreeting\(\)\}, \$\{firstName\}`"/',
            $src,
            'DashboardPage.vue must pass the greeting to <PageHeader :title> (one header anatomy, no local hero).'
        );
        $this->assertStringContainsString(
            'kicker="HOY"',
            $src,
            'DashboardPage.vue must declare the HOY kicker.'
        );

        // The retired compact greeting size is forbidden, and the page no
        // longer declares display typography of its own.
        $this->assertDoesNotMatchRegularExpression(
            '/text-lg[^"]*font-medium[^"]*text-theme-secondary[^"]*"[^>]*>\s*\{\{\s*getGreeting\(\)\s*\}\}/',
            $src,
            'DashboardPage.vue greeting must not keep the retired text-lg font-medium compact sizing.'
        );
        $this->assertStringNotContainsString(
            'text-4xl',
            $src,
            'DashboardPage.vue must not carry display typography: the only 36px title is PageHeader\'s.'
        );

        // The page declares no heading element of its own (PageHeader owns it).
        $this->assertSame(
            0,
            (int) preg_match_all('/<h1\b/i', $src),
            'DashboardPage.vue must not declare its own <h1>: PageHeader renders it.'
        );
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
        $path = self::projectRootPath().self::APP_LAYOUT_FILE;
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
     * HOTFIX-DASH-006 — no keyboard-shortcut badge on any dashboard
     * surface. The rule stood on the quick-action cards while they
     * existed; with the tiles removed (WU3 / D5) it is pinned page-wide:
     * zero data-keyhint hooks and zero <kbd> chips.
     */
    public function test_dashboard_renders_no_kbd_shortcut_badges(): void
    {
        $path = self::projectRootPath().self::DASHBOARD_FILE;
        $src = (string) self::readFile($path);
        $this->assertNotNull($src);

        $this->assertDoesNotMatchRegularExpression(
            '/data-keyhint="[A-Z]"/',
            $src,
            'DashboardPage.vue must not carry data-keyhint badges (HOTFIX-DASH-006).'
        );
        $this->assertDoesNotMatchRegularExpression(
            '/<kbd\b/',
            $src,
            'DashboardPage.vue must not render <kbd> keyhint chips (HOTFIX-DASH-006, design-taste §9.D).'
        );
    }

    /**
     * 4.5.1 (revised for U2) — the dashboard no longer renders an empty state,
     * and the page never reaches a third-party image host. The project-wide
     * host ban is pinned by the next test; this one keeps the page-level gate
     * on the surface that used to own the retired illustration.
     */
    public function test_dashboard_drops_the_retired_empty_state_illustration(): void
    {
        $path = self::projectRootPath().self::DASHBOARD_FILE;
        $src = (string) self::readFile($path);
        $this->assertNotNull($src);

        $this->assertStringNotContainsString(
            'data-state="empty-appointments"',
            $src,
            'DashboardPage.vue must not keep the retired empty state hook (U2).'
        );
        $this->assertDoesNotMatchRegularExpression(
            '/\billustration="https?:\/\//i',
            $src,
            'DashboardPage.vue must not bind a remote illustration (air-gapped deployments would render broken images).'
        );
        $this->assertStringNotContainsString(
            'picsum.photos',
            $src,
            'DashboardPage.vue must not reference a third-party image host.'
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
        $dir = self::projectRootPath().'/resources/js/';
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
                    .'(clinical products cannot reach out to third-party image hosts). '
                    ."Found: {$count} occurrences."
            );
        }
    }

    /**
     * D8 / R1 + U2 — the daily appointment count lives only in the KPI, and
     * the only source left is the summary the page fetches. No list, no
     * singular/plural suffix, no second endpoint.
     */
    public function test_dashboard_agenda_header_renders_no_appointment_count(): void
    {
        $path = self::projectRootPath().self::DASHBOARD_FILE;
        $src = (string) self::readFile($path);
        $this->assertNotNull($src);

        foreach (['todayAppointments', "?'cita':'citas'", 'appointments-today\''] as $retired) {
            $this->assertStringNotContainsString(
                $retired,
                $src,
                "DashboardPage.vue must not keep \"{$retired}\" once the appointment list is retired (U2)."
            );
        }

        $kpi = $this->statCardRegion($src, 'appointments-today');
        $this->assertStringContainsString(
            'data-stat="appointments-today"',
            $kpi,
            'The daily count must render in the KPI strip (single source, R1).'
        );
        $this->assertStringContainsString(
            'summary.value?.appointments_today',
            $src,
            'The daily count must read the summary count the page already fetches.'
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
        $path = self::projectRootPath().self::DASHBOARD_FILE;
        $src = (string) self::readFile($path);
        $this->assertNotNull($src);

        // Both labels must be present and rendered with text-xs.
        $expectedLabels = ['Citas Hoy', 'Saldo de Caja'];
        foreach ($expectedLabels as $label) {
            // The eyebrow pattern: <p class="text-xs ... uppercase ... whitespace-nowrap">{{ label }}</p>
            $pattern = '/<p[^>]*\btext-xs\b[^>]*\buppercase\b[^>]*\bwhitespace-nowrap\b[^>]*>\s*'.preg_quote($label, '/').'\s*<\/p>/';
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
            2,
            count($cards),
            'DashboardPage.vue must render exactly 2 data-stat-card elements for the eyebrow uniformity check (U2).'
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
     * T4 / WU4 + U2 — block heading. The KPI strip lives inside block "Hoy"
     * under its h2 (the retired "Resumen del día" footer heading is gone) and
     * the block heading sits at the top, above the card grid.
     */
    public function test_dashboard_hoy_block_heading_sits_above_the_kpi_strip(): void
    {
        $path = self::projectRootPath().self::DASHBOARD_FILE;
        $src = (string) self::readFile($path);
        $this->assertNotNull($src);

        $region = $this->sectionRegion($src, 'Hoy');

        $this->assertStringContainsString(
            '<h2 class="text-2xl font-semibold text-label">Hoy</h2>',
            $region,
            'DashboardPage.vue block "Hoy" must render its h2 with the strongest tier anatomy (WU4 / D7).'
        );

        // The heading comes first; the card grid follows it.
        $headingPos = strpos($region, '<h2');
        $gridPos = strpos($region, 'data-reveal="kpi"');
        $this->assertNotFalse($headingPos, 'Block "Hoy" must contain the h2 (T4).');
        $this->assertNotFalse($gridPos, 'Block "Hoy" must contain the card grid (data-reveal="kpi").');
        $this->assertLessThan(
            $gridPos,
            $headingPos,
            'DashboardPage.vue block heading must sit at the top of the block, above the card grid (T4).'
        );

        // The block owns no list anymore: the strip is its only content (U2).
        $this->assertStringNotContainsString(
            'aria-label="Agenda de hoy"',
            $src,
            'Block "Hoy" must not contain the retired agenda (U2).'
        );
    }

    /**
     * T4 / WU4 + U2 — one heading ladder: both blocks own an h2, sentence case
     * everywhere, and the retired headings are gone with their sections.
     */
    public function test_dashboard_section_headings_use_sentence_case(): void
    {
        $path = self::projectRootPath().self::DASHBOARD_FILE;
        $src = (string) self::readFile($path);
        $this->assertNotNull($src);

        $this->assertStringNotContainsString(
            'Acciones rápidas',
            $src,
            'DashboardPage.vue must drop the "Acciones rápidas" heading with its section (WU3 / D5).'
        );
        $this->assertStringNotContainsString(
            'Acciones Rápidas',
            $src,
            'DashboardPage.vue must not keep the title-case "Acciones Rápidas" heading (T4).'
        );

        $ladder = [
            '<h2 class="text-2xl font-semibold text-label">Hoy</h2>',
            '<h2 class="text-2xl font-semibold text-label">Dónde seguir</h2>',
        ];
        foreach ($ladder as $anatomy) {
            $this->assertStringContainsString(
                $anatomy,
                $src,
                "DashboardPage.vue heading ladder must render {$anatomy} (WU4 / D7)."
            );
        }

        foreach (['>Resumen del día<', '>Requiere tu atención<', '>Próximos días<', '>Agenda de hoy<'] as $retiredHeading) {
            $this->assertStringNotContainsString(
                $retiredHeading,
                $src,
                "DashboardPage.vue must keep no retired heading {$retiredHeading} (U2)."
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
        $path = self::projectRootPath().self::DASHBOARD_FILE;
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

        $expectedKeys = ['appointments-today', 'cash-balance'];
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
     * accent dot top-right that read as a status indicator. WU2 removed
     * the header pill that owned the last status dot, so no dot survives.
     */
    public function test_dashboard_kpi_cards_carry_no_decorative_dot(): void
    {
        $path = self::projectRootPath().self::DASHBOARD_FILE;
        $src = (string) self::readFile($path);
        $this->assertNotNull($src);

        preg_match_all(
            '/<UiCard[^>]*\bdata-stat-card="[^"]+"[^>]*>[\s\S]*?<\/UiCard>/',
            $src,
            $matches
        );
        $cards = $matches[0] ?? [];
        $this->assertSame(
            2,
            count($cards),
            'DashboardPage.vue must render exactly 2 stat cards for the decorative-dot check (U2).'
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

        // WU2: the header pill took the last status dot with it; no dot
        // carries meaning anymore.
        $this->assertSame(
            0,
            substr_count($src, 'inline-block w-1.5 h-1.5 rounded-full'),
            'DashboardPage.vue must keep zero status dots after the WU2 pill removal (T4: dots out).'
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
        $path = self::projectRootPath().self::DASHBOARD_FILE;
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
     * D1 / U2 — the day count derives from the summary the page already
     * fetches (`appointments_today`, branch-scoped server side). There is no
     * second list and no second endpoint to disagree with.
     */
    public function test_dashboard_day_kpi_reads_the_summary_count(): void
    {
        $path = self::projectRootPath().self::DASHBOARD_FILE;
        $src = (string) self::readFile($path);
        $this->assertNotNull($src);

        $this->assertMatchesRegularExpression(
            '/const citasHoy = computed\(\(\) => \{[\s\S]*?summary\.value\?\.appointments_today/',
            $src,
            'DashboardPage.vue must read the day count from the summary payload.'
        );
        $this->assertStringContainsString(
            '{{ citasHoyDisplay }}',
            $src,
            'DashboardPage.vue must render the day count through citasHoyDisplay.'
        );
        $this->assertStringNotContainsString(
            'todayAppointments',
            $src,
            'DashboardPage.vue must not keep the retired appointment list as a KPI source (U2).'
        );
        $this->assertStringNotContainsString(
            '/api/dashboard/appointments-today',
            $src,
            'DashboardPage.vue must not request the retired today endpoint.'
        );
    }

    /**
     * WU1 — caption grammar. The day caption states the scope of its number in
     * ONE line: the day's agenda.
     */
    public function test_dashboard_scope_captions_state_number_scope(): void
    {
        $path = self::projectRootPath().self::DASHBOARD_FILE;
        $src = (string) self::readFile($path);
        $this->assertNotNull($src);

        $expectedCaptions = [
            'appointments-today' => 'Agenda del día',
        ];
        foreach ($expectedCaptions as $statKey => $captionText) {
            $card = $this->statCardRegion($src, $statKey);
            $this->assertMatchesRegularExpression(
                '/data-kpi-caption="'.$statKey.'"[\s\S]{0,200}?'.preg_quote($captionText, '/').'/',
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
        $layoutPath = self::projectRootPath().self::APP_LAYOUT_FILE;
        $dashboardPath = self::projectRootPath().self::DASHBOARD_FILE;

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
        $path = self::projectRootPath().self::DASHBOARD_FILE;
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
            2,
            count($cards),
            'DashboardPage.vue must render exactly 2 data-stat-card elements for the KPI hairline/elevation check (U2).'
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
        $path = self::projectRootPath().self::DASHBOARD_FILE;
        $src = (string) self::readFile($path);
        $this->assertNotNull($src);

        preg_match_all(
            '/<UiCard[^>]*\bdata-stat-card="[^"]+"[^>]*>[\s\S]*?<\/UiCard>/',
            $src,
            $matches
        );
        $cards = $matches[0] ?? [];
        $this->assertSame(
            2,
            count($cards),
            'DashboardPage.vue must render exactly 2 data-stat-card elements for the icon-plate removal check (U2).'
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

        // T2b / WU3 - the page keeps its heroicons baseline (refresh,
        // error glyph) and the HOTFIX-DASH-007 empty-state line-art SVG
        // (stroke-width 1.5) stays inline; the quick-action tiles that
        // carried @heroicons/vue icons are removed (D5).
        $this->assertStringContainsString(
            '@heroicons/vue/24/outline',
            $src,
            'DashboardPage.vue must import its chrome icons from @heroicons/vue/24/outline (T2b).'
        );

        $this->assertSame(
            0,
            preg_match_all('/\bdata-action\s*=\s*[\'\"][^\'\"]+[\'\"]/', $src),
            'DashboardPage.vue must render zero data-action tiles (WU3 / D5 removed the heroicons tile surface).'
        );

        // The brand lockup is out of the page header, so the page keeps no
        // inline glyph at all: every icon comes from heroicons.
        $this->assertStringNotContainsString(
            'stroke-width="1.75"',
            $src,
            'DashboardPage.vue must not keep an inline brand glyph inside the page header.'
        );
        $this->assertStringNotContainsString(
            'DentalMotifLayer',
            $src,
            'DashboardPage.vue must not mount the dental motif inside the header.'
        );
    }

    /**
     * HOTFIX-DASH-001 wins over the PR5 sidebar headers: section labels
     * were removed (hairline dividers instead), pinned by
     * SidebarEyebrowAuditTest. This test now pins the removal — zero group
     * headers and no Operaciones/Configuración labels in AppLayout.vue.
     */
    public function test_pr5_sidebar_group_headers_added(): void
    {
        $source = (string) self::readFile(self::projectRootPath().self::APP_LAYOUT_FILE);
        $this->assertSame(0, substr_count($source, 'class="px-6 py-2 text-[11px] uppercase tracking-[0.12em] text-systemGray-500"'));
        $this->assertDoesNotMatchRegularExpression('/>\s*Operaciones\s*<\/div>/', $source);
        $this->assertDoesNotMatchRegularExpression('/>\s*Configuración\s*<\/div>/', $source);
    }

    public function test_pr5_nav_labels_remain_in_frozen_order(): void
    {
        $source = (string) self::readFile(self::projectRootPath().self::APP_LAYOUT_FILE);
        // T6 — the payment-methods entry carries the accent every sibling
        // label already had. Route path and permission keys stay ASCII.
        $labels = ['Dashboard', 'Calendario', 'Pacientes', 'Profesionales', 'Ambientes', 'Tipos de cita', 'Sucursales', 'Métodos de pago', 'Catálogo de procedimientos', 'Mis procedimientos', 'Business Intelligence', 'Caja', 'Planes de tratamiento', 'Presupuestos', 'Historias clínicas', 'Registros de especialidad', 'Análisis de IA'];
        $positions = array_map(fn (string $label): int => strpos($source, "name: '{$label}'"), $labels);
        $this->assertCount(17, array_filter($positions, fn (int|false $position): bool => $position !== false));
        $sorted = $positions;
        sort($sorted);
        $this->assertSame($sorted, $positions);
    }

    /**
     * T6 — one accented name for the payment-methods destination. The sidebar
     * entry and the module heading both read "Métodos de pago"; the ASCII
     * spelling is retired from user-visible copy (identifiers, route paths
     * and permission keys keep it by design).
     */
    public function test_t6_payment_methods_copy_carries_the_accent(): void
    {
        $layout = (string) self::readFile(self::projectRootPath().self::APP_LAYOUT_FILE);
        $this->assertStringContainsString(
            "name: 'Métodos de pago'",
            $layout,
            'AppLayout.vue sidebar entry must read "Métodos de pago" (T6 naming coherence with every sibling label).'
        );
        $this->assertStringNotContainsString(
            "name: 'Metodos de Pago'",
            $layout,
            'AppLayout.vue must not keep the unaccented sidebar label (T6).'
        );

        $pagePath = self::projectRootPath()
            .'/resources/js/modules/settings/payment-methods/PaymentMethodsPage.vue';
        $this->assertFileExists($pagePath, 'PaymentMethodsPage.vue must exist (T6 naming boundary).');
        $page = (string) self::readFile($pagePath);

        $this->assertMatchesRegularExpression(
            '/data-hero-title[^>]*>\s*Métodos de pago\s*</',
            $page,
            'PaymentMethodsPage.vue hero heading must read "Métodos de pago" (T6).'
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
     * T6 / U2 — every dashboard action is a real control. The remaining
     * actions (refresh, cash CTA) render through real buttons and the three
     * destination cards are real links: no clickable card surfaces, no
     * role="button" divs and no fake button rows.
     */
    public function test_t6_dashboard_actions_stay_real_buttons(): void
    {
        $src = (string) self::readFile(self::projectRootPath().self::DASHBOARD_FILE);
        $this->assertNotNull($src);

        $this->assertDoesNotMatchRegularExpression(
            '/\brole="button"/',
            $src,
            'DashboardPage.vue must not use role="button" divs (T6: real button semantics).'
        );
        $this->assertDoesNotMatchRegularExpression(
            '/<UiCard[^>]*\bclickable\b/',
            $src,
            'DashboardPage.vue must not bind the UiCard clickable prop (T6: the button owns the action).'
        );
        $this->assertStringContainsString(
            '<RouterLink',
            $src,
            'The destination cards must be real links (U2: navigation belongs to an anchor).'
        );
        $this->assertStringContainsString(
            '@click="goToCashRegister"',
            $src,
            'The cash CTA must stay a real button binding (D4).'
        );
    }

    /**
     * T6 — chevrons ride the documented 1.5 stroke baseline (apple-design
     * §16), the same value the empty-state line art already uses. The arrow
     * chevron in the "Ver calendario" CTA was the last stroke-width="2"
     * holdout on the page.
     */
    public function test_t6_dashboard_strokes_use_the_1_5_baseline(): void
    {
        $src = (string) self::readFile(self::projectRootPath().self::DASHBOARD_FILE);
        $this->assertNotNull($src);

        $this->assertSame(
            0,
            substr_count($src, 'stroke-width="2"'),
            'DashboardPage.vue must contain zero stroke-width="2" strokes (T6: every icon rides the 1.5 baseline).'
        );
    }
}
