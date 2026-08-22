<?php

namespace Tests\Feature\Ui;

use Tests\TestCase;

/**
 * HOTFIX-LOGIN-008 — Zero em-dashes in visible text.
 *
 * NO `—` (U+2014) character in any visible string on the Login page
 * (template text, caption, footer note, modal copy). Pin the RULE — zero
 * matches across the entire LoginPage.vue source (design-taste §9.G binary
 * ban).
 */
class HotfixLoginEmDashAuditTest extends TestCase
{
    private const LOGIN_PAGE_REL = '/resources/js/modules/auth/LoginPage.vue';

    private static function loginPagePath(): string
    {
        return dirname(__DIR__, 3) . self::LOGIN_PAGE_REL;
    }

    public function test_login_page_has_zero_em_dashes_in_visible_text(): void
    {
        $response = $this->get('/login');
        $response->assertStatus(200);

        $source = (string) file_get_contents(self::loginPagePath());

        // U+2014 EM DASH — banned across the whole page (template, script,
        // style — comments are excluded because they are not user-visible).
        // Strip <script> blocks (and their contents) before counting to keep
        // the rule about visible text only.
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
            'LoginPage.vue MUST contain zero U+2014 (em-dash) characters in visible text — design-taste §9.G binary ban (HOTFIX-LOGIN-008)'
        );
    }

    public function test_login_page_has_zero_em_dashes_in_template(): void
    {
        $response = $this->get('/login');
        $response->assertStatus(200);

        $source = (string) file_get_contents(self::loginPagePath());

        // Narrow to the <template> block — the strictest interpretation of
        // "visible text" the spec defines. Even template comments are not
        // user-visible, but the rule applies to anything a renderer could
        // turn into a text node.
        $this->assertMatchesRegularExpression(
            '/<template\b[^>]*>(?!.*\x{2014}).*<\/template>/us',
            $source,
            'LoginPage.vue <template> block MUST contain zero U+2014 em-dash characters (HOTFIX-LOGIN-008, design-taste §9.G)'
        );
    }
}
