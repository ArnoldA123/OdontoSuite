<?php

namespace Tests\Feature\Ui;

use App\Models\User;
use Tests\TestCase;

/**
 * HOTFIX-DASH-006 / WU3 — Quick Actions NO letter-key badge, pinned as a
 * removal.
 *
 * Quick Action cards carried the banned Material keyboard-shortcut chip
 * (<span>/<kbd> with a single uppercase letter). WU3 / D5 removed the
 * whole quick-actions block, so both the tiles and any shortcut badge are
 * gone. Pin the RULE page-wide: zero data-action tiles, zero <kbd> chips
 * and zero single-uppercase-letter badge markup. (design-taste §9.D "NO
 * three-equal Material cards".)
 */
class HotfixDashboardQuickActionsTest extends TestCase
{

    private const DASHBOARD_PAGE_REL = '/resources/js/modules/dashboard/DashboardPage.vue';

    private static function dashboardPagePath(): string
    {
        return dirname(__DIR__, 3) . self::DASHBOARD_PAGE_REL;
    }

    public function test_quick_action_cards_are_removed_so_no_kbd_badge_can_return(): void
    {
        $user = User::factory()->make();
        $response = $this->actingAs($user)->get('/dashboard');
        $response->assertStatus(200);

        $this->assertFileExists(
            self::dashboardPagePath(),
            'DashboardPage.vue must exist (HOTFIX-DASH-006 source boundary)'
        );

        $source = (string) file_get_contents(self::dashboardPagePath());

        // Pin the RULE: zero <kbd> elements anywhere on the page, and zero
        // data-action cards to host them on.
        $this->assertDoesNotMatchRegularExpression(
            '/<kbd\b/i',
            $source,
            'DashboardPage.vue MUST NOT contain <kbd> shortcut badges — HOTFIX-DASH-006, design-taste §9.D (no Material keyboard-shortcut reference visual).'
        );

        $this->assertSame(
            0,
            (int) preg_match_all('/\bdata-action\s*=\s*[\'"][^\'"]+[\'"]/i', $source),
            'DashboardPage.vue MUST NOT render the quick-action tiles that carried the badge (WU3 / D5).'
        );
    }

    public function test_no_span_with_single_uppercase_letter_on_dashboard_source(): void
    {
        $user = User::factory()->make();
        $response = $this->actingAs($user)->get('/dashboard');
        $response->assertStatus(200);

        $source = (string) file_get_contents(self::dashboardPagePath());

        // Belt-and-suspenders: zero <span>/<kbd> with a single-uppercase-
        // letter text content anywhere in the page source.
        $spanCount = preg_match_all(
            '/<(?:span|kbd)\b[^>]*>\s*[A-Z]\s*<\/(?:span|kbd)>/is',
            $source
        );

        $this->assertSame(
            0,
            (int) $spanCount,
            'DashboardPage.vue MUST NOT contain <span>/<kbd> with a single uppercase letter as visible text — HOTFIX-DASH-006, design-taste §9.D.'
        );
    }
}
