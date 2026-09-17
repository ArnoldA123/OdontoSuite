<?php

namespace Tests\Feature\Ui;

use Tests\TestCase;

/**
 * HOTFIX-LOGIN-005 (superseded 2026-09) - login form surface.
 *
 * HOTFIX-LOGIN-005 required an elevated <Card> around the login form. The
 * premium craft pass deleted that wrapper: it stacked a second bordered,
 * shadowed surface inside the white split-card panel, so the form read as a
 * form in a box inside a box. The rules that survive the deletion are the
 * ones asserted below: no glass surface on the login, no <Card> consumer at
 * all, and a surface elevation that still resolves through a tokenised rung.
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

    public function test_login_form_is_not_wrapped_in_a_card_surface(): void
    {
        $response = $this->get('/login');
        $response->assertStatus(200);

        $source = (string) file_get_contents(self::loginPagePath());

        // Premium craft pass (2026-09): the <Card variant="elevated"> wrapper
        // sat inside the white split-card panel, so the form was a form in a
        // box inside a box. The form now rests directly on the panel and the
        // panel padding owns the inset instead.
        $this->assertStringNotContainsString(
            '<Card',
            $source,
            'LoginPage.vue must not wrap the login form in a <Card>: the form rests directly on the split-card panel (premium craft pass, supersedes HOTFIX-LOGIN-005)'
        );
        $this->assertStringNotContainsString(
            "from '@/components/ui/Card.vue'",
            $source,
            'LoginPage.vue must not import Card.vue any more: a card inside a card is the defect this pass removed'
        );
    }

    public function test_login_surfaces_reference_elevation_2_token(): void
    {
        $response = $this->get('/login');
        $response->assertStatus(200);

        $source = (string) file_get_contents(self::loginPagePath());

        // The login surfaces must resolve their elevation through a tokenised
        // rung: the glass overlay widgets on the hero, or the submit button.
        // (The form Card that used to carry this contract is gone.)
        $hasWidgetElevation = preg_match(
            '/\.login-overlay-card\s*\{[^}]*box-shadow\s*:[^;}]*var\(--elevation-[23]\b[^;}]*[;}]/is',
            $source
        );
        $hasDeepButtonShadow = preg_match(
            '/\.login-form\s*:deep\([^)]*\)[\s\S]{0,200}?box-shadow\s*:[^;}]*var\(--elevation-[23]\b/i',
            $source
        );

        $this->assertTrue(
            (bool) ($hasWidgetElevation || $hasDeepButtonShadow),
            'LoginPage.vue must compute a box-shadow referencing var(--elevation-2) or higher for its surfaces (HOTFIX-LOGIN-005, apple-design §12 - bigger surfaces read thicker)'
        );
    }
}
