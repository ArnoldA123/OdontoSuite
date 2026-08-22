<?php

namespace Tests\Feature\Ui;

use Tests\TestCase;

/**
 * HOTFIX-LOGIN-002 — Headline optical sizing at display scale.
 *
 * At viewport ≥ 1024px the <h1 id="login-headline"> MUST compute a
 * letter-spacing of `-0.025em × font-size` or tighter (i.e. more negative).
 * Pin the RULE — the test computes the px equivalent of `-0.025em ×
 * font-size` and asserts the declared letter-spacing is ≤ that value.
 *
 * The spec pins `letter-spacing: 0` as FORBIDDEN and the headline as a
 * display-scale element (apple-design §15).
 */
class HotfixLoginHeadlineTrackingTest extends TestCase
{
    private const LOGIN_PAGE_REL = '/resources/js/modules/auth/LoginPage.vue';

    private static function loginPagePath(): string
    {
        return dirname(__DIR__, 3) . self::LOGIN_PAGE_REL;
    }

    /**
     * Extract the declared letter-spacing of .welcome-headline in em units.
     * Returns null if the rule cannot be located.
     */
    private static function headlineLetterSpacingEm(string $source): ?float
    {
        // Capture only the .welcome-headline block until the closing brace.
        if (!preg_match('/\.welcome-headline\s*\{([^}]+)\}/s', $source, $m)) {
            return null;
        }
        $block = $m[1];
        if (!preg_match('/letter-spacing\s*:\s*(-?[0-9]*\.?[0-9]+)\s*em/i', $block, $lm)) {
            return null;
        }
        return (float) $lm[1];
    }

    /**
     * Extract the declared font-size of .welcome-headline. Tailwind utility
     * classes (text-3xl / text-4xl) are resolved via a fixed table because
     * the runtime viewport (1440px) maps text-3xl to 1.875rem (30px) and
     * text-4xl to 2.25rem (36px); the @media (min-width: 640px) sm: variant
     * resolves text-3xl sm:text-4xl → 36px at the 1440 viewport.
     */
    private static function headlineFontSizePx(string $source): ?float
    {
        if (!preg_match('/\.welcome-headline\s*\{([^}]+)\}/s', $source, $m)) {
            return null;
        }
        $block = $m[1];

        // Direct font-size declaration wins.
        if (preg_match('/font-size\s*:\s*([0-9]*\.?[0-9]+)\s*(px|rem|em)/i', $block, $fm)) {
            $value = (float) $fm[1];
            $unit = strtolower($fm[2]);
            if ($unit === 'rem' || $unit === 'em') {
                return $value * 16.0;
            }
            return $value;
        }

        // Tailwind text-3xl sm:text-4xl utility at viewport ≥ sm (640px).
        // 1.875rem / 2.25rem respectively — sm: wins at 1440.
        if (preg_match('/text-3xl\s+sm:text-4xl|sm:text-4xl/', $block)) {
            return 36.0; // text-4xl = 2.25rem
        }
        if (preg_match('/\btext-4xl\b/', $block)) {
            return 36.0;
        }
        if (preg_match('/\btext-3xl\b/', $block)) {
            return 30.0;
        }
        return null;
    }

    public function test_headline_letter_spacing_is_at_least_minus_0_025em_at_1440_viewport(): void
    {
        $response = $this->get('/login');
        $response->assertStatus(200);

        $source = (string) file_get_contents(self::loginPagePath());

        $letterSpacingEm = self::headlineLetterSpacingEm($source);
        $fontSizePx = self::headlineFontSizePx($source);

        $this->assertNotNull(
            $letterSpacingEm,
            'LoginPage.vue must declare letter-spacing on .welcome-headline (HOTFIX-LOGIN-002, apple-design §15 — letter-spacing: 0 is FORBIDDEN at display scale)'
        );
        $this->assertNotNull(
            $fontSizePx,
            'LoginPage.vue must declare a resolvable font-size on .welcome-headline (HOTFIX-LOGIN-002)'
        );

        // The threshold in pixels: -0.025em × font-size at 16px root.
        $rootFontSize = 16.0;
        $thresholdPx = -0.025 * $fontSizePx;

        // Convert the declared em value into px for comparison.
        $declaredPx = $letterSpacingEm * $rootFontSize;

        $this->assertLessThanOrEqual(
            $thresholdPx,
            $declaredPx,
            sprintf(
                'At 1440 viewport, .welcome-headline letter-spacing must be ≤ -0.025em × font-size. Declared: %.4fem (%.2fpx). Threshold: ≤ %.4fpx. (HOTFIX-LOGIN-002, apple-design §15)',
                $letterSpacingEm,
                $declaredPx,
                $thresholdPx
            )
        );
    }

    public function test_headline_letter_spacing_is_not_zero(): void
    {
        $response = $this->get('/login');
        $response->assertStatus(200);

        $source = (string) file_get_contents(self::loginPagePath());

        $letterSpacingEm = self::headlineLetterSpacingEm($source);

        $this->assertNotNull(
            $letterSpacingEm,
            'LoginPage.vue must declare letter-spacing on .welcome-headline (HOTFIX-LOGIN-002)'
        );

        $this->assertNotEquals(
            0.0,
            $letterSpacingEm,
            'LoginPage.vue MUST NOT use letter-spacing: 0 on .welcome-headline — display-scale typography forbids it (HOTFIX-LOGIN-002, apple-design §15)'
        );
    }
}
