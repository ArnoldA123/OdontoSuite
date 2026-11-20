<?php

namespace Tests\Feature\Ui;

use App\Models\User;
use Tests\TestCase;

/**
 * HOTFIX-DASH-011 — Zero em-dashes in visible text.
 *
 * NO `—` (U+2014) character in any visible string on the Dashboard page
 * (template text, caption, footer note, modal copy). Pin the RULE: zero
 * matches across the entire DashboardPage.vue source. (design-taste §9.G
 * binary ban — em-dash is completely banned, no "limited use" allowance.)
 */
class HotfixDashboardEmDashAuditTest extends TestCase
{
    private const DASHBOARD_PAGE_REL = '/resources/js/modules/dashboard/DashboardPage.vue';

    private static function dashboardPagePath(): string
    {
        return dirname(__DIR__, 3).self::DASHBOARD_PAGE_REL;
    }

    public function test_dashboard_page_has_zero_em_dashes_in_visible_text(): void
    {
        $user = User::factory()->make();
        $response = $this->actingAs($user)->get('/dashboard');
        $response->assertStatus(200);

        $this->assertFileExists(
            self::dashboardPagePath(),
            'DashboardPage.vue must exist (HOTFIX-DASH-011 source boundary)'
        );

        $source = (string) file_get_contents(self::dashboardPagePath());

        // U+2014 EM DASH — banned across the whole page. Strip <script>
        // blocks (and their contents) before counting so the rule stays
        // about visible text only.
        $visibleSource = (string) preg_replace(
            '/<script\b[^>]*>.*?<\/script>/is',
            '',
            $source
        );

        // Multi-byte safe count of U+2014.
        $emDashCount = preg_match_all('/\x{2014}/u', $visibleSource);

        $this->assertSame(
            0,
            (int) $emDashCount,
            'DashboardPage.vue MUST contain zero U+2014 (em-dash) characters in visible text — design-taste §9.G binary ban (HOTFIX-DASH-011).'
        );
    }

    public function test_dashboard_page_has_zero_em_dashes_in_template_block(): void
    {
        $user = User::factory()->make();
        $response = $this->actingAs($user)->get('/dashboard');
        $response->assertStatus(200);

        $source = (string) file_get_contents(self::dashboardPagePath());

        // Narrow to the <template> block — the strictest interpretation of
        // "visible text" the spec defines. Even template comments are not
        // user-visible, but the rule applies to anything a renderer could
        // turn into a text node.
        $this->assertMatchesRegularExpression(
            '/<template\b[^>]*>(?!.*\x{2014}).*<\/template>/us',
            $source,
            'DashboardPage.vue <template> block MUST contain zero U+2014 em-dash characters — HOTFIX-DASH-011, design-taste §9.G (binary ban, zero exceptions).'
        );
    }

    /**
     * T5 / U2 — the shared section-state surface follows the same file-wide
     * em-dash ban as DashboardPage.vue. `DashboardSectionEmpty.vue` retired
     * with the dashboard lists, so the guard audits the surviving shared
     * pieces: the section error still consumed by Business Intelligence and
     * the empty-state primitive that owns the anatomy.
     */
    public function test_dashboard_section_state_components_have_zero_em_dashes(): void
    {
        $user = User::factory()->make();
        $response = $this->actingAs($user)->get('/dashboard');
        $response->assertStatus(200);

        $files = [
            'resources/js/modules/dashboard/DashboardSectionError.vue',
            'resources/js/components/ui/UiSectionEmpty.vue',
        ];

        foreach ($files as $relative) {
            $path = dirname(__DIR__, 3).'/'.$relative;
            $this->assertFileExists(
                $path,
                "{$relative} must exist (T5 shared section-state pattern, HOTFIX-DASH-011 boundary)."
            );

            $source = (string) file_get_contents($path);
            $emDashCount = preg_match_all('/\x{2014}/u', $source);

            $this->assertSame(
                0,
                (int) $emDashCount,
                "{$relative} MUST contain zero U+2014 (em-dash) characters anywhere in the file — HOTFIX-DASH-011, design-taste §9.G (binary ban, zero exceptions)."
            );
        }

        $this->assertFileDoesNotExist(
            dirname(__DIR__, 3).'/resources/js/modules/dashboard/DashboardSectionEmpty.vue',
            'DashboardSectionEmpty.vue must stay retired: its dashboard consumers are gone (U2).'
        );
    }
}
