<?php

namespace Tests\Feature\Ui;

use Tests\TestCase;

/**
 * HOTFIX-LOGIN-002 — Headline optical sizing at display scale.
 *
 * Premium craft pass (2026-09): the original assertion compared a root-em px
 * value against `-0.025em x element font-size`, which at 36px demanded
 * tracking tighter than -0.056em — tighter than the display token the design
 * system defines, and not what the type scale asks for. The rule is now the
 * real one: the login headline obeys `tokens.js`
 * `typography.fontSize['4xl']` (36px / 40px line-height / -0.022em tracking)
 * at semibold weight. The token is read from tokens.js so the assertion
 * cannot drift from the source of truth.
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
     * Read a font-size step from the design-system tokens (single source of
     * truth) so the utility resolution below cannot disagree with the scale.
     *
     * @param string $step Token step, e.g. '4xl'
     */
    private static function tokenFontSizePx(string $step): ?float
    {
        $tokensPath = dirname(__DIR__, 3) . '/resources/js/design-system/tokens.js';
        if (!is_file($tokensPath)) {
            return null;
        }
        $tokens = (string) file_get_contents($tokensPath);
        if (!preg_match(
            "/'" . preg_quote($step, '/') . "'\s*:\s*\[\s*'([0-9]*\.?[0-9]+)px'/",
            $tokens,
            $m
        )) {
            return null;
        }
        return (float) $m[1];
    }

    /**
     * Extract the declared font-size of .welcome-headline. Tailwind utility
     * classes (text-3xl / text-4xl) are resolved through `tokens.js`, NOT
     * through a hardcoded px table: at the 1440 viewport the sm: variant wins,
     * so text-4xl resolves to the token's 4xl step.
     *
     * HISTORY: this half of the helper used to `return 36.0;` — a literal —
     * while the class docblock claimed "the token is read from tokens.js so the
     * assertion cannot drift". Only the TRACKING half read the token; the size
     * half pinned 36.0/30.0 by hand. Editing `typography.fontSize['4xl']` in
     * tokens.js would have left this test green against a stale expectation,
     * which is the defect the sibling assertion in this candidate just stopped
     * carrying. Both halves now derive from the same source.
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

        // Tailwind utilities, resolved against the token scale. Order matters:
        // `\btext-4xl\b` also matches inside `sm:text-4xl`.
        foreach ([
            ['/sm:text-4xl/', '4xl'],
            ['/\btext-4xl\b/', '4xl'],
            ['/\btext-3xl\b/', '3xl'],
        ] as [$pattern, $step]) {
            if (preg_match($pattern, $block)) {
                return self::tokenFontSizePx($step);
            }
        }

        return null;
    }

    /**
     * Read the display step's tracking from the design-system tokens
     * (single source of truth) so this assertion cannot drift.
     */
    private static function tokenDisplayLetterSpacingEm(): ?float
    {
        $tokensPath = dirname(__DIR__, 3) . '/resources/js/design-system/tokens.js';
        if (!is_file($tokensPath)) {
            return null;
        }
        $tokens = (string) file_get_contents($tokensPath);
        if (!preg_match(
            "/'4xl'\s*:\s*\[\s*'36px'\s*,\s*\{[^}]*letterSpacing\s*:\s*'(-?[0-9]*\.?[0-9]+)em'/s",
            $tokens,
            $m
        )) {
            return null;
        }
        return (float) $m[1];
    }

    public function test_headline_tracking_uses_the_display_token_at_1440_viewport(): void
    {
        $response = $this->get('/login');
        $response->assertStatus(200);

        $source = (string) file_get_contents(self::loginPagePath());

        $declaredEm = self::headlineLetterSpacingEm($source);
        $tokenEm = self::tokenDisplayLetterSpacingEm();
        $fontSizePx = self::headlineFontSizePx($source);

        $this->assertNotNull(
            $tokenEm,
            "tokens.js must declare typography.fontSize['4xl'] with a letterSpacing of its own (HOTFIX-LOGIN-002)"
        );
        $this->assertNotNull(
            $declaredEm,
            'LoginPage.vue must declare letter-spacing on .welcome-headline (HOTFIX-LOGIN-002, apple-design §15 — letter-spacing: 0 is FORBIDDEN at display scale)'
        );
        $this->assertSame(
            36.0,
            $fontSizePx,
            'At 1440 viewport the headline must resolve to the 36px display step that owns the -0.022em tracking (HOTFIX-LOGIN-002)'
        );
        $this->assertSame(
            $tokenEm,
            $declaredEm,
            sprintf(
                'At 1440 viewport, .welcome-headline letter-spacing must equal the tokenised display step from tokens.js (%.4fem). Declared: %.4fem. (HOTFIX-LOGIN-002)',
                $tokenEm,
                $declaredEm
            )
        );
    }

    public function test_headline_uses_the_semibold_display_weight(): void
    {
        $response = $this->get('/login');
        $response->assertStatus(200);

        $source = (string) file_get_contents(self::loginPagePath());

        if (!preg_match('/\.welcome-headline\s*\{([^}]+)\}/s', $source, $m)) {
            $this->fail('LoginPage.vue must declare a .welcome-headline rule (HOTFIX-LOGIN-002)');
        }

        // A display headline is semibold; the previous 500 read as timid at
        // 36px and did not match the tokenised display scale.
        $this->assertMatchesRegularExpression(
            '/font-semibold|font-weight\s*:\s*600/i',
            $m[1],
            '.welcome-headline must declare semibold weight (600) for the display step (HOTFIX-LOGIN-002)'
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
