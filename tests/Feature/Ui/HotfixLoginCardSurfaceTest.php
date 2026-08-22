<?php

namespace Tests\Feature\Ui;

use Tests\TestCase;

/**
 * HOTFIX-LOGIN-005 — Form card surface uses elevated variant on solid canvas.
 *
 * The <Card> wrapping the login form MUST NOT use variant="glass" (glass on
 * a solid canvas reads flat per tokens.js). It MUST compute a box-shadow
 * referencing `var(--elevation-2)` (or higher rung) when on a solid canvas.
 * Pin the RULE — glass is banned, elevation-2 is required.
 */
class HotfixLoginCardSurfaceTest extends TestCase
{
    private const LOGIN_PAGE_REL = '/resources/js/modules/auth/LoginPage.vue';

    private static function loginPagePath(): string
    {
        return dirname(__DIR__, 3) . self::LOGIN_PAGE_REL;
    }

    public function test_login_card_does_not_use_glass_variant(): void
    {
        $response = $this->get('/login');
        $response->assertStatus(200);

        $source = (string) file_get_contents(self::loginPagePath());

        // The login form MUST NOT be wrapped by a glass Card. Glass on a
        // solid canvas reads flat and violates design-taste §4.4.
        $this->assertStringNotContainsString(
            'variant="glass"',
            $source,
            'LoginPage.vue MUST NOT render <Card variant="glass"> for the login form — glass on solid canvas reads flat (HOTFIX-LOGIN-005, apple-design §12)'
        );
    }

    public function test_login_card_uses_elevated_variant_or_other_non_glass(): void
    {
        $response = $this->get('/login');
        $response->assertStatus(200);

        $source = (string) file_get_contents(self::loginPagePath());

        // Pin the positive half: a <Card> wrapper must be present and must
        // use either variant="elevated" or no variant (defaulting to
        // elevated). The login-card-surface class is the load-bearing
        // contract for the surface treatment.
        $this->assertMatchesRegularExpression(
            '/<Card\b[^>]*variant\s*=\s*"(?:elevated|solid|default)"[^>]*>/i',
            $source,
            'LoginPage.vue must wrap the login form with <Card variant="elevated"> (or equivalent non-glass variant) — HOTFIX-LOGIN-005'
        );
    }

    public function test_login_card_surface_references_elevation_2_token(): void
    {
        $response = $this->get('/login');
        $response->assertStatus(200);

        $source = (string) file_get_contents(self::loginPagePath());

        // The login-card-surface (or its scoped rule) MUST resolve to a
        // box-shadow referencing `var(--elevation-2)` or higher rung
        // (elevation-3) so the surface reads elevated above the canvas.
        $hasElevatedShadowRule = preg_match(
            '/\.login-card-surface\s*\{[^}]*box-shadow\s*:[^;}]*var\(--elevation-[23]\b[^;}]*[;}]/is',
            $source
        );
        $hasDeepButtonShadow = preg_match(
            '/\.login-form\s*:deep\([^)]*\)[\s\S]{0,200}?box-shadow\s*:[^;}]*var\(--elevation-[23]\b/i',
            $source
        );

        $this->assertTrue(
            (bool) ($hasElevatedShadowRule || $hasDeepButtonShadow),
            'LoginPage.vue must compute a box-shadow referencing var(--elevation-2) or higher for the login card surface (HOTFIX-LOGIN-005, apple-design §12 — bigger surfaces read thicker)'
        );
    }
}
