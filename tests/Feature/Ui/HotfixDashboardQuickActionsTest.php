<?php

namespace Tests\Feature\Ui;

use App\Models\User;
use Tests\TestCase;

/**
 * HOTFIX-DASH-006 — Quick Actions NO letter-key badge.
 *
 * Quick Actions cards MUST NOT contain a <span> or <kbd> element displaying
 * a single uppercase letter as a shortcut badge. Pin the RULE: zero
 * <span>/<kbd> with single-uppercase-letter visible text on Quick Action
 * cards. (design-taste §9.D "NO three-equal Material cards" — the letter
 * shortcut reference visual is the Material keyboard-shortcut chip, which
 * is the LLM-default Material-leak signature.)
 */
class HotfixDashboardQuickActionsTest extends TestCase
{

    private const DASHBOARD_PAGE_REL = '/resources/js/modules/dashboard/DashboardPage.vue';

    private static function dashboardPagePath(): string
    {
        return dirname(__DIR__, 3) . self::DASHBOARD_PAGE_REL;
    }

    public function test_quick_action_cards_have_no_kbd_shortcut_badge(): void
    {
        $user = User::factory()->make();
        $response = $this->actingAs($user)->get('/dashboard');
        $response->assertStatus(200);

        $this->assertFileExists(
            self::dashboardPagePath(),
            'DashboardPage.vue must exist (HOTFIX-DASH-006 source boundary)'
        );

        $source = (string) file_get_contents(self::dashboardPagePath());

        // Pin the RULE: zero <kbd> elements on Quick Action cards.
        // The kbd element is the canonical Material keyboard-shortcut
        // chip — banned as a Quick Action affordance per the spec.
        $kbdCount = preg_match_all(
            '/data-action\s*=\s*[\'"][^\'"]+[\'"][\s\S]*?<kbd\b[^>]*>\s*[A-Za-z]\s*<\/kbd>/is',
            $source
        );

        $this->assertSame(
            0,
            (int) $kbdCount,
            'DashboardPage.vue Quick Action cards MUST NOT contain <kbd> shortcut badges — HOTFIX-DASH-006, design-taste §9.D (no Material keyboard-shortcut reference visual).'
        );
    }

    public function test_no_span_with_single_uppercase_letter_on_quick_action_card(): void
    {
        $user = User::factory()->make();
        $response = $this->actingAs($user)->get('/dashboard');
        $response->assertStatus(200);

        $source = (string) file_get_contents(self::dashboardPagePath());

        // Belt-and-suspenders: zero <span>/<kbd> with a single-uppercase-
        // letter text content anywhere inside a Quick Action card block
        // (data-action="..." through next </UiCard> or end-of-source).
        $spanCount = preg_match_all(
            '/data-action\s*=\s*[\'"][^\'"]+[\'"][\s\S]*?<(?:span|kbd)\b[^>]*>\s*[A-Z]\s*<\/(?:span|kbd)>/is',
            $source
        );

        $this->assertSame(
            0,
            (int) $spanCount,
            'DashboardPage.vue MUST NOT contain <span>/<kbd> with a single uppercase letter as visible text on Quick Action cards — HOTFIX-DASH-006, design-taste §9.D (no Material keyboard-shortcut reference visual).'
        );
    }
}