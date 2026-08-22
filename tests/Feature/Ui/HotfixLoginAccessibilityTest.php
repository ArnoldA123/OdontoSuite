<?php

namespace Tests\Feature\Ui;

use Tests\TestCase;

/**
 * HOTFIX-LOGIN-009 — Reduced-transparency + High-contrast legibility.
 *
 * The login page MUST honor:
 *   - prefers-reduced-motion: reduce (BOTH form and hero collapse to instant)
 *   - prefers-reduced-transparency: reduce (hero overlay becomes solid, no alpha)
 *   - prefers-contrast: more (text contrast lifts to var(--color-label-label))
 *
 * Pin the RULES — the page must actively neutralize transparency and lift
 * text contrast under these media queries (apple-design §14).
 */
class HotfixLoginAccessibilityTest extends TestCase
{
    private const LOGIN_PAGE_REL = '/resources/js/modules/auth/LoginPage.vue';

    private static function loginPagePath(): string
    {
        return dirname(__DIR__, 3) . self::LOGIN_PAGE_REL;
    }

    public function test_reduced_motion_neutralizes_form_and_hero_transforms(): void
    {
        $response = $this->get('/login');
        $response->assertStatus(200);

        $source = (string) file_get_contents(self::loginPagePath());

        // The form-wrap MUST be neutralized.
        $this->assertMatchesRegularExpression(
            '/@media\s*\(\s*prefers-reduced-motion\s*:\s*reduce\s*\)\s*\{[^}]*\.login-form-wrap[^}]*transform\s*:\s*none/s',
            $source,
            'prefers-reduced-motion must neutralize .login-form-wrap transform (HOTFIX-LOGIN-009, apple-design §14)'
        );

        // The hero column MUST also be neutralized so the spring entrance
        // does not run for users who opted out of motion.
        $this->assertMatchesRegularExpression(
            '/@media\s*\(\s*prefers-reduced-motion\s*:\s*reduce\s*\)\s*\{[^}]*\.login-hero-column[^}]*(?:transform\s*:\s*none|opacity\s*:\s*1)/s',
            $source,
            'prefers-reduced-motion must neutralize .login-hero-column (HOTFIX-LOGIN-009, apple-design §14)'
        );
    }

    public function test_reduced_transparency_flattens_hero_overlay_to_solid(): void
    {
        $response = $this->get('/login');
        $response->assertStatus(200);

        $source = (string) file_get_contents(self::loginPagePath());

        // The hero overlay under reduced-transparency MUST be a SOLID color
        // (no alpha). Pin the RULE: the declaration inside the media query
        // must not contain "rgba(" with a fractional alpha nor the modern
        // slash notation "rgb(... / 0.x)".
        if (!preg_match(
            '/@media\s*\(\s*prefers-reduced-transparency\s*:\s*reduce\s*\)\s*\{([^}]+)\}/s',
            $source,
            $m
        )) {
            $this->fail('LoginPage.vue must declare @media (prefers-reduced-transparency: reduce) — HOTFIX-LOGIN-009');
        }

        $block = $m[1];

        // Reject rgba() with any alpha < 1.
        $this->assertSame(
            0,
            preg_match('/rgba\s*\([^)]*,\s*(?:0?\.[0-9]+|0)\s*\)/i', $block),
            'prefers-reduced-transparency hero overlay must NOT use rgba() with fractional alpha — flatten to solid (HOTFIX-LOGIN-009, apple-design §14)'
        );
        // Reject rgb(... / 0.x) modern syntax with fractional alpha.
        $this->assertSame(
            0,
            preg_match('/rgb[a]?\s*\([^)]*\/\s*(?:0?\.[0-9]+|0)\s*\)/i', $block),
            'prefers-reduced-transparency hero overlay must NOT use rgb(... / 0.x) fractional alpha — flatten to solid (HOTFIX-LOGIN-009, apple-design §14)'
        );

        // The block MUST declare a background value (the flattened overlay).
        $this->assertMatchesRegularExpression(
            '/background\s*:/i',
            $block,
            'prefers-reduced-transparency block must declare a solid background for the hero overlay (HOTFIX-LOGIN-009)'
        );
    }

    public function test_high_contrast_lifts_headline_to_label_color(): void
    {
        $response = $this->get('/login');
        $response->assertStatus(200);

        $source = (string) file_get_contents(self::loginPagePath());

        // Pin the RULE: under prefers-contrast: more, the headline MUST
        // resolve to var(--color-label-label) — the highest-contrast label
        // token in the system. This guarantees legibility for users who
        // request more contrast at the OS level.
        if (!preg_match(
            '/@media\s*\(\s*prefers-contrast\s*:\s*more\s*\)\s*\{([^}]+)\}/s',
            $source,
            $m
        )) {
            $this->fail('LoginPage.vue must declare @media (prefers-contrast: more) — HOTFIX-LOGIN-009');
        }

        $block = $m[1];

        $this->assertMatchesRegularExpression(
            '/\.welcome-headline\s*\{[^}]*color\s*:\s*var\(--color-label-label\)/is',
            $block,
            'prefers-contrast: more must lift .welcome-headline color to var(--color-label-label) for AAA legibility (HOTFIX-LOGIN-009, apple-design §14)'
        );

        $this->assertMatchesRegularExpression(
            '/\.welcome-subtitle\s*\{[^}]*color\s*:\s*var\(--color-label-(?:label|secondary-label)\)/is',
            $block,
            'prefers-contrast: more must lift .welcome-subtitle color to a label token for AAA legibility (HOTFIX-LOGIN-009, apple-design §14)'
        );
    }
}
